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
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('payment_method_account_id')
                ->nullable()
                ->after('payment_type')
                ->constrained('accounts')
                ->onDelete('set null');
        
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['payment_method_account_id']);
            $table->dropColumn('payment_method_account_id');
        });
    }
};
