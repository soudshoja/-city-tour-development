<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_invites', function (Blueprint $table) {
            $table->id();
            $table->char('token', 64)->unique();
            $table->string('email');
            $table->decimal('monthly_fee', 10, 3)->default(0);
            $table->string('note')->nullable();
            $table->string('status', 20)->default('pending'); // pending|used|expired|cancelled
            $table->dateTime('expires_at');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('company_id')->nullable()->constrained('companies');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_invites');
    }
};
