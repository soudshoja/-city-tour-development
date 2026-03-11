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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('agent_id')->constrained()->nullable();
            $table->foreignId('account_id')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('passport_no')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->string('civil_no')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('phone');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
