<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_companies', function (Blueprint $table) {
            $table->boolean('is_active')->default(false)->after('company_id')->comment('Indicates if the supplier is active for the company');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_companies', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
