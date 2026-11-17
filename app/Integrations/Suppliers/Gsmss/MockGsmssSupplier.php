<?php

namespace App\Integrations\Suppliers\Gsmss;

use App\Integrations\Suppliers\AbstractMockSupplier;

/**
 * Simulates a GSMSS Fusion-compatible / REST v1 supplier. Fake credentials
 * only — never calls a real endpoint. GSMSS is modelled here as NOT
 * supporting bulk order status, to exercise the NotSupported path (keep in
 * sync with database/seeders/SupplierSeeder.php).
 */
class MockGsmssSupplier extends AbstractMockSupplier
{
    protected function unsupportedCapabilities(): array
    {
        return ['getBulkOrderStatus'];
    }
}
