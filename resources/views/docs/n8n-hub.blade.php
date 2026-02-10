<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>N8n Integration Documentation Hub</title>
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

        /* Card hover effects */
        .doc-card {
            transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .doc-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.3);
        }

        /* Icon pulse on card hover */
        .doc-card:hover .card-icon {
            transform: scale(1.1);
        }

        .card-icon {
            transition: transform 0.2s ease;
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
    </style>
</head>

<body class="bg-gray-50 text-gray-900 dark:bg-gray-900 dark:text-gray-100 transition-colors duration-200 min-h-screen">

    <!-- Header -->
    <header class="bg-white dark:bg-gray-800 shadow-sm sticky top-0 z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-primary-600">
                    <i class="fas fa-project-diagram text-white text-lg"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white">N8n Integration Documentation</h1>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Complete developer reference for the N8n document processing integration</p>
                </div>
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
                <a href="/docs/developer" class="text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300">
                    <i class="fas fa-code mr-1"></i> API Reference
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

        <!-- Hero Section -->
        <div class="text-center mb-12">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-primary-600/10 dark:bg-primary-500/10 mb-4">
                <i class="fas fa-project-diagram text-3xl text-primary-600 dark:text-primary-400"></i>
            </div>
            <h2 class="text-3xl font-extrabold text-gray-900 dark:text-white mb-3">
                N8n Integration Documentation
            </h2>
            <p class="text-lg text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
                Complete developer reference for the N8n document processing integration.
                Everything you need to understand, implement, test, and maintain the system.
            </p>
        </div>

        <!-- Documentation Cards Grid -->
        <section class="mb-16">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

                <!-- Card 1: Developer API Documentation -->
                <a href="/docs/developer" class="doc-card block bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden hover:border-primary-500 dark:hover:border-primary-500">
                    <div class="h-1 bg-blue-500"></div>
                    <div class="p-6">
                        <div class="flex items-start space-x-4 mb-4">
                            <div class="card-icon flex items-center justify-center w-12 h-12 rounded-lg bg-blue-500/10 dark:bg-blue-500/20 flex-shrink-0">
                                <i class="fas fa-code text-xl text-blue-500"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Developer API Documentation</h3>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-5 leading-relaxed">
                            Complete API reference for all endpoints including authentication, webhooks, and response formats.
                        </p>
                        <div class="flex items-center text-primary-600 dark:text-primary-400 text-sm font-medium">
                            <span>View Documentation</span>
                            <i class="fas fa-arrow-right ml-2 text-xs"></i>
                        </div>
                    </div>
                </a>

                <!-- Card 2: N8n Processing Guide -->
                <a href="/docs/n8n-processing" class="doc-card block bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden hover:border-orange-500 dark:hover:border-orange-500">
                    <div class="h-1 bg-orange-500"></div>
                    <div class="p-6">
                        <div class="flex items-start space-x-4 mb-4">
                            <div class="card-icon flex items-center justify-center w-12 h-12 rounded-lg bg-orange-500/10 dark:bg-orange-500/20 flex-shrink-0">
                                <i class="fas fa-cogs text-xl text-orange-500"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">N8n Processing Guide</h3>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-5 leading-relaxed">
                            Core document processing workflow &mdash; how documents flow from Laravel to N8n and back.
                        </p>
                        <div class="flex items-center text-primary-600 dark:text-primary-400 text-sm font-medium">
                            <span>View Documentation</span>
                            <i class="fas fa-arrow-right ml-2 text-xs"></i>
                        </div>
                    </div>
                </a>

                <!-- Card 3: Complete N8n Documentation -->
                <a href="/docs/n8n-complete" class="doc-card block bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden hover:border-purple-500 dark:hover:border-purple-500">
                    <div class="h-1 bg-purple-500"></div>
                    <div class="p-6">
                        <div class="flex items-start space-x-4 mb-4">
                            <div class="card-icon flex items-center justify-center w-12 h-12 rounded-lg bg-purple-500/10 dark:bg-purple-500/20 flex-shrink-0">
                                <i class="fas fa-book text-xl text-purple-500"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Complete N8n Documentation</h3>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-5 leading-relaxed">
                            Comprehensive reference covering all 5 phases of the N8n integration &mdash; architecture, security, error handling, and more.
                        </p>
                        <div class="flex items-center text-primary-600 dark:text-primary-400 text-sm font-medium">
                            <span>View Documentation</span>
                            <i class="fas fa-arrow-right ml-2 text-xs"></i>
                        </div>
                    </div>
                </a>

                <!-- Card 4: N8n Testing Documentation -->
                <a href="/docs/n8n-testing" class="doc-card block bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden hover:border-green-500 dark:hover:border-green-500">
                    <div class="h-1 bg-green-500"></div>
                    <div class="p-6">
                        <div class="flex items-start space-x-4 mb-4">
                            <div class="card-icon flex items-center justify-center w-12 h-12 rounded-lg bg-green-500/10 dark:bg-green-500/20 flex-shrink-0">
                                <i class="fas fa-flask text-xl text-green-500"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">N8n Testing Documentation</h3>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-5 leading-relaxed">
                            Test suites, fixtures, load testing scripts, and quality assurance procedures for the N8n integration.
                        </p>
                        <div class="flex items-center text-primary-600 dark:text-primary-400 text-sm font-medium">
                            <span>View Documentation</span>
                            <i class="fas fa-arrow-right ml-2 text-xs"></i>
                        </div>
                    </div>
                </a>

                <!-- Card 5: Integration Changelog -->
                <a href="/docs/n8n-changelog" class="doc-card block bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden hover:border-yellow-500 dark:hover:border-yellow-500">
                    <div class="h-1 bg-yellow-500"></div>
                    <div class="p-6">
                        <div class="flex items-start space-x-4 mb-4">
                            <div class="card-icon flex items-center justify-center w-12 h-12 rounded-lg bg-yellow-500/10 dark:bg-yellow-500/20 flex-shrink-0">
                                <i class="fas fa-history text-xl text-yellow-500"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Integration Changelog</h3>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-5 leading-relaxed">
                            Phase-by-phase changelog documenting every change, migration, and feature added across all 5 phases.
                        </p>
                        <div class="flex items-center text-primary-600 dark:text-primary-400 text-sm font-medium">
                            <span>View Documentation</span>
                            <i class="fas fa-arrow-right ml-2 text-xs"></i>
                        </div>
                    </div>
                </a>

                <!-- Card 6: N8n Workflow Files -->
                <a href="/downloads/n8n/" target="_blank" rel="noopener noreferrer" class="doc-card block bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden hover:border-cyan-500 dark:hover:border-cyan-500">
                    <div class="h-1 bg-cyan-500"></div>
                    <div class="p-6">
                        <div class="flex items-start space-x-4 mb-4">
                            <div class="card-icon flex items-center justify-center w-12 h-12 rounded-lg bg-cyan-500/10 dark:bg-cyan-500/20 flex-shrink-0">
                                <i class="fas fa-download text-xl text-cyan-500"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">N8n Workflow Files</h3>
                                <span class="inline-flex items-center text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    <i class="fas fa-external-link-alt mr-1"></i> Opens in new tab
                                </span>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-5 leading-relaxed">
                            Download N8n workflow JSON files, node configurations, and credential templates for import.
                        </p>
                        <div class="flex items-center text-primary-600 dark:text-primary-400 text-sm font-medium">
                            <span>Download Files</span>
                            <i class="fas fa-arrow-right ml-2 text-xs"></i>
                        </div>
                    </div>
                </a>

                <!-- Card 7: Postman Collection -->
                <a href="/docs/postman/download" class="doc-card block bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden hover:border-rose-500 dark:hover:border-rose-500">
                    <div class="h-1 bg-rose-500"></div>
                    <div class="p-6">
                        <div class="flex items-start space-x-4 mb-4">
                            <div class="card-icon flex items-center justify-center w-12 h-12 rounded-lg bg-rose-500/10 dark:bg-rose-500/20 flex-shrink-0">
                                <i class="fas fa-paper-plane text-xl text-rose-500"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Postman Collection</h3>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-5 leading-relaxed">
                            Download the Postman collection for testing all API endpoints and webhook integrations.
                        </p>
                        <div class="flex items-center text-primary-600 dark:text-primary-400 text-sm font-medium">
                            <span>Download Collection</span>
                            <i class="fas fa-arrow-right ml-2 text-xs"></i>
                        </div>
                    </div>
                </a>

                <!-- Card 8: Magic Webhook Docs -->
                <a href="/docs/magic-webhook" class="doc-card block bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden hover:border-indigo-500 dark:hover:border-indigo-500">
                    <div class="h-1 bg-indigo-500"></div>
                    <div class="p-6">
                        <div class="flex items-start space-x-4 mb-4">
                            <div class="card-icon flex items-center justify-center w-12 h-12 rounded-lg bg-indigo-500/10 dark:bg-indigo-500/20 flex-shrink-0">
                                <i class="fas fa-magic text-xl text-indigo-500"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Magic Webhook Docs</h3>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-5 leading-relaxed">
                            Magic Holiday Reserve webhook integration documentation for supplier booking callbacks.
                        </p>
                        <div class="flex items-center text-primary-600 dark:text-primary-400 text-sm font-medium">
                            <span>View Documentation</span>
                            <i class="fas fa-arrow-right ml-2 text-xs"></i>
                        </div>
                    </div>
                </a>

            </div>
        </section>

        <!-- Divider -->
        <div class="border-t border-gray-200 dark:border-gray-700 mb-12"></div>

        <!-- Quick Start & Architecture side by side -->
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-16">

            <!-- Quick Start -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-8">
                <div class="flex items-center space-x-3 mb-6">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-green-500/10 dark:bg-green-500/20">
                        <i class="fas fa-rocket text-lg text-green-500"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">Quick Start</h3>
                </div>

                <div class="space-y-4">
                    <!-- Step 1 -->
                    <div class="flex items-start space-x-3">
                        <div class="step-number bg-primary-600 text-white mt-0.5">1</div>
                        <div>
                            <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
                                Start with the <a href="/docs/n8n-complete" class="text-primary-500 hover:text-primary-400 font-medium underline decoration-primary-500/30 hover:decoration-primary-500">Complete N8n Documentation</a> for a full overview of the integration architecture and all 5 phases.
                            </p>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="flex items-start space-x-3">
                        <div class="step-number bg-primary-600 text-white mt-0.5">2</div>
                        <div>
                            <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
                                Use the <a href="/docs/developer" class="text-primary-500 hover:text-primary-400 font-medium underline decoration-primary-500/30 hover:decoration-primary-500">Developer API Documentation</a> for endpoint details, authentication, and request/response formats.
                            </p>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="flex items-start space-x-3">
                        <div class="step-number bg-primary-600 text-white mt-0.5">3</div>
                        <div>
                            <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
                                Download <a href="/downloads/n8n/" target="_blank" class="text-primary-500 hover:text-primary-400 font-medium underline decoration-primary-500/30 hover:decoration-primary-500">N8n workflow files</a> and import them into your N8n instance for document processing.
                            </p>
                        </div>
                    </div>

                    <!-- Step 4 -->
                    <div class="flex items-start space-x-3">
                        <div class="step-number bg-primary-600 text-white mt-0.5">4</div>
                        <div>
                            <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
                                Run the test suites using the <a href="/docs/n8n-testing" class="text-primary-500 hover:text-primary-400 font-medium underline decoration-primary-500/30 hover:decoration-primary-500">Testing Documentation</a> guide to verify your setup.
                            </p>
                        </div>
                    </div>

                    <!-- Step 5 -->
                    <div class="flex items-start space-x-3">
                        <div class="step-number bg-primary-600 text-white mt-0.5">5</div>
                        <div>
                            <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
                                Check the <a href="/docs/n8n-changelog" class="text-primary-500 hover:text-primary-400 font-medium underline decoration-primary-500/30 hover:decoration-primary-500">Changelog</a> for the latest updates, migrations, and feature additions.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Architecture Overview -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-8">
                <div class="flex items-center space-x-3 mb-6">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-purple-500/10 dark:bg-purple-500/20">
                        <i class="fas fa-sitemap text-lg text-purple-500"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">Architecture Overview</h3>
                </div>

                <div class="space-y-4">
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 mt-1.5">
                            <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                        </div>
                        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
                            <span class="font-semibold text-gray-900 dark:text-white">Laravel &rarr; N8n:</span>
                            Laravel sends documents via webhook to N8n for automated processing.
                        </p>
                    </div>

                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 mt-1.5">
                            <div class="w-2 h-2 rounded-full bg-orange-500"></div>
                        </div>
                        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
                            <span class="font-semibold text-gray-900 dark:text-white">Processing:</span>
                            N8n processes documents (PDF, Image, Email, AIR) with supplier-specific routing and extraction logic.
                        </p>
                    </div>

                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 mt-1.5">
                            <div class="w-2 h-2 rounded-full bg-green-500"></div>
                        </div>
                        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
                            <span class="font-semibold text-gray-900 dark:text-white">Callback:</span>
                            Results are sent back to Laravel with HMAC-SHA256 signed payloads for integrity verification.
                        </p>
                    </div>

                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 mt-1.5">
                            <div class="w-2 h-2 rounded-full bg-yellow-500"></div>
                        </div>
                        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
                            <span class="font-semibold text-gray-900 dark:text-white">Error Handling:</span>
                            18 error codes across 3 categories (transient, non-transient, system) with structured error responses.
                        </p>
                    </div>

                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 mt-1.5">
                            <div class="w-2 h-2 rounded-full bg-red-500"></div>
                        </div>
                        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
                            <span class="font-semibold text-gray-900 dark:text-white">Retry Logic:</span>
                            Automatic retry for transient errors with exponential backoff. Non-transient errors are flagged for admin review.
                        </p>
                    </div>
                </div>

                <!-- Flow diagram text -->
                <div class="mt-6 bg-gray-100 dark:bg-gray-900/50 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-center space-x-2 text-sm font-mono text-gray-600 dark:text-gray-400">
                        <span class="px-2 py-1 bg-blue-500/10 text-blue-500 rounded font-semibold">Laravel</span>
                        <i class="fas fa-long-arrow-alt-right text-gray-400"></i>
                        <span class="px-2 py-1 bg-orange-500/10 text-orange-500 rounded font-semibold">N8n</span>
                        <i class="fas fa-long-arrow-alt-right text-gray-400"></i>
                        <span class="px-2 py-1 bg-green-500/10 text-green-500 rounded font-semibold">Process</span>
                        <i class="fas fa-long-arrow-alt-right text-gray-400"></i>
                        <span class="px-2 py-1 bg-purple-500/10 text-purple-500 rounded font-semibold">Callback</span>
                    </div>
                </div>
            </div>

        </section>

    </main>

    <!-- Footer -->
    <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 mt-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex flex-col md:flex-row items-center justify-between space-y-3 md:space-y-0">
                <div class="flex items-center space-x-2 text-sm text-gray-500 dark:text-gray-400">
                    <i class="fas fa-building"></i>
                    <span>Built for City Tour Development Team</span>
                    <span>&middot;</span>
                    <span>&copy; {{ date('Y') }}</span>
                </div>
                <div class="flex items-center space-x-4 text-sm">
                    <a href="/docs/developer" class="text-gray-500 dark:text-gray-400 hover:text-primary-500 dark:hover:text-primary-400 transition-colors">
                        API Docs
                    </a>
                    <a href="/docs/n8n-complete" class="text-gray-500 dark:text-gray-400 hover:text-primary-500 dark:hover:text-primary-400 transition-colors">
                        N8n Docs
                    </a>
                    <a href="/docs/n8n-changelog" class="text-gray-500 dark:text-gray-400 hover:text-primary-500 dark:hover:text-primary-400 transition-colors">
                        Changelog
                    </a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Dark Mode Toggle Script -->
    <script>
        const darkModeToggle = document.getElementById('darkModeToggle');
        const html = document.documentElement;

        // Check for saved preference or default to dark
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'light') {
            html.classList.remove('dark');
        } else {
            html.classList.add('dark');
        }

        darkModeToggle.addEventListener('click', () => {
            html.classList.toggle('dark');
            const isDark = html.classList.contains('dark');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
        });
    </script>

</body>

</html>