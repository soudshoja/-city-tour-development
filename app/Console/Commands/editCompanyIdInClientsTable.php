<?php

namespace App\Console\Commands;

use App\Models\Client;
use Illuminate\Console\Command;

class editCompanyIdInClientsTable extends Command
{
    protected $signature = 'app:edit-company-clients';

    protected $description = 'Command description';

    public function handle()
    {
        $clients = Client::all();

        foreach($clients as $client) {
            if($client->company_id == null){
                if ($client->agent && $client->agent->branch->company_idl) {
                    $client->company_id = $client->agent->branch->company_id;
                    $client->save();
                    $this->info("Updated client ID {$client->id} with company ID {$client->company_id}");
                } else {
                    $this->warn("Client ID {$client->id} has no associated agent or agent has no company ID");
                }
            }
        }
    }
}
