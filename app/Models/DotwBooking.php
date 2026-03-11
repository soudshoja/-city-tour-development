<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * DotwBooking — immutable record of a DOTW hotel booking confirmation.
 *
 * No FK to companies (standalone DOTW module per MOD-06).
 * UPDATED_AT = null — booking records are append-only after creation.
 *
 * @property int $id
 * @property string $prebook_key UUID reference to dotw_prebooks.prebook_key
 * @property string|null $confirmation_code DOTW bookingCode from confirmBooking response
 * @property string|null $confirmation_number DOTW confirmationNumber (secondary reference)
 * @property string $customer_reference UUID generated and sent to DOTW as customerReference
 * @property string $booking_status 'confirmed' | 'failed'
 * @property array $passengers Passenger details array
 * @property array $hotel_details Hotel and rate context snapshot
 * @property string|null $resayil_message_id WhatsApp message traceability
 * @property string|null $resayil_quote_id WhatsApp quoted message traceability
 * @property int|null $company_id Company context (no FK constraint)
 * @property \Illuminate\Support\Carbon $created_at
 */
class DotwBooking extends Model
{
    public const UPDATED_AT = null;  // Booking records are immutable after creation

    protected $table = 'dotw_bookings';

    protected $fillable = [
        'prebook_key',
        'confirmation_code',
        'confirmation_number',
        'customer_reference',
        'booking_status',
        'passengers',
        'hotel_details',
        'resayil_message_id',
        'resayil_quote_id',
        'company_id',
    ];

    protected $casts = [
        'passengers' => 'array',
        'hotel_details' => 'array',
        'company_id' => 'integer',
    ];
}
