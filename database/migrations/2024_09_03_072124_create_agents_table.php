<?php

// database/migrations/2024_09_03_create_agents_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAgentsTable extends Migration
{
    public function up()
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Ensure this column exists
            $table->string('email')->unique();
            $table->string('company_id')->nullable();
            $table->string('type')->nullable();
            $table->integer('user_id')->nullable();
            $table->string('phone_number')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('agents');
    }
}
