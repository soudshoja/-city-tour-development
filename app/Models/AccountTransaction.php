<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'description',
        'category_id',
        'company_id',
        'amount',
        'date',
        'tax', 
        'vendor_id', 
        'customer_id',
        'reference',
        'notes', 
    ];

    public function coacategory()
    {
        return $this->belongsTo(CoaCategory::class, 'category_id');
    } 

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
        return $this->belongsTo(Supplier::class, 'vendor_id');
    }


}