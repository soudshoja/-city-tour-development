<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Airline extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'name_ar',
        'icao_designator',
        'country_id',
        'accounting_code',
        'alliance',
        'airline_type',
        'is_active',
        'logo_path',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }
}
