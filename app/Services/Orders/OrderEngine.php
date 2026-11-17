<?php

namespace App\Services\Orders;

use App\Exceptions\DuplicateOrderException;
use App\Integrations\Suppliers\Contracts\SupplierResult;
use App\Integrations\Suppliers\SupplierManager;
use App\Models\ApiCredential;
use App\Models\Order;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Pricing\PricingService;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The internal order workflow (master prompt §5 Module I), followed exactly:
 *  1. Validate user      2. Validate service      3. Validate required inputs
 *  4. Calculate price    5. Check wallet balance   6. Create order
 *  7. Reserve/debit wallet transactionally         8. Send to mock supplier
 *  9. Save supplier reference                     10. Update order status
 *  11. Finalize or refund based on confirmed result
 *
 * Idempotency: the same $idempotencyKey always returns the same Order,
 * never creates a second one and never debits the wallet twice.
 *
 * A supplier timeout/ambiguous response moves the order to STATUS_UNKNOWN
 * and it is left there for staff/the reconciliation job — it is NEVER
 * auto-refunded, because we don't yet know whether the supplier accepted it.
 */
class OrderEngine
{
    public function __construct(
        private WalletService $wallets,
        private PricingService $pricing,
        private SupplierManager $suppliers,
    ) {
    }

    public function placeOrder(
        User $user,
        Service $service,
        array $inputs,
        string $idempotencyKey,
        string $placedVia = 'storefront',
        ?ApiCredential $apiCredential = null,
    ): Order {
        // 1. Validate user
        if ($user->isSuspended()) {
            throw ValidationException::withMessages(['user' => 'This account is suspended and cannot place orders.']);
        }

        // Idempotency: replaying the same key returns the existing order
        // untouched — but ONLY to the user who originally created it.
        //
        // AUDIT FIX (BUG-001, P0, Phase 2.1): this lookup used to match on
        // idempotency_key alone, with no ownership check. Since the REST v1
        // API lets any caller supply an arbitrary idempotency_key string
        // (see OrderController::store), a second user who submitted — or
        // simply guessed/reused — the same key as someone else's order would
        // have that other user's Order object (price, status, service,
        // order_number) returned to them. That's a cross-user information
        // disclosure via idempotency key reuse. We now fail safely with a
        // validation error instead of ever returning another user's order.
        if ($existing = Order::where('idempotency_key', $idempotencyKey)->first()) {
            if ($existing->user_id !== $user->id) {
                throw ValidationException::withMessages([
                    'idempotency_key' => 'This idempotency key is already in use by another order.',
                ]);
            }

            return $existing;
        }

        // 2. Validate service
        if (! $service->is_active) {
            throw ValidationException::withMessages(['service' => 'This service is not currently available.']);
        }

        // 3. Validate required input fields
        $this->validateInputs($service, $inputs);

        // 4. Calculate price
        $price = $this->pricing->priceFor($service, $user);

        // 5 & 6 & 7: check balance, create order, debit wallet — one DB transaction.
        $order = DB::transaction(function () use ($user, $service, $inputs, $idempotencyKey, $price, $placedVia, $apiCredential) {
            // Re-check for a race between two concurrent requests with the
            // same key (the first check above is outside this transaction).
            // Locked and scoped by user_id for the same reason as above.
            $racingOrder = Order::where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();

            if ($racingOrder) {
                if ($racingOrder->user_id !== $user->id) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => 'This idempotency key is already in use by another order.',
                    ]);
                }

                throw new DuplicateOrderException("Order with idempotency key [{$idempotencyKey}] already exists.");
            }

            $order = Order::create([
                'user_id' => $user->id,
                'service_id' => $service->id,
                'price_charged' => $price,
                'idempotency_key' => $idempotencyKey,
                'status' => Order::STATUS_PENDING,
                'placed_via' => $placedVia,
                'api_credential_id' => $apiCredential?->id,
            ]);

            foreach ($inputs as $key => $value) {
                $order->inputs()->create(['key' => $key, 'value' => (string) $value]);
            }

            $order->recordEvent('created', ['price' => $price]);

            // Wallet debit shares the order's idempotency key suffix so a
            // retried request can never double-debit even across process
            // restarts.
            $this->wallets->debit(
                $user->wallet,
                $price,
                "order-debit:{$idempotencyKey}",
                $order,
                "Order {$order->order_number}"
            );

            $order->update(['status' => Order::STATUS_RESERVED]);
            $order->recordEvent('wallet_reserved', ['amount' => $price]);

