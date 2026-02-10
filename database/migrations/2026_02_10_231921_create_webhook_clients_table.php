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
        Schema::create('webhook_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('e.g., "N8n Primary", "External API v2"');
            $table->enum('type', ['n8n', 'external', 'internal'])->comment('Client type for filtering');
            $table->foreignId('company_id')->nullable()->constrained()->onDelete('restrict')->comment('Optional: restrict to specific company');
            $table->string('webhook_url', 2048)->nullable()->comment('Callback URL for this client');
            $table->integer('rate_limit')->default(60)->comment('Max requests per minute');
            $table->boolean('is_active')->default(true)->comment('Soft enable/disable without deletion');
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('is_active');
            $table->index('company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_clients');
    }
};
