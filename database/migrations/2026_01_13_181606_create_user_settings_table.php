<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('key'); 
            $table->text('value')->nullable(); // Store as JSON for complex data
            $table->string('type')->default('string');
            $table->timestamps();

            $table->unique(['user_id', 'key']);
        });
}

    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
