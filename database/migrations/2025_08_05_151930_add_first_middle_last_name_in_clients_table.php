<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('last_name')->nullable()->after('middle_name');

            $table->index(['first_name', 'middle_name', 'last_name']);
        });

        DB::table('clients')->get()->each(function ($client) {
            $names = explode(' ', $client->first_name);
            $firstName = $names[0] ?? null;
            $middleName = count($names) > 2 ? implode(' ', array_slice($names, 1, -1)) : null;
            $lastName = count($names) > 1 ? end($names) : null;
            
            DB::table('clients')->where('id', $client->id)->update([
                'first_name' => $firstName,
                'middle_name' => $middleName,
                'last_name' => $lastName,
            ]);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
            $table->string('first_name')->nullable(false)->change();
        });

    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['first_name', 'middle_name', 'last_name']);

            $table->dropColumn(['first_name', 'middle_name', 'last_name']);
        });
    }
};
