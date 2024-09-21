<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Item;
use App\Models\Agent;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
class ItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::find('id', 1);
        // Ensure the agent profile exists
        $agent = Agent::firstOrCreate(
            ['user_id' => $user->id],
            [
                'name' => $user->name,
                'email' => $user->email,
                'phone_number' => '1234567890', // Replace with actual data
                'company_id' => 1, // Replace with actual data
                'type' => 'salary', // Replace with actual data
            ]
        );

        // Create an item using the ItemFactory
        Item::factory()->create([
            'agent_email' => $agent->email,
            'agent_id' => $agent->id,
        ]);
    }
}
