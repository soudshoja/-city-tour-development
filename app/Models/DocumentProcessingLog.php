<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentProcessingLog extends Model
{
    protected $fillable = [
        'company_id',
        'supplier_id',
        'document_id',
        'document_type',
        'file_path',
        'file_size_bytes',
        'file_hash',
        'status',
        'needs_review',
        'started_at',
        'completed_at',
        'duration_ms',
        'input_payload',
        'output_data',
        'n8n_execution_id',
        'n8n_workflow_id',
        'extraction_result',
        'error_code',
        'error_message',
        'error_context',
        'hmac_signature',
        'callback_received_at',
        'processing_duration_ms',
        'reviewed_at',
        'reviewed_by',
        'review_notes',
    ];

    protected $casts = [
        'extraction_result' => 'array',
        'error_context' => 'array',
        'input_payload' => 'array',
        'output_data' => 'array',
        'needs_review' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'callback_received_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship to Company model
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Relationship to DocumentErrors
     */
    public function errors(): HasMany
    {
        return $this->hasMany(DocumentError::class);
    }

    /**
     * Relationship to reviewer (User)
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Scope for pending documents
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', ['queued', 'processing']);
    }

    /**
     * Scope for failed documents
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for completed documents
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for documents needing manual intervention
     */
    public function scopeNeedsIntervention($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for documents needing review
     */
    public function scopeNeedsReview($query)
    {
        return $query->where('needs_review', true)
            ->whereNull('reviewed_at');
    }

    /**
     * Mark document as retrying (reset to queued)
     */
    public function markAsRetrying(): void
    {
        $this->update([
            'status' => 'queued',
            'error_code' => null,
            'error_message' => null,
            'error_context' => null,
            'n8n_execution_id' => null,
            'n8n_workflow_id' => null,
            'callback_received_at' => null,
        ]);
    }

    /**
     * Mark document as manually resolved
     */
    public function markAsResolved(string $notes = null): void
    {
        $this->update([
            'status' => 'completed',
            'error_code' => null,
            'error_message' => $notes ? 'Manually resolved: ' . $notes : 'Manually resolved',
            'callback_received_at' => now(),
        ]);
    }

    /**
     * Mark document for review (ERR-03)
     */
    public function markForReview(string $errorCode = null, string $errorMessage = null): void
    {
        $this->update([
            'needs_review' => true,
            'status' => 'failed',
            'error_code' => $errorCode ?? $this->error_code,
            'error_message' => $errorMessage ?? $this->error_message,
        ]);
    }

    /**
     * Mark review as completed
     */
    public function markReviewCompleted(int $userId, string $notes = null): void
    {
        $this->update([
            'reviewed_at' => now(),
            'reviewed_by' => $userId,
            'review_notes' => $notes,
        ]);
    }

    /**
     * Calculate execution duration in milliseconds
     */
    public function calculateDuration(): ?int
    {
        if ($this->started_at && $this->completed_at) {
            return $this->started_at->diffInMilliseconds($this->completed_at);
        }

        return null;
    }
}
