<?php

use App\Models\PricingRule;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Services\Pricing\PricingService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('a service with no pricing rule charges its base price', function () {
    $service = Service::factory()->create(['service_category_id' => ServiceCategory::factory(), 'base_price' => 10]);
    $user = User::factory()->create();
    $user->assignRole('customer');

    expect(app(PricingService::class)->priceFor($service, $user))->toBe('10.0000');
});

test('a percent markup rule for a role is applied correctly using bcmath', function () {
    $service = Service::factory()->create(['service_category_id' => ServiceCategory::factory(), 'base_price' => 10]);
    $user = User::factory()->create();
    $user->assignRole('customer');

    PricingRule::create([
        'service_id' => $service->id, 'applies_to' => 'role', 'role_or_group_slug' => 'customer',
        'adjustment_type' => 'markup_percent', 'adjustment_value' => 10, 'is_active' => true,
    ]);

    expect(app(PricingService::class)->priceFor($service, $user))->toBe('11.0000');
});

test('a fixed_price rule overrides the base price entirely', function () {
    $service = Service::factory()->create(['service_category_id' => ServiceCategory::factory(), 'base_price' => 10]);
    $user = User::factory()->create();
    $user->assignRole('customer');

    PricingRule::create([
        'service_id' => $service->id, 'applies_to' => 'all',
        'adjustment_type' => 'fixed_price', 'adjustment_value' => 3.5, 'is_active' => true,
    ]);

    expect(app(PricingService::class)->priceFor($service, $user))->toBe('3.5000');
});
