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
        Schema::create('task_insurance_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->onDelete('cascade');
            $table->unsignedSmallInteger('date')->nullable();
            $table->integer('paid_leaves')->nullable();
            $table->string('document_reference')->nullable();
            $table->string('insurance_type')->nullable();
            $table->string('destination')->nullable();
            $table->string('plan_type')->nullable();
            $table->string('duration')->nullable();
            $table->string('package')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_insurance_details');
    }
};
