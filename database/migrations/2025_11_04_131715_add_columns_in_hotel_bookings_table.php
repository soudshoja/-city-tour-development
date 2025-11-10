<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->nullable()->after('prebook_id');
            $table->unsignedBigInteger('payment_id')->nullable()->after('client_id');

            $table->foreign('client_id')->references('id')->on('clients')->onDelete('set null');
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_bookings', function (Blueprint $table) {
            $table->dropColumn('client_id');
            $table->dropColumn('payment_id');
        });
    }
};
