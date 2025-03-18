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
        Schema::table('master', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
            $table->string('value')->nullable()->after('name');
            $table->string('description')->nullable()->after('value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master', function (Blueprint $table) {
            $table->dropColumn('name');
            $table->dropColumn('value');
            $table->dropColumn('description');
        });
    }
};
