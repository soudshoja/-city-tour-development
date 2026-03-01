<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('agent_loss', 5, 2)->nullable()->after('paid_date');
            $table->decimal('company_loss', 5, 2)->nullable()->after('agent_loss');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['agent_loss', 'company_loss']);
        });
    }
};
