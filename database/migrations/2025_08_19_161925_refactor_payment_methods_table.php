<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrations must not use application models (Eloquent models can carry
        // global scopes — e.g. PaymentMethod's BelongsToCompany — that reference
        // columns this migration hasn't created yet). Use the query builder
        // directly against the tables instead; the data transformation below is
        // otherwise unchanged from the original Eloquent version.
        $paymentMethods = DB::table('payment_methods')->get();

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('payment_methods')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->unsignedBigInteger('myfatoorah_id')->nullable()->after('id');
            $table->foreignId('company_id')->nullable()->after('myfatoorah_id')->constrained('companies')->onDelete('set null');
        });

        $companies = DB::table('companies')->get();

        $now = now();

        foreach($companies as $company) {
            foreach($paymentMethods as $method) {
                DB::table('payment_methods')->insert([
                    'myfatoorah_id' => $method->id,
                    'company_id' => $company->id,
                    'arabic_name' => $method->arabic_name,
                    'english_name' => $method->english_name,
                    'code' => $method->code,
                    'type' => $method->type,
                    'is_active' => $method->is_active,
                    'currency' => $method->currency,
                    'service_charge' => $method->service_charge,
                    'self_charge' => $method->self_charge,
                    'paid_by' => $method->paid_by,
                    'charge_type' => $method->charge_type,
                    'description' => $method->description,
                    'image' => $method->image,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
           }
        }

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('myfatoorah_id');
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
