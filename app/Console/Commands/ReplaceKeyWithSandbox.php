<?php

namespace App\Console\Commands;

use App\Models\Charge;
use Illuminate\Console\Command;

class ReplaceKeyWithSandbox extends Command
{
    protected $signature = 'charge:replace-sandbox-keys';

    protected $description = 'Replace API key with sandbox key in development environment';

    public function handle()
    {
        if(app()->environment() == 'production'){
            $this->info('This command can only be run in non-production environments.');
            return 1;
        }

        $filePath = base_path('.env');
        if (!file_exists($filePath)) {
            $this->error('.env file not found.');
            return 1;
        }

        $charges = Charge::all();

        foreach($charges as $charge) {
            switch (strtolower($charge->name)) {
                case 'tap':
                    $sandboxSecret = env('TAP_SANDBOX_SECRET');
                    if ($sandboxSecret) {
                        $charge->api_key = $sandboxSecret;
                        $charge->save();
                        $this->info("Replaced TAP API key with sandbox key for Charge ID: {$charge->id}");
                    } else {
                        $this->warn("TAP_SANDBOX_SECRET not set in .env file.");
                    }
                    break;
                case 'myfatoorah':
                    $sandboxKey = env('MYFATOORAH_SANDBOX_KEY');
                    if ($sandboxKey) {
                        $charge->api_key = $sandboxKey;
                        $charge->save();
                        $this->info("Replaced MyFatoorah API key with sandbox key for Charge ID: {$charge->id}");
                    } else {
                        $this->warn("MYFATOORAH_SANDBOX_KEY not set in .env file.");
                    }
                    break;
                case 'hesabe':
                    $sandboxKey = env('HESABE_SANDBOX_SECRET_KEY');
                    if ($sandboxKey) {
                        $charge->api_key = $sandboxKey;
                        $charge->save();
                        $this->info("Replaced Hesabe API key with sandbox key for Charge ID: {$charge->id}");
                    } else {
                        $this->warn("HESABE_SANDBOX_KEY not set in .env file.");
                    }
                    break;
                case 'upayment':
                    $sandboxKey = env('UPAYMENT_SANDBOX_KEY');
                    if ($sandboxKey) {
                        $charge->api_key = $sandboxKey;
                        $charge->save();
                        $this->info("Replaced UPayment API key with sandbox key for Charge ID: {$charge->id}");
                    } else {
                        $this->warn("UPAYMENT_SANDBOX_KEY not set in .env file.");
                    }
                    break;
                default:
                    $this->info("No sandbox key replacement needed for Charge ID: {$charge->id} ({$charge->name})");
                
            }
        }
        
        
    }
}
