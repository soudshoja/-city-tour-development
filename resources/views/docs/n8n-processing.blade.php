<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>N8n Document Processing - Developer Documentation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

        body {
            font-family: 'Inter', sans-serif;
        }

        .code-block {
            position: relative;
        }

        .copy-button {
            position: absolute;
            top: 8px;
            right: 8px;
            opacity: 0.7;
            transition: opacity 0.2s;
        }

        .copy-button:hover {
            opacity: 1;
        }

        .method-badge {
            font-size: 0.7rem;
            padding: 0.15rem 0.5rem;
            border-radius: 0.25rem;
            font-weight: 600;
            letter-spacing: 0.025em;
        }

        .http-post {
            background-color: #3B82F6;
            color: white;
        }

        .http-get {
            background-color: #10B981;
            color: white;
        }

        .http-put {
            background-color: #F59E0B;
            color: white;
        }

        .http-delete {
            background-color: #EF4444;
            color: white;
        }

        .sidebar-link {
            transition: all 0.2s;
        }

        .sidebar-link:hover {
            transform: translateX(4px);
        }

        pre {
            scrollbar-width: thin;
            scrollbar-color: rgba(156, 163, 175, 0.5) transparent;
        }

        pre::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        pre::-webkit-scrollbar-track {
            background: transparent;
        }

        pre::-webkit-scrollbar-thumb {
            background-color: rgba(156, 163, 175, 0.5);
            border-radius: 4px;
        }

        .doc-type-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .badge-air {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .badge-pdf {
            background-color: #fce7f3;
            color: #9f1239;
        }

        .badge-image {
            background-color: #d1fae5;
            color: #065f46;
        }

        .badge-email {
            background-color: #fef3c7;
            color: #92400e;
        }
    </style>
</head>

<body class="bg-gray-50 text-gray-900 dark:bg-gray-900 dark:text-gray-100 transition-colors duration-200">
    <!-- Header -->
    <header class="bg-white dark:bg-gray-800 shadow-sm sticky top-0 z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-primary-600 dark:text-primary-400" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z" />
                </svg>
                <h1 class="text-xl font-bold text-gray-900 dark:text-white">N8n Document Processing</h1>
            </div>
            <div class="flex items-center space-x-4">
                <button id="darkModeToggle" class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 dark:hidden" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z" />
                    </svg>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden dark:block text-yellow-300" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd" />
                    </svg>
                </button>
                <a href="{{ route('docs.developer-documentation') }}" class="text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300">API Docs</a>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="lg:grid lg:grid-cols-12 lg:gap-8">
            <!-- Sidebar -->
            <div class="hidden lg:block lg:col-span-3">
                <nav class="sticky top-24 space-y-1 p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                    <div class="pb-2 mb-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Contents</h2>
                    </div>
                    <a href="#overview" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md bg-gray-100 dark:bg-gray-700 text-primary-600 dark:text-primary-400">
                        <i class="fas fa-info-circle w-4 mr-3 text-primary-600 dark:text-primary-400"></i>
                        Overview
                    </a>
                    <a href="#endpoints" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-code w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        API Endpoints
                    </a>
                    <a href="#document-types" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 pl-10">
                        Document Types
                    </a>
                    <a href="#security" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-shield-alt w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Security
                    </a>
                    <a href="#error-handling" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-exclamation-triangle w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Error Handling
                    </a>
                    <a href="#workflow" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-project-diagram w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        N8n Workflow
                    </a>
                    <a href="#manual-intervention" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-hands-helping w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Manual Intervention
                    </a>
                    <a href="#analytics" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-chart-line w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Error Analytics
                    </a>
                    <a href="#configuration" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-cog w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Configuration
                    </a>
                    <a href="#testing" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-vial w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Testing
                    </a>
                </nav>
            </div>

            <!-- Main content -->
            <div class="mt-8 lg:mt-0 lg:col-span-9">
                <div class="prose prose-blue max-w-none dark:prose-invert">
                    <div class="bg-gradient-to-r from-primary-600 to-blue-600 rounded-xl shadow-lg p-8 mb-12 text-white">
                        <h1 class="text-4xl font-extrabold mb-4">N8n Document Processing</h1>
                        <p class="text-lg opacity-90 max-w-3xl">
                            Complete guide to the Laravel ↔ N8n document processing integration. Learn how documents flow from Laravel to N8n for extraction, validation, and callback with structured results.
                        </p>
                        <div class="mt-6 flex flex-wrap gap-3">
                            <a href="#endpoints" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-primary-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-primary-700 focus:ring-white transition-colors">
                                Get Started
                            </a>
                            <a href="#workflow" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-primary-800 bg-opacity-60 hover:bg-opacity-70 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-primary-700 focus:ring-white transition-colors">
                                View Workflow
                            </a>
                        </div>
                    </div>

                    <section id="overview" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">Overview</h2>
                        <p class="text-lg text-gray-700 dark:text-gray-300 mb-4">
                            The N8n Document Processing system enables automated extraction of travel bookings from various document formats (AIR files, PDFs, images, emails). Documents are submitted to N8n workflows, processed asynchronously, and results are sent back to Laravel via webhook callback.
                        </p>

                        <div class="bg-blue-50 dark:bg-blue-900/30 border-l-4 border-blue-400 p-4 rounded-md mb-6">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-info-circle text-blue-400"></i>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-semibold text-blue-800 dark:text-blue-200 mb-2">Architecture Flow</h3>
                                    <p class="text-sm text-blue-700 dark:text-blue-300">
                                        Laravel → N8n Webhook (202 Accepted) → Async Processing → Laravel Callback (200 OK)
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-4 rounded-md">
                                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2">Key Features</h3>
                                <ul class="text-sm text-gray-700 dark:text-gray-300 space-y-1">
                                    <li>✓ 4 document type support (AIR, PDF, Image, Email)</li>
                                    <li>✓ HMAC-SHA256 security</li>
                                    <li>✓ Supplier-specific routing</li>
                                    <li>✓ Async processing with callbacks</li>
                                    <li>✓ Automatic retry on transient errors</li>
                                </ul>
                            </div>
                            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-4 rounded-md">
                                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2">Processing Methods</h3>
                                <ul class="text-sm text-gray-700 dark:text-gray-300 space-y-1">
                                    <li><span class="doc-type-badge badge-air">AIR Parser</span> Amadeus GDS format</li>
                                    <li><span class="doc-type-badge badge-pdf">Tika OCR</span> PDF extraction</li>
                                    <li><span class="doc-type-badge badge-image">Gutenberg</span> Image OCR</li>
                                    <li><span class="doc-type-badge badge-email">Gmail API</span> Email parsing</li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    <section id="endpoints" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-6">API Endpoints</h2>

                        <!-- Queue Document Endpoint -->
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-8">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Queue Document for Processing</h3>
                                    <span class="method-badge http-post">POST</span>
                                </div>
                                <code class="text-sm text-gray-600 dark:text-gray-400">/api/documents/process</code>
                            </div>
                            <div class="px-6 py-4">
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Submit a document to N8n for asynchronous extraction. Returns immediately with 202 Accepted and document_id for tracking.</p>

                                <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">Request Headers</h4>
                                <div class="code-block mb-4">
                                    <pre class="bg-gray-100 dark:bg-gray-800 p-3 rounded text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>Content-Type: application/json
X-Signature: sha256={hmac_signature}
X-Timestamp: 1707588000000
X-Request-ID: {uuid}</code></pre>
                                    <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                        <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                                    </button>
                                </div>

                                <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">Request Body</h4>
                                <div class="code-block mb-4">
                                    <pre class="bg-gray-100 dark:bg-gray-800 p-3 rounded text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>{
  "company_id": 42,
  "supplier_id": 5,
  "document_id": "550e8400-e29b-41d4-a716-446655440000",
  "document_type": "air",
  "file_path": "test_company/amadeus/files_unprocessed/ticket.air",
  "file_size_bytes": 2048,
  "file_hash": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
  "callback_url": "https://yourapp.com/api/webhooks/n8n/extraction",
  "timestamp": 1707588000000
}</code></pre>
                                    <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                        <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                                    </button>
                                </div>

                                <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">Response (202 Accepted)</h4>
                                <div class="code-block">
                                    <pre class="bg-gray-100 dark:bg-gray-800 p-3 rounded text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>{
  "status": "accepted",
  "execution_id": "507f1f77bcf86cd799439011",
  "document_id": "550e8400-e29b-41d4-a716-446655440000",
  "message": "Document queued for processing",
  "estimated_processing_time_ms": 15000
}</code></pre>
                                    <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                        <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- N8n Callback Endpoint -->
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-8">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">N8n Extraction Callback</h3>
                                    <span class="method-badge http-post">POST</span>
                                </div>
                                <code class="text-sm text-gray-600 dark:text-gray-400">/api/webhooks/n8n/extraction</code>
                            </div>
                            <div class="px-6 py-4">
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">N8n calls this endpoint after processing with extraction results or error details. Must validate HMAC signature.</p>

                                <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">Success Callback</h4>
                                <div class="code-block mb-4">
                                    <pre class="bg-gray-100 dark:bg-gray-800 p-3 rounded text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>{
  "status": "success",
  "execution_id": "507f1f77bcf86cd799439011",
  "document_id": "550e8400-e29b-41d4-a716-446655440000",
  "company_id": 42,
  "supplier_id": 5,
  "timestamp": 1707588015000,
  "extracted_data": {
    "task_type": "flight",
    "passengers": [{
      "title": "MR",
      "first_name": "JOHN",
      "last_name": "DOE"
    }],
    "segments": [{
      "departure_city": "LHR",
      "arrival_city": "MUC",
      "airline_code": "LH",
      "flight_number": "700"
    }],
    "pricing": {
      "currency": "KWD",
      "base_fare": 250.00,
      "total": 325.00
    }
  },
  "extraction_metadata": {
    "confidence": 95,
    "extraction_method": "air_parser",
    "processing_time_ms": 250
  }
}</code></pre>
                                    <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                        <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                                    </button>
                                </div>

                                <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">Error Callback</h4>
                                <div class="code-block">
                                    <pre class="bg-gray-100 dark:bg-gray-800 p-3 rounded text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>{
  "status": "error",
  "execution_id": "507f1f77bcf86cd799439012",
  "document_id": "550e8400-e29b-41d4-a716-446655440001",
  "company_id": 42,
  "supplier_id": 5,
  "timestamp": 1707588020000,
  "error": {
    "type": "invalid_format",
    "message": "File does not match expected AIR file format",
    "details": "Expected header 'AIR-BLK1' not found"
  },
  "error_metadata": {
    "retry_count": 0,
    "should_retry": false,
    "processing_time_ms": 150
  }
}</code></pre>
                                    <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                        <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="document-types" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">Document Types</h2>
                        <p class="text-lg text-gray-700 dark:text-gray-300 mb-6">
                            The system supports four document formats, each routed to specialized extraction pipelines in N8n.
                        </p>

                        <div class="space-y-4">
                            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                <div class="flex items-center mb-2">
                                    <span class="doc-type-badge badge-air mr-3">AIR</span>
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">AIR Files</h3>
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    Amadeus GDS format files containing flight bookings. Uses specialized AIR parser for fast, accurate extraction of PNR, segments, passenger details, and pricing.
                                </p>
                            </div>

                            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                <div class="flex items-center mb-2">
                                    <span class="doc-type-badge badge-pdf mr-3">PDF</span>
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">PDF Documents</h3>
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    Travel confirmations, invoices, and itineraries in PDF format. Processed using Apache Tika for text extraction, followed by structured parsing.
                                </p>
                            </div>

                            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                <div class="flex items-center mb-2">
                                    <span class="doc-type-badge badge-image mr-3">IMAGE</span>
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Image Documents</h3>
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    Scanned documents or screenshots (JPG, PNG). Uses Gutenberg OCR service for text recognition before structured extraction.
                                </p>
                            </div>

                            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                <div class="flex items-center mb-2">
                                    <span class="doc-type-badge badge-email mr-3">EMAIL</span>
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Email Messages</h3>
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    Booking confirmation emails from suppliers. Parses email body, extracts attachments, and processes using supplier-specific rules via Gmail API integration.
                                </p>
                            </div>
                        </div>
                    </section>

                    <section id="security" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">Security</h2>

                        <div class="bg-yellow-50 dark:bg-yellow-900 border-l-4 border-yellow-400 p-4 rounded-md mb-6">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-shield-alt text-yellow-400"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-yellow-700 dark:text-yellow-200">
                                        <strong>Important:</strong> All webhook requests and callbacks use HMAC-SHA256 signing to ensure authenticity and prevent tampering. Always validate signatures before processing.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <h3 class="text-2xl font-semibold text-gray-900 dark:text-white mb-4">HMAC Signature Generation</h3>
                        <p class="text-gray-700 dark:text-gray-300 mb-4">
                            Both outbound (Laravel → N8n) and inbound (N8n → Laravel) requests are signed using HMAC-SHA256 with a company-specific secret.
                        </p>

                        <div class="code-block mb-6">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>// Signing algorithm
