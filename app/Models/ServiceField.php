<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceField extends Model
{
    protected $fillable = ['service_id', 'key', 'label', 'type', 'options', 'required', 'validation_regex', 'sort_order'];
    protected $casts = ['options' => 'array', 'required' => 'boolean'];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
