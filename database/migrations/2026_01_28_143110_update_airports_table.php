
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('airports', function (Blueprint $table) {
            $table->dropColumn('location');
            $table->dropColumn('city_code');

            $table->string('name_ar')->nullable()->after('name');
            $table->char('iata_code', 3)->nullable()->after('name_ar');
            $table->char('icao_code', 4)->nullable()->after('iata_code');
            $table->foreignId('city_id')->nullable()->after('icao_code')->constrained('cities')->nullOnDelete();
            $table->foreignId('country_id')->nullable()->after('city_id')->constrained('countries')->nullOnDelete();
            $table->string('timezone', 50)->nullable()->after('country_id');
            $table->decimal('latitude', 10, 7)->nullable()->after('timezone');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->boolean('is_active')->default(true)->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('airports', function (Blueprint $table) {
            $table->string('location')->nullable()->after('name');
            $table->string('city_code', 10)->nullable()->after('location');
            $table->dropColumn([
                'name_ar',
                'iata_code',
                'icao_code',
                'city_id',
                'country_id',
                'timezone',
                'latitude',
                'longitude',
                'is_active',
            ]);
        });
    }
};
