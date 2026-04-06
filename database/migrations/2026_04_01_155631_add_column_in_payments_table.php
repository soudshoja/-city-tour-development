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
            $table->foreignId('settlement_id')
                ->nullable()
                ->after('agent_id')
                ->constrained('agent_settlements')
                ->nullOnDelete();
        });

        DB::statement('ALTER TABLE payments ADD CONSTRAINT chk_payment_owner
        CHECK(
            (client_id IS NOT NULL AND settlement_id IS NULL) OR
            (client_id IS NULL AND settlement_id IS NOT NULL)
        )');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE payments DROP CHECK chk_payment_owner');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['settlement_id']);
            $table->dropColumn('settlement_id');
        });
    }
};
