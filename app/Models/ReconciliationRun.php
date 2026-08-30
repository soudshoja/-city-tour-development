<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * P2.5.G (p2_5-brief.md §P2.5.G): one `accounting:reconcile --auto` execution (nightly or
 * Run-now). See the creating migration's own docblock.
 */
class ReconciliationRun extends Model
{
    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const TRIGGER_NIGHTLY = 'nightly';

    public const TRIGGER_MANUAL = 'manual';

    protected $fillable = [
        'company_id',
        'status',
        'trigger',
        'triggered_by',
        'started_at',
        'finished_at',
        'proposals_created',
        'auto_matched_pending',
        'exceptions_count',
        'duration_ms',
        'error_message',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'triggered_by' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'proposals_created' => 'integer',
        'auto_matched_pending' => 'integer',
        'exceptions_count' => 'integer',
        'duration_ms' => 'integer',
    ];

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function proposals(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ReconciliationProposal::class, 'run_id');
    }
}
