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
use App\Models\FileUpload;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ProcessAirFiles extends Command
{
    protected $signature = 'app:process-files 
                            {--batch : Use batch processing (upload all files first, then process together)[default]}
                            {--single : Use single file processing (process files one by one)}
                            {--batch-size=10 : Maximum number of files to process in a single batch}';
    protected $description = 'Scans the root air-files directory for new AIR files, processes them using existing logic, and moves them.';
    protected $aiManager;
    protected $companies;
    protected $logger;

    public function __construct(AIManager $aiManager)
    {
        parent::__construct();
        $this->aiManager = $aiManager;
        $this->logger = Log::channel('air_processing');
    }

    public function handle()
    {
        $this->companies = Company::all();
        $useBatch = $this->option('batch') || (!$this->option('single') && !$this->option('batch'));
        $batchSize = max(1, (int) $this->option('batch-size'));

        $processingMode = $useBatch ? 'batch' : 'single';
        $this->info("Starting AIR file processing from root/air-files directory using {$processingMode} processing...");
        
        if ($useBatch) {
            $this->info("Batch size: {$batchSize} files per batch");
        }
        
        $this->logger->info('AIR File Processing: Service started.', [
            'mode' => $processingMode,
            'batch_size' => $useBatch ? $batchSize : null
        ]);

        $this->logger->info('AIR File Processing Service Started', [
            'mode' => $processingMode,
            'batch_size' => $useBatch ? $batchSize : null,
            'companies_count' => $this->companies->count()
        ]);

        foreach ($this->companies as $company) {
            $companyName = strtolower(preg_replace('/\s+/', '_', $company->name));
            $suppliers = $company->suppliers()
                ->wherePivot('is_active', true)
                ->get();

            if ($suppliers->isEmpty()) {
                $this->info("No active suppliers found for company: {$companyName}");
                $this->logger->info("AIR File Processing: No active suppliers found for company {$companyName}.");
                $this->logger->info("No active suppliers found", ['company' => $companyName]);
                continue;
            }

            foreach ($suppliers as $supplier) {
                $supplierName = strtolower(preg_replace('/\s+/', '_', $supplier->name));
                $supplierId = $supplier->id;

                if (!$supplierName) {
                    $this->error("Supplier name is empty for company: {$companyName}");
                    $this->logger->error("AIR File Processing: Supplier name is empty for company {$companyName}.");
                    $this->logger->error("Supplier name is empty", ['company' => $companyName]);
                    continue;
                }

                $filePath = storage_path("app/{$companyName}/{$supplierName}/files_unprocessed");

                if (!File::isDirectory($filePath)) {
                    $this->error("Source directory not found: {$filePath}");
                    $this->logger->error("Source directory not found", ['path' => $filePath, 'company' => $companyName, 'supplier' => $supplierName]);
                    File::makeDirectory($filePath, 0755, true, true);
                    $this->info("Created source directory: {$filePath}, please ensure files are pushed here.");
                    $this->logger->info("Created source directory", ['path' => $filePath, 'company' => $companyName, 'supplier' => $supplierName]);
                    continue;
                }

                $filesToProcess = File::files($filePath);

                if (empty($filesToProcess)) {
                    $this->info("No new files found in {$companyName}/{$supplierName} air-files directory to process.");
                    $this->logger->info("No files found to process", ['company' => $companyName, 'supplier' => $supplierName]);
                    continue;
                }

                $this->info(count($filesToProcess) . " file(s) found in {$companyName}/{$supplierName} air-files.");
                $this->logger->info("Files found for processing", [
                    'company' => $companyName,
                    'supplier' => $supplierName,
                    'file_count' => count($filesToProcess),
                    'processing_mode' => $useBatch ? 'batch' : 'single'
                ]);
                
                // Choose processing method based on command options
                if ($useBatch) {
                    // Process files in batches
                    $chunks = array_chunk($filesToProcess, $batchSize);
                    $this->logger->info("Starting batch processing", [
                        'company' => $companyName,
                        'supplier' => $supplierName,
                        'total_files' => count($filesToProcess),
                        'batch_count' => count($chunks),
                        'batch_size' => $batchSize
                    ]);
                    
                    foreach ($chunks as $chunkIndex => $fileChunk) {
                        $this->info("Processing batch " . ($chunkIndex + 1) . "/" . count($chunks) . " (" . count($fileChunk) . " files) for {$companyName}/{$supplierName}");
                        $this->logger->info("Processing batch", [
                            'company' => $companyName,
                            'supplier' => $supplierName,
                            'batch_number' => $chunkIndex + 1,
                            'total_batches' => count($chunks),
                            'files_in_batch' => count($fileChunk)
                        ]);
                        $this->processBatchFiles($company->id, $companyName, $supplierName, $supplierId, $fileChunk);
                    }
                } else {
                    // Process files one by one (legacy mode)
                    $this->logger->info("Starting single file processing", [
                        'company' => $companyName,
                        'supplier' => $supplierName,
                        'file_count' => count($filesToProcess)
                    ]);
                    
                    foreach ($filesToProcess as $file) {
                        $this->processSingleFile($company->id, $companyName, $supplierName, $supplierId, $file);
                    }
                }

                $this->info('AIR file processing for supplier ' . $supplierName . ' in company ' . $companyName . ' finished.');
                $this->logger->info("Supplier processing completed", [
                    'company' => $companyName,
                    'supplier' => $supplierName
                ]);
            }
        }
        $this->logger->info('AIR File Processing: Service finished.');
        $this->logger->info("AIR File Processing Service Completed", [
            'companies_processed' => $this->companies->count()
        ]);
        return 0;
    }

    /**
     * Process multiple AIR files in batch - handle all file types (PDFs, text files, etc.).
     */
    protected function processBatchFiles($companyId, $companyName, $supplierName, $supplierId, array $files)
    {
        if (empty($files)) {
            return;
        }

        $this->info("Starting batch processing for " . count($files) . " files in {$companyName}/{$supplierName}");
        $this->logger->info("AIR File Processing: Starting batch processing", [
            'company' => $companyName,
            'supplier' => $supplierName,
            'file_count' => count($files)
        ]);
        
        $this->logger->info("Batch processing started", [
            'company' => $companyName,
            'supplier' => $supplierName,
            'file_count' => count($files)
        ]);

        // Step 1: Prepare file information for batch processing
        $fileInfoArray = [];
        $fileMap = [];

        foreach ($files as $file) {
            $fileRealPath = $file->getRealPath();
            $fileName = $file->getFilename();
            
            $fileInfoArray[] = [
                'path' => $fileRealPath,
                'name' => $fileName
            ];
            
            $fileMap[$fileName] = [
                'file_path' => $fileRealPath,
                'file_object' => $file
            ];
        }

        // Step 2: Process all files in batch using the new mixed file type method
        try {
            $this->info("Processing " . count($fileInfoArray) . " files in batch...");
            $this->logger->info("Starting AI batch processing", [
                'company' => $companyName,
                'supplier' => $supplierName,
                'file_count' => count($fileInfoArray)
            ]);
            
            $batchResults = $this->aiManager->processBatchFiles($fileInfoArray);
            
            if ($batchResults['status'] === 'error') {
                $this->error("Batch AI processing failed: " . $batchResults['message']);
                $this->logger->error("AIR File Processing: Batch AI processing failed", $batchResults);
                $this->logger->error("Batch AI processing failed", [
                    'company' => $companyName,
                    'supplier' => $supplierName,
                    'error' => $batchResults['message']
                ]);
                
                // Move all files to error directory
                foreach ($fileMap as $fileName => $fileInfo) {
                    $this->handleFileError($companyName, $supplierName, $fileInfo['file_path'], $fileName, 'Batch AI processing error', $batchResults['message']);
                }
                return;
            }

            // Step 3: Process results for each file
            $batchResultsData = $batchResults['data'] ?? [];
            $overallStats = ['total' => 0, 'success' => 0, 'error' => 0];

            foreach ($fileMap as $fileName => $fileInfo) {
                $filePath = $fileInfo['file_path'];
                $file = $fileInfo['file_object'];

                if (!isset($batchResultsData[$fileName])) {
                    $this->handleFileError($companyName, $supplierName, $filePath, $fileName, 'No results in batch response', "File {$fileName} not found in batch results");
                    $overallStats['error']++;
                    continue;
                }

                $fileResult = $batchResultsData[$fileName];
                
                if ($fileResult['status'] === 'error') {
                    $this->handleFileError($companyName, $supplierName, $filePath, $fileName, 'AI extraction error', $fileResult['message']);
                    $overallStats['error']++;
                    continue;
                }

                // Process the extracted data for this file
                $extractedData = $fileResult['data'] ?? [];
                $fileStats = $this->processExtractedDataForFile($companyId, $companyName, $supplierName, $supplierId, $fileName, $filePath, $extractedData);
                
                $overallStats['total'] += $fileStats['total'];
                $overallStats['success'] += $fileStats['success'];
                $overallStats['error'] += $fileStats['error'];

                // Move file based on processing results
                if ($fileStats['all_success']) {
                    $successPath = storage_path("app/{$companyName}/{$supplierName}/files_processed");
                    $this->moveFileWithLogging($filePath, $successPath, $fileName, "All {$fileStats['success']} items processed successfully");
                } else {
                    $errorPath = storage_path("app/{$companyName}/{$supplierName}/files_error");
                    $this->moveFileWithLogging($filePath, $errorPath, $fileName, "Processing failed: {$fileStats['success']}/{$fileStats['total']} items successful");
                }
            }

            // Log overall batch statistics
            $this->info("Batch processing completed: {$overallStats['success']}/{$overallStats['total']} total items successful, {$overallStats['error']} files failed");
            $this->logger->info("AIR File Processing: Batch processing completed", [
                'company' => $companyName,
                'supplier' => $supplierName,
                'stats' => $overallStats
            ]);
            
            $this->logger->info("Batch processing completed", [
                'company' => $companyName,
                'supplier' => $supplierName,
                'total_items' => $overallStats['total'],
                'successful_items' => $overallStats['success'],
                'failed_files' => $overallStats['error']
            ]);

        } catch (\Exception $e) {
            $this->error("Batch processing failed: " . $e->getMessage());
            $this->logger->error("AIR File Processing: Batch processing exception: " . $e->getMessage());
            $this->logger->error("Batch processing exception", [
                'company' => $companyName,
                'supplier' => $supplierName,
                'error' => $e->getMessage()
            ]);
            
            // Move all files to error directory
            foreach ($fileMap as $fileName => $fileInfo) {
                $this->handleFileError($companyName, $supplierName, $fileInfo['file_path'], $fileName, 'Batch processing exception', $e->getMessage());
            }
        }
    }

    /**
     * Process extracted data for a single file and return statistics.
     */
    protected function processExtractedDataForFile($companyId, $companyName, $supplierName, $supplierId, $fileName, $filePath, array $extractedData): array
    {
        $this->info("Processing extracted data for file: {$fileName}");
        $this->logger->info("AIR File Processing: Processing extracted data for {$fileName}");
        $this->logger->info("Processing extracted data for file", [
            'file_name' => $fileName,
            'company' => $companyName,
            'supplier' => $supplierName
        ]);

        $dataItems = [];
        if (is_array($extractedData)) {
            if (array_keys($extractedData) === range(0, count($extractedData) - 1)) {
                $dataItems = $extractedData;
            } else {
                $dataItems[] = $extractedData;
            }
        } else {
            $dataItems[] = $extractedData ?? [];
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
            } catch (\Exception $e) {
                $processingResults[] = [
                    'success' => false,
                    'index' => $index,
                    'error' => $e->getMessage(),
                    'reason' => 'Exception during task data processing'
                ];
                $allSuccess = false;
                $this->logger->error("AIR File Processing: Exception processing item {$index} in {$fileName}: " . $e->getMessage());
            }
        }

        // Log processing summary
        $successCount = count(array_filter($processingResults, fn($r) => $r['success']));
        $totalCount = count($processingResults);
        
        $this->info("File {$fileName}: {$successCount}/{$totalCount} items processed successfully");
        $this->logger->info("AIR File Processing: File {$fileName} summary - {$successCount}/{$totalCount} items successful", [
            'results' => $processingResults
        ]);
        
        $this->logger->info("File processing summary", [
            'file_name' => $fileName,
            'total_items' => $totalCount,
            'successful_items' => $successCount,
            'failed_items' => $totalCount - $successCount
        ]);

        return [
            'total' => $totalCount,
            'success' => $successCount,
            'error' => $totalCount - $successCount,
            'all_success' => $allSuccess,
            'results' => $processingResults
        ];
    }

    /**
     * Process a single AIR file with proper error handling and separation of concerns.
     */
    protected function processSingleFile($companyId, $companyName, $supplierName, $supplierId, $file)
    {
        $fileRealPath = $file->getRealPath();
        $fileName = $file->getFilename();

        $this->info("Processing file: {$fileName}");
        $this->logger->info("AIR File Processing: Starting file {$fileName}");
        $this->logger->info("Starting single file processing", [
            'file_name' => $fileName,
            'company' => $companyName,
            'supplier' => $supplierName
        ]);

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
                    $this->logger->error("AIR File Processing: Exception processing item {$index} in {$fileName}: " . $e->getMessage());
                    $this->logger->error("Exception processing item", [
                        'file_name' => $fileName,
                        'item_index' => $index,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Log processing summary
            $successCount = count(array_filter($processingResults, fn($r) => $r['success']));
            $totalCount = count($processingResults);
            
            $this->info("File {$fileName}: {$successCount}/{$totalCount} items processed successfully");
            $this->logger->info("AIR File Processing: File {$fileName} summary - {$successCount}/{$totalCount} items successful", [
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
    ): array {
        try {
            $agentName = $taskData['agent_name'] ?? null;
            $agentEmail = $taskData['agent_email'] ?? null;
            $agentAmadeusId = $taskData['agent_amadeus_id'] ?? null;

            $this->logger->info("Processing file {$fileName} item {$index} with agent data: ", [
                'agent_name' => $agentName,
                'agent_email' => $agentEmail,
                'amadeus_id_agent' => $agentAmadeusId
            ]);
            
            $this->logger->info("Processing task data item", [
                'file_name' => $fileName,
                'item_index' => $index,
                'has_agent_name' => !empty($agentName),
                'has_agent_email' => !empty($agentEmail),
                'has_agent_amadeus_id' => !empty($agentAmadeusId),
                'task_status' => $taskData['status'] ?? 'unknown'
            ]);

            if (in_array($taskData['status'], ['reissued', 'refund', 'void', 'emd'])) {
                $this->logger->info("Task status is not 'issued'. Checking original task for reference: {$taskData['reference']}");

                $originalTask = Task::where('reference', $taskData['reference'])
                    ->where('status', 'issued')
                    ->first();

                if (!$originalTask) {
                    $this->warn("Original task not found for reference: {$taskData['reference']} (item {$index})");
                    $this->logger->warning("Original task not found for reference in item {$index}: ", $taskData);

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
                        'reason' => 'Task saved with but original task not found',
                        'task_id' => $response['task']->id ?? null
                    ];
                }

                $taskData['original_task_id'] = $originalTask->id;
                $this->logger->info("Original Task ID: " . $taskData['original_task_id']);

                $flightDetails = TaskFlightDetail::where('task_id', $taskData['original_task_id'])->get();

                if ($flightDetails->isEmpty()) {
                    $this->logger->warning("No flight details found for original task ID: {$taskData['original_task_id']} (item {$index})");
                    
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
                        'reason' => 'Task saved with but no flight details found',
                        'task_id' => $response['task']->id ?? null
                    ];
                }

                $flightDetailsArray = $flightDetails->toArray();
                $this->logger->info("Flight Details for Task ID {$taskData['original_task_id']}: ", $flightDetailsArray);
                $taskData['task_flight_details'] = $flightDetailsArray;

                $agent = $this->findAgent($agentAmadeusId, $agentName, $agentEmail);

                if (!$agent) {
                    $this->logger->warning("AIR File Processing: Agent not found for {$fileName} item {$index}. Agent name: {$agentName}, email: {$agentEmail}, Amadeus ID: {$agentAmadeusId}");
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
                        'reason' => 'Task saved with but agent not found',
                        'task_id' => $response['task']->id ?? null
                    ];
                }

                $taskData['agent_id'] = $agent->id;

                $branchId = $agent->branch_id;
                $branch = Branch::find($branchId);

                if (!$branch) {
                    $this->logger->error("AIR File Processing: Branch not found for agent {$agentName} in {$fileName} item {$index}.");

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
                        'reason' => 'Task saved with but branch not found',
                        'task_id' => $response['task']->id ?? null
                    ];
                }

                $companyId = $branch->company_id;
            } else {
                $this->logger->info("Task is 'issued', checking agent using Amadeus ID, name, or email (item {$index})");

                $agent = $this->findAgent($agentAmadeusId, $agentName, $agentEmail);

                if (!$agent) {
                    $this->logger->warning("AIR File Processing: Agent not found for {$fileName} item {$index}. Agent name: {$agentName}, email: {$agentEmail}, Amadeus ID: {$agentAmadeusId}");
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
                        'reason' => 'Task saved with but agent not found',
                        'task_id' => $response['task']->id ?? null
                    ];
                }

                $taskData['agent_id'] = $agent->id;
            }

            // Find branch and company for agent
            $branchId = $agent->branch_id;
            $branch = Branch::find($branchId);

            if (!$branch) {
                $this->logger->error("AIR File Processing: Branch not found for agent {$agentName} in {$fileName} item {$index}.");
                
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
                    'reason' => 'Task saved with but branch not found',
                    'task_id' => $response['task']->id ?? null
                ];
            }

            $companyId = $branch->company_id;
            
            $taskData['enabled'] = false;
            $taskData['file_name'] = $fileName;
            $response = $this->saveTask($companyId, $taskData, $supplierId);

            if ($response['status'] === 'error') {
                if (isset($response['code']) && $response['code'] === 409) {
                    $this->logger->info("Task already exists for {$fileName} item {$index}. Skipping save.");
                    return [
                        'success' => false,
                        'index' => $index,
                        'reason' => 'Task already exists',
                        'error' => 'Duplicate task'
                    ];
                } else {
                    $this->logger->error("Failed to save task for {$fileName} item {$index}: " . $response['message']);
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
                'reason' => 'Task saved successfully',
                'task_id' => $response['task']->id ?? null
            ];
        } catch (\Throwable $e) {
            $this->logger->error("Exception during task processing for file {$fileName}, item {$index}: " . $e->getMessage());
            return [
                'success' => false,
                'index' => $index,
                'reason' => 'Exception occurred during processing',
                'error' => $e->getMessage()
            ];
        } finally {
            FileUpload::where('file_name', $fileName)->update([
                'status' => 'completed'
            ]);

            $this->logger->info("Marked file_upload as completed for {$fileName}");
        }
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
            $words = array_filter(explode(' ', trim($name)));
            if (count($words) > 1) {
            $agentQuery->orWhere(function($query) use ($words) {
                foreach ($words as $word) {
                $query->where('name', 'like', '%' . $word . '%');
                }
            });
            } else {
            $agentQuery->orWhere('name', 'like', '%' . $name . '%');
            }
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
        $this->logger->error("AIR File Processing: {$reason} for {$fileName}: {$errorMessage}");
        $this->logger->error("File processing error", [
            'file_name' => $fileName,
            'reason' => $reason,
            'error_message' => $errorMessage
        ]);
        $errorPath = storage_path("app/{$companyName}/{$supplierName}/files_error");
        $this->moveFileWithLogging($fileRealPath, $errorPath, $fileName, $reason);
    }

    protected function saveTask($companyId, $data, $supplierId)
    {
        // $existingTask = Task::where('reference', $data['reference'])
        //     ->where('company_id', $companyId)
        //     ->where('status', $data['status'])
        //     ->first();

        // if ($existingTask) {
        //     Log::info("Task with reference {$data['reference']} already exists. Skipping save.");

        //     return [
        //         'status' => 'error',
        //         'message' => 'Task already exists',
        //         'code' => 409,
        //     ];
        // }

        try {
            $data['company_id'] = $companyId;
            $data['supplier_id'] = $supplierId;
    /**
     * The name and signature of the console command.
     */

            $taskController = new TaskController();

            $request = new Request($data);
            $response = $taskController->store($request);

            if ($response->getStatusCode() !== 201) {
                $this->logger->error("Failed to save task: " . $response->getContent());
                throw new Exception("Failed to save task: " . $response->getContent());
            }
        } catch (Exception $e) {
            $this->logger->error("Failed to save task: " . $e->getMessage(), [
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
            $this->logger->info("Created directory: {$destinationDir}");
            $this->logger->info("Directory created", ['directory' => $destinationDir]);
        }

        $destinationPath = $destinationDir . '/' . $fileName;

        // Move the file
        File::move($sourcePath, $destinationPath);

        $msg = "Moved file {$fileName} to {$destinationDir}";
        if ($reason) {
            $msg .= " ({$reason})";
        }
        $this->logger->info($msg);
        $this->logger->info("File moved", [
            'file_name' => $fileName,
            'destination' => $destinationDir,
            'reason' => $reason
        ]);
        $this->info($msg);
    }
}
