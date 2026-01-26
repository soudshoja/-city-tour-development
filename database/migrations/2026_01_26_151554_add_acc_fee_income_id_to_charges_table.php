<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->unsignedBigInteger('acc_fee_income_id')->nullable()->after('acc_fee_id');
        });

        $companies = DB::table('companies')->pluck('id');

        foreach ($companies as $companyId) {
            $incomeAccount = DB::table('accounts')
                ->where('name', 'Gateway Fee Recovery')
                ->where('company_id', $companyId)
                ->first();

            if ($incomeAccount) {
                DB::table('charges')
                    ->where('company_id', $companyId)
                    ->update(['acc_fee_income_id' => $incomeAccount->id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->dropColumn('acc_fee_income_id');
        });
    }
};
