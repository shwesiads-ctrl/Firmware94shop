<?php

namespace App\Integrations\Suppliers;

use App\Integrations\Suppliers\Contracts\SupplierAdapter;
use App\Integrations\Suppliers\Contracts\SupplierResult;

/**
 * Shared, deterministic simulation logic for all four mock suppliers
 * (master prompt §5 Module H). Every method is pure and uses only its
 * inputs — no randomness — so the same call always produces the same
 * result and the test suite stays deterministic.
 *
 * The caller selects a scenario either explicitly via
 * $payload['mock_scenario'] (used heavily in tests) or, failing that,
 * deterministically from the last character of the identifying value
 * (IMEI / order reference), which is how a developer can exercise every
 * path from the UI without a special test mode:
 *
 *   ...0 success   ...1 rejected   ...2 pending   ...3 processing
 *   ...4 timeout   ...5 unknown    ...6 auth_failure (testConnection only)
 *   anything else: success
 */
abstract class AbstractMockSupplier implements SupplierAdapter
{
    /** @return string[] capabilities this mock does NOT support, to exercise NotSupported handling */
    protected function unsupportedCapabilities(): array
    {
        return [];
    }

    public function supports(string $capability): bool
    {
        return ! in_array($capability, $this->unsupportedCapabilities(), true);
    }

    private function guard(string $capability): ?SupplierResult
    {
        return $this->supports($capability) ? null : SupplierResult::notSupported($capability);
    }

    public function testConnection(): SupplierResult
    {
        if ($blocked = $this->guard('testConnection')) {
            return $blocked;
        }

        return SupplierResult::success(['connected' => true, 'supplier' => static::class]);
    }

    public function getAccountInfo(): SupplierResult
    {
        if ($blocked = $this->guard('getAccountInfo')) {
            return $blocked;
        }

        return SupplierResult::success([
            'balance' => '1000.0000',
            'currency' => 'USD',
            'credit_limit' => '0.0000',
        ]);
    }

    public function getServices(): SupplierResult
    {
        if ($blocked = $this->guard('getServices')) {
            return $blocked;
        }

        return SupplierResult::success(['services' => [
            ['ref' => 'MOCK-SVC-1', 'name' => 'Mock IMEI Unlock', 'cost' => '2.5000'],
            ['ref' => 'MOCK-SVC-2', 'name' => 'Mock iCloud Clean', 'cost' => '5.0000'],
        ]]);
    }

    public function getServiceDetails(string $supplierServiceRef): SupplierResult
    {
        if ($blocked = $this->guard('getServiceDetails')) {
            return $blocked;
        }

        if (! str_starts_with($supplierServiceRef, 'MOCK-')) {
            return SupplierResult::failed('Unknown service reference.');
        }

        return SupplierResult::success(['ref' => $supplierServiceRef, 'fields' => ['imei']]);
    }

    public function placeOrder(array $payload): SupplierResult
    {
        if ($blocked = $this->guard('placeOrder')) {
            return $blocked;
        }

        $scenario = $this->resolveScenario($payload);
        $reference = 'MOCKORD-'.strtoupper(substr(md5(json_encode($payload)), 0, 10));

        return match ($scenario) {
            'rejected' => SupplierResult::failed('Supplier rejected the order (mock).', ['reference' => $reference]),
            'pending' => SupplierResult::pending(['reference' => $reference], $reference),
            'processing' => SupplierResult::processing(['reference' => $reference], $reference),
            'timeout' => SupplierResult::timeout(),
            'unknown' => SupplierResult::unknown('Ambiguous response (mock).', ['reference' => $reference]),
            default => SupplierResult::success(['reference' => $reference], $reference),
        };
    }

    public function getOrderStatus(string $supplierReference): SupplierResult
    {
        if ($blocked = $this->guard('getOrderStatus')) {
            return $blocked;
        }

        $scenario = $this->scenarioFromSuffix($supplierReference);

        return match ($scenario) {
            'rejected' => SupplierResult::failed('Order failed at supplier (mock).'),
            'pending' => SupplierResult::pending([], $supplierReference),
            'processing' => SupplierResult::processing([], $supplierReference),
            'timeout' => SupplierResult::timeout(),
            'unknown' => SupplierResult::unknown('Ambiguous status (mock).'),
            default => SupplierResult::success(['reference' => $supplierReference], $supplierReference),
        };
    }

    public function getBulkOrderStatus(array $supplierReferences): SupplierResult
    {
        if ($blocked = $this->guard('getBulkOrderStatus')) {
            return $blocked;
        }

        $results = [];

        foreach ($supplierReferences as $reference) {
            $results[$reference] = $this->getOrderStatus($reference)->toArray();
        }

        return SupplierResult::success(['results' => $results]);
    }

    private function resolveScenario(array $payload): string
    {
        if (isset($payload['mock_scenario'])) {
            return (string) $payload['mock_scenario'];
        }

        $identifier = (string) ($payload['imei'] ?? $payload['serial'] ?? $payload['reference'] ?? '0');

        return $this->scenarioFromSuffix($identifier);
    }

    private function scenarioFromSuffix(string $value): string
    {
        return match (substr($value, -1)) {
            '1' => 'rejected',
            '2' => 'pending',
            '3' => 'processing',
            '4' => 'timeout',
            '5' => 'unknown',
            default => 'success',
        };
    }
}
