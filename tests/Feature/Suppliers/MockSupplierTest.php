<?php

use App\Integrations\Suppliers\DhruClassic\MockDhruClassicSupplier;
use App\Integrations\Suppliers\FusionPro\MockFusionProSupplier;
use App\Integrations\Suppliers\GsmTheme\MockGsmThemeSupplier;
use App\Integrations\Suppliers\Gsmss\MockGsmssSupplier;

dataset('mock_suppliers', [
    'DHRU Classic' => [MockDhruClassicSupplier::class],
    'GSM Theme' => [MockGsmThemeSupplier::class],
    'Fusion Pro' => [MockFusionProSupplier::class],
    'GSMSS' => [MockGsmssSupplier::class],
]);

test('each mock supplier reports a successful connection', function (string $class) {
    expect((new $class)->testConnection()->isSuccessful())->toBeTrue();
})->with('mock_suppliers');

test('each mock supplier lists services and account info', function (string $class) {
    $supplier = new $class;

    expect($supplier->getServices()->isSuccessful())->toBeTrue();
    expect($supplier->getAccountInfo()->isSuccessful())->toBeTrue();
})->with('mock_suppliers');

test('placeOrder deterministically produces every documented outcome by scenario', function (string $class) {
    $supplier = new $class;

    expect($supplier->placeOrder(['mock_scenario' => 'success'])->status)->toBe('success');
    expect($supplier->placeOrder(['mock_scenario' => 'rejected'])->status)->toBe('failed');
    expect($supplier->placeOrder(['mock_scenario' => 'pending'])->status)->toBe('pending');
    expect($supplier->placeOrder(['mock_scenario' => 'processing'])->status)->toBe('processing');
    expect($supplier->placeOrder(['mock_scenario' => 'timeout'])->status)->toBe('timeout');
    expect($supplier->placeOrder(['mock_scenario' => 'unknown'])->status)->toBe('unknown');
})->with('mock_suppliers');

test('the same input always produces the same outcome (deterministic, no randomness)', function (string $class) {
    $supplier = new $class;
    $payload = ['imei' => '123456789012340'];

    $first = $supplier->placeOrder($payload);
    $second = $supplier->placeOrder($payload);

    expect($first->status)->toBe($second->status);
})->with('mock_suppliers');

test('GSMSS mock reports getBulkOrderStatus as not supported, every other mock supports it', function () {
    expect((new MockGsmssSupplier)->supports('getBulkOrderStatus'))->toBeFalse();
    expect((new MockGsmssSupplier)->getBulkOrderStatus(['ref-1'])->status)->toBe('not_supported');

    expect((new MockDhruClassicSupplier)->supports('getBulkOrderStatus'))->toBeTrue();
});

test('getOrderStatus reflects the reference suffix scenario mapping', function (string $class) {
    $supplier = new $class;

    expect($supplier->getOrderStatus('REF-0')->status)->toBe('success');
    expect($supplier->getOrderStatus('REF-4')->status)->toBe('timeout');
    expect($supplier->getOrderStatus('REF-5')->status)->toBe('unknown');
})->with('mock_suppliers');
