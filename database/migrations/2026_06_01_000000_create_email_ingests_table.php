<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('email_ingests')) {
            return;
        }

        Schema::create('email_ingests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->string('mailbox')->nullable();            // delivered-to address
            $table->string('message_id', 512)->nullable();    // RFC Message-ID (dedup key)
            $table->string('from_address')->nullable();
            $table->string('supplier_slug')->nullable();
            $table->string('file_name')->nullable();
            $table->string('pnr')->nullable();
            $table->string('status', 32)->default('received'); // received|dropped|unrouted|skipped|error
            $table->text('note')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index('message_id');
            $table->index('file_name');
            $table->index('agent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_ingests');
    }
};
