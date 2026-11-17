<?php

namespace App\Integrations\Suppliers\DhruClassic;

use App\Integrations\Suppliers\Contracts\SupplierAdapter;
use App\Integrations\Suppliers\Contracts\SupplierResult;
use RuntimeException;

/**
 * LIVE DHRU Classic API v6.1 adapter — architecture/interface only.
 *
 * Per master prompt §3: "Do NOT invent undocumented endpoints, authentication
 * methods, response formats, or capabilities. Use official documentation
 * supplied by the project owner when implementing actual integrations."
 *
 * No such documentation has been supplied yet, so every method intentionally
 * throws rather than guessing a request/response shape. Implement each
 * method against the real DHRU Classic v6.1 API docs when they are provided,
 * then flip F94_SUPPLIER_DHRU_CLASSIC_MODE=live in .env.
 */
class DhruClassicAdapter implements SupplierAdapter
{
    private function notImplemented(string $method): never
    {
        throw new RuntimeException(
            "DhruClassicAdapter::{$method}() is not implemented. Supply the official DHRU Classic API v6.1 ".
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
