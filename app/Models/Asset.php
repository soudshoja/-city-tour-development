<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
    'purchase_date',
    'asset', 
    'name', 
    'description', 
    'serial_no', 
    'purchase_price',
    'category_id',
    'company_id',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function coacategory()
    {
        return $this->belongsTo(CoaCategory::class, 'category_id');
    }

}