<?php

namespace App\AI\Services;

use App\AI\Contracts\AIClientInterface;
use App\AI\Contracts\WorkspaceAIInterface;
use App\AI\Support\AIResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Exception;

class AnythingLLMClient implements WorkspaceAIInterface
{
    protected string $base;
    protected string $key;
    protected string $workspace;
    protected int $timeout;
    protected string $slug;
    protected $logger;

    public function __construct()
    {
        $config = config('ai.providers.anythingLLM');
        $this->base = rtrim($config['base'] ?? '', '/');
        $this->key = $config['api_key'] ?? '';
        $this->workspace = $config['workspace'] ?? '';
        $this->timeout = $config['timeout'] ?? 45;
        $this->slug = $config['slug'] ?? 'default-workspace';
        $this->logger = Log::channel('ai');

        if (empty($this->base) || empty($this->key)) {
            throw new Exception('AnythingLLM configuration is missing. Please check your AI_PROVIDER settings.');
        }
    }

    protected function http()
    {
        return Http::baseUrl($this->base)
            ->withToken($this->key)
            ->acceptJson()
            ->timeout($this->timeout)
            ->retry(3, 250);
    }

    public function getWorkspaceSlug(?string $slug = null): string
    {
        return $slug ?: $this->slug;
    }

    /**
     * Chat with AnythingLLM using the workspace context
     */
    public function chat(array $messages): array
    {
        $requestId = Str::uuid();
        
        try {
            // Convert OpenAI-style messages to AnythingLLM format
            $lastMessage = end($messages);
            $messageText = is_array($lastMessage) ? ($lastMessage['content'] ?? '') : (string) $lastMessage;

            $payload = [
                'message' => $messageText,
                'mode' => 'query',
                'sessionId' => (string) Str::uuid(),
            ];

            $slug = $this->getWorkspaceSlug();
            
            // Log request
            $this->logger->info('AnythingLLM Chat Request', [
                'request_id' => $requestId,
                'method' => 'chat',
                'workspace' => $slug,
                'endpoint' => "/api/v1/workspace/{$slug}/chat",
                'payload' => $payload,
                'original_messages' => $messages,
                'timestamp' => now()->toISOString(),
            ]);

            $response = $this->http()->post("/api/v1/workspace/{$slug}/chat", $payload);

            if ($response->failed()) {
                $this->logger->error('AnythingLLM Chat Request Failed', [
                    'request_id' => $requestId,
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                    'timestamp' => now()->toISOString(),
                ]);
                throw new Exception('AnythingLLM chat request failed: ' . $response->body());
            }

            $result = $response->json();

            // Log successful response
            $this->logger->info('AnythingLLM Chat Response', [
                'request_id' => $requestId,
                'status_code' => $response->status(),
                'response_data' => $result,
                'response_size' => strlen(json_encode($result)),
                'timestamp' => now()->toISOString(),
            ]);

            // Return in standardized format
            return AIResponse::success(
                $result['textResponse'] ?? $result['response'] ?? 'No response received',
                'Chat completed successfully',
                [
                    'usage' => $result['usage'] ?? [],
                    'session_id' => $payload['sessionId'],
                    'workspace' => $slug,
                    'provider_response' => $result,
                    'request_id' => $requestId
                ]
            );

        } catch (Exception $e) {
            $this->logger->error('AnythingLLM Chat Error', [
                'request_id' => $requestId,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'timestamp' => now()->toISOString(),
            ]);
            return AIResponse::error('Chat failed: ' . $e->getMessage());
        }
    }

