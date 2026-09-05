<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql_map')->table('cities', function (Blueprint $table) {

            if (Schema::connection('mysql_map')->hasColumn('cities', 'latitude')) {
                $table->dropColumn('latitude');
            }

            if (Schema::connection('mysql_map')->hasColumn('cities', 'longitude')) {
                $table->dropColumn('longitude');
            }

            if (Schema::connection('mysql_map')->hasColumn('cities', 'services')) {
                $table->dropColumn('services');
            }

            // JSON (not a wide VARCHAR): matches how the app writes this column
            // (SyncCitiesJob::handle() stores json_encode($cityData['services'])),
            // matches the sibling `countries.services` column's type in this same
            // connection (2025_06_05_122511_create_tables_for_magic_holiday.php),
            // and — being stored off-page by InnoDB — avoids tripping the 8126-byte
            // row-size cap that a wide inline VARCHAR hit here on a fresh migrate.
            $table->json('services')->nullable()->after('country_id');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_map')->table('cities', function (Blueprint $table) {
            $table->dropColumn(['services', 'code']);
            $table->decimal('latitude', 10, 8)->nullable()->after('country_id');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
        });
    }
};