            return $order;
        });

        // 8-10: send to supplier, record reference + status outside the wallet
        // transaction (a slow/mock supplier call should not hold a DB lock).
        $this->sendToSupplier($order, $service, $inputs);

        return $order->refresh();
    }

    private function sendToSupplier(Order $order, Service $service, array $inputs): void
    {
        $mapping = $service->orderedSupplierMappings()->first();

        if (! $mapping) {
            $this->finalizeFailure($order, 'No active supplier mapping for this service.');
            return;
        }

        $supplier = $mapping->supplier;
        $adapter = $this->suppliers->for($supplier->slug);

        $order->update(['status' => Order::STATUS_SENT_TO_SUPPLIER]);
        $order->recordEvent('sent_to_supplier', ['supplier' => $supplier->slug]);

        $payload = array_merge($inputs, ['service_ref' => $mapping->supplier_service_ref]);

        // AUDIT FIX (BUG-010, P0, Phase 2.2): $adapter->placeOrder() can
        // throw — every "live" supplier stub adapter in this repo does so
        // unconditionally today (no real supplier docs yet), and any real
        // HTTP-based adapter can throw on a connection failure, timeout, or
        // malformed response. Previously nothing here caught that: the
        // wallet debit above (already committed, in its own transaction)
        // stood, the order was left at STATUS_SENT_TO_SUPPLIER forever, and
        // ReconcileUnknownSupplierOrders only ever looks at
        // STATUS_UNKNOWN orders — so a thrown exception here produced a
        // silent, permanent "funds debited, no service, no refund, not
        // even flagged for staff" state, on top of an uncaught 500 bubbling
        // to the caller. We don't know whether the supplier actually
        // received the order before it threw, so per the "never
        // auto-refund an order whose supplier acceptance is unknown" rule
        // we move it to STATUS_UNKNOWN — the same safe place a genuine
        // timeout/ambiguous response already goes — rather than guessing
        // success or failure.
        try {
            $result = $adapter->placeOrder($payload);
        } catch (\Throwable $e) {
            $order->supplierOrders()->create([
                'supplier_id' => $supplier->id,
                'supplier_reference' => null,
                'status' => 'unknown',
                'request_payload' => $payload,
                'response_payload' => ['exception' => $e->getMessage()],
                'submitted_at' => now(),
                'last_checked_at' => now(),
            ]);

            $this->markUnknown($order, 'Supplier call raised an exception before responding.');

            return;
        }

        $supplierOrder = $order->supplierOrders()->create([
            'supplier_id' => $supplier->id,
            'supplier_reference' => $result->supplierReference,
            'status' => $result->status,
            'request_payload' => $payload,
            'response_payload' => $result->toArray(),
            'submitted_at' => now(),
            'last_checked_at' => now(),
        ]);

        $this->applySupplierResult($order, $supplierOrder, $result);
    }

    private function applySupplierResult(Order $order, $supplierOrder, SupplierResult $result): void
    {
        match ($result->status) {
            'success' => $this->finalizeSuccess($order),
            'failed', 'not_supported' => $this->finalizeFailure($order, $result->message ?? 'Supplier rejected the order.'),
            'timeout', 'unknown' => $this->markUnknown($order, $result->message ?? 'Ambiguous supplier response.'),
            default => $order->update(['status' => Order::STATUS_PROCESSING]), // pending|processing: wait for reconciliation
        };
    }

    private function finalizeSuccess(Order $order): void
    {
        $order->update(['status' => Order::STATUS_SUCCESS, 'completed_at' => now()]);
        $order->recordEvent('finalized', ['result' => 'success']);
    }

    /**
     * Visibility: public (AUDIT FIX, BUG-011, P0, Phase 2.2) — was private.
     * ReconcileUnknownSupplierOrders needs to resolve a previously-unknown
     * order to a confirmed failure (with its automatic, now-safe refund)
     * through this exact same controlled path, rather than duplicating its
     * logic or — as it did before this fix — silently doing nothing. See
     * that class for the call site and the bug this closes.
     */
    public function finalizeFailure(Order $order, string $reason): void
    {
        $order->update(['status' => Order::STATUS_FAILED, 'completed_at' => now()]);
        $order->recordEvent('finalized', ['result' => 'failed', 'reason' => $reason]);

        // Supplier confirmed rejection -> safe to refund automatically.
        $this->wallets->credit(
            $order->user->wallet,
            (string) $order->price_charged,
            "order-refund:{$order->idempotency_key}",
            $order,
            "Refund for failed order {$order->order_number}",
            'refund'
        );

        $order->update(['status' => Order::STATUS_REFUNDED]);
        $order->recordEvent('refunded', ['reason' => $reason]);
    }

    /**
     * Supplier timeout or an ambiguous response. We deliberately do NOT
     * refund here — we don't know if the supplier actually accepted the
     * order. Left for ReconcileUnknownSupplierOrders / staff review.
     */
    private function markUnknown(Order $order, string $reason): void
    {
        $order->update(['status' => Order::STATUS_UNKNOWN]);
        $order->recordEvent('status_updated', ['result' => 'unknown', 'reason' => $reason]);
    }

    private function validateInputs(Service $service, array $inputs): void
    {
        $rules = [];

        foreach ($service->fields as $field) {
            $fieldRules = [$field->required ? 'required' : 'nullable'];

            if ($field->validation_regex) {
                $fieldRules[] = 'regex:'.$field->validation_regex;
            }

            $rules[$field->key] = $fieldRules;
        }

        Validator::make($inputs, $rules)->validate();
    }
}
