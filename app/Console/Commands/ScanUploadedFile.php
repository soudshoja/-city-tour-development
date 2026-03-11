<?php

namespace App\Console\Commands;

use App\Models\Company;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ScanUploadedFile extends Command
{
    protected $signature = 'app:scan-uploaded-file';

    protected $description = 'Scan uploaded files of supplier task in server and notify n8n webhook if any new file found';

    public function handle()
    {
        $companies = Company::all();

        $n8nWebhookUrl = config('services.n8n.webhook_url');

        $this->info("Using n8n Webhook URL: " . ($n8nWebhookUrl ?: 'Not Set'));

        foreach ($companies as $company) {
            $this->info("Scanning files for company: {$company->name}");

            $companySlug = strtolower(preg_replace('/\s+/', '_', $company->name));

            $suppliers = $company->suppliers()
                ->wherePivot('is_active', true)
                ->get();

            foreach ($suppliers as $supplier) {
                $this->info("Checking supplier: {$supplier->name}");

                $supplierSlug = strtolower(preg_replace('/\s+/', '_', $supplier->name));

                $filePath = storage_path("app/{$companySlug}/{$supplierSlug}/files_unprocessed");

                $this->info('Scanning path: ' . $filePath);

                if (is_dir($filePath)) {
                    $files = scandir($filePath);
                 
                    $fileUrls = array_map(function ($file) use ($companySlug, $supplierSlug) {
                        return url("storage/app/{$companySlug}/{$supplierSlug}/files_unprocessed/{$file}");
                    }, array_filter($files, function ($file) {
                        return !in_array($file, ['.', '..']);
                    }));

                    if (!empty($fileUrls)) {
                        $this->info("    New files found: " . implode(', ', $fileUrls));


                        if ($n8nWebhookUrl) {

                            $client = new Client();

                            try {
                                $response = $client->post($n8nWebhookUrl, [
                                    'json' => [
                                        'company' => $company->name,
                                        'supplier' => $supplier->name,
                                        'new_files' => array_values($fileUrls),
                                    ],
                                ]);

                                if ($response->getStatusCode() == 200) {
                                    Log::info("Notified n8n webhook successfully.");
                                    $this->info("Notified n8n webhook successfully.");
                                } else {
                                    Log::error("Failed to notify n8n webhook. Status code: " . $response->getStatusCode());
                                    $this->error("Failed to notify n8n webhook. Status code: " . $response->getStatusCode());
                                }
                            } catch (Exception $e) {
                                Log::error("Error notifying n8n webhook: " . $e->getMessage());
                                $this->error("Error notifying n8n webhook: " . $e->getMessage());
                            }  
                        }
                                
                    } else {
                        $this->info("No new files found.");
                    }
                } else {
                    $this->info("Directory does not exist: {$filePath}");
                }

            }

        }
    }
}
