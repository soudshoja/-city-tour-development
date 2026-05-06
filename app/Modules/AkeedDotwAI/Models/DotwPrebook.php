<?php

declare(strict_types=1);

namespace App\Modules\AkeedDotwAI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * DotwPrebook — Eloquent model for the `dotw_prebooks` table.
 *
 * Represents a DOTW rate-lock (block) record created by Phase 33's
 * confirm-after-payment flow. Phase 31 (search + browse) never writes to
 * this table — the model is shipped early to establish a clean
 * "models before the service that uses them" dependency chain, and so
 * Phase 31 tests can assert DotwPrebook::count() === 0 after any
 * browse-only path.
 *
 * No BelongsToCompany global scope — the AkeedDotwAI module is single-tenant,
 * pinned to config('akeed_dotwai.company_id').
 *
 * Migration: app/Modules/AkeedDotwAI/Database/Migrations/
 */
class DotwPrebook extends Model
{
    protected $table = 'dotw_prebooks';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @var array<string, string> */
    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
        'min_stay_date' => 'date',
        'expires_at' => 'datetime',
        'cancellation_rules' => 'array',
        'specials' => 'array',
        'changed_occupancy' => 'array',
        'is_refundable' => 'boolean',
        'is_apr' => 'boolean',
        'original_total_fare' => 'decimal:3',
        'price_before_markup' => 'decimal:3',
        'total_fare' => 'decimal:3',
        'markup_percentage' => 'decimal:4',
    ];

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    /**
     * Child rooms belonging to this prebook record.
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(DotwRoom::class, 'dotw_prebook_id');
    }

    /**
     * The hotel booking created by Phase 33's confirm-after-payment job.
     *
     * This relation is populated only after a successful DOTW confirmBooking
     * call; `hotel_booking_id` is nullable until that point.
     */
    public function hotelBooking(): BelongsTo
    {
        return $this->belongsTo(\App\Models\HotelBooking::class, 'hotel_booking_id');
    }
}
