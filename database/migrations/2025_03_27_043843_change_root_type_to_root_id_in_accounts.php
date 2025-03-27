<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('accounts', function (Blueprint $table) {
            // Drop the old column
            $table->dropColumn('root_type');

            // Add the new column
            $table->bigInteger('root_id')->nullable()->after('account_tag');
        });
    }

    public function down()
    {
        Schema::table('accounts', function (Blueprint $table) {
            // Drop the new column
            $table->dropColumn('root_id');

            // Re-add the old column in case of rollback
            $table->string('root_type', 100)->nullable()->after('account_tag');
        });
    }
};