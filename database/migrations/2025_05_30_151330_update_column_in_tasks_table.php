<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('gds_office_id');
            $table->string('created_by')->nullable()->after('reference')->comment('GDS Office ID indicate who created the task');
            $table->string('issued_by')->nullable()->after('created_by')->comment('GDS Office ID indicate who issued/pay the task');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('gds_office_id')->nullable()->after('user_id')->comment('GDS Office ID');
            $table->dropColumn('created_by');
            $table->dropColumn('issued_by');
        });
    }
};
