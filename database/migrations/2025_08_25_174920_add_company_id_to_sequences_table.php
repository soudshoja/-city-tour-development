<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sequences', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id');
        });

        DB::table('sequences')->whereNull('company_id')->update(['company_id' => 1]);

        Schema::table('sequences', function (Blueprint $table) {
            $table->unique('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->unsignedBigInteger('company_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sequences', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropUnique(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
