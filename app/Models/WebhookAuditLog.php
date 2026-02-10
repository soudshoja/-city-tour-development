<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'webhook_client_id',
        'direction',
        'http_method',
        'endpoint',
        'signature_provided',
        'signature_computed',
        'signature_valid',
        'timestamp_provided',
        'timestamp_computed',
        'timestamp_valid',
        'payload_hash',
        'status_code',
        'error_message',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'signature_valid' => 'boolean',
        'timestamp_valid' => 'boolean',
        'timestamp_provided' => 'integer',
        'timestamp_computed' => 'integer',
        'status_code' => 'integer',
        'created_at' => 'datetime',
    ];

    public function webhookClient(): BelongsTo
    {
        return $this->belongsTo(WebhookClient::class);
    }
}
