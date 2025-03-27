<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('account_tag');
        });
    }

    public function down()
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('account_tag', 200)->nullable()->after('serial_number');
        });
    }
};
