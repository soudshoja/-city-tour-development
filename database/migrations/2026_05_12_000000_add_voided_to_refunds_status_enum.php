<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE refunds MODIFY COLUMN status ENUM('pending','approved','processed','completed','declined','voided') NOT NULL DEFAULT 'processed'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE refunds MODIFY COLUMN status ENUM('pending','approved','processed','completed','declined') NOT NULL DEFAULT 'processed'");
        }
    }
};
