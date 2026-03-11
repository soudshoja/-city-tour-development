<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>N8n Integration User Guide</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
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

        html {
            scroll-behavior: smooth;
        }

        /* Scrollbar styling */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background-color: rgba(156, 163, 175, 0.5);
            border-radius: 4px;
        }

        * {
            scrollbar-width: thin;
            scrollbar-color: rgba(156, 163, 175, 0.5) transparent;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 64px;
            left: 0;
            width: 280px;
            height: calc(100vh - 64px);
            overflow-y: auto;
            z-index: 30;
            transition: transform 0.3s ease;
        }

        .sidebar-link {
            display: block;
            padding: 6px 16px 6px 24px;
            font-size: 0.8125rem;
            color: #9ca3af;
            border-left: 2px solid transparent;
            transition: all 0.15s ease;
        }

        .sidebar-link:hover {
            color: #e5e7eb;
            background-color: rgba(59, 130, 246, 0.05);
        }

        .sidebar-link.active {
            color: #60a5fa;
            border-left-color: #3b82f6;
            background-color: rgba(59, 130, 246, 0.1);
            font-weight: 500;
        }

        .sidebar-group-title {
            padding: 12px 16px 4px 16px;
            font-size: 0.6875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
        }

        /* Main content offset */
        .main-content {
            margin-left: 280px;
        }

        /* Step number styling */
        .step-number {
            width: 2rem;
            height: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-weight: 700;
            font-size: 0.875rem;
            flex-shrink: 0;
        }

        /* Code block wrapper */
        .code-block {
            position: relative;
        }

        .code-block .copy-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .code-block:hover .copy-btn {
            opacity: 1;
        }

        /* Mermaid diagrams */
        .mermaid {
            display: flex;
            justify-content: center;
            margin: 1.5rem 0;
        }

        .mermaid svg {
            max-width: 100%;
        }

        /* Table striped rows */
        .table-striped tbody tr:nth-child(even) {
            background-color: rgba(31, 41, 55, 0.5);
        }

        /* Mobile sidebar toggle */
        @media (max-width: 1023px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .sidebar-overlay {
                display: none;
            }

            .sidebar-overlay.open {
                display: block;
                position: fixed;
                inset: 0;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 25;
            }
        }

        /* Section divider */
        .section-divider {
            border: none;
            height: 1px;
            background: linear-gradient(to right, transparent, rgba(59, 130, 246, 0.3), transparent);
            margin: 3rem 0;
        }
    </style>
</head>

