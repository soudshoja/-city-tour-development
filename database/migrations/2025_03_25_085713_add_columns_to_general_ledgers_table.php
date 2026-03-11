<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('general_ledgers', function (Blueprint $table) {
            $table->string('currency', 10)->nullable()->after('id');
            $table->decimal('exchange_rate', 10, 2)->default(0.00)->after('currency');
            $table->decimal('amount', 10, 2)->default(0.00)->after('exchange_rate');
            $table->string('cheque_no', 100)->nullable()->after('amount');
            $table->timestamp('cheque_date')->nullable()->useCurrent()->after('cheque_no');
            $table->string('bank_info', 200)->nullable()->after('cheque_date');
            $table->string('auth_no', 100)->nullable()->after('bank_info');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('general_ledgers', function (Blueprint $table) {
            $table->dropColumn(['currency', 'exchange_rate', 'amount', 'cheque_no', 'cheque_date', 'bank_info', 'auth_no']);
        });
    }
};
