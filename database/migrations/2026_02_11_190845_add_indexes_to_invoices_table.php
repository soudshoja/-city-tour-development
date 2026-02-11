<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->index('status');
            $table->index('invoice_date');
            $table->index('due_date');
            $table->index('created_at');

            $table->index(['agent_id', 'status']);
            $table->index(['client_id', 'status']);
            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['invoice_date']);
            $table->dropIndex(['due_date']);
            $table->dropIndex(['created_at']);

            $table->dropIndex(['agent_id', 'status']);
            $table->dropIndex(['client_id', 'status']);
            $table->dropIndex(['status', 'due_date']);
        });
    }
};
