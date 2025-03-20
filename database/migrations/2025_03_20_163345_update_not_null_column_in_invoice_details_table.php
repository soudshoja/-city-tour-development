<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_details', function (Blueprint $table) {
            $table->string('task_description')->nullable()->change();
            $table->string('task_remark')->nullable()->change();
            $table->string('client_notes')->nullable()->change();
            $table->decimal('task_price', 10, 2)->nullable()->change();
            $table->decimal('supplier_price', 10, 2)->nullable()->change();
            $table->decimal('markup_price', 10, 2)->nullable()->change();
            $table->tinyInteger('paid')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_details', function (Blueprint $table) {
            $table->string('task_description')->nullable(false)->change();
            $table->string('task_remark')->nullable(false)->change();
            $table->string('client_notes')->nullable(false)->change();
            $table->decimal('task_price', 10, 2)->nullable(false)->change();
            $table->decimal('supplier_price', 10, 2)->nullable(false)->change();
            $table->decimal('markup_price', 10, 2)->nullable(false)->change();
            $table->tinyInteger('paid')->nullable(false)->change();
        });
    }
};
