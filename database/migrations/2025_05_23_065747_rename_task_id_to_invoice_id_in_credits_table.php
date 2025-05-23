<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameTaskIdToInvoiceIdInCreditsTable extends Migration
{
    public function up()
    {
        Schema::table('credits', function (Blueprint $table) {
            $table->renameColumn('task_id', 'invoice_id');
        });
    }

    public function down()
    {
        Schema::table('credits', function (Blueprint $table) {
            $table->renameColumn('invoice_id', 'task_id');
        });
    }
}
