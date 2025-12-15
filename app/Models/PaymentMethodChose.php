<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethodChose extends Model
{
    protected $fillable = [
        'company_id',
        'payment_method_group_id',
        'payment_method_id',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function paymentMethodGroup()
    {
        return $this->belongsTo(PaymentMethodGroup::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
