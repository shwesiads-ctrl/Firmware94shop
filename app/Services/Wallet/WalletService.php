<?php

namespace App\Services\Wallet;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\InvalidWalletAmountException;
use App\Exceptions\WalletOwnershipException;
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
        // AUDIT FIX (BUG-009, P1, Phase 2.2): bccomp()/bcsub() treat a
        // negative $amount as a smaller-than-anything value, so the
        // "insufficient balance" check below never fires for it, and
        // bcsub($balance, $amount) with a negative $amount *adds* to the
        // balance while the ledger row is still posted with type=debit.
        // Every current caller happens to pass a validated positive amount
        // (HTTP-layer `min:1` validation for deposits; admin-configured
        // service/pricing-rule values for orders), but nothing in this
        // class itself enforced that invariant — a single misconfigured
        // pricing rule (e.g. a negative `adjustment_value`) would have
        // silently credited a customer's wallet through what every log and
        // ledger entry calls a "debit". Guard it here, once, for every
        // caller present and future.
        $this->assertPositiveAmount($amount);

        return DB::transaction(function () use ($wallet, $amount, $idempotencyKey, $reference, $description) {
            if ($existing = WalletTransaction::where('idempotency_key', $idempotencyKey)->first()) {
                // AUDIT FIX (BUG-002, P1, Phase 2.1): the replay check used
                // to return whatever row matched the idempotency key, with
                // no check that it belonged to the SAME wallet the caller
                // asked to debit. Every caller in this codebase namespaces
                // its keys well enough that this should never legitimately
                // fire, but if it ever did (a bug elsewhere, or a key
                // collision), silently handing back another wallet's
                // transaction would be a cross-account ledger leak. Fail
                // loudly instead.
                if ($existing->wallet_id !== $wallet->id) {
                    throw new WalletOwnershipException(
                        "Idempotency key [{$idempotencyKey}] is already used on wallet #{$existing->wallet_id}, not wallet #{$wallet->id}."
                    );
                }

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
        // AUDIT FIX (BUG-009, P1, Phase 2.2) — same reasoning as debit() above.
        $this->assertPositiveAmount($amount);

        return DB::transaction(function () use ($wallet, $amount, $idempotencyKey, $reference, $description, $type) {
            if ($existing = WalletTransaction::where('idempotency_key', $idempotencyKey)->first()) {
                // See the matching comment in debit() above — AUDIT FIX (BUG-002).
                if ($existing->wallet_id !== $wallet->id) {
                    throw new WalletOwnershipException(
                        "Idempotency key [{$idempotencyKey}] is already used on wallet #{$existing->wallet_id}, not wallet #{$wallet->id}."
                    );
                }

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
        // AUDIT FIX (BUG-009, P1, Phase 2.2) — same reasoning as debit() above:
        // a negative $amount would reduce locked_balance instead of raising
        // it, silently freeing capacity rather than reserving it.
        $this->assertPositiveAmount($amount);

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

    /**
     * Every wallet mutation amount must be a strictly positive magnitude;
     * direction is conveyed solely by which method is called / which `type`
     * is recorded (AUDIT FIX, BUG-009, P1, Phase 2.2). Zero is rejected too:
     * a zero-amount "debit" or "credit" is never a legitimate financial
     * event and would otherwise post a meaningless ledger row.
     */
    private function assertPositiveAmount(string $amount): void
    {
        if (bccomp($amount, '0', 4) <= 0) {
            throw new InvalidWalletAmountException(
                "Wallet amount must be strictly positive; got [{$amount}]."
            );
        }
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
