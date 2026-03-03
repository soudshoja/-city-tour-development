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
            $table->foreignId('company_id')
                ->nullable()
                ->after('id')
                ->constrained('companies')
                ->nullOnDelete();

            $table->index('company_id');
        });

        // Backfill existing payments: agent -> branch -> company
        DB::table('payments')
            ->whereNull('company_id')
            ->whereNotNull('agent_id')
            ->update([
                'company_id' => DB::raw('(
                    SELECT branches.company_id
                    FROM agents
                    JOIN branches ON agents.branch_id = branches.id
                    WHERE agents.id = payments.agent_id
                )'),
            ]);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
