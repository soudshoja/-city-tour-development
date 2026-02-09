<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_partials', function (Blueprint $table) {
            $table->decimal('invoice_charge', 10, 3)->default(0)->after('client_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_partials', function (Blueprint $table) {
            $table->dropColumn('invoice_charge');
        });
    }
};
