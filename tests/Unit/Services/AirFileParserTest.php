<?php

namespace Tests\Unit\Services;

use App\Services\AirFileParser;
use Tests\TestCase;
use Illuminate\Support\Facades\File;

class AirFileParserTest extends TestCase
{
    protected $testDataPath;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->testDataPath = base_path('tests/TestData/AirFiles');
    }
    
    /**
     * Test all AIR files against their expected extracted data
     */
    public function test_all_air_files_extraction()
    {
        $testCases = $this->getTestCasesFromFiles();
        
        $this->assertNotEmpty($testCases, 'No test cases found. Please add AIR files to ' . $this->testDataPath);
        
        foreach ($testCases as $testCase) {
            $this->runSingleTestCase($testCase);
        }
    }
    
    /**
     * Test individual AIR file structures
     *
     * @dataProvider airFileTestCaseProvider
     */
    public function test_individual_air_file_extraction($testCase)
    {
        $this->runSingleTestCase($testCase);
    }
    
    /**
     * Test that parser can handle invalid or malformed files gracefully
     */
    public function test_parser_handles_invalid_files()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'invalid_air_');
        file_put_contents($tempFile, "INVALID CONTENT\nNOT A VALID AIR FILE");
        
        try {
            $parser = new AirFileParser($tempFile);
            $result = $parser->parseTaskSchema();
            
            // Should return array even for invalid files, but might be empty or have default values
            $this->assertIsArray($result);
            
        } catch (\Exception $e) {
            // It's acceptable for parser to throw exceptions for completely invalid files
            $this->assertInstanceOf(\Exception::class, $e);
        } finally {
            unlink($tempFile);
        }
    }
    
    /**
     * Test that parser can handle empty files
     */
    public function test_parser_handles_empty_files()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'empty_air_');
        file_put_contents($tempFile, "");
        
        try {
            $parser = new AirFileParser($tempFile);
            $result = $parser->parseTaskSchema();
            
            $this->assertIsArray($result);
            
        } catch (\Exception $e) {
            $this->assertInstanceOf(\Exception::class, $e);
        } finally {
            unlink($tempFile);
        }
    }
    
    /**
     * Run a single test case
     */
    protected function runSingleTestCase($testCase)
    {
        try {
            // Parse the file
            $parser = new AirFileParser($testCase['air_file_path']);
            $result = $parser->parseTaskSchema();
            
            // Validate the result
            $this->validateExtractionResult($result, $testCase['expected_data'], $testCase['test_name']);
            
        } catch (\Exception $e) {
            $this->fail("Failed to parse {$testCase['test_name']}: " . $e->getMessage());
        }
    }
    
    /**
     * Validate the extraction result against expected data
     */
    protected function validateExtractionResult($actualResult, $expectedData, $testName)
    {
        $this->assertIsArray($actualResult, "Result should be an array for test: {$testName}");
        $this->assertNotEmpty($actualResult, "Result should not be empty for test: {$testName}");
        
        // Check number of passengers
        if (isset($expectedData['passenger_count'])) {
            $this->assertCount(
                $expectedData['passenger_count'], 
                $actualResult, 
                "Expected {$expectedData['passenger_count']} passengers in test: {$testName}"
            );
        }
        
        // Validate each passenger's data
        foreach ($expectedData['passengers'] as $passengerIndex => $expectedPassengerData) {
            $this->assertArrayHasKey(
                $passengerIndex, 
                $actualResult, 
                "Missing passenger index {$passengerIndex} in test: {$testName}"
            );
            
            $actualPassengerData = $actualResult[$passengerIndex];
            
            $this->validatePassengerData($actualPassengerData, $expectedPassengerData, $testName, $passengerIndex);
        }
    }
    
    /**
     * Validate individual passenger data
     */
    protected function validatePassengerData($actual, $expected, $testName, $passengerIndex)
    {
        $prefix = "Test: {$testName}, Passenger: {$passengerIndex}";
        
        // Core fields validation
        $coreFields = [
            'client_name', 'reference', 'status', 'price', 'currency', 'total',
            'ticket_number', 'agent_name', 'agent_email', 'agent_amadeus_id'
        ];
        
        foreach ($coreFields as $field) {
            if (isset($expected[$field])) {
                $this->assertEquals(
                    $expected[$field], 
                    $actual[$field] ?? null, 
                    "{$prefix} - Field '{$field}' mismatch. Expected: '{$expected[$field]}', Got: '" . ($actual[$field] ?? 'null') . "'"
                );
            }
        }
        
        // Flight details validation
        if (isset($expected['task_flight_details'])) {
            $this->assertArrayHasKey('task_flight_details', $actual, "{$prefix} - Missing flight details");
            $this->validateFlightDetails($actual['task_flight_details'], $expected['task_flight_details'], $prefix);
        }
        
        // Tax records validation
        if (isset($expected['taxes_record'])) {
            $this->assertArrayHasKey('taxes_record', $actual, "{$prefix} - Missing tax records");
            $this->validateTaxRecords($actual['taxes_record'], $expected['taxes_record'], $prefix);
        }
        
        // Additional validations
        if (isset($expected['additional_validations'])) {
            foreach ($expected['additional_validations'] as $validation) {
                $this->runCustomValidation($actual, $validation, $prefix);
            }
        }
    }
    
    /**
     * Validate flight details - handles both object and array formats
     */
    protected function validateFlightDetails($actualFlights, $expectedFlights, $prefix)
    {
        // If expected is an object (AirFileParser format), validate fields directly
        if (is_array($expectedFlights) && !isset($expectedFlights[0])) {
            // Object format - validate individual fields
            $flightFields = ['farebase', 'departure_time', 'country_id_from', 'airport_from', 'terminal_from', 
                           'arrival_time', 'duration_time', 'country_id_to', 'airport_to', 'terminal_to',
                           'airline_id', 'flight_number', 'class_type', 'baggage_allowed', 'equipment',
                           'ticket_number', 'flight_meal', 'seat_no'];
            
            foreach ($flightFields as $field) {
                if (isset($expectedFlights[$field])) {
                    $this->assertEquals(
                        $expectedFlights[$field], 
                        $actualFlights[$field] ?? null, 
                        "{$prefix} - Flight field '{$field}' mismatch. Expected: '{$expectedFlights[$field]}', Got: '" . ($actualFlights[$field] ?? 'null') . "'"
                    );
                }
            }
            return;
        }
        
        // Array format (multiple flight segments)
        $this->assertCount(
            count($expectedFlights), 
            $actualFlights, 
            "{$prefix} - Flight count mismatch. Expected: " . count($expectedFlights) . ", Got: " . count($actualFlights)
        );
        
        foreach ($expectedFlights as $index => $expectedFlight) {
            $actualFlight = $actualFlights[$index] ?? null;
            $this->assertNotNull($actualFlight, "{$prefix} - Missing flight {$index}");
            
            $flightFields = ['from', 'to', 'flight_number', 'airline', 'departure_date', 'departure_time'];
            foreach ($flightFields as $field) {
                if (isset($expectedFlight[$field])) {
                    $this->assertEquals(
                        $expectedFlight[$field], 
                        $actualFlight[$field] ?? null, 
                        "{$prefix} - Flight {$index} field '{$field}' mismatch. Expected: '{$expectedFlight[$field]}', Got: '" . ($actualFlight[$field] ?? 'null') . "'"
                    );
                }
            }
        }
    }
    
    /**
     * Validate tax records - handles both string and array formats
     */
    protected function validateTaxRecords($actualTaxes, $expectedTaxes, $prefix)
    {
        // If expected is a string (AirFileParser format), just compare strings
        if (is_string($expectedTaxes)) {
            $this->assertEquals(
                $expectedTaxes, 
                $actualTaxes, 
                "{$prefix} - Tax record string mismatch. Expected: '{$expectedTaxes}', Got: '{$actualTaxes}'"
            );
            return;
        }
        
        // Array format (structured tax data)
        $this->assertCount(
            count($expectedTaxes), 
            $actualTaxes, 
            "{$prefix} - Tax count mismatch. Expected: " . count($expectedTaxes) . ", Got: " . count($actualTaxes)
        );
        
        foreach ($expectedTaxes as $index => $expectedTax) {
            $actualTax = $actualTaxes[$index] ?? null;
            $this->assertNotNull($actualTax, "{$prefix} - Missing tax {$index}");
            
            if (isset($expectedTax['code'])) {
                $this->assertEquals(
                    $expectedTax['code'], 
                    $actualTax['code'] ?? null, 
                    "{$prefix} - Tax {$index} code mismatch. Expected: '{$expectedTax['code']}', Got: '" . ($actualTax['code'] ?? 'null') . "'"
                );
            }
            
            if (isset($expectedTax['amount'])) {
                $this->assertEquals(
                    $expectedTax['amount'], 
                    $actualTax['amount'] ?? null, 
                    "{$prefix} - Tax {$index} amount mismatch. Expected: '{$expectedTax['amount']}', Got: '" . ($actualTax['amount'] ?? 'null') . "'"
                );
            }
        }
    }
    
    /**
     * Run custom validation
     */
    protected function runCustomValidation($actual, $validation, $prefix)
    {
        $field = $validation['field'];
        $value = $actual[$field] ?? '';
        
        switch ($validation['type']) {
            case 'not_empty':
                $this->assertNotEmpty($value, "{$prefix} - {$field} should not be empty");
                break;
            case 'numeric':
                $this->assertIsNumeric($value, "{$prefix} - {$field} should be numeric, got: " . gettype($value));
                break;
            case 'date_format':
                $pattern = $validation['pattern'] ?? '/\d{4}-\d{2}-\d{2}/';
                $this->assertMatchesRegularExpression($pattern, $value, "{$prefix} - {$field} date format mismatch. Value: '{$value}'");
                break;
            case 'contains':
                $expectedValue = $validation['value'];
                $this->assertStringContainsString($expectedValue, $value, "{$prefix} - {$field} should contain '{$expectedValue}', got: '{$value}'");
                break;
        }
    }
    
    /**
     * Data provider for individual test cases
     */
    public static function airFileTestCaseProvider()
    {
        // Get the base path differently for static context
        $testDataPath = dirname(__DIR__, 2) . '/TestData/AirFiles';
        $testCases = [];
        
        if (!is_dir($testDataPath)) {
            return [];
        }
        
        $airFiles = glob($testDataPath . '/*.air');
        
        foreach ($airFiles as $airFile) {
            $fileName = basename($airFile);
            $testName = str_replace('.air', '', $fileName);
            $expectedFile = $testDataPath . '/' . $testName . '.expected.json';
            
            if (file_exists($expectedFile)) {
                $expectedData = json_decode(file_get_contents($expectedFile), true);
                
                if (json_last_error() === JSON_ERROR_NONE) {
                    $testCases[] = [
                        'test_name' => $testName,
                        'air_file_path' => $airFile,
                        'expected_data' => $expectedData
                    ];
                }
            }
        }
        
        $provider = [];
        foreach ($testCases as $testCase) {
            $provider[$testCase['test_name']] = [$testCase];
        }
        
        return $provider;
    }
    
    /**
     * Get test cases from files in the test data directory
     */
    protected function getTestCasesFromFiles()
    {
        $testCases = [];
        
        if (!File::isDirectory($this->testDataPath)) {
            return $testCases;
        }
        
        $airFiles = File::glob($this->testDataPath . '/*.air');
        
        foreach ($airFiles as $airFile) {
            $fileName = basename($airFile);
            $testName = str_replace('.air', '', $fileName);
            $expectedFile = $this->testDataPath . '/' . $testName . '.expected.json';
            
            if (File::exists($expectedFile)) {
                $expectedData = json_decode(File::get($expectedFile), true);
                
                if (json_last_error() === JSON_ERROR_NONE) {
                    $testCases[] = [
                        'test_name' => $testName,
                        'air_file_path' => $airFile,
                        'expected_data' => $expectedData
                    ];
                }
            }
        }
        
        return $testCases;
    }
    
    /**
     * Test helper: Create a test case programmatically
     */
    protected function createTestCase($name, $airContent, $expectedData)
    {
        if (!File::isDirectory($this->testDataPath)) {
            File::makeDirectory($this->testDataPath, 0755, true, true);
        }
        
        $airFile = $this->testDataPath . '/' . $name . '.air';
        $expectedFile = $this->testDataPath . '/' . $name . '.expected.json';
        
        File::put($airFile, $airContent);
        File::put($expectedFile, json_encode($expectedData, JSON_PRETTY_PRINT));
        
        return [
            'test_name' => $name,
            'air_file_path' => $airFile,
            'expected_data' => $expectedData
        ];
    }
}
