<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderEvent extends Model
{
    public $timestamps = false;
    protected $fillable = ['order_id', 'event', 'context', 'created_at'];
    protected $casts = ['context' => 'array', 'created_at' => 'datetime'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
