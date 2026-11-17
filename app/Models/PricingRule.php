<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingRule extends Model
{
    protected $fillable = [
        'service_id', 'applies_to', 'role_or_group_slug', 'adjustment_type',
        'adjustment_value', 'starts_at', 'ends_at', 'is_active',
    ];

    protected $casts = [
        'adjustment_value' => 'decimal:4',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
