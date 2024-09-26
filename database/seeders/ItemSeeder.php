<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Item;
use App\Models\Agent;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        if (Agent::count() == 0) {
            $user = User::find(1);
            Agent::create([
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone_number' => '1234567890', // Replace with actual data
                'company_id' => 1, // Replace with actual data
                'type' => 'salary', // Replace with actual data
            ]);
        }

        if (Client::count() == 0) {
            Client::factory()->create();
        }

        // Get all agents and clients
        $agents = Agent::all();
        $clients = Client::all();

        // Create an item using the ItemFactory
        Item::factory()->count(10)->make()->each(function ($item) use ($agents, $clients) {
            $agent = $agents->random();
            $client = $clients->random();

            $item->agent_id = $agent->id;
            $item->client_id = $client->id;
            $item->agent_email = $agent->email;
            $item->client_email = $client->email;
            $item->save();
        });
    }
}
