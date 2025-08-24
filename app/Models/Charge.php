<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;

class Charge extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'description',
        'auth_type',
        'base_url',
        'api_key',
        'paid_by',
        'amount',
        'self_charge',
        'is_active',
        'charge_type',
        'company_id',
        'branch_id',
        'acc_bank_id',
        'acc_fee_id',
        'acc_fee_bank_id',
        'is_auto_paid',
        'has_url',
        'can_charge_invoice', // New column added for invoice charge capability
    ];

    public function getAmountAttribute($value)
    {
        return number_format($value, 2);
    }

    public function setAmountAttribute($value)
    {
        $this->attributes['amount'] = str_replace(',', '', $value);
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    } 

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    } 

    public function accFee()
    {
        return $this->belongsTo(Account::class, 'acc_fee_id');
    }
    
    public function accBank()
    {
        return $this->belongsTo(Account::class, 'acc_bank_id');
    }

    public function accBankFee()
    {
        return $this->belongsTo(Account::class, 'acc_fee_bank_id');
    }

    public function methods()
    {
        return $this->hasMany(PaymentMethod::class, 'type');
    }

    protected static ?int $resolvedCompanyId = null;

    protected static function resolveCompanyId(): ?int
    {
        if (static::$resolvedCompanyId !== null) {
            return static::$resolvedCompanyId;
        }

        $user = Auth::user();

        return match ($user->role_id) {
            Role::AGENT => $user->agent?->branch?->company_id ?? $user->company_id ?? $user->company?->id,
            Role::BRANCH => $user->branch?->company_id ?? $user->company_id ?? $user->company?->id,
            Role::COMPANY => $user->company?->id ?? $user->company_id,
            default => $user->company?->id ?? $user->agent?->branch?->company_id ?? $user->branch?->company_id,
        };
    }

    protected static function booted(): void
    {
        static::addGlobalScope('company', function (Builder $q) {
            $id = static::resolveCompanyId();
            if ($id !== null) {
                $q->where($q->qualifyColumn('company_id'), $id);
            }
        });
    }
}
