<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('supplier_surcharges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_company_id');
            $table->string('label', 100);
            $table->decimal('amount', 10, 3);
            $table->timestamps();

            $table->foreign('supplier_company_id')
                ->references('id')
                ->on('supplier_companies')
                ->onDelete('cascade');

            $table->unique(['supplier_company_id', 'label']);
            $table->comment('Stores defined auto surcharges for each supplier company');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_surcharges');
    }
};
