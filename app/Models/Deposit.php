<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Deposit extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id', 'payment_method_id', 'amount', 'currency', 'network',
        'transaction_hash', 'proof_path', 'status', 'reviewed_by', 'reviewed_at', 'admin_note',
    ];

    protected $casts = ['amount' => 'decimal:4', 'reviewed_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function callbacks()
    {
        return $this->hasMany(PaymentCallback::class);
    }
}
