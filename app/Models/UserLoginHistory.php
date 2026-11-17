<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserLoginHistory extends Model
{
    public $timestamps = false;
    protected $fillable = ['user_id', 'ip_address', 'user_agent', 'successful', 'failure_reason', 'created_at'];
    protected $casts = ['successful' => 'boolean', 'created_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
