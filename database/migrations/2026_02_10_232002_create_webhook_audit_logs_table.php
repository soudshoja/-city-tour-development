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
        Schema::create('webhook_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_client_id')->constrained('webhook_clients')->cascadeOnDelete();
            $table->enum('direction', ['outbound', 'inbound']);
            $table->string('http_method', 10)->nullable();
            $table->string('endpoint', 2048)->nullable();
            $table->string('signature_provided', 255)->nullable();
            $table->string('signature_computed', 255)->nullable();
            $table->boolean('signature_valid')->nullable();
            $table->bigInteger('timestamp_provided')->nullable()->comment('Unix timestamp from header');
            $table->bigInteger('timestamp_computed');
            $table->boolean('timestamp_valid')->default(true)->comment('false if outside tolerance');
            $table->string('payload_hash', 255)->nullable()->comment('SHA256 of payload for traceability');
            $table->integer('status_code')->unsigned()->nullable();
            $table->text('error_message')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('webhook_client_id');
            $table->index('direction');
            $table->index('signature_valid');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_audit_logs');
    }
};
