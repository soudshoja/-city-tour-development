<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTaskIdToJournalEntriesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('task_id')->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn('task_id');
        });
    }
}
