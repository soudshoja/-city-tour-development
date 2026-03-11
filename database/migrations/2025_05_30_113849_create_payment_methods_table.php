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
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('arabic_name')->nullable();
            $table->string('english_name');
            $table->string('code'); 
            $table->enum('type', ['tap', 'myfatoorah', 'hesabe']);
            $table->boolean('is_active')->default(true);
            $table->decimal('service_charge', 8, 2)->default(0.00);
            $table->string('image')->nullable(); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