signature = HMAC-SHA256(company_secret, request_body)

// Headers
X-Signature: sha256={signature}
X-Timestamp: {unix_timestamp_milliseconds}</code></pre>
                            <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>

                        <h3 class="text-2xl font-semibold text-gray-900 dark:text-white mb-4">Key Management</h3>
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Operation</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Responsibility</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Frequency</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">Generate secret</td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Laravel (on company creation)</td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Once per company</td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">Store in N8n</td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Admin (manual or API)</td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Per-supplier setup</td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">Rotate secret</td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Laravel (via admin UI)</td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Quarterly or on-demand</td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">Validate signature</td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">N8n + Laravel</td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Every request/callback</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <h3 class="text-2xl font-semibold text-gray-900 dark:text-white mt-8 mb-4">Additional Security Measures</h3>
                        <ul class="list-disc list-inside space-y-2 text-gray-700 dark:text-gray-300 mb-4">
                            <li><strong>Timestamp validation:</strong> Reject requests older than 5 minutes to prevent replay attacks</li>
                            <li><strong>Rate limiting:</strong> 100 requests/minute per supplier to prevent abuse</li>
                            <li><strong>File validation:</strong> Verify file size (max 50MB) and hash before processing</li>
                            <li><strong>HTTPS only:</strong> All webhook endpoints require TLS 1.2 or higher</li>
                        </ul>
                    </section>

                    <section id="error-handling" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">Error Handling</h2>
                        <p class="text-lg text-gray-700 dark:text-gray-300 mb-6">
                            Errors are classified into three categories: Transient (retriable), Non-Transient (manual intervention), and System (critical infrastructure issues).
                        </p>

                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Error Code</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Category</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Description</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Recovery</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                        <tr>
                                            <td class="px-6 py-4 font-mono text-gray-900 dark:text-white">ERR_TIMEOUT</td>
                                            <td class="px-6 py-4"><span class="px-2 py-1 text-xs rounded bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Transient</span></td>
                                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400">Processing exceeds timeout</td>
                                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400">Auto-retry</td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 font-mono text-gray-900 dark:text-white">ERR_RATE_LIMIT</td>
                                            <td class="px-6 py-4"><span class="px-2 py-1 text-xs rounded bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Transient</span></td>
                                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400">API rate limit hit</td>
                                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400">Auto-retry with backoff</td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 font-mono text-gray-900 dark:text-white">ERR_PARSE_FAILURE</td>
                                            <td class="px-6 py-4"><span class="px-2 py-1 text-xs rounded bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Non-Transient</span></td>
                                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400">Invalid JSON/format</td>
                                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400">Manual review</td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 font-mono text-gray-900 dark:text-white">ERR_FILE_NOT_FOUND</td>
                                            <td class="px-6 py-4"><span class="px-2 py-1 text-xs rounded bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Non-Transient</span></td>
                                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400">File path doesn't exist</td>
                                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400">Manual review</td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 font-mono text-gray-900 dark:text-white">ERR_HMAC_INVALID</td>
                                            <td class="px-6 py-4"><span class="px-2 py-1 text-xs rounded bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Non-Transient</span></td>
                                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400">Signature verification failed</td>
                                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400">Manual review</td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 font-mono text-gray-900 dark:text-white">ERR_N8N_UNAVAILABLE</td>
                                            <td class="px-6 py-4"><span class="px-2 py-1 text-xs rounded bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">System</span></td>
                                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400">N8n service offline</td>
                                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400">Escalate to ops</td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 font-mono text-gray-900 dark:text-white">ERR_DATABASE_ERROR</td>
                                            <td class="px-6 py-4"><span class="px-2 py-1 text-xs rounded bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">System</span></td>
                                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400">Database error</td>
                                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400">Escalate to infrastructure</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="bg-blue-50 dark:bg-blue-900/30 border-l-4 border-blue-400 p-4 rounded-md">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-info-circle text-blue-400"></i>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-semibold text-blue-800 dark:text-blue-200 mb-2">Total Error Codes</h3>
                                    <p class="text-sm text-blue-700 dark:text-blue-300">
                                        18 unique error codes across 3 categories: 5 Transient, 7 Non-Transient, 5 System (critical).
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="workflow" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">N8n Workflow Structure</h2>
                        <p class="text-lg text-gray-700 dark:text-gray-300 mb-6">
                            The N8n workflow consists of 18 nodes organized into trigger, routing, extraction, and callback stages.
                        </p>

                        <div class="bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6 mb-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Workflow Components</h3>
                            <div class="space-y-3 text-sm">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 h-6 w-6 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center text-primary-600 dark:text-primary-400 mr-3">1</div>
                                    <div>
                                        <strong class="text-gray-900 dark:text-white">Webhook Trigger:</strong>
                                        <span class="text-gray-600 dark:text-gray-400"> Receives POST request from Laravel with document metadata</span>
                                    </div>
                                </div>
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 h-6 w-6 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center text-primary-600 dark:text-primary-400 mr-3">2</div>
                                    <div>
                                        <strong class="text-gray-900 dark:text-white">Signature Validation:</strong>
                                        <span class="text-gray-600 dark:text-gray-400"> Code node verifies HMAC-SHA256 signature</span>
                                    </div>
                                </div>
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 h-6 w-6 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center text-primary-600 dark:text-primary-400 mr-3">3</div>
                                    <div>
                                        <strong class="text-gray-900 dark:text-white">Switch Router:</strong>
                                        <span class="text-gray-600 dark:text-gray-400"> Routes to supplier-specific branch based on supplier_id</span>
                                    </div>
                                </div>
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 h-6 w-6 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center text-primary-600 dark:text-primary-400 mr-3">4</div>
                                    <div>
                                        <strong class="text-gray-900 dark:text-white">Extraction Pipeline:</strong>
                                        <span class="text-gray-600 dark:text-gray-400"> Supplier-specific nodes process document based on type</span>
                                    </div>
                                </div>
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 h-6 w-6 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center text-primary-600 dark:text-primary-400 mr-3">5</div>
                                    <div>
                                        <strong class="text-gray-900 dark:text-white">Data Transformation:</strong>
                                        <span class="text-gray-600 dark:text-gray-400"> Code node normalizes extracted data to standard schema</span>
                                    </div>
                                </div>
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 h-6 w-6 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center text-primary-600 dark:text-primary-400 mr-3">6</div>
                                    <div>
                                        <strong class="text-gray-900 dark:text-white">Callback:</strong>
                                        <span class="text-gray-600 dark:text-gray-400"> HTTP Request node sends results to Laravel callback URL</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <h3 class="text-2xl font-semibold text-gray-900 dark:text-white mb-4">Supplier Routing</h3>
                        <p class="text-gray-700 dark:text-gray-300 mb-4">
                            The Switch node routes documents to supplier-specific branches. Each supplier has unique extraction logic tailored to their document format.
                        </p>

                        <div class="code-block">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>// Switch routing logic (N8n expression)
