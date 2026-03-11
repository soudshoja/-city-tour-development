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
        Schema::create('webhook_secrets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_client_id')->constrained('webhook_clients')->cascadeOnDelete();
            $table->string('secret_hash')->comment('bcrypt hash of actual secret (never store plaintext)');
            $table->string('secret_preview', 8)->comment('Last 8 chars for UI display, e.g., "...abc123"');
            $table->string('algorithm')->default('sha256')->comment('HMAC algorithm used');
            $table->boolean('is_active')->default(true)->comment('Only one secret active per client');
            $table->timestamp('rotation_scheduled_at')->nullable()->comment('When rotation takes effect');
            $table->timestamp('grace_period_until')->nullable()->comment('Old secret still accepted until this time');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('deactivated_at')->nullable()->comment('When this secret was retired');

            $table->unique(['webhook_client_id', 'is_active'], 'unique_active_secret');
            $table->index('created_at');
            $table->index('grace_period_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_secrets');
    }
};
