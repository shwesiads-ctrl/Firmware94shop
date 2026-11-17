<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierConnectionLog extends Model
{
    protected $fillable = ['supplier_id', 'action', 'outcome', 'message', 'duration_ms'];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
