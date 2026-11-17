<?php

namespace App\Providers;

use App\Integrations\Suppliers\SupplierManager;
use Illuminate\Support\ServiceProvider;

class SupplierServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SupplierManager::class, function ($app) {
            return new SupplierManager($app, config('firmware94.suppliers'));
        });
    }
}
