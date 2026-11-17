<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierServiceMapping extends Model
{
    protected $fillable = ['service_id', 'supplier_id', 'supplier_service_ref', 'supplier_cost', 'priority', 'is_active'];
    protected $casts = ['supplier_cost' => 'decimal:4', 'is_active' => 'boolean'];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
