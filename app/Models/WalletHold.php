<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletHold extends Model
{
    protected $fillable = ['wallet_id', 'amount', 'status', 'reference_type', 'reference_id', 'released_at'];
    protected $casts = ['amount' => 'decimal:4', 'released_at' => 'datetime'];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }
}
