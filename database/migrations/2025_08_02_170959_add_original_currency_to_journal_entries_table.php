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
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->string('original_currency', 3)->nullable()->after('balance')->comment('Original currency of the transaction');
            $table->decimal('original_amount', 10, 3)->nullable()->after('original_currency')->comment('Original amount before currency conversion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn(['original_currency', 'original_amount']);
        });
    }
};
