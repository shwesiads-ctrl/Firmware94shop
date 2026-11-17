<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Supplier;
use App\Models\SupplierServiceMapping;
use Illuminate\Database\Seeder;

class ServiceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'IMEI / Carrier / iCloud / FRP Service', 'slug' => 'imei-carrier-icloud-frp', 'group' => 'imei_carrier_icloud_frp'],
            ['name' => 'Server / Tools / Cards Service', 'slug' => 'server-tools-cards', 'group' => 'server_tools_cards'],
            ['name' => 'Rent / Shared Boxes & Tools', 'slug' => 'rent-shared-boxes', 'group' => 'rent_shared_boxes'],
            ['name' => 'File / Firmware Link Service', 'slug' => 'file-firmware-link', 'group' => 'file_firmware'],
        ];

        $supplier = Supplier::where('slug', 'dhru_classic')->first();

        foreach ($categories as $sort => $categoryData) {
            $category = ServiceCategory::updateOrCreate(
                ['slug' => $categoryData['slug']],
                [...$categoryData, 'sort_order' => $sort, 'is_active' => true]
            );

            $service = Service::updateOrCreate(
                ['slug' => $categoryData['slug'].'-sample-service'],
                [
                    'service_category_id' => $category->id,
                    'name' => 'Sample '.$categoryData['name'],
                    'description' => 'Development seed data — not a real service offer.',
                    'base_price' => 5.0000,
                    'estimated_time_minutes' => 30,
                    'is_active' => true,
                ]
            );

            if ($supplier) {
                SupplierServiceMapping::updateOrCreate(
                    ['service_id' => $service->id, 'supplier_id' => $supplier->id],
                    ['supplier_service_ref' => 'MOCK-'.$service->id, 'supplier_cost' => 3.0000, 'is_active' => true]
                );
            }
        }
    }
}
