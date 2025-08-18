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
use Carbon\Carbon;
use App\Schema\TaskFlightSchema;
use App\Schema\TaskSchema;
use App\Services\AirFileParser;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use App\Services\FileProcessingLogger;
use Illuminate\Http\JsonResponse;

class ProcessAirFiles extends Command
{
    protected $signature = 'app:process-files 
                            {--batch : Use batch processing (upload all files first, then process together)[default]}
                            {--single : Use single file processing (process files one by one)}
                            {--batch-size=10 : Maximum number of files to process in a single batch}
                            {--export-debug : Export parsed data to CSV/Excel files for debugging}
                            {--test-export : Test the export functionality with sample data}';
    protected $description = 'Scans the root air-files directory for new AIR files, processes them using existing logic, and moves them.';
    protected $aiManager;
    protected $companies;
    protected FileProcessingLogger $logger;

    public function __construct(AIManager $aiManager)
    {
        parent::__construct();
        $this->aiManager = $aiManager;
        $this->logger = new FileProcessingLogger('air_processing', [
            'command' => 'process-air-files',
            'process_id' => getmypid()
        ]);
    }

    public function handle()
    {
        // Test export functionality if requested
        if ($this->option('test-export')) {
            $this->testExportFunctionality();
            return 0;
        }

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

                // ✅ Skip files too new or too small (avoid processing during upload)
                $filesToProcess = array_filter(File::files($filePath), function ($file) {
                    // $minSize = 500; // bytes
                    // $minAge = 60;   // seconds

                    // $fileAge = time() - $file->getMTime();
                    // $fileSize = $file->getSize();

                    // if ($fileAge < $minAge) {
                    //     $this->logger->info("Skipping file too new: {$file->getFilename()} ({$fileAge}s old)");
                    //     return false;
                    // }

                    // if ($fileSize < $minSize) {
                    //     $this->logger->info("Skipping file too small: {$file->getFilename()} ({$fileSize} bytes)");
                    //     return false;
                    // }

                    return true;
                });

                // $filesToProcess = File::files($filePath);

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
                        // dump('Processing file: ' . $file->getFilename());
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
     * Process multiple AIR files in batch - uses conditional logic to choose parser
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

        // Determine processing method based on supplier and file types
        $supplier = Supplier::find($supplierId);
        $useAirFileParser = $this->shouldUseAirFileParser($supplier, $files);
        
        $processingMethod = $useAirFileParser ? 'AirFileParser' : 'AI-based processing';
        $this->info("Using {$processingMethod} for batch processing");
        $this->logger->info("Batch processing method selected", [
            'company' => $companyName,
            'supplier' => $supplierName,
            'method' => $processingMethod,
            'supplier_name' => $supplier->name ?? 'unknown'
        ]);

        if ($useAirFileParser) {
            $this->processBatchFilesWithAirParser($companyId, $companyName, $supplierName, $supplierId, $files);
        } else {
            $this->processBatchFilesWithAI($companyId, $companyName, $supplierName, $supplierId, $files);
        }
    }

