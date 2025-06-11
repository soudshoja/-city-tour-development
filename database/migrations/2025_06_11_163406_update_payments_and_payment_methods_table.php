<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->renameColumn('payment_method', 'payment_method_id');
            $table->foreignId('payment_method_id')->nullable()->change();
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->onDelete('set null');
        });

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->float('self_charge', 8, 2)->default(0)->after('service_charge')->change();
            $table->enum('charge_type', ['Percent', 'Flat Rate'])->default('Percent')->change();
            $table->enum('paid_by', ['Company', 'Client'])->default('Company')->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['payment_method_id']);
            $table->renameColumn('payment_method_id', 'payment_method');
            $table->integer('payment_method')->nullable()->change();
        });

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->text('self_charge')->default(null)->change();
            $table->text('charge_type')->default(null)->change();
            $table->text('paid_by')->default(null)->change();
        });
    }
};
