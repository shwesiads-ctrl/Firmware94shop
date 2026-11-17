<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResellerPrice extends Model
{
    protected $fillable = ['service_id', 'reseller_group_id', 'reseller_profile_id', 'price'];
    protected $casts = ['price' => 'decimal:4'];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function resellerGroup()
    {
        return $this->belongsTo(ResellerGroup::class);
    }

    public function resellerProfile()
    {
        return $this->belongsTo(ResellerProfile::class);
    }
}
