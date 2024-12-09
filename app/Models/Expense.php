<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
    'date',
    'type', 
    'amount', 
    'tax', 
    'supplier_id', 
    'customer_id',
    'category_id',
    'reference',
    'notes', 
    'company_id',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'customer_id');
    }

    public function supplier()
    {
        return $this->belongsTo(supplier::class, 'supplier_id');
    }

    public function coacategory()
    {
        return $this->belongsTo(CoaCategory::class, 'category_id');
    }

}