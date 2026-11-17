<?php

namespace App\Integrations\Suppliers\FusionPro;

use App\Integrations\Suppliers\Contracts\SupplierAdapter;
use App\Integrations\Suppliers\Contracts\SupplierResult;
use RuntimeException;

/**
 * LIVE DHRU Fusion Pro REST API adapter — architecture/interface only. See
 * DhruClassicAdapter's class docblock; the same rule applies. Implement
 * against the official Fusion Pro REST API documentation supplied by the
 * project owner.
 */
class FusionProAdapter implements SupplierAdapter
{
    private function notImplemented(string $method): never
    {
        throw new RuntimeException(
            "FusionProAdapter::{$method}() is not implemented. Supply the official DHRU Fusion Pro REST API ".
            'documentation and credentials, then implement this method against it. Never fabricate a response.'
        );
    }

    public function testConnection(): SupplierResult { $this->notImplemented(__FUNCTION__); }
    public function getAccountInfo(): SupplierResult { $this->notImplemented(__FUNCTION__); }
    public function getServices(): SupplierResult { $this->notImplemented(__FUNCTION__); }
    public function getServiceDetails(string $supplierServiceRef): SupplierResult { $this->notImplemented(__FUNCTION__); }
    public function placeOrder(array $payload): SupplierResult { $this->notImplemented(__FUNCTION__); }
    public function getOrderStatus(string $supplierReference): SupplierResult { $this->notImplemented(__FUNCTION__); }
    public function getBulkOrderStatus(array $supplierReferences): SupplierResult { $this->notImplemented(__FUNCTION__); }
    public function supports(string $capability): bool { return false; }
}
