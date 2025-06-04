<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('exchange_currency', 3)->nullable()->after('price')->comment('Currency used after exchange');
            $table->decimal('original_price', 10, 2)->nullable()->after('exchange_currency')->comment('Original price of the task before exchange currency');
            $table->string('original_currency', 3)->nullable()->after('original_price')->comment('Original currency of the task before exchange currency');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['exchange_currency', 'original_price', 'original_currency']);
        });
    }
};
