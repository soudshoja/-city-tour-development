<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMyfatoorahPaymentsTable extends Migration
{
    public function up()
    {
        Schema::create('myfatoorah_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_int_id')->nullable(); // Removed ->after('id')
            $table->string('payment_id')->nullable();
            $table->string('invoice_id')->nullable();
            $table->string('invoice_status')->nullable();
            $table->string('customer_reference')->nullable();
            $table->json('payload'); // Store full callback response
            $table->timestamps();
        });

    }

    public function down()
    {
        Schema::dropIfExists('myfatoorah_payments');
    }
}

