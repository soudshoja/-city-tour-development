<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Intervention\Image\Colors\Rgb\Channels\Blue;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('invoice_charge', 10, 2)->default(0)->after('sub_amount')->comment('Charge applied to the invoice');
        });

        Schema::table('charges', function (Blueprint $table) {
            $table->boolean('can_charge_invoice')->default(false)->after('has_url')->comment('Indicates if this charges can add invoice basis charge');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('invoice_charge');
        });

        Schema::table('charges', function (Blueprint $table) {
            $table->dropColumn('can_charge_invoice');
        });
    }
};
