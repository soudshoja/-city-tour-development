<?php

namespace App\Console\Commands;

use App\AI\AIManager;
use App\Http\Controllers\TaskController;
use App\Models\Agent;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\TaskFlightDetail;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

// If your AI tool is a class or service, you might need to import it here
// use App\Services\YourAiProcessingService;

class ProcessAirFiles extends Command
{
    protected $signature = 'app:process-files';

    protected $description = 'Scans the root air-files directory for new AIR files, processes them using existing logic, and moves them.';

    // protected $airFilesPath;
    // protected $processedFilesPath;
    protected $aiManager;
    protected $suppliers;
    protected $companies;

    public function __construct(AIManager $aiManager)
    {
        parent::__construct();

        // $this->airFilesPath = storage_path('app/air_files_unprocessed');
        // $this->processedFilesPath = storage_path('app/air_files_processed');
        $this->aiManager = $aiManager;
        $this->suppliers = Supplier::all();
        $this->companies = Company::all();
    }

    public function handle()
    {
        $this->info('Starting AIR file processing from root/air-files directory...');
        Log::info('AIR File Processing: Service started.');

        foreach ($this->companies as $company) {
            $companyName = strtolower(preg_replace('/\s+/', '_', $company->name));

            foreach ($this->suppliers as $supplier) {
                $supplierName = strtolower(preg_replace('/\s+/', '_', $supplier->name));

                $filePath = storage_path("app/{$companyName}/{$supplierName}/files_unprocessed");

                if (!File::isDirectory($filePath)) {
                    $this->error("Source directory not found: {$filePath}");
                    Log::error("AIR File Processing: Source directory {$filePath} not found.");
                    File::makeDirectory($filePath, 0755, true, true); // Optionally create it
                    $this->info("Created source directory: {$filePath}, please ensure files are pushed here.");
                    continue;
                }

                $filesToProcess = File::files($filePath);

                if (empty($filesToProcess)) {
                    $this->info("No new files found in {$companyName}/{$supplierName} air-files directory to process.");
                    Log::info("AIR File Processing: No new files found in {$companyName}/{$supplierName}.");
                    continue; // Skip to the next supplier
                }

                $this->info(count($filesToProcess) . " file(s) found in {$companyName}/{$supplierName} air-files.");
                foreach ($filesToProcess as $file) { // $file is an SplFileInfo object
                    $fileRealPath = $file->getRealPath();
                    $fileName = $file->getFilename();

                    $this->info("Processing file: {$fileName}");
                    Log::info("AIR File Processing: Starting file {$fileName}");

                    try {

                        $extractedData = $this->processWithAiTool($fileRealPath, $fileName);

                        if ($extractedData === null || (is_array($extractedData) && empty($extractedData))) {
                            Log::warning("AIR File Processing: AI tool returned no data or indicated an issue for {$fileName}. Skipping move, investigate.");
                            $this->warn("AI tool returned no data for {$fileName}. File will remain in place.");

                            $errorPath = storage_path("app/{$companyName}/{$supplierName}/files_error");                            

                            $this->moveFileWithLogging(
                                $fileRealPath,
                                $errorPath,
                                $fileName,
                                'AI tool returned no data'
                            );

                            Log::info("AIR File Processing: Moved {$fileName} to error directory {$errorPath}.");

                            continue;
                        }

                        $extractedData = is_array($extractedData) ? $extractedData : json_decode($extractedData, true);

                        $agentName = $extractedData['data']['agent_name'] ?? null;
                        $agentEmail = $extractedData['data']['agent_email'] ?? null;
                        $agentAmadeusId = $extractedData['data']['agent_amadeus_id'] ?? null;

                        if (!$agentName || !$agentEmail || !$agentAmadeusId) {
                            Log::warning("AIR File Processing: Missing agent information in {$fileName}. Skipping save.");
                            $this->warn("Missing agent information in {$fileName}. File will remain in place.");

                            $errorPath = storage_path("app/{$companyName}/{$supplierName}/files_error");

                            $this->moveFileWithLogging(
                                $fileRealPath,
                                $errorPath,
                                $fileName,
                                'Missing agent information'
                            );

                            continue;
                        }

                        // By default, 'like' is case-insensitive in MySQL (the default Laravel DB), but case-sensitive in PostgreSQL unless using ILIKE.
                        $agent = Agent::where('name', 'like', $agentName)
                            ->orWhere('email', 'like', $agentEmail)
                            ->orWhere('amadeus_id', 'like', $agentAmadeusId)
                            ->first();

                        if (!$agent) {
                            Log::warning("AIR File Processing: Agent not found for {$fileName}.");
                            $this->warn("Agent not found for {$fileName}.");

                            $errorPath = storage_path("app/{$companyName}/{$supplierName}/files_error");
                            $this->moveFileWithLogging(
                                $fileRealPath,
                                $errorPath,
                                $fileName,
                                'Agent not found'
                            );

                            continue;
                        }

                        $branchId = $agent->branch_id;

                        $branch = Branch::find($branchId);

                        if (!$branch) {
                            Log::error("AIR File Processing: Branch not found for agent {$agentName} in {$fileName}. Skipping save.");
                            $this->error("Branch not found for agent {$agentName} in {$fileName}. File will remain in place.");

                            $errorPath = storage_path("app/{$companyName}/{$supplierName}/files_error");

                            $this->moveFileWithLogging(
                                $fileRealPath,
                                $errorPath,
                                $fileName,
                                'Branch not found'
                            );

                            continue;
                        }

                        $extractedData['data']['agent_id'] = $agent->id;
                        $companyId = $branch->company_id;

                        $response = $this->saveTask($companyId, $extractedData['data']);

                        if ($response['status'] === 'error') {
                            if (isset($response['code']) && $response['code'] === 409) {
                                Log::info("Task already exists for {$fileName}. Skipping save.");
                                $this->info("Task already exists for {$fileName}. Skipping save.");
                                continue;
                            } else {
                                Log::error("Failed to save task for {$fileName}: " . $response['message']);
                                $this->error("Failed to save task for {$fileName}: " . $response['message']);

                                $errorPath = storage_path("app/{$companyName}/{$supplierName}/files_error");

                                $this->moveFileWithLogging(
                                    $fileRealPath,
                                    $errorPath,
                                    $fileName,
                                    'Failed to save task'
                                );

                                Log::info("AIR File Processing: Moved {$fileName} to error directory {$errorPath}.");
                                $this->info("File {$fileName} moved to error directory due to save failure.");
                                continue;
                            }
                        }

                        Log::info("AIR File Processing: File {$fileName} processed by AI tool. Output summary (if any): " . (is_array($extractedData) ? json_encode($extractedData) : $extractedData));
                        $this->info("File {$fileName} processed successfully by AI tool.");

                        // Move the file to the processed directory
                        $successPath = storage_path("app/{$companyName}/{$supplierName}/files_processed");

                        $this->moveFileWithLogging(
                            $fileRealPath,
                            $successPath,
                            $fileName,
                            'Successfully processed and saved task'
                        );

                        Log::info("AIR File Processing: Successfully moved {$fileName} to {$successPath}.");
                    } catch (Exception $e) {
                        $this->error("Error processing file {$fileName}: " . $e->getMessage());
                        Log::error("AIR File Processing: Error processing file {$fileName}. Error: " . $e->getMessage(), [
                            'file' => $fileName,
                            'trace' => $e->getTraceAsString()
                        ]);

                        $errorPath = storage_path("app/{$companyName}/{$supplierName}/files_error");

                        $this->moveFileWithLogging(
                            $fileRealPath,
                            $errorPath,
                            $fileName,
                            'Error during processing'
                        );

                        Log::info("AIR File Processing: Moved {$fileName} to error directory {$errorPath}.");
                    }

                    $this->info('AIR file processing for supplier ' . $supplierName . ' in company ' . $companyName . ' finished.');
                    Log::info('AIR File Processing: Finished processing for supplier ' . $supplierName . ' in company ' . $companyName . '.');
                }
            }
        }
        Log::info('AIR File Processing: Service finished.');
        return 0;
    }

