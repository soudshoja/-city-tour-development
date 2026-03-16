<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `suppliers` CHANGE `auth_method` `auth_type` ENUM('basic', 'oauth') NOT NULL DEFAULT 'basic'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `suppliers` CHANGE `auth_type` `auth_method` VARCHAR(255) NOT NULL DEFAULT 'basic'");
    }
};
