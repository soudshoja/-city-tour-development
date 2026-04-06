<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_settlement_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_settlement_id');
            $table->decimal('amount', 15, 3);
            $table->enum('method' , ['profit', 'payment_link', 'wallet']);
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->string('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('agent_settlement_id')->references('id')->on('agent_settlements')->onDelete('cascade');
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_settlement_payments');
    }
};
