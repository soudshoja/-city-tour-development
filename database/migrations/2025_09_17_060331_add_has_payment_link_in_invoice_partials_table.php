<?php

use App\Models\InvoicePartial;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_partials', function (Blueprint $table) {
            $table->boolean('has_payment_link')->default(false)->after('payment_id');
        });

    }

    public function down(): void
    {
        Schema::table('invoice_partials', function (Blueprint $table) {
            $table->dropColumn('has_payment_link');
        });
    }
};
