<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_settlement_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_settlement_id');
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('invoice_detail_id');
            $table->decimal('amount', 15, 3);
            $table->string('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('agent_settlement_id')->references('id')->on('agent_settlements')->onDelete('cascade');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->foreign('invoice_detail_id')->references('id')->on('invoice_details')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_settlement_details');
    }
};
