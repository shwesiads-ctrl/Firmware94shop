<?php

namespace App\Integrations\Suppliers\Contracts;

/**
 * Uniform envelope returned by every SupplierAdapter method so the order
 * engine never has to know which supplier it is talking to.
 *
 * Status values: success | failed | pending | processing | unknown | timeout | not_supported
 *
 * "unknown" and "timeout" are distinct from "failed" on purpose — an order
 * engine must never treat an ambiguous/timed-out response the same as a
 * confirmed rejection (see master prompt §5 Module I: never auto-refund an
 * order whose supplier acceptance is unknown).
 */
final class SupplierResult
{
    private function __construct(
        public readonly string $status,
        public readonly array $data = [],
        public readonly ?string $message = null,
        public readonly ?string $supplierReference = null,
    ) {
    }

    public static function success(array $data = [], ?string $supplierReference = null, ?string $message = null): self
    {
        return new self('success', $data, $message, $supplierReference);
    }

    public static function pending(array $data = [], ?string $supplierReference = null, ?string $message = null): self
    {
        return new self('pending', $data, $message, $supplierReference);
    }

    public static function processing(array $data = [], ?string $supplierReference = null, ?string $message = null): self
    {
        return new self('processing', $data, $message, $supplierReference);
    }

    public static function failed(?string $message = null, array $data = []): self
    {
        return new self('failed', $data, $message);
    }

    public static function unknown(?string $message = null, array $data = []): self
    {
        return new self('unknown', $data, $message);
    }

    public static function timeout(?string $message = 'Supplier did not respond in time.'): self
    {
        return new self('timeout', [], $message);
    }

    /** Returned when a supplier adapter genuinely does not support a capability. Never fake a success here. */
    public static function notSupported(string $capability): self
    {
        return new self('not_supported', [], "Capability \"{$capability}\" is not supported by this supplier.");
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    public function isFinal(): bool
    {
        return in_array($this->status, ['success', 'failed'], true);
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'data' => $this->data,
            'message' => $this->message,
            'supplier_reference' => $this->supplierReference,
        ];
    }
}
