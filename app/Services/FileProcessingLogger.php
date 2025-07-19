<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

class FileProcessingLogger
{
    private LoggerInterface $logger;
    private string $context;
    private array $contextData;

    public function __construct(string $context = 'file_processing', array $contextData = [])
    {
        $this->logger = Log::channel('air_processing');
        $this->context = $context;
        $this->contextData = $contextData;
    }

    /**
     * Set context for all subsequent logs
     */
    public function setContext(string $context, array $contextData = []): self
    {
        $this->context = $context;
        $this->contextData = $contextData;
        return $this;
    }

    /**
     * Add to existing context data
     */
    public function addContext(array $additionalData): self
    {
        $this->contextData = array_merge($this->contextData, $additionalData);
        return $this;
    }

    /**
     * Log informational messages with context
     */
    public function info(string $message, array $additionalData = []): void
    {
        $this->logger->info($this->formatMessage($message), $this->mergeContext($additionalData));
    }

    /**
     * Log error messages with context (avoid duplicates)
     */
    public function error(string $message, array $additionalData = []): void
    {
        $this->logger->error($this->formatMessage($message), $this->mergeContext($additionalData));
    }

    /**
     * Log warning messages with context
     */
    public function warning(string $message, array $additionalData = []): void
    {
        $this->logger->warning($this->formatMessage($message), $this->mergeContext($additionalData));
    }

    /**
     * Log debug messages with context
     */
    public function debug(string $message, array $additionalData = []): void
    {
        $this->logger->debug($this->formatMessage($message), $this->mergeContext($additionalData));
    }

    /**
     * Log task save errors specifically
     */
    public function taskSaveError(string $taskIdentifier, string $errorMessage, array $taskData = []): void
    {
        $this->error("Task save failed for {$taskIdentifier}: {$errorMessage}", [
            'task_identifier' => $taskIdentifier,
            'task_data' => $taskData,
            'error_type' => 'task_save_failure'
        ]);
    }

    /**
     * Log file processing start
     */
    public function fileProcessingStart(string $filename, array $metadata = []): void
    {
        $this->info("Starting file processing: {$filename}", array_merge([
            'filename' => $filename,
            'event' => 'file_processing_start'
        ], $metadata));
    }

    /**
     * Log file processing completion
     */
    public function fileProcessingComplete(string $filename, array $results = []): void
    {
        $this->info("Completed file processing: {$filename}", array_merge([
            'filename' => $filename,
            'event' => 'file_processing_complete'
        ], $results));
    }

    /**
     * Log batch processing events
     */
    public function batchEvent(string $event, array $data = []): void
    {
        $this->info("Batch {$event}", array_merge([
            'batch_event' => $event,
            'timestamp' => now()->toISOString()
        ], $data));
    }

    /**
     * Format message with context prefix
     */
    private function formatMessage(string $message): string
    {
        return "[{$this->context}] {$message}";
    }

    /**
     * Merge context data with additional data
     */
    private function mergeContext(array $additionalData): array
    {
        return array_merge($this->contextData, $additionalData, [
            'context' => $this->context,
            'timestamp' => now()->toISOString()
        ]);
    }
}
