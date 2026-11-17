<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value', 'type'];

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("system_setting:{$key}", function () use ($key, $default) {
            $row = static::where('key', $key)->first();

            if (! $row) {
                return $default;
            }

            return match ($row->type) {
                'bool' => filter_var($row->value, FILTER_VALIDATE_BOOLEAN),
                'int' => (int) $row->value,
                'json' => json_decode($row->value, true),
                default => $row->value,
            };
        });
    }

    public static function set(string $key, mixed $value, string $type = 'string'): void
    {
        static::updateOrCreate(['key' => $key], [
            'value' => $type === 'json' ? json_encode($value) : $value,
            'type' => $type,
        ]);

        Cache::forget("system_setting:{$key}");
    }
}
