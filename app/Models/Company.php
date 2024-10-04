<?php


// app/Models/Agent.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'user_id','status', 'code', 'email', 'address', 'phone', 'nationality','status'];

    public function agents()
    {
        return $this->hasMany(Agent::class);
    }
}