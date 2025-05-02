<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('gds_office_id')->nullable()->after('reference');
            $table->foreignId('original_task_id')->after('status')->nullable()->constrained('tasks');
            $table->decimal('penalty_fee', 10, 2)->after('surcharge')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('gds_office_id');
            $table->dropForeign(['original_task_id']);
            $table->dropColumn('original_task_id');
            $table->dropColumn('penalty_fee');
        });
    }
};