const routes = {
  "jazeera_airways": 1,
  "flydubai": 2,
  "eta_uk": 3,
  "the_skyrooms": 5,
  "air_arabia": 6,
  "indigo": 7,
  "cham_wings": 8,
  "vfs_global": 10,
  "emirates_ndc": 11,
  "oman_ndc": 12
};

return routes[$json.supplier_id] || 0; // 0 = unmapped</code></pre>
                            <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>
                    </section>

                    <section id="manual-intervention" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">Manual Intervention Dashboard</h2>
                        <p class="text-lg text-gray-700 dark:text-gray-300 mb-6">
                            Failed documents are flagged for manual review. Developers can investigate, retry, or escalate via the dashboard.
                        </p>

                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6 mb-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Dashboard Access</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                Navigate to: <code class="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">{{ url('/admin/manual-intervention') }}</code>
                            </p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Available to users with <strong>admin</strong> role. Shows all documents with <code>processing_status = 'error'</code> and <code>manual_review_required = true</code>.
                            </p>
                        </div>

                        <h3 class="text-2xl font-semibold text-gray-900 dark:text-white mb-4">Available Actions</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700 rounded-lg p-4">
                                <h4 class="text-sm font-semibold text-blue-800 dark:text-blue-200 mb-2">
                                    <i class="fas fa-redo mr-2"></i>Retry
                                </h4>
                                <p class="text-sm text-blue-700 dark:text-blue-300">
                                    Re-queue document for N8n processing. Use for transient errors after underlying issue is resolved.
                                </p>
                            </div>
                            <div class="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 rounded-lg p-4">
                                <h4 class="text-sm font-semibold text-green-800 dark:text-green-200 mb-2">
                                    <i class="fas fa-check mr-2"></i>Resolve
                                </h4>
                                <p class="text-sm text-green-700 dark:text-green-300">
                                    Mark as resolved with notes. Use when error is understood and no further action needed.
                                </p>
                            </div>
                            <div class="bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-700 rounded-lg p-4">
                                <h4 class="text-sm font-semibold text-yellow-800 dark:text-yellow-200 mb-2">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>Escalate
                                </h4>
                                <p class="text-sm text-yellow-700 dark:text-yellow-300">
                                    Create ticket for engineering team. Use for N8n workflow bugs or infrastructure issues.
                                </p>
                            </div>
                            <div class="bg-purple-50 dark:bg-purple-900/30 border border-purple-200 dark:border-purple-700 rounded-lg p-4">
                                <h4 class="text-sm font-semibold text-purple-800 dark:text-purple-200 mb-2">
                                    <i class="fas fa-search mr-2"></i>View Details
                                </h4>
                                <p class="text-sm text-purple-700 dark:text-purple-300">
                                    Inspect full error context, N8n logs, and execution metadata for investigation.
                                </p>
                            </div>
                        </div>

                        <h3 class="text-2xl font-semibold text-gray-900 dark:text-white mb-4">Filtering & Search</h3>
                        <ul class="list-disc list-inside space-y-2 text-gray-700 dark:text-gray-300">
                            <li>Filter by <strong>supplier_id</strong> to focus on specific supplier issues</li>
                            <li>Filter by <strong>error_code</strong> to group similar failures</li>
                            <li>Filter by <strong>date range</strong> to investigate time-based patterns</li>
                            <li>Sort by <strong>error_last_seen_at</strong> to prioritize recent failures</li>
                        </ul>
                    </section>

                    <section id="analytics" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">Error Analytics</h2>
                        <p class="text-lg text-gray-700 dark:text-gray-300 mb-6">
                            Monitor processing health with real-time metrics and trend analysis.
                        </p>

                        <h3 class="text-2xl font-semibold text-gray-900 dark:text-white mb-4">Key Metrics</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                <div class="text-3xl font-bold text-primary-600 dark:text-primary-400 mb-2">96.5%</div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Success Rate (24h)</div>
                            </div>
                            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                <div class="text-3xl font-bold text-green-600 dark:text-green-400 mb-2">250ms</div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Avg Processing Time</div>
                            </div>
                            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                <div class="text-3xl font-bold text-yellow-600 dark:text-yellow-400 mb-2">23</div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Pending Manual Review</div>
                            </div>
                        </div>

                        <h3 class="text-2xl font-semibold text-gray-900 dark:text-white mb-4">Error Distribution</h3>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <div class="space-y-3">
                                <div>
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="text-gray-700 dark:text-gray-300">Transient Errors</span>
                                        <span class="text-gray-600 dark:text-gray-400">35%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                        <div class="bg-green-500 h-2 rounded-full" style="width: 35%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="text-gray-700 dark:text-gray-300">Non-Transient Errors</span>
                                        <span class="text-gray-600 dark:text-gray-400">58%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                        <div class="bg-yellow-500 h-2 rounded-full" style="width: 58%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="text-gray-700 dark:text-gray-300">System Errors</span>
                                        <span class="text-gray-600 dark:text-gray-400">7%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                        <div class="bg-red-500 h-2 rounded-full" style="width: 7%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <h3 class="text-2xl font-semibold text-gray-900 dark:text-white mt-8 mb-4">Alert Thresholds</h3>
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Alert</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Condition</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Severity</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Notification</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        <tr>
                                            <td class="px-6 py-4 text-gray-900 dark:text-white">High Error Rate</td>
                                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400">&gt;5% in 1 hour</td>
                                            <td class="px-6 py-4"><span class="px-2 py-1 text-xs rounded bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">WARNING</span></td>
                                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400">Slack #dev-alerts</td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 text-gray-900 dark:text-white">System Error</td>
                                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400">Any system error</td>
                                            <td class="px-6 py-4"><span class="px-2 py-1 text-xs rounded bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">CRITICAL</span></td>
                                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400">Slack + PagerDuty</td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 text-gray-900 dark:text-white">Manual Queue</td>
                                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400">&gt;5 in 1 hour</td>
                                            <td class="px-6 py-4"><span class="px-2 py-1 text-xs rounded bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">INFO</span></td>
                                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400">Daily report</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    <section id="configuration" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">Configuration</h2>

                        <h3 class="text-2xl font-semibold text-gray-900 dark:text-white mb-4">Environment Variables</h3>
                        <div class="code-block mb-6">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code># Laravel .env
