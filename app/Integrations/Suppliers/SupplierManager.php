<?php

namespace App\Integrations\Suppliers;

use App\Integrations\Suppliers\Contracts\SupplierAdapter;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;

/**
 * Resolves the correct adapter (mock or live) for a supplier slug, driven by
 * config/firmware94.php. Phase 2 ships only "mock" mode; flipping to "live"
 * requires real, documented credentials (see docs/SECURITY.md) and is
 * explicitly out of scope here — the live adapter classes exist as
 * architecture/interfaces only and throw until implemented against real,
 * supplied documentation (master prompt §3).
 */
class SupplierManager
{
    public function __construct(
        private Application $app,
        private array $config,
    ) {
    }

    public function for(string $supplierSlug): SupplierAdapter
    {
        if (! isset($this->config[$supplierSlug])) {
            throw new InvalidArgumentException("Unknown supplier \"{$supplierSlug}\".");
        }

        $entry = $this->config[$supplierSlug];
        $class = $entry['mode'] === 'live' ? $entry['adapter'] : $entry['mock_adapter'];

        return $this->app->make($class);
    }

    public function supplierSlugs(): array
    {
        return array_keys($this->config);
    }
}
