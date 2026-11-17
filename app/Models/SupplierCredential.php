<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierCredential extends Model
{
    protected $fillable = ['supplier_id', 'encrypted_payload', 'rotated_at'];

    protected $casts = [
        'encrypted_payload' => 'encrypted:array', // decrypted array never serialized back to logs/UI
        'rotated_at' => 'datetime',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
