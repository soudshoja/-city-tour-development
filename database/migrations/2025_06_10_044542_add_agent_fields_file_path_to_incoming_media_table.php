<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('incoming_media', function (Blueprint $table) {
            $table->string('file_path')->nullable()->after('caption');
            $table->string('agent_phone')->nullable()->after('file_path');
            $table->string('agent_email')->nullable()->after('agent_phone');
        });
    }

    public function down()
    {
        Schema::table('incoming_media', function (Blueprint $table) {
            $table->dropColumn('file_path');
            $table->dropColumn('agent_phone');
            $table->dropColumn('agent_email');
        });
    }

};
