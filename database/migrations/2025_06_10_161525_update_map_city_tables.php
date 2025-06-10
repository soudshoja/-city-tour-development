<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql_map')->table('Cities', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
            $table->string('services')->nullable()->after('country_id');
            $table->string('code')->nullable()->after('services');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_map')->table('Cities', function (Blueprint $table) {
            $table->dropColumn(['services', 'code']);
            $table->decimal('latitude', 10, 8)->nullable()->after('country_id');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
        });
    }
};
