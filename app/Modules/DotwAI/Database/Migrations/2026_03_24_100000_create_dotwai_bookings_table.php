<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the dotwai_bookings table.
 *
 * This table tracks every prebook and confirmation in the DotwAI module.
 * No foreign key constraints are applied (module isolation -- soft FKs only).
 *
 * Indexes:
 * - prebook_key: unique lookup by booking key
 * - [company_id, status]: paginated listing per company per status
 * - agent_phone: lookup bookings by agent phone
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dotwai_bookings', function (Blueprint $table) {
            $table->id();

            // Booking identity
            $table->string('prebook_key')->unique();
            $table->unsignedBigInteger('dotw_prebook_id')->nullable();
            $table->unsignedBigInteger('dotw_booking_id')->nullable();
            $table->unsignedBigInteger('hotel_booking_id')->nullable();

            // Track and status
            $table->string('track', 20)->default('b2b');  // b2b | b2b_gateway | b2c
            $table->string('status', 30)->default('prebooked');

            // Company and contacts
            $table->unsignedBigInteger('company_id');
            $table->string('agent_phone');
            $table->string('client_phone')->nullable();
            $table->string('client_email')->nullable();

            // Hotel details
            $table->string('hotel_id');
            $table->string('hotel_name');
            $table->string('city_code')->nullable();
            $table->date('check_in');
            $table->date('check_out');

            // Pricing -- original = sent to DOTW, display = shown to user (with markup)
            $table->decimal('original_total_fare', 12, 3);
            $table->string('original_currency', 10);
            $table->decimal('display_total_fare', 12, 3);
            $table->string('display_currency', 10);
            $table->decimal('markup_percentage', 5, 2)->default(0);
            $table->decimal('minimum_selling_price', 12, 3)->nullable();

            // Rate properties
            $table->boolean('is_refundable')->default(true);
            $table->boolean('is_apr')->default(false);
            $table->dateTime('cancellation_deadline')->nullable();
            $table->json('cancellation_rules')->nullable();

            // DOTW confirmation output
            $table->string('confirmation_no')->nullable();
            $table->string('booking_ref')->nullable();

            // Payment tracking
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->text('payment_link')->nullable();
            $table->string('payment_status')->nullable();  // null | pending | paid | refunded | credit_applied
            $table->string('payment_gateway_ref')->nullable();

            // Downstream references
            $table->unsignedBigInteger('task_id')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->dateTime('voucher_sent_at')->nullable();

            // Guest and room data (stored for confirm step)
            $table->json('guest_details')->nullable();
            $table->text('allocation_details')->nullable();
            $table->string('room_type_code')->nullable();
            $table->string('rate_basis_id')->nullable();
            $table->string('nationality_code')->nullable();
            $table->string('residence_code')->nullable();
            $table->json('changed_occupancy')->nullable();
            $table->json('rooms_data')->nullable();

            // DOTW response fields
            $table->text('payment_guaranteed_by')->nullable();
            $table->text('special_requests')->nullable();

            $table->timestamps();

            // Indexes -- no foreign key constraints (module isolation)
            $table->index(['company_id', 'status'], 'dotwai_bookings_company_status_idx');
            $table->index('agent_phone', 'dotwai_bookings_agent_phone_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dotwai_bookings');
    }
};
