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
        Schema::create('dotw_prebooks', function (Blueprint $table) {
            $table->id();

            // Allocation tracking
            $table->string('prebook_key')->unique()->comment('Unique allocation identifier');
            $table->longText('allocation_details')->comment('Opaque DOTW allocation token');

            // Hotel information
            $table->string('hotel_code')->comment('DOTW hotel identifier');
            $table->string('hotel_name');

            // Room details
            $table->string('room_type')->comment('Room type code from DOTW');
            $table->integer('room_quantity')->default(1);
            $table->string('room_rate_basis')->comment('Rate basis code (1331=RoomOnly, 1332=BB, etc.)');

            // Pricing
            $table->decimal('total_fare', 12, 2);
            $table->decimal('total_tax', 12, 2)->default(0);
            $table->string('original_currency')->default('USD');
            $table->decimal('exchange_rate', 10, 4)->default(1.0);

            // Refundability
            $table->boolean('is_refundable')->default(true);

            // Client reference
            $table->string('customer_reference')->nullable()->index();

            // Additional booking details (stored as JSON)
            $table->json('booking_details')->nullable();

            // Expiry tracking (3-minute allocation window)
            $table->timestamp('expired_at')->nullable()->comment('Allocation expires at this time');

            // Timestamps
            $table->timestamps();

            // Indexes for performance
            $table->index('hotel_code');
            $table->index('customer_reference');
            $table->index('expired_at');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dotw_prebooks');
    }
};
