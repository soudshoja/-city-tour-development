<?php

namespace App\AI\Services;

use App\AI\Contracts\AIClientInterface;
use App\AI\Support\AIResponse;
use App\Enums\TaskType;
use App\Models\Agent;
use App\Models\Airport;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\Task;
use App\Schema\TaskSchema;
use App\Schema\TaskFlightSchema;
use App\Schema\TaskHotelSchema;
use App\Schema\TaskInsuranceSchema;
use App\Schema\TaskVisaSchema;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class OpenWebUIClient implements AIClientInterface
{
    protected string $apiUrl;
    protected string $apiKey;
    protected string $model;
    protected $logger;

    public function __construct()
    {
        $this->logger = Log::channel('ai');

        if (config('ai.default') !== 'openwebui') {
            return;
        }

        $this->apiUrl = config('ai.providers.openwebui.url');
        $this->apiKey = config('ai.providers.openwebui.key');
        $this->model = config('ai.providers.openwebui.model');

        if (config('app.env') !== 'testing') {
            if (empty($this->apiUrl) || empty($this->apiKey)) {
                throw new Exception('OpenWebUI configuration is missing. Please check your AI_PROVIDER settings.');
            }
        }
    }

    public function chat(array $messages): array
    {
        $requestId = Str::uuid();
        $url = $this->apiUrl . '/chat/completions';
        
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
        ];

        // Log request
        $this->logger->info('OpenWebUI Chat Request', [
            'request_id' => $requestId,
            'method' => 'chat',
            'endpoint' => $url,
            'model' => $this->model,
            'payload' => $payload,
            'timestamp' => now()->toISOString(),
        ]);

        $response = Http::withToken($this->apiKey)
            ->withoutVerifying()
            ->post($url, $payload);

        // Log response
        if ($response->successful()) {
            $result = $response->json();
            $this->logger->info('OpenWebUI Chat Response', [
                'request_id' => $requestId,
                'status_code' => $response->status(),
                'response_data' => $result,
                'usage' => $result['usage'] ?? [],
                'timestamp' => now()->toISOString(),
            ]);
        } else {
            $this->logger->error('OpenWebUI Chat Request Failed', [
                'request_id' => $requestId,
                'status_code' => $response->status(),
                'response_body' => $response->body(),
                'timestamp' => now()->toISOString(),
            ]);
        }

        // Check if the API call failed
        if ($response->failed()) {
            $this->logger->error('OpenWebUI Chat Request Failed', [
                'request_id' => $requestId,
                'status_code' => $response->status(),
                'response_body' => $response->body(),
                'timestamp' => now()->toISOString(),
            ]);
            return AIResponse::error('OpenWebUI API request failed: ' . $response->body());
        }

        $result = $response->json();
        $content = $result['choices'][0]['message']['content'] ?? 'No response received';

        // Return in standardized format
        return AIResponse::success(
            $content,
            'Chat completed successfully',
            [
                'usage' => $result['usage'] ?? [],
                'model' => $this->model,
                'provider_response' => $result,
                'request_id' => $requestId
            ]
        );
    }

    /**
     * Main function to process the file using OpenWebUI's new process.
     * This implements the new file processing workflow with RAG indexing.
     */
    public function processWithAiTool(string $filePath, string $fileName): array
    {
        try {
            // Use the new OpenWebUI process
            $result = $this->newProcess($filePath, $fileName);
            
            if (!$result['success']) {
                return [
                    'status' => 'error',
                    'message' => $result['error'],
                    'original_filename' => $fileName,
                    'data' => null,
                ];
            }

            // Extract and normalize the data similar to OpenAI processing
            $extractedData = $result['extracted_data'];
            
            if (!$extractedData) {
                return [
                    'status' => 'error',
                    'message' => 'No data extracted from file',
                    'original_filename' => $fileName,
                    'data' => null,
                ];
            }

            // Try to parse as JSON first
            if (is_string($extractedData)) {
                $decodedData = json_decode($extractedData, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $extractedData = $decodedData;
                }
            }

            // Ensure we have an array of tasks
            $processedItems = [];
            
            if (is_array($extractedData)) {
                // Check if it's a result wrapper or direct array
                if (isset($extractedData['result']) && is_array($extractedData['result'])) {
                    $items = $extractedData['result'];
                } elseif (array_keys($extractedData) === range(0, count($extractedData) - 1)) {
                    // Numeric indexed array
                    $items = $extractedData;
                } else {
                    // Single task object
                    $items = [$extractedData];
                }

                // Normalize all items
                foreach ($items as $item) {
                    if (is_array($item)) {
                        $normalized = TaskSchema::normalize(array_merge(
                            $item,
                            [
                                'task_flight_details' => $item['task_flight_details'] ?? [],
                                'task_hotel_details' => $item['task_hotel_details'] ?? [],
                                'task_insurance_details' => $item['task_insurance_details'] ?? [],
                                'task_visa_details' => $item['task_visa_details'] ?? [],
                            ]
                        ));
                        $processedItems[] = $normalized;
                    }
                }
            }

            $this->logger->info('OpenWebUI extracted data from ' . $fileName . ': ' . json_encode($processedItems));

            return [
                'status' => 'success',
                'message' => "Successfully processed {$fileName} using OpenWebUI.",
                'original_filename' => $fileName,
                'data' => $processedItems,
            ];

        } catch (Exception $e) {
            $this->logger->error("OpenWebUI processing failed for {$fileName}: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => "OpenWebUI processing failed: " . $e->getMessage(),
                'original_filename' => $fileName,
                'data' => null,
            ];
        }
    }

    /**
     * New OpenWebUI processing workflow
     */
    public function newProcess($file, $fileName = null): array
    {
        $filename = $fileName ?? 'N/A';
        try {
            // Handle different file types to get filename
            if ($file instanceof UploadedFile) {
                $filename = $file->getClientOriginalName();
                $filePath = $file->getRealPath();
            } elseif ($file instanceof \SplFileInfo) {
                $filename = $file->getFilename();
                $filePath = $file->getRealPath();
            } elseif (is_string($file)) {
                $filename = $fileName ?? basename($file);
                $filePath = $file;
                
                // Create a temporary UploadedFile-like object for processing
                if (!file_exists($filePath)) {
                    throw new Exception("File not found: {$filePath}");
                }
            } else {
                $filename = basename($file);
                $filePath = $file;
            }

            // 1. Upload the file
            $fileId = $this->uploadToOpenWebUI($filePath, $filename);

            // 2. Wait for the file to be processed/indexed for RAG
            $this->waitForFileProcessing($fileId, $filename);

            // 3. Extract information using the file in the RAG context
            $extractionPrompt = 'Extract Booking Details From Files';
            $extractedInfo = $this->extractWithOpenWebUI($fileId, $extractionPrompt, $filename);

            return [
                'success' => true,
                'filename' => $filename,
                'file_id' => $fileId,
                'extracted_data' => $extractedInfo
            ];
            
        } catch (Exception $e) {
            // Centralized error handling for the entire process
            $this->logger->error('OpenWebUI file processing pipeline failed: ' . $e->getMessage(), ['filename' => $filename]);
            return [
                'success' => false,
                'filename' => $filename,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Uploads the file to the OpenWebUI API.
     */
    private function uploadToOpenWebUI($filePath, $filename)
    {
        try {
            $this->logger->info("Uploading file to OpenWebUI: {$filename}", [
                'file_path' => $filePath,
            ]);

            // Handle different file input types
            if ($filePath instanceof UploadedFile) {
                $fileContents = file_get_contents($filePath->getRealPath());
                $actualFilename = $filePath->getClientOriginalName();
                $fileSize = $filePath->getSize();
            } elseif (is_string($filePath) && file_exists($filePath)) {
                $fileContents = file_get_contents($filePath);
                $actualFilename = $filename;
                $fileSize = filesize($filePath);
            } else {
                throw new Exception("Invalid file path or file does not exist: {$filePath}");
            }

            $this->logger->info("File details for OpenWebUI upload", [
                'filename' => $actualFilename,
                'file_size' => $fileSize,
                'file_path' => is_string($filePath) ? $filePath : 'UploadedFile'
            ]);

            $response = Http::timeout(120)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json'
                ])
                ->attach('file', $fileContents, $actualFilename)
                ->post($this->apiUrl . '/v1/files/');

            $this->logger->info("OpenWebUI upload response for {$actualFilename}: " . $response->body());
            
            if ($response->successful()) {
                $result = $response->json();
                $fileId = $result['id'] ?? null;
                
                if (!$fileId) {
                    throw new Exception('No file ID returned from OpenWebUI');
                }
                
                return $fileId;
            } else {
                throw new Exception('OpenWebUI upload failed: ' . $response->status() . ' - ' . $response->body());
            }
        } catch (Exception $e) {
            $this->logger->error('OpenWebUI upload error: ' . $e->getMessage());
            throw new Exception('Failed to upload to OpenWebUI: ' . $e->getMessage());
        }
    }

    /**
     * Polls the OpenWebUI status endpoint until the file is fully processed for RAG.
     */
    private function waitForFileProcessing($fileId, $filename)
    {
        $maxAttempts = 60; // 60 attempts
        $delay = 2; // 2 seconds between attempts (Total timeout: 120 seconds)
        $attempt = 0;

        $this->logger->info("Waiting for file RAG processing on OpenWebUI for file: {$filename}");

        while ($attempt < $maxAttempts) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json'
                ])->get($this->apiUrl . '/v1/files/' . $fileId);

                // Added log level to DEBUG since this runs frequently
                $this->logger->debug("Attempt {$attempt}/{$maxAttempts}: OpenWebUI file status response: " . $response->body());

                if ($response->successful()) {
                    $fileData = $response->json();
                    
                    // Check for nested 'data' object with 'status' as 'completed' AND actual content
                    $ragStatus = $fileData['data']['status'] ?? 'pending';
                    $hasContent = !empty($fileData['data']['content'] ?? null);

                    if ($ragStatus === 'completed' && $hasContent) {
                        $this->logger->info("File processed successfully: {$filename} (RAG Status: {$ragStatus})");
                        return; // Successfully processed, exit the loop
                    }

                    // Log current status for tracing
                    $this->logger->info("File still processing: {$filename}. Current RAG status: {$ragStatus}");
                }
                
                $attempt++;
                sleep($delay);
                
            } catch (Exception $e) {
                // Catch HTTP errors, network issues, or JSON decoding problems
                $this->logger->warning("Error checking file status: " . $e->getMessage() . ". Retrying in {$delay} seconds.");
                $attempt++;
                sleep($delay);
            }
        }

        throw new Exception("File processing timeout. OpenWebUI took too long to process the file (over " . ($maxAttempts * $delay) . " seconds). The LLM will not be able to read it.");
    }

    /**
     * Sends the extraction prompt and file ID to OpenWebUI for RAG extraction.
     */
    private function extractWithOpenWebUI($fileId, $extractionPrompt, $filename)
    {
        try {
            // Build the comprehensive extraction prompt similar to OpenAI
            $fullPrompt = $this->buildExtractionPrompt($extractionPrompt);

            // IMPORTANT: Use the files parameter correctly with RAG
            $payload = [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert at extracting information from documents. Use the provided document content to answer the user\'s request accurately. Always cite specific information from the document.'
                    ],
                    [
                        'role' => 'user',
                        'content' => "Document: {$filename}\n\nTask: {$fullPrompt}\n\nPlease extract the requested information from the uploaded document."
                    ]
                ],
                'files' => [
                    [
                        'type' => 'file',
                        'id' => $fileId
                    ]
                ],
                'stream' => false
            ];

            $this->logger->info("Sending extraction request to OpenWebUI", ['payload' => $payload]);

            $response = Http::timeout(180)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json'
                ])
                ->post($this->apiUrl . '/chat/completions', $payload);

            $this->logger->info("OpenWebUI extraction response for {$filename}: " . $response->body());

            if ($response->successful()) {
                $result = $response->json();
                
                $this->logger->info("OpenWebUI Response", ['response' => $result]);
                
                // Extract content from various possible response structures
                if (isset($result['choices'][0]['message']['content'])) {
                    return $result['choices'][0]['message']['content'];
                } elseif (isset($result['message']['content'])) {
                    return $result['message']['content'];
                } elseif (isset($result['response'])) {
                    return $result['response'];
                } else {
                    return json_encode($result, JSON_PRETTY_PRINT);
                }
            } else {
                $errorBody = $response->body();
                $this->logger->error("OpenWebUI chat error: " . $response->status() . " - " . $errorBody);
                throw new Exception("OpenWebUI chat failed: {$response->status()} - {$errorBody}");
            }
        } catch (Exception $e) {
            $this->logger->error('OpenWebUI chat error: ' . $e->getMessage());
            throw new Exception('Failed to extract information: ' . $e->getMessage());
        }
    }

    /**
     * Build comprehensive extraction prompt similar to OpenAI implementation
     */
    private function buildExtractionPrompt($basePrompt): string
    {
        $taskFields = TaskSchema::getSchema();
        $flightFields = TaskFlightSchema::getSchema();
        $hotelFields = TaskHotelSchema::getSchema();
        $insuranceFields = TaskInsuranceSchema::getSchema();
        $visaFields = TaskVisaSchema::getSchema();

        $suppliers = Supplier::all();
        $supplierList = $suppliers->pluck('name')->toArray();
        $supplierList = json_encode($supplierList);

        $airportList = json_encode(Airport::all()->toArray());

        $prompt = "You are an assistant for processing uploaded files to extract structured data for a task management system.\n\n";
        $prompt .= "Base task: {$basePrompt}\n\n";
        $prompt .= "Extract data following these models:\n\n";
        $prompt .= "1. `tasks` model with the following fields:\n";
        foreach ($taskFields as $field => $meta) {
            $prompt .= "   - `$field`: {$meta['desc']}\n";
        }
        $prompt .= "\n2. `task_flight_details` model (for flights) - THIS IS AN ARRAY that can contain multiple flight details:\n";
        foreach ($flightFields as $field => $meta) {
            $prompt .= "   - `$field`: {$meta['description']}\n";
        }
        $prompt .= "\n3. `task_hotel_details` model (for hotels) - THIS IS AN ARRAY that can contain multiple hotel details:\n";
        foreach ($hotelFields as $field => $meta) {
            $prompt .= "   - `$field`: {$meta['description']}\n";
        }
        $prompt .= "\n4. `task_insurance_details` model (for insurances) - THIS IS AN ARRAY that can contain multiple insurance details:\n";
        foreach ($insuranceFields as $field => $meta) {
            $prompt .= "   - `$field`: {$meta['description']}\n";
        }
        $prompt .= "\n5. `task_visa_details` model (for visa):\n";
        foreach ($visaFields as $field => $meta) {
            $prompt .= "   - `$field`: {$meta['description']}\n";
        }

        $prompt .= "\nINSTRUCTIONS:\n";
        $prompt .= "- Extract relevant data from the uploaded content in JSON format, matching the structure of these models.\n";
        $prompt .= "- Only include fields with available data, and omit any null or empty fields.\n";
        $prompt .= "- All related time should be in the format of 'Y-m-d H:i:s'\n";
        $prompt .= "- The file may contain multiple passengers/bookings. Return an array of task objects.\n";
        $prompt .= "- Each passenger should be a separate task object with their own ticket/booking details.\n";
        $prompt .= "- For supplier name, refer to this list: $supplierList\n";
        $prompt .= "- Airport codes should be matched against: $airportList\n";
        $prompt .= "- Return ONLY valid JSON format without any additional text or explanations\n";

        $prompt .= "\nReturn the result in this JSON format:\n";
        $prompt .= "{\n";
        $prompt .= "  \"result\": [\n";
        $prompt .= "    {\n";
        $prompt .= "      \"additional_info\": \"relevant booking info\",\n";
        $prompt .= "      \"ticket_number\": \"document/ticket number\",\n";
        $prompt .= "      \"status\": \"issued/confirmed/cancelled\",\n";
        $prompt .= "      \"price\": 100.00,\n";
        $prompt .= "      \"currency\": \"KWD\",\n";
        $prompt .= "      \"total\": 115.00,\n";
        $prompt .= "      \"reference\": \"main reference number\",\n";
        $prompt .= "      \"type\": \"flight/hotel/package\",\n";
        $prompt .= "      \"client_name\": \"passenger/customer name\",\n";
        $prompt .= "      \"supplier_name\": \"supplier/vendor name\",\n";
        $prompt .= "      \"venue\": \"service location\",\n";
        $prompt .= "      \"issued_date\": \"2025-07-04 00:00:00\",\n";
        $prompt .= "      \"task_flight_details\": [...],\n";
        $prompt .= "      \"task_hotel_details\": [...],\n";
        $prompt .= "      \"task_insurance_details\": [...],\n";
        $prompt .= "      \"task_visa_details\": {...}\n";
        $prompt .= "    }\n";
        $prompt .= "  ]\n";
        $prompt .= "}\n";

        return $prompt;
    }

    // Implement other required interface methods
    
    public function extractPassportData(string $filePath, string $fileName): array
    {
        try {
            $result = $this->newProcess($filePath, $fileName);
            
            if (!$result['success']) {
                return AIResponse::error($result['error']);
            }

            // For passport extraction, we need a specific prompt
            $passportPrompt = $this->buildPassportExtractionPrompt();
            $extractedInfo = $this->extractWithOpenWebUI($result['file_id'], $passportPrompt, $fileName);

            $passportData = is_string($extractedInfo) ? json_decode($extractedInfo, true) : $extractedInfo;

            return AIResponse::success(
                $passportData,
                'Passport data extracted successfully',
                [
                    'file_name' => $fileName,
                    'file_id' => $result['file_id'],
                    'extracted_content' => $extractedInfo
                ]
            );
        } catch (Exception $e) {
            $this->logger->error('Exception in OpenWebUI extractPassportData: ' . $e->getMessage());
            return AIResponse::error('Exception occurred during passport extraction: ' . $e->getMessage());
        }
    }

    private function buildPassportExtractionPrompt(): string
    {
        return "Extract passport details from the provided document. Return the data in JSON format only:

        - `passport_no`: Passport number or Passport No.
        - `civil_no`: Civil number or Civil No. (if available)
        - `name`: Full name as per the passport
        - `first_name`: First name based on the first name from the 'Full Name'
        - `middle_name`: Middle name (if available)
        - `last_name`: Last name based on the last name from the 'Full Name'(if available)
        - `nationality`: Nationality
        - `date_of_birth`: Date of birth in YYYY-MM-DD format
        - `date_of_issue`: Date of issue in YYYY-MM-DD format
        - `date_of_expiry`: Date of expiry in YYYY-MM-DD format
        - `place_of_birth`: Place of birth
        - `place_of_issue`: Place of issue

        Important guidelines:
        1. If a field is not found or not clearly visible, set its value to null
        2. Ensure all dates are in YYYY-MM-DD format
        3. Extract the full name exactly as it appears on the passport
        4. For passport number, include only the alphanumeric passport number without any prefixes
        5. Return only valid JSON format without any additional text or explanations";
    }

    public function extractAirFiles(string $content): array
    {
        // For OpenWebUI, we need to handle text content differently
        // We can create a temporary file and process it, or use chat completion directly
        try {
            $prompt = $this->buildAirFileExtractionPrompt();
            
            $response = $this->chat([
                [
                    'role' => 'system',
                    'content' => $prompt
                ],
                [
                    'role' => 'user',
                    'content' => $content
                ]
            ]);

            if ($response['status'] !== 'success') {
                return [
                    'status' => 'error',
                    'message' => $response['message'],
                    'data' => null,
                ];
            }

            $message = $response['data'];
            $decodedResponse = json_decode($message, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'status' => 'error',
                    'message' => 'Failed to decode response JSON: ' . json_last_error_msg(),
                    'data' => null,
                ];
            }

            return [
                'status' => 'success',
                'message' => 'Data extracted successfully',
                'data' => $decodedResponse['result'] ?? $decodedResponse,
            ];

        } catch (Exception $e) {
            $this->logger->error('OpenWebUI extractAirFiles error: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to extract air files: ' . $e->getMessage(),
                'data' => null,
            ];
        }
    }

    private function buildAirFileExtractionPrompt(): string
    {
        $airportList = json_encode(Airport::all()->toArray());
        $taskFields = TaskSchema::getSchema();
        $flightFields = TaskFlightSchema::getSchema();
        $hotelFields = TaskHotelSchema::getSchema();

        $prompt = "You are an assistant for processing AIR files to extract structured data for a task management system.\n\n";
        $prompt .= "1. `tasks` model with the following fields:\n";
        foreach ($taskFields as $field => $meta) {
            $prompt .= "   - `$field`: {$meta['desc']}\n";
        }
        $prompt .= "\n2. `task_flight_details` model (for flights) - THIS IS AN ARRAY that can contain multiple flight details:\n";
        foreach ($flightFields as $field => $meta) {
            $prompt .= "   - `$field`: {$meta['description']}\n";
        }

        $prompt .= "\nExtract relevant data from the uploaded content in JSON format, matching the structure of these models.";
        $prompt .= "\nReturn the data in JSON format with 'result' containing array of tasks.";
        $prompt .= "\nSet supplier_name to 'Amadeus' for AIR files.";
        $prompt .= "\nAirport list for reference: $airportList";

        return $prompt;
    }

    public function extractPdfFiles(string $fileId): array
    {
        try {
            // For OpenWebUI, $fileId should be a file path
            $result = $this->newProcess($fileId);
            
            if (!$result['success']) {
                return [
                    'status' => 'error',
                    'message' => $result['error'],
                    'data' => null,
                ];
            }

            $extractedData = $result['extracted_data'];
            
            // Parse the response
            if (is_string($extractedData)) {
                $decodedData = json_decode($extractedData, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $extractedData = $decodedData;
                }
            }

            // Ensure proper format
            if (isset($extractedData['result'])) {
                $data = $extractedData['result'];
            } elseif (is_array($extractedData)) {
                $data = $extractedData;
            } else {
                $data = [$extractedData];
            }

            return [
                'status' => 'success',
                'message' => 'Data extracted successfully',
                'data' => $data,
            ];

        } catch (Exception $e) {
            $this->logger->error('OpenWebUI extractPdfFiles error: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to extract PDF files: ' . $e->getMessage(),
                'data' => null,
            ];
        }
    }

    public function extractMultiplePdfFiles(array $fileIds): array
    {
        $results = [];
        
        foreach ($fileIds as $fileId) {
            $result = $this->extractPdfFiles($fileId);
            $results[$fileId] = $result;
        }

        return [
            'status' => 'success',
            'message' => 'Batch extraction completed',
            'data' => $results
        ];
    }

    public function processBatchFiles(array $files): array
    {
        $results = [];
        
        foreach ($files as $fileInfo) {
            $filePath = $fileInfo['path'];
            $fileName = $fileInfo['name'];
            
            try {
                $result = $this->processWithAiTool($filePath, $fileName);
                $results[$fileName] = $result;
            } catch (Exception $e) {
                $this->logger->error("OpenWebUI batch processing failed for {$fileName}: " . $e->getMessage());
                $results[$fileName] = [
                    'status' => 'error',
                    'message' => 'Batch processing failed: ' . $e->getMessage(),
                    'data' => []
                ];
            }
        }

        return [
            'status' => 'success',
            'message' => 'Batch processing completed',
            'data' => $results
        ];
    }

    public function uploadFileToOpenAI($file, string $purpose = 'user_data')
    {
        // For OpenWebUI, redirect to our upload method
        if ($file instanceof UploadedFile) {
            return $this->uploadToOpenWebUI($file, $file->getClientOriginalName());
        } elseif (is_string($file)) {
            return $this->uploadToOpenWebUI($file, basename($file));
        } else {
            throw new Exception('Unsupported file type for OpenWebUI upload');
        }
    }
}