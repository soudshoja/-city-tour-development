<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Webhook Security Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for HMAC-SHA256 webhook signing and verification.
    | Used for secure communication between Laravel and N8n.
    |
    */

    // HMAC Settings
    'hmac' => [
        'algorithm' => env('WEBHOOK_HMAC_ALGORITHM', 'sha256'),
        'timestamp_tolerance_seconds' => env('WEBHOOK_TIMESTAMP_TOLERANCE', 300), // 5 minutes
        'signature_header' => 'X-Signature-SHA256',
        'timestamp_header' => 'X-Signature-Timestamp',
    ],

    // Rate Limiting
    'rate_limiting' => [
        'enabled' => env('WEBHOOK_RATE_LIMITING_ENABLED', true),
        'global_rate_limit' => env('WEBHOOK_GLOBAL_RATE_LIMIT', 100), // requests per minute
        'per_client_default' => env('WEBHOOK_PER_CLIENT_RATE_LIMIT', 60),
    ],

    // Timeout Settings (for outbound webhooks to N8n)
    'timeouts' => [
        'connection_timeout' => env('WEBHOOK_CONNECTION_TIMEOUT', 10), // seconds
        'request_timeout' => env('WEBHOOK_REQUEST_TIMEOUT', 30), // seconds
        'retry_attempts' => env('WEBHOOK_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('WEBHOOK_RETRY_DELAY', 1000), // milliseconds
    ],

    // File Validation
    'file_validation' => [
        'enabled' => env('WEBHOOK_FILE_VALIDATION_ENABLED', true),
        'max_file_size' => env('WEBHOOK_MAX_FILE_SIZE', 10485760), // 10MB in bytes
        'allowed_mime_types' => [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/gif',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'message/rfc822', // email
        ],
        'allowed_extensions' => [
            'pdf', 'jpg', 'jpeg', 'png', 'gif',
            'doc', 'docx', 'xls', 'xlsx', 'eml',
        ],
    ],

    // N8n Integration
    'n8n' => [
        'base_url' => env('N8N_BASE_URL', 'http://localhost:5678'),
        'webhook_path' => env('N8N_WEBHOOK_PATH', '/webhook/document-processing'),
        'api_key' => env('N8N_API_KEY', ''),
    ],

    // Error Logging
    'error_logging' => [
        'enabled' => env('WEBHOOK_ERROR_LOGGING_ENABLED', true),
        'log_channel' => env('WEBHOOK_LOG_CHANNEL', 'stack'),
        'log_level' => env('WEBHOOK_LOG_LEVEL', 'error'),
        'include_payload' => env('WEBHOOK_LOG_INCLUDE_PAYLOAD', false),
        'include_stack_trace' => env('WEBHOOK_LOG_INCLUDE_STACK_TRACE', true),
    ],

    // Audit Log Retention
    'audit' => [
        'retention_days' => env('WEBHOOK_AUDIT_RETENTION_DAYS', 90),
        'cleanup_enabled' => env('WEBHOOK_AUDIT_CLEANUP_ENABLED', true),
    ],

    // Deduplication
    'deduplication' => [
        'enabled' => env('WEBHOOK_DEDUPLICATION_ENABLED', true),
        'cache_ttl' => env('WEBHOOK_DEDUPLICATION_TTL', 3600), // 1 hour in seconds
    ],

    // Error Alerting (ERR-05)
    'alerting' => [
        'enabled' => env('WEBHOOK_ALERTING_ENABLED', true),
        'error_rate_threshold' => env('WEBHOOK_ERROR_RATE_THRESHOLD', 10), // 10% per hour
        'consecutive_failures' => env('WEBHOOK_CONSECUTIVE_FAILURES', 5),
        'alert_cooldown_minutes' => env('WEBHOOK_ALERT_COOLDOWN', 30), // 30 minutes
        'check_interval_minutes' => env('WEBHOOK_ALERT_CHECK_INTERVAL', 5), // 5 minutes
    ],

];
