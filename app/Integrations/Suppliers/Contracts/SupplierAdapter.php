<?php

namespace App\Integrations\Suppliers\Contracts;

/**
 * Common contract every supplier integration must implement — DHRU Classic
 * API v6.1, GSM Theme API, DHRU Fusion Pro REST API, and the GSMSS
 * Fusion-compatible / REST v1 API (master prompt §3).
 *
 * Rules that bind every implementation of this contract:
 *  - If a supplier does not document/support a capability, return
 *    SupplierResult::notSupported() — never fabricate a success.
 *  - A production ("live") adapter must only call documented endpoints with
 *    documented auth. Never guess an endpoint or response shape.
 *  - Never return a fake success response from a production adapter.
 */
interface SupplierAdapter
{
    public function testConnection(): SupplierResult;

    public function getAccountInfo(): SupplierResult;

    /** @return SupplierResult with data['services'] = array of raw supplier service rows */
    public function getServices(): SupplierResult;

    public function getServiceDetails(string $supplierServiceRef): SupplierResult;

    /** @param array $payload service ref + customer-entered fields (IMEI, serial, etc.) */
    public function placeOrder(array $payload): SupplierResult;

    public function getOrderStatus(string $supplierReference): SupplierResult;

    /** @param string[] $supplierReferences */
    public function getBulkOrderStatus(array $supplierReferences): SupplierResult;

    /** True only if this adapter can genuinely perform the named capability. */
    public function supports(string $capability): bool;
}