    /**
     * Process file with AI tool - uploads and embeds the file, then queries it
     */
    public function processWithAiTool(string $filePath, string $fileName): array
    {
        try {
            // Create a temporary UploadedFile from the file path
            $file = new UploadedFile($filePath, $fileName, null, null, true);
            
            // Upload and embed the file
            $uploadResult = $this->uploadAndEmbedFile($file);
            
            if (isset($uploadResult['error'])) {
                return AIResponse::error($uploadResult['error']);
            }

            // Query the uploaded document
            $query = "Please analyze the uploaded file '{$fileName}' and provide a summary of its contents.";
            $chatResult = $this->chat([['role' => 'user', 'content' => $query]]);

            if (!$chatResult['success']) {
                return $chatResult; // Return the error from chat
            }

            return AIResponse::success(
                $chatResult['data'],
                'File processed successfully',
                [
                    'upload_result' => $uploadResult,
                    'file_name' => $fileName,
                    'file_path' => $filePath
                ]
            );

        } catch (Exception $e) {
            $this->logger->error('AnythingLLM processWithAiTool error: ' . $e->getMessage());
            return AIResponse::error('File processing failed: ' . $e->getMessage());
        }
    }

    /**
     * Extract airline files - uses document processing capabilities
     */
    public function extractAirFiles(string $content): array
    {
        try {
            $query = "Extract airline/flight information from the following content. Please identify flight numbers, dates, destinations, passenger names, and booking references:\n\n" . $content;
            
            $result = $this->chat([['role' => 'user', 'content' => $query]]);
            
            return [
                'status' => 'success',
                'extracted_data' => $result['choices'][0]['message']['content'] ?? 'No data extracted',
                'original_response' => $result
            ];

        } catch (Exception $e) {
            $this->logger->error('AnythingLLM extractAirFiles error: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Extract PDF files - queries embedded documents
     */
    public function extractPdfFiles(string $fileId): array
    {
        try {
            $query = "Please extract and summarize the content from the PDF document with ID: {$fileId}";
            
            $result = $this->chat([['role' => 'user', 'content' => $query]]);
            
            return [
                'status' => 'success',
                'extracted_data' => $result['choices'][0]['message']['content'] ?? 'No data extracted',
                'file_id' => $fileId
            ];

        } catch (Exception $e) {
            $this->logger->error('AnythingLLM extractPdfFiles error: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Extract multiple PDF files
     */
    public function extractMultiplePdfFiles(array $fileIds): array
    {
        $results = [];
        
        foreach ($fileIds as $fileId) {
            $results[$fileId] = $this->extractPdfFiles($fileId);
        }
        
        return $results;
    }

    /**
     * Process batch files
     */
    public function processBatchFiles(array $files): array
    {
        $results = [];
        
        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $fileName = $file->getClientOriginalName();
                $filePath = $file->getRealPath();
            } else {
                $fileName = basename($file);
                $filePath = $file;
            }
            
            $results[] = $this->processWithAiTool($filePath, $fileName);
        }
        
        return $results;
    }

    /**
     * Upload file to AnythingLLM - implements the interface but adapted for AnythingLLM
     */
    public function uploadFileToOpenAI($file, string $purpose = 'user_data')
    {
        try {
            if (is_string($file)) {
                // If file is a path, create UploadedFile
                $file = new UploadedFile($file, basename($file), null, null, true);
            }

            $uploadResult = $this->uploadAndEmbedFile($file);
            
            if (isset($uploadResult['error'])) {
                throw new Exception($uploadResult['error']);
            }

            // Return the document ID for compatibility
            return $uploadResult['upload']['id'] ?? $uploadResult['upload']['document']['id'] ?? null;

        } catch (Exception $e) {
            $this->logger->error('AnythingLLM uploadFileToOpenAI error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Extract passport data using AnythingLLM
     */
    public function extractPassportData(string $filePath, string $fileName): array
    {
        try {
            // Upload the file first
            $file = new UploadedFile($filePath, $fileName, null, null, true);
            $uploadResult = $this->uploadAndEmbedFile($file);
            
            if (isset($uploadResult['error'])) {
                return AIResponse::error($uploadResult['error']);
            }

            // Query for passport data extraction
            $prompt = "You are an assistant for a travel agency. Your task is to extract passport details from the uploaded document. 

            Analyze the document carefully and extract the following fields. Return the data in JSON format only:

            - `passport_no`: Passport number or Passport No.
            - `civil_no`: Civil number or Civil No. (if available)
            - `name`: Full name as per the passport
            - `first_name`: First name
            - `middle_name`: Middle name (if available)
            - `last_name`: Last name (if available)
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

            $result = $this->chat([['role' => 'user', 'content' => $prompt]]);
            
            if (!$result['success']) {
                return $result; // Return the error from chat
            }

            $extractedContent = $result['data'];
            
            // Try to parse JSON from the response
            $jsonData = AIResponse::parseJsonContent($extractedContent);
            
            return AIResponse::success(
                $jsonData,
                'Passport data extracted successfully',
                [
                    'upload_result' => $uploadResult,
                    'file_name' => $fileName,
                    'file_path' => $filePath
                ]
            );

        } catch (Exception $e) {
            $this->logger->error('AnythingLLM extractPassportData error: ' . $e->getMessage());
            return AIResponse::error('Passport extraction failed: ' . $e->getMessage());
        }
    }

    /**
     * Upload and embed file in AnythingLLM workspace
     */
    public function uploadAndEmbedFile($file, ?string $workspaceSlug = null): array
    {
        $requestId = Str::uuid();
        
        // Handle different file input types
        if (is_string($file)) {
            $file = new UploadedFile($file, basename($file), null, null, true);
        }
        
        if (!$file instanceof UploadedFile) {
            $this->logger->error('AnythingLLM Upload Error', [
                'request_id' => $requestId,
                'error' => 'Invalid file type provided',
                'file_type' => gettype($file),
                'timestamp' => now()->toISOString(),
            ]);
            return ['error' => 'Invalid file type provided'];
        }

        $slug = $this->getWorkspaceSlug($workspaceSlug);

        $endpoint = "/api/v1/document/upload";

        try {
            // Log upload request
            $this->logger->info('AnythingLLM Upload Request', [
                'request_id' => $requestId,
                'method' => 'uploadAndEmbedFile',
                'workspace' => $slug,
                'endpoint' => $endpoint,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'file_mime_type' => $file->getMimeType(),
                'timestamp' => now()->toISOString(),
            ]);

            // Upload file
            $upload = $this->http()
                ->attach('file', fopen($file->getRealPath(), 'r'), $file->getClientOriginalName())
                ->post($endpoint);

            $uploadJson = $upload->json();
            
            // Log upload response
            $this->logger->info('AnythingLLM Upload Response', [
                'request_id' => $requestId,
                'status_code' => $upload->status(),
                'response_data' => $uploadJson,
                'timestamp' => now()->toISOString(),
            ]);
            
            if ($upload->failed()) {
                $this->logger->error('AnythingLLM Upload Failed', [
                    'request_id' => $requestId,
                    'status_code' => $upload->status(),
                    'error' => $uploadJson['error'] ?? 'Unknown error',
                    'response_body' => $upload->body(),
                    'timestamp' => now()->toISOString(),
                ]);
                return ['error' => 'Failed to upload file: ' . ($uploadJson['error'] ?? 'Unknown error')];
            }

            // Extract document IDs
            $docIds = $this->extractDocumentIds($uploadJson);

            if (empty($docIds)) {
                $this->logger->warning('AnythingLLM Upload Warning', [
                    'request_id' => $requestId,
                    'warning' => 'No document IDs returned from upload',
                    'upload_response' => $uploadJson,
                    'timestamp' => now()->toISOString(),
                ]);
                return ['error' => 'No document IDs returned from upload'];
            }

            // Log embed request
            $this->logger->info('AnythingLLM Embed Request', [
                'request_id' => $requestId,
                'document_ids' => $docIds,
                'workspace' => $slug,
                'timestamp' => now()->toISOString(),
            ]);

            // Embed documents
            $embedResult = $this->embedDocuments($docIds, $slug);

            // Log final result
            $this->logger->info('AnythingLLM Upload & Embed Complete', [
                'request_id' => $requestId,
                'document_ids' => $docIds,
                'embed_result' => $embedResult,
                'timestamp' => now()->toISOString(),
            ]);

            return [
                'upload' => $uploadJson,
                'embed' => $embedResult,
                'document_ids' => $docIds,
                'request_id' => $requestId
            ];

        } catch (Exception $e) {
            $this->logger->error('AnythingLLM Upload & Embed Error', [
                'request_id' => $requestId,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'timestamp' => now()->toISOString(),
            ]);
            return ['error' => 'Upload failed: ' . $e->getMessage()];
        }
    }

    /**
     * Extract document IDs from upload response
     */
    protected function extractDocumentIds(array $uploadResponse): array
    {
        $docIds = [];

        if (isset($uploadResponse['id'])) {
            $docIds[] = $uploadResponse['id'];
        }
        
        if (isset($uploadResponse['document']['id'])) {
            $docIds[] = $uploadResponse['document']['id'];
        }
        
        if (isset($uploadResponse['documents']) && is_array($uploadResponse['documents'])) {
            foreach ($uploadResponse['documents'] as $doc) {
                if (isset($doc['id'])) {
                    $docIds[] = $doc['id'];
                }
            }
        }

        return array_values(array_unique(array_filter($docIds)));
    }

    /**
     * Embed documents in workspace
     */
    protected function embedDocuments(array $documentIds, ?string $workspaceSlug = null): array
    {
        $requestId = Str::uuid();
        $slug = $this->getWorkspaceSlug($workspaceSlug);

        $endpoint = "/api/v1/workspace/{$slug}/documents/embed";

        $this->logger->info('AnythingLLM Embed Documents Request', [
            'request_id' => $requestId,
            'method' => 'embedDocuments',
            'workspace' => $slug,
            'endpoint' =>$endpoint,
            'document_ids' => $documentIds,
            'timestamp' => now()->toISOString(),
        ]);

        $response = $this->http()->post($endpoint, [
            'documentIds' => array_values($documentIds),
        ]);

        $result = $response->json();

        $this->logger->info('AnythingLLM Embed Documents Response', [
            'request_id' => $requestId,
            'status_code' => $response->status(),
            'response_data' => $result,
            'timestamp' => now()->toISOString(),
        ]);

        return $result ?? [];
    }

    /**
     * Additional AnythingLLM specific methods (not in interface)
     */
    
    public function createWorkspace(string $name, string $slug, string $description = ''): array
    {
        $requestId = Str::uuid();
        
        $payload = [
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
        ];

        $this->logger->info('AnythingLLM Create Workspace Request', [
            'request_id' => $requestId,
            'method' => 'createWorkspace',
            'endpoint' => '/api/v1/workspaces',
            'payload' => $payload,
            'timestamp' => now()->toISOString(),
        ]);

        $response = $this->http()->post('/api/v1/workspaces', $payload);
        $result = $response->json();

        $this->logger->info('AnythingLLM Create Workspace Response', [
            'request_id' => $requestId,
            'status_code' => $response->status(),
            'response_data' => $result,
            'timestamp' => now()->toISOString(),
        ]);

        return $result;
    }

    public function listDocuments(?string $workspaceSlug = null): array
    {
        $slug = $this->getWorkspaceSlug($workspaceSlug);
        $response = $this->http()->get("/api/v1/workspace/{$slug}/documents");
        return $response->json();
    }

    public function deleteDocument(string $documentId, ?string $workspaceSlug = null): array
    {
        $slug = $this->getWorkspaceSlug($workspaceSlug);
        $response = $this->http()->delete("/api/v1/workspace/{$slug}/documents/{$documentId}");
        return $response->json();
    }

    public function addUrl(string $url, ?string $workspaceSlug = null): array
    {
        $slug = $this->getWorkspaceSlug($workspaceSlug);

        $response = $this->http()->post("/api/v1/workspace/{$slug}/documents/from-url", [
            'url' => $url,
        ]);

        return $response->json();
    }
}
