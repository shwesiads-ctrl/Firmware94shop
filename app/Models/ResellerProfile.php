<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResellerProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'reseller_group_id', 'company_name', 'contact_phone',
        'tax_id', 'approval_status', 'approved_by', 'approved_at',
    ];

    protected $casts = ['approved_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function resellerGroup()
    {
        return $this->belongsTo(ResellerGroup::class);
    }

    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }
}
