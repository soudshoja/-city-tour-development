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
        Schema::create('task_visa_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->onDelete('cascade');
            $table->string('visa_type')->nullable();
            $table->string('application_number')->nullable();
            $table->date('expiry_date')->nullable();
            $table->enum('number_of_entries', ['single', 'double', 'multiple'])->nullable()->comment('Number of allowed entries');
            $table->integer('stay_duration')->nullable()->comment('Stay of duration in days');
            $table->string('issuing_country')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_visa_details');
    }
};
