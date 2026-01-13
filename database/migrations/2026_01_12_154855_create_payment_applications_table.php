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
        Schema::create('payment_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_id');
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('invoice_partial_id')->nullable();
            $table->decimal('amount', 15, 3);
            $table->unsignedBigInteger('applied_by')->nullable();
            $table->timestamp('applied_at')->useCurrent();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->foreign('invoice_partial_id')->references('id')->on('invoice_partials')->onDelete('set null');
            $table->foreign('applied_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['payment_id', 'invoice_id']);
            $table->index('invoice_partial_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_applications');
    }
};
