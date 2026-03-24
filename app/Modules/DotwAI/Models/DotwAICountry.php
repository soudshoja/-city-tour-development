<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * DotwAI Country static data model.
 *
 * Stores DOTW country codes synced from the DOTW API via the
 * dotwai:sync-static artisan command. Used for nationality and
 * residence code resolution (e.g., "Kuwait" -> DOTW code 66).
 *
 * @property int         $id
 * @property string      $code              DOTW internal country code
 * @property string      $name              Country name
 * @property string|null $nationality_name   Nationality label (e.g., "Kuwaiti")
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class DotwAICountry extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'dotwai_countries';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'name',
        'nationality_name',
    ];
}
