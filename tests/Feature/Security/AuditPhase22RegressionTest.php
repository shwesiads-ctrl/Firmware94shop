<?php

/**
 * Regression tests for the Phase 2.2 real-environment verification pass
 * (docs/PHASE2_2_BUG_FIX_LOG.md). Like the Phase 2.1 suite, each test is
 * written to FAIL against the pre-fix code and PASS against the fixed code.
 *
 * IMPORTANT (see docs/PHASE2_2_TEST_REPORT.md): this file, like every test
 * file in this repository, has NEVER BEEN EXECUTED in this sandbox —
 * `vendor/autoload.php` does not exist (composer install is blocked by the
 * sandbox's network policy) so Pest/PHPUnit cannot even boot. These tests
 * are reviewed by hand for correctness against the fixed source and are
 * reported as NOT EXECUTED, not as PASS.
 */

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\InvalidWalletAmountException;
use App\Exceptions\WalletOwnershipException;
use App\Jobs\ReconcileUnknownSupplierOrders;
use App\Models\ApiCredential;
use App\Models\Deposit;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Supplier;
use App\Models\SupplierServiceMapping;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Orders\OrderEngine;
use App\Services\Payments\DepositService;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->wallet = Wallet::create(['user_id' => $this->user->id, 'balance' => 100]);
    $this->wallets = app(WalletService::class);
});

// --- BUG-009 (P1): debit()/credit()/hold() silently flip sign on a negative amount ---

test('BUG-009a: debit() rejects a negative amount instead of crediting the wallet', function () {
    expect(fn () => $this->wallets->debit($this->wallet, '-50.0000', 'neg-debit-1'))
        ->toThrow(InvalidWalletAmountException::class);

    // Balance must be completely unchanged — pre-fix, this exact call would
    // have *raised* the balance to 150.0000 while logging it as a "debit".
    expect((string) $this->wallet->refresh()->balance)->toBe('100.0000');
    expect($this->wallet->transactions()->count())->toBe(0);
});

test('BUG-009b: credit() rejects a negative amount instead of debiting the wallet', function () {
    expect(fn () => $this->wallets->credit($this->wallet, '-50.0000', 'neg-credit-1'))
        ->toThrow(InvalidWalletAmountException::class);

    expect((string) $this->wallet->refresh()->balance)->toBe('100.0000');
});

test('BUG-009c: hold() rejects a negative amount instead of freeing locked balance', function () {
    $this->wallets->hold($this->wallet, '40.0000'); // legitimate prior hold
    expect((string) $this->wallet->refresh()->locked_balance)->toBe('40.0000');

    expect(fn () => $this->wallets->hold($this->wallet, '-40.0000'))
        ->toThrow(InvalidWalletAmountException::class);

    // locked_balance must not have been reduced by the negative-amount call
    expect((string) $this->wallet->refresh()->locked_balance)->toBe('40.0000');
});

test('BUG-009d: debit() and credit() reject a zero amount', function () {
    expect(fn () => $this->wallets->debit($this->wallet, '0.0000', 'zero-debit'))
        ->toThrow(InvalidWalletAmountException::class);
    expect(fn () => $this->wallets->credit($this->wallet, '0.0000', 'zero-credit'))
        ->toThrow(InvalidWalletAmountException::class);
    expect($this->wallet->transactions()->count())->toBe(0);
});

test('Scenario check: a valid positive credit still works normally after the BUG-009 fix', function () {
    $tx = $this->wallets->credit($this->wallet, '25.0000', 'valid-credit-1');

    expect((string) $this->wallet->refresh()->balance)->toBe('125.0000');
    expect($tx->type)->toBe('credit');
});

// --- Required scenario: cross-wallet idempotency attempt (WalletOwnershipException, BUG-002 regression) ---

