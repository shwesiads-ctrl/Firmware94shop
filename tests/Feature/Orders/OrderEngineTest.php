<?php

use App\Models\Order;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Supplier;
use App\Models\SupplierServiceMapping;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Orders\OrderEngine;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
    Wallet::create(['user_id' => $this->user->id, 'balance' => 100]);

    $category = ServiceCategory::factory()->create();
    $this->service = Service::factory()->create(['service_category_id' => $category->id, 'base_price' => 10]);

    $supplier = Supplier::create(['name' => 'Mock Supplier', 'slug' => 'dhru_classic', 'driver' => 'dhru_classic', 'mode' => 'mock']);
    SupplierServiceMapping::create([
        'service_id' => $this->service->id, 'supplier_id' => $supplier->id,
        'supplier_service_ref' => 'MOCK-1', 'supplier_cost' => 5, 'is_active' => true,
    ]);

    $this->engine = app(OrderEngine::class);
});

test('a successful mock order debits the wallet and finalizes as success', function () {
    $order = $this->engine->placeOrder($this->user, $this->service, ['imei' => '1111111111111110'], (string) Str::uuid());

    expect($order->status)->toBe(Order::STATUS_SUCCESS);
    expect((string) $this->user->wallet->refresh()->balance)->toBe('90.0000');
});

test('a supplier-rejected mock order is automatically refunded', function () {
    $order = $this->engine->placeOrder($this->user, $this->service, ['imei' => '1111111111111111'], (string) Str::uuid());

    expect($order->status)->toBe(Order::STATUS_REFUNDED);
    expect((string) $this->user->wallet->refresh()->balance)->toBe('100.0000');
});

test('a supplier timeout leaves the order as unknown and does NOT refund', function () {
    $order = $this->engine->placeOrder($this->user, $this->service, ['imei' => '1111111111111114'], (string) Str::uuid());

    expect($order->status)->toBe(Order::STATUS_UNKNOWN);
    expect((string) $this->user->wallet->refresh()->balance)->toBe('90.0000'); // still debited, not refunded
});

test('replaying the same idempotency key returns the same order and does not double-charge', function () {
    $key = (string) Str::uuid();

    $first = $this->engine->placeOrder($this->user, $this->service, ['imei' => '1111111111111110'], $key);
    $second = $this->engine->placeOrder($this->user, $this->service, ['imei' => '1111111111111110'], $key);

    expect($first->id)->toBe($second->id);
    expect(Order::count())->toBe(1);
});

test('a suspended user cannot place an order', function () {
    $this->user->update(['status' => 'suspended']);

    expect(fn () => $this->engine->placeOrder($this->user, $this->service, [], (string) Str::uuid()))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});
