<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DotwRoom Model
 *
 * Stores individual room details within a DOTW pre-booking
 * Tracks occupancy and passenger information
 *
 * Similar to TBORoom model but for DOTW integration
 *
 * @property int $id
 * @property int $dotw_preboot_id Foreign key to DotwPrebook
 * @property int $room_number Room sequence number (0-indexed)
 * @property int $adults_count Number of adults in this room
 * @property int $children_count Number of children in this room
 * @property array $children_ages Ages of children in this room
 * @property string|null $passenger_nationality Passenger nationality code
 * @property string|null $passenger_residence Country of residence code
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class DotwRoom extends Model
{
    protected $table = 'dotw_rooms';

    protected $fillable = [
        'dotw_preboot_id',
        'room_number',
        'adults_count',
        'children_count',
        'children_ages',
        'passenger_nationality',
        'passenger_residence',
    ];

    protected $casts = [
        'adults_count' => 'integer',
        'children_count' => 'integer',
        'children_ages' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship: Room belongs to pre-booking
     *
     * @return BelongsTo
     */
    public function prebook(): BelongsTo
    {
        return $this->belongsTo(DotwPrebook::class, 'dotw_preboot_id');
    }

    /**
     * Get total occupancy (adults + children)
     *
     * @return int Total number of guests in this room
     */
    public function getTotalOccupancy(): int
    {
        return $this->adults_count + $this->children_count;
    }

    /**
     * Get occupancy description
     *
     * Example: "2 adults, 1 child (age 8)"
     *
     * @return string Human-readable occupancy description
     */
    public function getOccupancyDescription(): string
    {
        $parts = [];

        if ($this->adults_count > 0) {
            $parts[] = $this->adults_count . ' adult' . ($this->adults_count > 1 ? 's' : '');
        }

        if ($this->children_count > 0) {
            $parts[] = $this->children_count . ' child' . ($this->children_count > 1 ? 'ren' : '');

            if (!empty($this->children_ages)) {
                $agesStr = implode(', ', $this->children_ages);
                $parts[] = "(age{$this->children_count > 1 ? 's' : ''} {$agesStr})";
            }
        }

        return implode(', ', $parts) ?: 'Empty room';
    }
}
