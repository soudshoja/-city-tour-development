<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_steps', function (Blueprint $table) {
            $table->string('hotel')->nullable()->comment('Hotel name or identifier if applicable')->change();
        });
    }

    public function down(): void
    {
        Schema::table('user_steps', function (Blueprint $table) {
            $table->string('hotel')->comment('Hotel name or identifier if applicable')->change();
        });
    }
};
