<?php

namespace App\Api\Inbound\DhruCompatible;

use App\Exceptions\DuplicateOrderException;
use App\Exceptions\InsufficientBalanceException;
use App\Models\ApiCredential;
use App\Models\Order;
use App\Models\Service;
use App\Services\Orders\OrderEngine;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * DHRU Classic-compatible inbound endpoint: POST /api/index.php
 * (master prompt §4A). Action is selected by the "action" POST field, the
 * convention every DHRU-compatible reseller script already expects.
 *
 * Only the actions explicitly listed in the master prompt are implemented.
 * Anything else returns a documented error — never a fabricated success.
 */
class DhruCompatibleController
{
    public function __construct(private OrderEngine $orderEngine)
    {
    }

    public function handle(Request $request)
    {
        $action = $request->input('action');
        /** @var ApiCredential $credential */
        $credential = $request->attributes->get('api_credential');

        return match ($action) {
            'accountinfo' => $this->accountInfo($credential),
            'imeiservicelist' => $this->serviceList(),
            'placeimeiorder' => $this->placeOrder($request, $credential),
            'getimeiorder' => $this->getOrder($request, $credential),
            'placeimeiorderbulk' => $this->placeOrderBulk($request, $credential),
            'getimeiorderbulk' => $this->getOrderBulk($request, $credential),
            'serverservicelist' => $this->serviceList(), // where supported; identical catalog in Phase 2
            default => response()->json(['ERROR' => 'Unsupported or undocumented action.', 'STATUS' => 'error'], 400),
        };
    }

    private function accountInfo(ApiCredential $credential)
    {
        $wallet = $credential->user->wallet;

        return response()->json([
            'STATUS' => 'success',
            'BALANCE' => (string) $wallet->balance,
            'CURRENCY' => $wallet->currency,
            'CREDIT_LIMIT' => '0.0000',
        ]);
    }

    private function serviceList()
    {
        $services = Service::where('is_active', true)->with('category')->get(['id', 'service_category_id', 'name', 'base_price']);

        return response()->json([
            'STATUS' => 'success',
            'SERVICES' => $services->map(fn ($s) => [
                'ID' => $s->id,
                'CATEGORY' => $s->category->name,
                'NAME' => $s->name,
                'CREDIT' => (string) $s->base_price,
            ]),
        ]);
    }

    private function placeOrder(Request $request, ApiCredential $credential)
    {
        // AUDIT FIX (BUG-015, P1, Phase 2.2): the REST v1 OrderController
        // requires the `orders.create` scope before placing an order
        // (OrderController::store()); this DHRU-compatible equivalent
        // called the exact same OrderEngine::placeOrder() with no scope
        // check at all. A credential deliberately minted WITHOUT
        // `orders.create` (e.g. a read-only integration key) could not
        // place an order through /api/v1/orders, but could place one
        // through /api/index.php with the identical key — an
        // authorization bypass via the alternate API surface. Both inbound
        // APIs now enforce the same rule for the same action.
        if (! $credential->hasScope('orders.create')) {
            return response()->json(['STATUS' => 'error', 'ERROR' => 'This API key does not have the orders.create scope.'], 403);
        }

        $request->validate(['id' => ['required', 'exists:services,id'], 'imei' => ['required', 'string']]);

        $service = Service::findOrFail($request->input('id'));
        $idempotencyKey = $request->input('idempotency_key') ?: 'dhru:'.$credential->id.':'.$request->input('imei').':'.$request->input('id');

        // AUDIT FIX (BUG-003 / BUG-004, Phase 2.1) — same rationale as the
        // REST v1 OrderController: these are expected outcomes, not server
        // faults, and must come back in the documented DHRU error shape
        // rather than an unhandled exception.
        try {
            $order = $this->orderEngine->placeOrder(
                $credential->user,
                $service,
                ['imei' => $request->input('imei')],
                $idempotencyKey,
                'dhru_compatible_api',
                $credential,
            );
        } catch (ValidationException $e) {
            return response()->json(['STATUS' => 'error', 'ERROR' => $e->getMessage()], 422);
        } catch (InsufficientBalanceException $e) {
            return response()->json(['STATUS' => 'error', 'ERROR' => 'Insufficient wallet balance.'], 402);
        } catch (DuplicateOrderException $e) {
            $order = Order::where('idempotency_key', $idempotencyKey)
                ->where('user_id', $credential->user_id)
                ->first();

            if (! $order) {
                return response()->json(['STATUS' => 'error', 'ERROR' => 'Duplicate order request could not be resolved.'], 409);
            }
        }

        return response()->json([
            'STATUS' => 'success',
            'ID' => $order->id,
            'ORDERID' => $order->order_number,
            'ORDERSTATUS' => $this->toDhruStatus($order->status),
        ]);
    }

    private function getOrder(Request $request, ApiCredential $credential)
    {
        $order = Order::where('user_id', $credential->user_id)
            ->where(function ($q) use ($request) {
                $q->where('id', $request->input('id'))->orWhere('order_number', $request->input('id'));
            })->first();

        if (! $order) {
            return response()->json(['STATUS' => 'error', 'ERROR' => 'Order not found.'], 404);
        }

        return response()->json([
            'STATUS' => 'success',
            'ID' => $order->id,
            'ORDERID' => $order->order_number,
            'ORDERSTATUS' => $this->toDhruStatus($order->status),
        ]);
    }

    private function placeOrderBulk(Request $request, ApiCredential $credential)
    {
        $items = $request->input('orders', []);
        $results = [];

        foreach ($items as $item) {
            $subRequest = Request::create('', 'POST', $item);
            $results[] = $this->placeOrder($subRequest, $credential)->getData(true);
        }

        return response()->json(['STATUS' => 'success', 'RESULTS' => $results]);
    }

    private function getOrderBulk(Request $request, ApiCredential $credential)
    {
        $ids = (array) $request->input('ids', []);
        $orders = Order::where('user_id', $credential->user_id)->whereIn('id', $ids)->get();

        return response()->json([
            'STATUS' => 'success',
            'RESULTS' => $orders->map(fn ($o) => [
                'ID' => $o->id,
                'ORDERID' => $o->order_number,
                'ORDERSTATUS' => $this->toDhruStatus($o->status),
            ]),
        ]);
    }

    private function toDhruStatus(string $internal): string
    {
        return match ($internal) {
            Order::STATUS_SUCCESS => 'Completed',
            Order::STATUS_FAILED, Order::STATUS_REFUNDED => 'Rejected',
            Order::STATUS_UNKNOWN => 'In Process',
            default => 'Processing',
        };
    }
}