    protected function processWithAiTool(string $filePath, string $fileName) : mixed
    {

        $this->info("Handing over content of {$fileName} to AI processing tool...");

        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if ($extension === 'pdf') {
            $response = $this->aiManager->extractPdfFiles($filePath);

            Log::info("extractPdfFiles response for {$fileName}: " . json_encode($response));

            if($response['status'] !== 'success') {
                $errorMessage = $response['message'] ?? 'Unknown error occurred.';
                Log::error("AI Tool processing failed for {$fileName}: " . $errorMessage);
                $this->error("AI Tool processing failed for {$fileName}: " . $errorMessage);
                return null;
            }

            $data = $response['data'] ?? null;

            if(!$data) {
                Log::error("Failed to decode AI Tool response for {$fileName}: " . json_last_error_msg());
                $this->error("Failed to decode AI Tool response for {$fileName}: " . json_last_error_msg());
                return null;
            }

            Log::info('Extracting data from AI Tool for ' . $fileName . ': ' . json_encode($data));

            $task = $data['task'] ?? null;
            $taskFlightDetails = $data['task_flight_details'] ?? null;
            $taskHotelDetails = $data['task_hotel_details'] ?? null;

            if ($task['type'] === 'flight') {
                $processedData = [
                    'status' => 'success',
                    'message' => "Successfully processed {$fileName} using AI.",
                    'original_filename' => $fileName,
                    'data' => [
                        'additional_info' => $task['additional_info'] ?? 'N/A',
                        'ticket_number' => $task['ticket_number'] ?? 'N/A',
                        'status' => $task['status'] ?? 'N/A',
                        'reference' => $task['reference'] ?? 'N/A',
                        'gds_office_id' => $task['gds_office_id'] ?? 'N/A',
                        'type' => $task['type'] ?? 'N/A',
                        'agent_name' => $task['agent_name'] ?? 'N/A',
                        'agent_email' => $task['agent_email'] ?? 'N/A',
                        'agent_amadeus_id' => $task['agent_amadeus_id'] ?? 'N/A',
                        'client_name' => $task['client_name'] ?? 'N/A',
                        'supplier_name' => $task['supplier_name'] ?? 'N/A',
                        'supplier_country' => $task['supplier_country'] ?? 'N/A',
                        'cancellation_policy' => $task['cancellation_policy'] ?? null,
                        'venue' => $task['venue'] ?? null,
                        'price' => $task['price'] ?? null,
                        'surcharge' => $task['surcharge'] ?? null,
                        'tax' => $task['tax'] ?? null,
                        'taxes_record' => $task['taxes_record'] ?? 'N/A',
                        'penalty_fee' => $task['penalty_fee'] ?? 0.00,
                        'refund_charge' => $task['refund_charge'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                        'task_flight_details' => [
                            'farebase' => $taskFlightDetails['farebase'] ?? null,
                            'departure_time' => $taskFlightDetails['departure_time'] ?? null,
                            'departure_from' => $taskFlightDetails['departure_from'] ?? null,
                            'airport_from' => $taskFlightDetails['airport_from'] ?? 'N/A',
                            'terminal_from' => $taskFlightDetails['terminal_from'] ?? 'N/A',
                            'arrival_time' => $taskFlightDetails['arrival_time'] ?? null,
                            'duration_time' => $taskFlightDetails['duration_time'] ?? 'N/A',
                            'arrive_to' => $taskFlightDetails['arrive_to'] ?? 'N/A',
                            'airport_to' => $taskFlightDetails['airport_to'] ?? 'N/A',
                            'terminal_to' => $taskFlightDetails['terminal_to'] ?? 'N/A',
                            'airline_name' => $taskFlightDetails['airline_name'] ?? 'N/A',
                            'flight_number' => $taskFlightDetails['flight_number'] ?? 'N/A',
                            'class_type' => $taskFlightDetails['class_type'] ?? 'N/A',
                            'baggage_allowed' => $taskFlightDetails['baggage_allowed'] ?? 'N/A',
                            'equipment' => $taskFlightDetails['equipment'] ?? null,
                            'flight_meal' => $taskFlightDetails['flight_meal'] ?? null,
                            'seat_no' => $taskFlightDetails['seat_no'] ?? null,
                        ],
                        
                    ]
                ];
            } else if( $task['type'] === 'hotel'){
                $processedData = [
                    'status' => 'success',
                    'message' => "Successfully processed {$fileName} using AI.",
                    'original_filename' => $fileName,
                    'data' => [
                        'additional_info' => $task['additional_info'] ?? 'N/A',
                        'ticket_number' => $task['ticket_number'] ?? 'N/A',
                        'status' => $task['status'] ?? 'N/A',
                        'reference' => $task['reference'] ?? 'N/A',
                        'created_by' => $task['created_by'] ?? null,
                        'issued_by' => $task['issued_by'] ?? null,
                        'type' => $task['type'] ?? 'N/A',
                        'agent_name' => $task['agent_name'] ?? 'N/A',
                        'agent_email' => $task['agent_email'] ?? 'N/A',
                        'client_name' => $task['client_name'] ?? 'N/A',
                        'supplier_name' => $task['supplier_name'] ?? 'N/A',
                        'supplier_country' => $task['supplier_country'] ?? null,
                        'cancellation_policy' => $task['cancellation_policy'] ?? null,
                        'venue' => $task['venue'] ?? null,
                        'price' => $task['price'] ?? null,
                        'surcharge' => $task['surcharge'] ?? null,
                        'tax' => $task['tax'] ?? null,
                        'taxes_record' => $task['taxes_record'] ?? 'N/A',
                        'penalty_fee' => $task['penalty_fee'] ?? 0.00,
                        'refund_charge' => $task['refund_charge'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                        'task_hotel_details' => [
                            'hotel_name' => $taskHotelDetails['hotel_name'] ?? null,
                            'check_in_date' => $taskHotelDetails['check_in_date'] ?? null,
                            'check_out_date' => $taskHotelDetails['check_out_date'] ?? null,
                            'room_type' => $taskHotelDetails['room_type'] ?? null,
                            'number_of_rooms' => $taskHotelDetails['number_of_rooms'] ?? null,
                            'number_of_guests' => $taskHotelDetails['number_of_guests'] ?? null,
                            'meal_plan' => $taskHotelDetails['meal_plan'] ?? null,
                        ],
                    ]
                ];

            } else {
                Log::warning("Unsupported task type in {$fileName}: {$task['type']}");
                return null;
            }

            return $processedData;

        } elseif (in_array($extension, ['txt', 'text', 'air'])) {

            $fileContent = File::get($filePath);

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
                        'supplier_status' => $extractedData['status'] ?? 'N/A',
                        'reference' => $extractedData['reference'] ?? 'N/A',
                        'created_by' => $extractedData['created_by'] ?? null,
                        'issued_by' => $extractedData['issued_by'] ?? null,
                        'type' => $extractedData['type'] ?? 'N/A',
                        'agent_name' => $extractedData['agent_name'] ?? 'N/A',
                        'agent_email' => $extractedData['agent_email'] ?? 'N/A',
                        'agent_amadeus_id' => $extractedData['agent_amadeus_id'] ?? 'N/A',
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
        } else {
            Log::warning("Unsupported file type for {$fileName}: {$extension}");
            $this->warn("Unsupported file type for {$fileName}: {$extension}");
            return null;
        }
    }

    protected function saveTask($companyId, $data) : array
    {
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

        $supplier = Supplier::where('name', 'Amadeus')->first();

        if ($supplier) {
            $data['supplier_id'] = $supplier->id;
        } else {
            Log::error("Supplier 'Amadeus' not found.");
            return [
                'status' => 'error',
                'message' => 'Supplier not found',
            ];
        }


        try {
            $data['company_id'] = $companyId;
            $data['total'] = $data['price'] + $data['surcharge'] + $data['tax'] + $data['penalty_fee'];
            $data['enabled'] = true;

            $taskController = new TaskController();

            $request = new Request($data);
            $response = $taskController->store($request);

            if ($response->getStatusCode() !== 201) {
                Log::error("Failed to save task: " . $response->getContent());
                throw new Exception("Failed to save task: " . $response->getContent());
            }

        } catch (Exception $e) {
            Log::error("Failed to save task: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'status' => 'error',
                'message' => 'Failed to save task',
                'error' => $e->getMessage(),
            ];
        }

        $task = $response->getData();

        return [
            'status' => 'success',
            'message' => 'Task saved successfully',
            'task' => $task,
        ];

    }

    protected function moveFileWithLogging(
        string $sourcePath,
        string $destinationDir,
        string $fileName,
        string $reason = ''
    )
    {
        if (!File::isDirectory($destinationDir)) {
            File::makeDirectory($destinationDir, 0755, true, true);
            Log::info("Created directory: {$destinationDir}");
        }

        $destinationPath = $destinationDir . '/' . $fileName;

        // Move the file
        File::move($sourcePath, $destinationPath);

        $msg = "Moved file {$fileName} to {$destinationDir}";
        if ($reason) {
            $msg .= " ({$reason})";
        }
        Log::info($msg);
        $this->info($msg);
    }
}