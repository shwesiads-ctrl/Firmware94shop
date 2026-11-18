<?php

namespace App\Services\Orders;

use App\Integrations\Suppliers\Contracts\SupplierResult;
use App\Integrations\Suppliers\SupplierManager;
use App\Models\ApiCredential;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Services\Pricing\PricingService;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Reserve the wallet once per order; never re-submit a replay to the supplier.
 * Ambiguous supplier results require reconciliation/manual review, not refund.
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
        if ($user->isSuspended()) {
            throw ValidationException::withMessages(['user' => 'This account is suspended.']);
        }

        if ($idempotencyKey === '' || strlen($idempotencyKey) > 191) {
            throw ValidationException::withMessages(['idempotency_key' => 'Invalid idempotency key.']);
        }

        if ($existing = Order::where('idempotency_key', $idempotencyKey)->first()) {
            return $this->validateReplay($existing, $user, $service, $inputs, $placedVia);
        }

        if (! $service->is_active) {
            throw ValidationException::withMessages(['service' => 'This service is unavailable.']);
        }

        $this->validateInputs($service, $inputs);

        // Base prices are denominated in the configured currency. Until an
        // explicit FX conversion is implemented, never debit another currency.
        $wallet = $user->wallet;
        $currency = strtoupper((string) config('firmware94.currency', 'USD'));
        if (! $wallet || strtoupper((string) $wallet->currency) !== $currency) {
            throw ValidationException::withMessages(['wallet' => 'Wallet currency mismatch; a verified FX conversion is required.']);
        }

        $price = $this->pricing->priceFor($service, $user);
        if (! is_string($price) || ! is_numeric($price) || bccomp($price, '0', 4) < 0) {
            throw ValidationException::withMessages(['price' => 'Invalid service price.']);
        }

        $created = false;
        $order = DB::transaction(function () use ($user, $service, $inputs, $idempotencyKey, $price, $currency, $placedVia, $apiCredential, &$created) {
            if ($existing = Order::where('idempotency_key', $idempotencyKey)->lockForUpdate()->first()) {
                return $this->validateReplay($existing, $user, $service, $inputs, $placedVia);
            }

            $order = Order::create([
                'user_id' => $user->id,
                'service_id' => $service->id,
                'price_charged' => $price,
                'currency' => $currency,
                'idempotency_key' => $idempotencyKey,
                'status' => Order::STATUS_PENDING,
                'placed_via' => $placedVia,
                'api_credential_id' => $apiCredential?->id,
            ]);

            foreach ($inputs as $key => $value) {
                $order->inputs()->create(['key' => $key, 'value' => (string) $value]);
            }
            $order->recordEvent('created', ['price' => $price, 'currency' => $currency]);

            // Free services must not create a zero-value debit ledger entry.
            if (bccomp($price, '0', 4) > 0) {
                $this->wallets->debit(
                    $user->wallet,
                    $price,
                    "order-debit:{$idempotencyKey}",
                    $order,
                    "Order {$order->order_number}"
                );
            }

            $order->update(['status' => Order::STATUS_RESERVED]);
            $order->recordEvent('wallet_reserved', ['amount' => $price]);
            $created = true;

            return $order;
        });

        // The supplier may only be called for a newly created order.
        if ($created) {
            $this->sendToSupplier($order, $service, $inputs);
        }

        return $order->refresh();
    }

    private function validateReplay(Order $order, User $user, Service $service, array $inputs, string $placedVia): Order
    {
        if ((int) $order->user_id !== (int) $user->id
            || (int) $order->service_id !== (int) $service->id
            || $order->placed_via !== $placedVia
            || $this->normalizedInputs($inputs) !== $this->normalizedInputs($order->inputs->pluck('value', 'key')->all())) {
            throw ValidationException::withMessages(['idempotency_key' => 'Key already used for a different order request.']);
        }

        return $order;
    }

    private function normalizedInputs(array $inputs): array
    {
        $normalized = [];
        foreach ($inputs as $key => $value) {
            if (! is_string($key) || (! is_string($value) && ! is_numeric($value))) {
                throw ValidationException::withMessages(['inputs' => 'Order inputs must be scalar text fields.']);
            }
            $normalized[$key] = (string) $value;
        }
        ksort($normalized);

        return $normalized;
    }

    private function sendToSupplier(Order $order, Service $service, array $inputs): void
    {
        $mapping = $service->orderedSupplierMappings()->first();

        if (! $mapping) {
            $this->resolveConfirmedSupplierResult($order, SupplierResult::failed('No active supplier mapping.'));
            return;
        }

        $supplier = $mapping->supplier;

        $order->update(['status' => Order::STATUS_SENT_TO_SUPPLIER]);
        $order->recordEvent('sent_to_supplier', ['supplier' => $supplier->slug]);

        $payload = array_merge($inputs, ['service_ref' => $mapping->supplier_service_ref]);

        try {
            $adapter = $this->suppliers->for($supplier->slug);
            $result = $adapter->placeOrder($payload);
        } catch (Throwable $e) {
            // A thrown exception may occur AFTER the supplier accepted the order.
            // Never retry or refund automatically without authoritative status.
            report($e);
            $this->markUnknown($order, 'Supplier result could not be confirmed.');
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

        if ($result->status === 'not_supported') {
            // Adapter explicitly rejected the capability without dispatching.
            $this->resolveConfirmedSupplierResult($order, SupplierResult::failed($result->message));
        } elseif ($result->isFinal()) {
            $this->resolveConfirmedSupplierResult($order, $result);
        } elseif (in_array($result->status, ['timeout', 'unknown'], true)) {
            $this->markUnknown($order, $result->message ?? 'Ambiguous supplier result.');
        } else {
            $order->update(['status' => Order::STATUS_PROCESSING]);
        }
    }

    /** Only confirmed supplier success or failure can finalize/refund an order. */
    public function resolveConfirmedSupplierResult(Order $order, SupplierResult $result): void
    {
        if (! $result->isFinal()) {
            throw new \InvalidArgumentException('Cannot finalize an unconfirmed supplier result.');
        }

        DB::transaction(function () use ($order, $result) {
            $locked = Order::lockForUpdate()->findOrFail($order->id);

            if ($locked->isFinal()) {
                return; // idempotent retry
            }

            if ($result->isSuccessful()) {
                $locked->update(['status' => Order::STATUS_SUCCESS, 'completed_at' => now()]);
                $locked->recordEvent('finalized', ['result' => 'success']);
                return;
            }

            // Confirmed rejection: wallet refund and order status commit together.
            $locked->update(['status' => Order::STATUS_FAILED, 'completed_at' => now()]);
            $locked->recordEvent('finalized', ['result' => 'failed', 'reason' => $result->message]);

            // No wallet debit exists for zero-priced services.
            if (bccomp((string) $locked->price_charged, '0', 4) > 0) {
                $this->wallets->credit(
                    $locked->user->wallet,
                    (string) $locked->price_charged,
                    "order-refund:{$locked->idempotency_key}",
                    $locked,
                    "Refund for failed order {$locked->order_number}",
                    'refund'
                );
            }
            $locked->update(['status' => Order::STATUS_REFUNDED]);
            $locked->recordEvent('refunded', ['reason' => $result->message]);
        });
    }

    private function markUnknown(Order $order, string $reason): void
    {
        $order->update(['status' => Order::STATUS_UNKNOWN]);
        $order->recordEvent('status_updated', ['result' => 'unknown', 'reason' => $reason]);
    }

    private function validateInputs(Service $service, array $inputs): void
    {
        $rules = [];
        foreach ($service->fields as $field) {
            $fieldRules = [$field->required ? 'required' : 'nullable', 'string'];
            if ($field->validation_regex) {
                $fieldRules[] = 'regex:'.$field->validation_regex;
            }
            $rules[$field->key] = $fieldRules;
        }
        Validator::make($inputs, $rules)->validate();
        $this->normalizedInputs($inputs);
    }
}
