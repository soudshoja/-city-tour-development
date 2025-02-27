<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Webklex\IMAP\Facades\Client;
use Illuminate\Support\Facades\DB;
use OpenAI;
use Illuminate\Support\Facades\Log;

class ReadAndProcessEmails extends Command
{
    protected $signature = 'emails:process';
    protected $description = 'Read emails, process using OpenAI, and insert into the tasks table';

    public function handle()
    {
        $client = Client::account('default'); 
        $client->connect();

        // Gmail labels to read emails from
        $labels = ['magic', 'tbo', 'webbeds'];

        $openai = OpenAI::client(env('OPENAI_API_KEY'));

        foreach ($labels as $label) {
            $this->info("\n📂 Processing emails from: " . strtoupper($label));

            try {
                $folder = $client->getFolder($label);
                $messages = $folder->query()->all()->limit(5)->get();

                foreach ($messages as $message) {
                    $emailId = $message->getMessageId(); // Unique email identifier
                    $emailText = $message->getTextBody();

                    // ✅ Check if this email has already been processed
                    if (DB::table('tasks')->where('email_id', $emailId)->exists()) {
                        $this->warn("⚠️ Email already processed (ID: $emailId), skipping...");
                        continue;
                    }

                    // 🔹 Use OpenAI to extract structured data
                    $extractedData = $this->processWithAI($openai, $emailText);

                    if ($extractedData) {
                        // 🔹 Add the email ID before inserting into DB
                        $extractedData['email_id'] = $emailId;

                        // 🔹 Insert data into `tasks` table
                        DB::table('tasks')->insert($extractedData);
                        $this->info("✅ Email processed and inserted into database (ID: $emailId).");
                    } else {
                        $this->warn("⚠️ Could not extract valid data from email (ID: $emailId).");
                    }
                }
            } catch (\Exception $e) {
                $this->error("⚠️ Error processing $label: " . $e->getMessage());
            }
        }

        $this->info("\n✅ Email processing completed!");
    }

    private function processWithAI($openai, $emailText)
    {
        // Fetch clients and agents from DB
        $userData = $this->fetchUserBasedData();
        
        // Convert them to JSON format for AI processing
        $agentsJson = json_encode($userData['agents']);
        $clientsJson = json_encode($userData['clients']);

        $prompt = "Extract structured data from the following email and return JSON format. 
        - Match `client_name` with the `clients` list to get `client_id`.
        - Match `agent_name` with the `agents` list to get `agent_id`.

        Available Clients:
        $clientsJson

        Available Agents:
        $agentsJson

        Email Content:
        ---
        $emailText
        ---
        Return JSON format with `client_id`, `agent_id`, and relevant details.";

        $response = $openai->completions()->create([
            'model' => 'gpt-4',
            'prompt' => $prompt,
            'max_tokens' => 500,
            'temperature' => 0.3,
        ]);

        $jsonText = trim($response->choices[0]->text);
        $jsonData = json_decode($jsonText, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $jsonData;
        }

        Log::error("JSON decoding failed for OpenAI response: " . $jsonText);
        return null;
    }

    private function fetchUserBasedData()
    {
        $suppliers = Supplier::all();
        $company = auth()->user()->company; // Assuming user is authenticated and has a company.

        return [
            'suppliers' => $suppliers,
            'company' => [
                'name' => $company->name,
                'id' => $company->id,
            ],
            'branches' => $company->branches->map(function ($branch) {
                return [
                    'name' => $branch->name,
                    'id' => $branch->id,
                ];
            }),
            'agents' => $company->branches->flatMap->agents->map(function ($agent) {
                return [
                    'name' => $agent->name,
                    'id' => $agent->id,
                    'email' => $agent->email,
                    'contact' => $agent->phone_number,
                    'branchId' => $agent->branch_id,
                    'branchName' => $agent->branch->name,
                    'type' => $agent->type,
                ];
            }),
            'clients' => $company->branches->flatMap->agents->flatMap->clients->map(function ($client) {
                return [
                    'name' => $client->name,
                    'id' => $client->id,
                    'agentId' => $client->agent_id,
                    'agentName' => $client->agent->name,
                    'contact' => $client->phone,
                    'email' => $client->email,
                    'address' => $client->address,
                    'passportNo' => $client->passport_no,
                ];
            }),
        ];
    }
}
