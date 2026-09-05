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

    // ────────────────────────────────────────────────────────────────────────────────────────
    // Canonical-form mutators (post-fix re-verify pass, T14, 2026-09-02). `currency`, `iban` and
    // the two BIC columns are IDENTIFIERS, not free text: their canonical form is uppercase, and
    // an IBAN is stored without its display grouping spaces (ISO 13616 print format is a display
    // convention only). Normalizing here rather than only in SupplierController means EVERY
    // writer gets it -- a seeder, a future supplier import, an artisan/tinker fix-up, a factory,
    // or a direct `SupplierBankDetail::create()` (which this task's own tests use) -- not just
    // the one HTTP CRUD path. `ValidIban`/`ValidSwiftBic` normalize their own local copy for the
    // shape/mod-97 check but never write back, so without this a validation-passing
    // "kw81 cbku ..." is persisted with its spaces intact and breaks exact-match lookups on the
    // column (column collation is utf8mb4_unicode_ci, so case alone is forgiving -- spaces are
    // not). See `SupplierBankDetailAdversarialTest`.
    // ────────────────────────────────────────────────────────────────────────────────────────

    public function setCurrencyAttribute(mixed $value): void
    {
        $this->attributes['currency'] = ($value === null || $value === '') ? $value : mb_strtoupper(trim((string) $value));
    }

    public function setIbanAttribute(mixed $value): void
    {
        $this->attributes['iban'] = ($value === null || $value === '') ? $value : strtoupper(str_replace(' ', '', trim((string) $value)));
    }

    public function setSwiftBicAttribute(mixed $value): void
    {
        $this->attributes['swift_bic'] = ($value === null || $value === '') ? $value : strtoupper(trim((string) $value));
    }

    public function setIntermediarySwiftBicAttribute(mixed $value): void
    {
        $this->attributes['intermediary_swift_bic'] = ($value === null || $value === '') ? $value : strtoupper(trim((string) $value));
    }

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
