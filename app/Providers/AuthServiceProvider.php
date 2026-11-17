<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\Wallet;
use App\Policies\OrderPolicy;
use App\Policies\SupportTicketPolicy;
use App\Policies\WalletPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Order::class => OrderPolicy::class,
        Wallet::class => WalletPolicy::class,
        SupportTicket::class => SupportTicketPolicy::class,
    ];

    public function boot(): void
    {
        // Super Admin bypasses every explicit permission check. Every other
        // role must be granted permissions explicitly (see PermissionSeeder) —
        // we never rely on hiding UI buttons as the only control.
        Gate::before(function ($user, string $ability) {
            return $user->hasRole('super-admin') ? true : null;
        });
    }
}
