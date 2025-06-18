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

            $table->string('services')->nullable()->after('country_id');
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
