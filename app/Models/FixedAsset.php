<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * accounting-builds T2 (M2, Lane B): one capitalised fixed asset. See the creating migration's own
 * docblock for the full column rationale — in particular why there is NO `nbv`/`current_value`
 * column (L8: always derived, see {@see \App\Services\Accounting\FixedAssets\FixedAssetService::nbv()}).
 */
class FixedAsset extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_FULLY_DEPRECIATED = 'fully_depreciated';

    public const STATUS_DISPOSED = 'disposed';

    public const METHOD_STRAIGHT_LINE = 'straight_line';

    protected $fillable = [
        'company_id',
        'branch_id',
        'asset_class',
        'name',
        'code',
        'cost',
        'salvage',
        'acquisition_date',
        'in_service_date',
        'useful_life_months',
        'method',
        'status',
        'acquisition_transaction_id',
        'disposal_date',
        'disposal_proceeds',
        'disposal_transaction_id',
        'supplier_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'branch_id' => 'integer',
        'cost' => 'decimal:3',
        'salvage' => 'decimal:3',
        'acquisition_date' => 'date',
        'in_service_date' => 'date',
        'useful_life_months' => 'integer',
        'acquisition_transaction_id' => 'integer',
        'disposal_date' => 'date',
        'disposal_proceeds' => 'decimal:3',
        'disposal_transaction_id' => 'integer',
        'supplier_id' => 'integer',
        'created_by' => 'integer',
    ];

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function depreciations(): HasMany
    {
        return $this->hasMany(FixedAssetDepreciation::class);
    }

    public function isDisposable(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_FULLY_DEPRECIATED], true);
    }
}
