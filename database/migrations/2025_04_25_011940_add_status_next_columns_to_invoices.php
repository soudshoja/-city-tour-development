<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('status_next', 50)
                ->nullable()
                ->after('invoice_date')
                ->comment('for reissue & refund');

            $table->timestamp('status_next_date')
                ->nullable()
                ->after('status_next')
                ->comment('for reissue & refund');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['status_next', 'status_next_date']);
        });
    }
};
