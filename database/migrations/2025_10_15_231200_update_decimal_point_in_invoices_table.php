<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('sub_amount',  15, 3)->nullable(false)->default(0)->change();
            $table->decimal('invoice_charge',  15, 3)->nullable(false)->default(0)->change();
            $table->decimal('amount',  15, 3)->nullable(false)->default(0)->change();
            $table->decimal('tax',  15, 3)->nullable()->default(0)->change();
            $table->decimal('discount',  15, 3)->nullable()->default(0)->change();
            $table->decimal('shipping',  15, 3)->nullable()->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('sub_amount',  15, 2)->nullable(false)->change();
            $table->decimal('invoice_charge',  10, 2)->nullable(false)->default(0)->change();
            $table->decimal('amount',  15, 2)->nullable(false)->change();
            $table->decimal('tax',  15, 2)->nullable()->change();
            $table->decimal('discount',  15, 2)->nullable()->change();
            $table->decimal('shipping',  15, 2)->nullable()->change();
        });
    }
};
