<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends Model
{
    protected $fillable = [
        'user_id',
        'key',
        'value',
        'type'
    ];

    protected $casts = [
        'value' => 'json',
    ];

    public const KEYS = [
        'theme',
        'invoice_whatsapp_notification',
        'payment_whatsapp_notification',
    ];

    public const DEFAULTS = [
        'theme' => 'light',
        'invoice_whatsapp_notification' => false,
        'payment_whatsapp_notification' => false,
    ];

    public const TYPES = [
        'string',
        'boolean',
        'integer',
        'array'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted()
    {
        static::saving(function ($setting) {
            if (!in_array($setting->type, self::TYPES)) {
                throw new \InvalidArgumentException("Invalid type: {$setting->type}");
            }
            if (!in_array($setting->key, self::KEYS)) {
                throw new \InvalidArgumentException("Invalid key: {$setting->key}");
            }
            // Add more validation, e.g., for specific keys
        });
    }

    public static function getValue(int $userId, string $key, $default = null)
    {
        $fallback = $default ?? (self::DEFAULTS[$key] ?? null);
        $setting = self::where('user_id', $userId)->where('key', $key)->first();
        return $setting ? $setting->value : $fallback;
    }

    public static function setValue(int $userId, string $key, $value, string $type = 'string')
    {
        return self::updateOrCreate(
            ['user_id' => $userId, 'key' => $key],
            ['value' => $value, 'type' => $type]
        );
    }
}