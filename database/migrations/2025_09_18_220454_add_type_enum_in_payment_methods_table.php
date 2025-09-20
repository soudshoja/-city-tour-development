<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->enum('type', ['myfatoorah', 'tap', 'hesabe', 'upayment'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->enum('type', ['myfatoorah', 'tap', 'hesabe'])->change();
        });
    }
};
