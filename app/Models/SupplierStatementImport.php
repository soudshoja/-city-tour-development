<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * accounting-builds T8 (Lane E), migration M5. One row per uploaded supplier statement (DOTW
 * this task). See the migration's own docblock for `content_hash` idempotency and `column_map`
 * provenance. Read + state only — never writes journal_entries/transactions.
 */
class SupplierStatementImport extends Model
{
    use BelongsToCompany;

    public const STATUS_STAGED = 'staged';

    public const STATUS_MATCHED = 'matched';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'company_id',
        'supplier_id',
        'file_name',
        'statement_currency',
        'period_from',
        'period_to',
        'statement_reference',
        'content_hash',
        'column_map',
        'status',
        'counts',
        'imported_by',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'supplier_id' => 'integer',
        'period_from' => 'date',
        'period_to' => 'date',
        'column_map' => 'array',
        'counts' => 'array',
        'imported_by' => 'integer',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(SupplierStatementImportLine::class, 'import_id');
    }

    public function supplier(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
