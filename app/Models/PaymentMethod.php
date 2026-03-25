<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory, BelongsToCompany;

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
        'extra_charge',
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
}
