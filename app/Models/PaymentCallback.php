<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentCallback extends Model
{
    public $timestamps = false;
    protected $fillable = ['deposit_id', 'source', 'payload', 'verified', 'created_at'];
    protected $casts = ['payload' => 'array', 'verified' => 'boolean', 'created_at' => 'datetime'];

    public function deposit()
    {
        return $this->belongsTo(Deposit::class);
    }
}
