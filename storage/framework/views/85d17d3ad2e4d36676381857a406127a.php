<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Developer API Documentation</title>
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

        .task-type-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .badge-flight {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .badge-hotel {
            background-color: #fce7f3;
            color: #9f1239;
        }

        .badge-insurance {
            background-color: #d1fae5;
            color: #065f46;
        }

        .badge-visa {
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
                <h1 class="text-xl font-bold text-gray-900 dark:text-white">Developer API Documentation</h1>
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
                <a href="#" class="text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300">API Reference</a>
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
                    <a href="#overview" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-info-circle w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Overview
                    </a>
                    <a href="#endpoint" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md bg-gray-100 dark:bg-gray-700 text-primary-600 dark:text-primary-400">
                        <i class="fas fa-code w-4 mr-3 text-primary-600 dark:text-primary-400"></i>
                        API Endpoint
                    </a>
                    <a href="#task-types" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-layer-group w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Task Types
                    </a>
                    <a href="#flight-task" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 pl-10">
                        Flight Tasks
                    </a>
                    <a href="#hotel-task" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 pl-10">
                        Hotel Tasks
                    </a>
                    <a href="#insurance-task" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 pl-10">
                        Insurance Tasks
                    </a>
                    <a href="#visa-task" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 pl-10">
                        Visa Tasks
                    </a>
                    <a href="#utility-endpoints" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-tools w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Utility Endpoints
                    </a>
                    <a href="#responses" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-reply w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Responses
                    </a>
                    <a href="#error-handling" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-exclamation-triangle w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Error Handling
                    </a>
                    <a href="#best-practices" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-check-circle w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Best Practices
                    </a>
                </nav>
            </div>

            <!-- Main content -->
            <div class="mt-8 lg:mt-0 lg:col-span-9">
                <div class="prose prose-blue max-w-none dark:prose-invert">
                    <div class="bg-gradient-to-r from-primary-600 to-blue-600 rounded-xl shadow-lg p-8 mb-12 text-white">
                        <h1 class="text-4xl font-extrabold mb-4">Developer API Documentation</h1>
                        <p class="text-lg opacity-90 max-w-3xl">
                            Complete API documentation for integrating with our platform. Access task creation webhooks, utility endpoints, and helper APIs to build seamless integrations with your travel management system.
                        </p>
                        <div class="mt-6 flex flex-wrap gap-3">
                            <a href="#endpoint" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-primary-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-primary-700 focus:ring-white transition-colors">
                                Get Started
                            </a>
                            <a href="#task-types" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-primary-800 bg-opacity-60 hover:bg-opacity-70 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-primary-700 focus:ring-white transition-colors">
                                View Task Types
                            </a>
                            <a href="<?php echo e(route('docs.postman.download')); ?>" class="inline-flex items-center px-4 py-2 border border-white border-opacity-50 text-sm font-medium rounded-md text-white hover:bg-white hover:bg-opacity-10 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-primary-700 focus:ring-white transition-colors">
                                <i class="fas fa-download mr-2"></i>
                                Download Postman Collection
                            </a>
                        </div>
                    </div>

                    <section id="overview" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">Overview</h2>
                        <p class="text-lg text-gray-700 dark:text-gray-300 mb-4">
                            The Task Creation Webhook API enables external systems to create tasks in your platform. Each task represents a booking or transaction for travel-related services including flights, hotels, insurance policies, and visa applications.
                        </p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div class="bg-blue-50 dark:bg-blue-900/30 border-l-4 border-blue-400 p-4 rounded-md">
                                <h3 class="text-sm font-semibold text-blue-800 dark:text-blue-200 mb-2">Key Features</h3>
                                <ul class="text-sm text-blue-700 dark:text-blue-300 space-y-1">
                                    <li>✓ Support for 4 task types</li>
                                    <li>✓ Automatic financial processing</li>
                                    <li>✓ Supplier integration support</li>
                                    <li>✓ Duplicate detection</li>
                                </ul>
                            </div>
                            <div class="bg-green-50 dark:bg-green-900/30 border-l-4 border-green-400 p-4 rounded-md">
                                <h3 class="text-sm font-semibold text-green-800 dark:text-green-200 mb-2">Supported Task Types</h3>
                                <ul class="text-sm text-green-700 dark:text-green-300 space-y-1">
                                    <li><span class="task-type-badge badge-flight">Flight</span></li>
                                    <li><span class="task-type-badge badge-hotel">Hotel</span></li>
                                    <li><span class="task-type-badge badge-insurance">Insurance</span></li>
                                    <li><span class="task-type-badge badge-visa">Visa</span></li>
                                </ul>
                            </div>
                        </div>
                        <div class="bg-yellow-50 dark:bg-yellow-900 border-l-4 border-yellow-400 p-4 rounded-md mb-6">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-yellow-700 dark:text-yellow-200">
                                        <strong>Important:</strong> All requests must include valid company, supplier, and agent IDs that exist in your database. The API will automatically create necessary financial entries and process supplier integrations.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="endpoint" class="mb-12">
                        <div class="flex items-center mb-4">
                            <h2 class="text-3xl font-bold text-gray-900 dark:text-white">API Endpoint</h2>
                            <span class="method-badge http-post ml-3">POST</span>
                        </div>
                        <div class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg mb-6 border border-gray-200 dark:border-gray-700">
                            <code class="text-sm text-gray-900 dark:text-gray-100">POST /api/task/webhook</code>
                        </div>
                        <p class="text-lg text-gray-700 dark:text-gray-300 mb-4">
                            Send a POST request with a JSON payload containing task information. The endpoint will validate the data, create the task, and process all related business logic including financial entries, supplier integrations, and AutoBilling rules.
                        </p>

                        <h3 class="text-2xl font-semibold mt-8 mb-4 text-gray-900 dark:text-white">Headers</h3>
                        <div class="code-block">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg mb-6 text-sm overflow-x-auto border border-gray-200 dark:border-gray-700"><code>Content-Type: application/json
Accept: application/json</code></pre>
                            <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>
                    </section>

                    <section id="common-fields" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">Common Request Fields</h2>
                        <p class="text-lg text-gray-700 dark:text-gray-300 mb-6">
                            The following fields are common across all task types:
                        </p>

                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-8">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Field</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Required</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Description</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">reference</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">string</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 dark:text-green-400 font-semibold">Yes</td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Unique task reference number</td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">status</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">string</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 dark:text-green-400 font-semibold">Yes</td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Task status (e.g., "issued", "pending")</td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">company_id</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">integer</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 dark:text-green-400 font-semibold">Yes</td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Valid company ID from your database</td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">type</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">enum</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 dark:text-green-400 font-semibold">Yes</td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">flight, hotel, insurance, or visa</td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">supplier_id</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">integer</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">No</td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Valid supplier ID from your database</td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">agent_id</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">integer</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">No</td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Valid agent ID from your database</td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">client_name</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">string</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">No</td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Client name for this task</td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">price</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">decimal</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">No</td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Task price</td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">tax</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">decimal</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">No</td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Tax amount</td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">total</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">decimal</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">No</td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Total amount</td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">issued_date</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">date</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">No</td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Issue date (YYYY-MM-DD)</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    <section id="task-types" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-6">Task Types</h2>
                        <p class="text-lg text-gray-700 dark:text-gray-300 mb-6">
                            Each task type has specific detail fields that must be included in the request. Click on a task type below to view its requirements:
                        </p>
                    </section>

                    <!-- Flight Task -->
                    <section id="flight-task" class="mb-12">
                        <div class="flex items-center mb-4">
                            <span class="task-type-badge badge-flight mr-3">FLIGHT</span>
                            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">Flight Tasks</h3>
                        </div>
                        <p class="text-gray-700 dark:text-gray-300 mb-4">
                            Flight tasks require a <code class="bg-gray-200 dark:bg-gray-700 px-2 py-1 rounded text-sm">task_flight_details</code> array containing one or more flight segments.
                        </p>

                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Required Fields in <code class="bg-gray-200 dark:bg-gray-700 px-2 py-1 rounded text-sm">task_flight_details</code></h4>
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Field</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Description</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                        <tr><td class="px-4 py-3 font-medium text-gray-900 dark:text-white">departure_time</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">datetime</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">Flight departure (YYYY-MM-DD HH:MM:SS)</td></tr>
                                        <tr><td class="px-4 py-3 font-medium text-gray-900 dark:text-white">arrival_time</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">datetime</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">Flight arrival (YYYY-MM-DD HH:MM:SS)</td></tr>
                                        <tr><td class="px-4 py-3 font-medium text-gray-900 dark:text-white">country_id_from</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">integer</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">Origin country ID</td></tr>
                                        <tr><td class="px-4 py-3 font-medium text-gray-900 dark:text-white">country_id_to</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">integer</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">Destination country ID</td></tr>
                                        <tr><td class="px-4 py-3 font-medium text-gray-900 dark:text-white">airport_from</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">string</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">Origin airport code</td></tr>
                                        <tr><td class="px-4 py-3 font-medium text-gray-900 dark:text-white">airport_to</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">string</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">Destination airport code</td></tr>
                                        <tr><td class="px-4 py-3 font-medium text-gray-900 dark:text-white">airline_id</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">integer</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">Airline ID</td></tr>
                                        <tr><td class="px-4 py-3 font-medium text-gray-900 dark:text-white">flight_number</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">string</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">Flight number</td></tr>
                                        <tr><td class="px-4 py-3 font-medium text-gray-900 dark:text-white">ticket_number</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">string</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">Ticket number</td></tr>
                                        <tr><td class="px-4 py-3 font-medium text-gray-900 dark:text-white">class_type</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">string</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">Cabin class (economy, business, first)</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Example Request</h4>
                        <div class="code-block">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg mb-6 text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>{
  "reference": "FL-TEST-001",
  "status": "issued",
  "company_id": 1,
  "type": "flight",
  "supplier_id": 2,
  "client_name": "John Doe",
  "agent_id": 1,
  "price": 450.00,
  "tax": 45.00,
  "total": 495.00,
  "exchange_currency": "KWD",
  "issued_date": "2026-01-22",
  "task_flight_details": [
    {
      "is_ancillary": false,
      "farebase": 450.00,
      "departure_time": "2026-02-15 14:00:00",
      "country_id_from": 1,
      "airport_from": "KWI",
      "terminal_from": "T1",
      "arrival_time": "2026-02-15 16:30:00",
      "duration_time": "2h 30m",
      "country_id_to": 2,
      "airport_to": "DXB",
      "terminal_to": "T3",
      "airline_id": 1,
      "flight_number": "KU-671",
      "ticket_number": "3580878589",
      "class_type": "economy",
      "baggage_allowed": "30kg",
      "equipment": "Boeing 777",
      "seat_no": "12A"
    }
  ]
}</code></pre>
                            <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>
                    </section>

                    <!-- Hotel Task -->
                    <section id="hotel-task" class="mb-12">
                        <div class="flex items-center mb-4">
                            <span class="task-type-badge badge-hotel mr-3">HOTEL</span>
                            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">Hotel Tasks</h3>
                        </div>
                        <p class="text-gray-700 dark:text-gray-300 mb-4">
                            Hotel tasks require a <code class="bg-gray-200 dark:bg-gray-700 px-2 py-1 rounded text-sm">task_hotel_details</code> array. The system will automatically find or create hotels based on the hotel name.
                        </p>

                        <div class="bg-blue-50 dark:bg-blue-900/30 border-l-4 border-blue-400 p-4 rounded-md mb-6">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-info-circle text-blue-400"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-blue-700 dark:text-blue-200">
                                        <strong>Note:</strong> You only need to provide <code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">hotel_name</code>. The system will automatically search for existing hotels or create new ones if they don't exist.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Required Fields in <code class="bg-gray-200 dark:bg-gray-700 px-2 py-1 rounded text-sm">task_hotel_details</code></h4>
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Field</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Description</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                        <tr><td class="px-4 py-3 font-medium text-gray-900 dark:text-white">hotel_name</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">string</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">Hotel name (auto-creates if doesn't exist)</td></tr>
                                        <tr><td class="px-4 py-3 font-medium text-gray-900 dark:text-white">check_in</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">date</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">Check-in date (YYYY-MM-DD)</td></tr>
                                        <tr><td class="px-4 py-3 font-medium text-gray-900 dark:text-white">check_out</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">date</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">Check-out date (after check-in)</td></tr>
                                        <tr><td class="px-4 py-3 font-medium text-gray-900 dark:text-white">room_type</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">string</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">Room type</td></tr>
                                        <tr><td class="px-4 py-3 font-medium text-gray-900 dark:text-white">room_amount</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">integer</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">Number of rooms (min: 1)</td></tr>
                                        <tr><td class="px-4 py-3 font-medium text-gray-900 dark:text-white">rate</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">decimal</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">Room rate per night</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Example Request</h4>
                        <div class="code-block">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg mb-6 text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>{
  "reference": "HT-TEST-001",
  "status": "issued",
  "company_id": 1,
  "type": "hotel",
  "supplier_id": 5,
  "client_name": "Jane Smith",
  "agent_id": 1,
  "price": 300.00,
  "tax": 30.00,
  "total": 330.00,
  "issued_date": "2026-01-22",
  "task_hotel_details": [
    {
      "hotel_name": "Grand Hyatt Hotel",
      "booking_time": "2026-01-22 10:30:00",
      "check_in": "2026-02-20",
      "check_out": "2026-02-23",
      "room_reference": "RM-123456",
      "room_number": "305",
      "room_type": "Deluxe Double Room",
      "room_amount": 1,
      "room_details": "King bed, sea view",
      "rate": 100.00,
      "meal_type": "Breakfast included",
      "is_refundable": true
    }
  ]
}</code></pre>
                            <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>
                    </section>

                    <!-- Insurance Task -->
                    <section id="insurance-task" class="mb-12">
                        <div class="flex items-center mb-4">
                            <span class="task-type-badge badge-insurance mr-3">INSURANCE</span>
                            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">Insurance Tasks</h3>
                        </div>
                        <p class="text-gray-700 dark:text-gray-300 mb-4">
                            Insurance tasks require a <code class="bg-gray-200 dark:bg-gray-700 px-2 py-1 rounded text-sm">task_insurance_details</code> array containing policy information.
                        </p>

                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Required Fields in <code class="bg-gray-200 dark:bg-gray-700 px-2 py-1 rounded text-sm">task_insurance_details</code></h4>
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Field</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Description</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                        <tr><td class="px-4 py-3 font-medium text-gray-900 dark:text-white">date</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">string</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">Coverage year only (YYYY)</td></tr>
                                        <tr><td class="px-4 py-3 font-medium text-gray-900 dark:text-white">paid_leaves</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">integer</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">Number of paid leave days</td></tr>
                                        <tr><td class="px-4 py-3 font-medium text-gray-900 dark:text-white">document_reference</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">string</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">Policy document reference</td></tr>
                                        <tr><td class="px-4 py-3 font-medium text-gray-900 dark:text-white">insurance_type</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">string</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">Type of insurance</td></tr>
                                        <tr><td class="px-4 py-3 font-medium text-gray-900 dark:text-white">destination</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">string</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">Coverage destination</td></tr>
                                        <tr><td class="px-4 py-3 font-medium text-gray-900 dark:text-white">plan_type</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">string</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">Insurance plan type</td></tr>
                                        <tr><td class="px-4 py-3 font-medium text-gray-900 dark:text-white">duration</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">string</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">Coverage duration</td></tr>
                                        <tr><td class="px-4 py-3 font-medium text-gray-900 dark:text-white">package</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">string</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">Package name</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Example Request</h4>
                        <div class="code-block">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg mb-6 text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>{
  "reference": "INS-TEST-001",
  "status": "issued",
  "company_id": 1,
  "type": "insurance",
  "supplier_id": 10,
  "client_name": "Bob Johnson",
  "agent_id": 1,
  "price": 75.00,
  "tax": 7.50,
  "total": 82.50,
  "issued_date": "2026-01-22",
  "task_insurance_details": [
    {
      "date": "2026",
      "paid_leaves": 0,
      "document_reference": "INS-DOC-789456",
      "insurance_type": "Travel Insurance",
      "destination": "Europe",
      "plan_type": "Comprehensive",
      "duration": "15 days",
      "package": "Premium Travel Package"
    }
  ]
}</code></pre>
                            <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>
                    </section>

                    <!-- Visa Task -->
                    <section id="visa-task" class="mb-12">
                        <div class="flex items-center mb-4">
                            <span class="task-type-badge badge-visa mr-3">VISA</span>
                            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">Visa Tasks</h3>
                        </div>
                        <p class="text-gray-700 dark:text-gray-300 mb-4">
                            Visa tasks require a <code class="bg-gray-200 dark:bg-gray-700 px-2 py-1 rounded text-sm">task_visa_details</code> array containing visa application information.
                        </p>

                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Required Fields in <code class="bg-gray-200 dark:bg-gray-700 px-2 py-1 rounded text-sm">task_visa_details</code></h4>
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Field</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Description</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                        <tr><td class="px-4 py-3 font-medium text-gray-900 dark:text-white">visa_type</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">string</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">Type of visa</td></tr>
                                        <tr><td class="px-4 py-3 font-medium text-gray-900 dark:text-white">application_number</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">string</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">Application reference number</td></tr>
                                        <tr><td class="px-4 py-3 font-medium text-gray-900 dark:text-white">expiry_date</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">date</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">Visa expiry (must be in future)</td></tr>
                                        <tr><td class="px-4 py-3 font-medium text-gray-900 dark:text-white">number_of_entries</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">enum</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">"single", "double", or "multiple"</td></tr>
                                        <tr><td class="px-4 py-3 font-medium text-gray-900 dark:text-white">stay_duration</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">integer</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">Stay duration in days</td></tr>
                                        <tr><td class="px-4 py-3 font-medium text-gray-900 dark:text-white">issuing_country</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">string</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">Visa issuing country</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Example Request</h4>
                        <div class="code-block">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg mb-6 text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>{
  "reference": "VISA-TEST-001",
  "status": "issued",
  "company_id": 1,
  "type": "visa",
  "supplier_id": 15,
  "client_name": "Alice Brown",
  "agent_id": 1,
  "price": 150.00,
  "tax": 15.00,
  "total": 165.00,
  "issued_date": "2026-01-22",
  "task_visa_details": [
    {
      "visa_type": "Tourist Visa",
      "application_number": "VISA-APP-456789",
      "expiry_date": "2026-08-22",
      "number_of_entries": "double",
      "stay_duration": 90,
      "issuing_country": "United Kingdom"
    }
  ]
}</code></pre>
                            <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>
                    </section>

                    <!-- Utility Endpoints -->
                    <section id="utility-endpoints" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">Utility Endpoints</h2>
                        <p class="text-lg text-gray-700 dark:text-gray-300 mb-6">
                            Before creating tasks, you can use these utility endpoints to validate IDs and fetch reference data from your system. These endpoints help ensure your webhook requests have valid foreign key references.
                        </p>

                        <div class="bg-blue-50 dark:bg-blue-900/30 border-l-4 border-blue-400 p-4 rounded-md mb-8">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-lightbulb text-blue-400"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-blue-700 dark:text-blue-200">
                                        <strong>Tip:</strong> Use these endpoints to validate your data before sending task creation requests. This helps prevent validation errors and improves integration reliability.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <!-- Get Task Structure -->
                            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Get Task Structure</h3>
                                        <span class="method-badge http-post">POST</span>
                                    </div>
                                    <code class="text-sm text-gray-600 dark:text-gray-400">/api/get-task-structure</code>
                                </div>
                                <div class="px-6 py-4">
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Retrieve the complete task structure and schema for a specific task type.</p>
                                    <div class="code-block">
                                        <pre class="bg-gray-100 dark:bg-gray-800 p-3 rounded text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>// Request
{ "type": "flight" }

// Response
{
  "status": "success",
  "data": {
    "fields": [...],
    "detail_fields": [...]
  }
}</code></pre>
                                        <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                            <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Get Client -->
                            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Get Client</h3>
                                        <span class="method-badge http-post">POST</span>
                                    </div>
                                    <code class="text-sm text-gray-600 dark:text-gray-400">/api/get-client</code>
                                </div>
                                <div class="px-6 py-4">
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Fetch client information by ID or search criteria.</p>
                                    <div class="code-block">
                                        <pre class="bg-gray-100 dark:bg-gray-800 p-3 rounded text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>// Request
{ "client_id": 123 }

// Response
{
  "status": "success",
  "data": {
    "id": 123,
    "name": "John Doe",
    "email": "john@example.com"
  }
}</code></pre>
                                        <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                            <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Get Company -->
                            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Get Company</h3>
                                        <span class="method-badge http-post">POST</span>
                                    </div>
                                    <code class="text-sm text-gray-600 dark:text-gray-400">/api/get-company</code>
                                </div>
                                <div class="px-6 py-4">
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Retrieve company details by company ID.</p>
                                    <div class="code-block">
                                        <pre class="bg-gray-100 dark:bg-gray-800 p-3 rounded text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>// Request
{ "company_id": 1 }

// Response
{
  "status": "success",
  "data": {
    "id": 1,
    "name": "ACME Travel Agency"
  }
}</code></pre>
                                        <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                            <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Get Agent -->
                            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Get Agent</h3>
                                        <span class="method-badge http-post">POST</span>
                                    </div>
                                    <code class="text-sm text-gray-600 dark:text-gray-400">/api/get-agent</code>
                                </div>
                                <div class="px-6 py-4">
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Fetch agent information by agent ID.</p>
                                    <div class="code-block">
                                        <pre class="bg-gray-100 dark:bg-gray-800 p-3 rounded text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>// Request
{ "agent_id": 5 }

// Response
{
  "status": "success",
  "data": {
    "id": 5,
    "name": "Sarah Agent"
  }
}</code></pre>
                                        <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                            <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Get Supplier -->
                            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Get Supplier</h3>
                                        <span class="method-badge http-post">POST</span>
                                    </div>
                                    <code class="text-sm text-gray-600 dark:text-gray-400">/api/get-supplier</code>
                                </div>
                                <div class="px-6 py-4">
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Retrieve supplier details by supplier ID.</p>
                                    <div class="code-block">
                                        <pre class="bg-gray-100 dark:bg-gray-800 p-3 rounded text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>// Request
{ "supplier_id": 2 }

// Response
{
  "status": "success",
  "data": {
    "id": 2,
    "name": "Amadeus"
  }
}</code></pre>
                                        <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                            <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Get Country -->
                            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Get Country</h3>
                                        <span class="method-badge http-post">POST</span>
                                    </div>
                                    <code class="text-sm text-gray-600 dark:text-gray-400">/api/get-country</code>
                                </div>
                                <div class="px-6 py-4">
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Fetch country information by country ID (useful for flight tasks).</p>
                                    <div class="code-block">
                                        <pre class="bg-gray-100 dark:bg-gray-800 p-3 rounded text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>// Request
{ "country_id": 1 }

// Response
{
  "status": "success",
  "data": {
    "id": 1,
    "name": "Kuwait",
    "code": "KW"
  }
}</code></pre>
                                        <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                            <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Get Hotel -->
                            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Get Hotel</h3>
                                        <span class="method-badge http-post">POST</span>
                                    </div>
                                    <code class="text-sm text-gray-600 dark:text-gray-400">/api/get-hotel</code>
                                </div>
                                <div class="px-6 py-4">
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Search for hotel by name or retrieve hotel details by ID.</p>
                                    <div class="code-block">
                                        <pre class="bg-gray-100 dark:bg-gray-800 p-3 rounded text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>// Request
{ "hotel_name": "Grand Hyatt Hotel" }

// Response
{
  "status": "success",
  "data": {
    "id": 42,
    "name": "Grand Hyatt Hotel",
    "city": "Dubai"
  }
}</code></pre>
                                        <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                            <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Responses -->
                    <section id="responses" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">API Responses</h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                            <!-- Success Response -->
                            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                                <div class="px-4 py-5 border-b border-gray-200 dark:border-gray-700 sm:px-6">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white flex items-center">
                                        <span class="h-6 w-6 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center mr-2">
                                            <i class="fas fa-check text-green-600 dark:text-green-400 text-sm"></i>
                                        </span>
                                        Success (201 Created)
                                    </h3>
                                </div>
                                <div class="px-4 py-5 sm:p-6">
                                    <div class="code-block">
                                        <pre class="bg-gray-100 dark:bg-gray-800 p-3 rounded text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>{
  "status": "success",
  "message": "Task created successfully via webhook",
  "data": {
    "task_id": 12345,
    "reference": "FL-TEST-001",
    "type": "flight",
    "enabled": true
  }
}</code></pre>
                                        <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                            <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Validation Error Response -->
                            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                                <div class="px-4 py-5 border-b border-gray-200 dark:border-gray-700 sm:px-6">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white flex items-center">
                                        <span class="h-6 w-6 rounded-full bg-red-100 dark:bg-red-900 flex items-center justify-center mr-2">
                                            <i class="fas fa-times text-red-600 dark:text-red-400 text-sm"></i>
                                        </span>
                                        Validation Error (422)
                                    </h3>
                                </div>
                                <div class="px-4 py-5 sm:p-6">
                                    <div class="code-block">
                                        <pre class="bg-gray-100 dark:bg-gray-800 p-3 rounded text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>{
  "status": "error",
  "message": "Validation failed",
  "errors": {
    "reference": [
      "The reference field is required."
    ],
    "company_id": [
      "The company id field is required."
    ]
  }
}</code></pre>
                                        <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                            <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Error Handling -->
                    <section id="error-handling" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">Error Handling</h2>
                        <p class="text-lg text-gray-700 dark:text-gray-300 mb-4">
                            The API uses standard HTTP status codes to indicate success or failure. All error responses include detailed information to help diagnose issues.
                        </p>

                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-8">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status Code</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Description</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-green-600 dark:text-green-400">201 Created</td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Task created successfully</td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-red-600 dark:text-red-400">422 Unprocessable Entity</td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Validation failed - check request fields</td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-red-600 dark:text-red-400">500 Internal Server Error</td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Task creation failed due to server error</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-3">Common Validation Errors</h3>
                        <div class="space-y-3">
                            <div class="bg-red-50 dark:bg-red-900/20 border-l-4 border-red-400 p-4 rounded-md">
                                <p class="text-sm text-red-700 dark:text-red-300 font-medium">Missing required fields</p>
                                <p class="text-sm text-red-600 dark:text-red-400 mt-1">Ensure all required fields are included in your request</p>
                            </div>
                            <div class="bg-red-50 dark:bg-red-900/20 border-l-4 border-red-400 p-4 rounded-md">
                                <p class="text-sm text-red-700 dark:text-red-300 font-medium">Invalid foreign key reference</p>
                                <p class="text-sm text-red-600 dark:text-red-400 mt-1">company_id, supplier_id, agent_id, airline_id, or country_id does not exist in the database</p>
                            </div>
                            <div class="bg-red-50 dark:bg-red-900/20 border-l-4 border-red-400 p-4 rounded-md">
                                <p class="text-sm text-red-700 dark:text-red-300 font-medium">Invalid date format</p>
                                <p class="text-sm text-red-600 dark:text-red-400 mt-1">Dates must be in YYYY-MM-DD format. Datetimes must be in YYYY-MM-DD HH:MM:SS format</p>
                            </div>
                        </div>
                    </section>

                    <!-- Best Practices -->
                    <section id="best-practices" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">Best Practices</h2>
                        <div class="space-y-6">
                            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                                <div class="px-4 py-5 sm:p-6">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-2">
                                        <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                        Use Unique References
                                    </h3>
                                    <p class="text-gray-500 dark:text-gray-400">
                                        Always use unique reference numbers for each task. The system will detect existing tasks and update them instead of creating duplicates.
                                    </p>
                                </div>
                            </div>

                            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                                <div class="px-4 py-5 sm:p-6">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-2">
                                        <i class="fas fa-database text-blue-500 mr-2"></i>
                                        Validate Foreign Keys
                                    </h3>
                                    <p class="text-gray-500 dark:text-gray-400">
                                        Ensure all IDs (company_id, supplier_id, agent_id, etc.) exist in your database before sending requests. Invalid IDs will result in validation errors.
                                    </p>
                                </div>
                            </div>

                            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                                <div class="px-4 py-5 sm:p-6">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-2">
                                        <i class="fas fa-calendar-check text-purple-500 mr-2"></i>
                                        Use Correct Date Formats
                                    </h3>
                                    <p class="text-gray-500 dark:text-gray-400">
                                        Always use ISO date formats: YYYY-MM-DD for dates and YYYY-MM-DD HH:MM:SS for datetimes. Future dates should be validated on your end before sending.
                                    </p>
                                </div>
                            </div>

                            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                                <div class="px-4 py-5 sm:p-6">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-2">
                                        <i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>
                                        Handle Errors Gracefully
                                    </h3>
                                    <p class="text-gray-500 dark:text-gray-400">
                                        Always check the response status and handle validation errors appropriately. Log errors for debugging and implement retry logic for server errors.
                                    </p>
                                </div>
                            </div>

                            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                                <div class="px-4 py-5 sm:p-6">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-2">
                                        <i class="fas fa-file-alt text-indigo-500 mr-2"></i>
                                        Monitor Logs
                                    </h3>
                                    <p class="text-gray-500 dark:text-gray-400">
                                        All webhook requests are logged with the [WEBHOOK] prefix in storage/logs/laravel.log. Monitor these logs to debug integration issues.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div class="border-t border-gray-200 dark:border-gray-700 pt-8 mt-12">
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Need more help?</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Contact our development team for assistance with API integration.
                                </p>
                            </div>
                            <div class="mt-4 md:mt-0">
                                <a href="#" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                    Contact Support
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
                &copy; <?php echo e(date('Y')); ?> <?php echo e(config('app.name')); ?>. Developer API Documentation.
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

                const icon = link.querySelector('i, svg');
                if (icon) {
                    icon.classList.remove('text-primary-600', 'dark:text-primary-400');
                    icon.classList.add('text-gray-500', 'dark:text-gray-400');
                }
            });

            const activeLink = document.querySelector(`.sidebar-link[href="${hash}"]`);
            if (activeLink) {
                activeLink.classList.add('bg-gray-100', 'dark:bg-gray-700', 'text-primary-600', 'dark:text-primary-400');
                activeLink.classList.remove('text-gray-900', 'dark:text-white');

                const icon = activeLink.querySelector('i, svg');
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
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/docs/developer-documentation.blade.php ENDPATH**/ ?>