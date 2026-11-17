<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SupplierSeeder::class,
            ServiceCatalogSeeder::class,
        ]);

        if (app()->environment(['local', 'testing'])) {
            $this->call(DevelopmentDataSeeder::class);
        }

        // The initial Super Admin is intentionally NOT created here.
        // Run `php artisan f94:install-admin` instead (see app/Console/Commands/InstallAdminCommand.php)
        // so a real admin password is never baked into seed data or version control.
    }
}
