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
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->change();
            $table->foreignId('agent_id')->nullable()->change();
            $table->foreignId('company_id')->nullable()->change();
            $table->foreignId('supplier_id')->nullable()->change();
            $table->string('type')->nullable()->change();
            $table->string('status')->nullable()->change();
            $table->string('client_name')->nullable()->change();
            $table->string('reference')->nullable()->change();
            $table->string('duration')->nullable()->change();
            $table->string('payment_type')->nullable()->change();
            $table->decimal('price', 10, 2)->nullable()->change();
            $table->decimal('tax', 10, 2)->nullable()->change();
            $table->decimal('surcharge', 10, 2)->nullable()->change();
            $table->decimal('total', 10, 2)->nullable()->change();
            $table->string('cancellation_policy')->nullable()->change();
            $table->text('additional_info')->nullable()->change();
            $table->string('venue')->nullable()->change();
            $table->decimal('invoice_price', 10, 2)->nullable()->change();
            $table->string('voucher_status')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable(false)->change();
            $table->foreignId('agent_id')->nullable(false)->change();
            $table->foreignId('company_id')->nullable(false)->change();
            $table->foreignId('supplier_id')->nullable(false)->change();
            $table->string('type')->nullable(false)->change();
            $table->string('status')->nullable(false)->change();
            $table->string('client_name')->nullable(false)->change();
            $table->string('reference')->nullable(false)->change();
            $table->string('duration')->nullable(false)->change();
            $table->string('payment_type')->nullable(false)->change();
            $table->decimal('price', 10, 2)->nullable(false)->change();
            $table->decimal('tax', 10, 2)->nullable(false)->change();
            $table->decimal('surcharge', 10, 2)->nullable(false)->change();
            $table->decimal('total', 10, 2)->nullable(false)->change();
            $table->string('cancellation_policy')->nullable(false)->change();
            $table->text('additional_info')->nullable(false)->change();
            $table->string('venue')->nullable(false)->change();
            $table->decimal('invoice_price', 10, 2)->nullable(false)->change();
            $table->string('voucher_status')->nullable(false)->change();
        });
    }
};