    /**
     * Process multiple AIR files in batch using AirFileParser and export all data to single Excel file
     */
    protected function processBatchFilesWithAirParser($companyId, $companyName, $supplierName, $supplierId, array $files)
    {
        // Step 1: Process all files using AirFileParser and collect data
        $allParsedData = [];
        $processedFiles = [];
        $errorFiles = [];
        $savedTasks = [];
        $failedTasks = [];

        foreach ($files as $file) {
            $fileRealPath = $file->getRealPath();
            $fileName = $file->getFilename();
            
            $this->info("Processing file with AirFileParser: {$fileName}");
            
            try {
                // Parse the file using AirFileParser
                $parser = new AirFileParser($fileRealPath);
                $tasksData = $parser->parseTaskSchema(); // Now returns array of tasks

                $this->info("Found " . count($tasksData) . " passenger(s) in file: {$fileName}");

                // Handle multiple passengers
                foreach ($tasksData as $passengerIndex => $taskData) {
                    $passengerNum = $passengerIndex + 1;
                    $this->info("Processing passenger {$passengerNum}/{" . count($tasksData) . "}: {$taskData['client_name']}");
                    
                    // Normalize the data
                    $normalizedTask = TaskSchema::normalize($taskData);
                    if (isset($normalizedTask['task_flight_details']) && is_array($normalizedTask['task_flight_details'])) {
                        $normalizedTask['task_flight_details'] = TaskFlightSchema::normalize($normalizedTask['task_flight_details']);
                    }

                    // Add to collection for export (include file name with the data)
                    $taskDataWithFileName = $taskData;
                    $taskDataWithFileName['_source_file_name'] = $fileName;
                    $allParsedData[] = $taskDataWithFileName;

                    // Process and save the task using the same logic as single file processing
                    try {
                        $taskResult = $this->processTaskData($companyId, $companyName, $supplierName, $supplierId, $fileName, $taskData, $passengerIndex);
                        
                        if ($taskResult['success']) {
                            $savedTasks[] = [
                                'file_name' => $fileName,
                                'passenger_index' => $passengerIndex,
                                'client_name' => $taskData['client_name'],
                                'task_id' => $taskResult['task_id'] ?? null,
                                'reason' => $taskResult['reason']
                            ];
                            $this->info("✓ Saved task for passenger {$passengerNum}: {$taskData['client_name']}");
                        } else {
                            $failedTasks[] = [
                                'file_name' => $fileName,
                                'passenger_index' => $passengerIndex,
                                'client_name' => $taskData['client_name'],
                                'error' => $taskResult['error'] ?? $taskResult['reason'],
                                'reason' => $taskResult['reason']
                            ];
                            $this->warn("✗ Failed to save task for passenger {$passengerNum}: {$taskData['client_name']} - {$taskResult['reason']}");
                        }
                    } catch (\Exception $e) {
                        $failedTasks[] = [
                            'file_name' => $fileName,
                            'passenger_index' => $passengerIndex,
                            'client_name' => $taskData['client_name'],
                            'error' => $e->getMessage(),
                            'reason' => 'Exception during task processing'
                        ];
                        $this->error("✗ Exception saving task for passenger {$passengerNum}: {$e->getMessage()}");
                        $this->logger->error("Exception during batch task processing", [
                            'file_name' => $fileName,
                            'passenger_index' => $passengerIndex,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
                
                $processedFiles[] = $fileName;
                
                $this->info("✓ Successfully parsed: {$fileName} ({" . count($tasksData) . "} passenger(s))");
                
            } catch (\Exception $e) {
                $this->error("✗ Failed to parse {$fileName}: " . $e->getMessage());
                $this->logger->error("AirFileParser failed for {$fileName}", [
                    'error' => $e->getMessage(),
                    'file_path' => $fileRealPath
                ]);
                
                $errorFiles[] = [
                    'file_name' => $fileName,
                    'file_path' => $fileRealPath,
                    'error' => $e->getMessage()
                ];
            }
        }

        // Step 2: Export all data to single Excel file (including task save results) - only if export-debug is enabled
        if (!empty($allParsedData) && $this->option('export-debug')) {
            $totalRecords = count($allParsedData);
            $this->info("Exporting {$totalRecords} parsed records to single Excel file...");
            $this->info("Task save summary: {" . count($savedTasks) . "} saved, {" . count($failedTasks) . "} failed");
            
            try {
                $batchExcelPath = $this->exportBatchDataToSingleExcel($allParsedData, $processedFiles, $companyName, $supplierName);
                
                if ($batchExcelPath) {
                    $this->info("✓ Batch data exported to: {$batchExcelPath}");
                    
                    // Also export task save results summary
                    $this->exportTaskSaveResults($savedTasks, $failedTasks, $companyName, $supplierName);
                } else {
                    $this->error("✗ Failed to export batch data to Excel");
                }
                
            } catch (\Exception $e) {
                $this->error("✗ Failed to export batch data: " . $e->getMessage());
                $this->logger->error("Batch export failed", [
                    'error' => $e->getMessage(),
                    'company' => $companyName,
                    'supplier' => $supplierName
                ]);
            }
        } elseif (!empty($allParsedData) && !$this->option('export-debug')) {
            $totalRecords = count($allParsedData);
            $this->info("Processed {$totalRecords} parsed records (use --export-debug to export to Excel)");
            $this->info("Task save summary: {" . count($savedTasks) . "} saved, {" . count($failedTasks) . "} failed");
        }

        // Step 3: Move files based on processing results
        foreach ($files as $file) {
            $fileName = $file->getFilename();
            $fileRealPath = $file->getRealPath();
            
            if (in_array($fileName, $processedFiles)) {
                // Check if all tasks for this file were saved successfully
                $fileTaskResults = array_filter($savedTasks, fn($task) => $task['file_name'] === $fileName);
                $fileTaskFailures = array_filter($failedTasks, fn($task) => $task['file_name'] === $fileName);
                
                if (count($fileTaskFailures) === 0) {
                    // All tasks saved successfully - move to processed directory
                    $successPath = storage_path("app/{$companyName}/{$supplierName}/files_processed");
                    $this->moveFileWithLogging($fileRealPath, $successPath, $fileName, 
                        "Successfully parsed and saved " . count($fileTaskResults) . " tasks");
                } else {
                    // Some tasks failed - move to error directory but log partial success
                    $errorPath = storage_path("app/{$companyName}/{$supplierName}/files_error");
                    $successCount = count($fileTaskResults);
                    $failureCount = count($fileTaskFailures);
                    $this->moveFileWithLogging($fileRealPath, $errorPath, $fileName, 
                        "Partial success: {$successCount} tasks saved, {$failureCount} tasks failed");
                }
            } else {
                // Move to error directory
                $errorPath = storage_path("app/{$companyName}/{$supplierName}/files_error");
                $errorInfo = collect($errorFiles)->firstWhere('file_name', $fileName);
                $reason = $errorInfo ? $errorInfo['error'] : 'Unknown parsing error';
                $this->moveFileWithLogging($fileRealPath, $errorPath, $fileName, "Parsing failed: {$reason}");
            }
        }

        // Log comprehensive summary
        $successCount = count($processedFiles);
        $errorCount = count($errorFiles);
        $totalCount = count($files);
        $totalTasksSaved = count($savedTasks);
        $totalTasksFailed = count($failedTasks);
        $totalTasks = $totalTasksSaved + $totalTasksFailed;
        
        $this->info("Batch processing completed:");
        $this->info("  Files: {$successCount}/{$totalCount} files parsed successfully, {$errorCount} files failed");
        $this->info("  Tasks: {$totalTasksSaved}/{$totalTasks} tasks saved successfully, {$totalTasksFailed} tasks failed");
        
        $this->logger->info("Batch processing summary", [
            'company' => $companyName,
            'supplier' => $supplierName,
            'total_files' => $totalCount,
            'successful_files' => $successCount,
            'failed_files' => $errorCount,
            'processed_files' => $processedFiles,
            'error_files' => array_column($errorFiles, 'file_name'),
            'total_tasks' => $totalTasks,
            'saved_tasks' => $totalTasksSaved,
            'failed_tasks' => $totalTasksFailed,
            'saved_task_details' => $savedTasks,
            'failed_task_details' => $failedTasks
        ]);
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
     * Process a single file using conditional logic to choose parser
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

        // Determine processing method based on supplier and file type
        $supplier = Supplier::find($supplierId);
        $useAirFileParser = $this->shouldUseAirFileParser($supplier, [$file]);
        
        $processingMethod = $useAirFileParser ? 'AirFileParser' : 'AI-based processing';
        $this->info("Using {$processingMethod} for file: {$fileName}");
        $this->logger->info("Single file processing method selected", [
            'file_name' => $fileName,
            'company' => $companyName,
            'supplier' => $supplierName,
            'method' => $processingMethod,
            'supplier_name' => $supplier->name ?? 'unknown'
        ]);

        if ($useAirFileParser) {
            $this->processSingleFileWithAirParser($companyId, $companyName, $supplierName, $supplierId, $file);
        } else {
            $this->processSingleFileWithAI($companyId, $companyName, $supplierName, $supplierId, $file);
        }
    }

    /**
     * Process a single AIR file using AirFileParser with proper error handling
     */
    protected function processSingleFileWithAirParser($companyId, $companyName, $supplierName, $supplierId, $file)
    {
        $fileRealPath = $file->getRealPath();
        $fileName = $file->getFilename();

        try {
            $parser = new AirFileParser($fileRealPath);
            $tasksData = $parser->parseTaskSchema(); // Now returns array of tasks

            $this->info("Found " . count($tasksData) . " passenger(s) in file: {$fileName}");

            $savedTasks = [];
            $failedTasks = [];

            // Process each passenger's task
            foreach ($tasksData as $index => $taskData) {
                $passengerIndex = $index + 1;
                $this->info("Processing passenger {$passengerIndex}/{" . count($tasksData) . "}: {$taskData['client_name']}");
                
                $normalizedTask = TaskSchema::normalize($taskData);

                if (isset($normalizedTask['task_flight_details']) && is_array($normalizedTask['task_flight_details'])) {
                    $normalizedTask['task_flight_details'] = TaskFlightSchema::normalize($normalizedTask['task_flight_details']);
                }

                // Export parsed data to CSV and Excel for debugging (if enabled)
                if ($this->option('export-debug')) {
                    $passengerFileName = "passenger_{$passengerIndex}_{$fileName}";
                    $this->exportParsedDataToCsv($taskData, $passengerFileName, $companyName, $supplierName);
                    $this->exportParsedDataToExcel($taskData, $passengerFileName, $companyName, $supplierName);
                    
                    // Also export normalized data if different
                    if ($normalizedTask !== $taskData) {
                        $normalizedFileName = 'normalized_' . $passengerFileName;
                        $this->exportParsedDataToCsv($normalizedTask, $normalizedFileName, $companyName, $supplierName);
                        $this->exportParsedDataToExcel($normalizedTask, $normalizedFileName, $companyName, $supplierName);
                    }
                }

                // Process and save the task
                try {
                    $taskResult = $this->processTaskData($companyId, $companyName, $supplierName, $supplierId, $fileName, $taskData, $index);
                    
                    if ($taskResult['success']) {
                        $savedTasks[] = $taskResult;
                        $this->info("✓ Saved task for passenger {$passengerIndex}: {$taskData['client_name']}");
                    } else {
                        $failedTasks[] = $taskResult;
                        $this->warn("✗ Failed to save task for passenger {$passengerIndex}: {$taskData['client_name']} - {$taskResult['reason']}");
                    }
                } catch (Exception $e) {
                    $failedTasks[] = [
                        'success' => false,
                        'index' => $index,
                        'error' => $e->getMessage(),
                        'reason' => 'Exception during task data processing'
                    ];
                    $this->error("✗ Exception processing passenger {$passengerIndex}: " . $e->getMessage());
                    $this->logger->error("Exception processing passenger", [
                        'file_name' => $fileName,
                        'passenger_index' => $passengerIndex,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Move file based on results
            $successCount = count($savedTasks);
            $totalCount = count($tasksData);
            
            if (count($failedTasks) === 0) {
                // All tasks saved successfully
                $successPath = storage_path("app/{$companyName}/{$supplierName}/files_processed");
                $this->moveFileWithLogging($fileRealPath, $successPath, $fileName, 
                    "Successfully processed all {$totalCount} passengers");
            } else {
                // Some tasks failed
                $errorPath = storage_path("app/{$companyName}/{$supplierName}/files_error");
                $failureCount = count($failedTasks);
                $this->moveFileWithLogging($fileRealPath, $errorPath, $fileName, 
                    "Partial success: {$successCount}/{$totalCount} passengers processed successfully");
            }

        } catch (Exception $e) {
            $this->handleFileError($companyName, $supplierName, $fileRealPath, $fileName, 
                'AirFileParser processing error', $e->getMessage());
        }
    }

    /**
     * Process a single file using AI-based extraction (legacy method)
     */
    protected function processSingleFileWithAI($companyId, $companyName, $supplierName, $supplierId, $file)
    {
        $fileRealPath = $file->getRealPath();
        $fileName = $file->getFilename();

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
            $agent = null;
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

            if ( $taskData['type'] == 'hotel' && isset($taskData['cancellation_deadline']) && !empty($taskData['cancellation_deadline'])) {
                $this->logger->info("Cancellation deadline found for item {$index} in file {$fileName}: " . $taskData['cancellation_deadline']);

                $cancelDeadline  = Carbon::parse($taskData['cancellation_deadline']);
                $createdAt = Carbon::parse($taskData['created_at'] ?? now());

                if($createdAt->greaterThanOrEqualTo($cancelDeadline)) {
                    $this->logger->info("Cancellation deadline has passed for item {$index} in file {$fileName}. Task will be marked as issued");
                    $taskData['status'] = 'issued';
                } else {
                    $this->logger->info("Cancellation deadline is still valid for item {$index} in file {$fileName}. Task will not be marked as issued");
                    $taskData['status'] = 'confirmed';
                }
            } else {
                $this->logger->info("No cancellation deadline found for item {$index} in file {$fileName}. Task status will not be modified");
            } 

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
                            'error' => $response['error']
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
                            'error' => $response['error']
                        ];
                    }

                    return [
                        'success' => true,
                        'index' => $index,
                        'reason' => 'Task saved with but no flight details found',
                        'task_id' => $response['task']->id ?? null
                    ];
                }

                $flightDetailsArray = $flightDetails->map(function ($flightDetail) {
                    $attributes = $flightDetail->getAttributes();
                    
                    // Ensure datetime fields keep their full format
                    foreach (['departure_time', 'arrival_time', 'created_at', 'updated_at'] as $dateField) {
                        if (isset($attributes[$dateField]) && $flightDetail->$dateField) {
                            $attributes[$dateField] = $flightDetail->$dateField->format('Y-m-d H:i:s');
                        }
                    }
                    
                    return $attributes;
                })->toArray();
                
                $this->logger->info("Flight Details for Task ID {$taskData['original_task_id']}: ", $flightDetailsArray);
                $taskData['task_flight_details'] = $flightDetailsArray;

                if($agentAmadeusId !== null || $agentName !== null || $agentEmail !== null){
                    $agent = $this->findAgent($agentAmadeusId, $agentName, $agentEmail, $companyId);
                }

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
                            'error' => $response['error']
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
                            'error' => $response['error']
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
                $this->logger->info("Task is " . $taskData['status'] . ". No original task processing needed for item {$index} in file {$fileName}.");

                if($agentAmadeusId !== null || $agentName !== null || $agentEmail !== null){
                    $agent = $this->findAgent($agentAmadeusId, $agentName, $agentEmail, $companyId);
                }

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
                            'error' => $response['error']
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
                        'error' => $response['error']
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
                        'error' => $response['error']
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
    protected function findAgent($amadeusId, $name, $email, $companyId) : ?Agent
    {
        $agentQuery = Agent::query();
        $branchesId = Branch::where('company_id', $companyId)->pluck('id');

        // Then add the OR conditions for agent identification within a grouped where clause
        $agentQuery->where(function($query) use ($amadeusId, $name, $email) {
            $hasConditions = false;
            
            if ($amadeusId) {
                $query->where('amadeus_id', 'like', $amadeusId);
                $hasConditions = true;
            }
            
            if ($name) {
                $words = array_filter(explode(' ', trim($name)));
                if (count($words) > 1) {
                    if ($hasConditions) {
                        $query->orWhere(function($subQuery) use ($words) {
                            foreach ($words as $word) {
                                $subQuery->where('name', 'like', '%' . $word . '%');
                            }
                        });
                    } else {
                        $query->where(function($subQuery) use ($words) {
                            foreach ($words as $word) {
                                $subQuery->where('name', 'like', '%' . $word . '%');
                            }
                        });
                        $hasConditions = true;
                    }
                } else {
                    if ($hasConditions) {
                        $query->orWhere('name', 'like', '%' . $name . '%');
                    } else {
                        $query->where('name', 'like', '%' . $name . '%');
                        $hasConditions = true;
                    }
                }
            }
            
            if ($email) {
                if ($hasConditions) {
                    $query->orWhere('email', 'like', $email);
                } else {
                    $query->where('email', 'like', $email);
                }
            }
        });

        if ($agentQuery->first()) {
            return $agentQuery->whereIn('branch_id', $branchesId)
                ->where('company_id', $companyId)
                ->first();
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

    protected function saveTask($companyId, $data, $supplierId) : array
    {

        try {
            $data['company_id'] = $companyId;
            $data['supplier_id'] = $supplierId;

            $taskController = new TaskController();
            $request = new Request($data);
            $response = $taskController->store($request);

            if(!$response instanceof JsonResponse) {
                $this->logger->error("Unexpected response type from TaskController::store", [
                    'expected' => JsonResponse::class,
                    'actual' => get_class($response)
                ]);
                $this->error("Unexpected response from TaskController::store");

                throw new Exception("Unexpected response from TaskController", 500);
            }

            $response = $response->getData(true);

            if (isset($response['status']) && $response['status'] === 'error') {

                $this->logger->error("Failed to save task", [
                    'error_message' => $response['message'] ?? 'Unknown error',
                    'task_data' => $data
                ]);
                $this->error("Failed to save task: " . ($response['message'] ?? 'Unknown error'));

                throw new Exception($response['message'] ?? 'Unknown error', $response['code'] ?? 500);
            }

            $this->info("Task saved successfully");

        } catch (Exception $e) {
            // Single error log entry with comprehensive context
            $this->logger->error("Task save exception occurred", [
                'error_message' => $e->getMessage(),
                'task_data' => $data,
                'trace' => $e->getTraceAsString(),
                'exception_class' => get_class($e)
            ]);
            
            return [
                'status' => 'error',
                'message' => 'Failed to save task',
                'error' => $e->getMessage(),
            ];
        }

        $task = $response['data'];

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

    /**
     * Export parsed data to CSV/Excel file for debugging
     * Flattens nested arrays and includes the file name
     */
    protected function exportParsedDataToCsv($data, $fileName, $companyName, $supplierName)
    {
        try {
            // Create export directory if it doesn't exist
            $exportDir = storage_path("app/{$companyName}/{$supplierName}/debug_exports");
            if (!File::isDirectory($exportDir)) {
                File::makeDirectory($exportDir, 0755, true, true);
            }

            // Generate CSV filename with timestamp
            $timestamp = now()->format('Y-m-d_H-i-s');
            $csvFileName = "parsed_data_{$timestamp}_{$fileName}.csv";
            $csvFilePath = $exportDir . '/' . $csvFileName;

            // Flatten the data structure
            $flattenedData = $this->flattenDataStructure($data, $fileName);

            // Write to CSV
            $handle = fopen($csvFilePath, 'w');
            
            if ($handle === false) {
                throw new \Exception("Could not create CSV file: {$csvFilePath}");
            }

            // Write header row
            if (!empty($flattenedData)) {
                $headers = array_keys($flattenedData[0]);
                fputcsv($handle, $headers);

                // Write data rows
                foreach ($flattenedData as $row) {
                    fputcsv($handle, $row);
                }
            }

            fclose($handle);

            $this->info("Exported parsed data to: {$csvFilePath}");
            $this->logger->info("Exported parsed data to CSV", [
                'file_name' => $fileName,
                'csv_path' => $csvFilePath,
                'company' => $companyName,
                'supplier' => $supplierName
            ]);

            return $csvFilePath;

        } catch (\Exception $e) {
            $this->error("Failed to export parsed data to CSV: " . $e->getMessage());
            $this->logger->error("Failed to export parsed data to CSV", [
                'file_name' => $fileName,
                'error' => $e->getMessage(),
                'company' => $companyName,
                'supplier' => $supplierName
            ]);
            return null;
        }
    }

    /**
     * Export parsed data to Excel file for debugging using Laravel Excel
     * Flattens nested arrays and includes the file name
     */
    protected function exportParsedDataToExcel($data, $fileName, $companyName, $supplierName)
    {
        try {
            // Create export directory if it doesn't exist
            $exportDir = storage_path("app/{$companyName}/{$supplierName}/debug_exports");
            if (!File::isDirectory($exportDir)) {
                File::makeDirectory($exportDir, 0755, true, true);
            }

            // Generate Excel filename with timestamp
            $timestamp = now()->format('Y-m-d_H-i-s');
            $excelFileName = "parsed_data_{$timestamp}_{$fileName}.xlsx";
            $excelFilePath = $exportDir . '/' . $excelFileName;

            // Flatten the data structure
            $flattenedData = $this->flattenDataStructure($data, $fileName);

            // Create a simple Excel export class
            $export = new class($flattenedData) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings {
                private $data;

                public function __construct($data)
                {
                    $this->data = $data;
                }

                public function array(): array
                {
                    return array_values($this->data);
                }

                public function headings(): array
                {
                    return !empty($this->data) ? array_keys($this->data[0]) : [];
                }
            };

            // Export to Excel
            \Maatwebsite\Excel\Facades\Excel::store($export, "/{$companyName}/{$supplierName}/debug_exports/{$excelFileName}");

            $this->info("Exported parsed data to Excel: {$excelFilePath}");
            $this->logger->info("Exported parsed data to Excel", [
                'file_name' => $fileName,
                'excel_path' => $excelFilePath,
                'company' => $companyName,
                'supplier' => $supplierName
            ]);

            return $excelFilePath;

        } catch (\Exception $e) {
            $this->error("Failed to export parsed data to Excel: " . $e->getMessage());
            $this->logger->error("Failed to export parsed data to Excel", [
                'file_name' => $fileName,
                'error' => $e->getMessage(),
                'company' => $companyName,
                'supplier' => $supplierName
            ]);
            return null;
        }
    }

    /**
     * Flatten nested data structure for CSV export
     * Converts nested arrays to flattened key-value pairs
     */
    protected function flattenDataStructure($data, $fileName)
    {
        if (!is_array($data)) {
            return [];
        }

        $flattened = [];
        
        // Handle array of data items vs single data item
        $dataItems = [];
        if (isset($data[0]) && is_array($data[0])) {
            // Array of items
            $dataItems = $data;
        } else {
            // Single item
            $dataItems = [$data];
        }

        foreach ($dataItems as $index => $item) {
            $flatItem = [];
            
            // Add file name and item index
            $flatItem['source_file'] = $fileName;
            $flatItem['item_index'] = $index;
            
            // Flatten the main data
            $this->flattenArray($item, $flatItem, '');
            
            $flattened[] = $flatItem;
        }

        return $flattened;
    }

    /**
     * Recursively flatten an array with prefixed keys
     */
    protected function flattenArray($array, &$result, $prefix = '')
    {
        foreach ($array as $key => $value) {
            $newKey = $prefix ? $prefix . '_' . $key : $key;
            
            if (is_array($value)) {
                // Check if it's an empty array
                if (empty($value)) {
                    $result[$newKey] = '';
                }
                // If it's a numeric indexed array, handle each element
                else if (array_keys($value) === range(0, count($value) - 1)) {
                    // Check if elements are simple values or arrays
                    $stringElements = [];
                    foreach ($value as $element) {
                        if (is_array($element)) {
                            // Convert sub-array to JSON string for display
                            $stringElements[] = json_encode($element);
                        } else {
                            $stringElements[] = $this->convertValueToString($element);
                        }
                    }
                    $result[$newKey] = implode(' | ', $stringElements);
                } else {
                    // Recursively flatten associative arrays
                    $this->flattenArray($value, $result, $newKey);
                }
            } else {
                // Convert values to strings for CSV compatibility
                $result[$newKey] = $this->convertValueToString($value);
            }
        }
    }

    /**
     * Convert a value to string for CSV export
     */
    protected function convertValueToString($value)
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        } else if (is_null($value)) {
            return '';
        } else if (is_numeric($value)) {
            return (string) $value;
        } else if (is_string($value)) {
            return $value;
        } else {
            // For objects or other types, convert to JSON
            return json_encode($value);
        }
    }

    /**
     * Test the export functionality with sample data
     */
    protected function testExportFunctionality()
    {
        $this->info("Testing export functionality with sample data...");
        
        // Create sample parsed data structure similar to what AirFileParser would produce
        $sampleData = [
            'id' => '12345',
            'pnr' => 'ABC123',
            'price' => 1250.00,
            'currency' => 'USD',
            'base_fare' => 1000.00,
            'total_fare' => 1250.00,
            'taxes' => [
                ['code' => 'YQ', 'amount' => 150.00],
                ['code' => 'US', 'amount' => 100.00]
            ],
            'task_flight_details' => [
                [
                    'from' => 'JFK',
                    'to' => 'LAX',
                    'date' => '2024-01-15',
                    'flight_number' => 'AA123',
                    'airline' => 'American Airlines'
                ],
                [
                    'from' => 'LAX',
                    'to' => 'JFK',
                    'date' => '2024-01-22',
                    'flight_number' => 'AA456',
                    'airline' => 'American Airlines'
                ]
            ],
            'passenger_info' => [
                'name' => 'John Doe',
                'email' => 'john.doe@example.com',
                'phone' => '+1234567890'
            ],
            'booking_status' => 'confirmed',
            'is_refund' => false,
            'exchange_rate' => null
        ];

        // Test with a sample company and supplier
        $testCompany = 'TestCompany';
        $testSupplier = 'TestSupplier';
        $testFileName = 'sample_air_file.txt';

        // Export the sample data
        $csvPath = $this->exportParsedDataToCsv($sampleData, $testFileName, $testCompany, $testSupplier);
        $excelPath = $this->exportParsedDataToExcel($sampleData, $testFileName, $testCompany, $testSupplier);

        if ($csvPath && $excelPath) {
            $this->info("✓ Export test completed successfully!");
            $this->info("CSV file: {$csvPath}");
            $this->info("Excel file: {$excelPath}");
        } else {
            $this->error("✗ Export test failed!");
        }
    }

    /**
     * Export all batch parsed data to a single Excel file
     * Combines data from multiple files into one Excel file with separate sheets or all in one sheet
     */
    protected function exportBatchDataToSingleExcel($allParsedData, $fileNames, $companyName, $supplierName)
    {
        try {
            // Create export directory if it doesn't exist
            $exportDir = storage_path("app/{$companyName}/{$supplierName}/debug_exports");
            if (!File::isDirectory($exportDir)) {
                File::makeDirectory($exportDir, 0755, true, true);
            }

            // Generate Excel filename with timestamp
            $timestamp = now()->format('Y-m-d_H-i-s');
            $excelFileName = "batch_parsed_data_{$timestamp}.xlsx";
            $excelFilePath = $exportDir . '/' . $excelFileName;

            // Flatten all data and include file names
            $allFlattenedData = [];
            
            foreach ($allParsedData as $index => $data) {
                // Extract file name from the data itself (embedded during processing)
                $fileName = $data['_source_file_name'] ?? $fileNames[$index] ?? "unknown_file_{$index}";
                
                // Remove the temporary field before flattening
                if (isset($data['_source_file_name'])) {
                    unset($data['_source_file_name']);
                }
                
                $flattenedData = $this->flattenDataStructure($data, $fileName);
                
                // Since flattenDataStructure returns an array of items, merge them all
                foreach ($flattenedData as $item) {
                    $allFlattenedData[] = $item;
                }
            }

            if (empty($allFlattenedData)) {
                throw new \Exception("No data to export");
            }

            // Create Excel export class for all data
            $export = new class($allFlattenedData) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings {
                private $data;

                public function __construct($data)
                {
                    $this->data = $data;
                }

                public function array(): array
                {
                    return array_values($this->data);
                }

                public function headings(): array
                {
                    return !empty($this->data) ? array_keys($this->data[0]) : [];
                }
            };

            // Export to Excel
            \Maatwebsite\Excel\Facades\Excel::store($export, "/{$companyName}/{$supplierName}/debug_exports/{$excelFileName}");

            $this->info("Exported batch data to Excel: {$excelFilePath}");
            $this->info("Total records exported: " . count($allFlattenedData));
            $this->info("Files included: " . implode(', ', $fileNames));
            
            $this->logger->info("Exported batch data to Excel", [
                'excel_path' => $excelFilePath,
                'total_records' => count($allFlattenedData),
                'files_included' => $fileNames,
                'company' => $companyName,
                'supplier' => $supplierName
            ]);

            return $excelFilePath;

        } catch (\Exception $e) {
            $this->error("Failed to export batch data to Excel: " . $e->getMessage());
            $this->logger->error("Failed to export batch data to Excel", [
                'error' => $e->getMessage(),
                'company' => $companyName,
                'supplier' => $supplierName,
                'files_count' => count($fileNames)
            ]);
            return null;
        }
    }

    /**
     * Export task save results summary to Excel file
     */
    protected function exportTaskSaveResults($savedTasks, $failedTasks, $companyName, $supplierName)
    {
        try {
            // Create export directory if it doesn't exist
            $exportDir = storage_path("app/{$companyName}/{$supplierName}/debug_exports");
            if (!File::isDirectory($exportDir)) {
                File::makeDirectory($exportDir, 0755, true, true);
            }

            // Generate Excel filename with timestamp
            $timestamp = now()->format('Y-m-d_H-i-s');
            $summaryFileName = "task_save_summary_{$timestamp}.xlsx";

            // Prepare data for export
            $summaryData = [];
            
            // Add saved tasks
            foreach ($savedTasks as $task) {
                $summaryData[] = [
                    'file_name' => $task['file_name'],
                    'passenger_index' => $task['passenger_index'],
                    'client_name' => $task['client_name'],
                    'status' => 'SUCCESS',
                    'task_id' => $task['task_id'],
                    'reason' => $task['reason'],
                    'error' => ''
                ];
            }
            
            // Add failed tasks
            foreach ($failedTasks as $task) {
                $summaryData[] = [
                    'file_name' => $task['file_name'],
                    'passenger_index' => $task['passenger_index'],
                    'client_name' => $task['client_name'],
                    'status' => 'FAILED',
                    'task_id' => '',
                    'reason' => $task['reason'],
                    'error' => $task['error']
                ];
            }

            if (!empty($summaryData)) {
                // Create Excel export class for task save results
                $export = new class($summaryData) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings {
                    private $data;

                    public function __construct($data)
                    {
                        $this->data = $data;
                    }

                    public function array(): array
                    {
                        return array_values($this->data);
                    }

                    public function headings(): array
                    {
                        return ['File Name', 'Passenger Index', 'Client Name', 'Status', 'Task ID', 'Reason', 'Error'];
                    }
                };

                // Export to Excel
                \Maatwebsite\Excel\Facades\Excel::store($export, "/{$companyName}/{$supplierName}/debug_exports/{$summaryFileName}");

                $summaryPath = $exportDir . '/' . $summaryFileName;
                $this->info("✓ Task save summary exported to: {$summaryPath}");
                
                $this->logger->info("Exported task save summary", [
                    'summary_path' => $summaryPath,
                    'total_saved' => count($savedTasks),
                    'total_failed' => count($failedTasks),
                    'company' => $companyName,
                    'supplier' => $supplierName
                ]);

                return $summaryPath;
            }

        } catch (\Exception $e) {
            $this->error("Failed to export task save summary: " . $e->getMessage());
            $this->logger->error("Failed to export task save summary", [
                'error' => $e->getMessage(),
                'company' => $companyName,
                'supplier' => $supplierName
            ]);
        }

        return null;
    }

    /**
     * Determine whether to use AirFileParser or AI-based processing
     */
    protected function shouldUseAirFileParser($supplier, array $files): bool
    {
        // Use AirFileParser if:
        // 1. Supplier is Amadeus (name matches exactly)
        // 2. At least one file has .air extension
        
        if (!$supplier || strcasecmp($supplier->name, 'Amadeus') !== 0) {
            return false;
        }
        
        // Check if any file has .air extension
        foreach ($files as $file) {
            $extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
            if ($extension === 'air') {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Process multiple files using AI-based extraction (legacy method)
     */
    protected function processBatchFilesWithAI($companyId, $companyName, $supplierName, $supplierId, array $files)
    {
        $this->info("Using AI-based processing for batch of " . count($files) . " files");
        
        // Process each file individually using AI extraction
        foreach ($files as $file) {
            try {
                $this->processSingleFileWithAI($companyId, $companyName, $supplierName, $supplierId, $file);
            } catch (Exception $e) {
                $this->error("Failed to process file {$file->getFilename()} with AI: " . $e->getMessage());
                $this->logger->error("AI batch processing failed for file", [
                    'file_name' => $file->getFilename(),
                    'company' => $companyName,
                    'supplier' => $supplierName,
                    'error' => $e->getMessage()
                ]);
                
                // Move failed file to error directory
                $this->handleFileError($companyName, $supplierName, $file->getRealPath(), $file->getFilename(), 
                    'AI processing error in batch', $e->getMessage());
            }
        }
    }
}
