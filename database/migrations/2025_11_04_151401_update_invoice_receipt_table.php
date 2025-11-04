<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('invoice_receipt', 'invoice_receipts');

        Schema::table('invoice_receipts', function (Blueprint $table) {
            $table->enum('type',['invoice', 'credit', 'account', 'import'])->after('id')->change();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->after('amount')->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_receipts', function (Blueprint $table) {
            $table->string('type')->change();
            $table->string('status')->default('pending')->change();
        });

        Schema::rename('invoice_receipts', 'invoice_receipt');
        
    }
};
