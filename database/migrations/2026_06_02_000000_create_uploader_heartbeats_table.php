<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('uploader_heartbeats')) {
            return;
        }
        Schema::create('uploader_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->string('host_id', 64)->unique();
            $table->string('status', 32)->nullable();
            $table->unsignedInteger('files_today')->default(0);
            $table->unsignedBigInteger('files_total')->default(0);
            $table->string('version', 32)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('alert_sent_at')->nullable();
            $table->timestamp('recovered_alert_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uploader_heartbeats');
    }
};
