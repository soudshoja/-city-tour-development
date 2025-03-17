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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('level');
            $table->decimal('actual_balance', 10, 2);
            $table->decimal('budget_balance', 10, 2);
            $table->decimal('variance', 10, 2);
            $table->foreignId('parent_id')->nullable()->constrained('accounts');
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('reference_id')->nullable()->constrained('accounts');
            $table->string('code');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
