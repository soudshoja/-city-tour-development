<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * W6.S item (4). See database/migrations/2026_08_29_140005_w6s_create_task_webhook_dedupes_table.php
 * for why this is a dedicated table, separate from `webhook_audit_logs`.
 */
class TaskWebhookDedupe extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'webhook_client_id',
        'payload_hash',
        'task_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
