<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ApiCredential extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'label', 'public_key', 'secret_hash', 'scopes',
        'rate_limit_per_minute', 'is_active', 'last_used_at',
    ];

    protected $casts = [
        'scopes' => 'array',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ipRules()
    {
        return $this->hasMany(ApiIpRule::class);
    }

    public function usageLogs()
    {
        return $this->hasMany(ApiUsageLog::class);
    }

    /**
     * Generates a new public/secret pair. The raw secret is returned once —
     * only its hash is persisted. Never log or expose it again after this call.
     */
    public static function generate(User $user, array $attributes = []): array
    {
        $secret = Str::random(40);

        $credential = static::create(array_merge([
            'user_id' => $user->id,
            'public_key' => 'f94_'.Str::random(24),
            'secret_hash' => Hash::make($secret),
            'is_active' => true,
        ], $attributes));

        return ['credential' => $credential, 'secret' => $secret];
    }

    public function verifySecret(string $secret): bool
    {
        return Hash::check($secret, $this->secret_hash);
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
    }
}
