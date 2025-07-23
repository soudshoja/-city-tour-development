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
        // Add soft deletes to tasks table
        Schema::table('tasks', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Add soft deletes to journal_entries table
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Add soft deletes to transactions table
        Schema::table('transactions', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Add soft deletes to invoices table
        Schema::table('invoices', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Add soft deletes to invoice_details table
        Schema::table('invoice_details', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Add soft deletes to payments table
        Schema::table('payments', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Add soft deletes to task_flight_details table
        Schema::table('task_flight_details', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Note: task_hotel_details already has soft deletes, so we skip it
        // Schema::table('task_hotel_details', function (Blueprint $table) {
        //     $table->softDeletes();
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove soft deletes from tasks table
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        // Remove soft deletes from journal_entries table
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        // Remove soft deletes from transactions table
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        // Remove soft deletes from invoices table
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        // Remove soft deletes from invoice_details table
        Schema::table('invoice_details', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        // Remove soft deletes from payments table
        Schema::table('payments', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        // Remove soft deletes from task_flight_details table
        Schema::table('task_flight_details', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
