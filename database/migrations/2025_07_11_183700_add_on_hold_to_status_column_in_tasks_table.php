<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Add 'on_hold' to the 'status' column
            $table->enum('status', [
                'issued',
                'cancelled',
                'pending',
                'refunded',
                'voided',
                'on_hold', // New status added
            ])->default('pending')->change();

        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Revert 'status' column to its previous state
            $table->enum('status', [
                'issued',
                'cancelled',
                'pending',
                'refunded',
                'voided',
            ])->default('pending')->change();
        });
    }
};
