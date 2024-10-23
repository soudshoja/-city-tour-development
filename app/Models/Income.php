<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Income extends Model
{
    use HasFactory;

    protected $fillable = [
    'date',
    'type', 
    'amount', 
    'tax', 
    'vendor_id', 
    'customer_id',
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

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function coacategory()
    {
        return $this->belongsTo(CoaCategory::class, 'category_id');
    }
}