<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddTicketedAndConfirmedToStatusColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("ALTER TABLE tasks MODIFY COLUMN status ENUM('refund', 'issued', 'reissued', 'void', 'ticketed', 'confirmed')");

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE tasks MODIFY COLUMN status ENUM('refund', 'issued', 'reissued', 'void')");
    }
}
