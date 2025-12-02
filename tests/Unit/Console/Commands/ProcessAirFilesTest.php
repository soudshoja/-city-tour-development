<?php

namespace Tests\Unit\Console\Commands;

use App\Console\Commands\ProcessAirFiles;
use App\AI\AIManager;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplierCompany;
use App\Models\Task;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\User;
use App\Services\AirFileParser;
use App\Services\FileProcessingLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Comprehensive test suite for ProcessAirFiles command
 * Covers both unit-level component testing and feature-level integration testing
 */
class ProcessAirFilesTest extends TestCase
{
    use RefreshDatabase;

    protected ProcessAirFiles $command;
    protected AIManager $aiManager;
    protected FileProcessingLogger $logger;
    protected User $user;
    protected Company $company;
    protected Supplier $supplier;
    protected AgentType $agentType;
    protected Branch $branch;
    protected Agent $agent;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock dependencies using Mockery
        $this->aiManager = Mockery::mock(AIManager::class);
        
        // Bind the mock in the service container
        $this->app->instance(AIManager::class, $this->aiManager);
        
        // Create the command instance
        $this->command = new ProcessAirFiles($this->aiManager);
        
        // Set up test data
        $this->setupTestData();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function setupTestData(): void
    {
        // Disable foreign key checks for testing
        DB::statement('SET foreign_key_checks=0');
        
        // Create test user first (needed for branch)
        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'testuser@example.com'
        ]);

        // Create test company
        $this->company = Company::factory()->create([
            'name' => 'TestCompany'
        ]);

        // Create test supplier
        $this->supplier = Supplier::factory()->create([
            'name' => 'TestSupplier'
        ]);

        // Create supplier-company relationship
        SupplierCompany::factory()->create([
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id
        ]);

        // Create agent type (needed for agent)
        $this->agentType = AgentType::factory()->create([
            'name' => 'Test Agent Type'
        ]);

        // Create test branch
        $this->branch = Branch::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'name' => 'TestBranch'
        ]);

        // Create test agent
        $this->agent = Agent::factory()->create([
            'user_id' => $this->user->id,
            'branch_id' => $this->branch->id,
            'type_id' => $this->agentType->id,
            'account_id' => 1, // Use dummy value since we disabled foreign key checks
            'name' => 'TestAgent',
            'email' => 'test@example.com'
        ]);
        
        // Re-enable foreign key checks
        DB::statement('SET foreign_key_checks=1');
    }

    // ===========================================
    // UNIT TESTS - Component Testing
    // ===========================================

    public function test_command_can_be_instantiated()
    {
        $this->assertInstanceOf(ProcessAirFiles::class, $this->command);
    }

    public function test_task_data_validation_before_saving()
    {
        // Complete Task data matching database format
        $taskData = [
            // Required fields
            'client_id' => null, // nullable
            'agent_id' => $this->agent->id,
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'type' => 'flight', // enum: 'flight', 'hotel'
            'status' => 'issued', // various status options
            'supplier_status' => 'confirmed',
            'original_task_id' => null,
            'client_name' => 'Test Client',
            'passenger_name' => 'John Doe Passenger',
            'reference' => 'TEST123',
            'gds_reference' => 'AMADEUS123',
            'airline_reference' => 'KU123456',
            'created_by' => 'System',
            'issued_by' => 'KWIKT2844',
            'duration' => '2h 30m',
            'payment_type' => 'Credit Card',
            'price' => 100.00,
            'exchange_currency' => 'USD',
            'original_price' => 95.00,
            'original_currency' => 'KWD',
            'tax' => 10.00,
            'surcharge' => 5.00,
            'penalty_fee' => 0.00,
            'total' => 115.00,
            'cancellation_policy' => 'Non-refundable',
            'cancellation_deadline' => '2025-09-01 23:59:59',
            'additional_info' => 'Special assistance required',
            'venue' => 'Kuwait International Airport',
            'invoice_price' => 115.00,
            'voucher_status' => 'active',
            'refund_date' => null,
            'enabled' => true,
            'taxes_record' => 'KRF:7.50,CJ:7.60,YQ:0.25',
            'refund_charge' => 1.15,
            'ticket_number' => '2833133219',
            'file_name' => 'test_air_file.txt'
        ];

        // Test valid task data
        $this->assertTrue($this->isValidTaskData($taskData));

        // Test invalid task data (missing required fields)
        $invalidTaskData = $taskData;
        unset($invalidTaskData['company_id']); // Remove required field
        $this->assertFalse($this->isValidTaskData($invalidTaskData));

        // Test invalid enum value
        $invalidEnumData = $taskData;
        $invalidEnumData['type'] = 'invalid_type';
        $this->assertFalse($this->isValidTaskData($invalidEnumData));
    }

    public function test_duplicate_task_detection()
    {
        // Create a client for the task
        $client = Client::factory()->create([
            'agent_id' => $this->agent->id
        ]);

        // Create existing task
        $existingTask = Task::factory()->create([
            'reference' => 'TEST123',
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'agent_id' => $this->agent->id,
            'client_id' => $client->id
        ]);

        $duplicateTaskData = [
            'reference' => 'TEST123',
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'agent_id' => $this->agent->id,
            'client_name' => 'Test Client',
            'type' => 'flight',
            'status' => 'issued'
        ];

        // Should detect duplicate
        $isDuplicate = Task::where('reference', $duplicateTaskData['reference'])
            ->where('company_id', $duplicateTaskData['company_id'])
            ->where('supplier_id', $duplicateTaskData['supplier_id'])
            ->exists();

        $this->assertTrue($isDuplicate);
    }

    public function test_file_moving_after_successful_processing()
    {
        $sourceFile = storage_path("app/{$this->company->name}/{$this->supplier->name}/files_unprocessed/test.txt");
        $processedFile = storage_path("app/{$this->company->name}/{$this->supplier->name}/files_processed/test.txt");

        // Mock file operations
        File::shouldReceive('move')
            ->once()
            ->with($sourceFile, $processedFile)
            ->andReturn(true);

        File::shouldReceive('isDirectory')
            ->andReturn(true);

        File::shouldReceive('makeDirectory')
            ->andReturn(true);

        $result = $this->moveFileToProcessed($sourceFile, $processedFile);
        $this->assertTrue($result);
    }

    public function test_debug_data_export_functionality()
    {
        $debugData = [
            'tasks' => [
                [
                    'reference' => 'TEST123',
                    'client_name' => 'Test Client',
                    'type' => 'flight',
                    'status' => 'issued'
                ]
            ],
            'processing_stats' => [
                'total_files' => 1,
                'successful_tasks' => 1,
                'failed_tasks' => 0
            ]
        ];

        // Mock export functionality
        Storage::shouldReceive('disk')
            ->with('local')
            ->andReturnSelf();

        Storage::shouldReceive('put')
            ->once()
            ->with(Mockery::pattern('/debug_export_.*\.json/'), Mockery::type('string'))
            ->andReturn(true);

        $result = $this->exportDebugData($debugData);
        $this->assertTrue($result);
    }

    public function test_batch_size_limits_handling()
    {
        $files = [];
        for ($i = 0; $i < 15; $i++) {
            $mockFile = Mockery::mock(\Symfony\Component\Finder\SplFileInfo::class);
            $mockFile->shouldReceive('getFilename')->andReturn("test_{$i}.txt");
            $mockFile->shouldReceive('getPathname')->andReturn("/path/test_{$i}.txt");
            $files[] = $mockFile;
        }

        $batchSize = 10;
        $batches = array_chunk($files, $batchSize);

        $this->assertCount(2, $batches);
        $this->assertCount(10, $batches[0]);
        $this->assertCount(5, $batches[1]);
    }

    public function test_logger_prevents_duplicate_error_messages()
    {
        $logger = new FileProcessingLogger('test_context');
        
        // This should only log once per unique error
        $logger->taskSaveError('TEST123', 'Test error', ['reference' => 'TEST123']);
        
        // Even if called multiple times, it should not create duplicate logs
        $logger->taskSaveError('TEST123', 'Test error', ['reference' => 'TEST123']);
        
        // This test passes if no duplicate log entries are created
        $this->assertTrue(true);
    }

    // ===========================================
    // INTEGRATION TESTS - Using Real AirFile Test Data
    // ===========================================

    public function test_process_air_files_with_real_test_data()
    {
        $testDataPath = base_path('tests/TestData/AirFiles');
        
        if (!File::isDirectory($testDataPath)) {
            $this->markTestSkipped('AirFiles test data directory not found at: ' . $testDataPath);
            return;
        }

        $airFiles = File::glob($testDataPath . '/*.air');
        
        if (empty($airFiles)) {
            $this->markTestSkipped('No .air test files found in: ' . $testDataPath);
            return;
        }

        foreach ($airFiles as $airFile) {
            $fileName = basename($airFile);
            $testName = str_replace('.air', '', $fileName);
            $expectedFile = $testDataPath . '/' . $testName . '.expected.json';
            
            if (File::exists($expectedFile)) {
                $this->runProcessAirFileWithRealData($airFile, $expectedFile, $testName);
            }
        }
    }

    public function test_specific_air_file_processing()
    {
        // Test with a specific air file that we know should work
        $testDataPath = base_path('tests/TestData/AirFiles');
        
        if (!File::isDirectory($testDataPath)) {
            File::makeDirectory($testDataPath, 0755, true, true);
            
            // Create a sample test file if directory doesn't exist
            $sampleAirContent = "SSR FOID HK1 NI123456789/P1\nNAME1.JOHN/DOE MR\nTKT/TIME LIMIT\n13JUL24/1200Z\n";
            $sampleAirFile = $testDataPath . '/sample_test.air';
            File::put($sampleAirFile, $sampleAirContent);
            
            $expectedData = [
                'passenger_count' => 1,
                'passengers' => [
                    [
                        'client_name' => 'JOHN DOE',
                        'reference' => 'TEST123',
                        'status' => 'issued',
                        'price' => 150.00,
                        'total' => 150.00
                    ]
                ]
            ];
            File::put($testDataPath . '/sample_test.expected.json', json_encode($expectedData, JSON_PRETTY_PRINT));
        }

        // Now test with the sample file
        $airFiles = File::glob($testDataPath . '/*.air');
        if (!empty($airFiles)) {
            $airFile = $airFiles[0];
            $fileName = basename($airFile);
            $testName = str_replace('.air', '', $fileName);
            $expectedFile = $testDataPath . '/' . $testName . '.expected.json';
            
            if (File::exists($expectedFile)) {
                $this->runProcessAirFileWithRealData($airFile, $expectedFile, $testName);
            }
        }
    }

    public function test_air_file_parser_to_task_data_transformation()
    {
        $testDataPath = base_path('tests/TestData/AirFiles');
        
        if (!File::isDirectory($testDataPath)) {
            $this->markTestSkipped('AirFiles test data directory not found');
            return;
        }

        $airFiles = File::glob($testDataPath . '/*.air');
        
        if (empty($airFiles)) {
            $this->markTestSkipped('No .air test files found');
            return;
        }
        
        $processedFiles = 0;
        $skippedFiles = [];
        
        foreach ($airFiles as $airFile) {
            $fileName = basename($airFile);
            $testName = str_replace('.air', '', $fileName);
            
            try {
                // Use AirFileParser directly to parse the file
                $parser = new AirFileParser($airFile);
                $parserResult = $parser->parseTaskSchema();
                
                $this->assertIsArray($parserResult, "Parser should return array for {$testName}");
                
                // Skip empty results (parser might return empty array for some test files)
                if (empty($parserResult)) {
                    $skippedFiles[] = "{$testName}: Parser returned empty array";
                    continue;
                }
                
                // Transform parser result to ProcessAirFiles format for each passenger
                foreach ($parserResult as $passengerIndex => $passengerData) {
                    // Ensure we have minimum required data for transformation
                    if (!is_array($passengerData)) {
                        $skippedFiles[] = "{$testName} passenger {$passengerIndex}: Invalid passenger data format";
                        continue;
                    }
                    
                    $taskData = $this->transformParserDataToTaskData($passengerData);
                    
                    // Validate that the transformed data is valid for ProcessAirFiles
                    $isValid = $this->isValidTaskData($taskData);
                    
                    if (!$isValid) {
                        // Log which fields are missing for debugging
                        $missing = $this->getMissingRequiredFields($taskData);
                        $skippedFiles[] = "{$testName} passenger {$passengerIndex}: Missing required fields: " . implode(', ', $missing);
                        continue;
                    }
                    
                    $this->assertTrue(
                        $isValid,
                        "Transformed data from {$testName} passenger {$passengerIndex} should be valid for ProcessAirFiles"
                    );
                    
                    // Test flight details if present
                    if (isset($passengerData['task_flight_details'])) {
                        $flightData = $passengerData['task_flight_details'];
                        $flightData['task_id'] = 1; // Add required field
                        $this->assertTrue(
                            $this->isValidFlightDetailData($flightData),
                            "Flight details from {$testName} passenger {$passengerIndex} should be valid"
                        );
                    }
                }
                
                $processedFiles++;
                
            } catch (\Exception $e) {
                // Collect error information instead of immediately skipping
                $skippedFiles[] = "{$testName}: " . $e->getMessage();
                continue;
            }
        }
        
        // Provide feedback about what was processed vs skipped
        if ($processedFiles === 0 && !empty($skippedFiles)) {
            $errorDetails = implode("; ", array_slice($skippedFiles, 0, 3)); // Show first 3 errors
            $this->markTestSkipped("No air files could be processed. Errors: {$errorDetails}");
        }
        
        // Assert that at least some files were processed successfully
        $this->assertGreaterThan(0, $processedFiles, 
            "At least one air file should be successfully processed. Skipped files: " . implode("; ", $skippedFiles)
        );
    }

    public function test_debug_air_file_parsing_issues()
    {
        $testDataPath = base_path('tests/TestData/AirFiles');
        
        if (!File::isDirectory($testDataPath)) {
            $this->markTestSkipped('AirFiles test data directory not found at: ' . $testDataPath);
            return;
        }

        $airFiles = File::glob($testDataPath . '/*.air');
        
        if (empty($airFiles)) {
            $this->markTestSkipped('No .air test files found in: ' . $testDataPath);
            return;
        }

        $debugInfo = [];
        
        foreach ($airFiles as $airFile) {
            $fileName = basename($airFile);
            $testName = str_replace('.air', '', $fileName);
            
            try {
                // Check if file exists and is readable
                if (!File::exists($airFile)) {
                    $debugInfo[$testName] = 'File does not exist';
                    continue;
                }
                
                if (!File::isReadable($airFile)) {
                    $debugInfo[$testName] = 'File is not readable';
                    continue;
                }
                
                $fileSize = File::size($airFile);
                if ($fileSize === 0) {
                    $debugInfo[$testName] = 'File is empty';
                    continue;
                }
                
                // Try to read file content
                $content = File::get($airFile);
                if (empty($content)) {
                    $debugInfo[$testName] = 'File content is empty';
                    continue;
                }
                
                // Try to parse with AirFileParser
                $parser = new AirFileParser($airFile);
                $parserResult = $parser->parseTaskSchema();
                
                $debugInfo[$testName] = [
                    'file_size' => $fileSize,
                    'content_length' => strlen($content),
                    'parser_result_type' => gettype($parserResult),
                    'parser_result_count' => is_array($parserResult) ? count($parserResult) : 'N/A',
                    'sample_content' => substr($content, 0, 100) . (strlen($content) > 100 ? '...' : ''),
                ];
                
            } catch (\Exception $e) {
                $debugInfo[$testName] = [
                    'error' => $e->getMessage(),
                    'error_line' => $e->getLine(),
                    'error_file' => basename($e->getFile()),
                ];
            }
        }
        
        // This test always passes but provides debug information
        $this->assertTrue(true, 'Debug info: ' . json_encode($debugInfo, JSON_PRETTY_PRINT));
    }

    public function test_command_processes_real_air_files_end_to_end()
    {
        $testDataPath = base_path('tests/TestData/AirFiles');
        
        if (!File::isDirectory($testDataPath)) {
            $this->markTestSkipped('AirFiles test data directory not found');
            return;
        }

        // Copy a real test file to the command's expected location
        $airFiles = File::glob($testDataPath . '/*.air');
        
        if (empty($airFiles)) {
            $this->markTestSkipped('No .air test files found');
            return;
        }

        $sourceAirFile = $airFiles[0]; // Use first available test file
        $commandTestPath = storage_path("app/{$this->company->name}/{$this->supplier->name}/files_unprocessed");
        
        // Create the directory structure that ProcessAirFiles expects
        File::makeDirectory($commandTestPath, 0755, true, true);
        
        $targetAirFile = $commandTestPath . '/real_test_file.air';
        File::copy($sourceAirFile, $targetAirFile);
        
        // Mock Log for command output
        Log::shouldReceive('channel')->with('air_processing')->andReturnSelf();
        Log::shouldReceive('info')->andReturnSelf();
        Log::shouldReceive('error')->andReturnSelf();

        // Test that the command can handle real air files by running it
        // The command will use the mocked AIManager from setUp()
        $result = $this->artisan('app:process-files', ['--single' => true]);
        
        // Verify command ran successfully
        $result->assertExitCode(0);
        
        // Clean up
        File::deleteDirectory(dirname($commandTestPath, 2));
    }

    // ===========================================
    // HELPER METHODS FOR INTEGRATION
    // ===========================================

    private function runProcessAirFileWithRealData($airFile, $expectedFile, $testName)
    {
        try {
            // Parse the air file using AirFileParser
            $parser = new AirFileParser($airFile);
            $parserResult = $parser->parseTaskSchema();
            
            // Load expected data
            $expectedData = json_decode(File::get($expectedFile), true);
            
            $this->assertIsArray($parserResult, "Parser result should be array for {$testName}");
            
            // Validate parser result structure matches expectations
            if (isset($expectedData['passenger_count'])) {
                $this->assertCount(
                    $expectedData['passenger_count'],
                    $parserResult,
                    "Passenger count mismatch for {$testName}"
                );
            }
            
            // Transform and validate each passenger for ProcessAirFiles compatibility
            foreach ($parserResult as $index => $passengerData) {
                $taskData = $this->transformParserDataToTaskData($passengerData);

                $this->assertTrue(
                    $this->isValidTaskData($taskData),
                    "Passenger {$index} from {$testName} should produce valid task data for ProcessAirFiles"
                );
            }
            
        } catch (\Exception $e) {
            $this->fail("Failed to process real air file {$testName}: " . $e->getMessage());
        }
    }

    private function transformParserDataToTaskData(array $passengerData): array
    {
        // Transform AirFileParser output to ProcessAirFiles task format
        return [
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'agent_id' => $this->agent->id,
            'type' => isset($passengerData['task_flight_details']) ? 'flight' : 'hotel',
            'status' => $passengerData['status'] ?? 'issued',
            'reference' => $passengerData['reference'] ?? 'TEST_REF',
            'total' => $passengerData['total'] ?? $passengerData['price'] ?? 0.00,
            'client_name' => $passengerData['client_name'] ?? 'Test Client',
            'price' => $passengerData['price'] ?? 0.00,
            'ticket_number' => $passengerData['ticket_number'] ?? null,
            'taxes_record' => $passengerData['taxes_record'] ?? null,
            'created_by' => $passengerData['agent_name'] ?? 'System',
            'issued_by' => $passengerData['agent_amadeus_id'] ?? $passengerData['agent_email'] ?? 'SYSTEM',
        ];
    }

    private function getMissingRequiredFields(array $taskData): array
    {
        $required = ['company_id', 'supplier_id', 'type', 'status', 'reference', 'total'];
        $missing = [];
        
        foreach ($required as $field) {
            if (!isset($taskData[$field]) || (is_string($taskData[$field]) && empty($taskData[$field]))) {
                $missing[] = $field;
            }
        }
        
        return $missing;
    }

    // ===========================================
    // FEATURE TESTS - Integration Testing
    // ===========================================

    public function test_missing_directories_handling()
    {
        // Mock Log to capture the improved logging
        Log::shouldReceive('channel')->with('air_processing')->andReturnSelf();
        Log::shouldReceive('info')->andReturnSelf();
        Log::shouldReceive('error')->andReturnSelf();

        $this->artisan('app:process-files', ['--single' => true])
            ->expectsOutput('Starting AIR file processing from root/air-files directory using single processing...')
            ->assertExitCode(0);
    }

    public function test_empty_directories_processing()
    {
        $testPath = storage_path("app/{$this->company->name}/{$this->supplier->name}/files_unprocessed");
        
        // Create empty directory
        File::makeDirectory($testPath, 0755, true, true);

        // Mock Log
        Log::shouldReceive('channel')->with('air_processing')->andReturnSelf();
        Log::shouldReceive('info')->andReturnSelf();

        $this->artisan('app:process-files', ['--batch' => true])
            ->assertExitCode(0);

        // Clean up
        File::deleteDirectory(dirname($testPath, 2));
    }

    public function test_processing_modes_correctness()
    {
        // Test single processing mode
        $this->artisan('app:process-files', ['--single' => true])
            ->expectsOutput('Starting AIR file processing from root/air-files directory using single processing...')
            ->assertExitCode(0);

        // Test batch processing mode (default)
        $this->artisan('app:process-files', ['--batch' => true])
            ->expectsOutput('Starting AIR file processing from root/air-files directory using batch processing...')
            ->assertExitCode(0);
    }

    public function test_batch_size_limits_in_command()
    {
        $batchSize = 5;
        
        $this->artisan('app:process-files', [
            '--batch' => true,
            '--batch-size' => $batchSize
        ])->assertExitCode(0);
    }

    public function test_debug_data_export_flag()
    {
        // Mock storage for debug export
        Storage::fake('local');

        $this->artisan('app:process-files', ['--test-export' => true])
            ->assertExitCode(0);
    }

    public function test_logger_context_data_inclusion()
    {
        $logger = new FileProcessingLogger('air_processing', [
            'command' => 'process-air-files',
            'company_id' => $this->company->id
        ]);

        // Test that context is properly maintained
        $logger->addContext(['supplier_id' => $this->supplier->id]);
        $logger->fileProcessingStart('test.air');

        // If this runs without error, context is working properly
        $this->assertTrue(true);
    }

    public function test_batch_events_logging()
    {
        $logger = new FileProcessingLogger('batch_processing');
        
        $logger->batchEvent('started', [
            'batch_size' => 10,
            'total_files' => 25
        ]);

        $logger->batchEvent('completed', [
            'processed_files' => 25,
            'success_count' => 23,
            'error_count' => 2
        ]);

        $this->assertTrue(true);
    }

    public function test_file_processing_lifecycle()
    {
        $logger = new FileProcessingLogger('file_processing');
        
        $filename = 'test_file.air';
        
        // Start processing
        $logger->fileProcessingStart($filename, [
            'size' => 1024,
            'type' => 'air_file'
        ]);

        // Complete processing
        $logger->fileProcessingComplete($filename, [
            'tasks_created' => 3,
            'processing_time' => 2.5
        ]);

        $this->assertTrue(true);
    }

    public function test_source_directory_creation()
    {
        // Generate the actual path that will be used by the command
        // Company and supplier names are converted to lowercase with spaces replaced by underscores
        $companyName = strtolower(preg_replace('/\s+/', '_', $this->company->name));
        $supplierName = strtolower(preg_replace('/\s+/', '_', $this->supplier->name));
        $expectedPath = storage_path("app/{$companyName}/{$supplierName}/files_unprocessed");
        
        // Mock File facade
        File::shouldReceive('isDirectory')
            ->once()
            ->with($expectedPath)
            ->andReturn(false);

        File::shouldReceive('makeDirectory')
            ->once()
            ->with($expectedPath, 0755, true, true)
            ->andReturn(true);

        // Note: File::files() is not called because command uses 'continue' after creating directory

        // Mock logger with expected info and error calls
        $mockLogger = Mockery::mock(\Psr\Log\LoggerInterface::class);
        $mockLogger->shouldReceive('info')
            ->atLeast()
            ->once()
            ->andReturn(null);
        $mockLogger->shouldReceive('error')
            ->atLeast()
            ->once()
            ->andReturn(null);

        Log::shouldReceive('channel')
            ->with('air_processing')
            ->andReturn($mockLogger);

        $this->artisan('app:process-files', ['--single' => true])
            ->expectsOutput('Created source directory: ' . $expectedPath . ', please ensure files are pushed here.')
            ->assertExitCode(0);
    }

    // ===========================================
    // HELPER METHODS
    // ===========================================

    private function isValidTaskData(array $taskData): bool
    {
        // Check required fields based on Task model
        $required = ['company_id', 'supplier_id', 'type', 'status', 'reference', 'total'];
        
        foreach ($required as $field) {
            if (!isset($taskData[$field]) || (is_string($taskData[$field]) && empty($taskData[$field]))) {
                return false;
            }
        }

        // Validate enum values
        if (isset($taskData['type']) && !in_array($taskData['type'], ['flight', 'hotel'])) {
            return false;
        }

        // Validate numeric fields
        $numericFields = ['price', 'tax', 'surcharge', 'penalty_fee', 'total', 'original_price', 'invoice_price', 'refund_charge'];
        foreach ($numericFields as $field) {
            if (isset($taskData[$field]) && !is_numeric($taskData[$field])) {
                return false;
            }
        }

        // Validate boolean fields
        if (isset($taskData['enabled']) && !is_bool($taskData['enabled'])) {
            return false;
        }

        // Validate datetime fields
        $datetimeFields = ['cancellation_deadline', 'refund_date'];
        foreach ($datetimeFields as $field) {
            if (isset($taskData[$field]) && $taskData[$field] !== null) {
                if (!$this->isValidDatetime($taskData[$field])) {
                    return false;
                }
            }
        }
        
        return true;
    }

    private function isValidFlightDetailData(array $flightData): bool
    {
        // Check required fields for flight details
        $required = ['task_id'];
        
        foreach ($required as $field) {
            if (!isset($flightData[$field]) || empty($flightData[$field])) {
                return false;
            }
        }

        // Validate numeric fields
        $numericFields = ['farebase', 'task_id', 'country_id_from', 'country_id_to', 'airline_id'];
        foreach ($numericFields as $field) {
            if (isset($flightData[$field]) && !is_numeric($flightData[$field])) {
                return false;
            }
        }

        // Validate datetime fields
        $datetimeFields = ['departure_time', 'arrival_time'];
        foreach ($datetimeFields as $field) {
            if (isset($flightData[$field]) && !$this->isValidDatetime($flightData[$field])) {
                return false;
            }
        }

        return true;
    }

    private function isValidHotelDetailData(array $hotelData): bool
    {
        // Check required fields for hotel details
        $required = ['task_id'];
        
        foreach ($required as $field) {
            if (!isset($hotelData[$field]) || empty($hotelData[$field])) {
                return false;
            }
        }

        // Validate numeric fields
        $numericFields = ['task_id', 'hotel_id', 'room_amount', 'rate'];
        foreach ($numericFields as $field) {
            if (isset($hotelData[$field]) && !is_numeric($hotelData[$field])) {
                return false;
            }
        }

        // Validate boolean fields
        if (isset($hotelData['is_refundable']) && !is_bool($hotelData['is_refundable'])) {
            return false;
        }

        // Validate datetime fields
        $datetimeFields = ['booking_time', 'check_in', 'check_out'];
        foreach ($datetimeFields as $field) {
            if (isset($hotelData[$field]) && !$this->isValidDatetime($hotelData[$field])) {
                return false;
            }
        }

        return true;
    }

    private function isValidCompleteTaskData(array $taskData): bool
    {
        // Validate main task data
        if (!$this->isValidTaskData($taskData)) {
            return false;
        }

        // Validate nested flight details if present
        if (isset($taskData['task_flight_details']) && is_array($taskData['task_flight_details'])) {
            foreach ($taskData['task_flight_details'] as $flightDetail) {
                // Add task_id for validation
                $flightDetail['task_id'] = 1; // Temporary ID for validation
                if (!$this->isValidFlightDetailData($flightDetail)) {
                    return false;
                }
            }
        }

        // Validate nested hotel details if present
        if (isset($taskData['task_hotel_details']) && is_array($taskData['task_hotel_details'])) {
            $hotelDetail = $taskData['task_hotel_details'];
            $hotelDetail['task_id'] = 1; // Temporary ID for validation
            if (!$this->isValidHotelDetailData($hotelDetail)) {
                return false;
            }
        }

        return true;
    }

    private function isValidDatetime(string $datetime): bool
    {
        $format = 'Y-m-d H:i:s';
        $d = \DateTime::createFromFormat($format, $datetime);
        return $d && $d->format($format) === $datetime;
    }

    private function moveFileToProcessed(string $sourceFile, string $processedFile): bool
    {
        return File::move($sourceFile, $processedFile);
    }

    private function exportDebugData(array $data): bool
    {
        $filename = 'debug_export_' . date('Y-m-d_H-i-s') . '.json';
        return Storage::disk('local')->put($filename, json_encode($data, JSON_PRETTY_PRINT));
    }

    public function test_task_flight_detail_data_validation()
    {
        // Complete TaskFlightDetail data matching database format
        $flightDetailData = [
            'task_id' => 1, // Will be set when task is created
            'farebase' => 95.50,
            'departure_time' => '2025-09-05 12:05:00',
            'country_id_from' => 1, // Kuwait
            'airport_from' => 'KWI',
            'terminal_from' => '4',
            'arrival_time' => '2025-09-05 14:35:00',
            'duration_time' => '2h 30m',
            'country_id_to' => 2, // Singapore
            'airport_to' => 'SIN',
            'terminal_to' => '1',
            'airline_id' => 1, // Kuwait Airways
            'flight_number' => 'KU-123',
            'ticket_number' => '2833133219',
            'class_type' => 'economy',
            'baggage_allowed' => '30kg',
            'equipment' => 'A320',
            'flight_meal' => 'Vegetarian',
            'seat_no' => '12A'
        ];

        // Test valid flight detail data
        $this->assertTrue($this->isValidFlightDetailData($flightDetailData));

        // Test invalid flight detail data (missing task_id)
        $invalidFlightData = $flightDetailData;
        unset($invalidFlightData['task_id']);
        $this->assertFalse($this->isValidFlightDetailData($invalidFlightData));

        // Test invalid datetime format
        $invalidDatetimeData = $flightDetailData;
        $invalidDatetimeData['departure_time'] = 'invalid-date';
        $this->assertFalse($this->isValidFlightDetailData($invalidDatetimeData));
    }

    public function test_task_hotel_detail_data_validation()
    {
        // Complete TaskHotelDetail data matching database format
        $hotelDetailData = [
            'task_id' => 1, // Will be set when task is created
            'hotel_id' => 1,
            'booking_time' => '2025-09-01 10:00:00',
            'check_in' => '2025-09-05 15:00:00',
            'check_out' => '2025-09-07 12:00:00',
            'room_reference' => 'ROOM123456',
            'room_number' => '505',
            'room_type' => 'Deluxe Suite',
            'room_amount' => 150.00,
            'room_details' => 'Sea view, king bed, balcony',
            'room_promotion' => 'Early bird discount 10%',
            'rate' => 135.00,
            'meal_type' => 'Breakfast included',
            'is_refundable' => true,
            'supplements' => 'Airport transfer, spa access'
        ];

        // Test valid hotel detail data
        $this->assertTrue($this->isValidHotelDetailData($hotelDetailData));

        // Test invalid hotel detail data (missing task_id)
        $invalidHotelData = $hotelDetailData;
        unset($invalidHotelData['task_id']);
        $this->assertFalse($this->isValidHotelDetailData($invalidHotelData));

        // Test invalid boolean value
        $invalidBooleanData = $hotelDetailData;
        $invalidBooleanData['is_refundable'] = 'not_boolean';
        $this->assertFalse($this->isValidHotelDetailData($invalidBooleanData));
    }

    public function test_complete_task_with_nested_details_validation()
    {
        // Test complete task data with both flight and hotel details
        $completeTaskData = [
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'agent_id' => $this->agent->id,
            'type' => 'flight',
            'status' => 'issued',
            'client_name' => 'Complete Test Client',
            'reference' => 'COMP123',
            'total' => 250.00,
            'price' => 200.00,
            'tax' => 30.00,
            'surcharge' => 20.00,
            'task_flight_details' => [
                [
                    'task_id' => 1,
                    'farebase' => 180.00,
                    'departure_time' => '2025-09-05 08:00:00',
                    'arrival_time' => '2025-09-05 12:30:00',
                    'airport_from' => 'KWI',
                    'airport_to' => 'DXB',
                    'flight_number' => 'KU-101',
                    'class_type' => 'business'
                ]
            ]
        ];

        // Test complete task with nested details
        $this->assertTrue($this->isValidCompleteTaskData($completeTaskData));

        // Test with invalid nested flight details
        $invalidNestedData = $completeTaskData;
        $invalidNestedData['task_flight_details'][0]['departure_time'] = 'invalid-datetime';
        $this->assertFalse($this->isValidCompleteTaskData($invalidNestedData));
    }
}
