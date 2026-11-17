<?php

use App\Exceptions\InsufficientBalanceException;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Wallet\WalletService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->wallet = Wallet::create(['user_id' => $this->user->id, 'balance' => 100]);
    $this->service = app(WalletService::class);
});

test('a debit reduces the balance and writes an immutable ledger row', function () {
    $tx = $this->service->debit($this->wallet, '40.0000', 'debit-key-1');

    expect((string) $this->wallet->refresh()->balance)->toBe('60.0000');
    expect($tx->type)->toBe('debit');
    expect((string) $tx->balance_after)->toBe('60.0000');
});

test('replaying the same idempotency key does not double-debit', function () {
    $this->service->debit($this->wallet, '40.0000', 'same-key');
    $this->service->debit($this->wallet, '40.0000', 'same-key');

    expect((string) $this->wallet->refresh()->balance)->toBe('60.0000');
    expect($this->wallet->transactions()->count())->toBe(1);
});

test('a debit larger than the available balance throws and changes nothing', function () {
    expect(fn () => $this->service->debit($this->wallet, '999.0000', 'too-much'))
        ->toThrow(InsufficientBalanceException::class);

    expect((string) $this->wallet->refresh()->balance)->toBe('100.0000');
});

test('a hold locks balance without touching the ledger, and release frees it', function () {
    $hold = $this->service->hold($this->wallet, '30.0000');

    expect((string) $this->wallet->refresh()->locked_balance)->toBe('30.0000');
    expect($this->wallet->availableBalance())->toBe('70.0000');

    $this->service->releaseHold($hold);

    expect((string) $this->wallet->refresh()->locked_balance)->toBe('0.0000');
});

test('a reversal of a debit posts a new credit row rather than editing the original', function () {
    $debit = $this->service->debit($this->wallet, '25.0000', 'debit-to-reverse');
    $this->service->reverse($debit, 'reverse-of-debit-to-reverse');

    expect((string) $this->wallet->refresh()->balance)->toBe('100.0000');
    expect($this->wallet->transactions()->count())->toBe(2);
    expect($debit->refresh()->amount)->toEqual('25.0000'); // original row untouched
});

test('concurrent debits for the same wallet never push the balance negative', function () {
    // Simulated sequentially (SQLite in tests has no real concurrent
    // connections), but exercises the same lockForUpdate + bcmath path
    // relied on under real concurrent load.
    foreach (range(1, 5) as $i) {
        try {
            $this->service->debit($this->wallet, '25.0000', "race-{$i}");
        } catch (InsufficientBalanceException) {
            // expected once the balance runs out
        }
    }

    expect((float) $this->wallet->refresh()->balance)->toBeGreaterThanOrEqual(0);
});
