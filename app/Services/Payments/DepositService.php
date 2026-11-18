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
        return DB::transaction(function () use ($deposit, $admin, $note) {
            // Lock and re-read inside the transaction; concurrent approvals/rejections
            // must never both succeed or credit the wallet twice.
            $locked = Deposit::query()->lockForUpdate()->findOrFail($deposit->id);

            if ($locked->status !== Deposit::STATUS_PENDING) {
                throw new RuntimeException('Only a pending deposit can be approved.');
            }

            $wallet = $locked->user->wallet;

            if (! $wallet || strtoupper((string) $wallet->currency) !== strtoupper((string) $locked->currency)) {
                throw new RuntimeException('Deposit and wallet currencies differ. Manual conversion is required.');
            }

            if (bccomp((string) $locked->amount, '0', 4) <= 0) {
                throw new RuntimeException('A deposit must have a positive amount.');
            }

            $this->wallets->credit(
                $wallet,
                (string) $locked->amount,
                "deposit-credit:{$locked->id}",
                $locked,
                "Deposit #{$locked->id} approved",
                'deposit'
            );

            $locked->update([
                'status' => Deposit::STATUS_APPROVED,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'admin_note' => $note,
            ]);

            AuditLogger::record('deposit.approved', $locked, [], ['amount' => (string) $locked->amount]);

            return $locked;
        });
    }

    public function reject(Deposit $deposit, User $admin, string $note): Deposit
    {
        return DB::transaction(function () use ($deposit, $admin, $note) {
            $locked = Deposit::query()->lockForUpdate()->findOrFail($deposit->id);

            if ($locked->status !== Deposit::STATUS_PENDING) {
                throw new RuntimeException('Only a pending deposit can be rejected.');
            }

            $locked->update([
                'status' => Deposit::STATUS_REJECTED,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'admin_note' => $note,
            ]);

            AuditLogger::record('deposit.rejected', $locked, [], ['note' => $note]);

            return $locked;
        });
    }

    public function proofUrl(Deposit $deposit): ?string
    {
        return $deposit->proof_path ? Storage::disk('payment_proofs')->path($deposit->proof_path) : null;
    }
}
