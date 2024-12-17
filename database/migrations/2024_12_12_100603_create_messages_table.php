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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->string('run_id')->nullable();
            $table->string('message_id')->unique();
            $table->bigInteger('prompt_tokens')->nullable();
            $table->bigInteger('completion_tokens')->nullable();
            $table->bigInteger('total_tokens')->nullable();
            $table->bigInteger('cache_tokens')->nullable();
            $table->string('type')->nullable()->enum('prompt', 'answer');
            $table->string('role')->nullable()->enum('user', 'assistant');
            $table->text('content')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
