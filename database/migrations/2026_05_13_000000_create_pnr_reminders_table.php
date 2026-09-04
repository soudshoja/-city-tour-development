<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pnr_reminders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('reference', 32)->comment('Task reference / PNR');
            $table->string('type', 64)->comment('Reminder type, e.g. kwikt2843_creator');
            $table->unsignedBigInteger('agent_id')->nullable()->comment('Agent who received the reminder');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['company_id', 'reference', 'type'], 'pnr_reminders_company_reference_type_unique');
            $table->index(['type', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pnr_reminders');
    }
};