<body class="bg-gray-50 text-gray-900 dark:bg-gray-900 dark:text-gray-100 transition-colors duration-200 min-h-screen">

    <!-- Header -->
    <header class="bg-white dark:bg-gray-800 shadow-sm sticky top-0 z-40">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <!-- Mobile hamburger -->
                <button id="sidebarToggle" class="lg:hidden p-2 rounded-md hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                    <i class="fas fa-bars text-gray-600 dark:text-gray-300"></i>
                </button>
                <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-primary-600">
                    <i class="fas fa-book-open text-white text-lg"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white">N8n Integration User Guide</h1>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Step-by-step guide for administrators and developers</p>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <span class="hidden sm:inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-500/10 text-green-400 border border-green-500/20">
                    <i class="fas fa-user-shield mr-1"></i> Admin Guide
                </span>
                <span class="hidden sm:inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-500/10 text-blue-400 border border-blue-500/20">
                    <i class="fas fa-code mr-1"></i> Developer Guide
                </span>
                <button id="darkModeToggle" class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 dark:hidden" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z" />
                    </svg>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden dark:block text-yellow-300" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd" />
                    </svg>
                </button>
                <a href="/docs/n8n-hub" class="text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300">
                    <i class="fas fa-home mr-1"></i> Hub
                </a>
            </div>
        </div>
    </header>

    <!-- Sidebar Overlay (mobile) -->
    <div id="sidebarOverlay" class="sidebar-overlay"></div>

    <!-- Left Sidebar Navigation -->
    <aside id="sidebar" class="sidebar bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700">
        <nav class="py-4">
            <!-- Getting Started -->
            <div class="sidebar-group-title">Getting Started</div>
            <a href="#system-overview" class="sidebar-link" data-section="system-overview">
                <i class="fas fa-diagram-project mr-2 w-4 text-center"></i> System Overview
            </a>
            <a href="#key-concepts" class="sidebar-link" data-section="key-concepts">
                <i class="fas fa-lightbulb mr-2 w-4 text-center"></i> Key Concepts
            </a>

            <!-- Admin Guide -->
            <div class="sidebar-group-title mt-2">Admin Guide</div>
            <a href="#dashboard-overview" class="sidebar-link" data-section="dashboard-overview">
                <i class="fas fa-gauge-high mr-2 w-4 text-center"></i> Dashboard Overview
            </a>
            <a href="#processing-documents" class="sidebar-link" data-section="processing-documents">
                <i class="fas fa-file-arrow-up mr-2 w-4 text-center"></i> Processing Documents
            </a>
            <a href="#managing-errors" class="sidebar-link" data-section="managing-errors">
                <i class="fas fa-triangle-exclamation mr-2 w-4 text-center"></i> Managing Errors
            </a>
            <a href="#monitoring-alerts" class="sidebar-link" data-section="monitoring-alerts">
                <i class="fas fa-bell mr-2 w-4 text-center"></i> Monitoring &amp; Alerts
            </a>
            <a href="#supplier-management" class="sidebar-link" data-section="supplier-management">
                <i class="fas fa-truck mr-2 w-4 text-center"></i> Supplier Management
            </a>

            <!-- Developer Guide -->
            <div class="sidebar-group-title mt-2">Developer Guide</div>
            <a href="#local-setup" class="sidebar-link" data-section="local-setup">
                <i class="fas fa-laptop-code mr-2 w-4 text-center"></i> Local Setup
            </a>
            <a href="#n8n-workflow-setup" class="sidebar-link" data-section="n8n-workflow-setup">
                <i class="fas fa-sitemap mr-2 w-4 text-center"></i> N8n Workflow Setup
            </a>
            <a href="#hmac-security" class="sidebar-link" data-section="hmac-security">
                <i class="fas fa-shield-halved mr-2 w-4 text-center"></i> HMAC Security
            </a>
            <a href="#error-code-reference" class="sidebar-link" data-section="error-code-reference">
                <i class="fas fa-list-ol mr-2 w-4 text-center"></i> Error Code Reference
            </a>
            <a href="#api-endpoints" class="sidebar-link" data-section="api-endpoints">
                <i class="fas fa-plug mr-2 w-4 text-center"></i> API Endpoints
            </a>
            <a href="#database-schema" class="sidebar-link" data-section="database-schema">
                <i class="fas fa-database mr-2 w-4 text-center"></i> Database Schema
            </a>
            <a href="#testing" class="sidebar-link" data-section="testing">
                <i class="fas fa-vial mr-2 w-4 text-center"></i> Testing
            </a>

            <!-- Troubleshooting -->
            <div class="sidebar-group-title mt-2">Troubleshooting</div>
            <a href="#common-issues" class="sidebar-link" data-section="common-issues">
                <i class="fas fa-wrench mr-2 w-4 text-center"></i> Common Issues
            </a>
            <a href="#debug-checklist" class="sidebar-link" data-section="debug-checklist">
                <i class="fas fa-clipboard-check mr-2 w-4 text-center"></i> Debug Checklist
            </a>
            <a href="#support-resources" class="sidebar-link" data-section="support-resources">
                <i class="fas fa-life-ring mr-2 w-4 text-center"></i> Support Resources
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content px-6 sm:px-10 lg:px-16 py-10 max-w-5xl">

        
        
        

        <div class="mb-6">
            <div class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-primary-600/10 text-primary-400 border border-primary-500/20 uppercase tracking-wider">
                Part 1 &mdash; Getting Started
            </div>
        </div>

        <!-- 1.1 System Overview -->
        <section id="system-overview" class="mb-16 scroll-mt-24">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2 flex items-center">
                <span class="step-number bg-primary-600/10 text-primary-400 mr-3">1</span>
                System Overview
            </h2>
            <p class="text-gray-600 dark:text-gray-400 mb-6 leading-relaxed">
                The N8n integration automates document processing by connecting the Laravel application to
                an N8n workflow server. When a document is uploaded, it is dispatched via a signed webhook
                to N8n, which routes it to the appropriate processor based on supplier configuration.
                Once processing completes, N8n sends the extracted data back to Laravel through a
                signed callback. The entire flow is secured with bidirectional HMAC-SHA256 signatures.
            </p>

            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 mb-6">
                <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4">
                    <i class="fas fa-diagram-project mr-2"></i> System Architecture
                </h4>
                <div class="mermaid">
                    graph TB
                        A[Laravel App] -->|Webhook POST| B[N8n Server]
                        B -->|Route by Supplier| C{Supplier Router}
                        C -->|PDF| D[Apache Tika]
                        C -->|Image| E[Gutenberg OCR]
                        C -->|Email| F[Gmail API]
                        C -->|AIR| G[Laravel Fallback]
                        D --> H[Schema Normalizer]
                        E --> H
                        F --> H
                        G --> H
                        H -->|Callback POST| A
                        A -->|Store Results| I[(Database)]
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-arrow-right-arrow-left text-primary-400 mr-2"></i>
                        <span class="font-semibold text-sm text-gray-900 dark:text-white">Bidirectional</span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Both outbound webhooks and inbound callbacks are HMAC-signed for tamper-proof communication.</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-rotate text-green-400 mr-2"></i>
                        <span class="font-semibold text-sm text-gray-900 dark:text-white">Auto-Retry</span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Transient failures are automatically retried with exponential backoff up to 3 attempts.</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-route text-orange-400 mr-2"></i>
                        <span class="font-semibold text-sm text-gray-900 dark:text-white">Supplier Routing</span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Each supplier has a dedicated processing pipeline tailored to its document format.</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-clock-rotate-left text-purple-400 mr-2"></i>
                        <span class="font-semibold text-sm text-gray-900 dark:text-white">Full Audit Trail</span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Every processing attempt is logged with input/output payloads, duration, and error details.</p>
                </div>
            </div>
        </section>

        <!-- 1.2 Key Concepts -->
        <section id="key-concepts" class="mb-16 scroll-mt-24">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2 flex items-center">
                <span class="step-number bg-primary-600/10 text-primary-400 mr-3">2</span>
                Key Concepts
            </h2>
            <p class="text-gray-600 dark:text-gray-400 mb-6 leading-relaxed">
                Before diving into administration or development, familiarize yourself with these core
                concepts that underpin the entire document processing pipeline.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <h4 class="font-semibold text-gray-900 dark:text-white mb-1"><i class="fas fa-file-lines text-blue-400 mr-2"></i>Documents</h4>
                    <p class="text-sm text-gray-500 dark:text-gray-400">The source files (PDFs, images, emails) uploaded for data extraction. Each document belongs to a supplier.</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <h4 class="font-semibold text-gray-900 dark:text-white mb-1"><i class="fas fa-truck text-green-400 mr-2"></i>Suppliers</h4>
                    <p class="text-sm text-gray-500 dark:text-gray-400">External entities whose documents are processed. Each supplier maps to a specific N8n processing route.</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <h4 class="font-semibold text-gray-900 dark:text-white mb-1"><i class="fas fa-scroll text-orange-400 mr-2"></i>Processing Logs</h4>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Detailed records of each processing attempt, including status, duration, payloads, and error information.</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <h4 class="font-semibold text-gray-900 dark:text-white mb-1"><i class="fas fa-circle-exclamation text-red-400 mr-2"></i>Error Codes</h4>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Standardized codes categorized as transient, non-transient, or system errors for consistent handling.</p>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4">
                    <i class="fas fa-arrows-spin mr-2"></i> Document Lifecycle
                </h4>
                <div class="mermaid">
                    stateDiagram-v2
                        [*] --> Pending: Document Uploaded
                        Pending --> Processing: Sent to N8n
                        Processing --> Completed: Success Callback
                        Processing --> Failed: Error Callback
                        Failed --> NeedsReview: Non-transient Error
                        Failed --> Retrying: Transient Error
                        Retrying --> Processing: Auto Retry
                        NeedsReview --> Resolved: Admin Review
                        Completed --> [*]
                        Resolved --> [*]
                </div>
            </div>
        </section>

        <hr class="section-divider">

        
        
        

        <div class="mb-6">
            <div class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-600/10 text-green-400 border border-green-500/20 uppercase tracking-wider">
                <i class="fas fa-user-shield mr-1.5"></i> Part 2 &mdash; Admin User Guide
            </div>
        </div>

        <!-- 2.1 Dashboard Overview -->
        <section id="dashboard-overview" class="mb-16 scroll-mt-24">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2 flex items-center">
                <span class="step-number bg-green-600/10 text-green-400 mr-3">3</span>
                Dashboard Overview
            </h2>
            <p class="text-gray-600 dark:text-gray-400 mb-6 leading-relaxed">
                The admin dashboard provides a high-level view of all document processing activity.
                Access it at <code class="text-sm bg-gray-800 text-primary-300 px-1.5 py-0.5 rounded">/admin/n8n-processing</code> after logging in with an admin account.
            </p>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 text-center">
                    <div class="text-2xl font-bold text-primary-400 mb-1">1,247</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Total Documents</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 text-center">
                    <div class="text-2xl font-bold text-green-400 mb-1">96.3%</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Success Rate</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 text-center">
                    <div class="text-2xl font-bold text-yellow-400 mb-1">8</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Pending Reviews</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 text-center">
                    <div class="text-2xl font-bold text-red-400 mb-1">3</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Active Errors</div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4">
                    <i class="fas fa-route mr-2"></i> Admin Workflow
                </h4>
                <div class="mermaid">
                    flowchart LR
                        A[Login] --> B[Dashboard]
                        B --> C{Check Alerts}
                        C -->|Errors| D[Review Failed Docs]
                        C -->|All Clear| E[Monitor Processing]
                        D --> F[Mark Reviewed]
                        D --> G[Trigger Retry]
                        F --> B
                        G --> B
                        E --> B
                </div>
            </div>
        </section>

        <!-- 2.2 Processing Documents -->
        <section id="processing-documents" class="mb-16 scroll-mt-24">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2 flex items-center">
                <span class="step-number bg-green-600/10 text-green-400 mr-3">4</span>
                Processing Documents
            </h2>
            <p class="text-gray-600 dark:text-gray-400 mb-6 leading-relaxed">
                Follow these steps to submit a document for processing and track its progress
                through the pipeline.
            </p>

            <div class="space-y-4 mb-8">
                <div class="flex items-start space-x-4">
                    <div class="step-number bg-primary-600 text-white mt-0.5">1</div>
                    <div>
                        <h4 class="font-semibold text-gray-900 dark:text-white">Navigate to Document Processing</h4>
                        <p class="text-sm text-gray-500 dark:text-gray-400">From the admin sidebar, click <strong>Document Processing</strong> to open the processing management page.</p>
                    </div>
                </div>
                <div class="flex items-start space-x-4">
                    <div class="step-number bg-primary-600 text-white mt-0.5">2</div>
                    <div>
                        <h4 class="font-semibold text-gray-900 dark:text-white">Upload or Select Documents</h4>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Upload a new document or select existing unprocessed documents from the list. Supported formats include PDF, PNG, JPEG, and email exports.</p>
                    </div>
                </div>
                <div class="flex items-start space-x-4">
                    <div class="step-number bg-primary-600 text-white mt-0.5">3</div>
                    <div>
                        <h4 class="font-semibold text-gray-900 dark:text-white">Choose Supplier</h4>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Select the supplier from the dropdown. If left on &ldquo;Auto-detect,&rdquo; the system will attempt to determine the supplier from the document metadata.</p>
                    </div>
                </div>
                <div class="flex items-start space-x-4">
                    <div class="step-number bg-primary-600 text-white mt-0.5">4</div>
                    <div>
                        <h4 class="font-semibold text-gray-900 dark:text-white">Monitor Processing Status</h4>
                        <p class="text-sm text-gray-500 dark:text-gray-400">The status badge updates in real-time: <span class="text-yellow-400">Pending</span> &rarr; <span class="text-blue-400">Processing</span> &rarr; <span class="text-green-400">Completed</span> or <span class="text-red-400">Failed</span>.</p>
                    </div>
                </div>
                <div class="flex items-start space-x-4">
                    <div class="step-number bg-primary-600 text-white mt-0.5">5</div>
                    <div>
                        <h4 class="font-semibold text-gray-900 dark:text-white">View Results</h4>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Once completed, click the document row to view the extracted data, processing duration, and any notes.</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4">
                    <i class="fas fa-arrows-turn-to-dots mr-2"></i> Document Processing Flow
                </h4>
                <div class="mermaid">
                    sequenceDiagram
                        participant Admin
                        participant Laravel
                        participant N8n
                        participant Processor

                        Admin->>Laravel: Upload Document
                        Laravel->>Laravel: Validate & Store
                        Laravel->>N8n: POST /webhook (HMAC signed)
                        N8n-->>Laravel: 202 Accepted
                        Note over Laravel: Status: Processing
                        N8n->>Processor: Route to Processor
                        Processor->>Processor: Extract Data
                        Processor->>N8n: Return Results
                        N8n->>Laravel: POST /callback (HMAC signed)
                        Laravel->>Laravel: Verify HMAC & Store
                        Note over Laravel: Status: Completed
                        Admin->>Laravel: View Results
                </div>
            </div>
        </section>

        <!-- 2.3 Managing Errors -->
        <section id="managing-errors" class="mb-16 scroll-mt-24">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2 flex items-center">
                <span class="step-number bg-green-600/10 text-green-400 mr-3">5</span>
                Managing Errors
            </h2>
            <p class="text-gray-600 dark:text-gray-400 mb-6 leading-relaxed">
                Errors are categorized into three types. Understanding each type helps you decide
                whether to wait for automatic retry, intervene manually, or escalate to the development team.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-yellow-500/30 p-4">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-clock text-yellow-400 mr-2"></i>
                        <span class="font-semibold text-sm text-yellow-400">Transient Errors</span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Temporary failures (timeouts, rate limits, network issues). These are retried automatically up to 3 times.</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-red-500/30 p-4">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-circle-xmark text-red-400 mr-2"></i>
                        <span class="font-semibold text-sm text-red-400">Non-Transient Errors</span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Permanent failures (invalid documents, unsupported types, corrupt files). Require admin review and manual resolution.</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-purple-500/30 p-4">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-server text-purple-400 mr-2"></i>
                        <span class="font-semibold text-sm text-purple-400">System Errors</span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Infrastructure failures (database, storage, configuration). Alert the development team immediately.</p>
                </div>
            </div>

            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Reviewing Failed Documents</h3>
            <div class="space-y-3 mb-6">
                <div class="flex items-start space-x-3">
                    <div class="step-number bg-primary-600 text-white text-xs mt-0.5">1</div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Navigate to <strong class="text-gray-300">Processing Logs</strong> and filter by <code class="text-xs bg-gray-800 text-red-300 px-1 py-0.5 rounded">needs_review = true</code>.</p>
                </div>
                <div class="flex items-start space-x-3">
                    <div class="step-number bg-primary-600 text-white text-xs mt-0.5">2</div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Click a log entry to view the error details, including the error code, message, and the original input payload.</p>
                </div>
                <div class="flex items-start space-x-3">
                    <div class="step-number bg-primary-600 text-white text-xs mt-0.5">3</div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Choose an action: <strong class="text-green-400">Mark Reviewed</strong> (with resolution notes) or <strong class="text-blue-400">Trigger Retry</strong> (resubmits to N8n).</p>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4">
                    <i class="fas fa-code-branch mr-2"></i> Error Handling Decision Tree
                </h4>
                <div class="mermaid">
                    flowchart TD
                        A[Error Detected] --> B{Error Type?}
                        B -->|Transient| C[Auto Retry]
                        C --> D{Retry Count < 3?}
                        D -->|Yes| E[Wait & Retry]
                        D -->|No| F[Escalate to Admin]
                        B -->|Non-Transient| G[Mark for Review]
                        G --> H[Admin Reviews]
                        H --> I{Fixable?}
                        I -->|Yes| J[Fix & Resubmit]
                        I -->|No| K[Mark Resolved with Notes]
                        B -->|System| L[Alert Admin]
                        L --> M[Check N8n Server]
                </div>
            </div>
        </section>

        <!-- 2.4 Monitoring & Alerts -->
        <section id="monitoring-alerts" class="mb-16 scroll-mt-24">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2 flex items-center">
                <span class="step-number bg-green-600/10 text-green-400 mr-3">6</span>
                Monitoring &amp; Alerts
            </h2>
            <p class="text-gray-600 dark:text-gray-400 mb-6 leading-relaxed">
                The system provides real-time monitoring of all processing activity and configurable
                alert thresholds to keep you informed of issues before they become critical.
            </p>

            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 mb-6">
                <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4">Real-Time Status Indicators</h4>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm table-striped">
                        <thead>
                            <tr class="border-b border-gray-700">
                                <th class="text-left py-2 px-3 text-gray-400 font-medium">Metric</th>
                                <th class="text-left py-2 px-3 text-gray-400 font-medium">Description</th>
                                <th class="text-left py-2 px-3 text-gray-400 font-medium">Alert Threshold</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-300">
                            <tr class="border-b border-gray-700/50">
                                <td class="py-2 px-3 font-medium">Processing Queue</td>
                                <td class="py-2 px-3">Documents waiting to be sent to N8n</td>
                                <td class="py-2 px-3"><span class="text-yellow-400">&gt; 50 documents</span></td>
                            </tr>
                            <tr class="border-b border-gray-700/50">
                                <td class="py-2 px-3 font-medium">Error Rate</td>
                                <td class="py-2 px-3">Percentage of failed processing attempts in the last hour</td>
                                <td class="py-2 px-3"><span class="text-red-400">&gt; 10%</span></td>
                            </tr>
                            <tr class="border-b border-gray-700/50">
                                <td class="py-2 px-3 font-medium">Avg Duration</td>
                                <td class="py-2 px-3">Mean processing time (duration_ms) over the last 100 documents</td>
                                <td class="py-2 px-3"><span class="text-orange-400">&gt; 30,000 ms</span></td>
                            </tr>
                            <tr class="border-b border-gray-700/50">
                                <td class="py-2 px-3 font-medium">N8n Health</td>
                                <td class="py-2 px-3">Connectivity status to the N8n server</td>
                                <td class="py-2 px-3"><span class="text-red-400">Unreachable</span></td>
                            </tr>
                            <tr>
                                <td class="py-2 px-3 font-medium">Pending Reviews</td>
                                <td class="py-2 px-3">Documents flagged for admin review</td>
                                <td class="py-2 px-3"><span class="text-yellow-400">&gt; 10 documents</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-blue-500/5 border border-blue-500/20 rounded-lg p-4">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-blue-400 mt-0.5 mr-3"></i>
                    <div>
                        <h4 class="font-semibold text-sm text-blue-300 mb-1">Performance Tracking</h4>
                        <p class="text-xs text-gray-400">Every processing log records <code class="bg-gray-800 text-blue-300 px-1 py-0.5 rounded text-xs">duration_ms</code> &mdash; the time from webhook dispatch to callback receipt. Use this metric to identify slow suppliers or degraded N8n performance.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- 2.5 Supplier Management -->
        <section id="supplier-management" class="mb-16 scroll-mt-24">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2 flex items-center">
                <span class="step-number bg-green-600/10 text-green-400 mr-3">7</span>
                Supplier Management
            </h2>
            <p class="text-gray-600 dark:text-gray-400 mb-6 leading-relaxed">
                Suppliers are configured entities whose documents follow specific processing routes.
                Each supplier ID maps to a dedicated processor in the N8n workflow. Currently, suppliers
                1 through 12 have specific routes; any unknown supplier falls through to the fallback processor.
            </p>

            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 mb-6">
                <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4">
                    <i class="fas fa-shuffle mr-2"></i> Supplier Routing
                </h4>
                <div class="mermaid">
                    flowchart LR
                        A[Incoming Document] --> B{Supplier ID}
                        B -->|Supplier 1-12| C[Specific Processor]
                        B -->|Unknown| D[Fallback Processor]
                        C --> E[Schema Normalizer]
                        D --> E
                        E --> F[Standardized Output]
                </div>
            </div>

            <div class="bg-yellow-500/5 border border-yellow-500/20 rounded-lg p-4">
                <div class="flex items-start">
                    <i class="fas fa-triangle-exclamation text-yellow-400 mt-0.5 mr-3"></i>
                    <div>
                        <h4 class="font-semibold text-sm text-yellow-300 mb-1">Important</h4>
                        <p class="text-xs text-gray-400">Adding a new supplier requires updates in both the Laravel configuration and the N8n workflow. Coordinate with the development team before adding new supplier routes.</p>
                    </div>
                </div>
            </div>
        </section>

        <hr class="section-divider">

        
        
        

        <div class="mb-6">
            <div class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-blue-600/10 text-blue-400 border border-blue-500/20 uppercase tracking-wider">
                <i class="fas fa-code mr-1.5"></i> Part 3 &mdash; Developer Guide
            </div>
        </div>

        <!-- 3.1 Local Setup -->
        <section id="local-setup" class="mb-16 scroll-mt-24">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2 flex items-center">
                <span class="step-number bg-blue-600/10 text-blue-400 mr-3">8</span>
                Local Setup
            </h2>
            <p class="text-gray-600 dark:text-gray-400 mb-6 leading-relaxed">
                Set up your local development environment to work with the N8n document processing integration.
            </p>

            <div class="space-y-6">
                <!-- Step 1: Clone -->
                <div class="flex items-start space-x-4">
                    <div class="step-number bg-primary-600 text-white mt-0.5">1</div>
                    <div class="flex-1">
                        <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Clone the Repository</h4>
                        <div class="code-block bg-gray-950 rounded-lg p-4 overflow-x-auto">
                            <button class="copy-btn px-2 py-1 text-xs bg-gray-700 hover:bg-gray-600 text-gray-300 rounded transition-colors" onclick="copyCode(this)">
                                <i class="fas fa-copy mr-1"></i> Copy
                            </button>
                            <pre class="text-sm text-gray-300"><code>git clone git@github.com:your-org/soud-laravel.git
