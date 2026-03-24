<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * DotwAI City static data model.
 *
 * Stores DOTW city codes synced from the DOTW API via the
 * dotwai:sync-static artisan command. Used for city name resolution
 * (e.g., "Dubai" -> DOTW city code).
 *
 * @property int    $id
 * @property string $code          DOTW internal city code
 * @property string $name          City name
 * @property string $country_code  DOTW country code this city belongs to
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class DotwAICity extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'dotwai_cities';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'name',
        'country_code',
    ];
}
