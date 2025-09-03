<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('task_visa_details', function (Blueprint $table) {
            if (!Schema::hasColumn('task_visa_details', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_visa_details', function (Blueprint $table) {
            if (Schema::hasColumn('task_visa_details', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
