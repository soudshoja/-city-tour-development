<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookSecret extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'webhook_client_id',
        'secret_hash',
        'secret_preview',
        'algorithm',
        'is_active',
        'rotation_scheduled_at',
        'grace_period_until',
        'created_at',
        'deactivated_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'rotation_scheduled_at' => 'datetime',
        'grace_period_until' => 'datetime',
        'deactivated_at' => 'datetime',
    ];

    public function webhookClient(): BelongsTo
    {
        return $this->belongsTo(WebhookClient::class);
    }

    /**
     * Display secret preview with masking
     */
    public function getSecretPreviewAttribute($value)
    {
        return "***" . $value;
    }
}
