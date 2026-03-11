<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dotw_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('prebook_key', 36)->unique();           // Reference to dotw_prebooks.prebook_key — no FK constraint (MOD-06 standalone module)
            $table->string('confirmation_code')->nullable();        // DOTW bookingCode from parseConfirmation()
            $table->string('confirmation_number')->nullable();      // DOTW confirmationNumber (secondary, optional)
            $table->string('customer_reference', 36);              // UUID sent to DOTW as customerReference (Str::uuid())
            $table->string('booking_status', 50)->default('pending'); // 'confirmed' | 'failed'
            $table->json('passengers');                             // Array of passenger detail objects (sanitized — no passport numbers)
            $table->json('hotel_details');                          // hotel_code, hotel_name, checkin, checkout, room_type, total_fare, currency
            $table->string('resayil_message_id')->nullable();       // WhatsApp conversation traceability link
            $table->string('resayil_quote_id')->nullable();         // Quoted WhatsApp message link
            $table->unsignedBigInteger('company_id')->nullable();   // No FK — standalone DOTW module (MOD-06)
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'created_at']);
            $table->index('prebook_key');
            $table->index('confirmation_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dotw_bookings');
    }
};
