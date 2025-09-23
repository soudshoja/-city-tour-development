<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Accountant extends Model
{
    use HasFactory;

    protected $table = 'accountants';

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'country_code',
        'phone_number',
        'company_id',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function user() 
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
}