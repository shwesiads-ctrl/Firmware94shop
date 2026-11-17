<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            ['name' => 'DHRU Classic (Mock)', 'slug' => 'dhru_classic', 'driver' => 'dhru_classic'],
            ['name' => 'GSM Theme (Mock)', 'slug' => 'gsm_theme', 'driver' => 'gsm_theme'],
            ['name' => 'Fusion Pro (Mock)', 'slug' => 'fusion_pro', 'driver' => 'fusion_pro'],
            ['name' => 'GSMSS (Mock)', 'slug' => 'gsmss', 'driver' => 'gsmss'],
        ];

        $capabilities = [
            'testConnection', 'getAccountInfo', 'getServices', 'getServiceDetails',
            'placeOrder', 'getOrderStatus', 'getBulkOrderStatus',
        ];

        foreach ($suppliers as $i => $data) {
            $supplier = Supplier::updateOrCreate(
                ['slug' => $data['slug']],
                ['name' => $data['name'], 'driver' => $data['driver'], 'mode' => 'mock', 'is_active' => true, 'priority' => $i]
            );

            foreach ($capabilities as $capability) {
                // GSMSS does not support bulk status in Phase 2 docs supplied — demonstrates NotSupported handling.
                $supported = ! ($data['slug'] === 'gsmss' && $capability === 'getBulkOrderStatus');

                $supplier->capabilities()->updateOrCreate(
                    ['capability' => $capability],
                    ['supported' => $supported]
                );
            }
        }
    }
}
