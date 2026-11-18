<?php

namespace App\Services\Wallet;

use App\Exceptions\InsufficientBalanceException;
use App\Models\Wallet;
use App\Models\WalletHold;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

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
        $this->assertAmount($amount);

        return DB::transaction(function () use ($wallet, $amount, $idempotencyKey, $reference, $description) {
            /** @var Wallet $locked */
            $locked = Wallet::lockForUpdate()->findOrFail($wallet->id);

            if ($existing = $this->matchingExisting($locked, 'debit', $amount, $idempotencyKey, $reference)) {
                return $existing;
            }

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
        $this->assertAmount($amount);

        if (! in_array($type, ['credit', 'deposit', 'refund', 'reversal'], true)) {
            throw new InvalidArgumentException('Unsupported credit transaction type.');
        }

        return DB::transaction(function () use ($wallet, $amount, $idempotencyKey, $reference, $description, $type) {
            /** @var Wallet $locked */
            $locked = Wallet::lockForUpdate()->findOrFail($wallet->id);

            if ($existing = $this->matchingExisting($locked, $type, $amount, $idempotencyKey, $reference)) {
                return $existing;
            }
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
        $this->assertAmount($amount);

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
            /** @var Wallet $locked */
            $locked = Wallet::lockForUpdate()->findOrFail($hold->wallet_id);
            // Re-read after acquiring the wallet lock. Two concurrent release
            // requests cannot decrement locked_balance twice.
            $current = WalletHold::lockForUpdate()->findOrFail($hold->id);

            if ($current->status !== 'active') {
                return;
            }

            $locked->locked_balance = bcsub((string) $locked->locked_balance, (string) $current->amount, 4);
            $locked->save();

            $current->update(['status' => 'released', 'released_at' => now()]);
        });
    }

    private function assertAmount(string $amount): void
    {
        if (! preg_match('/^(?:0|[1-9][0-9]{0,9})(?:\\.[0-9]{1,4})?$/D', $amount) || bccomp($amount, '0', 4) <= 0) {
            throw new InvalidArgumentException('Wallet amount must be a positive decimal with at most four places.');
        }
    }

    private function matchingExisting(
        Wallet $wallet,
        string $type,
        string $amount,
        string $key,
        ?Model $reference,
    ): ?WalletTransaction {
        $existing = WalletTransaction::where('idempotency_key', $key)->first();

        if (! $existing) {
            return null;
        }

        if ((int) $existing->wallet_id !== (int) $wallet->id
            || $existing->type !== $type
            || bccomp((string) $existing->amount, $amount, 4) !== 0
            || $existing->reference_type !== ($reference?->getMorphClass())
            || ($existing->reference_id === null ? null : (int) $existing->reference_id) !== ($reference?->id === null ? null : (int) $reference->id)) {
            throw new LogicException('Idempotency key was reused for a different wallet operation.');
        }

        return $existing;
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
