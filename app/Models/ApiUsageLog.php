<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiUsageLog extends Model
{
    protected $fillable = [
        'api_credential_id', 'ip_address', 'method', 'path', 'status_code', 'response_time_ms',
    ];

    public function apiCredential()
    {
        return $this->belongsTo(ApiCredential::class);
    }
}
