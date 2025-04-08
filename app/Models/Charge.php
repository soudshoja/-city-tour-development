<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Charge extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'description',
        'amount',
        'company_id',
        'branch_id',
        'acc_bank_id',
        'acc_fee_id',
        'acc_fee_bank_id',
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


}
