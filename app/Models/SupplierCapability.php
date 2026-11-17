<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierCapability extends Model
{
    protected $fillable = ['supplier_id', 'capability', 'supported'];
    protected $casts = ['supported' => 'boolean'];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
