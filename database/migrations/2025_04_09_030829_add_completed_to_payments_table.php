<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCompletedToPaymentsTable extends Migration
{
    public function up()
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->tinyInteger('completed')
                  ->default(1)
                  ->nullable()
                  ->after('shipping');
        });
    }

    public function down()
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('completed');
        });
    }
}
