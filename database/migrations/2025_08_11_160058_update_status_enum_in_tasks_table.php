<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->enum('status', [
                'refund',
                'issued',
                'reissued',
                'void',
                'ticketed',
                'confirmed',
                'emd',
                'refunded',
                'on hold',
            ])->change();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->enum('status',[
                'refund',
                'issued',
                'reissued',
                'void',
                'ticketed',
                'confirmed',
                'emd',
                'refunded',
                'on_hold',
            ])->change();
        });
    }
};
