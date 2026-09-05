<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'auth_type',
        'is_manual',
        'whatsapp_group',
        'has_hotel',
        'has_flight',
        'has_visa',
        'has_insurance',
        'has_tour',
        'has_cruise',
        'has_car',
        'has_rail',
        'has_esim',
        'has_event',
        'has_lounge',
        'has_ferry',
        'contact_person',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'postal_code',
        'country_id',
        'website',
        'payment_terms',
        'is_online',
        'agency_commission',
    ];

    protected $casts = [
        'is_online' => 'bool',
        'is_manual' => 'bool',
        'agency_commission' => 'decimal:2',
    ];

    public function netOf($retail): float
    {
        $retail = (float) $retail;
        $pct = (float) ($this->agency_commission ?? 0);
        if ($pct <= 0) { return round($retail, 3); }
        return round($retail * (1 - $pct / 100), 3);
    }

    public function payableAccount()
    {
        return $this->hasOne(Account::class, 'supplier_id');
    }
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function companies()
    {
        return $this->belongsToMany(Company::class, 'supplier_companies')
            ->using(SupplierCompany::class)
            ->withPivot('id', 'is_active');
    }

    public function credentials()
    {
        return $this->hasMany(SupplierCredential::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
    public function exchangeRates()
    {
        return $this->hasMany(SupplierExchangeRate::class);
    }

    public function scopeActiveForCompany($query, int $companyId)
    {
        return $query->whereHas('companies', function ($q) use ($companyId) {
            $q->where('companies.id', $companyId)
                ->where('supplier_companies.is_active', 1);
        });
    }

    public function isMergeSupplier(): bool
    {
        if (in_array($this->name, ['TBO Air', 'TBO Car'])) {
            return true;
        }

        if ($this->has_hotel == 1 && $this->name !== 'Amadeus') {
            return true;
        }

        return false;
    }

    public function taskRules()
    {
        return $this->hasMany(TaskRules::class);
    }

    public function procedures()
    {
        return $this->hasMany(SupplierProcedure::class);
    }

    public function supplierCompanies()
    {
        return $this->hasMany(SupplierCompany::class);
    }

    /**
     * T14 "Supplier bank details per currency" (L18). One-to-many remittance details keyed by
     * currency -- see {@see SupplierBankDetail} for the DB-level default-per-currency guard.
     */
    public function bankDetails()
    {
        return $this->hasMany(SupplierBankDetail::class);
    }

    /**
     * The supplier's DEFAULT, active, non-deleted bank detail for one currency -- the exact lookup
     * the supplier payment voucher (BankPaymentController) uses to auto-select remittance details
     * for the voucher's payment currency. Currency is matched case-insensitively but the stored
     * value is always uppercase (see {@see SupplierBankDetail::scopeForCurrency()}); returns null
     * (never a silent fallback to another currency's row) when none exists -- the caller is
     * responsible for surfacing the missing-currency warning.
     */
    public function defaultBankDetailFor(string $currency): ?SupplierBankDetail
    {
        return $this->bankDetails()
            ->active()
            ->default()
            ->forCurrency($currency)
            ->first();
    }
}
