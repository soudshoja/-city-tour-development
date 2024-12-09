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
        Schema::table('agents', function (Blueprint $table) {
            $table->unsignedBigInteger('type_id')->nullable()->after('id'); // Add type_id column
            $table->foreign('type_id')->references('id')->on('agent_type')->onDelete('set null'); // Add foreign key constraint
        });
    }

    public function down()
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropForeign(['type_id']); // Drop foreign key
            $table->dropColumn('type_id'); // Drop column
        });
    }
};
