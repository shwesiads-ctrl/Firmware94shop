<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Immutable ledger row. Application code must never update or delete a
 * persisted transaction — corrections go through WalletService::reverse(),
 * which posts a new offsetting row. See master prompt §5 Module B (WALLET).
 */
class WalletTransaction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'wallet_id', 'type', 'amount', 'balance_after', 'reference_type',
        'reference_id', 'idempotency_key', 'description', 'created_at',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'balance_after' => 'decimal:4',
        'created_at' => 'datetime',
    ];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }
}
