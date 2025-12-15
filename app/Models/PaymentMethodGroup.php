<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethodGroup extends Model
{
    protected $fillable = ['name'];

    public function paymentMethods()
    {
        return $this->hasMany(PaymentMethod::class, 'payment_method_group_id');
    }
}
