<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            // WhatsApp group (name or WID like 12345-67890@g.us) whose PDF
            // documents are ingested as tasks for this supplier's pipeline.
            $table->string('whatsapp_group')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('whatsapp_group');
        });
    }
};
