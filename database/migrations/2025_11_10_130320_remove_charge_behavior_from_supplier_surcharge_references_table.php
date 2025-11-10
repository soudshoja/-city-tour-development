<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('supplier_surcharge_references', 'charge_behavior')) {
            DB::statement('ALTER TABLE supplier_surcharge_references DROP COLUMN charge_behavior');
        }
    }

    public function down(): void
    {
        Schema::table('supplier_surcharge_references', function (Blueprint $table) {
            $table->enum('charge_behavior', ['single', 'repetitive'])->nullable()->after('reference');
        });
    }
};
