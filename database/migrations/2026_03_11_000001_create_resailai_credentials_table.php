<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates table to store encrypted ResailAI API credentials.
     */
    public function up(): void
    {
        Schema::create('resailai_credentials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->comment('User who created the credential');
            $table->string('name')->comment('Credential name for identification');
            $table->string('api_key')->comment('Encrypted API key');
            $table->string('api_secret')->nullable()->comment('Encrypted API secret (if applicable)');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resailai_credentials');
    }
};
