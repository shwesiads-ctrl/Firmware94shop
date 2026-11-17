<?php

namespace App\Services\Wallet;

use App\Exceptions\InsufficientBalanceException;
use App\Models\Wallet;
use App\Models\WalletHold;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * All wallet mutations go through here and nowhere else. Every mutation:
 *   1. runs inside a DB transaction with the wallet row locked (lockForUpdate)
 *   2. is keyed by a caller-supplied idempotency key, enforced by the
 *      wallet_transactions.idempotency_key unique constraint — replaying the
 *      same key is a no-op that returns the original transaction instead of
 *      debiting/crediting twice.
 *   3. appends an immutable ledger row; nothing here ever UPDATEs or DELETEs
 *      a posted wallet_transactions row (master prompt §5 Module B: WALLET).
 */
class WalletService
{
    public function debit(Wallet $wallet, string $amount, string $idempotencyKey, ?Model $reference = null, ?string $description = null): WalletTransaction
    {
        return DB::transaction(function () use ($wallet, $amount, $idempotencyKey, $reference, $description) {
            if ($existing = WalletTransaction::where('idempotency_key', $idempotencyKey)->first()) {
                return $existing;
            }

            /** @var Wallet $locked */
            $locked = Wallet::lockForUpdate()->findOrFail($wallet->id);

            if (bccomp($locked->availableBalance(), $amount, 4) < 0) {
                throw new InsufficientBalanceException('Wallet balance is insufficient for this debit.');
            }

            $locked->balance = bcsub((string) $locked->balance, $amount, 4);
            $locked->save();

            return $this->postLedgerRow($locked, 'debit', $amount, $idempotencyKey, $reference, $description);
        });
    }

    public function credit(Wallet $wallet, string $amount, string $idempotencyKey, ?Model $reference = null, ?string $description = null, string $type = 'credit'): WalletTransaction
    {
        return DB::transaction(function () use ($wallet, $amount, $idempotencyKey, $reference, $description, $type) {
            if ($existing = WalletTransaction::where('idempotency_key', $idempotencyKey)->first()) {
                return $existing;
            }

            /** @var Wallet $locked */
            $locked = Wallet::lockForUpdate()->findOrFail($wallet->id);
            $locked->balance = bcadd((string) $locked->balance, $amount, 4);
            $locked->save();

            return $this->postLedgerRow($locked, $type, $amount, $idempotencyKey, $reference, $description);
        });
    }

    /** Reverses a previous ledger entry by posting a new, offsetting entry. Never edits the original row. */
    public function reverse(WalletTransaction $original, string $idempotencyKey, ?string $description = null): WalletTransaction
    {
        $wallet = $original->wallet;
        $description ??= "Reversal of transaction #{$original->id}";

        return in_array($original->type, ['debit'], true)
            ? $this->credit($wallet, (string) $original->amount, $idempotencyKey, $original->reference, $description, 'reversal')
            : $this->debit($wallet, (string) $original->amount, $idempotencyKey, $original->reference, $description);
    }

    public function hold(Wallet $wallet, string $amount, ?Model $reference = null): WalletHold
    {
        return DB::transaction(function () use ($wallet, $amount, $reference) {
            /** @var Wallet $locked */
            $locked = Wallet::lockForUpdate()->findOrFail($wallet->id);

            if (bccomp($locked->availableBalance(), $amount, 4) < 0) {
                throw new InsufficientBalanceException('Wallet balance is insufficient for this hold.');
            }

            $locked->locked_balance = bcadd((string) $locked->locked_balance, $amount, 4);
            $locked->save();

            return $locked->holds()->create([
                'amount' => $amount,
                'status' => 'active',
                'reference_type' => $reference ? $reference::class : null,
                'reference_id' => $reference?->id,
            ]);
        });
    }

    public function releaseHold(WalletHold $hold): void
    {
        DB::transaction(function () use ($hold) {
            if ($hold->status !== 'active') {
                return;
            }

            /** @var Wallet $locked */
            $locked = Wallet::lockForUpdate()->findOrFail($hold->wallet_id);
            $locked->locked_balance = bcsub((string) $locked->locked_balance, (string) $hold->amount, 4);
            $locked->save();

            $hold->update(['status' => 'released', 'released_at' => now()]);
        });
    }

    private function postLedgerRow(Wallet $wallet, string $type, string $amount, string $idempotencyKey, ?Model $reference, ?string $description): WalletTransaction
    {
        return $wallet->transactions()->create([
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $wallet->balance,
            'reference_type' => $reference ? $reference::class : null,
            'reference_id' => $reference?->id,
            'idempotency_key' => $idempotencyKey,
            'description' => $description,
            'created_at' => now(),
        ]);
    }
}
