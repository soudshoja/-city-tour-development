<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('airlines', function (Blueprint $table) {
            $table->dropColumn('code');

            $table->string('name_ar')->nullable()->after('name');
            $table->foreignId('country_id')->nullable()->after('icao_designator')->constrained('countries')->nullOnDelete();
            $table->string('accounting_code', 10)->nullable()->after('country_id');
            $table->string('alliance', 50)->nullable()->after('accounting_code');
            $table->enum('airline_type', ['full_service', 'low_cost', 'charter', 'cargo'])->default('full_service')->after('alliance');
            $table->boolean('is_active')->default(true)->after('airline_type');
            $table->string('logo_path')->nullable()->after('is_active');

            $table->char('iata_designator', 2)->nullable()->change();
            $table->char('icao_designator', 3)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('airlines', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropColumn([
                'name_ar',
                'country_id',
                'accounting_code',
                'alliance',
                'airline_type',
                'is_active',
                'logo_path',
            ]);

            $table->string('code')->nullable()->after('iata_designator');
        });
    }
};
