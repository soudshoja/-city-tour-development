<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReconciledToJournalEntriesTable extends Migration
{
    public function up()
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->tinyInteger('reconciled')
                  ->nullable()
                  ->default(0)
                  ->after('voucher_number');
        });
    }

    public function down()
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn('reconciled');
        });
    }
}
