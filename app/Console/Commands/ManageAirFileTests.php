<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use App\Services\AirFileParser;

class ManageAirFileTests extends Command
{
    protected $signature = 'airfile:test-manage 
                            {action : Action to perform: list, add, validate, run}
                            {--file= : AIR file path for add action}
                            {--name= : Test case name for add action}
                            {--description= : Test case description}';
    
    protected $description = 'Manage AIR file parser test cases';
    
    protected $testDataPath;
    
    public function __construct()
    {
        parent::__construct();
        $this->testDataPath = base_path('tests/TestData/AirFiles');
    }
    
    public function handle()
    {
        $action = $this->argument('action');
        
        switch ($action) {
            case 'list':
                $this->listTestCases();
                break;
            case 'add':
                $this->addTestCase();
                break;
            case 'validate':
                $this->validateTestCase();
                break;
            case 'run':
                $this->runTests();
                break;
            default:
                $this->error("Unknown action: {$action}");
                $this->info("Available actions: list, add, validate, run");
        }
        
        return 0;
    }
    
    protected function listTestCases()
    {
        $this->info("Current AIR file test cases:");
        $this->info("=" . str_repeat("=", 50));
        
        if (!File::isDirectory($this->testDataPath)) {
            $this->warn("Test data directory does not exist: {$this->testDataPath}");
            return;
        }
        
        $testFiles = File::files($this->testDataPath);
        
        if (empty($testFiles)) {
            $this->warn("No test files found.");
            return;
        }
        
        foreach ($testFiles as $file) {
            $fileName = $file->getFilename();
            if (str_ends_with($fileName, '.air')) {
                $expectedFile = str_replace('.air', '.expected.json', $fileName);
                $expectedPath = $this->testDataPath . '/' . $expectedFile;
                
                $this->info("📄 {$fileName}");
                
                if (File::exists($expectedPath)) {
                    $expected = json_decode(File::get($expectedPath), true);
                    $this->line("   ✅ Expected data: {$expectedFile}");
                    $this->line("   📝 Description: " . ($expected['description'] ?? 'No description'));
                    $this->line("   👥 Passengers: " . ($expected['passenger_count'] ?? 'Unknown'));
                } else {
                    $this->line("   ❌ Missing expected data file: {$expectedFile}");
                }
                $this->line("");
            }
        }
    }
    
    protected function addTestCase()
    {
        $filePath = $this->option('file');
        $testName = $this->option('name');
        $description = $this->option('description') ?? '';
        
        if (!$filePath) {
            $filePath = $this->ask('Enter the path to the AIR file');
        }
        
        if (!$testName) {
            $testName = $this->ask('Enter a name for this test case');
        }
        
        if (!$description) {
            $description = $this->ask('Enter a description for this test case (optional)');
        }
        
        if (!File::exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return;
        }
        
        // Create test data directory if it doesn't exist
        if (!File::isDirectory($this->testDataPath)) {
            File::makeDirectory($this->testDataPath, 0755, true, true);
            $this->info("Created test data directory: {$this->testDataPath}");
        }
        
        // Copy the AIR file
        $testFileName = $testName . '.air';
        $destinationPath = $this->testDataPath . '/' . $testFileName;
        File::copy($filePath, $destinationPath);
        
        $this->info("✅ Copied AIR file to: {$destinationPath}");
        
        // Parse the file to get actual results
        try {
            $parser = new AirFileParser($filePath);
            $result = $parser->parseTaskSchema();
            
            $this->info("📊 Parsed file successfully. Found " . count($result) . " passenger(s)");
            
            // Display parsed data for admin review
            $this->displayParsedData($result);
            
            // Ask admin to confirm and modify expected data
            if ($this->confirm('Do you want to save this as the expected result?')) {
                $expectedData = [
                    'description' => $description,
                    'passenger_count' => count($result),
                    'passengers' => []
                ];
                
                // Process each passenger
                foreach ($result as $index => $passengerData) {
                    $this->info("\n--- Reviewing Passenger " . ($index + 1) . " ---");
                    
                    $expectedPassenger = $this->reviewPassengerData($passengerData);
                    $expectedData['passengers'][$index] = $expectedPassenger;
                }
                
                // Save expected data
                $expectedFile = str_replace('.air', '.expected.json', $testFileName);
                $expectedPath = $this->testDataPath . '/' . $expectedFile;
                
                File::put($expectedPath, json_encode($expectedData, JSON_PRETTY_PRINT));
                
                $this->info("✅ Saved expected data to: {$expectedPath}");
                $this->info("🎯 Test case '{$testName}' has been created successfully!");
            }
            
        } catch (\Exception $e) {
            $this->error("Failed to parse AIR file: " . $e->getMessage());
        }
    }
    
