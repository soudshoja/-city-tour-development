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
            if (!Schema::hasColumn('items', 'item_code')) {
                $table->string('item_code')->nullable();
            }
            if (!Schema::hasColumn('items', 'time_signed')) {
                $table->timestamp('time_signed')->nullable();
            }
            if (!Schema::hasColumn('items', 'client_email')) {
                $table->string('client_email')->nullable();
            }
            if (!Schema::hasColumn('items', 'agent_email')) {
                $table->string('agent_email')->nullable();
            }
            if (!Schema::hasColumn('items', 'total_price')) {
                $table->decimal('total_price', 8, 2)->nullable();
            }
            if (!Schema::hasColumn('items', 'payment_date')) {
                $table->timestamp('payment_date')->nullable();
            }
            if (!Schema::hasColumn('items', 'paid')) {
                $table->boolean('paid')->default(false);
            }
            if (!Schema::hasColumn('items', 'payment_time')) {
                $table->timestamp('payment_time')->nullable();
            }
            if (!Schema::hasColumn('items', 'payment_amount')) {
                $table->decimal('payment_amount', 8, 2)->nullable();
            }
            if (!Schema::hasColumn('items', 'refunded')) {
                $table->boolean('refunded')->default(false);
            }
            if (!Schema::hasColumn('items', 'trip_name')) {
                $table->string('trip_name')->nullable();
            }
            if (!Schema::hasColumn('items', 'trip_code')) {
                $table->string('trip_code')->nullable();
            }

            // Foreign key setup
            if (!Schema::hasColumn('items', 'client_id')) {
                $table->unsignedBigInteger('client_id')->nullable()->change();
                $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            }

            if (!Schema::hasColumn('items', 'agent_id')) {
                $table->unsignedBigInteger('agent_id')->nullable()->after('client_id');
                $table->foreign('agent_id')->references('id')->on('agents')->onDelete('cascade');
            }
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