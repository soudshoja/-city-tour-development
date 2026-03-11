<?php

use App\AI\AIManager;
use Illuminate\Support\Facades\Log;

/**
 * Test script for OpenWebUI integration
 * 
 * This script can be used to test the OpenWebUI integration manually
 * Run with: php artisan tinker
 * Then execute: include_once 'tests/openwebui_test.php';
 */

function testOpenWebUIIntegration()
{
    try {
        echo "Testing OpenWebUI Integration...\n";
        
        // Create AI Manager instance
        $aiManager = new AIManager();
        
        // Test basic chat functionality
        echo "1. Testing chat functionality...\n";
        $chatResult = $aiManager->chat([
            ['role' => 'user', 'content' => 'Hello, can you confirm you are working?']
        ]);
        
        if ($chatResult['status'] === 'success') {
            echo "✓ Chat test passed\n";
            echo "Response: " . substr($chatResult['data'], 0, 100) . "...\n";
        } else {
            echo "✗ Chat test failed: " . $chatResult['message'] . "\n";
            return false;
        }
        
        // Test file processing (if you have a test file)
        $testFilePath = storage_path('app/test_files/sample.pdf');
        
        if (file_exists($testFilePath)) {
            echo "2. Testing file processing...\n";
            $fileResult = $aiManager->processWithAiTool($testFilePath, 'sample.pdf');
            
            if ($fileResult['status'] === 'success') {
                echo "✓ File processing test passed\n";
                echo "Extracted " . count($fileResult['data']) . " items\n";
            } else {
                echo "✗ File processing test failed: " . $fileResult['message'] . "\n";
            }
        } else {
            echo "2. Skipping file processing test (no test file at $testFilePath)\n";
        }
        
        echo "\nOpenWebUI integration test completed successfully!\n";
        echo "You can now run: php artisan app:process-files\n";
        
        return true;
        
    } catch (Exception $e) {
        echo "✗ Test failed with exception: " . $e->getMessage() . "\n";
        Log::error('OpenWebUI test failed', ['error' => $e->getMessage()]);
        return false;
    }
}

function createSampleEnvConfig()
{
    echo "\nSample .env configuration for OpenWebUI:\n";
    echo "========================================\n";
    echo "AI_PROVIDER=openwebui\n";
    echo "OPENWEBUI_API_KEY=your_api_key_here\n";
    echo "OPENWEBUI_API_URL=http://localhost:3000\n";
    echo "OPENWEBUI_MODEL=llama3.1:latest\n";
    echo "========================================\n";
}

// Uncomment the line below to run the test when this file is included
// testOpenWebUIIntegration();

echo "OpenWebUI test functions loaded.\n";
echo "Run testOpenWebUIIntegration() to test the integration.\n";
echo "Run createSampleEnvConfig() to see sample configuration.\n";