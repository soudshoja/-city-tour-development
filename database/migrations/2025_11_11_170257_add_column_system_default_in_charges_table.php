<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->boolean('is_system_default')->default(false)->after('can_generate_link');
            $table->boolean('can_be_deleted')->default(true)->after('is_system_default');
            $table->enum('enabled_by', ['admin', 'company'])->nullable()->after('can_be_deleted');
        });
    }

    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->dropColumn(['is_system_default', 'can_be_deleted', 'enabled_by']);
        });
    }
};
