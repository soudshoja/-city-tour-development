<?php

namespace App\Console\Commands;

use App\AI\AIManager;
use App\Models\Task;
use App\Models\TaskFlightDetail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
// If your AI tool is a class or service, you might need to import it here
// use App\Services\YourAiProcessingService;

class ProcessAirFiles extends Command
{
    protected $signature = 'air:process-files';

    protected $description = 'Scans the root air-files directory for new AIR files, processes them using existing logic, and moves them.';

    protected $airFilesPath;
    protected $processedFilesPath;
    protected $aiManager;

    public function __construct(AIManager $aiManager)
    {
        parent::__construct();

        $this->airFilesPath = base_path('air-files');
        $this->processedFilesPath = storage_path('app/air_files_processed');
        $this->aiManager = $aiManager;
    }

    public function handle()
    {
        $this->info('Starting AIR file processing from root/air-files directory...');
        Log::info('AIR File Processing: Service started.');

        if (!File::isDirectory($this->airFilesPath)) {
            $this->error("Source directory not found: {$this->airFilesPath}");
            Log::error("AIR File Processing: Source directory {$this->airFilesPath} not found.");
            File::makeDirectory($this->airFilesPath, 0755, true, true); // Optionally create it
            $this->info("Created source directory: {$this->airFilesPath}, please ensure files are pushed here.");
            return 1;
        }


        if (!File::isDirectory($this->processedFilesPath)) {
            File::makeDirectory($this->processedFilesPath, 0755, true, true);
            $this->info("Created processed files directory: {$this->processedFilesPath}");
        }

        // The example filename 'AIR-BLK206;...' doesn't have a typical extension.
        // File::files() gets all files. You might want to add more specific filtering
        // if there are other types of files in this directory you want to ignore.
        $filesToProcess = File::files($this->airFilesPath);

        if (empty($filesToProcess)) {
            $this->info('No new files found in air-files directory to process.');
            Log::info('AIR File Processing: No new files found.');
            return 0; // Success
        }

        $this->info(count($filesToProcess) . ' file(s) found in air-files.');

        foreach ($filesToProcess as $file) { // $file is an SplFileInfo object
            $filePath = $file->getRealPath();
            $fileName = $file->getFilename();

            $this->info("Processing file: {$fileName}");
            Log::info("AIR File Processing: Starting file {$fileName}");

            try {
                $fileContent = File::get($filePath);

                $extractedData = $this->processWithAiTool($fileContent, $fileName);

                if ($extractedData === null || (is_array($extractedData) && empty($extractedData))) {
                    Log::warning("AIR File Processing: AI tool returned no data or indicated an issue for {$fileName}. Skipping move, investigate.");
                    $this->warn("AI tool returned no data for {$fileName}. File will remain in place.");
                    continue;
                }

                $extractedData = is_array($extractedData) ? $extractedData : json_decode($extractedData, true);

                $response = $this->saveTask(1,$extractedData['data']);

                if ($response['status'] === 'error') {
                    if(isset($response['code']) && $response['code'] === 409) {
                        Log::info("Task already exists for {$fileName}. Skipping save.");
                        $this->info("Task already exists for {$fileName}. Skipping save.");
                        $this->info("Moving {$fileName} to {$this->processedFilesPath}.");
                        $destinationPath = $this->processedFilesPath . '/' . $fileName;
                        File::move($filePath, $destinationPath);
                        continue;
                    } else {
                        Log::error("Failed to save task for {$fileName}: " . $response['message']);
                        $this->error("Failed to save task for {$fileName}: " . $response['message']);
                        continue;
                    }
                }

                Log::info("AIR File Processing: File {$fileName} processed by AI tool. Output summary (if any): " . (is_array($extractedData) ? json_encode($extractedData) : $extractedData));
                $this->info("File {$fileName} processed successfully by AI tool.");

                $destinationPath = $this->processedFilesPath . '/' . $fileName;
                File::move($filePath, $destinationPath);

                $this->info("Successfully moved {$fileName} to {$this->processedFilesPath}.");
                Log::info("AIR File Processing: Successfully moved {$fileName} to {$destinationPath}.");

            } catch (\Exception $e) {
                $this->error("Error processing file {$fileName}: " . $e->getMessage());
                Log::error("AIR File Processing: Error processing file {$fileName}. Error: " . $e->getMessage(), [
                    'file' => $fileName,
                    'trace' => $e->getTraceAsString()
                ]);
                // Optional: Move to an error directory
                // $errorPath = storage_path('app/air_files_error');
                // if (!File::isDirectory($errorPath)) { File::makeDirectory($errorPath, 0755, true, true); }
                // File::move($filePath, $errorPath . '/' . $fileName);
            }
        }

        $this->info('AIR file processing finished.');
        Log::info('AIR File Processing: Service finished.');
        return 0; 
    }

