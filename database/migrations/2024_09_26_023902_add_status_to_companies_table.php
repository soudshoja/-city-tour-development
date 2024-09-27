<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusToCompaniesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('companies', function (Blueprint $table) {
            // Add a 'status' column that can store values like 'active', 'inactive', 'suspended', 'terminated'
            $table->enum('status', ['active', 'inactive', 'suspended', 'terminated'])->default('inactive')->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('companies', function (Blueprint $table) {
            // Remove the 'status' column when rolling back the migration
            $table->dropColumn('status');
        });
    }
}