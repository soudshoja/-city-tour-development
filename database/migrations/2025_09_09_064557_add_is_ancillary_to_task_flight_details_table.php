<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up()
{
    Schema::table('task_flight_details', function (Blueprint $table) {
        $table->boolean('is_ancillary')->default(false)->after('id'); 
    });
}

public function down()
{
    Schema::table('task_flight_details', function (Blueprint $table) {
        $table->dropColumn('is_ancillary');
    });
}
};
