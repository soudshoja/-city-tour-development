<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->decimal('commission', 8, 2)->default(0.00)->after('account_id')->comment('Commission percentage for the agent'); 
            $table->decimal('salary', 10, 2)->default(0.00)->after('commission')->comment('Monthly salary for the agent');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['commission', 'salary']);
        });
    }
};
