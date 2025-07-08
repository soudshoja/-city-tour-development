<?php

namespace App\Services;

use App\Schema\TaskSchema;
use App\Schema\TaskFlightSchema;
use Illuminate\Support\Collection;

class AirFileService
{
    /**
     * Process a single AIR file and return normalized data
     *
     * @param string $filePath Path to the AIR file
     * @return array Normalized task data
     * @throws \Exception
     */
    public function processFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \Exception("AIR file not found: {$filePath}");
        }

        $parser = new AirFileParser($filePath);
        $taskData = $parser->parseTaskSchema();
        
        // Normalize the data using the schema
        $normalizedTask = TaskSchema::normalize($taskData);
        
        // Normalize flight details if present
        if (isset($normalizedTask['task_flight_details']) && is_array($normalizedTask['task_flight_details'])) {
            $normalizedTask['task_flight_details'] = TaskFlightSchema::normalize($normalizedTask['task_flight_details']);
        }

        return $normalizedTask;
    }

    /**
     * Process multiple AIR files from a directory
     *
     * @param string $directoryPath Path to directory containing AIR files
     * @param bool $includeErrors Whether to include files that failed to process
     * @return Collection Collection of processed results
     */
    public function processDirectory(string $directoryPath, bool $includeErrors = false): Collection
    {
        if (!is_dir($directoryPath)) {
            throw new \Exception("Directory not found: {$directoryPath}");
        }

        $files = glob($directoryPath . '/*.AIR');
        $results = collect();

        foreach ($files as $filePath) {
            try {
                $taskData = $this->processFile($filePath);
                $results->push([
                    'success' => true,
                    'file' => basename($filePath),
                    'file_path' => $filePath,
                    'data' => $taskData,
                    'processed_at' => now(),
                ]);
            } catch (\Exception $e) {
                if ($includeErrors) {
                    $results->push([
                        'success' => false,
                        'file' => basename($filePath),
                        'file_path' => $filePath,
                        'error' => $e->getMessage(),
                        'processed_at' => now(),
                    ]);
                }
            }
        }

        return $results;
    }

    /**
     * Extract specific field from AIR file
     *
     * @param string $filePath Path to the AIR file
     * @param string $field Field name to extract
     * @return mixed Field value or null if not found
     */
    public function extractField(string $filePath, string $field)
    {
        $data = $this->processFile($filePath);
        
        // Check if field exists in main data
        if (array_key_exists($field, $data)) {
            return $data[$field];
        }
        
        // Check if field exists in flight details
        if (isset($data['task_flight_details']) && array_key_exists($field, $data['task_flight_details'])) {
            return $data['task_flight_details'][$field];
        }
        
        return null;
    }

    /**
     * Validate AIR file format
     *
     * @param string $filePath Path to the AIR file
     * @return array Validation result with status and messages
     */
    public function validateFile(string $filePath): array
    {
        $validation = [
            'valid' => true,
            'messages' => [],
            'warnings' => [],
        ];

        if (!file_exists($filePath)) {
            $validation['valid'] = false;
            $validation['messages'][] = 'File does not exist';
            return $validation;
        }

        if (!str_ends_with(strtoupper($filePath), '.AIR')) {
            $validation['warnings'][] = 'File does not have .AIR extension';
        }

        $content = file_get_contents($filePath);
        
        if (empty($content)) {
            $validation['valid'] = false;
            $validation['messages'][] = 'File is empty';
            return $validation;
        }

        // Check for required AIR file markers
        if (!str_contains($content, 'AIR-BLK')) {
            $validation['warnings'][] = 'File does not contain AIR-BLK header';
        }

        if (!str_contains($content, 'T-K')) {
            $validation['warnings'][] = 'File does not contain ticket number (T-K line)';
        }

        if (!str_contains($content, 'MUC1A')) {
            $validation['warnings'][] = 'File does not contain GDS reference (MUC1A line)';
        }

        return $validation;
    }

    /**
     * Get summary statistics from processed AIR files
     *
     * @param Collection $processedFiles Collection of processed file results
     * @return array Summary statistics
     */
    public function getSummaryStats(Collection $processedFiles): array
    {
        $successful = $processedFiles->where('success', true);
        $failed = $processedFiles->where('success', false);

        $stats = [
            'total_files' => $processedFiles->count(),
            'successful' => $successful->count(),
            'failed' => $failed->count(),
            'success_rate' => $processedFiles->count() > 0 ? 
                round(($successful->count() / $processedFiles->count()) * 100, 2) : 0,
        ];

        if ($successful->count() > 0) {
            $successfulData = $successful->pluck('data');
            
            $stats['status_breakdown'] = $successfulData
                ->pluck('status')
                ->countBy()
                ->toArray();
                
            $stats['total_amount'] = $successfulData
                ->pluck('total')
                ->sum();
                
            $stats['average_amount'] = $successfulData
                ->pluck('total')
                ->avg();
                
            $stats['currency_breakdown'] = $successfulData
                ->pluck('exchange_currency')
                ->countBy()
                ->toArray();
        }

        return $stats;
    }

    /**
     * Export processed data to various formats
     *
     * @param Collection $processedFiles Collection of processed file results
     * @param string $format Export format (json, csv, xml)
     * @param string $outputPath Output file path
     * @return bool Success status
     */
    public function exportData(Collection $processedFiles, string $format, string $outputPath): bool
    {
        try {
            switch (strtolower($format)) {
                case 'json':
                    return $this->exportToJson($processedFiles, $outputPath);
                    
                case 'csv':
                    return $this->exportToCsv($processedFiles, $outputPath);
                    
                case 'xml':
                    return $this->exportToXml($processedFiles, $outputPath);
                    
                default:
                    throw new \Exception("Unsupported export format: {$format}");
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Export to JSON format
     */
    private function exportToJson(Collection $processedFiles, string $outputPath): bool
    {
        $data = $processedFiles->toArray();
        return file_put_contents($outputPath, json_encode($data, JSON_PRETTY_PRINT)) !== false;
    }

    /**
     * Export to CSV format
     */
    private function exportToCsv(Collection $processedFiles, string $outputPath): bool
    {
        $successful = $processedFiles->where('success', true);
        
        if ($successful->isEmpty()) {
            return false;
        }

        $handle = fopen($outputPath, 'w');
        
        // Get headers from first successful result
        $firstResult = $successful->first()['data'];
        $headers = array_keys($firstResult);
        
        // Remove nested objects for CSV
        $headers = array_filter($headers, function($header) {
            return !in_array($header, ['task_flight_details', 'task_hotel_details']);
        });
        
        // Add flight detail headers
        if (isset($firstResult['task_flight_details'])) {
            $flightHeaders = array_keys($firstResult['task_flight_details']);
            foreach ($flightHeaders as $header) {
                $headers[] = 'flight_' . $header;
            }
        }
        
        // Write headers
        fputcsv($handle, array_merge(['file', 'processed_at'], $headers));
        
        // Write data
        foreach ($successful as $result) {
            $row = [
                $result['file'],
                $result['processed_at']->toISOString()
            ];
            
            $taskData = $result['data'];
            
            // Add task data
            foreach ($headers as $header) {
                if (strpos($header, 'flight_') === 0) {
                    $flightField = substr($header, 7);
                    $row[] = $taskData['task_flight_details'][$flightField] ?? '';
                } elseif (!in_array($header, ['task_flight_details', 'task_hotel_details'])) {
                    $row[] = $taskData[$header] ?? '';
                }
            }
            
            fputcsv($handle, $row);
        }
        
        fclose($handle);
        return true;
    }

    /**
     * Export to XML format
     */
    private function exportToXml(Collection $processedFiles, string $outputPath): bool
    {
        $xml = new \SimpleXMLElement('<air_files/>');
        
        foreach ($processedFiles as $result) {
            $fileElement = $xml->addChild('file');
            $fileElement->addAttribute('name', $result['file']);
            $fileElement->addAttribute('success', $result['success'] ? 'true' : 'false');
            $fileElement->addAttribute('processed_at', $result['processed_at']->toISOString());
            
            if ($result['success']) {
                $this->arrayToXml($result['data'], $fileElement);
            } else {
                $fileElement->addChild('error', htmlspecialchars($result['error']));
            }
        }
        
        return $xml->asXML($outputPath) !== false;
    }

    /**
     * Convert array to XML recursively
     */
    private function arrayToXml(array $data, \SimpleXMLElement $xml): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $child = $xml->addChild($key);
                $this->arrayToXml($value, $child);
            } else {
                $xml->addChild($key, htmlspecialchars((string) $value));
            }
        }
    }
}

