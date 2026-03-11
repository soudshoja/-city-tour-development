<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>N8n Document Processing - Complete Documentation</title>
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

        .sidebar-link {
            transition: all 0.2s;
        }

        .sidebar-link:hover {
            transform: translateX(4px);
        }

        .sidebar-link.active {
            background-color: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
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

        section[id] {
            scroll-margin-top: 100px;
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
                <h1 class="text-xl font-bold text-gray-900 dark:text-white">N8n Document Processing - Complete Documentation</h1>
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
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="lg:grid lg:grid-cols-12 lg:gap-8">
            <!-- Sidebar -->
            <div class="hidden lg:block lg:col-span-3">
                <nav class="sticky top-24 space-y-1 p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm max-h-[calc(100vh-120px)] overflow-y-auto">
                    <div class="pb-2 mb-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Contents</h2>
                    </div>
                    <a href="#overview" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-info-circle w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Overview
                    </a>
                    <a href="#architecture" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-project-diagram w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Architecture
                    </a>
                    <a href="#phase1" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-foundation w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Phase 1: Foundation
                    </a>
                    <a href="#phase2" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-plug w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Phase 2: Integration
                    </a>
                    <a href="#phase3" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-shield-alt w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Phase 3: Security
                    </a>
                    <a href="#phase4" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-exclamation-triangle w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Phase 4: Error Handling
                    </a>
                    <a href="#phase5" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-vial w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Phase 5: Testing
                    </a>
                    <a href="#configuration" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-cog w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Configuration
                    </a>
                    <a href="#database" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-database w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Database Schema
                    </a>
                    <a href="#deployment" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-rocket w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Deployment
                    </a>
                    <a href="#troubleshooting" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-wrench w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Troubleshooting
                    </a>
                </nav>
            </div>

            <!-- Main content -->
            <div class="mt-8 lg:mt-0 lg:col-span-9">
                <div class="prose prose-blue max-w-none dark:prose-invert">
                    <div class="bg-gradient-to-r from-primary-600 to-blue-600 rounded-xl shadow-lg p-8 mb-12 text-white">
                        <h1 class="text-4xl font-extrabold mb-4">N8n Document Processing System</h1>
                        <p class="text-lg opacity-90 max-w-3xl">
                            Complete developer documentation covering all phases of the N8n-Laravel async document processing pipeline. From webhook contracts to HMAC security, error handling, and deployment strategies.
                        </p>
                        <div class="mt-6 flex flex-wrap gap-3">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-white bg-opacity-20">
                                Laravel 10+
                            </span>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-white bg-opacity-20">
                                N8n Workflows
                            </span>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-white bg-opacity-20">
                                HMAC-SHA256
                            </span>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-white bg-opacity-20">
                                Docker Services
                            </span>
                        </div>
                    </div>

                    <!-- Overview Section -->
                    <section id="overview" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">Overview</h2>
                        <p class="text-lg text-gray-700 dark:text-gray-300 mb-6">
                            The N8n Document Processing System is an asynchronous, event-driven architecture for extracting structured data from supplier documents (PDFs, images, emails, AIR files). The system routes documents through N8n workflows based on supplier ID, processes them using various extraction methods (Tika OCR, GPT-4 Vision, Gmail API), and returns normalized task data to Laravel via secure webhook callbacks.
                        </p>

                        <div class="bg-blue-50 dark:bg-blue-900/30 border-l-4 border-blue-400 p-4 rounded-md mb-6">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-info-circle text-blue-400"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-blue-700 dark:text-blue-200">
                                        <strong>Why N8n?</strong> N8n provides a visual workflow builder for complex document routing, supports conditional branching by supplier, integrates with multiple APIs (OpenAI, Gmail, FTP), and enables rapid iteration without Laravel code changes.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <h3 class="text-2xl font-semibold mt-8 mb-4 text-gray-900 dark:text-white">Key Features</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                                <h4 class="font-semibold text-primary-600 dark:text-primary-400 mb-2">
                                    <i class="fas fa-code-branch mr-2"></i>Supplier-Based Routing
                                </h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    Switch node routes documents to supplier-specific workflows (Amadeus, Emirates, Jazeera Airways) with custom extraction logic per supplier.
                                </p>
                            </div>
                            <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                                <h4 class="font-semibold text-primary-600 dark:text-primary-400 mb-2">
                                    <i class="fas fa-shield-alt mr-2"></i>HMAC-SHA256 Security
                                </h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    Per-client webhook secrets with signature verification, replay attack protection, and key rotation strategy with grace periods.
                                </p>
                            </div>
                            <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                                <h4 class="font-semibold text-primary-600 dark:text-primary-400 mb-2">
                                    <i class="fas fa-bug mr-2"></i>18 Error Codes
                                </h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    Comprehensive error classification (Transient, Non-Transient, System) with automatic retry logic and manual intervention dashboard.
                                </p>
                            </div>
                            <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                                <h4 class="font-semibold text-primary-600 dark:text-primary-400 mb-2">
                                    <i class="fas fa-chart-line mr-2"></i>Execution Tracking
                                </h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    Full lifecycle monitoring (queued → processing → completed/failed) with metrics, error analytics, and alert thresholds.
                                </p>
                            </div>
                        </div>
                    </section>

                    <!-- Architecture Section -->
                    <section id="architecture" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">System Architecture</h2>

                        <h3 class="text-2xl font-semibold mt-8 mb-4 text-gray-900 dark:text-white">Async Flow Diagram</h3>
                        <div class="bg-gray-100 dark:bg-gray-800 p-6 rounded-lg mb-6 border border-gray-200 dark:border-gray-700">
                            <pre class="text-xs text-gray-900 dark:text-gray-100 overflow-x-auto"><code>┌─────────────┐      POST /api/documents/process      ┌──────────────┐
│   Laravel   │ ────────────────────────────────────▶ │  N8n Webhook │
│  (Client)   │      {company_id, supplier_id, ...}   │   Trigger    │
└─────────────┘                                        └──────┬───────┘
      │                                                       │
      │ 202 Accepted                                         │ Async Processing
      │ {document_id, status: queued}                        │
      ◀─────────────────────────────────────────────────────┘
                                                              │
                                                              ▼
                                              ┌───────────────────────────┐
                                              │   Switch by Supplier ID   │
                                              └───────────┬───────────────┘
                                                          │
                ┌─────────────────────────────────────────┼─────────────────────────────────────────┐
                │                                         │                                         │
                ▼                                         ▼                                         ▼
        ┌───────────────┐                       ┌───────────────┐                       ┌───────────────┐
        │ Supplier A    │                       │ Supplier B    │                       │ Supplier C    │
        │ (Tika PDF)    │                       │ (Gutenberg)   │                       │ (Gmail API)   │
        └───────┬───────┘                       └───────┬───────┘                       └───────┬───────┘
                │                                       │                                       │
                └───────────────────────────────────────┼───────────────────────────────────────┘
                                                        │
                                                        ▼
                                            ┌───────────────────────┐
                                            │ Schema Normalization  │
                                            └───────────┬───────────┘
                                                        │
                                                        ▼
                                            ┌───────────────────────┐
                                            │  HTTP Callback Node   │
                                            │  (HMAC Signed)        │
                                            └───────────┬───────────┘
                                                        │
                ┌───────────────────────────────────────┘
                │ POST /api/webhooks/n8n/extraction
                │ {status: success|error, extraction_result, ...}
                │ Headers: X-Signature, X-Timestamp
                ▼
        ┌──────────────────┐
        │  Laravel Callback │
        │  Controller       │
        └────────┬──────────┘
                 │
                 ├──▶ Verify HMAC
                 │
                 ├──▶ Update DocumentProcessingLog
                 │
                 ├──▶ Create DocumentError (if failed)
                 │
                 └──▶ Return 200 OK</code></pre>
                        </div>

                        <h3 class="text-2xl font-semibold mt-8 mb-4 text-gray-900 dark:text-white">Technology Stack</h3>
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Component</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Technology</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Purpose</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr>
                                        <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">Backend</td>
                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Laravel 10+</td>
                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">API endpoints, database, webhook signing</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">Workflow Engine</td>
                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">N8n (self-hosted)</td>
                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Document routing, extraction orchestration</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">PDF Extraction</td>
                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Apache Tika (Docker)</td>
                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">OCR, text extraction from PDFs</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">Image Extraction</td>
                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Gutenberg OCR (Docker)</td>
                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Image-to-text conversion</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">Email Processing</td>
                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Gmail API</td>
                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Email attachment extraction</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">AIR File Parser</td>
                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Custom N8n Code Node</td>
                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Amadeus AIR file parsing</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">Database</td>
                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">MySQL</td>
                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">DocumentProcessingLog, DocumentErrors</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">Cache</td>
                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Redis</td>
                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Rate limiting, alert cooldowns</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- Phase 1 Section -->
                    <section id="phase1" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">Phase 1: Foundation</h2>
                        <p class="text-lg text-gray-700 dark:text-gray-300 mb-6">
                            Phase 1 establishes the contract between Laravel and N8n, defines error handling patterns, and designs the HMAC security strategy.
                        </p>

                        <h3 class="text-2xl font-semibold mt-8 mb-4 text-gray-900 dark:text-white">Webhook Contract Specification</h3>

                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Request: Laravel → N8n</h4>
                        <div class="code-block">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg mb-6 text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>{
  "company_id": 42,
  "supplier_id": 5,
  "document_id": "550e8400-e29b-41d4-a716-446655440000",
  "document_type": "air|pdf|image|email",
  "file_path": "test_company/amadeus/files_unprocessed/ticket.air",
  "file_size_bytes": 2048,
  "file_hash": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
  "callback_url": "https://yourapp.com/api/webhooks/n8n/extraction",
  "metadata": {
    "email_sender": "bookings@amadeus.example.com",
    "supplier_name": "Amadeus GDS"
  },
  "timestamp": 1707588000000
}</code></pre>
                            <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>

                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Response: N8n → Laravel (Immediate)</h4>
                        <div class="code-block">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg mb-6 text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>HTTP/1.1 202 Accepted
Content-Type: application/json

{
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

                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Callback: N8n → Laravel (Async Result)</h4>
                        <div class="code-block">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg mb-6 text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>POST /api/webhooks/n8n/extraction
Headers:
  X-Signature: abc123def456...
  X-Timestamp: 1707588015

{
  "status": "success",
  "execution_id": "507f1f77bcf86cd799439011",
  "document_id": "550e8400-e29b-41d4-a716-446655440000",
  "company_id": 42,
  "supplier_id": 5,
  "timestamp": 1707588015000,
  "extracted_data": {
    "task_type": "flight",
    "passengers": [...],
    "segments": [...],
    "pricing": {...}
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

                        <h3 class="text-2xl font-semibold mt-8 mb-4 text-gray-900 dark:text-white">N8n Pattern Analysis</h3>
                        <p class="text-gray-700 dark:text-gray-300 mb-4">
                            Based on production Supplier Extraction workflow (<code>TAHzFBF3QC9tX4MZ</code>), the N8n flow follows this pattern:
                        </p>
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Node</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Purpose</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Webhook Trigger</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">n8n-nodes-base.webhook</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">Receive POST from Laravel</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Switch by Supplier</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">n8n-nodes-base.switch</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">Route to supplier-specific branch (12 outputs)</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Get Attachments</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">n8n-nodes-base.httpRequest</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">Fetch document from storage/API</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Filter PDFs</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">n8n-nodes-base.filter</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">Select only PDF/image files</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Parse Data</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">n8n-nodes-base.code</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">Extract fields via Tika/Gutenberg/GPT-4</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Convert to PDF</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">n8n-nodes-base.httpRequest</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">Gotenberg HTML→PDF conversion</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Upload FTP</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">n8n-nodes-base.ftp</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">Store processed document</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Error Handler</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">n8n-nodes-base.if</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">Route success vs. error path</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Callback</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">n8n-nodes-base.httpRequest</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">POST result to Laravel callback URL</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <h3 class="text-2xl font-semibold mt-8 mb-4 text-gray-900 dark:text-white">Error Handling Architecture</h3>
                        <p class="text-gray-700 dark:text-gray-300 mb-4">
                            18 error codes across 3 categories ensure comprehensive failure tracking:
                        </p>
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Category</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Error Codes</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Recovery</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-yellow-600 dark:text-yellow-400">Transient (5)</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">ERR_TIMEOUT, ERR_SERVICE_UNAVAILABLE, ERR_RATE_LIMIT, ERR_FILE_TEMP_UNAVAILABLE, ERR_NETWORK_TRANSIENT</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">Auto-retry with exponential backoff</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-orange-600 dark:text-orange-400">Non-Transient (7)</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">ERR_PARSE_FAILURE, ERR_VALIDATION_FAILURE, ERR_UNSUPPORTED_FORMAT, ERR_FILE_NOT_FOUND, ERR_INSUFFICIENT_DATA, ERR_HMAC_INVALID, ERR_SUPPLIER_NOT_CONFIG</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">Manual intervention required</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-red-600 dark:text-red-400">System (5)</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">ERR_N8N_UNAVAILABLE, ERR_CALLBACK_UNREACHABLE, ERR_DATABASE_ERROR, ERR_AUTH_FAILURE, ERR_RESOURCE_EXHAUSTION</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">Critical alert + ops escalation</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- Phase 2 Section -->
                    <section id="phase2" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">Phase 2: Integration</h2>
                        <p class="text-lg text-gray-700 dark:text-gray-300 mb-6">
                            Phase 2 implements the Laravel API endpoints, N8n workflows, and document extraction services.
                        </p>

                        <h3 class="text-2xl font-semibold mt-8 mb-4 text-gray-900 dark:text-white">API Endpoints</h3>

                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-lg font-semibold text-gray-900 dark:text-white">Submit Document for Processing</h4>
                                    <span class="method-badge http-post">POST</span>
                                </div>
                                <code class="text-sm text-gray-600 dark:text-gray-400">/api/documents/process</code>
                            </div>
                            <div class="px-6 py-4">
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Queue a document for N8n processing. Returns 202 Accepted with document_id.</p>
                                <div class="code-block">
                                    <pre class="bg-gray-100 dark:bg-gray-800 p-3 rounded text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>// Request
POST /api/documents/process
Content-Type: application/json

{
  "company_id": 42,
  "supplier_id": 5,
  "document_type": "air",
  "file_path": "test_company/amadeus/ticket.air",
  "file_size_bytes": 2048,
  "file_hash": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
}

// Response (202 Accepted)
{
  "document_id": "550e8400-e29b-41d4-a716-446655440000",
  "status": "queued",
  "message": "Document queued for processing"
}</code></pre>
                                    <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                        <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-lg font-semibold text-gray-900 dark:text-white">N8n Extraction Callback</h4>
                                    <span class="method-badge http-post">POST</span>
                                </div>
                                <code class="text-sm text-gray-600 dark:text-gray-400">/api/webhooks/n8n/extraction</code>
                            </div>
                            <div class="px-6 py-4">
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Receive extraction results from N8n. Verifies HMAC signature before processing.</p>
                                <div class="code-block">
                                    <pre class="bg-gray-100 dark:bg-gray-800 p-3 rounded text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>// Request
POST /api/webhooks/n8n/extraction
X-Signature: abc123def456...
X-Timestamp: 1707588015

{
  "document_id": "550e8400-e29b-41d4-a716-446655440000",
  "status": "success",
  "execution_id": "507f1f77bcf86cd799439011",
  "workflow_id": "supplier-extraction",
  "execution_time_ms": 2500,
  "extraction_result": {
    "task_type": "flight",
    "passengers": [...],
    "segments": [...]
  }
}

// Response (200 OK)
{
  "message": "Callback processed",
  "document_id": "550e8400-e29b-41d4-a716-446655440000"
}</code></pre>
                                    <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                        <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <h3 class="text-2xl font-semibold mt-8 mb-4 text-gray-900 dark:text-white">N8n Workflow Structure</h3>
                        <p class="text-gray-700 dark:text-gray-300 mb-4">
                            The supplier extraction workflow uses 18 nodes to route, extract, and normalize document data:
                        </p>
                        <div class="code-block">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg mb-6 text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>Webhook Trigger (POST)
  ↓
Validate Input (Code Node)
  ↓
Switch by Supplier ID
  ├─ Output 1: Amadeus (supplier_id = 1)
  │    ├─ Fetch AIR File
  │    ├─ Parse AIR Format (Code Node)
  │    └─ Normalize Schema
  ├─ Output 2: Emirates (supplier_id = 2)
  │    ├─ Fetch Email (Gmail API)
  │    ├─ Extract PDF Attachment
  │    ├─ OCR via Tika
  │    └─ Normalize Schema
  ├─ Output 3: Jazeera Airways (supplier_id = 3)
  │    ├─ Fetch Image
  │    ├─ OCR via Gutenberg
  │    └─ Normalize Schema
  └─ Output 0: Unmapped Supplier
       └─ Error: ERR_SUPPLIER_NOT_CONFIG
  ↓
Error Handler (If Node)
  ├─ Success Path → HTTP Callback (POST to Laravel)
  └─ Error Path → Log Error → HTTP Callback (status: error)</code></pre>
                            <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>

                        <h3 class="text-2xl font-semibold mt-8 mb-4 text-gray-900 dark:text-white">Document Extraction Methods</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div class="bg-blue-50 dark:bg-blue-900/30 border-l-4 border-blue-400 p-4 rounded-md">
                                <h4 class="text-sm font-semibold text-blue-800 dark:text-blue-200 mb-2">Tika PDF Extraction</h4>
                                <ul class="text-sm text-blue-700 dark:text-blue-300 space-y-1">
                                    <li>• Docker: <code>apache/tika:latest</code></li>
                                    <li>• Port: 9998</li>
                                    <li>• Endpoint: POST /tika</li>
                                    <li>• Use: PDF text extraction, OCR</li>
                                </ul>
                            </div>
                            <div class="bg-green-50 dark:bg-green-900/30 border-l-4 border-green-400 p-4 rounded-md">
                                <h4 class="text-sm font-semibold text-green-800 dark:text-green-200 mb-2">Gutenberg Image OCR</h4>
                                <ul class="text-sm text-green-700 dark:text-green-300 space-y-1">
                                    <li>• Docker: <code>gotenberg/gotenberg:7</code></li>
                                    <li>• Port: 3000</li>
                                    <li>• Endpoint: POST /forms/chromium/convert/html</li>
                                    <li>• Use: Image → PDF, HTML → PDF</li>
                                </ul>
                            </div>
                            <div class="bg-purple-50 dark:bg-purple-900/30 border-l-4 border-purple-400 p-4 rounded-md">
                                <h4 class="text-sm font-semibold text-purple-800 dark:text-purple-200 mb-2">Gmail API</h4>
                                <ul class="text-sm text-purple-700 dark:text-purple-300 space-y-1">
                                    <li>• Node: n8n-nodes-base.gmail</li>
                                    <li>• Auth: OAuth2 or Service Account</li>
                                    <li>• Endpoint: GET /gmail/v1/users/me/messages</li>
                                    <li>• Use: Email attachment extraction</li>
                                </ul>
                            </div>
                            <div class="bg-yellow-50 dark:bg-yellow-900/30 border-l-4 border-yellow-400 p-4 rounded-md">
                                <h4 class="text-sm font-semibold text-yellow-800 dark:text-yellow-200 mb-2">AIR File Parser (Fallback)</h4>
                                <ul class="text-sm text-yellow-700 dark:text-yellow-300 space-y-1">
                                    <li>• Node: n8n-nodes-base.code</li>
                                    <li>• Language: JavaScript</li>
                                    <li>• Lib: Custom Amadeus AIR parser</li>
                                    <li>• Use: Direct AIR file parsing</li>
                                </ul>
                            </div>
                        </div>

                        <h3 class="text-2xl font-semibold mt-8 mb-4 text-gray-900 dark:text-white">Schema Normalization</h3>
                        <p class="text-gray-700 dark:text-gray-300 mb-4">
                            All extraction methods normalize output to a standard task schema:
                        </p>
                        <div class="code-block">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg mb-6 text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>{
  "task_type": "flight|hotel|insurance|visa",
  "passengers": [
    {
      "title": "MR",
      "first_name": "JOHN",
      "last_name": "DOE",
      "passport_number": "AB1234567"
    }
  ],
  "segments": [
    {
      "departure_city": "LHR",
      "arrival_city": "MUC",
      "airline_code": "LH",
      "flight_number": "700",
      "departure_date": "2026-03-15",
      "departure_time": "10:30",
      "booking_reference": "ABC123"
    }
  ],
  "pricing": {
    "currency": "KWD",
    "base_fare": 250.00,
    "taxes": 75.00,
    "total": 325.00
  },
  "ticket_reference": "0018881229833"
}</code></pre>
                            <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>
                    </section>

                    <!-- Phase 3 Section -->
                    <section id="phase3" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">Phase 3: Security & Reliability</h2>
                        <p class="text-lg text-gray-700 dark:text-gray-300 mb-6">
                            Phase 3 adds HMAC-SHA256 signing, rate limiting, and audit logging for production security.
                        </p>

                        <h3 class="text-2xl font-semibold mt-8 mb-4 text-gray-900 dark:text-white">HMAC-SHA256 Signing</h3>
                        <div class="code-block">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg mb-6 text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>// Laravel: Sign outbound request to N8n
$payload = json_encode($data);
$timestamp = time();
$message = "POST /webhook/document-processing\n{$timestamp}\n{$payload}";
$signature = hash_hmac('sha256', $message, $secret);

// Headers
X-Signature-SHA256: {$signature}
X-Signature-Timestamp: {$timestamp}

// N8n: Verify signature in Code node
const crypto = require('crypto');
const receivedSignature = $headers['x-signature-sha256'];
const timestamp = $headers['x-signature-timestamp'];
const payload = $body;

const message = `POST /webhook/document-processing\n${timestamp}\n${JSON.stringify(payload)}`;
const expectedSignature = crypto
  .createHmac('sha256', process.env.WEBHOOK_SECRET)
  .update(message)
  .digest('hex');

if (receivedSignature !== expectedSignature) {
  throw new Error('HMAC verification failed');
}</code></pre>
                            <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>

                        <h3 class="text-2xl font-semibold mt-8 mb-4 text-gray-900 dark:text-white">Rate Limiting</h3>
                        <p class="text-gray-700 dark:text-gray-300 mb-4">
                            Redis-backed per-client rate limiting prevents abuse:
                        </p>
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Limit Type</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Threshold</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Window</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Global</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">100 req/min</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">60 seconds</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">429 Too Many Requests</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Per-Client</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">60 req/min</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">60 seconds</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">429 Too Many Requests</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Burst</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">10 req/sec</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">1 second</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">429 Too Many Requests</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <h3 class="text-2xl font-semibold mt-8 mb-4 text-gray-900 dark:text-white">File Validation</h3>
                        <div class="bg-yellow-50 dark:bg-yellow-900 border-l-4 border-yellow-400 p-4 rounded-md mb-6">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-yellow-700 dark:text-yellow-200">
                                        <strong>Security:</strong> All file paths are validated to prevent directory traversal attacks. MIME types are checked against whitelist before processing.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="code-block">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg mb-6 text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>// File validation in Laravel
$validated = $request->validate([
    'file_path' => [
        'required',
        'string',
        'max:500',
        function ($attribute, $value, $fail) {
            // Prevent directory traversal
            if (str_contains($value, '..') || str_contains($value, '//')) {
                $fail('Invalid file path');
            }
        }
    ],
    'file_size_bytes' => 'required|integer|max:10485760', // 10MB
]);

// MIME type whitelist
$allowedMimeTypes = [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'message/rfc822', // email
];</code></pre>
                            <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>
                    </section>

                    <!-- Phase 4 Section -->
                    <section id="phase4" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">Phase 4: Error Handling & Observability</h2>

                        <h3 class="text-2xl font-semibold mt-8 mb-4 text-gray-900 dark:text-white">N8n Execution Tracker</h3>
                        <p class="text-gray-700 dark:text-gray-300 mb-4">
                            <code>N8nExecutionTracker</code> service tracks document lifecycle from queue to completion:
                        </p>
                        <div class="code-block">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg mb-6 text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>// Track execution start
$tracker->startExecution($documentId, $payload, $workflowId);
// Status: queued → processing

// Track successful completion
$tracker->completeExecution($documentId, $result, $executionId);
// Status: processing → completed
// Stores: extraction_result, duration_ms

// Track failure
$tracker->failExecution($documentId, $error, $executionId);
// Status: processing → failed
// Creates: DocumentError record
// Sets: needs_review = true</code></pre>
                            <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>

                        <h3 class="text-2xl font-semibold mt-8 mb-4 text-gray-900 dark:text-white">Document Errors Table</h3>
                        <p class="text-gray-700 dark:text-gray-300 mb-4">
                            All failures are logged to <code>document_errors</code> table with full context:
                        </p>
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Field</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Purpose</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">error_type</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">enum</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">transient, non_transient, system</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">error_code</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">string</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">ERR_TIMEOUT, ERR_PARSE_FAILURE, etc.</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">error_message</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">text</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">Human-readable error description</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">stack_trace</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">text</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">N8n error stack for debugging</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">input_context</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">json</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">Original payload, execution_id, failed_at_node</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">retry_count</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">integer</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">Number of retry attempts</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <h3 class="text-2xl font-semibold mt-8 mb-4 text-gray-900 dark:text-white">Error Analytics Dashboard</h3>
                        <p class="text-gray-700 dark:text-gray-300 mb-4">
                            Real-time error metrics via <code>N8nExecutionTracker::getExecutionMetrics()</code>:
                        </p>
                        <div class="code-block">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg mb-6 text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>{
  "timeframe": "day",
  "period_start": "2026-02-09T00:00:00Z",
  "period_end": "2026-02-10T00:00:00Z",
  "total_executions": 156,
  "completed": 142,
  "failed": 14,
  "processing": 0,
  "success_rate": 91.03,
  "avg_duration_ms": 3250.45,
  "p95_duration_ms": 8500,
  "errors_by_type": {
    "transient": 5,
    "non_transient": 8,
    "system": 1
  },
  "top_error_codes": {
    "ERR_TIMEOUT": 3,
    "ERR_PARSE_FAILURE": 2,
    "ERR_INSUFFICIENT_DATA": 2
  }
}</code></pre>
                            <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>

                        <h3 class="text-2xl font-semibold mt-8 mb-4 text-gray-900 dark:text-white">Alert Service</h3>
                        <p class="text-gray-700 dark:text-gray-300 mb-4">
                            <code>ErrorAlertService</code> monitors thresholds and sends alerts:
                        </p>
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Alert Type</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Threshold</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cooldown</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Severity</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Error Rate Exceeded</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">&gt;10% in 1 hour</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">30 minutes</td>
                                        <td class="px-4 py-3"><span class="px-2 py-1 text-xs rounded bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200">Warning</span></td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Consecutive Failures</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">5 in a row</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">30 minutes</td>
                                        <td class="px-4 py-3"><span class="px-2 py-1 text-xs rounded bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200">Critical</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <h3 class="text-2xl font-semibold mt-8 mb-4 text-gray-900 dark:text-white">Manual Intervention Workflow</h3>
                        <p class="text-gray-700 dark:text-gray-300 mb-4">
                            Failed documents are flagged for manual review via dashboard (future implementation):
                        </p>
                        <div class="bg-gray-100 dark:bg-gray-800 p-6 rounded-lg mb-6 border border-gray-200 dark:border-gray-700">
                            <pre class="text-xs text-gray-900 dark:text-gray-100 overflow-x-auto"><code>1. Document fails with non-transient error
   ↓
2. DocumentError record created with needs_review = true
   ↓
3. Dashboard shows in "Failed Documents" tab
   ↓
4. Developer investigates:
   • View error message + stack trace
   • Review original file_path
   • Check N8n execution logs
   ↓
5. Resolution actions:
   ├─ Force Retry: Requeue document
   ├─ Mark Resolved: Close ticket
   ├─ Extract Manually: Open form to enter data
   └─ Escalate: Assign to engineering</code></pre>
                        </div>

                        <h3 class="text-2xl font-semibold mt-8 mb-4 text-gray-900 dark:text-white">Artisan Commands</h3>
                        <div class="code-block">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg mb-6 text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code># Check error thresholds and send alerts
php artisan webhook:check-error-thresholds

# Retry failed documents (with transient errors only)
php artisan webhook:retry-failed-documents --type=transient --max-age=24h

# Cleanup old audit logs (>90 days)
php artisan webhook:cleanup-audit-logs --days=90</code></pre>
                            <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>
                    </section>

                    <!-- Phase 5 Section -->
                    <section id="phase5" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">Phase 5: Testing</h2>

                        <h3 class="text-2xl font-semibold mt-8 mb-4 text-gray-900 dark:text-white">Test Suites Overview</h3>
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Test Type</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Coverage</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Command</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Unit Tests</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">WebhookSigningService, N8nExecutionTracker</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400"><code>./vendor/bin/phpunit --testsuite=Unit</code></td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Feature Tests</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">API endpoints, callback processing</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400"><code>./vendor/bin/phpunit --testsuite=Feature</code></td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Integration Tests</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">Laravel → N8n → Laravel full flow</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400"><code>./vendor/bin/phpunit --group=integration</code></td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">N8n Workflow Tests</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">Manual testing via N8n UI + curl</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">N/A (manual)</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <h3 class="text-2xl font-semibold mt-8 mb-4 text-gray-900 dark:text-white">How to Run Tests</h3>
                        <div class="code-block">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg mb-6 text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code># Run all tests
./vendor/bin/phpunit

# Run specific test class
./vendor/bin/phpunit tests/Feature/DocumentProcessingTest.php

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage

# Test HMAC signing
php artisan tinker
>>> $service = app(\App\Services\WebhookSigningService::class);
>>> $service->signPayload('{"test": true}', 'secret123', 'POST', '/webhook');

# Test N8n callback manually
curl -X POST http://localhost/api/webhooks/n8n/extraction \
  -H "Content-Type: application/json" \
  -H "X-Signature: abc123..." \
  -H "X-Timestamp: 1707588015" \
  -d '{...}'</code></pre>
                            <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>
                    </section>

                    <!-- Configuration Section -->
                    <section id="configuration" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">Configuration Reference</h2>

                        <h3 class="text-2xl font-semibold mt-8 mb-4 text-gray-900 dark:text-white">Environment Variables</h3>
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Variable</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Default</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Description</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr>
                                        <td class="px-4 py-3 font-mono text-sm text-gray-900 dark:text-white">N8N_BASE_URL</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">http://localhost:5678</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">N8n instance URL</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-mono text-sm text-gray-900 dark:text-white">N8N_WEBHOOK_PATH</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">/webhook/document-processing</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">N8n webhook endpoint path</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-mono text-sm text-gray-900 dark:text-white">WEBHOOK_SECRET</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">(required)</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">HMAC signing secret (64 char hex)</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-mono text-sm text-gray-900 dark:text-white">WEBHOOK_RATE_LIMITING_ENABLED</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">true</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">Enable rate limiting</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-mono text-sm text-gray-900 dark:text-white">WEBHOOK_GLOBAL_RATE_LIMIT</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">100</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">Max requests per minute (global)</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-mono text-sm text-gray-900 dark:text-white">WEBHOOK_ERROR_RATE_THRESHOLD</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">10</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">Error rate % to trigger alert</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-mono text-sm text-gray-900 dark:text-white">TIKA_URL</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">http://tika:9998</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">Apache Tika service URL</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-mono text-sm text-gray-900 dark:text-white">GOTENBERG_URL</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">http://gotenberg:3000</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">Gotenberg service URL</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <h3 class="text-2xl font-semibold mt-8 mb-4 text-gray-900 dark:text-white">Docker Compose Services</h3>
                        <div class="code-block">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg mb-6 text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>version: '3.8'
services:
  n8n:
    image: n8nio/n8n:latest
    ports:
      - "5678:5678"
    environment:
      - N8N_BASIC_AUTH_ACTIVE=true
      - N8N_BASIC_AUTH_USER=admin
      - N8N_BASIC_AUTH_PASSWORD=changeme
      - WEBHOOK_URL=https://n8n.yourdomain.com
    volumes:
      - n8n_data:/home/node/.n8n

  tika:
    image: apache/tika:latest
    ports:
      - "9998:9998"

  gotenberg:
    image: gotenberg/gotenberg:7
    ports:
      - "3000:3000"

volumes:
  n8n_data:</code></pre>
                            <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>
                    </section>

                    <!-- Database Section -->
                    <section id="database" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">Database Schema</h2>

                        <h3 class="text-2xl font-semibold mt-8 mb-4 text-gray-900 dark:text-white">document_processing_logs</h3>
                        <div class="code-block">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg mb-6 text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>CREATE TABLE document_processing_logs (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  company_id BIGINT UNSIGNED NOT NULL,
  supplier_id BIGINT UNSIGNED NOT NULL,
  document_id VARCHAR(36) UNIQUE NOT NULL,
  document_type ENUM('air', 'pdf', 'image', 'email') NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  file_size_bytes INT UNSIGNED,
  file_hash VARCHAR(64),
  status ENUM('queued', 'processing', 'completed', 'failed') DEFAULT 'queued',
  n8n_execution_id VARCHAR(255),
  n8n_workflow_id VARCHAR(255),
  processing_duration_ms INT UNSIGNED,
  extraction_result JSON,
  error_code VARCHAR(50),
  error_message TEXT,
  error_context JSON,
  needs_review BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  started_at TIMESTAMP NULL,
  completed_at TIMESTAMP NULL,
  callback_received_at TIMESTAMP NULL,
  INDEX idx_company_id (company_id),
  INDEX idx_supplier_id (supplier_id),
  INDEX idx_status (status),
  INDEX idx_created_at (created_at)
);</code></pre>
                            <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>

                        <h3 class="text-2xl font-semibold mt-8 mb-4 text-gray-900 dark:text-white">document_errors</h3>
                        <div class="code-block">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg mb-6 text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>CREATE TABLE document_errors (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  document_processing_log_id BIGINT UNSIGNED NOT NULL,
  error_type ENUM('transient', 'non_transient', 'system') NOT NULL,
  error_code VARCHAR(50) NOT NULL,
  error_message TEXT NOT NULL,
  stack_trace TEXT,
  input_context JSON,
  retry_count INT DEFAULT 0,
  last_retry_at TIMESTAMP NULL,
  resolved_at TIMESTAMP NULL,
  resolved_by_user_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (document_processing_log_id) REFERENCES document_processing_logs(id) ON DELETE CASCADE,
  INDEX idx_error_type (error_type),
  INDEX idx_error_code (error_code),
  INDEX idx_created_at (created_at)
);</code></pre>
                            <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>
                    </section>

                    <!-- Deployment Section -->
                    <section id="deployment" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">Deployment Guide</h2>

                        <h3 class="text-2xl font-semibold mt-8 mb-4 text-gray-900 dark:text-white">Server Requirements</h3>
                        <ul class="list-disc list-inside text-gray-700 dark:text-gray-300 mb-6 space-y-2">
                            <li>PHP 8.1+ with extensions: pdo_mysql, redis, curl</li>
                            <li>MySQL 8.0+ or MariaDB 10.5+</li>
                            <li>Redis 6.0+ (for rate limiting, cache)</li>
                            <li>Docker + Docker Compose (for N8n, Tika, Gotenberg)</li>
                            <li>SSL certificate (required for webhook callbacks)</li>
                        </ul>

                        <h3 class="text-2xl font-semibold mt-8 mb-4 text-gray-900 dark:text-white">Deployment Steps</h3>
                        <div class="code-block">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg mb-6 text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code># 1. Clone repository
git clone https://github.com/your-org/soud-laravel.git
cd soud-laravel

# 2. Install dependencies
composer install --no-dev --optimize-autoloader

# 3. Configure environment
cp .env.example .env
php artisan key:generate

# 4. Set webhook secret
php artisan tinker
>>> app(\App\Services\WebhookSigningService::class)->generateSecret();
# Copy output to .env: WEBHOOK_SECRET=...

# 5. Run migrations
php artisan migrate --force

# 6. Start Docker services
docker-compose up -d n8n tika gotenberg

# 7. Import N8n workflow
# Access http://localhost:5678
# Import workflow JSON from /n8n/workflows/supplier-extraction.json

# 8. Configure webhook URL in N8n
# Settings → Environment Variables → LARAVEL_CALLBACK_URL
# Set to: https://yourapp.com/api/webhooks/n8n/extraction

# 9. Test integration
curl -X POST https://yourapp.com/api/documents/process \
  -H "Content-Type: application/json" \
  -d '{"company_id": 1, "supplier_id": 1, ...}'</code></pre>
                            <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>

                        <h3 class="text-2xl font-semibold mt-8 mb-4 text-gray-900 dark:text-white">cPanel Deployment</h3>
                        <p class="text-gray-700 dark:text-gray-300 mb-4">
                            For cPanel hosting, use <code>.cpanel.yml</code> for automated deployment:
                        </p>
                        <div class="code-block">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg mb-6 text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>---
deployment:
  tasks:
    - export DEPLOYPATH=/home/username/public_html
    - /bin/cp -R * $DEPLOYPATH
    - cd $DEPLOYPATH
    - composer install --no-dev --optimize-autoloader
    - php artisan migrate --force
    - php artisan config:cache
    - php artisan route:cache
    - php artisan view:cache</code></pre>
                            <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>
                    </section>

                    <!-- Troubleshooting Section -->
                    <section id="troubleshooting" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">Troubleshooting</h2>

                        <h3 class="text-2xl font-semibold mt-8 mb-4 text-gray-900 dark:text-white">Common Errors</h3>

                        <div class="space-y-4">
                            <div class="bg-red-50 dark:bg-red-900/20 border-l-4 border-red-400 p-4 rounded-md">
                                <h4 class="text-sm font-semibold text-red-700 dark:text-red-300 mb-2">N8n webhook returns 401 Unauthorized</h4>
                                <p class="text-sm text-red-600 dark:text-red-400 mb-2">
                                    <strong>Cause:</strong> HMAC signature mismatch or missing headers.
                                </p>
                                <p class="text-sm text-red-600 dark:text-red-400 mb-2">
                                    <strong>Solution:</strong> Verify webhook secret matches in both Laravel (.env) and N8n (environment variables). Check timestamp is within 5 minutes.
                                </p>
                                <div class="code-block">
                                    <pre class="bg-red-100 dark:bg-red-900/30 p-2 rounded text-xs"><code># Debug signature in Laravel
Log::debug('Signature verification', [
    'provided' => $request->header('X-Signature'),
    'computed' => hash_hmac('sha256', $request->getContent(), config('services.n8n.webhook_secret')),
]);</code></pre>
                                </div>
                            </div>

                            <div class="bg-yellow-50 dark:bg-yellow-900/20 border-l-4 border-yellow-400 p-4 rounded-md">
                                <h4 class="text-sm font-semibold text-yellow-700 dark:text-yellow-300 mb-2">Document stuck in "processing" status</h4>
                                <p class="text-sm text-yellow-600 dark:text-yellow-400 mb-2">
                                    <strong>Cause:</strong> N8n callback never reached Laravel or failed silently.
                                </p>
                                <p class="text-sm text-yellow-600 dark:text-yellow-400 mb-2">
                                    <strong>Solution:</strong> Check N8n execution logs for errors. Verify callback URL is accessible from N8n server.
                                </p>
                                <div class="code-block">
                                    <pre class="bg-yellow-100 dark:bg-yellow-900/30 p-2 rounded text-xs"><code># Query stuck documents
SELECT * FROM document_processing_logs
WHERE status = 'processing'
  AND created_at < NOW() - INTERVAL 1 HOUR;</code></pre>
                                </div>
                            </div>

                            <div class="bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-400 p-4 rounded-md">
                                <h4 class="text-sm font-semibold text-blue-700 dark:text-blue-300 mb-2">Tika service not responding</h4>
                                <p class="text-sm text-blue-600 dark:text-blue-400 mb-2">
                                    <strong>Cause:</strong> Tika Docker container crashed or not running.
                                </p>
                                <p class="text-sm text-blue-600 dark:text-blue-400 mb-2">
                                    <strong>Solution:</strong> Restart Tika service and check logs for OOM errors.
                                </p>
                                <div class="code-block">
                                    <pre class="bg-blue-100 dark:bg-blue-900/30 p-2 rounded text-xs"><code># Restart Tika
docker-compose restart tika

# Check logs
docker-compose logs -f tika

# Test connection
curl http://localhost:9998/tika</code></pre>
                                </div>
                            </div>
                        </div>

                        <h3 class="text-2xl font-semibold mt-8 mb-4 text-gray-900 dark:text-white">Debug Commands</h3>
                        <div class="code-block">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg mb-6 text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code># View recent document processing logs
php artisan tinker
>>> \App\Models\DocumentProcessingLog::latest()->limit(10)->get();

# Check error rate
>>> app(\App\Services\N8nExecutionTracker::class)->getExecutionMetrics('hour');

# Manually trigger alert check
php artisan webhook:check-error-thresholds

# View specific document
>>> \App\Models\DocumentProcessingLog::where('document_id', 'abc-123')->first();

# Resend callback to Laravel (for testing)
curl -X POST http://localhost/api/webhooks/n8n/extraction \
  -H "Content-Type: application/json" \
  -H "X-Signature: $(echo -n '{}' | openssl dgst -sha256 -hmac 'secret' -hex | cut -d' ' -f2)" \
  -H "X-Timestamp: $(date +%s)" \
  -d '{...}'</code></pre>
                            <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>

                        <h3 class="text-2xl font-semibold mt-8 mb-4 text-gray-900 dark:text-white">Log Locations</h3>
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Component</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Log Path</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Laravel</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400"><code>storage/logs/laravel.log</code></td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">N8n</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400"><code>/home/node/.n8n/logs/n8n.log</code></td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Tika</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400"><code>docker-compose logs tika</code></td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Gotenberg</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400"><code>docker-compose logs gotenberg</code></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <div class="border-t border-gray-200 dark:border-gray-700 pt-8 mt-12">
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Need more help?</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Contact the development team for assistance with N8n integration or document processing workflows.
                                </p>
                            </div>
                            <div class="mt-4 md:mt-0">
                                <a href="/docs/developer-documentation" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                    View Task API Docs
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
                &copy; {{ date('Y') }} {{ config('app.name') }}. N8n Document Processing - Complete Documentation.
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

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                    updateActiveNavLink(this.getAttribute('href'));
                }
            });
        });

        // Update active nav link
        function updateActiveNavLink(hash) {
            document.querySelectorAll('.sidebar-link').forEach(link => {
                link.classList.remove('active');
            });
            const activeLink = document.querySelector(`.sidebar-link[href="${hash}"]`);
            if (activeLink) {
                activeLink.classList.add('active');
            }
        }

        // Update active nav on scroll with IntersectionObserver
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
