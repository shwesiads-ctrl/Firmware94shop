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
            'transaction_hash' => $transactionHash,
            'proof_path' => $proofPath,
            'status' => Deposit::STATUS_PENDING,
        ]);
    }

    public function approve(Deposit $deposit, User $admin, ?string $note = null): Deposit
    {
        if ($deposit->status !== Deposit::STATUS_PENDING) {
            throw new RuntimeException('Only a pending deposit can be approved.');
        }

        return DB::transaction(function () use ($deposit, $admin, $note) {
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
    }

    public function proofUrl(Deposit $deposit): ?string
    {
        return $deposit->proof_path ? Storage::disk('payment_proofs')->path($deposit->proof_path) : null;
    }
}