N8N_WEBHOOK_URL=https://n8n.example.com/webhook/document-processing
N8N_WEBHOOK_SECRET=your_long_random_secret_key_min_32_chars
N8N_TIMEOUT_SECONDS=30
N8N_MAX_RETRIES=3

# File Storage
DOCUMENT_STORAGE_PATH=storage/documents
DOCUMENT_MAX_SIZE_MB=50

# Callback
WEBHOOK_CALLBACK_URL=https://yourapp.com/api/webhooks/n8n/extraction
WEBHOOK_SIGNATURE_TOLERANCE_MINUTES=5</code></pre>
                            <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>

                        <h3 class="text-2xl font-semibold text-gray-900 dark:text-white mb-4">config/webhook.php</h3>
                        <div class="code-block mb-6">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>return [
    'n8n' => [
        'url' => env('N8N_WEBHOOK_URL'),
        'secret' => env('N8N_WEBHOOK_SECRET'),
        'timeout' => env('N8N_TIMEOUT_SECONDS', 30),
        'max_retries' => env('N8N_MAX_RETRIES', 3),
    ],

    'callback' => [
        'url' => env('WEBHOOK_CALLBACK_URL'),
        'signature_tolerance_minutes' => env('WEBHOOK_SIGNATURE_TOLERANCE_MINUTES', 5),
    ],

    'documents' => [
        'storage_path' => env('DOCUMENT_STORAGE_PATH', 'storage/documents'),
        'max_size_mb' => env('DOCUMENT_MAX_SIZE_MB', 50),
        'allowed_types' => ['air', 'pdf', 'image', 'email'],
    ],
];</code></pre>
                            <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>

                        <h3 class="text-2xl font-semibold text-gray-900 dark:text-white mb-4">Docker Services</h3>
                        <p class="text-gray-700 dark:text-gray-300 mb-4">
                            N8n workflows use external services for document processing:
                        </p>
                        <ul class="list-disc list-inside space-y-2 text-gray-700 dark:text-gray-300">
                            <li><strong>Apache Tika:</strong> PDF text extraction (port 9998)</li>
                            <li><strong>Gutenberg OCR:</strong> Image OCR processing (port 3000)</li>
                            <li><strong>Gotenberg:</strong> HTML to PDF conversion (port 3000)</li>
                        </ul>
                    </section>

                    <section id="testing" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">Testing</h2>

                        <h3 class="text-2xl font-semibold text-gray-900 dark:text-white mb-4">Running Tests</h3>
                        <div class="code-block mb-6">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code># Run all webhook tests
