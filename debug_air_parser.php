<?php

require_once 'vendor/autoload.php';
require_once 'bootstrap/app.php';

use App\Services\AirFileParser;

// Test both files
$testFiles = [
    'void_single_passenger.air',
    'multi_passenger_issued.air'
];

foreach ($testFiles as $testFile) {
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "Testing: {$testFile}\n";
    echo str_repeat("=", 50) . "\n";
    
    $filePath = "tests/TestData/AirFiles/{$testFile}";
    
    try {
        $parser = new AirFileParser($filePath);
        $result = $parser->parseTaskSchema();
        
        echo "Parser output (array of " . count($result) . " task(s)):\n";
        echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
        
    } catch (Exception $e) {
        echo "Error parsing {$testFile}: " . $e->getMessage() . "\n";
    }
}
