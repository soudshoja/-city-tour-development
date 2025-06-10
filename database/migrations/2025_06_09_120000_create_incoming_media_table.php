<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIncomingMediaTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('incoming_media', function (Blueprint $table) {
            $table->id();
            $table->string('phone');
            $table->string('media_id')->unique();
            $table->string('mime_type')->nullable();
            $table->text('caption')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps(); // created_at and updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('incoming_media');
    }
}