test('a debit idempotency key already used on another wallet is rejected, not silently returned', function () {
    $otherUser = User::factory()->create();
    $otherWallet = Wallet::create(['user_id' => $otherUser->id, 'balance' => 100]);

    $this->wallets->debit($this->wallet, '10.0000', 'shared-key-wallets');

    expect(fn () => $this->wallets->debit($otherWallet, '10.0000', 'shared-key-wallets'))
        ->toThrow(WalletOwnershipException::class);

    // otherWallet's balance must be untouched by the rejected attempt
    expect((string) $otherWallet->refresh()->balance)->toBe('100.0000');
});

// --- Required scenario: decimal precision ---

test('decimal precision is preserved to 4 places through bcmath, never float rounding', function () {
    $tx = $this->wallets->debit($this->wallet, '33.3333', 'precision-1');

    expect((string) $this->wallet->refresh()->balance)->toBe('66.6667');
    expect((string) $tx->amount)->toBe('33.3333');
});

// --- BUG-008 (P1): deposit transaction_hash normalization + dedupe design ---

test('BUG-008: a resubmitted transaction hash with different case normalizes to the same stored value', function () {
    $method = PaymentMethod::create(['code' => 'usdt_trc20', 'label' => 'USDT TRC20', 'network' => 'TRC20']);
    $deposits = app(DepositService::class);

    $first = $deposits->submit($this->user, $method->id, '50.0000', 'TRC20', '  ABC123DEF456  ', null);

    expect($first->transaction_hash)->toBe('abc123def456');
});

test('BUG-008: a null transaction hash remains null after normalization (manual deposits unaffected)', function () {
    $method = PaymentMethod::create(['code' => 'manual', 'label' => 'Manual']);
    $deposits = app(DepositService::class);

    $deposit = $deposits->submit($this->user, $method->id, '50.0000', null, null, null);

    expect($deposit->transaction_hash)->toBeNull();
});

// --- BUG-010 (P0): an exception from the supplier adapter left the order
// stuck at SENT_TO_SUPPLIER forever, with wallet funds already debited and
// never refunded, never flagged, never reconciled. ---

test('BUG-010: a supplier adapter throwing during placeOrder() moves the order to UNKNOWN, not a stuck state', function () {
    // NOTE on BUG-012 (P2, documented not fixed — see bug fix log):
    // SupplierManager::for() resolves mock-vs-live purely from
    // config('firmware94.suppliers.<slug>.mode'), which is env-driven —
    // NOT from this Supplier row's own `mode` column (Supplier::isMocked()
    // exists but is never actually consulted anywhere). So forcing "live"
    // for this test means overriding config() directly; setting
    // `mode => 'live'` on the Supplier row below has no effect on which
    // adapter class actually gets resolved, and is set here only because
    // the column exists and must be given some value.
    config(['firmware94.suppliers.gsmss.mode' => 'live']);

    $category = ServiceCategory::factory()->create();
    $service = Service::factory()->create(['service_category_id' => $category->id, 'base_price' => 10]);

    // gsmss's "live" adapter is architecture-only and unconditionally
    // throws RuntimeException on every call (App\Integrations\Suppliers\
    // Gsmss\GsmssAdapter::notImplemented) — the real-world shape of "the
    // supplier call blew up" this bug is about, without needing a test
    // double.
    $supplier = Supplier::create(['name' => 'GSMSS', 'slug' => 'gsmss', 'driver' => 'gsmss', 'mode' => 'live']);
    SupplierServiceMapping::create([
        'service_id' => $service->id, 'supplier_id' => $supplier->id,
        'supplier_service_ref' => 'LIVE-1', 'supplier_cost' => 5, 'is_active' => true,
    ]);

    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 100]);

    $order = app(OrderEngine::class)->placeOrder($user, $service, ['imei' => '1111111111111110'], (string) Str::uuid());

    // Pre-fix: this line would never be reached — the uncaught
    // RuntimeException from GsmssAdapter would propagate out of
    // placeOrder() entirely, and the order row would be left at
    // STATUS_SENT_TO_SUPPLIER with the wallet already debited.
    expect($order->status)->toBe(Order::STATUS_UNKNOWN);
    expect((string) $user->wallet->refresh()->balance)->toBe('90.0000'); // debited, not lost, not doubled
    expect($order->supplierOrders()->latest()->first()->status)->toBe('unknown');
});

