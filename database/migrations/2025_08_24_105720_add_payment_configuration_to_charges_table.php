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
        Schema::table('charges', function (Blueprint $table) {
            $table->enum('auth_type', ['basic', 'oauth' ])->default('basic')->after('description');
            $table->string('base_url')->nullable()->after('auth_type');
            $table->text('api_key')->nullable()->after('base_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->dropColumn(['auth_type', 'base_url', 'api_key']);
        });
    }
};
