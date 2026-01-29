<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This table stores configuration for who bears the EXTRA CHARGES per agent.
     * Extra charges = Gateway charges (from invoice_partial.service_charge) + Supplier surcharges
     * 
     * The profit calculation will be based on these settings:
     * - company: Company bears 100% of extra charges (agent keeps full markup)
     * - agent: Agent bears 100% of extra charges (deducted from profit)
     * - split: Both share charges based on percentages
     */
    public function up(): void
    {
        Schema::create('agent_charge', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->onDelete('cascade');
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');

            // Who bears extra charges: 'company', 'agent', 'split'
            $table->string('charge_bearer')->default('company');

            // Percentages for split (must sum to 100)
            $table->decimal('agent_percentage', 5, 2)->default(0);
            $table->decimal('company_percentage', 5, 2)->default(100);

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One setting per agent per company
            $table->unique(['agent_id', 'company_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_charge');
    }
};
