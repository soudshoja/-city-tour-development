<?php

use App\Models\Charge;
use App\Models\InvoicePartial;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_partials', function (Blueprint $table) {
            $table->dropColumn('has_payment_link');
            $table->foreignId('charge_id')->after('type')->nullable()->references('id')->on('charges');
        });

        $invoicePartials = InvoicePartial::all();

        foreach($invoicePartials as $partial){
            $chargeId = Charge::where('name', $partial->payment_gateway)->value('id');

            if($chargeId){
                $partial->charge_id = $chargeId;
                $partial->save();
            }
        }

    }

    public function down(): void
    {
        Schema::table('invoice_partials', function (Blueprint $table) {
            $table->boolean('has_payment_link')->default(false)->after('payment_id');
            $table->dropForeign(['charge_id']);
            $table->dropColumn('charge_id');
        });
    }
};
