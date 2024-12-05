<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'iso_code'
    ];

    // public function companies()
    // {
    //     return $this->hasMany(Company::class, 'nationality_id');
    // }
}