    protected function processWithAiTool(string $fileContent, string $fileName) : mixed
    {
        $this->info("Handing over content of {$fileName} to AI processing tool...");

        try {
            $response = $this->aiManager->extractAirFiles($fileContent);

            if (!isset($response['status']) || $response['status'] !== 'success') {
                $errorMessage = $response['message'] ?? 'Unknown error occurred.';
                Log::error("AI Tool processing failed for {$fileName}: " . $errorMessage);
                $this->error("AI Tool processing failed for {$fileName}: " . $errorMessage);
                return null;
            }

            Log::info("AI Tool processing response for {$fileName}: " . json_encode($response));

            $extractedData = $response['data'] ?? null;

            if (!$extractedData) {
                Log::error("Failed to decode AI Tool response for {$fileName}: " . json_last_error_msg());
                $this->error("Failed to decode AI Tool response for {$fileName}: " . json_last_error_msg());
                return null;
            }

            $processedData = [
                'status' => 'success',
                'message' => "Successfully processed {$fileName} using AI.",
                'original_filename' => $fileName,
                'data' => [
                    'additional_info' => $extractedData['additional_info'] ?? 'N/A',
                    'ticket_number' => $extractedData['ticket_number'] ?? 'N/A',
                    'status' => $extractedData['status'] ?? 'N/A',
                    'reference' => $extractedData['reference'] ?? 'N/A',
                    'gds_office_id' => $extractedData['gds_office_id'] ?? 'N/A',
                    'type' => $extractedData['type'] ?? 'N/A',
                    'agent_name' => $extractedData['agent_name'] ?? 'N/A',
                    'client_name' => $extractedData['client_name'] ?? 'N/A',
                    'supplier_name' => $extractedData['supplier_name'] ?? 'N/A',
                    'supplier_country' => $extractedData['supplier_country'] ?? 'N/A',
                    'cancellation_policy' => $extractedData['cancellation_policy'] ?? 'N/A',
                    'venue' => $extractedData['venue'] ?? 'N/A',
                    'task_flight_details' => [
                        'farebase' => $extractedData['task_flight_details']['farebase'] ?? null,
                        'departure_time' => $extractedData['task_flight_details']['departure_time'] ?? null,
                        'departure_from' => $extractedData['task_flight_details']['departure_from'] ?? null,
                        'airport_from' => $extractedData['task_flight_details']['airport_from'] ?? 'N/A',
                        'terminal_from' => $extractedData['task_flight_details']['terminal_from'] ?? 'N/A',
                        'arrival_time' => $extractedData['task_flight_details']['arrival_time'] ?? null,
                        'duration_time' => $extractedData['task_flight_details']['duration_time'] ?? 'N/A',
                        'arrive_to' => $extractedData['task_flight_details']['arrive_to'] ?? 'N/A',
                        'airport_to' => $extractedData['task_flight_details']['airport_to'] ?? 'N/A',
                        'terminal_to' => $extractedData['task_flight_details']['terminal_to'] ?? 'N/A',
                        'airline_name' => $extractedData['task_flight_details']['airline_name'] ?? 'N/A',
                        'flight_number' => $extractedData['task_flight_details']['flight_number'] ?? 'N/A',
                        'class_type' => $extractedData['task_flight_details']['class_type'] ?? 'N/A',
                        'baggage_allowed' => $extractedData['task_flight_details']['baggage_allowed'] ?? 'N/A',
                        'equipment' => $extractedData['task_flight_details']['equipment'] ?? 'N/A',
                        'flight_meal' => $extractedData['task_flight_details']['flight_meal'] ?? 'N/A',
                        'seat_no' => $extractedData['task_flight_details']['seat_no'] ?? 'N/A',
                        'ticket_number' => $extractedData['task_flight_details']['ticket_number'] ?? 'N/A',
                    ],
                    'price' => $extractedData['price'] ?? null,
                    'surcharge' => $extractedData['surcharge'] ?? null,
                    'tax' => $extractedData['tax'] ?? null,
                    'taxes_record' => $extractedData['taxes_record'] ?? 'N/A',
                    'penalty_fee' => $extractedData['penalty_fee'] ?? 0.00,
                    'refund_charge' => $extractedData['refund_charge'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            ];

            return $processedData;

        } catch (\Exception $e) {
            Log::error("Exception occurred while processing {$fileName}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            $this->error("An error occurred while processing {$fileName}: " . $e->getMessage());
            return null;
        }
    }

    protected function saveTask($companyId, $data) : array
    {
        $taskFlightDetails = $data['task_flight_details'] ?? [];

        unset($data['task_flight_details']);

        $existingTask = Task::where('reference' , $data['reference'])
            ->where('company_id' , $companyId)
            ->where('status' , $data['status'])
            ->first();

        if ($existingTask) {
            Log::info("Task with reference {$data['reference']} already exists. Skipping save.");
            return [
                'status' => 'error',
                'message' => 'Task already exists',
                'code' => 409,
            ];
        }

        DB::beginTransaction();

        try {
            $data['company_id'] = $companyId;
            $data['total'] = $data['price'] + $data['surcharge'] + $data['tax'] + $data['penalty_fee'];

            $task = Task::create($data);

            if (!empty($taskFlightDetails)) {
                $taskFlightDetails['task_id'] = $task->id;
                TaskFlightDetail::create($taskFlightDetails);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to save task: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'status' => 'error',
                'message' => 'Failed to save task',
                'error' => $e->getMessage(),
            ];
        }

        DB::commit();
        return [
            'status' => 'success',
            'message' => 'Task saved successfully',
            'task' => $task,
        ];

    }
}