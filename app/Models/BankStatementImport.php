<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * accounting-builds T9 (Wave 2), migration M6. One row per uploaded bank statement, scoped to
 * exactly one bank leaf ({@see self::$bank_account_id}). See the migration's own docblock for
 * `content_hash` idempotency/conflict semantics and `column_map` provenance. Read + state only —
 * never writes journal_entries/transactions.
 */
class BankStatementImport extends Model
{
    use BelongsToCompany;

    public const STATUS_STAGED = 'staged';

    public const STATUS_MATCHED = 'matched';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'company_id',
        'bank_account_id',
        'file_name',
        'statement_currency',
        'statement_from',
        'statement_to',
        'opening_balance',
        'closing_balance',
        'statement_reference',
        'content_hash',
        'column_map',
        'status',
        'counts',
        'imported_by',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'bank_account_id' => 'integer',
        'statement_from' => 'date',
        'statement_to' => 'date',
        'opening_balance' => 'float',
        'closing_balance' => 'float',
        'column_map' => 'array',
        'counts' => 'array',
        'imported_by' => 'integer',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(BankStatementImportLine::class, 'import_id');
    }

    public function bankAccount(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Account::class, 'bank_account_id');
    }
}
