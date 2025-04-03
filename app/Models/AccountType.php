<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountType extends Model
{
    protected $fillable = [
        'name',
        'created_at',
        'updated_at',
    ];

    public function accounts()
    {
        return $this->hasMany(Account::class);
    }
}
