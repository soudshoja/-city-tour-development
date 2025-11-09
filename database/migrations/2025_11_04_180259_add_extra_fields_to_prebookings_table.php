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
        Schema::table('prebookings', function (Blueprint $table) {
            $table->json('service_dates')->nullable()->after('remarks');
            $table->json('package')->nullable()->after('service_dates');
            $table->json('payment_methods')->nullable()->after('package');
            $table->json('booking_options')->nullable()->after('payment_methods');
            $table->json('price_breakdown')->nullable()->after('booking_options');
            $table->json('taxes')->nullable()->after('price_breakdown');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prebookings', function (Blueprint $table) {
            $table->dropColumn([
                'package',
                'payment_methods',
                'booking_options',
                'price_breakdown',
                'taxes',
            ]);
        });
    }
};