    protected function displayParsedData($result)
    {
        $this->info("\n📋 Parsed Data Preview:");
        $this->info("=" . str_repeat("=", 40));
        
        foreach ($result as $index => $passengerData) {
            $this->info("Passenger " . ($index + 1) . ":");
            $this->line("  Name: " . ($passengerData['client_name'] ?? 'N/A'));
            $this->line("  Reference: " . ($passengerData['reference'] ?? 'N/A'));
            $this->line("  Status: " . ($passengerData['status'] ?? 'N/A'));
            $this->line("  Price: " . ($passengerData['price'] ?? 'N/A') . " " . ($passengerData['currency'] ?? ''));
            $this->line("  Ticket: " . ($passengerData['ticket_number'] ?? 'N/A'));
            $this->line("  Agent: " . ($passengerData['agent_name'] ?? 'N/A'));
            
            if (isset($passengerData['task_flight_details']) && is_array($passengerData['task_flight_details'])) {
                $this->line("  Flights: " . count($passengerData['task_flight_details']));
                foreach ($passengerData['task_flight_details'] as $fIndex => $flight) {
                    $this->line("    " . ((int)$fIndex + 1) . ". " . ($flight['from'] ?? 'N/A') . "-" . ($flight['to'] ?? 'N/A') . " " . ($flight['flight_number'] ?? 'N/A'));
                }
            }
            
            if (isset($passengerData['taxes_record']) && is_array($passengerData['taxes_record'])) {
                $this->line("  Taxes: " . count($passengerData['taxes_record']) . " items");
            }
            
            $this->line("");
        }
    }
    
    protected function reviewPassengerData($passengerData)
    {
        $expectedPassenger = [];
        
        // Core fields that admin should review
        $coreFields = [
            'client_name' => 'Passenger Name',
            'reference' => 'Reference/PNR',
            'status' => 'Status',
            'price' => 'Price',
            'currency' => 'Currency',
            'ticket_number' => 'Ticket Number',
            'agent_name' => 'Agent Name',
            'agent_email' => 'Agent Email',
            'agent_amadeus_id' => 'Agent Amadeus ID'
        ];
        
        foreach ($coreFields as $field => $label) {
            $value = $passengerData[$field] ?? null;
            $this->line("{$label}: {$value}");
            
            if ($this->confirm("Include {$label} in validation?", true)) {
                $expectedPassenger[$field] = $value;
            }
        }
        
        // Flight details
        if (isset($passengerData['task_flight_details']) && is_array($passengerData['task_flight_details'])) {
            if ($this->confirm("Include flight details validation?", true)) {
                $expectedPassenger['task_flight_details'] = [];
                
                foreach ($passengerData['task_flight_details'] as $index => $flight) {
                    $this->line("Flight " . ((int)$index + 1) . ": " . ($flight['from'] ?? 'N/A') . "-" . ($flight['to'] ?? 'N/A') . " " . ($flight['flight_number'] ?? 'N/A'));
                    
                    if ($this->confirm("Include this flight in validation?", true)) {
                        $expectedFlight = [];
                        $flightFields = ['from', 'to', 'flight_number', 'airline', 'departure_date', 'departure_time'];
                        
                        foreach ($flightFields as $field) {
                            if (isset($flight[$field])) {
                                $expectedFlight[$field] = $flight[$field];
                            }
                        }
                        
                        $expectedPassenger['task_flight_details'][] = $expectedFlight;
                    }
                }
            }
        }
        
        // Tax records
        if (isset($passengerData['taxes_record']) && is_array($passengerData['taxes_record'])) {
            if ($this->confirm("Include tax records validation?", true)) {
                $expectedPassenger['taxes_record'] = $passengerData['taxes_record'];
            }
        }
        
        // Additional validations
        $validationTypes = [
            'not_empty' => 'Should not be empty',
            'numeric' => 'Should be numeric',
            'date_format' => 'Should match date format',
            'contains' => 'Should contain specific text'
        ];
        
        if ($this->confirm("Add additional validations?", false)) {
            $expectedPassenger['additional_validations'] = [];
            
            foreach ($coreFields as $field => $label) {
                if (isset($expectedPassenger[$field])) {
                    $this->line("Available validations for {$label}:");
                    foreach ($validationTypes as $type => $desc) {
                        $this->line("  {$type}: {$desc}");
                    }
                    
                    $validationType = $this->choice("Add validation for {$label}?", array_merge(['none'], array_keys($validationTypes)), 'none');
                    
                    if ($validationType !== 'none') {
                        $validation = ['type' => $validationType, 'field' => $field];
                        
                        if ($validationType === 'contains') {
                            $validation['value'] = $this->ask("What text should it contain?");
                        } elseif ($validationType === 'date_format') {
                            $validation['pattern'] = $this->ask("Enter regex pattern for date format", '/\d{4}-\d{2}-\d{2}/');
                        }
                        
                        $expectedPassenger['additional_validations'][] = $validation;
                    }
                }
            }
        }
        
        return $expectedPassenger;
    }
    
