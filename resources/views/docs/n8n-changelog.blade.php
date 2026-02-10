<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>N8n Integration Changelog - Developer Documentation</title>
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

        .phase-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.025em;
            text-transform: uppercase;
        }

        .phase-1 { background-color: #dbeafe; color: #1e40af; }
        .phase-2 { background-color: #d1fae5; color: #065f46; }
        .phase-3 { background-color: #fef3c7; color: #92400e; }
        .phase-4 { background-color: #fce7f3; color: #9f1239; }
        .phase-5 { background-color: #ede9fe; color: #5b21b6; }

        .file-list-item {
            transition: background-color 0.15s;
        }

        .file-list-item:hover {
            background-color: rgba(59, 130, 246, 0.05);
        }

        .dark .file-list-item:hover {
            background-color: rgba(59, 130, 246, 0.1);
        }

        .timeline-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid;
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
                <h1 class="text-xl font-bold text-gray-900 dark:text-white">N8n Integration Changelog</h1>
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
                <a href="/docs/n8n-processing" class="text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300">Processing Docs</a>
                <a href="/docs/n8n-complete" class="text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300">Complete Docs</a>
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
                    <a href="#phase-1" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-drafting-compass w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Phase 1: Foundation
                    </a>
                    <a href="#phase-2" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-plug w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Phase 2: Integration
                    </a>
                    <a href="#phase-3" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-shield-alt w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Phase 3: Security
                    </a>
                    <a href="#phase-4" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-exclamation-triangle w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Phase 4: Observability
                    </a>
                    <a href="#phase-5" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-vial w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Phase 5: Testing
                    </a>
                    <a href="#architecture" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-project-diagram w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Architecture Diagram
                    </a>
                    <a href="#n8n-workflows" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-cogs w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        N8n Workflow Files
                    </a>
                    <a href="#configuration" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-cog w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Configuration
                    </a>
                    <a href="#database-schema" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-database w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Database Schema
                    </a>
                </nav>
            </div>

            <!-- Main content -->
            <div class="mt-8 lg:mt-0 lg:col-span-9">
                <div class="prose prose-blue max-w-none dark:prose-invert">
                    <div class="bg-gradient-to-r from-primary-600 to-blue-600 rounded-xl shadow-lg p-8 mb-12 text-white">
                        <h1 class="text-4xl font-extrabold mb-4">N8n Integration Changelog</h1>
                        <p class="text-lg opacity-90 max-w-3xl">
                            Complete developer changelog documenting all changes across all phases of the N8n Document Processing integration. Every file, migration, service, and workflow node -- from initial planning through production testing.
                        </p>
                        <div class="mt-6 flex flex-wrap gap-3">
                            <span class="phase-badge phase-1">Phase 1</span>
                            <span class="phase-badge phase-2">Phase 2</span>
                            <span class="phase-badge phase-3">Phase 3</span>
                            <span class="phase-badge phase-4">Phase 4</span>
                            <span class="phase-badge phase-5">Phase 5</span>
                        </div>
                    </div>

                    <!-- ========== OVERVIEW ========== -->
                    <section id="overview" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">Overview</h2>
                        <p class="text-lg text-gray-700 dark:text-gray-300 mb-4">
                            The N8n Document Processing integration was built across 5 phases, from architectural planning through comprehensive test coverage. This changelog documents every file created, every migration run, and every design decision made during the build.
                        </p>

                        <div class="bg-blue-50 dark:bg-blue-900/30 border-l-4 border-blue-400 p-4 rounded-md mb-6">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-info-circle text-blue-400"></i>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-semibold text-blue-800 dark:text-blue-200 mb-2">Quick Summary</h3>
                                    <p class="text-sm text-blue-700 dark:text-blue-300">
                                        5 phases | 60+ files created | 5 database tables | 18 error codes | 18-node N8n workflow | 59+ automated tests
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-4 rounded-md text-center">
                                <div class="text-3xl font-extrabold text-primary-600 dark:text-primary-400 mb-1">5</div>
                                <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Development Phases</div>
                            </div>
                            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-4 rounded-md text-center">
                                <div class="text-3xl font-extrabold text-green-600 dark:text-green-400 mb-1">60+</div>
                                <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Files Created</div>
                            </div>
                            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-4 rounded-md text-center">
                                <div class="text-3xl font-extrabold text-purple-600 dark:text-purple-400 mb-1">59+</div>
                                <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Automated Tests</div>
                            </div>
                        </div>

                        <!-- Phase Timeline -->
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6 mb-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Phase Timeline</h3>
                            <div class="space-y-4">
                                <div class="flex items-start space-x-4">
                                    <div class="timeline-dot border-blue-500 bg-blue-100 dark:bg-blue-900 mt-1 flex-shrink-0"></div>
                                    <div>
                                        <span class="phase-badge phase-1">Phase 1</span>
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white ml-2">Foundation &amp; Planning</span>
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Webhook contract design, error taxonomy, supplier routing architecture</p>
                                    </div>
                                </div>
                                <div class="flex items-start space-x-4">
                                    <div class="timeline-dot border-green-500 bg-green-100 dark:bg-green-900 mt-1 flex-shrink-0"></div>
                                    <div>
                                        <span class="phase-badge phase-2">Phase 2</span>
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white ml-2">Integration</span>
                                        <span class="text-xs font-mono text-gray-500 dark:text-gray-400 ml-2">bc82f50f</span>
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Controllers, models, N8n workflow, Docker services, admin dashboard</p>
                                    </div>
                                </div>
                                <div class="flex items-start space-x-4">
                                    <div class="timeline-dot border-yellow-500 bg-yellow-100 dark:bg-yellow-900 mt-1 flex-shrink-0"></div>
                                    <div>
                                        <span class="phase-badge phase-3">Phase 3</span>
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white ml-2">Security &amp; Reliability</span>
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">HMAC signing service, middleware, rate limiting, audit logging</p>
                                    </div>
                                </div>
                                <div class="flex items-start space-x-4">
                                    <div class="timeline-dot border-pink-500 bg-pink-100 dark:bg-pink-900 mt-1 flex-shrink-0"></div>
                                    <div>
                                        <span class="phase-badge phase-4">Phase 4</span>
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white ml-2">Error Handling &amp; Observability</span>
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Execution tracking, error registry, alerting, analytics dashboard</p>
                                    </div>
                                </div>
                                <div class="flex items-start space-x-4">
                                    <div class="timeline-dot border-purple-500 bg-purple-100 dark:bg-purple-900 mt-1 flex-shrink-0"></div>
                                    <div>
                                        <span class="phase-badge phase-5">Phase 5</span>
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white ml-2">Testing &amp; Documentation</span>
                                        <span class="text-xs font-mono text-gray-500 dark:text-gray-400 ml-2">6673f86c</span>
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">E2E tests, contract tests, load tests, fixtures, documentation</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- ========== PHASE 1 ========== -->
                    <section id="phase-1" class="mb-12">
                        <div class="flex items-center space-x-3 mb-6">
                            <span class="phase-badge phase-1">Phase 1</span>
                            <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Foundation &amp; Planning</h2>
                        </div>

                        <p class="text-gray-700 dark:text-gray-300 mb-6">
                            Phase 1 established the architectural foundation for the integration. No code was committed; this phase produced the design contracts and patterns that guided all subsequent implementation.
                        </p>

                        <div class="space-y-6">
                            <!-- Webhook Contract -->
                            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Webhook Contract Design</h3>
                                </div>
                                <div class="px-6 py-4">
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                        Designed the asynchronous 202 pattern: Laravel submits documents to N8n and immediately receives a <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded">202 Accepted</code> response with a tracking <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded">document_id</code>. N8n processes the document asynchronously, then calls back to Laravel with structured extraction results.
                                    </p>
                                    <div class="code-block">
                                        <pre class="bg-gray-100 dark:bg-gray-800 p-3 rounded text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>Request Flow:
  Laravel POST /api/documents/process  -->  N8n Webhook (202 Accepted)
       |                                        |
       v                                        v
  Store DocumentProcessingLog            Async: Extract, Normalize
  (status: pending)                             |
       ^                                        v
       |                                  POST /api/webhooks/n8n/extraction
       +--- Update log (status: completed) <----+</code></pre>
                                        <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                            <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- N8n Workflow Analysis -->
                            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">N8n Workflow Pattern Analysis</h3>
                                </div>
                                <div class="px-6 py-4">
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                        Analyzed the production N8n workflow <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded">TAHzFBF3QC9tX4MZ</code> to understand existing patterns, then designed the supplier document processing workflow to follow the same conventions (webhook trigger, HMAC validation, conditional routing, HTTP callback).
                                    </p>
                                </div>
                            </div>

                            <!-- Error Code Taxonomy -->
                            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Error Code Taxonomy</h3>
                                </div>
                                <div class="px-6 py-4">
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                        Defined 18 error codes across 3 categories. Each code has a severity level, retry policy, and resolution hint for operators.
                                    </p>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-md p-3">
                                            <h4 class="text-sm font-semibold text-yellow-800 dark:text-yellow-200 mb-2">Transient Errors</h4>
                                            <ul class="text-xs text-yellow-700 dark:text-yellow-300 space-y-1">
                                                <li>ERR_TIMEOUT - Processing timeout</li>
                                                <li>ERR_CONN_REFUSED - Connection refused</li>
                                                <li>ERR_SERVICE_UNAVAIL - Service down</li>
                                                <li>ERR_RATE_LIMITED - Rate limited</li>
                                                <li>ERR_TEMP_FAILURE - Temporary failure</li>
                                                <li>ERR_NETWORK - Network error</li>
                                            </ul>
                                        </div>
                                        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md p-3">
                                            <h4 class="text-sm font-semibold text-red-800 dark:text-red-200 mb-2">Non-Transient Errors</h4>
                                            <ul class="text-xs text-red-700 dark:text-red-300 space-y-1">
                                                <li>ERR_INVALID_DOC - Corrupt document</li>
                                                <li>ERR_UNSUPPORTED - Unsupported type</li>
                                                <li>ERR_PARSE_FAIL - Parse failure</li>
                                                <li>ERR_SCHEMA_MISMATCH - Bad schema</li>
                                                <li>ERR_AUTH_FAIL - Authentication error</li>
                                                <li>ERR_FORBIDDEN - Access denied</li>
                                            </ul>
                                        </div>
                                        <div class="bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 rounded-md p-3">
                                            <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2">System Errors</h4>
                                            <ul class="text-xs text-gray-700 dark:text-gray-300 space-y-1">
                                                <li>ERR_N8N_INTERNAL - N8n crash</li>
                                                <li>ERR_CALLBACK_FAIL - Callback failed</li>
                                                <li>ERR_CONFIG - Misconfiguration</li>
                                                <li>ERR_DISK_FULL - Storage full</li>
                                                <li>ERR_MEMORY - Out of memory</li>
                                                <li>ERR_UNKNOWN - Unknown error</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Supplier Routing -->
                            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Supplier Routing Architecture</h3>
                                </div>
                                <div class="px-6 py-4">
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                        Chose a single-workflow architecture with a Switch node that routes documents based on <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded">document_type</code>. This avoids workflow proliferation and allows centralized HMAC validation and schema normalization.
                                    </p>
                                    <div class="code-block">
                                        <pre class="bg-gray-100 dark:bg-gray-800 p-3 rounded text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>Webhook Trigger
    |
    +-- HMAC Validation (reject invalid)
    |
    +-- Switch Node (document_type)
        |
        +-- "pdf"   --> Tika PDF Processor
        +-- "image" --> Gutenberg OCR Processor
        +-- "email" --> Gmail API Processor
        +-- "air"   --> Laravel AIR Parser Fallback
        |
        +-- Schema Normalizer (unified output)
        |
        +-- Compute Callback HMAC --> POST to Laravel</code></pre>
                                        <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                            <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- ========== PHASE 2 ========== -->
                    <section id="phase-2" class="mb-12">
                        <div class="flex items-center space-x-3 mb-6">
                            <span class="phase-badge phase-2">Phase 2</span>
                            <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Integration</h2>
                            <span class="text-xs font-mono bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 px-2 py-1 rounded">Commit bc82f50f</span>
                        </div>

                        <p class="text-gray-700 dark:text-gray-300 mb-6">
                            Phase 2 implemented the core integration: Laravel controllers, Eloquent models, database migrations, the 18-node N8n workflow, Docker extraction services, and the admin manual intervention dashboard.
                        </p>

                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Files Created</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">17 files across controllers, models, migrations, workflows, and views</p>
                            </div>
                            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                                <!-- Laravel Controllers -->
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <span class="method-badge http-post mr-3 mt-0.5">POST</span>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">app/Http/Controllers/Api/DocumentProcessingController.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">POST /api/documents/process endpoint. Signs requests with HMAC, queues documents to N8n, returns 202 Accepted with document_id for tracking.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <span class="method-badge http-post mr-3 mt-0.5">POST</span>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">app/Http/Controllers/Api/Webhooks/N8nCallbackController.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">POST /api/webhooks/n8n/extraction callback handler. Validates HMAC signature, enforces replay attack protection (5-minute window), detects duplicate callbacks (409 Conflict).</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Model -->
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-cube text-gray-400 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">app/Models/DocumentProcessingLog.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Eloquent model with scopes (pending, failed, completed) and review workflow methods. UUID document_id, status tracking, extraction results stored as JSON.</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Migration -->
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-database text-gray-400 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">database/migrations/2026_02_10_120000_create_document_processing_logs_table.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Creates document_processing_logs table with UUID document_id, status enum, supplier_id, document_type, extraction_results JSON, error tracking columns.</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- N8n Workflow -->
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-project-diagram text-gray-400 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">n8n/workflows/supplier-document-processing.json</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Complete 18-node N8n workflow: Webhook trigger, HMAC validation, supplier router (Switch node), 4 extraction paths, schema normalizer, callback with HMAC signature.</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- N8n Nodes -->
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-file-pdf text-red-400 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">n8n/nodes/pdf-processor.json</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Apache Tika integration node. Connects to Tika server on port 9998 for PDF text extraction and metadata parsing.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-image text-green-400 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">n8n/nodes/image-processor.json</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Gutenberg OCR integration node. Connects to Gutenberg server on port 8080 for image-to-text optical character recognition.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-envelope text-yellow-400 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">n8n/nodes/email-processor.json</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Gmail API integration node. Fetches and parses email content including attachments for booking data extraction.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-plane text-blue-400 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">n8n/nodes/air-processor.json</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Laravel AirFileParser fallback node. Calls back to Laravel's existing AIR parser for Amadeus GDS format files (returns deferred status).</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-exchange-alt text-purple-400 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">n8n/nodes/schema-normalizer.json</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Unified task schema normalization node. Transforms extraction output from all processors into a consistent task schema before callback.</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Docker -->
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fab fa-docker text-blue-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">docker-compose.extraction-services.yml</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Docker Compose file defining Tika (port 9998) and Gutenberg OCR (port 8080) extraction services.</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Admin Views -->
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-columns text-gray-400 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">resources/views/admin/manual-intervention/index.blade.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Failed documents dashboard. Lists documents with failed/error status, filterable by supplier, type, and date range.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-columns text-gray-400 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">resources/views/admin/manual-intervention/show.blade.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Document detail view. Shows full extraction results, error details, and action buttons (retry, resolve, escalate).</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-columns text-gray-400 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">resources/views/admin/manual-intervention/timeline.blade.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Processing timeline view. Visual timeline of document lifecycle: submitted, processing, completed/failed, reviewed.</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Admin Controller -->
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-cog text-gray-400 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">app/Http/Controllers/Admin/ManualInterventionController.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Admin controller with retry, resolve, escalate, bulk-retry, and CSV export actions for failed document processing logs.</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Routes & Config -->
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-route text-gray-400 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">routes/api.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Added API routes: POST /api/documents/process, POST /api/webhooks/n8n/extraction.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-cog text-gray-400 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">config/services.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Added N8n service configuration: webhook_url, webhook_secret, callback_url, timeout settings.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- ========== PHASE 3 ========== -->
                    <section id="phase-3" class="mb-12">
                        <div class="flex items-center space-x-3 mb-6">
                            <span class="phase-badge phase-3">Phase 3</span>
                            <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Security &amp; Reliability</h2>
                        </div>

                        <p class="text-gray-700 dark:text-gray-300 mb-6">
                            Phase 3 hardened the integration with centralized HMAC signing, webhook middleware, rate limiting, input validation, and a full audit trail for webhook activity.
                        </p>

                        <div class="bg-yellow-50 dark:bg-yellow-900/30 border-l-4 border-yellow-400 p-4 rounded-md mb-6">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-shield-alt text-yellow-400"></i>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-semibold text-yellow-800 dark:text-yellow-200 mb-2">Security Highlights</h3>
                                    <p class="text-sm text-yellow-700 dark:text-yellow-300">
                                        Timing-safe HMAC comparison (hash_equals), multi-secret key rotation, per-client Redis rate limiting, directory traversal prevention, MIME type whitelisting.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Files Created</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">12 files: services, middleware, form requests, models, migrations, and N8n credentials</p>
                            </div>
                            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-key text-yellow-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">app/Services/WebhookSigningService.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Central HMAC-SHA256 signing and verification service. Uses timing-safe hash_equals comparison. Generates and validates signatures for both outbound requests and inbound callbacks.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-lock text-yellow-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">app/Http/Middleware/VerifyWebhookSignature.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">HMAC middleware with multi-secret support for seamless key rotation. Tries all active secrets for verification, rejects requests with no valid match.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-tachometer-alt text-yellow-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">app/Http/Middleware/WebhookRateLimiter.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Per-client Redis-backed rate limiting middleware. Configurable limits per client, returns 429 Too Many Requests with Retry-After header.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-clipboard-check text-yellow-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">app/Http/Requests/DocumentProcessingRequest.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Form request validation: directory traversal prevention on file paths, MIME type whitelist (pdf, png, jpg, eml, air), file size limits.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-cog text-yellow-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">config/webhook.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Comprehensive webhook configuration: signing algorithm, timestamp tolerance, rate limits, allowed MIME types, max file size, audit log retention.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-cube text-yellow-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">app/Models/WebhookClient.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Client registration model. Stores client name, API key hash, rate limit configuration, active/inactive status.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-cube text-yellow-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">app/Models/WebhookSecret.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Secret management model with rotation support. Tracks active/retired secrets, activation timestamps, expiry dates.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-cube text-yellow-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">app/Models/WebhookAuditLog.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Audit trail model for all webhook activity. Logs request IP, client ID, endpoint, payload hash, validation result, response code.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-database text-yellow-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">database/migrations/create_webhook_clients_table.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Creates webhook_clients table for registered API consumers.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-database text-yellow-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">database/migrations/create_webhook_secrets_table.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Creates webhook_secrets table for HMAC key management and rotation tracking.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-database text-yellow-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">database/migrations/create_webhook_audit_logs_table.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Creates webhook_audit_logs table for full request/response audit trail.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-key text-yellow-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">n8n/credentials/laravel-webhook-secret.json</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">N8n credential template for the shared HMAC secret. Import into N8n credentials manager for webhook signing.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- ========== PHASE 4 ========== -->
                    <section id="phase-4" class="mb-12">
                        <div class="flex items-center space-x-3 mb-6">
                            <span class="phase-badge phase-4">Phase 4</span>
                            <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Error Handling &amp; Observability</h2>
                        </div>

                        <p class="text-gray-700 dark:text-gray-300 mb-6">
                            Phase 4 added execution lifecycle tracking, a registry of 18 error codes with severity and retry policies, configurable alert thresholds, structured JSON logging, and a Chart.js analytics dashboard.
                        </p>

                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Files Created</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">10 files: services, commands, models, controllers, views, migrations, and factories</p>
                            </div>
                            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-clock text-pink-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">app/Services/N8nExecutionTracker.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Execution lifecycle tracking service. Records start time, monitors progress, detects timeouts, logs completion or failure with duration metrics.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-list-ol text-pink-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">app/Services/ErrorCodeRegistry.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Registry of 18 error codes. Each code includes severity (critical/high/medium/low), retry policy (max attempts, backoff), and human-readable resolution hints for operators.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-bell text-pink-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">app/Services/ErrorAlertService.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Alert monitoring with configurable thresholds. Triggers alerts on error rate percentage exceeding threshold or consecutive failure count. Supports multiple notification channels.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-file-alt text-pink-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">app/Services/N8nErrorLogger.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Structured JSON logging to a dedicated log channel. Captures error code, document metadata, execution context, and stack traces in machine-parseable format.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-terminal text-pink-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">app/Console/Commands/CheckErrorThresholds.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Artisan command <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded">webhook:check-errors</code>. Runs on schedule to evaluate error thresholds and fire alerts when conditions are met.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-cube text-pink-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">app/Models/DocumentError.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Error details model with scopes for filtering by error code, severity, date range. Belongs to DocumentProcessingLog.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-chart-bar text-pink-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">app/Http/Controllers/Admin/ErrorDashboardController.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Analytics dashboard controller. Provides error distribution, trends over time, top error codes, supplier breakdown, and success rate metrics.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-columns text-pink-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">resources/views/admin/error-dashboard/index.blade.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Error analytics view with Chart.js visualizations: error rate over time (line chart), error distribution by code (doughnut chart), supplier failure rates (bar chart).</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-database text-pink-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">database/migrations/create_document_errors_table.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Creates document_errors table with error_code, severity, message, context JSON, resolution_hint, linked to document_processing_logs.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-database text-pink-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">database/migrations/update_document_processing_logs_for_execution_tracking.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Adds execution tracking columns to document_processing_logs: execution_started_at, execution_completed_at, execution_duration_ms, n8n_execution_id.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-flask text-pink-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">database/factories/DocumentProcessingLogFactory.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Test factory for DocumentProcessingLog model. Generates realistic test data with configurable status, supplier, document type, and error states.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- ========== PHASE 5 ========== -->
                    <section id="phase-5" class="mb-12">
                        <div class="flex items-center space-x-3 mb-6">
                            <span class="phase-badge phase-5">Phase 5</span>
                            <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Testing &amp; Documentation</h2>
                            <span class="text-xs font-mono bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 px-2 py-1 rounded">Commit 6673f86c</span>
                        </div>

                        <p class="text-gray-700 dark:text-gray-300 mb-6">
                            Phase 5 added comprehensive automated testing (E2E, contract, error scenario, staging, and load tests), test fixtures, and full developer documentation pages.
                        </p>

                        <!-- Test Files -->
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Test Files Created</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">11 test files with 59+ test scenarios across E2E, contract, error, staging, and load testing</p>
                            </div>
                            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-vial text-purple-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">tests/Feature/Integration/EndToEndDocumentProcessingTest.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">15 end-to-end tests covering the full document lifecycle: PDF submission, image OCR, email parsing, AIR fallback, concurrent processing, and error recovery flows.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-vial text-purple-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">tests/Feature/Integration/WebhookContractTest.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">12 contract validation tests. Verifies HMAC signatures, timestamp validation, replay protection, request/response schema conformance, and error response formats.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-vial text-purple-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">tests/Feature/ErrorScenarios/ErrorScenarioTest.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">12 error scenario tests. Covers each error category (transient, non-transient, system), retry behavior, escalation triggers, and error code mapping.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-vial text-purple-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">tests/Feature/ErrorLogging/ErrorLoggingVerificationTest.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Verifies that all error scenarios produce correctly structured JSON log entries with the expected fields and severity levels.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-vial text-purple-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">tests/Feature/ErrorLogging/AlertThresholdTest.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Tests alert threshold evaluation: error rate percentage triggers, consecutive failure triggers, alert cooldown periods, and notification channel routing.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-vial text-purple-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">tests/Feature/Staging/StagingSupplierTest.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">14 staging supplier tests. Validates supplier-specific routing, extraction accuracy, and schema conformance for each supported supplier configuration.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-vial text-purple-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">tests/Feature/Staging/StagingTestReport.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Test report generator. Outputs formatted HTML and JSON reports summarizing staging test results, pass/fail counts, and timing metrics.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-tachometer-alt text-purple-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">tests/Load/DocumentProcessingLoadTest.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">6 load test scenarios: sustained throughput, burst traffic, stress testing, mixed document types, error injection under load, and daily production simulation.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-tools text-purple-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">tests/Load/LoadTestHelper.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Load test utilities: concurrent request dispatcher, response time aggregator, percentile calculator (p50, p95, p99), throughput metrics.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-file-code text-purple-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">tests/Load/run-load-test.sh</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Bash wrapper script for running load tests. Configures concurrency, duration, and target URL. Outputs results to both console and JSON file.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-terminal text-purple-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">app/Console/Commands/RunLoadTest.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Artisan command <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded">php artisan test:load --type=TYPE</code>. Runs load tests from command line with options for sustained, burst, stress, mixed, error, and daily scenarios.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Fixtures -->
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Test Fixtures &amp; Helpers</h3>
                            </div>
                            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-folder text-purple-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">tests/Fixtures/</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">20 fixture files covering PDF, image, email, and AIR document types. Realistic test data including multi-page PDFs, multi-language images, and various email formats.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-tools text-purple-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">tests/Fixtures/FixtureLoader.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Fixture loading utility. Resolves fixture paths, loads file contents, and provides typed accessors for different fixture categories.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-tools text-purple-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">tests/Fixtures/N8nResponseFactory.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Factory for generating realistic N8n callback response payloads. Supports success, error, partial, and timeout response scenarios with valid HMAC signatures.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Documentation -->
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Documentation Files</h3>
                            </div>
                            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-book text-purple-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">resources/views/docs/n8n-complete-documentation.blade.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Full developer documentation (98KB). Covers architecture, API endpoints, security model, error handling, configuration, and deployment guide.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-book text-purple-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">resources/views/docs/n8n-testing-documentation.blade.php</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Testing documentation. Covers test setup, running tests, fixture management, load testing configuration, and CI/CD integration.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-cog text-purple-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">Controllers and routes for all documentation pages</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">N8nDocumentationController with index() and complete() methods. Routes registered at /docs/n8n-processing, /docs/n8n-complete, /docs/n8n-testing.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- ========== ARCHITECTURE DIAGRAM ========== -->
                    <section id="architecture" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-6">Architecture Diagram</h2>

                        <p class="text-gray-700 dark:text-gray-300 mb-6">
                            The complete async document processing flow from submission through extraction to callback.
                        </p>

                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">End-to-End Processing Flow</h3>
                            </div>
                            <div class="px-6 py-4">
                                <div class="code-block">
                                    <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded text-xs overflow-x-auto border border-gray-200 dark:border-gray-700 leading-relaxed"><code>Laravel App  -->  POST /api/documents/process
    |                (HMAC signed, returns 202 Accepted)
    v
N8n Webhook  -->  Validate HMAC Signature
    |                (reject if invalid or replayed)
    v
Switch Node  -->  Route by document_type
    |
    +-- "pdf"   -->  Tika PDF Processor (port 9998)
    |
    +-- "image" -->  Gutenberg OCR Processor (port 8080)
    |
    +-- "email" -->  Gmail API Processor
    |
    +-- "air"   -->  Laravel AIR Parser Fallback (deferred)
    |
    v
Schema Normalizer  -->  Unified task schema output
    |
    v
Compute Callback HMAC  -->  Sign response payload
    |                         (HMAC-SHA256)
    v
POST /api/webhooks/n8n/extraction
    |                (HMAC signed callback)
    v
Laravel Callback Handler
    |-- Validate HMAC (timing-safe)
    |-- Check replay window (5 min)
    |-- Detect duplicates (409)
    v
Update DocumentProcessingLog
    |-- status: completed
    |-- extraction_results: {...}
    |-- execution_duration_ms: N
    v
Done (or trigger manual review if errors)</code></pre>
                                    <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                        <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- ========== N8N WORKFLOW FILES ========== -->
                    <section id="n8n-workflows" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-6">N8n Workflow Files</h2>

                        <p class="text-gray-700 dark:text-gray-300 mb-6">
                            All N8n workflow and node configuration files are stored in the repository under <code class="text-sm bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded">n8n/</code>. These JSON files can be imported directly into your N8n instance.
                        </p>

                        <div class="bg-blue-50 dark:bg-blue-900/30 border-l-4 border-blue-400 p-4 rounded-md mb-6">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-download text-blue-400"></i>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-semibold text-blue-800 dark:text-blue-200 mb-2">Download &amp; Import</h3>
                                    <p class="text-sm text-blue-700 dark:text-blue-300">
                                        Workflow files are available in the repository at <code class="text-xs bg-blue-100 dark:bg-blue-800 px-1 py-0.5 rounded">n8n/workflows/</code> and <code class="text-xs bg-blue-100 dark:bg-blue-800 px-1 py-0.5 rounded">n8n/nodes/</code>. To import into N8n: open your N8n instance, go to Workflows, click Import from File, and select the JSON file.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Available Files (8 total)</h3>
                            </div>
                            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-start">
                                            <i class="fas fa-project-diagram text-primary-500 mr-3 mt-1"></i>
                                            <div>
                                                <code class="text-sm font-mono text-gray-900 dark:text-gray-100">n8n/workflows/supplier-document-processing.json</code>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Main 18-node workflow. Import this first -- it is the primary workflow.</p>
                                            </div>
                                        </div>
                                        <span class="text-xs font-medium text-green-600 dark:text-green-400 bg-green-50 dark:bg-green-900/30 px-2 py-1 rounded">Primary</span>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-file-pdf text-red-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">n8n/nodes/pdf-processor.json</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Apache Tika PDF extraction node configuration (port 9998)</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-image text-green-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">n8n/nodes/image-processor.json</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Gutenberg OCR image processing node configuration (port 8080)</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-envelope text-yellow-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">n8n/nodes/email-processor.json</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Gmail API email parsing node configuration</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-plane text-blue-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">n8n/nodes/air-processor.json</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Laravel AirFileParser fallback node (deferred processing)</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-exchange-alt text-purple-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">n8n/nodes/schema-normalizer.json</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Unified task schema normalization node</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fas fa-key text-yellow-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">n8n/credentials/laravel-webhook-secret.json</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">HMAC secret credential template for N8n</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-6 py-3 file-list-item">
                                    <div class="flex items-start">
                                        <i class="fab fa-docker text-blue-500 mr-3 mt-1"></i>
                                        <div>
                                            <code class="text-sm font-mono text-gray-900 dark:text-gray-100">docker-compose.extraction-services.yml</code>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Tika + Gutenberg Docker service definitions</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Import Instructions -->
                        <div class="mt-6 bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Import Instructions</h3>
                            </div>
                            <div class="px-6 py-4">
                                <ol class="text-sm text-gray-700 dark:text-gray-300 space-y-3 list-decimal list-inside">
                                    <li><strong>Import credential first:</strong> In N8n, go to <em>Settings &gt; Credentials &gt; Add Credential</em>. Import <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded">n8n/credentials/laravel-webhook-secret.json</code> and set your shared HMAC secret.</li>
                                    <li><strong>Import the workflow:</strong> Go to <em>Workflows &gt; Import from File</em>. Select <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded">n8n/workflows/supplier-document-processing.json</code>.</li>
                                    <li><strong>Update environment URLs:</strong> In the workflow nodes, update the callback URL to point to your Laravel instance (<code class="text-xs bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded">N8N_CALLBACK_URL</code>).</li>
                                    <li><strong>Start Docker services:</strong> Run <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded">docker compose -f docker-compose.extraction-services.yml up -d</code> to start Tika and Gutenberg.</li>
                                    <li><strong>Activate the workflow:</strong> Toggle the workflow to Active in N8n.</li>
                                </ol>
                            </div>
                        </div>
                    </section>

                    <!-- ========== CONFIGURATION ========== -->
                    <section id="configuration" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-6">Configuration Reference</h2>

                        <p class="text-gray-700 dark:text-gray-300 mb-6">
                            All environment variables required for the N8n document processing integration. Add these to your <code class="text-sm bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded">.env</code> file.
                        </p>

                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Required Environment Variables</h3>
                            </div>
                            <div class="px-6 py-4">
                                <div class="code-block">
                                    <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code># ============================================
# N8n Document Processing Configuration
# ============================================

# N8n Webhook URL - The URL where N8n listens for document processing requests
N8N_WEBHOOK_URL=https://your-n8n/webhook/supplier-document-processing

# Shared HMAC Secret - Used for signing requests between Laravel and N8n
# Must be the same value in both Laravel .env and N8n credentials
N8N_WEBHOOK_SECRET=your-shared-secret

# Callback URL - Where N8n sends extraction results back to Laravel
N8N_CALLBACK_URL=https://your-laravel/api/webhooks/n8n/extraction

# ============================================
# Extraction Service URLs (Docker)
# ============================================

# Apache Tika - PDF text extraction and metadata parsing
TIKA_SERVER_URL=http://localhost:9998

# Gutenberg OCR - Image-to-text optical character recognition
GUTENBERG_OCR_URL=http://localhost:8080</code></pre>
                                    <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                        <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                                    </button>
                                </div>

                                <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-md p-4">
                                        <h4 class="text-sm font-semibold text-green-800 dark:text-green-200 mb-2">Production</h4>
                                        <ul class="text-xs text-green-700 dark:text-green-300 space-y-1">
                                            <li>Use HTTPS URLs for all endpoints</li>
                                            <li>Generate a cryptographically strong secret (32+ chars)</li>
                                            <li>Run Tika/Gutenberg behind a reverse proxy</li>
                                            <li>Enable rate limiting in config/webhook.php</li>
                                        </ul>
                                    </div>
                                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md p-4">
                                        <h4 class="text-sm font-semibold text-blue-800 dark:text-blue-200 mb-2">Development</h4>
                                        <ul class="text-xs text-blue-700 dark:text-blue-300 space-y-1">
                                            <li>localhost URLs are fine for local development</li>
                                            <li>Use any string for the webhook secret</li>
                                            <li>Docker services run on default ports</li>
                                            <li>Rate limiting can be disabled in config</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- ========== DATABASE SCHEMA ========== -->
                    <section id="database-schema" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-6">Database Schema</h2>

                        <p class="text-gray-700 dark:text-gray-300 mb-6">
                            The integration adds 5 new database tables. Run <code class="text-sm bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded">php artisan migrate</code> to create them.
                        </p>

                        <div class="space-y-6">
                            <!-- document_processing_logs -->
                            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">document_processing_logs</h3>
                                        <span class="phase-badge phase-2">Phase 2</span>
                                    </div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Core processing log table. Tracks every document from submission through extraction to completion.</p>
                                </div>
                                <div class="px-6 py-4">
                                    <div class="code-block">
                                        <pre class="bg-gray-100 dark:bg-gray-800 p-3 rounded text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>Column                    | Type         | Notes
--------------------------|--------------|----------------------------------
id                        | bigint       | Primary key
document_id               | uuid         | Unique tracking ID (returned in 202)
supplier_id               | bigint       | FK to suppliers
document_type             | varchar      | pdf, image, email, air
status                    | enum         | pending, processing, completed, failed
file_path                 | varchar      | Original document path
extraction_results        | json         | Extracted task data (nullable)
error_code                | varchar      | Error code if failed (nullable)
error_message             | text         | Human-readable error (nullable)
retry_count               | integer      | Number of retry attempts
n8n_execution_id          | varchar      | N8n execution tracking ID
execution_started_at      | timestamp    | When N8n started processing
execution_completed_at    | timestamp    | When callback was received
execution_duration_ms     | integer      | Total processing time in ms
reviewed_at               | timestamp    | Manual review timestamp
reviewed_by               | bigint       | Admin user who reviewed
created_at                | timestamp    | Submission time
updated_at                | timestamp    | Last update time</code></pre>
                                        <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                            <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- document_errors -->
                            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">document_errors</h3>
                                        <span class="phase-badge phase-4">Phase 4</span>
                                    </div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Detailed error records linked to processing logs. One log can have multiple errors.</p>
                                </div>
                                <div class="px-6 py-4">
                                    <div class="code-block">
                                        <pre class="bg-gray-100 dark:bg-gray-800 p-3 rounded text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>Column                    | Type         | Notes
--------------------------|--------------|----------------------------------
id                        | bigint       | Primary key
document_processing_log_id| bigint       | FK to document_processing_logs
error_code                | varchar      | One of 18 defined error codes
severity                  | enum         | critical, high, medium, low
message                   | text         | Error description
context                   | json         | Additional error context
resolution_hint           | text         | Operator guidance for resolution
created_at                | timestamp    | When error occurred
updated_at                | timestamp    | Last update</code></pre>
                                        <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                            <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- webhook_clients -->
                            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">webhook_clients</h3>
                                        <span class="phase-badge phase-3">Phase 3</span>
                                    </div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Registered API consumers. Each client has its own rate limit configuration.</p>
                                </div>
                                <div class="px-6 py-4">
                                    <div class="code-block">
                                        <pre class="bg-gray-100 dark:bg-gray-800 p-3 rounded text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>Column                    | Type         | Notes
--------------------------|--------------|----------------------------------
id                        | bigint       | Primary key
name                      | varchar      | Client display name
api_key_hash              | varchar      | Hashed API key
rate_limit                | integer      | Max requests per minute
is_active                 | boolean      | Active/inactive toggle
created_at                | timestamp    | Registration date
updated_at                | timestamp    | Last update</code></pre>
                                        <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                            <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- webhook_secrets -->
                            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">webhook_secrets</h3>
                                        <span class="phase-badge phase-3">Phase 3</span>
                                    </div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">HMAC secret management with rotation support. Multiple secrets can be active during key rotation.</p>
                                </div>
                                <div class="px-6 py-4">
                                    <div class="code-block">
                                        <pre class="bg-gray-100 dark:bg-gray-800 p-3 rounded text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>Column                    | Type         | Notes
--------------------------|--------------|----------------------------------
id                        | bigint       | Primary key
webhook_client_id         | bigint       | FK to webhook_clients
secret                    | varchar      | The HMAC secret value (encrypted)
is_active                 | boolean      | Whether this secret is active
activated_at              | timestamp    | When secret became active
expires_at                | timestamp    | Optional expiry date
created_at                | timestamp    | Creation date
updated_at                | timestamp    | Last update</code></pre>
                                        <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                            <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- webhook_audit_logs -->
                            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">webhook_audit_logs</h3>
                                        <span class="phase-badge phase-3">Phase 3</span>
                                    </div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Complete audit trail for all webhook activity. Every request is logged regardless of success or failure.</p>
                                </div>
                                <div class="px-6 py-4">
                                    <div class="code-block">
                                        <pre class="bg-gray-100 dark:bg-gray-800 p-3 rounded text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>Column                    | Type         | Notes
--------------------------|--------------|----------------------------------
id                        | bigint       | Primary key
webhook_client_id         | bigint       | FK to webhook_clients (nullable)
endpoint                  | varchar      | Request URL path
method                    | varchar      | HTTP method (POST, GET, etc.)
ip_address                | varchar      | Client IP address
payload_hash              | varchar      | SHA-256 hash of request body
signature_valid           | boolean      | Whether HMAC validation passed
response_code             | integer      | HTTP response status code
response_time_ms          | integer      | Processing time in milliseconds
error_message             | text         | Error details if validation failed
created_at                | timestamp    | Request timestamp
updated_at                | timestamp    | Last update</code></pre>
                                        <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                            <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Related Docs -->
                    <section class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-6">Related Documentation</h2>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <a href="/docs/n8n-processing" class="block bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6 hover:shadow-md transition-shadow">
                                <h3 class="text-md font-semibold text-gray-900 dark:text-white mb-2">Processing Docs</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400">API endpoints, document types, security model, and workflow overview.</p>
                            </a>
                            <a href="/docs/n8n-complete" class="block bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6 hover:shadow-md transition-shadow">
                                <h3 class="text-md font-semibold text-gray-900 dark:text-white mb-2">Complete Documentation</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Full 98KB developer reference covering all integration aspects.</p>
                            </a>
                            <a href="/docs/n8n-testing" class="block bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6 hover:shadow-md transition-shadow">
                                <h3 class="text-md font-semibold text-gray-900 dark:text-white mb-2">Testing Documentation</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Test setup, fixture management, load testing, and CI/CD integration.</p>
                            </a>
                        </div>
                    </section>

                </div>
            </div>
        </div>
    </div>

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
