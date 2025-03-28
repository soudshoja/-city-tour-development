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
        Schema::rename('general_ledgers', 'journal_entries');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('journal_entries', 'general_ledgers');
    }
};
