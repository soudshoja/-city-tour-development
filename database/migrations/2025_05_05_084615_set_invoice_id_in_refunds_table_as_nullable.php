<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SetInvoiceIdInRefundsTableAsNullable extends Migration
{
    public function up()
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->unsignedBigInteger('invoice_id')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->unsignedBigInteger('invoice_id')->nullable(false)->change();
        });
    }
}