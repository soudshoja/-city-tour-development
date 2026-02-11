<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_loss', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->onDelete('cascade');
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->enum('loss_bearer', ['company', 'agent', 'split'])->default('company');
            $table->decimal('agent_percentage', 5, 2)->default(0);
            $table->decimal('company_percentage', 5, 2)->default(100);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['agent_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_loss');
    }
};
