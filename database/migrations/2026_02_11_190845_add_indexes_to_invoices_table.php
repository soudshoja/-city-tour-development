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
        // MySQL prevents dropping indexes used by foreign key constraints.
        // Drop the FK first, then indexes, then re-add the FK.
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);
            $table->dropForeign(['client_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['agent_id', 'status']);
            $table->dropIndex(['client_id', 'status']);
            $table->dropIndex(['status', 'due_date']);
            $table->dropIndex(['status']);
            $table->dropIndex(['invoice_date']);
            $table->dropIndex(['due_date']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('agent_id')->references('id')->on('agents')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
        });
    }
};
