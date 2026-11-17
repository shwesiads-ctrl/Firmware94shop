<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Append-only. See AuditLogger service — never updated or deleted. */
class AuditLog extends Model
{
    public $timestamps = false;
    protected $fillable = ['user_id', 'action', 'auditable_type', 'auditable_id', 'before', 'after', 'ip_address', 'created_at'];
    protected $casts = ['before' => 'array', 'after' => 'array', 'created_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
