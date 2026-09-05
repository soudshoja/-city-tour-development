<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * accounting-builds T7 (Lane D, M4): one real gateway payout batch. See the creating migration's
 * own docblock and {@see \App\Services\Accounting\GatewaySettlementService}.
 */
class GatewaySettlement extends Model
{
    public const STATUS_RECORDED = 'recorded';

    public const STATUS_POSTED = 'posted';

    public const STATUS_FAILED = 'failed';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_CSV = 'csv';

    public const SOURCE_API = 'api';

    protected $fillable = [
        'company_id',
        'gateway',
        'settlement_channel',
        'payout_reference',
        'payout_date',
        'gross',
        'fee',
        'net',
        'recognised_fee',
        'currency',
        'bank_account_id',
        'status',
        'transaction_id',
        'imported_by',
        'source',
        'raw',
        'failure_reason',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'payout_date' => 'date',
        'gross' => 'float',
        'fee' => 'float',
        'net' => 'float',
        'recognised_fee' => 'float',
        'bank_account_id' => 'integer',
        'transaction_id' => 'integer',
        'imported_by' => 'integer',
        'raw' => 'array',
    ];

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function bankAccount(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Account::class, 'bank_account_id');
    }
}
