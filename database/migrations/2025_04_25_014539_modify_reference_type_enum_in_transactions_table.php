<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE transactions MODIFY reference_type ENUM('Invoice', 'Payment', 'Refund') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE transactions MODIFY reference_type ENUM('Invoice', 'Payment') NOT NULL");
    }
};
