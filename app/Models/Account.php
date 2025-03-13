<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
       'name', 
       'level', 
       'actual_balance',
       'budget_balance',    
       'variance',  
       'parent_id', 
       'company_id', 
       'reference_id', 
       'code',
    ];

    public function parent()
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id'); 
    }
    
    public function agent()
    {
        return $this->hasOne(Agent::class, 'account_id');
    }

    public function client()
    {
        return $this->hasOne(Agent::class, 'account_id');
    }
}