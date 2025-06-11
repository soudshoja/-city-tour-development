<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('pay_to')->constrained('users')->onDelete('set null');
        });

        // Set 'created_by' to the user_id of the agent for existing rows
        DB::table('payments')->update([
            'created_by' => DB::raw('(SELECT user_id FROM agents WHERE agents.id = payments.agent_id LIMIT 1)')
        ]);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });
    }
};
