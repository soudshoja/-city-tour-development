<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add extra_charge column
        DB::statement("ALTER TABLE payment_methods ADD COLUMN extra_charge DECIMAL(10, 3) DEFAULT 0 COMMENT 'Additional flat fee in KWD' AFTER self_charge");

        // Reorder: Move service_charge before self_charge
        DB::statement("ALTER TABLE payment_methods MODIFY COLUMN service_charge DECIMAL(10, 2) NULL COMMENT 'Contract charge (API charge from gateway)' AFTER currency");
        DB::statement("ALTER TABLE payment_methods MODIFY COLUMN self_charge DECIMAL(10, 2) NULL COMMENT 'Back office charge (contract + markup)' AFTER service_charge");
        DB::statement("ALTER TABLE payment_methods MODIFY COLUMN extra_charge DECIMAL(10, 3) DEFAULT 0 COMMENT 'Additional flat fee in KWD' AFTER self_charge");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('extra_charge');
        });
    }
};
