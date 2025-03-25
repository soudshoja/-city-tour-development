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
        Schema::table('transactions', function (Blueprint $table) {
            $table->integer('branch_id')->nullable()->after('id');
            $table->string('name', 200)->nullable()->after('branch_id');
            $table->string('reference_number', 20)->nullable()->after('name');
            $table->text('remarks_internal')->nullable()->after('reference_number');
            $table->text('remarks_fl')->nullable()->after('remarks_internal');
            $table->timestamp('created_at')->useCurrent()->after('remarks_fl');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['branch_id', 'name', 'reference_number', 'remarks_internal', 'remarks_fl', 'created_at']);
        });
    }
};
