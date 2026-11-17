<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResellerGroup extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'default_discount_percent', 'description'];
    protected $casts = ['default_discount_percent' => 'decimal:2'];

    public function profiles()
    {
        return $this->hasMany(ResellerProfile::class);
    }

    public function resellerPrices()
    {
        return $this->hasMany(ResellerPrice::class);
    }
}
