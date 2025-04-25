<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            // Drop old amount column
            $table->dropColumn('amount');

            // Modify columns to be nullable
            $table->text('reason')->nullable()->change();
            $table->string('method')->nullable()->change();

            // Add new columns
            $table->string('refund_number')->after('id');
            $table->text('remarks')->nullable()->after('agent_id');
            $table->text('remarks_internal')->nullable()->after('remarks');
            $table->decimal('airline_nett_fare', 15, 2)->nullable()->after('remarks_internal');
            $table->decimal('tax_refund', 15, 2)->nullable()->after('airline_nett_fare');
            $table->decimal('refund_airline_charge', 15, 2)->nullable()->after('tax_refund');
            $table->decimal('original_task_profit', 15, 2)->nullable()->after('refund_airline_charge');
            $table->decimal('new_task_profit', 15, 2)->nullable()->after('original_task_profit');
            $table->decimal('total_nett_refund', 15, 2)->nullable()->after('new_task_profit');
            $table->decimal('service_charge', 15, 2)->nullable()->after('total_nett_refund');
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            // Re-add old column
            $table->decimal('amount', 15, 2)->nullable();

            // Drop newly added columns
            $table->dropColumn([
                'refund_number',
                'remarks',
                'remarks_internal',
                'airline_nett_fare',
                'tax_refund',
                'refund_airline_charge',
                'original_task_profit',
                'new_task_profit',
                'total_nett_refund',
                'service_charge',
            ]);

            // Revert nullable changes
            $table->text('reason')->nullable(false)->change();
            $table->string('method')->nullable(false)->change();
        });
    }
};
