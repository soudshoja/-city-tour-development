<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->decimal('debit', 15, 3)->default(0.00)->change();
            $table->decimal('credit', 15, 3)->default(0.00)->change();
            $table->decimal('balance', 15, 3)->nullable()->change();
            $table->decimal('amount', 15, 3)->default(0.00)->change();
            $table->decimal('original_amount', 15, 3)->nullable()->change();
            $table->decimal('exchange_rate', 15, 6)->default(0.00)->change();
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->decimal('debit', 15, 2)->default(0.00)->change();
            $table->decimal('credit', 15, 2)->default(0.00)->change();
            $table->decimal('balance', 15, 2)->nullable()->change();
            $table->decimal('amount', 10, 2)->default(0.00)->change();
            $table->decimal('original_amount', 10, 3)->nullable()->change();
            $table->decimal('exchange_rate', 10, 4)->default(0.00)->change();
        });
    }
};
