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
        // Check if the 'email' column does not already exist before adding it
        if (!Schema::hasColumn('clients', 'email')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->string('email')->nullable()->after('name');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Check if the 'email' column exists before trying to drop it
            if (Schema::hasColumn('clients', 'email')) {
                $table->dropColumn('email');
            }
        });
    }
};