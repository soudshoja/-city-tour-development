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

    public function paymentMethodGroup()
    {
        return $this->belongsTo(PaymentMethodGroup::class, 'payment_method_group_id');
    }

    public function paymentLinks()
    {
        return $this->belongsToMany(Payment::class, 'payment_link_payment_method')
            ->withTimestamps();
    }

    protected static ?int $resolvedCompanyId = null;

    protected static function resolveCompanyId(): ?int
    {
        if (static::$resolvedCompanyId !== null) {
            return static::$resolvedCompanyId;
        }

        $user = Auth::user();

        if (!$user) {
            return null;
        }

        static::$resolvedCompanyId = getCompanyId($user);

        return static::$resolvedCompanyId;
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
