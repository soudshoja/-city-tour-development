<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * P2.5.A (p2_5-brief.md, period-lock-design.md §2/§14): one calendar period per company, the row
 * {@see \App\Services\Accounting\PeriodGuard} resolves against. See the creating migration's own
 * docblock for the full column rationale (in particular {@see self::ANNUAL_MONTH}).
 */
class AccountingPeriod extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_SOFT_CLOSED = 'soft_closed';
    public const STATUS_LOCKED = 'locked';

    /**
     * Sentinel `month` value used only when `config('accounting.period.length') === 'annual'` --
     * represents "the whole year" as a single row/lockable unit rather than 1-12. Never a real
     * calendar month; chosen as 0 (outside the 1-12 range) rather than leaving `month` nullable so
     * the table's `unique(company_id, year, month)` index stays meaningful under both length modes
     * (see the migration's own docblock for why a nullable month would not have worked).
     */
    public const ANNUAL_MONTH = 0;

    protected $fillable = [
        'company_id',
        'year',
        'month',
        'status',
        'closed_by',
        'closed_at',
        'reopened_by',
        'reopened_at',
        'reopen_reason',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'closed_at' => 'datetime',
        'reopened_at' => 'datetime',
    ];

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isSoftClosed(): bool
    {
        return $this->status === self::STATUS_SOFT_CLOSED;
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }
}
