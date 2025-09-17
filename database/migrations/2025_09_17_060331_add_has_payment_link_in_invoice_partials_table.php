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

        $invoicePartials = InvoicePartial::all();

        $paymengGatewayHasLink = [
            'Tap',
            'MyFatoorah',
            'Hesabe'
        ];

        foreach ($invoicePartials as $invoicePartial) {
            if (in_array($invoicePartial->payment_gateway, $paymengGatewayHasLink)) {
                $invoicePartial->has_payment_link = true;
                $invoicePartial->save();
            }
        }

    }

    public function down(): void
    {
        Schema::table('invoice_partials', function (Blueprint $table) {
            $table->dropColumn('has_payment_link');
        });
    }
};
