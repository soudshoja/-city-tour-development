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
            // Check if the 'status' column does not already exist before adding it
            if (!Schema::hasColumn('companies', 'status')) {
                $table->enum('status', ['active', 'inactive', 'suspended', 'terminated'])->default('inactive')->after('user_id');
            }
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