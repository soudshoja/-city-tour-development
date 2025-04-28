<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE refunds 
            MODIFY COLUMN status ENUM('pending', 'approved', 'processed', 'completed', 'declined') 
            DEFAULT 'processed' COMMENT 'Transaction status';");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE refunds 
            MODIFY COLUMN status ENUM('pending', 'approved') 
            DEFAULT 'pending' COMMENT 'Transaction status';");
    }
};
