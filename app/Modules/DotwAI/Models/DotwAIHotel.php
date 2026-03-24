<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * DotwAI Hotel static data model.
 *
 * Stores hotel information imported from DOTW Excel/CSV files via the
 * dotwai:import-hotels artisan command. Used for fuzzy name matching
 * to resolve natural text like "Hilton Dubai" to DOTW hotel IDs.
 *
 * @property int         $id
 * @property string      $dotw_hotel_id   DOTW internal hotel/product ID
 * @property string      $name            Hotel name
 * @property string      $city            City name
 * @property string      $country         Country name
 * @property int|null    $star_rating     Star classification (1-5)
 * @property string|null $address         Hotel address
 * @property float|null  $latitude        GPS latitude
 * @property float|null  $longitude       GPS longitude
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class DotwAIHotel extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'dotwai_hotels';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'dotw_hotel_id',
        'name',
        'city',
        'country',
        'star_rating',
        'address',
        'latitude',
        'longitude',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'star_rating' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
    ];
}
