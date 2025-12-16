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

    public function activePaymentMethods()
    {
        return $this->hasMany(PaymentMethod::class, 'payment_method_group_id')
            ->where('is_active', 1);
    }

    /**
     * Get the chosen payment method for this group and company
     */
    public function chosenMethod()
    {
        return $this->hasOne(PaymentMethodChose::class, 'payment_method_group_id');
    }

    /**
     * Get the current active/chosen payment method for a specific company
     * Uses PaymentMethodChose to determine which method is currently selected
     */
    public function getCurrentActiveMethod($companyId)
    {
        $chose = PaymentMethodChose::where('company_id', $companyId)
            ->where('payment_method_group_id', $this->id)
            ->first();

        if ($chose && $chose->paymentMethod && $chose->paymentMethod->is_active) {
            return $chose->paymentMethod;
        }

        // Fallback: return first active method in this group if no choice recorded
        return $this->hasMany(PaymentMethod::class, 'payment_method_group_id')
            ->where('company_id', $companyId)
            ->where('is_active', 1)
            ->first();
    }

    public function paymentLinks()
    {
        return $this->belongsToMany(Payment::class, 'payment_link_payment_method_group')
            ->withTimestamps();
    }
}