// --- BUG-011 (P0): reconciliation confirming a supplier failure did nothing
// — no refund, order stuck at UNKNOWN forever. ---

test('BUG-011: reconciliation that confirms a supplier failure now actually refunds and resolves the order', function () {
    $category = ServiceCategory::factory()->create();
    $service = Service::factory()->create(['service_category_id' => $category->id, 'base_price' => 10]);

    $supplier = Supplier::create(['name' => 'Mock', 'slug' => 'dhru_classic', 'driver' => 'dhru_classic', 'mode' => 'mock']);
    SupplierServiceMapping::create([
        'service_id' => $service->id, 'supplier_id' => $supplier->id,
        'supplier_service_ref' => 'MOCK-1', 'supplier_cost' => 5, 'is_active' => true,
    ]);

    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 100]);

    // ...4 suffix -> mock "timeout" scenario -> order lands at UNKNOWN, debited, not refunded.
    $order = app(OrderEngine::class)->placeOrder($user, $service, ['imei' => '1111111111111114'], (string) Str::uuid());
    expect($order->status)->toBe(Order::STATUS_UNKNOWN);

    // Give the stuck order a supplier_reference ending in '...1' so a
    // reconciliation re-check of getOrderStatus() now reports a CONFIRMED
    // "rejected" (final, not successful) result.
    $order->supplierOrders()->latest()->first()->update(['supplier_reference' => 'MOCKORD-0000000001']);

    (new ReconcileUnknownSupplierOrders())->handle(app(\App\Integrations\Suppliers\SupplierManager::class));

    // Pre-fix: this would still be STATUS_UNKNOWN and the balance would
    // still be 90.0000 forever — the reconciliation branch was a no-op.
    expect($order->refresh()->status)->toBe(Order::STATUS_REFUNDED);
    expect((string) $user->wallet->refresh()->balance)->toBe('100.0000');
});

// --- BUG-015 (P1): DHRU placeimeiorder didn't check the orders.create scope
// that its REST v1 sibling endpoint already enforces. ---

test('BUG-015: a credential without the orders.create scope cannot place an order via the DHRU-compatible endpoint', function () {
    $category = ServiceCategory::factory()->create();
    $service = Service::factory()->create(['service_category_id' => $category->id, 'base_price' => 10, 'is_active' => true]);

    $supplier = Supplier::create(['name' => 'Mock', 'slug' => 'dhru_classic', 'driver' => 'dhru_classic', 'mode' => 'mock']);
    SupplierServiceMapping::create([
        'service_id' => $service->id, 'supplier_id' => $supplier->id,
        'supplier_service_ref' => 'MOCK-1', 'supplier_cost' => 5, 'is_active' => true,
    ]);

    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 100]);

    // Deliberately no `orders.create` scope — e.g. a read-only reporting key.
    ['credential' => $credential, 'secret' => $secret] = ApiCredential::generate($user, ['scopes' => ['orders.read']]);

    $response = $this->post('/api/index.php', [
        'username' => $credential->public_key,
        'apiAccessKey' => $secret,
        'action' => 'placeimeiorder',
        'id' => $service->id,
        'imei' => '1111111111111110',
    ]);

    // Pre-fix: this would return STATUS=success and actually debit the
    // wallet, even though the equivalent REST v1 call
    // (POST /api/v1/orders) with the same credential would have been
    // rejected with 403 for missing the orders.create scope.
    $response->assertStatus(403);
    expect($user->wallet->refresh()->balance)->toEqual('100.0000');
    expect(Order::count())->toBe(0);
});
