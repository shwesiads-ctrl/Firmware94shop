<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\ResellerGroup;
use App\Models\ResellerProfile;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;

/**
 * Clearly-labelled DEVELOPMENT-ONLY seed data (master prompt §5 Module J).
 * Never run this seeder against a production database. No real customer
 * data is used anywhere here.
 */
class DevelopmentDataSeeder extends Seeder
{
    public function run(): void
    {
        $distributor = User::factory()->create(['name' => '[DEV] Sample Distributor', 'email' => 'distributor@f94shop.dev']);
        $distributor->assignRole('distributor');
        Wallet::updateOrCreate(['user_id' => $distributor->id], ['balance' => 500]);

        $group = ResellerGroup::factory()->create(['name' => '[DEV] Gold Resellers', 'slug' => 'dev-gold-resellers']);

        $reseller = User::factory()->create(['name' => '[DEV] Sample Reseller', 'email' => 'reseller@f94shop.dev']);
        $reseller->assignRole('reseller');
        ResellerProfile::updateOrCreate(
            ['user_id' => $reseller->id],
            ['reseller_group_id' => $group->id, 'company_name' => '[DEV] Sample Reseller Co', 'approval_status' => 'approved']
        );
        Wallet::updateOrCreate(['user_id' => $reseller->id], ['balance' => 250]);

        $customer = User::factory()->create(['name' => '[DEV] Sample Customer', 'email' => 'customer@f94shop.dev']);
        $customer->assignRole('customer');
        Wallet::updateOrCreate(['user_id' => $customer->id], ['balance' => 25]);

        Announcement::create([
            'title' => '[DEV] Welcome to Firmware94shop',
            'body' => 'This is development seed data for the Phase 2 Foundation build.',
            'audience' => 'all',
            'is_pinned' => true,
            'published_at' => now(),
        ]);
    }
}
