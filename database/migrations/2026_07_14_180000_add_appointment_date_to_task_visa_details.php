<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('task_visa_details', 'appointment_date')) {
            Schema::table('task_visa_details', function (Blueprint $table) {
                // Visa-appointment date (VFS etc.). Nullable — issued visas from
                // other sources don't have one.
                $table->date('appointment_date')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('task_visa_details', 'appointment_date')) {
            Schema::table('task_visa_details', function (Blueprint $table) {
                $table->dropColumn('appointment_date');
            });
        }
    }
};
