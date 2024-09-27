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
        Schema::table('items', function (Blueprint $table) {
            $table->string('item_code')->nullable();
            $table->timestamp('time_signed')->nullable();
            $table->string('client_email')->nullable();
            $table->string('agent_email')->nullable();
            $table->decimal('total_price', 8, 2)->nullable();
            $table->timestamp('payment_date')->nullable();
            $table->boolean('paid')->default(false);
            $table->timestamp('payment_time')->nullable();
            $table->decimal('payment_amount', 8, 2)->nullable();
            $table->boolean('refunded')->default(false);
            $table->string('trip_name')->nullable();
            $table->string('trip_code')->nullable();

            // Add foreign key constraint if client_id references clients table
            $table->unsignedBigInteger('client_id')->nullable()->change();
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');

            $table->unsignedBigInteger('agent_id')->nullable()->after('client_id');
            $table->foreign('agent_id')->references('id')->on('agents')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropForeign(['agent_id']);
            $table->dropColumn([
                'item_code',
                'time_signed',
                'client_email',
                'agent_email',
                'total_price',
                'payment_date',
                'paid',
                'payment_time',
                'payment_amount',
                'refunded',
                'trip_name',
                'trip_code',
            ]);
        });
    }
};