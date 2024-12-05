<?php


// app/Models/Agent.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = ['id', 'name', 'user_id', 'status', 'code', 'email', 'address', 'phone', 'nationality_id'];

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    public function user()
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }
}

