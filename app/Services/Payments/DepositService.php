<?php

namespace App\Services\Payments;

use App\Models\Deposit;
use App\Models\User;
use App\Services\Wallet\WalletService;
use App\Support\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Manual / USDT deposit workflow (master prompt §5 Module G). A deposit is
 * ALWAYS created as "pending" and only ever credited to the wallet after an
 * admin explicitly approves it here — never from a screenshot, a tx hash, or
 * a browser redirect alone. Real on-chain verification is out of scope for
 * Phase 2 (config('firmware94.payments.auto_verify_blockchain') is false).
 */
class DepositService
{
    public function __construct(private WalletService $wallets)
    {
    }

    public function submit(User $user, int $paymentMethodId, string $amount, ?string $network, ?string $transactionHash, ?UploadedFile $proof): Deposit
    {
        $proofPath = $proof?->store('', 'payment_proofs');

        return Deposit::create([
            'user_id' => $user->id,
            'payment_method_id' => $paymentMethodId,
            'amount' => $amount,
            'currency' => 'USD',
            'network' => $network,
            // PHASE 2.2 (BUG-008, P1): normalize before persisting so that
            // the DB-level dedupe key added in
            // 2026_01_01_000100_add_transaction_hash_dedupe_to_deposits_table
            // actually catches the same on-chain transaction resubmitted
            // with different case or incidental whitespace. Normalization
            // happens here, at write time, rather than only in the
            // generated column, so the raw stored value and the dedupe key
            // agree and nothing downstream has to re-derive it.
            'transaction_hash' => $this->normalizeTransactionHash($transactionHash),
            'proof_path' => $proofPath,
            'status' => Deposit::STATUS_PENDING,
        ]);
    }

    public function approve(Deposit $deposit, User $admin, ?string $note = null): Deposit
    {
        return DB::transaction(function () use ($deposit, $admin, $note) {
            // AUDIT FIX (BUG-006, P1, Phase 2.1): the pending-status check
            // used to run before the transaction opened and before any row
            // lock was taken. Two concurrent approve requests for the same
            // deposit (a double-click, a retried webhook/form submit, or two
            // admins acting at once) could both read status=pending before
            // either one's UPDATE committed, and both would then credit the
            // wallet — a double credit for a single real-world deposit. The
            // check now happens only after lockForUpdate() has the row, so
            // the second concurrent caller sees the already-approved status
            // and is rejected instead of crediting twice.
            $deposit = Deposit::lockForUpdate()->findOrFail($deposit->id);

            if ($deposit->status !== Deposit::STATUS_PENDING) {
                throw new RuntimeException('Only a pending deposit can be approved.');
            }

            $deposit->update([
                'status' => Deposit::STATUS_APPROVED,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'admin_note' => $note,
            ]);

            $this->wallets->credit(
                $deposit->user->wallet,
                (string) $deposit->amount,
                "deposit-credit:{$deposit->id}",
                $deposit,
                "Deposit #{$deposit->id} approved",
                'deposit'
            );

            AuditLogger::record('deposit.approved', $deposit, [], ['amount' => (string) $deposit->amount]);

            return $deposit;
        });
    }

    public function reject(Deposit $deposit, User $admin, string $note): Deposit
    {
        // Same lock-then-check pattern as approve() — AUDIT FIX (BUG-006).
        return DB::transaction(function () use ($deposit, $admin, $note) {
            $deposit = Deposit::lockForUpdate()->findOrFail($deposit->id);

            if ($deposit->status !== Deposit::STATUS_PENDING) {
                throw new RuntimeException('Only a pending deposit can be rejected.');
            }

            $deposit->update([
                'status' => Deposit::STATUS_REJECTED,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'admin_note' => $note,
            ]);

            AuditLogger::record('deposit.rejected', $deposit, [], ['note' => $note]);

            return $deposit;
        });
    }

    public function proofUrl(Deposit $deposit): ?string
    {
        return $deposit->proof_path ? Storage::disk('payment_proofs')->path($deposit->proof_path) : null;
    }

    /**
     * Lower-case and trim a user-submitted transaction hash. Does not
     * validate format (network-specific hash shapes vary); only removes the
     * case/whitespace variance that would otherwise defeat the dedupe key.
     * Returns null unchanged for an absent hash (manual/no-hash deposits).
     */
    private function normalizeTransactionHash(?string $transactionHash): ?string
    {
        if ($transactionHash === null) {
            return null;
        }

        $trimmed = trim($transactionHash);

        return $trimmed === '' ? null : strtolower($trimmed);
    }
}
