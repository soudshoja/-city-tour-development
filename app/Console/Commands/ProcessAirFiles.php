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
                        $extractedData = $this->aiManager->processWithAiTool($fileRealPath, $fileName);

                        if ($extractedData['status'] === 'error') {
                            Log::error("AIR File Processing: AI tool processing error for {$fileName}: " . $extractedData['message']);
                            $this->error("AI tool processing error for {$fileName}: " . $extractedData['message']);

                            $errorPath = storage_path("app/{$companyName}/{$supplierName}/files_error");
                            $this->moveFileWithLogging($fileRealPath, $errorPath, $fileName, 'AI tool processing error');
                            continue;
                        }

                        $extractedData = is_array($extractedData) ? $extractedData : json_decode($extractedData, true);

                        $dataItems = [];
                        if (isset($extractedData['data']) && is_array($extractedData['data'])) {
                            // If it's an array of objects
                            if (array_keys($extractedData['data']) === range(0, count($extractedData['data']) - 1)) {
                                $dataItems = $extractedData['data'];
                            } else {
                                // Single object as associative array
                                $dataItems[] = $extractedData['data'];
                            }
                        } else {
                            $dataItems[] = $extractedData['data'] ?? [];
                        }

                        $allSuccess = true;
                        foreach ($dataItems as $taskData) {
                            $agentName = $taskData['agent_name'] ?? null;
                            $agentEmail = $taskData['agent_email'] ?? null;
                            $agentAmadeusId = $taskData['agent_amadeus_id'] ?? null;

                            Log::info("Processing file {$fileName} with agent data: ", [
                                'agent_name' => $agentName,
                                'agent_email' => $agentEmail,
                                'amadeus_id_agent' => $agentAmadeusId
                            ]);

                            // Check if all agent fields are missing
                            if ($taskData['status'] == 'reissued' || $taskData['status'] == 'refund' || $taskData['status'] == 'void' || $taskData['status'] == 'emd') {
                                Log::info("Task status is not 'issued'. Checking original task for reference: {$taskData['reference']}");

                                $originalTask = Task::where('reference', $taskData['reference'])
                                    ->where('status', 'issued')
                                    ->first();

                                if (!$originalTask) {
                                    Log::warning('Original task not found for reference: ', $taskData);
                                    return response()->json([
                                        'status' => 'error',
                                        'message' => 'Original task not found. Task status: ' . $taskData['status'],
                                    ], 404);
                                }

                                $taskData['original_task_id'] = $originalTask->id;
                                Log::info("Original Task ID: " . $taskData['original_task_id']);

                                $flightDetails = TaskFlightDetail::where('task_id', $taskData['original_task_id'])->get();

                                if ($flightDetails->isEmpty()) {
                                    Log::warning("No flight details found for original task ID: {$taskData['original_task_id']}");
                                    return response()->json([
                                        'status' => 'error',
                                        'message' => 'No flight details found for original task.',
                                    ], 404);
                                }
                                $flightDetailsArray = $flightDetails->toArray();
                                Log::info("Flight Details for Task ID {$taskData['original_task_id']}: ", $flightDetailsArray);
                                $taskData['task_flight_details'] = $flightDetailsArray;
                                $agentQuery = Agent::query();

                                if ($agentAmadeusId) {
                                    $agentQuery->orWhere('amadeus_id', 'like', $agentAmadeusId);
                                }

                                if ($agentName) {
                                    $agentQuery->orWhere('name', 'like', $agentName);
                                }

                                if ($agentEmail) {
                                    $agentQuery->orWhere('email', 'like', $agentEmail);
                                }

                                $agent = $agentQuery->first();
                                $taskData['agent_id'] = $agent->id;

                                // Fetch the branch associated with the agent
                                $branchId = $agent->branch_id;
                                $branch = Branch::find($branchId);

                                if (!$branch) {
                                    Log::error("AIR File Processing: Branch not found for agent {$agentName} in {$fileName}. Skipping save.");
                                    $this->error("Branch not found for agent {$agentName} in {$fileName}. File will remain in place.");

                                    $errorPath = storage_path("app/{$companyName}/{$supplierName}/files_error");
                                    $this->moveFileWithLogging($fileRealPath, $errorPath, $fileName, 'Branch not found');
                                    $allSuccess = false;
                                    break;
                                }

                                $companyId = $branch->company_id;

                                // Save the task data
                                $response = $this->saveTask($companyId, $taskData);

                                if ($response['status'] === 'error') {
                                    if (isset($response['code']) && $response['code'] === 409) {
                                        Log::info("Task already exists for {$fileName}. Skipping save.");
                                        $this->info("Task already exists for {$fileName}. Skipping save.");

                                        $errorPath = storage_path("app/{$companyName}/{$supplierName}/files_error");
                                        $this->moveFileWithLogging($fileRealPath, $errorPath, $fileName, 'Task already exists');
                                        $allSuccess = false;
                                        break;
                                    } else {
                                        Log::error("Failed to save task for {$fileName}: " . $response['message']);
                                        $this->error("Failed to save task for {$fileName}: " . $response['message']);

                                        $errorPath = storage_path("app/{$companyName}/{$supplierName}/files_error");
                                        $this->moveFileWithLogging($fileRealPath, $errorPath, $fileName, 'Failed to save task');
                                        Log::info("AIR File Processing: Moved {$fileName} to error directory {$errorPath}.");
                                        $this->info("File {$fileName} moved to error directory due to save failure.");
                                        $allSuccess = false;
                                        break;
                                    }
                                }

                            } else {
                                // If task is 'issued', use the agent data from the current task
                                Log::info("Task is 'issued', checking agent using Amadeus ID, name, or email");

                                // Log the values being used for the query
                                Log::info("Querying for agent with values: ", [
                                    'amadeus_id' => $agentAmadeusId,
                                    'name' => $agentName,
                                    'email' => $agentEmail,
                                ]);

                                $agentQuery = Agent::query();

                                if ($agentAmadeusId) {
                                    $agentQuery->orWhere('amadeus_id', 'like', $agentAmadeusId);
                                }

                                if ($agentName) {
                                    $agentQuery->orWhere('name', 'like', $agentName);
                                }

                                if ($agentEmail) {
                                    $agentQuery->orWhere('email', 'like', $agentEmail);
                                }

                                $agent = $agentQuery->first();


                                // Log the SQL query to check for issues
                                Log::info('SQL Query Executed: ', [
                                    'agentQuery' => DB::getQueryLog()
                                ]);

                                if (!$agent) {
                                    Log::warning("AIR File Processing: Agent not found for {$fileName}. Agent name: {$agentName}, email: {$agentEmail}, Amadeus ID: {$agentAmadeusId}");
                                    $this->warn("Agent not found for {$fileName}.");
                                } else {
                                    Log::info("Agent found: ", [
                                        'agent_name' => $agent->name,
                                        'agent_email' => $agent->email,
                                        'amadeus_id_agent' => $agent->amadeus_id
                                    ]);
                                }
                            }

                            // Try to find the agent using the Amadeus ID, name, or email
                           /*  $agent = Agent::where('amadeus_id', 'like', $agentAmadeusId)
                                ->orWhere('name', 'like', $agentName)
                                ->orWhere('email', 'like', $agentEmail)
                                ->first(); */

                            // Log the SQL query to check for issues
                           /*  Log::info('SQL Query Executed: ', [
                                'query' => DB::getQueryLog()
                            ]); */

                           /*  if (!$agent) {
                                Log::warning("AIR File Processing: Agent not found for {$fileName}. Agent name: {$agentName}, email: {$agentEmail}, Amadeus ID: {$agentAmadeusId}");
                                $this->warn("Agent not found for {$fileName}.");
                            } else {
                                Log::info("Agent found: ", [
                                    'agent_name' => $agent->name,
                                    'agent_email' => $agent->email,
                                    'amadeus_id_agent' => $agent->amadeus_id
                                ]);
                            } */

                            // Associate the agent with the task data
                            $taskData['agent_id'] = $agent->id;

                            // Fetch the branch associated with the agent
                            $branchId = $agent->branch_id;
                            $branch = Branch::find($branchId);

                            if (!$branch) {
                                Log::error("AIR File Processing: Branch not found for agent {$agentName} in {$fileName}. Skipping save.");
                                $this->error("Branch not found for agent {$agentName} in {$fileName}. File will remain in place.");

                                $errorPath = storage_path("app/{$companyName}/{$supplierName}/files_error");
                                $this->moveFileWithLogging($fileRealPath, $errorPath, $fileName, 'Branch not found');
                                $allSuccess = false;
                                break;
                            }

                            $companyId = $branch->company_id;

                            // Save the task data
                            $response = $this->saveTask($companyId, $taskData);

                            if ($response['status'] === 'error') {
                                if (isset($response['code']) && $response['code'] === 409) {
                                    Log::info("Task already exists for {$fileName}. Skipping save.");
                                    $this->info("Task already exists for {$fileName}. Skipping save.");

                                    $errorPath = storage_path("app/{$companyName}/{$supplierName}/files_error");
                                    $this->moveFileWithLogging($fileRealPath, $errorPath, $fileName, 'Task already exists');
                                    $allSuccess = false;
                                    break;
                                } else {
                                    Log::error("Failed to save task for {$fileName}: " . $response['message']);
                                    $this->error("Failed to save task for {$fileName}: " . $response['message']);

                                    $errorPath = storage_path("app/{$companyName}/{$supplierName}/files_error");
                                    $this->moveFileWithLogging($fileRealPath, $errorPath, $fileName, 'Failed to save task');
                                    Log::info("AIR File Processing: Moved {$fileName} to error directory {$errorPath}.");
                                    $this->info("File {$fileName} moved to error directory due to save failure.");
                                    $allSuccess = false;
                                    break;
                                }
                            }
                        }

                        if ($allSuccess) {
                            $this->info("File {$fileName} processed successfully by AI tool.");
                            $successPath = storage_path("app/{$companyName}/{$supplierName}/files_processed");
                            $this->moveFileWithLogging($fileRealPath, $successPath, $fileName, 'Successfully processed and saved task');
                            Log::info("AIR File Processing: Successfully moved {$fileName} to {$successPath}.");
                        }
                    } catch (Exception $e) {
                        $this->error("Error processing file {$fileName}: " . $e->getMessage());
                        Log::error("AIR File Processing: Error processing file {$fileName}. Error: " . $e->getMessage(), [
                            'file' => $fileName,
                            'trace' => $e->getTraceAsString()
                        ]);

                        $errorPath = storage_path("app/{$companyName}/{$supplierName}/files_error");
                        $this->moveFileWithLogging($fileRealPath, $errorPath, $fileName, 'Error during processing');
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


    protected function saveTask($companyId, $data): array
    {
        $existingTask = Task::where('reference', $data['reference'])
            ->where('company_id', $companyId)
            ->where('status', $data['status'])
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
            $data['enabled'] = false;

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
    ) {
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
