<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('incoming_media', function (Blueprint $table) {
            $table->string('media_type')->nullable()->after('file_path');
            $table->unsignedBigInteger('agent_id')->nullable()->after('media_type');
            $table->unsignedBigInteger('client_id')->nullable()->after('agent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::tbale('incoming_media', function (Blueprint $table) {
            $table->dropColumm('media_type','agent_id','client_id');
        });
    }
};
