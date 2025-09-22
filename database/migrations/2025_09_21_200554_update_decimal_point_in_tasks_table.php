<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->decimal('price', 10 , 3)->nullable()->change();
            $table->decimal('original_price', 10 , 3)->nullable()->change();
            $table->decimal('tax', 10 , 3)->nullable()->change();
            $table->decimal('surcharge', 10 , 3)->nullable()->change();
            $table->decimal('penalty_fee', 10 , 3)->nullable()->change();
            $table->decimal('total', 10 , 3)->nullable()->change();
            $table->decimal('invoice_price', 10 , 3)->nullable()->change();
            $table->decimal('refund_charge', 10 , 3)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->decimal('price', 10 , 2)->nullable()->change();
            $table->decimal('original_price', 10 , 2)->nullable()->change();
            $table->decimal('tax', 10 , 2)->nullable()->change();
            $table->decimal('surcharge', 10 , 2)->nullable()->change();
            $table->decimal('penalty_fee', 10 , 2)->nullable()->change();
            $table->decimal('total', 10 , 2)->nullable()->change();
            $table->decimal('invoice_price', 10 , 2)->nullable()->change();
            $table->decimal('refund_charge', 10 , 2)->nullable()->change();
        });
    }
};
