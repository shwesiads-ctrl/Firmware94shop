<?php

namespace App\Api\Inbound\RestV1;

use App\Exceptions\DuplicateOrderException;
use App\Exceptions\InsufficientBalanceException;
use App\Models\ApiCredential;
use App\Models\Order;
use App\Models\Service;
use App\Services\Orders\OrderEngine;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrderController
{
    public function __construct(private OrderEngine $orderEngine)
    {
    }

    public function index(Request $request)
    {
        $orders = Order::where('user_id', $request->user()->id)->latest()->paginate(25);

        return response()->json($orders);
    }

    public function show(Request $request, Order $order)
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        return response()->json(['data' => $order->load('events', 'supplierOrders')]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_id' => ['required', 'exists:services,id'],
            'inputs' => ['required', 'array'],
            'idempotency_key' => ['required', 'string', 'max:191'],
        ]);

        /** @var ApiCredential|null $credential */
        $credential = $request->attributes->get('api_credential');

        if ($credential && ! $credential->hasScope('orders.create')) {
            return response()->json(['message' => 'This API key does not have the orders.create scope.'], 403);
        }

        // AUDIT FIX (BUG-003 / BUG-004, P1/P2, Phase 2.1): InsufficientBalanceException
        // and DuplicateOrderException used to propagate unhandled out of this
        // method, past OrderEngine, straight into Laravel's default exception
        // handler — a generic unhandled-error response instead of a clean,
        // documented 4xx. Both are expected, common outcomes (a customer with
        // too little balance; two near-simultaneous identical requests), not
        // server faults, so they're now handled explicitly.
        try {
            $order = $this->orderEngine->placeOrder(
                $request->user(),
                Service::findOrFail($validated['service_id']),
                $validated['inputs'],
                $validated['idempotency_key'],
                'reseller_rest_api',
                $credential,
            );
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        } catch (InsufficientBalanceException $e) {
            return response()->json(['message' => 'Insufficient wallet balance.'], 402);
        } catch (DuplicateOrderException $e) {
            // The race was lost to a concurrent request with the same key —
            // that request's order now exists; return it rather than erroring.
            $order = Order::where('idempotency_key', $validated['idempotency_key'])
                ->where('user_id', $request->user()->id)
                ->first();

            if (! $order) {
                return response()->json(['message' => 'Duplicate order request could not be resolved.'], 409);
            }
        }

        return response()->json(['data' => $order], 201);
    }
}
