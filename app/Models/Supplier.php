<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'driver', 'mode', 'is_active', 'priority'];
    protected $casts = ['is_active' => 'boolean'];

    public function credentials()
    {
        return $this->hasOne(SupplierCredential::class);
    }

    public function capabilities()
    {
        return $this->hasMany(SupplierCapability::class);
    }

    public function connectionLogs()
    {
        return $this->hasMany(SupplierConnectionLog::class);
    }

    public function serviceMappings()
    {
        return $this->hasMany(SupplierServiceMapping::class);
    }

    public function supports(string $capability): bool
    {
        return $this->capabilities()->where('capability', $capability)->where('supported', true)->exists();
    }

    public function isMocked(): bool
    {
        return $this->mode === 'mock';
    }
}