    protected function validateTestCase()
    {
        $this->info("🔍 Validating all test cases...");
        
        if (!File::isDirectory($this->testDataPath)) {
            $this->error("Test data directory does not exist: {$this->testDataPath}");
            return;
        }
        
        $airFiles = File::glob($this->testDataPath . '/*.air');
        
        if (empty($airFiles)) {
            $this->warn("No AIR test files found.");
            return;
        }
        
        $validCount = 0;
        $totalCount = count($airFiles);
        
        foreach ($airFiles as $airFile) {
            $fileName = basename($airFile);
            $expectedFile = str_replace('.air', '.expected.json', $fileName);
            $expectedPath = $this->testDataPath . '/' . $expectedFile;
            
            $this->line("Validating: {$fileName}");
            
            if (!File::exists($expectedPath)) {
                $this->error("  ❌ Missing expected data file: {$expectedFile}");
                continue;
            }
            
            try {
                $expected = json_decode(File::get($expectedPath), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->error("  ❌ Invalid JSON in expected data file");
                    continue;
                }
                
                $parser = new AirFileParser($airFile);
                $result = $parser->parseTaskSchema();
                
                // Quick validation
                $passengerCount = count($result);
                $expectedCount = $expected['passenger_count'] ?? 0;
                
                if ($passengerCount === $expectedCount) {
                    $this->info("  ✅ Valid - {$passengerCount} passenger(s)");
                    $validCount++;
                } else {
                    $this->error("  ❌ Passenger count mismatch: expected {$expectedCount}, got {$passengerCount}");
                }
                
            } catch (\Exception $e) {
                $this->error("  ❌ Parsing error: " . $e->getMessage());
            }
        }
        
        $this->info("\n📊 Validation Summary:");
        $this->info("Valid test cases: {$validCount}/{$totalCount}");
        
        if ($validCount === $totalCount) {
            $this->info("🎉 All test cases are valid!");
        } else {
            $this->warn("⚠️  Some test cases need attention.");
        }
    }
    
    protected function runTests()
    {
        $this->info("🏃 Running AIR file parser tests...");
        
        $exitCode = $this->call('test', [
            '--filter' => 'AirFileParserTest',
            '--testdox' => true
        ]);
        
        if ($exitCode === 0) {
            $this->info("🎉 All tests passed!");
        } else {
            $this->error("❌ Some tests failed. Check the output above.");
        }
    }
}
