<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_SENT_TO_SUPPLIER = 'sent_to_supplier';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_UNKNOWN = 'unknown'; // supplier timeout / ambiguous — never auto-refunded
    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'order_number', 'user_id', 'service_id', 'price_charged', 'currency',
        'status', 'idempotency_key', 'placed_via', 'api_credential_id', 'completed_at',
    ];

    protected $casts = [
        'price_charged' => 'decimal:4',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->order_number ??= 'F94-'.Str::upper(Str::random(10));
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function inputs()
    {
        return $this->hasMany(OrderInput::class);
    }

    public function events()
    {
        return $this->hasMany(OrderEvent::class);
    }

    public function supplierOrders()
    {
        return $this->hasMany(SupplierOrder::class);
    }

    public function apiCredential()
    {
        return $this->belongsTo(ApiCredential::class);
    }

    public function isFinal(): bool
    {
        return in_array($this->status, [self::STATUS_SUCCESS, self::STATUS_FAILED, self::STATUS_REFUNDED], true);
    }

    public function recordEvent(string $event, array $context = []): OrderEvent
    {
        return $this->events()->create(['event' => $event, 'context' => $context]);
    }
}
