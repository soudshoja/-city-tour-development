<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('client_ref')->nullable()->after('client_name');
            $table->boolean('is_n8n_booking')->default(false)->after('original_task_id');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('client_ref');
            $table->dropColumn('is_n8n_booking');
        });
    }
};
