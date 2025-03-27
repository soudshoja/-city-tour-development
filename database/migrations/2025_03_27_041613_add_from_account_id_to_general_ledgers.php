<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up()
    {
        // Add the new column 'from_account_id' after 'transaction_date' using raw SQL
        DB::statement("ALTER TABLE `general_ledgers` ADD `from_account_id` INT(11) NULL AFTER `transaction_date`");
    }

    public function down()
    {
        Schema::table('general_ledgers', function (Blueprint $table) {
            $table->dropColumn('from_account_id');
        });
    }
};
