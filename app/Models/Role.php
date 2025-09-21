<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'guard_name',
        'description',
        'company_id',
    ];

    use HasFactory;

    public const ADMIN = 1;
    public const COMPANY = 2;
    public const BRANCH = 3;
    public const AGENT = 4;
    public const ACCOUNTANT = 5;
    public const CLIENT =   6;
}
