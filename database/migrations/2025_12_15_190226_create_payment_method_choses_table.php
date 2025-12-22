<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_method_choses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('payment_method_group_id')->constrained();
            $table->foreignId('payment_method_id')->constrained();
            $table->timestamps();

            $table->unique(['company_id', 'payment_method_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_method_choses');
    }
};
