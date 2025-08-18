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
            $table->renameColumn('supplier_created_date', 'supplier_pay_date');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->datetime('supplier_pay_date')->nullable()->after('cancellation_deadline')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->renameColumn('supplier_pay_date', 'supplier_created_date');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('supplier_created_date');
        });
    }
};
