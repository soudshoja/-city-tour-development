<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('currency')->after('code')->nullable();
            $table->boolean('is_group')->after('currency')->default(true);
            $table->boolean('disabled')->after('is_group')->default(false);
            $table->enum('balance_must_be', ['debit', 'credit'])->after('disabled')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('currency');
            $table->dropColumn('is_group');
            $table->dropColumn('disabled');
            $table->dropColumn('balance_must_be');
        });
    }
};
