<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientStatus extends Model
{
    use HasFactory;

    //table name in the database
    protected $table = 'client_status';

    protected $fillable = ['name'];

    public const ACTIVE = 1;
    public const INACTIVE = 2;
    public const SUSPENDED = 3;
    public const TERMINATED = 4;
}
