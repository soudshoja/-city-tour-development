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
        Schema::dropIfExists('refunds');

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->string('refund_number')->unique();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade');
            $table->foreignId('refund_invoice_id')->nullable()->constrained('invoices')->onDelete('cascade');
            $table->enum('method', ['Cash', 'Bank', 'Online', 'Credit'])->nullable();
            $table->text('remarks')->nullable();
            $table->text('remarks_internal')->nullable();
            $table->text('reason')->nullable();
            $table->decimal('total_refund_amount', 15, 3)->default(0);
            $table->decimal('total_refund_charge', 15, 3)->default(0);
            $table->decimal('total_nett_refund', 15, 3)->default(0);
            $table->enum('status', ['pending', 'approved', 'processed', 'completed', 'declined'])->default('processed');
            $table->date('refund_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });             
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
