<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add B2B/B2C track toggle columns to the existing company_dotw_credentials table.
 *
 * These per-company flags override the global dotwai config defaults,
 * allowing individual companies to enable/disable tracks independently.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('company_dotw_credentials', function (Blueprint $table) {
            $table->boolean('b2b_enabled')->default(true)->after('is_active');
            $table->boolean('b2c_enabled')->default(false)->after('b2b_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_dotw_credentials', function (Blueprint $table) {
            $table->dropColumn(['b2b_enabled', 'b2c_enabled']);
        });
    }
};
