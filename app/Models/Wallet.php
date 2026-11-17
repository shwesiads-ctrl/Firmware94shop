<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    protected $fillable = ['user_id', 'balance', 'locked_balance', 'currency'];
    protected $casts = ['balance' => 'decimal:4', 'locked_balance' => 'decimal:4'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function holds()
    {
        return $this->hasMany(WalletHold::class);
    }

    public function availableBalance(): string
    {
        return bcsub((string) $this->balance, (string) $this->locked_balance, 4);
    }
}
