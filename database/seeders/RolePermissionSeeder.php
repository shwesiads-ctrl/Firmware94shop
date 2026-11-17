<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'users.view', 'users.create', 'users.update', 'users.suspend',
            'suppliers.view', 'suppliers.manage',
            'services.view', 'services.manage',
            'orders.view', 'orders.manage',
            'wallet.view', 'wallet.manage',
            'payments.approve', 'withdrawals.approve',
            'api.manage',
            'reports.view',
            'settings.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $roleMap = [
            'super-admin' => $permissions, // also bypasses via Gate::before in AuthServiceProvider
            'admin' => $permissions,
            'staff' => [
                'users.view', 'suppliers.view', 'services.view', 'orders.view',
                'orders.manage', 'wallet.view', 'payments.approve', 'reports.view',
            ],
            'distributor' => ['services.view', 'orders.view', 'orders.manage', 'wallet.view'],
            'reseller' => ['services.view', 'orders.view', 'orders.manage', 'wallet.view', 'api.manage'],
            'customer' => ['services.view', 'orders.view', 'orders.manage', 'wallet.view'],
        ];

        foreach ($roleMap as $roleName => $rolePermissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($rolePermissions);
        }
    }
}
