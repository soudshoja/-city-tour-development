<?php

namespace App\Console\Commands;

use App\AI\AIManager;
use App\Http\Controllers\TaskController;
use App\Models\Agent;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplierCompany;
use App\Models\Task;
use App\Models\TaskFlightDetail;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ProcessAirFiles extends Command
{
    protected $signature = 'app:process-files';
    protected $description = 'Scans the root air-files directory for new AIR files, processes them using existing logic, and moves them.';
    protected $aiManager;
    protected $companies;

    public function __construct(AIManager $aiManager)
    {
        parent::__construct();
        $this->aiManager = $aiManager;
    }

    public function handle()
    {
        $this->companies = Company::all();

        $this->info('Starting AIR file processing from root/air-files directory...');
        Log::info('AIR File Processing: Service started.');

        foreach ($this->companies as $company) {
            $companyName = strtolower(preg_replace('/\s+/', '_', $company->name));
            $suppliers = $company->suppliers()
                ->wherePivot('is_active', true)
                ->get();

            if ($suppliers->isEmpty()) {
                $this->info("No active suppliers found for company: {$companyName}");
                Log::info("AIR File Processing: No active suppliers found for company {$companyName}.");
                continue;
            }

            foreach ($suppliers as $supplier) {
                $supplierName = strtolower(preg_replace('/\s+/', '_', $supplier->name));
                $supplierId = $supplier->id;

                if (!$supplierName) {
                    $this->error("Supplier name is empty for company: {$companyName}");
                    Log::error("AIR File Processing: Supplier name is empty for company {$companyName}.");
                    continue;
                }

                $filePath = storage_path("app/{$companyName}/{$supplierName}/files_unprocessed");

                if (!File::isDirectory($filePath)) {
                    $this->error("Source directory not found: {$filePath}");
                    Log::error("AIR File Processing: Source directory {$filePath} not found.");
                    File::makeDirectory($filePath, 0755, true, true);
                    $this->info("Created source directory: {$filePath}, please ensure files are pushed here.");
                    continue;
                }

                $filesToProcess = File::files($filePath);

                if (empty($filesToProcess)) {
                    $this->info("No new files found in {$companyName}/{$supplierName} air-files directory to process.");
                    Log::info("AIR File Processing: No new files found in {$companyName}/{$supplierName}.");
                    continue;
                }

                $this->info(count($filesToProcess) . " file(s) found in {$companyName}/{$supplierName} air-files.");
                foreach ($filesToProcess as $file) {
                    $this->processSingleFile($company->id, $companyName, $supplierName, $supplierId, $file);
                }

                $this->info('AIR file processing for supplier ' . $supplierName . ' in company ' . $companyName . ' finished.');
                Log::info('AIR File Processing: Finished processing for supplier ' . $supplierName . ' in company ' . $companyName . '.');
            }
        }
        Log::info('AIR File Processing: Service finished.');
        return 0;
    }

    /**
     * Process a single AIR file with proper error handling and separation of concerns.
     */
    protected function processSingleFile($companyId, $companyName, $supplierName, $supplierId, $file)
    {
        $fileRealPath = $file->getRealPath();
        $fileName = $file->getFilename();

        $this->info("Processing file: {$fileName}");
        Log::info("AIR File Processing: Starting file {$fileName}");

        try {
            $extractedData = $this->aiManager->processWithAiTool($fileRealPath, $fileName);

            if ($extractedData['status'] === 'error') {
                $this->handleFileError($companyName, $supplierName, $fileRealPath, $fileName, 'AI tool processing error', $extractedData['message']);
                return;
            }

            $extractedData = is_array($extractedData) ? $extractedData : json_decode($extractedData, true);

            $dataItems = [];
            if (isset($extractedData['data']) && is_array($extractedData['data'])) {
                if (array_keys($extractedData['data']) === range(0, count($extractedData['data']) - 1)) {
                    $dataItems = $extractedData['data'];
                } else {
                    $dataItems[] = $extractedData['data'];
                }
            } else {
                $dataItems[] = $extractedData['data'] ?? [];
            }

            // Process all items and collect results
            $processingResults = [];
            $allSuccess = true;

            foreach ($dataItems as $index => $taskData) {
                try {
                    $result = $this->processTaskData($companyId, $companyName, $supplierName, $supplierId, $fileName, $taskData, $index);
                    $processingResults[] = $result;
                    
                    if (!$result['success']) {
                        $allSuccess = false;
                    }
                } catch (Exception $e) {
                    $processingResults[] = [
                        'success' => false,
                        'index' => $index,
                        'error' => $e->getMessage(),
                        'reason' => 'Exception during task data processing'
                    ];
                    $allSuccess = false;
                    Log::error("AIR File Processing: Exception processing item {$index} in {$fileName}: " . $e->getMessage());
                }
            }

            // Log processing summary
            $successCount = count(array_filter($processingResults, fn($r) => $r['success']));
            $totalCount = count($processingResults);
            
            $this->info("File {$fileName}: {$successCount}/{$totalCount} items processed successfully");
            Log::info("AIR File Processing: File {$fileName} summary - {$successCount}/{$totalCount} items successful", [
                'results' => $processingResults
            ]);

            // Move file based on overall result
            if ($allSuccess) {
                $successPath = storage_path("app/{$companyName}/{$supplierName}/files_processed");
                $this->moveFileWithLogging($fileRealPath, $successPath, $fileName, "All {$totalCount} items processed successfully");
            } else {
                $errorPath = storage_path("app/{$companyName}/{$supplierName}/files_error");
                $this->moveFileWithLogging($fileRealPath, $errorPath, $fileName, "Processing failed: {$successCount}/{$totalCount} items successful");
            }

        } catch (Exception $e) {
            $this->handleFileError($companyName, $supplierName, $fileRealPath, $fileName, 'Error during processing', $e->getMessage());
        }
    }

    /**
     * Handles a single task data item, returns array with success status and details.
     */
    protected function processTaskData(
        $companyId,
        $companyName,
        $supplierName,
        $supplierId,
        $fileName,
        array $taskData,
        int $index
    ): array
    {
        $agentName = $taskData['agent_name'] ?? null;
        $agentEmail = $taskData['agent_email'] ?? null;
        $agentAmadeusId = $taskData['agent_amadeus_id'] ?? null;

        Log::info("Processing file {$fileName} item {$index} with agent data: ", [
            'agent_name' => $agentName,
            'agent_email' => $agentEmail,
            'amadeus_id_agent' => $agentAmadeusId
        ]);

        if (in_array($taskData['status'], ['reissued', 'refund', 'void', 'emd'])) {
            Log::info("Task status is not 'issued'. Checking original task for reference: {$taskData['reference']}");

            $originalTask = Task::where('reference', $taskData['reference'])
                ->where('status', 'issued')
                ->first();

            if (!$originalTask) {
                $this->warn("Original task not found for reference: {$taskData['reference']} (item {$index})");
                Log::warning("Original task not found for reference in item {$index}: ", $taskData);

                // Save to task directly with enabled = false
                $taskData['enabled'] = false;
                $taskData['file_name'] = $fileName;
                $response = $this->saveTask($companyId, $taskData, $supplierId);

                if ($response['status'] === 'error') {
                    return [
                        'success' => false,
                        'index' => $index,
                        'reason' => 'Failed to save task (original task not found)',
                        'error' => $response['message']
                    ];
                }

                return [
                    'success' => true,
                    'index' => $index,
                    'reason' => 'Task saved with enabled=false (original task not found)',
                    'task_id' => $response['task']->id ?? null
                ];
            }

            $taskData['original_task_id'] = $originalTask->id;
            Log::info("Original Task ID: " . $taskData['original_task_id']);

            $flightDetails = TaskFlightDetail::where('task_id', $taskData['original_task_id'])->get();

            if ($flightDetails->isEmpty()) {
                Log::warning("No flight details found for original task ID: {$taskData['original_task_id']} (item {$index})");
                
                // Save to task directly with enabled = false
                $taskData['enabled'] = false;
                $taskData['file_name'] = $fileName;
                $response = $this->saveTask($companyId, $taskData, $supplierId);

                if ($response['status'] === 'error') {
                    return [
                        'success' => false,
                        'index' => $index,
                        'reason' => 'Failed to save task (no flight details)',
                        'error' => $response['message']
                    ];
                }

                return [
                    'success' => true,
                    'index' => $index,
                    'reason' => 'Task saved with enabled=false (no flight details)',
                    'task_id' => $response['task']->id ?? null
                ];
            }

            $flightDetailsArray = $flightDetails->toArray();
            Log::info("Flight Details for Task ID {$taskData['original_task_id']}: ", $flightDetailsArray);
            $taskData['task_flight_details'] = $flightDetailsArray;

            $agent = $this->findAgent($agentAmadeusId, $agentName, $agentEmail);

            if (!$agent) {
                Log::warning("AIR File Processing: Agent not found for {$fileName} item {$index}. Agent name: {$agentName}, email: {$agentEmail}, Amadeus ID: {$agentAmadeusId}");
                $this->warn("Agent not found for {$fileName} item {$index}.");

                // Save to task directly with enabled = false
                $taskData['enabled'] = false;
                $taskData['file_name'] = $fileName;
                $response = $this->saveTask($companyId, $taskData, $supplierId);

                if ($response['status'] === 'error') {
                    return [
                        'success' => false,
                        'index' => $index,
                        'reason' => 'Failed to save task (agent not found)',
                        'error' => $response['message']
                    ];
                }

                return [
                    'success' => true,
                    'index' => $index,
                    'reason' => 'Task saved with enabled=false (agent not found)',
                    'task_id' => $response['task']->id ?? null
                ];
            }

            $taskData['agent_id'] = $agent->id;

            $branchId = $agent->branch_id;
            $branch = Branch::find($branchId);

            if (!$branch) {
                Log::error("AIR File Processing: Branch not found for agent {$agentName} in {$fileName} item {$index}.");

                // Save to task directly with enabled = false
                $taskData['enabled'] = false;
                $taskData['file_name'] = $fileName;
                $response = $this->saveTask($companyId, $taskData, $supplierId);

                if ($response['status'] === 'error') {
                    return [
                        'success' => false,
                        'index' => $index,
                        'reason' => 'Failed to save task (branch not found)',
                        'error' => $response['message']
                    ];
                }

                return [
                    'success' => true,
                    'index' => $index,
                    'reason' => 'Task saved with enabled=false (branch not found)',
                    'task_id' => $response['task']->id ?? null
                ];
            }

            $companyId = $branch->company_id;
        } else {
            Log::info("Task is 'issued', checking agent using Amadeus ID, name, or email (item {$index})");

            $agent = $this->findAgent($agentAmadeusId, $agentName, $agentEmail);

            if (!$agent) {
                Log::warning("AIR File Processing: Agent not found for {$fileName} item {$index}. Agent name: {$agentName}, email: {$agentEmail}, Amadeus ID: {$agentAmadeusId}");
                $this->warn("Agent not found for {$fileName} item {$index}.");

                // Save to task directly with enabled = false
                $taskData['enabled'] = false;
                $taskData['file_name'] = $fileName;
                $response = $this->saveTask($companyId, $taskData, $supplierId);

                if ($response['status'] === 'error') {
                    return [
                        'success' => false,
                        'index' => $index,
                        'reason' => 'Failed to save task (agent not found)',
                        'error' => $response['message']
                    ];
                }

                return [
                    'success' => true,
                    'index' => $index,
                    'reason' => 'Task saved with enabled=false (agent not found)',
                    'task_id' => $response['task']->id ?? null
                ];
            }

            $taskData['agent_id'] = $agent->id;
        }

        // Find branch and company for agent
        $branchId = $agent->branch_id;
        $branch = Branch::find($branchId);

        if (!$branch) {
            Log::error("AIR File Processing: Branch not found for agent {$agentName} in {$fileName} item {$index}.");
            
            // Save to task directly with enabled = false
            $taskData['enabled'] = false;
            $taskData['file_name'] = $fileName;
            $response = $this->saveTask($companyId, $taskData, $supplierId);

            if ($response['status'] === 'error') {
                return [
                    'success' => false,
                    'index' => $index,
                    'reason' => 'Failed to save task (branch not found)',
                    'error' => $response['message']
                ];
            }

            return [
                'success' => true,
                'index' => $index,
                'reason' => 'Task saved with enabled=false (branch not found)',
                'task_id' => $response['task']->id ?? null
            ];
        }

        $companyId = $branch->company_id;
        
        $taskData['enabled'] = false;
        $taskData['file_name'] = $fileName;
        $response = $this->saveTask($companyId, $taskData, $supplierId);

        if ($response['status'] === 'error') {
            if (isset($response['code']) && $response['code'] === 409) {
                Log::info("Task already exists for {$fileName} item {$index}. Skipping save.");
                return [
                    'success' => false,
                    'index' => $index,
                    'reason' => 'Task already exists',
                    'error' => 'Duplicate task'
                ];
            } else {
                Log::error("Failed to save task for {$fileName} item {$index}: " . $response['message']);
                return [
                    'success' => false,
                    'index' => $index,
                    'reason' => 'Failed to save task',
                    'error' => $response['message']
                ];
            }
        }

        return [
            'success' => true,
            'index' => $index,
            'reason' => 'Task saved successfully with enabled=true',
            'task_id' => $response['task']->id ?? null
        ];
    }

    /**
     * Find agent by Amadeus ID, name, or email.
     */
    protected function findAgent($amadeusId, $name, $email)
    {
        $agentQuery = Agent::query();

        if ($amadeusId) {
            $agentQuery->orWhere('amadeus_id', 'like', $amadeusId);
        }
        if ($name) {
            $agentQuery->orWhere('name', 'like', $name);
        }
        if ($email) {
            $agentQuery->orWhere('email', 'like', $email);
        }

        return $agentQuery->first();
    }

    /**
     * Handle file error: log, move file, and show error.
     */
    protected function handleFileError($companyName, $supplierName, $fileRealPath, $fileName, $reason, $errorMessage = '')
    {
        $this->error("{$reason} for {$fileName}: {$errorMessage}");
        Log::error("AIR File Processing: {$reason} for {$fileName}: {$errorMessage}");
        $errorPath = storage_path("app/{$companyName}/{$supplierName}/files_error");
        $this->moveFileWithLogging($fileRealPath, $errorPath, $fileName, $reason);
    }

    protected function saveTask($companyId, $data, $supplierId)
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

        try {
            $data['company_id'] = $companyId;
            $data['supplier_id'] = $supplierId;

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
