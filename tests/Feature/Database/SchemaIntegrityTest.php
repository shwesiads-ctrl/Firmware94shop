<?php

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

test('every table the Phase 2 Foundation requires exists after migration', function () {
    $expected = [
        'users', 'password_reset_tokens', 'sessions', 'user_login_histories',
        'roles', 'permissions', 'model_has_roles', 'model_has_permissions', 'permission_role',
        'reseller_groups', 'reseller_profiles', 'reseller_group_members',
        'api_credentials', 'api_ip_rules', 'api_usage_logs',
        'suppliers', 'supplier_credentials', 'supplier_capabilities', 'supplier_connection_logs',
        'service_categories', 'services', 'service_fields', 'supplier_service_mappings',
        'currency_settings', 'pricing_rules', 'reseller_prices',
        'wallets', 'wallet_transactions', 'wallet_holds',
        'orders', 'order_inputs', 'order_events', 'supplier_orders',
        'payment_methods', 'deposits', 'payment_callbacks',
        'announcements', 'audit_logs', 'support_tickets', 'support_ticket_replies', 'system_settings',
    ];

    foreach ($expected as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Missing table: {$table}");
    }
});

test('a wallet is unique per user at the database level', function () {
    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 0]);

    expect(fn () => Wallet::create(['user_id' => $user->id, 'balance' => 0]))
        ->toThrow(QueryException::class);
});

test('a user email is unique at the database level', function () {
    User::factory()->create(['email' => 'dupe@example.test']);

    expect(fn () => User::factory()->create(['email' => 'dupe@example.test']))
        ->toThrow(QueryException::class);
});
