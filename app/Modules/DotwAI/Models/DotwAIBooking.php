<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * DotwAI Booking lifecycle model.
 *
 * Tracks every prebook and confirmation attempt end-to-end.
 * Each booking progresses through: prebooked -> (confirming) -> confirmed | failed | cancelled | expired.
 * For payment-required tracks (b2b_gateway, b2c), status moves to pending_payment after prebook.
 *
 * NO foreign key constraints are enforced at the DB level (module isolation).
 * All reference IDs are soft foreign keys stored as nullable bigints.
 *
 * @property string $prebook_key       Unique booking reference (DOTWAI-{UUID})
 * @property string $track             Booking track: 'b2b', 'b2b_gateway', or 'b2c'
 * @property string $status            Current lifecycle status
 * @property int    $company_id        Company performing the booking
 * @property string $agent_phone       WhatsApp phone of the initiating agent
 */
class DotwAIBooking extends Model
{
    /**
     * Lifecycle status constants.
     */
    public const STATUS_PREBOOKED = 'prebooked';
    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_CONFIRMING = 'confirming';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLATION_PENDING = 'cancellation_pending';

    /**
     * Track type constants.
     */
    public const TRACK_B2B = 'b2b';
    public const TRACK_B2B_GATEWAY = 'b2b_gateway';
    public const TRACK_B2C = 'b2c';

    protected $table = 'dotwai_bookings';

    protected $fillable = [
        'prebook_key',
        'dotw_prebook_id',
        'dotw_booking_id',
        'hotel_booking_id',
        'track',
        'status',
        'company_id',
        'agent_phone',
        'client_phone',
        'client_email',
        'hotel_id',
        'hotel_name',
        'city_code',
        'check_in',
        'check_out',
        'original_total_fare',
        'original_currency',
        'display_total_fare',
        'display_currency',
        'markup_percentage',
        'minimum_selling_price',
        'is_refundable',
        'is_apr',
        'cancellation_deadline',
        'cancellation_rules',
        'confirmation_no',
        'booking_ref',
        'payment_id',
        'payment_link',
        'payment_status',
        'payment_gateway_ref',
        'task_id',
        'invoice_id',
        'voucher_sent_at',
        'guest_details',
        'allocation_details',
        'room_type_code',
        'rate_basis_id',
        'nationality_code',
        'residence_code',
        'changed_occupancy',
        'rooms_data',
        'payment_guaranteed_by',
        'special_requests',
    ];

    protected $casts = [
        'check_in'            => 'date',
        'check_out'           => 'date',
        'original_total_fare' => 'decimal:3',
        'display_total_fare'  => 'decimal:3',
        'markup_percentage'   => 'decimal:2',
        'minimum_selling_price' => 'decimal:3',
        'is_refundable'       => 'boolean',
        'is_apr'              => 'boolean',
        'cancellation_deadline' => 'datetime',
        'voucher_sent_at'     => 'datetime',
        'guest_details'       => 'array',
        'changed_occupancy'   => 'array',
        'cancellation_rules'  => 'array',
        'rooms_data'          => 'array',
    ];

    /**
     * Generate a unique prebook key in DOTWAI-{UUID} format.
     *
     * @return string E.g. "DOTWAI-550E8400-E29B-41D4-A716-446655440000"
     */
    public function generatePrebookKey(): string
    {
        return 'DOTWAI-' . strtoupper(Str::uuid()->toString());
    }

    /**
     * Check if this prebook has expired.
     *
     * A prebooked record expires after the configured number of minutes
     * (default 30). Confirmed/failed/cancelled records never expire.
     *
     * @return bool True if expired and should not be confirmed
     */
    public function isExpired(): bool
    {
        if ($this->status !== self::STATUS_PREBOOKED) {
            return false;
        }

        $expiryMinutes = config('dotwai.prebook_expiry_minutes', 30);

        return $this->created_at !== null
            && $this->created_at->diffInMinutes(now()) >= $expiryMinutes;
    }

    /**
     * Check if this booking can proceed to confirmation.
     *
     * A booking can be confirmed if:
     * - Status is 'prebooked' (B2B credit flow), OR
     * - Status is 'pending_payment' AND payment_status is 'paid'
     *
     * @return bool True if confirmation should proceed
     */
    public function canConfirm(): bool
    {
        if ($this->status === self::STATUS_PREBOOKED) {
            return true;
        }

        if ($this->status === self::STATUS_PENDING_PAYMENT
            && $this->payment_status === 'paid') {
            return true;
        }

        return false;
    }
}
