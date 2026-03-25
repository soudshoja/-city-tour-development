<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->index('voucher_number');
            $table->index('payment_reference');
            $table->index('client_id');
            $table->index('agent_id');
            $table->index('status');
            $table->index('completed');
            $table->index('payment_date');

            $table->index(['client_id', 'status']);
            $table->index(['agent_id', 'status']);
            $table->index(['invoice_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['voucher_number']);
            $table->dropIndex(['payment_reference']);
            $table->dropIndex(['status']);
            $table->dropIndex(['completed']);
            $table->dropIndex(['payment_date']);

            // Drop composite indexes that share columns with simple indexes.
            // Must drop composites BEFORE the simple single-column indexes
            // so MySQL still has an index for FK constraints.
            $table->dropIndex(['client_id', 'status']);
            $table->dropIndex(['agent_id', 'status']);
            $table->dropIndex(['invoice_id', 'status']);

            // Now safe to drop the simple indexes
            $table->dropIndex(['client_id']);
            $table->dropIndex(['agent_id']);
        });
    }
};
