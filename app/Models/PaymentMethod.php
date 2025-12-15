<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'charge_id',
        'myfatoorah_id',
        'company_id',
        'arabic_name',
        'english_name',
        'payment_method_group_id',
        'code',
        'type',
        'is_active',
        'currency',
        'service_charge',
        'self_charge',
        'paid_by',
        'charge_type',
        'description',
        'image',
    ];

    public function charge()
    {
        return $this->belongsTo(Charge::class, 'charge_id');
    }

    public function gateways()
    {
        return $this->belongsTo(Charge::class, 'type', 'name');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    protected static ?int $resolvedCompanyId = null;

    protected static function resolveCompanyId(): ?int
    {
        if (static::$resolvedCompanyId !== null) {
            return static::$resolvedCompanyId;
        }

        $user = Auth::user();
        
        // Return null if no authenticated user (like in tests)
        if (!$user) {
            return null;
        }

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
            // Only apply scope if user is authenticated
            if (Auth::check()) {
                $id = static::resolveCompanyId();
                if ($id !== null) {
                    $q->where($q->qualifyColumn('company_id'), $id);
                }
            }
        });
    }
}
