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
        Schema::create('supplier_companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id');
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('account_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_companies');
    }
};