php artisan test --filter=WebhookTest

# Test specific scenarios
php artisan test --filter=testDocumentQueueSuccess
php artisan test --filter=testHMACValidation
php artisan test --filter=testErrorCallback</code></pre>
                            <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>

                        <h3 class="text-2xl font-semibold text-gray-900 dark:text-white mb-4">Test Categories</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Unit Tests</h4>
                                <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                                    <li>• HMAC signature generation</li>
                                    <li>• Payload validation</li>
                                    <li>• Error code mapping</li>
                                    <li>• Timestamp validation</li>
                                </ul>
                            </div>
                            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Integration Tests</h4>
                                <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                                    <li>• Laravel → N8n webhook flow</li>
                                    <li>• N8n → Laravel callback</li>
                                    <li>• Error handling pipeline</li>
                                    <li>• Retry logic</li>
                                </ul>
                            </div>
                        </div>

                        <h3 class="text-2xl font-semibold text-gray-900 dark:text-white mt-6 mb-4">Sample Test Payloads</h3>
                        <div class="code-block">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>// Test AIR file processing
{
  "company_id": 1,
  "supplier_id": 5,
  "document_id": "test-550e8400",
  "document_type": "air",
  "file_path": "tests/fixtures/sample_ticket.air",
  "file_size_bytes": 2048,
  "file_hash": "abc123...",
  "callback_url": "http://localhost/api/webhooks/test",
  "timestamp": 1707588000000
}

