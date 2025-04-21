<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('agent_id')->nullable()->after('id')->constrained('agents')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->after('agent_id')->constrained('clients')->nullOnDelete();
            $table->string('notes')->nullable()->after('payment_date');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);
            $table->dropForeign(['client_id']);
            $table->dropColumn('agent_id');
            $table->dropColumn('client_id');
            $table->dropColumn('notes');
        });
    }
};
