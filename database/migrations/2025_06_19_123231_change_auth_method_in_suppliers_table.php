<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->renameColumn('auth_method', 'auth_type');
            $table->enum('auth_type', ['basic', 'oauth'])->default('basic')->change();
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->renameColumn('auth_type', 'auth_method');
            $table->string('auth_type')->default('basic')->change();
        });
    }
};
