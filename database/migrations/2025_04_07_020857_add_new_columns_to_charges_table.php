<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->after('amount');
            $table->unsignedBigInteger('branch_id')->nullable()->after('company_id');
            $table->unsignedBigInteger('acc_fee_id')->nullable()->after('branch_id');
            $table->unsignedBigInteger('acc_bank_id')->nullable()->after('acc_fee_id');
        });
    }

    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->dropColumn(['company_id', 'branch_id', 'acc_fee_id', 'acc_bank_id']);
        });
    }
};