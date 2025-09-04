<?php

use App\Models\Client;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->onDelete('cascade');
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->timestamps();
        });

        $clients = Client::all();

        try{
            foreach($clients as $client) {
                $client->agents()->sync($client->agent->id);
                $client->save();
            }
        } catch (Exception $e) {
            Log::error('Error syncing agents for clients: ' . $e->getMessage());
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);
            // $table->dropColumn('agent_id');
        });
    }

    public function down(): void
    {
        // $clients = Client::all();

        // Schema::table('clients', function (Blueprint $table) {
        //     $table->foreignId('agent_id')->nullable()->constrained('agents')->onDelete('set null')->after('name');
        // });

        // foreach ($clients as $client){
        //     $client->agent_id = $client->agents->first()->id ?? null;
        //     $client->save();
        // }

        Schema::table('clients', function (Blueprint $table) {
            $table->foreign('agent_id')->references('id')->on('agents');
        });

        Schema::dropIfExists('client_agents');
    }
};
