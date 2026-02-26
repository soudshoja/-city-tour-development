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
        Schema::table('settings', function (Blueprint $table) {
            // Drop the old unique on key alone (prevents same key for different companies)
            $table->dropUnique(['key']);

            // Add composite unique on key + company_id (allows same key per company)
            $table->unique(['key', 'company_id'], 'settings_key_company_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique('settings_key_company_unique');
            $table->unique('key');
        });
    }
};
