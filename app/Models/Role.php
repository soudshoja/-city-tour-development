<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'name',
        'permissions',
    ];

    public const ADMIN = 1;
    public const COMPANY = 2;
    public const AGENT = 3;
    public const ACCOUNTANT = 4;
    public const CLIENT = 5;
}
