<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    protected $fillable = ['name', 'group'];

    public static function getGroupedByGroup()
    {
        return self::all()->groupBy('group');
    }
}
