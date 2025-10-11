<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('amount', 15, 3)->change();
            $table->decimal('service_charge', 15, 3)->nullable()->change();
            $table->decimal('tax', 15, 3)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->change();
            $table->decimal('service_charge', 8, 2)->nullable()->change();
            $table->decimal('tax', 10, 2)->nullable()->change();
        });
    }
};
