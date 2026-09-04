<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('whatsapp_ingests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->string('from_phone')->nullable();        // sender's WhatsApp number
            $table->string('country_code', 8)->nullable();   // for replies
            $table->string('message_id', 512)->nullable();   // Resayil inbound id (dedup key)
            $table->string('supplier_slug')->nullable();
            $table->string('file_name')->nullable();         // dropped PDF name (matches Task.file_name)
            $table->string('pnr')->nullable();
            $table->string('confidence', 16)->default('deterministic'); // deterministic|ai|none
            // received|dropped|awaiting_field|live|review|passport|duplicate|error
            $table->string('status', 32)->default('received');
            $table->unsignedBigInteger('task_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index('message_id');
            $table->index('file_name');
            $table->index('agent_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_ingests');
    }
};
