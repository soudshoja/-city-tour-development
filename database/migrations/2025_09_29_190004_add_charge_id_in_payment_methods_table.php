<?php

use App\Models\Charge;
use App\Models\PaymentMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->foreignId('charge_id')->after('id')->nullable()->constrained('charges')->onDelete('set null');
        });

        $paymentMethods = PaymentMethod::all();

        foreach ($paymentMethods as $method) {
            $charge = Charge::where('name', 'like', $method->type)->first();

            if($charge){
                $method->charge_id = $charge->id;
                $method->save();
            }
        }
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropForeign(['charge_id']);
            $table->dropColumn('charge_id');
        });
    }
};
