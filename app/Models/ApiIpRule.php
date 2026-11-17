<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiIpRule extends Model
{
    protected $fillable = ['api_credential_id', 'ip_cidr'];

    public function apiCredential()
    {
        return $this->belongsTo(ApiCredential::class);
    }
}
