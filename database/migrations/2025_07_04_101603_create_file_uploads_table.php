<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery\Generator\StringManipulationGenerator;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('file_name');
            $table->string('destination_path');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('cascade');
            $table->foreignId('task_id')->nullable()->constrained('tasks')->onDelete('cascade');
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_uploads');
    }
};
