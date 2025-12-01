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
        Schema::create('supplier_surcharge_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_surcharge_id')->constrained('supplier_surcharges')->onDelete('cascade');
            $table->string('reference')->index();
            // Should we charge once or every time the same reference appears in multiple tasks?
            $table->enum('charge_behavior', ['single', 'repetitive'])->default('single');
            // Track whether the charge has already been applied for 'single' mode
            $table->boolean('is_charged')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_surcharge_references');
    }
};
