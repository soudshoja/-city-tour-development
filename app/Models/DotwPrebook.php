<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * DotwPrebook Model
 *
 * Stores pre-booking allocation data from DOTW V4 API
 * Tracks temporary rate locks with 3-minute expiry
 *
 * Similar to TBO model but for DOTW integration
 *
 * @property int $id
 * @property string $prebook_key Unique allocation identifier
 * @property string $allocation_details Opaque allocation token from DOTW
 * @property string $hotel_code DOTW hotel identifier
 * @property string $hotel_name Hotel name
 * @property string $room_type Room type code
 * @property int $room_quantity Number of rooms
 * @property float $total_fare Total price
 * @property float $total_tax Tax amount
 * @property string $original_currency Original currency from API
 * @property float $exchange_rate Exchange rate applied (if different currency requested)
 * @property string $room_rate_basis Rate basis (1331=RoomOnly, 1332=BB, 1333=HB, etc.)
 * @property bool $is_refundable Refundability flag
 * @property string $customer_reference Client's booking reference
 * @property array $booking_details JSON stored booking details
 * @property \Carbon\Carbon $created_at Allocation created timestamp
 * @property \Carbon\Carbon $updated_at Last update timestamp
 * @property \Carbon\Carbon|null $expired_at When allocation expired (3 minutes after creation)
 */
class DotwPrebook extends Model
{
    protected $table = 'dotw_prebooks';

    protected $fillable = [
        'prebook_key',
        'allocation_details',
        'hotel_code',
        'hotel_name',
        'room_type',
        'room_quantity',
        'total_fare',
        'total_tax',
        'original_currency',
        'exchange_rate',
        'room_rate_basis',
        'is_refundable',
        'customer_reference',
        'booking_details',
        'expired_at',
    ];

    protected $casts = [
        'total_fare' => 'float',
        'total_tax' => 'float',
        'exchange_rate' => 'float',
        'is_refundable' => 'boolean',
        'booking_details' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    /**
     * Relationship: Pre-booking has many rooms
     *
     * @return HasMany
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(DotwRoom::class, 'dotw_preboot_id');
    }

    /**
     * Check if allocation is still valid (not expired)
     *
     * DOTW allocations expire after 3 minutes as per spec
     *
     * @return bool True if allocation is still valid
     */
    public function isValid(): bool
    {
        if ($this->expired_at) {
            return now()->isBefore($this->expired_at);
        }

        // If no expiry set, calculate from created_at
        $expiryMinutes = config('dotw.allocation_expiry_minutes', 3);
        return now()->diffInMinutes($this->created_at) < $expiryMinutes;
    }

    /**
     * Calculate and set expiry timestamp
     *
     * Sets expired_at to current time + allocation_expiry_minutes
     * Called when allocation is first created
     *
     * @return void
     */
    public function setExpiry(): void
    {
        $expiryMinutes = config('dotw.allocation_expiry_minutes', 3);
        $this->expired_at = now()->addMinutes($expiryMinutes);
        $this->save();
    }

    /**
     * Mark allocation as expired
     *
     * Called when allocation is used or times out
     *
     * @return void
     */
    public function markExpired(): void
    {
        $this->expired_at = now();
        $this->save();
    }

    /**
     * Get all valid (non-expired) allocations
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function valid()
    {
        $expiryMinutes = config('dotw.allocation_expiry_minutes', 3);

        return static::where(function ($query) use ($expiryMinutes) {
            $query->whereNull('expired_at')
                ->where('created_at', '>', now()->subMinutes($expiryMinutes));
        })->orWhere(function ($query) {
            $query->whereNotNull('expired_at')
                ->where('expired_at', '>', now());
        });
    }

    /**
     * Clean up expired allocations
     *
     * Call this periodically to remove old expired allocations
     * Typically via scheduled command or cleanup middleware
     *
     * @return int Number of records deleted
     */
    public static function cleanupExpired(): int
    {
        return static::where('expired_at', '<', now()->subHours(1))->delete();
    }
}
