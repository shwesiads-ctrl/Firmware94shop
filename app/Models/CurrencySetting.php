<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrencySetting extends Model
{
    protected $fillable = ['code', 'symbol', 'exchange_rate_to_usd', 'is_default'];
    protected $casts = ['exchange_rate_to_usd' => 'decimal:8', 'is_default' => 'boolean'];
}
