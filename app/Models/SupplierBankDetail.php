<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * T14 "Supplier bank details per currency" (accounting-builds PLAN.md §5 T14; L18). One-to-many
 * supplier -> bank remittance details keyed by currency. Master data only -- no ledger write ever
 * originates from this model; see the migration
 * (2026_09_02_000008_create_supplier_bank_details_table.php) for the DB-level "at most one
 * DEFAULT per (supplier, currency)" enforcement via the `default_group` generated column.
 *
 * `is_active = false` is the everyday retire action ("deactivate", never a hard delete per L18);
 * `deleted_at` (SoftDeletes) is the rare true-removal escape hatch. Both are excluded from
 * selection by {@see self::scopeActive()} / the payment-voucher lookup in
 * {@see Supplier::defaultBankDetailFor()}.
 */
class SupplierBankDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'supplier_id',
        'currency',
        'bank_name',
        'beneficiary_name',
        'account_number',
        'iban',
        'swift_bic',
        'bank_country',
        'intermediary_bank_name',
        'intermediary_swift_bic',
        'notes',
        'is_default',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** Rows eligible for anything -- active and not soft-deleted. Soft-deleted rows are excluded
     *  by Eloquent's own default global scope; this scope only adds the `is_active` half. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    public function scopeForCurrency(Builder $query, string $currency): Builder
    {
        return $query->where('currency', mb_strtoupper($currency));
    }
}
