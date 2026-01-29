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
        Schema::table('invoice_details', function (Blueprint $table) {
            // profit = markup_price - agent_charge_deduction
            $table->decimal('profit', 15, 3)->default(0)->after('markup_price');

            // Commission calculated from profit (for agent types 2, 3, 4)
            $table->decimal('commission', 15, 3)->default(0)->after('profit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_details', function (Blueprint $table) {
            $table->dropColumn([
                'profit',
                'commission',
            ]);
        });
    }
};
