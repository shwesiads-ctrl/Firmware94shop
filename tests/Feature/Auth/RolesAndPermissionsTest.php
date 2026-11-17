<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('seeding creates all six roles with the documented permission sets', function () {
    $this->seed(RolePermissionSeeder::class);

    foreach (config('firmware94.roles') as $role) {
        expect(\Spatie\Permission\Models\Role::where('name', $role)->exists())->toBeTrue();
    }
});

test('super-admin bypasses explicit permission checks via Gate::before', function () {
    $this->seed(RolePermissionSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    expect($admin->can('anything.not.defined.anywhere'))->toBeTrue();
});

test('a customer cannot access permissions reserved for staff', function () {
    $this->seed(RolePermissionSeeder::class);

    $customer = User::factory()->create();
    $customer->assignRole('customer');

    expect($customer->can('payments.approve'))->toBeFalse();
});
