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
        Schema::table('supplier_surcharges', function (Blueprint $table) {
            $table->enum('charge_mode', ['task', 'reference'])->default('task')->after('amount');
            $table->boolean('is_refund')->default(false)->after('charge_mode');
            $table->boolean('is_issued')->default(false)->after('is_refund');
            $table->boolean('is_reissued')->default(false)->after('is_issued');
            $table->boolean('is_void')->default(false)->after('is_reissued');
            $table->boolean('is_confirmed')->default(false)->after('is_void');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_surcharges', function (Blueprint $table) {
            $table->dropColumn([
                'charge_mode',
                'is_refund',
                'is_issued',
                'is_reissued',
                'is_void',
                'is_confirmed',
            ]);
        });
    }
};
