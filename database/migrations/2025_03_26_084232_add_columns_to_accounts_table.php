<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('serial_number', 50)->nullable()->after('id');
            $table->string('account_tag', 200)->nullable()->after('serial_number');
            $table->string('root_type', 100)->nullable()->after('account_tag');
            $table->string('account_type', 255)->nullable()->after('root_type');
            $table->unsignedBigInteger('branch_id')->nullable()->after('company_id');
            $table->unsignedBigInteger('agent_id')->nullable()->after('branch_id');
            $table->unsignedBigInteger('client_id')->nullable()->after('agent_id');
            $table->unsignedBigInteger('supplier_id')->nullable()->after('client_id');
        });
    }

    public function down()
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['serial_number', 'account_tag', 'root_type', 'account_type', 'branch_id', 'agent_id', 'client_id', 'supplier_id']);
        });
    }
};
