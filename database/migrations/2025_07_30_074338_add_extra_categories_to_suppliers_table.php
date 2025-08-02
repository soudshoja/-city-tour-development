<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
         $table->boolean('has_tour')->default(false)->after('has_insurance');
        $table->boolean('has_cruise')->default(false)->after('has_tour');
        $table->boolean('has_car')->default(false)->after('has_cruise');
        $table->boolean('has_rail')->default(false)->after('has_car');
        $table->boolean('has_esim')->default(false)->after('has_rail');
        $table->boolean('has_event')->default(false)->after('has_esim');
        $table->boolean('has_lounge')->default(false)->after('has_event');
        $table->boolean('has_ferry')->default(false)->after('has_lounge');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn([
                'has_tour', 'has_cruise', 'has_car', 'has_rail',
                'has_esim', 'has_event', 'has_lounge', 'has_ferry'
            ]);
        });
    }
};
