<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class LivewireServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Livewire::component('admin.dashboard', \App\Livewire\Admin\Dashboard::class);
        Livewire::component('admin.orders-table', \App\Livewire\Admin\OrdersTable::class);
        Livewire::component('admin.supplier-connections', \App\Livewire\Admin\SupplierConnections::class);
        Livewire::component('customer.dashboard', \App\Livewire\Customer\Dashboard::class);
        Livewire::component('customer.place-order', \App\Livewire\Customer\PlaceOrder::class);
        Livewire::component('customer.add-funds', \App\Livewire\Customer\AddFunds::class);
        Livewire::component('reseller.dashboard', \App\Livewire\Reseller\Dashboard::class);
        Livewire::component('reseller.api-keys', \App\Livewire\Reseller\ApiKeys::class);
        Livewire::component('storefront.catalog', \App\Livewire\Storefront\Catalog::class);
    }
}
