<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('credits', function (Blueprint $table) {
            $table->unsignedBigInteger('invoice_partial_id')->nullable()->after('invoice_id');
        });
    }

    public function down()
    {
        Schema::table('credits', function (Blueprint $table) {
            $table->dropColumn('invoice_partial_id');
        });
    }

};
