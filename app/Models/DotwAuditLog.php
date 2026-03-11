<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * DotwAuditLog Model
 *
 * Eloquent model for the dotw_audit_logs table.
 * Stores sanitized request and response payloads for every DOTW API operation.
 *
 * This model is append-only — audit logs are never updated after creation.
 * Payload columns are automatically JSON-decoded when accessed via Eloquent.
 *
 * Design: standalone module, no relationships to non-DOTW models (MOD-05, MOD-06).
 *
 * @property int $id
 * @property int|null $company_id Company context (nullable — DOTW module is standalone)
 * @property string|null $resayil_message_id WhatsApp message ID from X-Resayil-Message-ID header
 * @property string|null $resayil_quote_id Quoted message ID from X-Resayil-Quote-ID header
 * @property string $operation_type Operation type: search|rates|block|book
 * @property array|null $request_payload Sanitized request payload (JSON-decoded by Eloquent)
 * @property array|null $response_payload Sanitized response payload (JSON-decoded by Eloquent)
 * @property \Carbon\Carbon $created_at Row creation timestamp (no updated_at)
 */
class DotwAuditLog extends Model
{
    /**
     * The database table used by this model.
     */
    protected $table = 'dotw_audit_logs';

    /**
     * This model is append-only — no updated_at column.
     */
    public $timestamps = false;

    /**
     * The name of the "created at" column.
     */
    const CREATED_AT = 'created_at';

    /**
     * No updated_at column — audit logs are immutable.
     */
    const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'company_id',
        'resayil_message_id',
        'resayil_quote_id',
        'operation_type',
        'request_payload',
        'response_payload',
    ];

    /**
     * The attributes that should be cast.
     *
     * payload columns are cast to array so Eloquent automatically
     * JSON-encodes on write and JSON-decodes on read.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'request_payload' => 'array',
        'response_payload' => 'array',
    ];

    /**
     * Create and persist a new audit log entry.
     *
     * Semantic entry point for writing audit logs. Callers should always
     * use this method instead of ::create() directly — it allows future
     * extension (e.g., async queued logging) without changing call sites.
     *
     * @param  array<string, mixed>  $data  Audit log data (must match $fillable)
     * @return static The newly created model instance
     */
    public static function log(array $data): static
    {
        return static::create($data);
    }
}
