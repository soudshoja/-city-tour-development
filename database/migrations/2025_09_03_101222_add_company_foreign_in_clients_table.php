<?php

use App\Models\Client;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->after('agent_id');

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('set null');
        });

        $clients = Client::all();

        foreach($clients as $client) {
            if ($client->agent) {
                $client->company_id = $client->agent->branch->company_id;
                $client->save();
            }
        }
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