cd soud-laravel</code></pre>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Dependencies -->
                <div class="flex items-start space-x-4">
                    <div class="step-number bg-primary-600 text-white mt-0.5">2</div>
                    <div class="flex-1">
                        <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Install Dependencies</h4>
                        <div class="code-block bg-gray-950 rounded-lg p-4 overflow-x-auto">
                            <button class="copy-btn px-2 py-1 text-xs bg-gray-700 hover:bg-gray-600 text-gray-300 rounded transition-colors" onclick="copyCode(this)">
                                <i class="fas fa-copy mr-1"></i> Copy
                            </button>
                            <pre class="text-sm text-gray-300"><code>composer install
npm install && npm run build</code></pre>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Environment -->
                <div class="flex items-start space-x-4">
                    <div class="step-number bg-primary-600 text-white mt-0.5">3</div>
                    <div class="flex-1">
                        <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Configure Environment</h4>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">Add these N8n-specific variables to your <code class="text-xs bg-gray-800 text-primary-300 px-1 py-0.5 rounded">.env</code> file:</p>
                        <div class="code-block bg-gray-950 rounded-lg p-4 overflow-x-auto">
                            <button class="copy-btn px-2 py-1 text-xs bg-gray-700 hover:bg-gray-600 text-gray-300 rounded transition-colors" onclick="copyCode(this)">
                                <i class="fas fa-copy mr-1"></i> Copy
                            </button>
                            <pre class="text-sm text-gray-300"><code># N8n Integration
