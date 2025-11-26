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
        Schema::table('tbo', function (Blueprint $table) {
            $table->string('booking_type', 10)->default('b2b')->after('supplier_status');
            $table->decimal('markup_percentage', 5, 4)->default(0)->after('booking_type');
            $table->string('original_currency', 10)->nullable()->after('currency');
            $table->decimal('exchange_rate', 10, 6)->default(1)->after('original_currency');
            $table->decimal('price_before_markup', 10, 3)->nullable()->after('total_fare');
            $table->decimal('tax_before_markup', 10, 3)->nullable()->after('total_tax');
            $table->decimal('original_total_fare', 10, 3)->nullable()->after('tax_before_markup');
            $table->decimal('original_total_tax', 10, 3)->nullable()->after('original_total_fare');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbo', function (Blueprint $table) {
            $table->dropColumn([
                'booking_type',
                'markup_percentage',
                'original_currency',
                'exchange_rate',
                'price_before_markup',
                'tax_before_markup',
                'original_total_fare',
                'original_total_tax'
            ]);
        });
    }
};