// Expected callback response
{
  "status": "success",
  "document_id": "test-550e8400",
  "extracted_data": {
    "task_type": "flight",
    "passengers": [...],
    "segments": [...]
  }
}</code></pre>
                            <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>
                    </section>

                    <div class="border-t border-gray-200 dark:border-gray-700 pt-8 mt-12">
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Need more help?</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Contact the development team for assistance with N8n integration and document processing workflows.
                                </p>
                            </div>
                            <div class="mt-4 md:mt-0 flex space-x-3">
                                <a href="{{ route('admin.manual-intervention.index') }}" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                    View Dashboard
                                </a>
                                <a href="{{ route('docs.developer-documentation') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                    API Docs
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 mt-12">
        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <p class="text-center text-sm text-gray-500 dark:text-gray-400">
                &copy; {{ date('Y') }} {{ config('app.name') }}. N8n Document Processing Documentation.
            </p>
        </div>
    </footer>

    <script>
        // Dark mode toggle
        const darkModeToggle = document.getElementById('darkModeToggle');

        const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)');
        const storedTheme = localStorage.getItem('theme');

        if (storedTheme === 'dark' || (!storedTheme && prefersDarkScheme.matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }

        darkModeToggle.addEventListener('click', () => {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('theme', 'light');
            } else {
                document.documentElement.classList.add('dark');
                localStorage.setItem('theme', 'dark');
            }
        });

        // Copy code function
        function copyCode(button) {
            const pre = button.parentElement.querySelector('pre');
            const code = pre.textContent;

            navigator.clipboard.writeText(code).then(() => {
                const originalHTML = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check text-green-600 dark:text-green-400"></i>';

                setTimeout(() => {
                    button.innerHTML = originalHTML;
                }, 2000);
            });
        }

        // Smooth scroll for anchor links and update active navigation
        const navLinks = document.querySelectorAll('.sidebar-link');

        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });

                    // Update active state
                    updateActiveNavLink(this.getAttribute('href'));
                }
            });
        });

        // Update active nav link function
        function updateActiveNavLink(hash) {
            navLinks.forEach(link => {
                link.classList.remove('bg-gray-100', 'dark:bg-gray-700', 'text-primary-600', 'dark:text-primary-400');
                link.classList.add('text-gray-900', 'dark:text-white');

                const icon = link.querySelector('i');
                if (icon) {
                    icon.classList.remove('text-primary-600', 'dark:text-primary-400');
                    icon.classList.add('text-gray-500', 'dark:text-gray-400');
                }
            });

            const activeLink = document.querySelector(`.sidebar-link[href="${hash}"]`);
            if (activeLink) {
                activeLink.classList.add('bg-gray-100', 'dark:bg-gray-700', 'text-primary-600', 'dark:text-primary-400');
                activeLink.classList.remove('text-gray-900', 'dark:text-white');

                const icon = activeLink.querySelector('i');
                if (icon) {
                    icon.classList.add('text-primary-600', 'dark:text-primary-400');
                    icon.classList.remove('text-gray-500', 'dark:text-gray-400');
                }
            }
        }

        // Update active nav on scroll
        const sections = document.querySelectorAll('section[id]');
        const observerOptions = {
            root: null,
            rootMargin: '-100px 0px -66%',
            threshold: 0
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    updateActiveNavLink(`#${entry.target.id}`);
                }
            });
        }, observerOptions);

        sections.forEach(section => {
            observer.observe(section);
        });
    </script>
</body>

</html>
