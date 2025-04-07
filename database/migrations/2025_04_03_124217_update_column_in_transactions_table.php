<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->change();
            $table->foreignId('company_id')->nullable()->after('branch_id')->constrained('companies');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->integer('branch_id')->nullable()->change();
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