N8N_WEBHOOK_URL=http://localhost:5678/webhook/document-process
N8N_WEBHOOK_SECRET=your-hmac-secret-key-here
N8N_CALLBACK_URL=http://localhost:8000/api/n8n/callback
N8N_TIMEOUT=30
N8N_MAX_RETRIES=3</code></pre>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Migrations -->
                <div class="flex items-start space-x-4">
                    <div class="step-number bg-primary-600 text-white mt-0.5">4</div>
                    <div class="flex-1">
                        <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Run Migrations</h4>
                        <div class="code-block bg-gray-950 rounded-lg p-4 overflow-x-auto">
                            <button class="copy-btn px-2 py-1 text-xs bg-gray-700 hover:bg-gray-600 text-gray-300 rounded transition-colors" onclick="copyCode(this)">
                                <i class="fas fa-copy mr-1"></i> Copy
                            </button>
                            <pre class="text-sm text-gray-300"><code>php artisan migrate</code></pre>
                        </div>
                    </div>
                </div>

                <!-- Step 5: Import Workflows -->
                <div class="flex items-start space-x-4">
                    <div class="step-number bg-primary-600 text-white mt-0.5">5</div>
                    <div class="flex-1">
                        <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Import N8n Workflows</h4>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Download the workflow JSON files from <code class="text-xs bg-gray-800 text-primary-300 px-1 py-0.5 rounded">/downloads/n8n/</code> in the admin panel and import them into your local N8n instance.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- 3.2 N8n Workflow Setup -->
        <section id="n8n-workflow-setup" class="mb-16 scroll-mt-24">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2 flex items-center">
                <span class="step-number bg-blue-600/10 text-blue-400 mr-3">9</span>
                N8n Workflow Setup
            </h2>
            <p class="text-gray-600 dark:text-gray-400 mb-6 leading-relaxed">
                Configure N8n to receive webhooks from Laravel, process documents through supplier-specific
                pipelines, and send results back via callback.
            </p>

            <div class="space-y-4 mb-8">
                <div class="flex items-start space-x-4">
                    <div class="step-number bg-primary-600 text-white mt-0.5">1</div>
                    <div>
                        <h4 class="font-semibold text-gray-900 dark:text-white">Install N8n</h4>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Install N8n locally via npm (<code class="text-xs bg-gray-800 text-primary-300 px-1 py-0.5 rounded">npm install -g n8n</code>) or use the N8n Cloud service.</p>
                    </div>
                </div>
                <div class="flex items-start space-x-4">
                    <div class="step-number bg-primary-600 text-white mt-0.5">2</div>
                    <div>
                        <h4 class="font-semibold text-gray-900 dark:text-white">Import Workflow JSON Files</h4>
                        <p class="text-sm text-gray-500 dark:text-gray-400">In the N8n editor, go to <strong>Settings &rarr; Import Workflow</strong> and load the JSON files from the <code class="text-xs bg-gray-800 text-primary-300 px-1 py-0.5 rounded">/downloads/n8n/</code> directory.</p>
                    </div>
                </div>
                <div class="flex items-start space-x-4">
                    <div class="step-number bg-primary-600 text-white mt-0.5">3</div>
                    <div>
                        <h4 class="font-semibold text-gray-900 dark:text-white">Configure Webhook Credentials</h4>
                        <p class="text-sm text-gray-500 dark:text-gray-400">In the webhook trigger node, ensure the path matches your <code class="text-xs bg-gray-800 text-primary-300 px-1 py-0.5 rounded">N8N_WEBHOOK_URL</code> path.</p>
                    </div>
                </div>
                <div class="flex items-start space-x-4">
                    <div class="step-number bg-primary-600 text-white mt-0.5">4</div>
                    <div>
                        <h4 class="font-semibold text-gray-900 dark:text-white">Set HMAC Secret</h4>
                        <p class="text-sm text-gray-500 dark:text-gray-400">In the HMAC verification node, set the secret to the same value as your Laravel <code class="text-xs bg-gray-800 text-primary-300 px-1 py-0.5 rounded">N8N_WEBHOOK_SECRET</code>. Both sides must match exactly.</p>
                    </div>
                </div>
                <div class="flex items-start space-x-4">
                    <div class="step-number bg-primary-600 text-white mt-0.5">5</div>
                    <div>
                        <h4 class="font-semibold text-gray-900 dark:text-white">Activate Workflows</h4>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Toggle the workflow to <strong>Active</strong>. The webhook endpoint becomes live immediately.</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4">
                    <i class="fas fa-sitemap mr-2"></i> N8n Workflow Structure
                </h4>
                <div class="mermaid">
                    graph LR
                        A[Webhook Trigger] --> B[HMAC Verify]
                        B --> C[Switch: Supplier]
                        C --> D1[PDF Processor]
                        C --> D2[Image Processor]
                        C --> D3[Email Processor]
                        C --> D4[AIR Processor]
                        C --> D5[Fallback]
                        D1 --> E[Schema Normalizer]
                        D2 --> E
                        D3 --> E
                        D4 --> E
                        D5 --> E
                        E --> F[HTTP Callback]
                </div>
            </div>
        </section>

        <!-- 3.3 HMAC Security -->
        <section id="hmac-security" class="mb-16 scroll-mt-24">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2 flex items-center">
                <span class="step-number bg-blue-600/10 text-blue-400 mr-3">10</span>
                HMAC Security
            </h2>
            <p class="text-gray-600 dark:text-gray-400 mb-6 leading-relaxed">
                All communication between Laravel and N8n is secured with bidirectional HMAC-SHA256 signatures.
                Both outbound webhook requests and inbound callback responses are signed and verified to prevent
                tampering and replay attacks.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-pen-nib text-blue-400 mr-2"></i>
                        <span class="font-semibold text-sm text-gray-900 dark:text-white">Signing</span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">The sender computes <code class="text-xs bg-gray-800 text-blue-300 px-1 rounded">hash_hmac('sha256', payload, secret)</code> and attaches it as an HTTP header.</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-check-double text-green-400 mr-2"></i>
                        <span class="font-semibold text-sm text-gray-900 dark:text-white">Verification</span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">The receiver recalculates the HMAC using the same secret and compares it to the received header using timing-safe comparison.</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-shield-halved text-purple-400 mr-2"></i>
                        <span class="font-semibold text-sm text-gray-900 dark:text-white">Replay Protection</span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">A timestamp header is included and validated within a 5-minute window to prevent replay attacks.</p>
                </div>
            </div>

            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">HMAC Generation Example (PHP)</h3>
            <div class="code-block bg-gray-950 rounded-lg p-4 overflow-x-auto mb-6">
                <button class="copy-btn px-2 py-1 text-xs bg-gray-700 hover:bg-gray-600 text-gray-300 rounded transition-colors" onclick="copyCode(this)">
                    <i class="fas fa-copy mr-1"></i> Copy
                </button>
                <pre class="text-sm text-gray-300"><code>// Generate HMAC signature for outbound request
$payload   = json_encode($documentData);
$timestamp = now()->timestamp;
$secret    = config('services.n8n.webhook_secret');

$signature = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

// Attach headers to the HTTP request
$headers = [
    'X-Signature' => $signature,
    'X-Timestamp' => $timestamp,
    'Content-Type' => 'application/json',
];</code></pre>
            </div>

            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">HMAC Verification Example (PHP)</h3>
            <div class="code-block bg-gray-950 rounded-lg p-4 overflow-x-auto mb-6">
                <button class="copy-btn px-2 py-1 text-xs bg-gray-700 hover:bg-gray-600 text-gray-300 rounded transition-colors" onclick="copyCode(this)">
                    <i class="fas fa-copy mr-1"></i> Copy
                </button>
                <pre class="text-sm text-gray-300"><code>// Verify inbound callback signature
