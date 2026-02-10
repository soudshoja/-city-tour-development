<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class DocumentError extends Model
{
    protected $fillable = [
        'document_processing_log_id',
        'error_type',
        'error_code',
        'error_message',
        'stack_trace',
        'input_context',
        'retry_count',
        'last_retry_at',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
    ];

    protected $casts = [
        'input_context' => 'array',
        'retry_count' => 'integer',
        'last_retry_at' => 'datetime',
        'resolved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Error type constants
     */
    const TYPE_TRANSIENT = 'transient';
    const TYPE_NON_TRANSIENT = 'non_transient';
    const TYPE_SYSTEM = 'system';

    /**
     * Relationship to DocumentProcessingLog
     */
    public function documentProcessingLog(): BelongsTo
    {
        return $this->belongsTo(DocumentProcessingLog::class);
    }

    /**
     * Relationship to resolver (User)
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Scope: Unresolved errors
     */
    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    /**
     * Scope: Filter by error type
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('error_type', $type);
    }

    /**
     * Scope: Recent errors (last N days)
     */
    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope: Transient errors
     */
    public function scopeTransient(Builder $query): Builder
    {
        return $query->where('error_type', self::TYPE_TRANSIENT);
    }

    /**
     * Scope: Non-transient errors
     */
    public function scopeNonTransient(Builder $query): Builder
    {
        return $query->where('error_type', self::TYPE_NON_TRANSIENT);
    }

    /**
     * Scope: System errors
     */
    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('error_type', self::TYPE_SYSTEM);
    }

    /**
     * Check if error is transient (retriable)
     */
    public function isTransient(): bool
    {
        return $this->error_type === self::TYPE_TRANSIENT;
    }

    /**
     * Check if error is resolved
     */
    public function isResolved(): bool
    {
        return !is_null($this->resolved_at);
    }

    /**
     * Mark error as resolved
     */
    public function markAsResolved(int $userId, string $notes = null): void
    {
        $this->update([
            'resolved_at' => now(),
            'resolved_by' => $userId,
            'resolution_notes' => $notes,
        ]);
    }

    /**
     * Increment retry count
     */
    public function incrementRetry(): void
    {
        $this->increment('retry_count');
        $this->update(['last_retry_at' => now()]);
    }
}
