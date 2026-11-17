<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_category_id', 'name', 'slug', 'description', 'base_price',
        'estimated_time_minutes', 'is_active', 'requires_manual_review',
    ];

    protected $casts = [
        'base_price' => 'decimal:4',
        'is_active' => 'boolean',
        'requires_manual_review' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function fields()
    {
        return $this->hasMany(ServiceField::class)->orderBy('sort_order');
    }

    public function supplierMappings()
    {
        return $this->hasMany(SupplierServiceMapping::class);
    }

    public function pricingRules()
    {
        return $this->hasMany(PricingRule::class);
    }

    /**
     * Supplier mappings ordered by priority, active mappings only — used by
     * the order engine to decide which supplier to try first/next.
     */
    public function orderedSupplierMappings()
    {
        return $this->supplierMappings()->where('is_active', true)->orderBy('priority');
    }
}