$receivedSignature = $request->header('X-Signature');
$receivedTimestamp = $request->header('X-Timestamp');
$payload           = $request->getContent();
$secret            = config('services.n8n.webhook_secret');

// Check timestamp is within 5-minute window
if (abs(now()->timestamp - $receivedTimestamp) > 300) {
    abort(401, 'Request timestamp expired');
}

// Recalculate and compare
$expectedSignature = hash_hmac('sha256', $receivedTimestamp . '.' . $payload, $secret);

if (!hash_equals($expectedSignature, $receivedSignature)) {
    abort(401, 'Invalid HMAC signature');
}</code></pre>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4">
                    <i class="fas fa-lock mr-2"></i> HMAC Verification Flow
                </h4>
                <div class="mermaid">
                    sequenceDiagram
                        participant Laravel
                        participant N8n

                        Note over Laravel: Generate HMAC
                        Laravel->>Laravel: hash_hmac('sha256', payload, secret)
                        Laravel->>N8n: POST + X-Signature + X-Timestamp
                        N8n->>N8n: Verify timestamp < 5min
                        N8n->>N8n: Recalculate HMAC
                        N8n->>N8n: Compare signatures
                        Note over N8n: Process if valid

                        Note over N8n: Generate callback HMAC
                        N8n->>Laravel: POST + X-Signature + X-Timestamp
                        Laravel->>Laravel: Verify timestamp < 5min
                        Laravel->>Laravel: Recalculate HMAC
                        Note over Laravel: Accept if valid
                </div>
            </div>
        </section>

        <!-- 3.4 Error Code Reference -->
        <section id="error-code-reference" class="mb-16 scroll-mt-24">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2 flex items-center">
                <span class="step-number bg-blue-600/10 text-blue-400 mr-3">11</span>
                Error Code Reference
            </h2>
            <p class="text-gray-600 dark:text-gray-400 mb-6 leading-relaxed">
                The system uses 18 standardized error codes grouped into three categories. Each code
                determines whether the system auto-retries, flags for admin review, or triggers an alert.
            </p>

            <!-- Transient Errors -->
            <h3 class="text-lg font-semibold text-yellow-400 mb-3 flex items-center">
                <i class="fas fa-clock mr-2"></i> Transient Errors (Auto-Retry)
            </h3>
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm table-striped">
                        <thead>
                            <tr class="border-b border-gray-700 bg-yellow-500/5">
                                <th class="text-left py-3 px-4 text-gray-400 font-medium">Code</th>
                                <th class="text-left py-3 px-4 text-gray-400 font-medium">Description</th>
                                <th class="text-left py-3 px-4 text-gray-400 font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-300">
                            <tr class="border-b border-gray-700/50">
                                <td class="py-2 px-4"><code class="text-xs bg-gray-900 text-yellow-300 px-1.5 py-0.5 rounded">ERR_TIMEOUT</code></td>
                                <td class="py-2 px-4">Processing exceeded the configured timeout</td>
                                <td class="py-2 px-4">Retry with backoff</td>
                            </tr>
                            <tr class="border-b border-gray-700/50">
                                <td class="py-2 px-4"><code class="text-xs bg-gray-900 text-yellow-300 px-1.5 py-0.5 rounded">ERR_N8N_UNAVAILABLE</code></td>
                                <td class="py-2 px-4">N8n server is unreachable or returned 5xx</td>
                                <td class="py-2 px-4">Retry with backoff</td>
                            </tr>
                            <tr class="border-b border-gray-700/50">
                                <td class="py-2 px-4"><code class="text-xs bg-gray-900 text-yellow-300 px-1.5 py-0.5 rounded">ERR_RATE_LIMITED</code></td>
                                <td class="py-2 px-4">N8n returned 429 (too many requests)</td>
                                <td class="py-2 px-4">Wait and retry</td>
                            </tr>
                            <tr class="border-b border-gray-700/50">
                                <td class="py-2 px-4"><code class="text-xs bg-gray-900 text-yellow-300 px-1.5 py-0.5 rounded">ERR_TEMP_FAILURE</code></td>
                                <td class="py-2 px-4">Temporary processing failure on the N8n side</td>
                                <td class="py-2 px-4">Retry with backoff</td>
                            </tr>
                            <tr class="border-b border-gray-700/50">
                                <td class="py-2 px-4"><code class="text-xs bg-gray-900 text-yellow-300 px-1.5 py-0.5 rounded">ERR_NETWORK</code></td>
                                <td class="py-2 px-4">Network connectivity issue between services</td>
                                <td class="py-2 px-4">Retry with backoff</td>
                            </tr>
                            <tr>
                                <td class="py-2 px-4"><code class="text-xs bg-gray-900 text-yellow-300 px-1.5 py-0.5 rounded">ERR_QUEUE_FULL</code></td>
                                <td class="py-2 px-4">N8n processing queue is at capacity</td>
                                <td class="py-2 px-4">Delay and retry</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Non-Transient Errors -->
            <h3 class="text-lg font-semibold text-red-400 mb-3 flex items-center">
                <i class="fas fa-circle-xmark mr-2"></i> Non-Transient Errors (Requires Review)
            </h3>
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm table-striped">
                        <thead>
                            <tr class="border-b border-gray-700 bg-red-500/5">
                                <th class="text-left py-3 px-4 text-gray-400 font-medium">Code</th>
                                <th class="text-left py-3 px-4 text-gray-400 font-medium">Description</th>
                                <th class="text-left py-3 px-4 text-gray-400 font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-300">
                            <tr class="border-b border-gray-700/50">
                                <td class="py-2 px-4"><code class="text-xs bg-gray-900 text-red-300 px-1.5 py-0.5 rounded">ERR_INVALID_DOC</code></td>
                                <td class="py-2 px-4">Document is malformed or unreadable</td>
                                <td class="py-2 px-4">Admin review</td>
                            </tr>
                            <tr class="border-b border-gray-700/50">
                                <td class="py-2 px-4"><code class="text-xs bg-gray-900 text-red-300 px-1.5 py-0.5 rounded">ERR_UNSUPPORTED_TYPE</code></td>
                                <td class="py-2 px-4">Document type is not supported for this supplier</td>
                                <td class="py-2 px-4">Admin review</td>
                            </tr>
                            <tr class="border-b border-gray-700/50">
                                <td class="py-2 px-4"><code class="text-xs bg-gray-900 text-red-300 px-1.5 py-0.5 rounded">ERR_CORRUPT_FILE</code></td>
                                <td class="py-2 px-4">File is corrupted and cannot be parsed</td>
                                <td class="py-2 px-4">Admin review, re-upload</td>
                            </tr>
                            <tr class="border-b border-gray-700/50">
                                <td class="py-2 px-4"><code class="text-xs bg-gray-900 text-red-300 px-1.5 py-0.5 rounded">ERR_AUTH_FAILED</code></td>
                                <td class="py-2 px-4">Third-party authentication failure (e.g., Gmail API)</td>
                                <td class="py-2 px-4">Reconfigure credentials</td>
                            </tr>
                            <tr class="border-b border-gray-700/50">
                                <td class="py-2 px-4"><code class="text-xs bg-gray-900 text-red-300 px-1.5 py-0.5 rounded">ERR_VALIDATION</code></td>
                                <td class="py-2 px-4">Extracted data failed schema validation</td>
                                <td class="py-2 px-4">Check schema config</td>
                            </tr>
                            <tr>
                                <td class="py-2 px-4"><code class="text-xs bg-gray-900 text-red-300 px-1.5 py-0.5 rounded">ERR_SUPPLIER_REJECTED</code></td>
                                <td class="py-2 px-4">Supplier-specific processing explicitly rejected the document</td>
                                <td class="py-2 px-4">Contact supplier</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- System Errors -->
            <h3 class="text-lg font-semibold text-purple-400 mb-3 flex items-center">
                <i class="fas fa-server mr-2"></i> System Errors (Alert DevOps)
            </h3>
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm table-striped">
                        <thead>
                            <tr class="border-b border-gray-700 bg-purple-500/5">
                                <th class="text-left py-3 px-4 text-gray-400 font-medium">Code</th>
                                <th class="text-left py-3 px-4 text-gray-400 font-medium">Description</th>
                                <th class="text-left py-3 px-4 text-gray-400 font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-300">
                            <tr class="border-b border-gray-700/50">
                                <td class="py-2 px-4"><code class="text-xs bg-gray-900 text-purple-300 px-1.5 py-0.5 rounded">ERR_INTERNAL</code></td>
                                <td class="py-2 px-4">Unexpected internal error in the processing pipeline</td>
                                <td class="py-2 px-4">Check logs, alert devs</td>
                            </tr>
                            <tr class="border-b border-gray-700/50">
                                <td class="py-2 px-4"><code class="text-xs bg-gray-900 text-purple-300 px-1.5 py-0.5 rounded">ERR_CONFIG</code></td>
                                <td class="py-2 px-4">Missing or invalid configuration (env vars, workflow settings)</td>
                                <td class="py-2 px-4">Fix configuration</td>
                            </tr>
                            <tr class="border-b border-gray-700/50">
                                <td class="py-2 px-4"><code class="text-xs bg-gray-900 text-purple-300 px-1.5 py-0.5 rounded">ERR_DB_FAILURE</code></td>
                                <td class="py-2 px-4">Database connection or query failure</td>
                                <td class="py-2 px-4">Check DB health</td>
                            </tr>
                            <tr class="border-b border-gray-700/50">
                                <td class="py-2 px-4"><code class="text-xs bg-gray-900 text-purple-300 px-1.5 py-0.5 rounded">ERR_STORAGE</code></td>
                                <td class="py-2 px-4">File storage read/write failure</td>
                                <td class="py-2 px-4">Check disk/S3 access</td>
                            </tr>
                            <tr class="border-b border-gray-700/50">
                                <td class="py-2 px-4"><code class="text-xs bg-gray-900 text-purple-300 px-1.5 py-0.5 rounded">ERR_HMAC_INVALID</code></td>
                                <td class="py-2 px-4">HMAC signature verification failed on callback</td>
                                <td class="py-2 px-4">Verify secrets match</td>
                            </tr>
                            <tr>
                                <td class="py-2 px-4"><code class="text-xs bg-gray-900 text-purple-300 px-1.5 py-0.5 rounded">ERR_CALLBACK_FAILED</code></td>
                                <td class="py-2 px-4">N8n was unable to deliver the callback to Laravel</td>
                                <td class="py-2 px-4">Check callback URL</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- 3.5 API Endpoints -->
        <section id="api-endpoints" class="mb-16 scroll-mt-24">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2 flex items-center">
                <span class="step-number bg-blue-600/10 text-blue-400 mr-3">12</span>
                API Endpoints
            </h2>
            <p class="text-gray-600 dark:text-gray-400 mb-6 leading-relaxed">
                Quick reference for the key API endpoints used in the document processing integration.
                All endpoints require authentication. See the
                <a href="/docs/developer" class="text-primary-400 hover:text-primary-300 underline">Developer API Documentation</a>
                for full request/response schemas.
            </p>

            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm table-striped">
                        <thead>
                            <tr class="border-b border-gray-700 bg-gray-800/50">
                                <th class="text-left py-3 px-4 text-gray-400 font-medium">Method</th>
                                <th class="text-left py-3 px-4 text-gray-400 font-medium">Endpoint</th>
                                <th class="text-left py-3 px-4 text-gray-400 font-medium">Description</th>
                                <th class="text-left py-3 px-4 text-gray-400 font-medium">Auth</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-300">
                            <tr class="border-b border-gray-700/50">
                                <td class="py-2 px-4"><span class="inline-block px-2 py-0.5 text-xs font-semibold rounded bg-green-500/10 text-green-400">POST</span></td>
                                <td class="py-2 px-4 font-mono text-xs text-primary-300">/api/documents/process</td>
                                <td class="py-2 px-4">Submit a document for processing</td>
                                <td class="py-2 px-4">Bearer token</td>
                            </tr>
                            <tr class="border-b border-gray-700/50">
                                <td class="py-2 px-4"><span class="inline-block px-2 py-0.5 text-xs font-semibold rounded bg-green-500/10 text-green-400">POST</span></td>
                                <td class="py-2 px-4 font-mono text-xs text-primary-300">/api/n8n/callback</td>
                                <td class="py-2 px-4">Receive N8n processing results</td>
                                <td class="py-2 px-4">HMAC signature</td>
                            </tr>
                            <tr class="border-b border-gray-700/50">
                                <td class="py-2 px-4"><span class="inline-block px-2 py-0.5 text-xs font-semibold rounded bg-blue-500/10 text-blue-400">GET</span></td>
                                <td class="py-2 px-4 font-mono text-xs text-primary-300">/api/documents/{id}/status</td>
                                <td class="py-2 px-4">Check processing status of a document</td>
                                <td class="py-2 px-4">Bearer token</td>
                            </tr>
                            <tr class="border-b border-gray-700/50">
                                <td class="py-2 px-4"><span class="inline-block px-2 py-0.5 text-xs font-semibold rounded bg-green-500/10 text-green-400">POST</span></td>
                                <td class="py-2 px-4 font-mono text-xs text-primary-300">/api/documents/{id}/retry</td>
                                <td class="py-2 px-4">Trigger manual retry for a failed document</td>
                                <td class="py-2 px-4">Bearer token</td>
                            </tr>
                            <tr class="border-b border-gray-700/50">
                                <td class="py-2 px-4"><span class="inline-block px-2 py-0.5 text-xs font-semibold rounded bg-blue-500/10 text-blue-400">GET</span></td>
                                <td class="py-2 px-4 font-mono text-xs text-primary-300">/api/admin/processing-logs</td>
                                <td class="py-2 px-4">View all processing logs (paginated)</td>
                                <td class="py-2 px-4">Admin token</td>
                            </tr>
                            <tr>
                                <td class="py-2 px-4"><span class="inline-block px-2 py-0.5 text-xs font-semibold rounded bg-orange-500/10 text-orange-400">PATCH</span></td>
                                <td class="py-2 px-4 font-mono text-xs text-primary-300">/api/admin/processing-logs/{id}/review</td>
                                <td class="py-2 px-4">Mark a processing log as reviewed</td>
                                <td class="py-2 px-4">Admin token</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- 3.6 Database Schema -->
        <section id="database-schema" class="mb-16 scroll-mt-24">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2 flex items-center">
                <span class="step-number bg-blue-600/10 text-blue-400 mr-3">13</span>
                Database Schema
            </h2>
            <p class="text-gray-600 dark:text-gray-400 mb-6 leading-relaxed">
                The integration uses three core tables to track documents, processing attempts, and errors.
                The entity relationship diagram below shows how they relate.
            </p>

            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 mb-6">
                <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4">
                    <i class="fas fa-database mr-2"></i> Entity Relationship Diagram
                </h4>
                <div class="mermaid">
                    erDiagram
                        DOCUMENTS ||--o{ DOCUMENT_PROCESSING_LOGS : has
                        DOCUMENT_PROCESSING_LOGS ||--o{ DOCUMENT_ERRORS : has
                        USERS ||--o{ DOCUMENT_PROCESSING_LOGS : reviews
                        USERS ||--o{ DOCUMENT_ERRORS : resolves

                        DOCUMENTS {
                            bigint id PK
                            string title
                            string type
                            string supplier_id
                            timestamp created_at
                        }
                        DOCUMENT_PROCESSING_LOGS {
                            bigint id PK
                            bigint document_id FK
                            string status
                            boolean needs_review
                            timestamp started_at
                            timestamp completed_at
                            int duration_ms
                            json input_payload
                            json output_data
                        }
                        DOCUMENT_ERRORS {
                            bigint id PK
                            bigint processing_log_id FK
                            enum error_type
                            string error_code
                            text error_message
                            int retry_count
                            timestamp resolved_at
                        }
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <h4 class="font-semibold text-sm text-gray-900 dark:text-white mb-2"><i class="fas fa-table text-blue-400 mr-2"></i>documents</h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Source documents submitted for processing. Links to supplier via <code class="text-xs bg-gray-800 text-primary-300 px-1 rounded">supplier_id</code>.</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <h4 class="font-semibold text-sm text-gray-900 dark:text-white mb-2"><i class="fas fa-table text-green-400 mr-2"></i>document_processing_logs</h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400">One row per processing attempt. Tracks status, timing, input/output payloads, and review state.</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <h4 class="font-semibold text-sm text-gray-900 dark:text-white mb-2"><i class="fas fa-table text-red-400 mr-2"></i>document_errors</h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Error records linked to processing logs. Tracks error type, code, retry count, and resolution.</p>
                </div>
            </div>
        </section>

        <!-- 3.7 Testing -->
        <section id="testing" class="mb-16 scroll-mt-24">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2 flex items-center">
                <span class="step-number bg-blue-600/10 text-blue-400 mr-3">14</span>
                Testing
            </h2>
            <p class="text-gray-600 dark:text-gray-400 mb-6 leading-relaxed">
                The integration includes comprehensive unit, integration, and load tests.
                Twenty fixture files are provided for consistent test data across all test suites.
            </p>

            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Running Unit Tests</h3>
            <div class="code-block bg-gray-950 rounded-lg p-4 overflow-x-auto mb-6">
                <button class="copy-btn px-2 py-1 text-xs bg-gray-700 hover:bg-gray-600 text-gray-300 rounded transition-colors" onclick="copyCode(this)">
                    <i class="fas fa-copy mr-1"></i> Copy
                </button>
                <pre class="text-sm text-gray-300"><code># Run all N8n-related tests
php artisan test --filter=N8n

# Run specific test suites
php artisan test --filter=N8nWebhookServiceTest
php artisan test --filter=N8nCallbackControllerTest
php artisan test --filter=HmacVerificationTest</code></pre>
            </div>

            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Running Integration Tests</h3>
            <div class="code-block bg-gray-950 rounded-lg p-4 overflow-x-auto mb-6">
                <button class="copy-btn px-2 py-1 text-xs bg-gray-700 hover:bg-gray-600 text-gray-300 rounded transition-colors" onclick="copyCode(this)">
                    <i class="fas fa-copy mr-1"></i> Copy
                </button>
                <pre class="text-sm text-gray-300"><code># Integration tests require a running N8n instance
# Set N8N_TEST_URL in your .env.testing file

php artisan test --filter=N8nIntegration --env=testing</code></pre>
            </div>

            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Test Fixtures</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                Twenty fixture files are available in <code class="text-xs bg-gray-800 text-primary-300 px-1.5 py-0.5 rounded">tests/fixtures/n8n/</code>,
                covering all supported document types and supplier configurations.
            </p>

            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Load Testing</h3>
            <div class="code-block bg-gray-950 rounded-lg p-4 overflow-x-auto mb-6">
                <button class="copy-btn px-2 py-1 text-xs bg-gray-700 hover:bg-gray-600 text-gray-300 rounded transition-colors" onclick="copyCode(this)">
                    <i class="fas fa-copy mr-1"></i> Copy
                </button>
                <pre class="text-sm text-gray-300"><code># Run load test with 100 concurrent documents
php artisan n8n:load-test --count=100 --concurrency=10</code></pre>
            </div>

            <div class="bg-blue-500/5 border border-blue-500/20 rounded-lg p-4">
                <div class="flex items-start">
                    <i class="fas fa-arrow-up-right-from-square text-blue-400 mt-0.5 mr-3"></i>
                    <div>
                        <h4 class="font-semibold text-sm text-blue-300 mb-1">Full Testing Documentation</h4>
                        <p class="text-xs text-gray-400">For comprehensive testing guides including mock setup, fixture generation, and CI/CD integration, visit the
                            <a href="/docs/n8n-testing" class="text-primary-400 hover:text-primary-300 underline">N8n Testing Documentation</a> page.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <hr class="section-divider">

        
        
        

        <div class="mb-6">
            <div class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-orange-600/10 text-orange-400 border border-orange-500/20 uppercase tracking-wider">
                <i class="fas fa-wrench mr-1.5"></i> Part 4 &mdash; Troubleshooting
            </div>
        </div>

        <!-- 4.1 Common Issues -->
        <section id="common-issues" class="mb-16 scroll-mt-24">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2 flex items-center">
                <span class="step-number bg-orange-600/10 text-orange-400 mr-3">15</span>
                Common Issues
            </h2>
            <p class="text-gray-600 dark:text-gray-400 mb-6 leading-relaxed">
                Solutions to the most frequently encountered problems with the N8n integration.
            </p>

            <div class="space-y-4">
                <!-- Issue 1 -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                    <div class="flex items-start space-x-3 mb-3">
                        <i class="fas fa-circle-question text-red-400 mt-1"></i>
                        <h4 class="font-semibold text-gray-900 dark:text-white">N8n is not receiving webhooks</h4>
                    </div>
                    <div class="ml-7 space-y-2 text-sm text-gray-500 dark:text-gray-400">
                        <p><strong class="text-gray-300">Check <code class="text-xs bg-gray-900 text-primary-300 px-1 rounded">N8N_WEBHOOK_URL</code></strong> &mdash; ensure the URL is correct and accessible from the Laravel server.</p>
                        <p><strong class="text-gray-300">Firewall rules</strong> &mdash; verify that port 5678 (default N8n) is not blocked by server or cloud firewall.</p>
                        <p><strong class="text-gray-300">Workflow active</strong> &mdash; confirm the N8n workflow is toggled to &ldquo;Active&rdquo; mode in the N8n editor.</p>
                        <p><strong class="text-gray-300">Check N8n logs</strong> &mdash; look at N8n container logs for incoming request errors.</p>
                    </div>
                </div>

                <!-- Issue 2 -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                    <div class="flex items-start space-x-3 mb-3">
                        <i class="fas fa-circle-question text-red-400 mt-1"></i>
                        <h4 class="font-semibold text-gray-900 dark:text-white">HMAC verification is failing</h4>
                    </div>
                    <div class="ml-7 space-y-2 text-sm text-gray-500 dark:text-gray-400">
                        <p><strong class="text-gray-300">Secrets must match</strong> &mdash; ensure <code class="text-xs bg-gray-900 text-primary-300 px-1 rounded">N8N_WEBHOOK_SECRET</code> in Laravel matches the secret configured in the N8n HMAC verification node exactly.</p>
                        <p><strong class="text-gray-300">Clock synchronization</strong> &mdash; both servers must have synced system clocks (NTP). The 5-minute window check will fail if clocks drift.</p>
                        <p><strong class="text-gray-300">Timestamp format</strong> &mdash; verify the timestamp is a Unix epoch integer, not an ISO string.</p>
                    </div>
                </div>

                <!-- Issue 3 -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                    <div class="flex items-start space-x-3 mb-3">
                        <i class="fas fa-circle-question text-red-400 mt-1"></i>
                        <h4 class="font-semibold text-gray-900 dark:text-white">Documents stuck in &ldquo;Processing&rdquo; state</h4>
                    </div>
                    <div class="ml-7 space-y-2 text-sm text-gray-500 dark:text-gray-400">
                        <p><strong class="text-gray-300">N8n server health</strong> &mdash; check if the N8n server is running and responsive.</p>
                        <p><strong class="text-gray-300">Callback URL</strong> &mdash; verify <code class="text-xs bg-gray-900 text-primary-300 px-1 rounded">N8N_CALLBACK_URL</code> is correct and reachable from the N8n server.</p>
                        <p><strong class="text-gray-300">Timeout settings</strong> &mdash; increase <code class="text-xs bg-gray-900 text-primary-300 px-1 rounded">N8N_TIMEOUT</code> if processing large documents.</p>
                        <p><strong class="text-gray-300">N8n execution log</strong> &mdash; check the N8n execution history for error details in the failing node.</p>
                    </div>
                </div>

                <!-- Issue 4 -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                    <div class="flex items-start space-x-3 mb-3">
                        <i class="fas fa-circle-question text-red-400 mt-1"></i>
                        <h4 class="font-semibold text-gray-900 dark:text-white">High error rates across multiple suppliers</h4>
                    </div>
                    <div class="ml-7 space-y-2 text-sm text-gray-500 dark:text-gray-400">
                        <p><strong class="text-gray-300">Supplier configurations</strong> &mdash; verify that all supplier routing rules in the N8n Switch node are correctly defined.</p>
                        <p><strong class="text-gray-300">Document formats</strong> &mdash; confirm documents match the expected format for each supplier processor.</p>
                        <p><strong class="text-gray-300">External services</strong> &mdash; check if Apache Tika, Gutenberg OCR, or Gmail API are healthy and responding.</p>
                    </div>
                </div>

                <!-- Issue 5 -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                    <div class="flex items-start space-x-3 mb-3">
                        <i class="fas fa-circle-question text-red-400 mt-1"></i>
                        <h4 class="font-semibold text-gray-900 dark:text-white">Slow processing times</h4>
                    </div>
                    <div class="ml-7 space-y-2 text-sm text-gray-500 dark:text-gray-400">
                        <p><strong class="text-gray-300">N8n resources</strong> &mdash; check CPU and memory usage on the N8n server. Increase resources if constrained.</p>
                        <p><strong class="text-gray-300">Concurrent execution limits</strong> &mdash; N8n has a default concurrency limit. Increase <code class="text-xs bg-gray-900 text-primary-300 px-1 rounded">EXECUTIONS_PROCESS</code> in N8n environment if needed.</p>
                        <p><strong class="text-gray-300">Document size</strong> &mdash; very large documents (>50MB) may require dedicated processing capacity or pre-splitting.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- 4.2 Debug Checklist -->
        <section id="debug-checklist" class="mb-16 scroll-mt-24">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2 flex items-center">
                <span class="step-number bg-orange-600/10 text-orange-400 mr-3">16</span>
                Debug Checklist
            </h2>
            <p class="text-gray-600 dark:text-gray-400 mb-6 leading-relaxed">
                Follow this checklist when investigating any processing issue. Work through each item in order
                to systematically isolate the root cause.
            </p>

            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                <ol class="space-y-3">
                    <li class="flex items-start space-x-3">
                        <div class="step-number bg-primary-600 text-white text-xs mt-0.5">1</div>
                        <p class="text-sm text-gray-300"><strong>Check the processing log status</strong> &mdash; go to the admin processing logs page and identify the exact status and error code.</p>
                    </li>
                    <li class="flex items-start space-x-3">
                        <div class="step-number bg-primary-600 text-white text-xs mt-0.5">2</div>
                        <p class="text-sm text-gray-300"><strong>Review the input payload</strong> &mdash; open the processing log detail to see the exact payload sent to N8n.</p>
                    </li>
                    <li class="flex items-start space-x-3">
                        <div class="step-number bg-primary-600 text-white text-xs mt-0.5">3</div>
                        <p class="text-sm text-gray-300"><strong>Verify N8n is reachable</strong> &mdash; from the Laravel server, run <code class="text-xs bg-gray-900 text-primary-300 px-1 rounded">curl -s -o /dev/null -w "%{'{'}http_code{'}'}" $N8N_WEBHOOK_URL</code> and confirm a 200 response.</p>
                    </li>
                    <li class="flex items-start space-x-3">
                        <div class="step-number bg-primary-600 text-white text-xs mt-0.5">4</div>
                        <p class="text-sm text-gray-300"><strong>Check N8n execution history</strong> &mdash; in the N8n editor, open the execution list and inspect the failing run for detailed node-by-node output.</p>
                    </li>
                    <li class="flex items-start space-x-3">
                        <div class="step-number bg-primary-600 text-white text-xs mt-0.5">5</div>
                        <p class="text-sm text-gray-300"><strong>Verify HMAC configuration</strong> &mdash; confirm both sides share the same secret by checking <code class="text-xs bg-gray-900 text-primary-300 px-1 rounded">.env</code> and the N8n credential node.</p>
                    </li>
                    <li class="flex items-start space-x-3">
                        <div class="step-number bg-primary-600 text-white text-xs mt-0.5">6</div>
                        <p class="text-sm text-gray-300"><strong>Check callback delivery</strong> &mdash; review Laravel logs (<code class="text-xs bg-gray-900 text-primary-300 px-1 rounded">storage/logs/laravel.log</code>) for incoming callback entries or errors.</p>
                    </li>
                    <li class="flex items-start space-x-3">
                        <div class="step-number bg-primary-600 text-white text-xs mt-0.5">7</div>
                        <p class="text-sm text-gray-300"><strong>Inspect error records</strong> &mdash; query the <code class="text-xs bg-gray-900 text-primary-300 px-1 rounded">document_errors</code> table for the processing log ID to see retry count and error details.</p>
                    </li>
                    <li class="flex items-start space-x-3">
                        <div class="step-number bg-primary-600 text-white text-xs mt-0.5">8</div>
                        <p class="text-sm text-gray-300"><strong>Check external services</strong> &mdash; verify that dependent services (Tika, OCR, Gmail API) are healthy and responding within expected timeframes.</p>
                    </li>
                    <li class="flex items-start space-x-3">
                        <div class="step-number bg-primary-600 text-white text-xs mt-0.5">9</div>
                        <p class="text-sm text-gray-300"><strong>Review server resources</strong> &mdash; check disk space, memory, and CPU usage on both the Laravel server and N8n server.</p>
                    </li>
                    <li class="flex items-start space-x-3">
                        <div class="step-number bg-primary-600 text-white text-xs mt-0.5">10</div>
                        <p class="text-sm text-gray-300"><strong>Attempt a manual retry</strong> &mdash; if the issue appears resolved, trigger a retry from the admin panel or via <code class="text-xs bg-gray-900 text-primary-300 px-1 rounded">POST /api/documents/{'{'}id{'}'}/retry</code>.</p>
                    </li>
                </ol>
            </div>
        </section>

        <!-- 4.3 Support Resources -->
        <section id="support-resources" class="mb-16 scroll-mt-24">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2 flex items-center">
                <span class="step-number bg-orange-600/10 text-orange-400 mr-3">17</span>
                Support Resources
            </h2>
            <p class="text-gray-600 dark:text-gray-400 mb-6 leading-relaxed">
                Additional documentation and resources for the N8n document processing integration.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <a href="/docs/n8n-hub" class="block bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 hover:border-primary-500 transition-colors">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-home text-primary-400 mr-2"></i>
                        <span class="font-semibold text-sm text-gray-900 dark:text-white">Documentation Hub</span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Central hub linking to all documentation pages.</p>
                </a>
                <a href="/docs/developer" class="block bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 hover:border-blue-500 transition-colors">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-code text-blue-400 mr-2"></i>
                        <span class="font-semibold text-sm text-gray-900 dark:text-white">Developer API Documentation</span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Full API reference with request/response schemas and examples.</p>
                </a>
                <a href="/docs/n8n-processing" class="block bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 hover:border-orange-500 transition-colors">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-cogs text-orange-400 mr-2"></i>
                        <span class="font-semibold text-sm text-gray-900 dark:text-white">N8n Processing Guide</span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Detailed breakdown of the document processing workflow and supplier routing.</p>
                </a>
                <a href="/docs/n8n-complete" class="block bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 hover:border-purple-500 transition-colors">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-book text-purple-400 mr-2"></i>
                        <span class="font-semibold text-sm text-gray-900 dark:text-white">Complete N8n Documentation</span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Comprehensive reference covering every aspect of the integration in a single page.</p>
                </a>
                <a href="/docs/n8n-testing" class="block bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 hover:border-green-500 transition-colors">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-vial text-green-400 mr-2"></i>
                        <span class="font-semibold text-sm text-gray-900 dark:text-white">Testing Documentation</span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Unit, integration, and load testing guides with fixture data and CI/CD integration.</p>
                </a>
                <a href="/docs/n8n-changelog" class="block bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 hover:border-yellow-500 transition-colors">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-clock-rotate-left text-yellow-400 mr-2"></i>
                        <span class="font-semibold text-sm text-gray-900 dark:text-white">Changelog</span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Version history and release notes for the N8n integration.</p>
                </a>
            </div>
        </section>

    </main>

    <!-- Footer -->
    <footer class="main-content border-t border-gray-200 dark:border-gray-700 py-8 mt-8">
        <div class="px-6 sm:px-10 lg:px-16 max-w-5xl">
            <div class="flex flex-col sm:flex-row justify-between items-center text-sm text-gray-500 dark:text-gray-400">
                <p>N8n Integration User Guide &mdash; City Tour Development Team</p>
                <p>&copy; <?php echo e(date('Y')); ?> All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Initialize Mermaid with dark theme
        mermaid.initialize({
            startOnLoad: true,
            theme: 'dark',
            themeVariables: {
                darkMode: true,
                background: '#1f2937',
                primaryColor: '#3b82f6',
                primaryTextColor: '#e5e7eb',
                primaryBorderColor: '#4b5563',
                lineColor: '#6b7280',
                secondaryColor: '#1e40af',
                tertiaryColor: '#111827',
                fontFamily: 'Inter, sans-serif',
                fontSize: '14px',
            },
            flowchart: {
                useMaxWidth: true,
                htmlLabels: true,
                curve: 'basis',
            },
            sequence: {
                useMaxWidth: true,
                actorMargin: 60,
                messageMargin: 40,
            }
        });

        // Dark Mode Toggle
        const darkModeToggle = document.getElementById('darkModeToggle');
        darkModeToggle.addEventListener('click', () => {
            document.documentElement.classList.toggle('dark');
            localStorage.setItem('darkMode', document.documentElement.classList.contains('dark'));
        });

        // Restore dark mode preference
        if (localStorage.getItem('darkMode') === 'false') {
            document.documentElement.classList.remove('dark');
        }

        // Mobile Sidebar Toggle
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            sidebarOverlay.classList.toggle('open');
        });

        sidebarOverlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            sidebarOverlay.classList.remove('open');
        });

        // Close sidebar on link click (mobile)
        sidebar.querySelectorAll('.sidebar-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 1024) {
                    sidebar.classList.remove('open');
                    sidebarOverlay.classList.remove('open');
                }
            });
        });

        // Active sidebar link highlighting on scroll
        const sections = document.querySelectorAll('section[id]');
        const sidebarLinks = document.querySelectorAll('.sidebar-link[data-section]');

        function highlightActiveLink() {
            let currentSection = '';
            const scrollPosition = window.scrollY + 120;

            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.offsetHeight;

                if (scrollPosition >= sectionTop && scrollPosition < sectionTop + sectionHeight) {
                    currentSection = section.getAttribute('id');
                }
            });

            sidebarLinks.forEach(link => {
                link.classList.remove('active');
                if (link.dataset.section === currentSection) {
                    link.classList.add('active');
                }
            });
        }

        window.addEventListener('scroll', highlightActiveLink);
        highlightActiveLink();

        // Copy code button functionality
        function copyCode(button) {
            const codeBlock = button.closest('.code-block');
            const code = codeBlock.querySelector('code').innerText;

            navigator.clipboard.writeText(code).then(() => {
                const originalHTML = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check mr-1"></i> Copied!';
                button.classList.add('bg-green-600');
                button.classList.remove('bg-gray-700');

                setTimeout(() => {
                    button.innerHTML = originalHTML;
                    button.classList.remove('bg-green-600');
                    button.classList.add('bg-gray-700');
                }, 2000);
            });
        }
    </script>

</body>
</html>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/docs/n8n-user-guide.blade.php ENDPATH**/ ?>