<?php

declare(strict_types=1);

namespace App\Modules\AkeedDotwAI\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for the dotw_catalogs snapshot table.
 *
 * Stores every DOTW V4 reference list (classifications, chains, locations,
 * amenities, leisure, business, preferences, meal plans, room types,
 * specials, and salutations) keyed by (type, code).
 *
 * Populated by CatalogSyncService (Phase 34). Read by StarRatingResolver
 * (Phase 35) and AgentFilterResolver (Phase 37).
 *
 * @property string $type  One of DotwCatalog::ALL_TYPES
 * @property string $code  DOTW short numeric or word-keyed ID (VARCHAR 64)
 * @property string $name  Human-readable label
 * @property string|null $category  Sub-category (used for amenity/leisure/business)
 * @property array|null $payload  Reserved for richer future endpoints
 * @property \Illuminate\Support\Carbon $synced_at  Last sync timestamp
 */
class DotwCatalog extends Model
{
    public const TYPE_CLASSIFICATION = 'classification';
    public const TYPE_CHAIN          = 'chain';
    public const TYPE_LOCATION       = 'location';
    public const TYPE_AMENITY        = 'amenity';
    public const TYPE_LEISURE        = 'leisure';
    public const TYPE_BUSINESS       = 'business';
    public const TYPE_PREFERENCE     = 'preference';
    public const TYPE_MEAL_PLAN      = 'meal_plan';
    public const TYPE_ROOM_TYPE      = 'room_type';
    public const TYPE_SPECIAL        = 'special';
    public const TYPE_SALUTATION     = 'salutation';

    /**
     * All valid catalog type values.
     *
     * Used by SyncCatalogsCommand to validate --type= input and by
     * CatalogSyncService as the canonical dispatch list.
     *
     * @var array<string>
     */
    public const ALL_TYPES = [
        self::TYPE_CLASSIFICATION,
        self::TYPE_CHAIN,
        self::TYPE_LOCATION,
        self::TYPE_AMENITY,
        self::TYPE_LEISURE,
        self::TYPE_BUSINESS,
        self::TYPE_PREFERENCE,
        self::TYPE_MEAL_PLAN,
        self::TYPE_ROOM_TYPE,
        self::TYPE_SPECIAL,
        self::TYPE_SALUTATION,
    ];

    protected $table = 'dotw_catalogs';

    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'payload'   => 'array',
        'synced_at' => 'datetime',
    ];

    /**
     * Scope to rows of a specific catalog type.
     *
     * Usage: DotwCatalog::ofType('chain')->get()
     */
    public function scopeOfType(Builder $q, string $type): Builder
    {
        return $q->where('type', $type);
    }
}
