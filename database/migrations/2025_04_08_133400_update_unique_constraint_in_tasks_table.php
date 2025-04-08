<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            
            $table->dropUnique(['supplier_id', 'reference']); // Drop the existing unique constraint on supplier_id and reference

            $table->unique(['supplier_id', 'reference', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropUnique(['supplier_id', 'reference', 'company_id']);

            $table->unique(['supplier_id', 'reference']);
        });
    }
};
