<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hotel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'city',
        'state',
        'country',
        'zip_code',
        'phone',
        'email',
        'website',
        'rating',
        'image',
        'description',
    ];

    public function hotelDetails()
    {
        return $this->hasMany(TaskHotelDetail::class);
    }

}
