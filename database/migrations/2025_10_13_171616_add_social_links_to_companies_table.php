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
        Schema::table('companies', function (Blueprint $table) {
            $table->string('facebook')->nullable()->after('logo');
            $table->string('instagram')->nullable()->after('facebook');
            $table->string('snapchat')->nullable()->after('instagram');
            $table->string('tiktok')->nullable()->after('snapchat');
            $table->string('whatsapp')->nullable()->after('tiktok');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['facebook', 'instagram', 'snapchat', 'tiktok', 'whatsapp']);
        });
    }
};
