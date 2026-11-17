<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = ['code', 'label', 'network', 'is_active', 'receiving_address'];
    protected $casts = ['is_active' => 'boolean'];

    public function deposits()
    {
        return $this->hasMany(Deposit::class);
    }
}
