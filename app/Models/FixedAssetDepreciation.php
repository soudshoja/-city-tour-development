<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * accounting-builds T3 (M3, Lane B): one asset-month row {@see \App\Services\Accounting\FixedAssets\
 * DepreciationRunService} posted. See the creating migration's own docblock — this table is a
 * posting LOG, never read back as an NBV input (L8).
 */
class FixedAssetDepreciation extends Model
{
    public const STATUS_POSTED = 'posted';

    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'fixed_asset_id',
        'period_year',
        'period_month',
        'amount',
        'transaction_id',
        'status',
    ];

    protected $casts = [
        'fixed_asset_id' => 'integer',
        'period_year' => 'integer',
        'period_month' => 'integer',
        'amount' => 'decimal:3',
        'transaction_id' => 'integer',
    ];

    public function scopeForPeriod(Builder $query, int $year, int $month): Builder
    {
        return $query->where('period_year', $year)->where('period_month', $month);
    }

    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class);
    }
}
