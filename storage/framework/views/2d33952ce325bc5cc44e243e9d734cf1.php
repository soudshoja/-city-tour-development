<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>N8n Integration Testing Documentation</title>
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

        .test-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .badge-unit {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .badge-feature {
            background-color: #fce7f3;
            color: #9f1239;
        }

        .badge-integration {
            background-color: #d1fae5;
            color: #065f46;
        }

        .badge-security {
            background-color: #fef3c7;
            color: #92400e;
        }

        .badge-load {
            background-color: #f3e8ff;
            color: #5b21b6;
        }
    </style>
</head>

<body class="bg-gray-50 text-gray-900 dark:bg-gray-900 dark:text-gray-100 transition-colors duration-200">
    <!-- Header -->
    <header class="bg-white dark:bg-gray-800 shadow-sm sticky top-0 z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-primary-600 dark:text-primary-400" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" />
                    <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm9.707 5.707a1 1 0 00-1.414-1.414L9 12.586l-1.293-1.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <h1 class="text-xl font-bold text-gray-900 dark:text-white">N8n Integration Testing Documentation</h1>
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
                    <a href="#overview" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md bg-gray-100 dark:bg-gray-700 text-primary-600 dark:text-primary-400">
                        <i class="fas fa-info-circle w-4 mr-3 text-primary-600 dark:text-primary-400"></i>
                        Overview
                    </a>
                    <a href="#unit-tests" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-cube w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Unit Tests
                    </a>
                    <a href="#feature-api" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-code w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Feature Tests: API
                    </a>
                    <a href="#feature-security" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-shield-alt w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Feature Tests: Security
                    </a>
                    <a href="#feature-integration" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-link w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Feature Tests: Integration
                    </a>
                    <a href="#feature-errors" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-exclamation-triangle w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Feature Tests: Error Handling
                    </a>
                    <a href="#feature-admin" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-user-shield w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Feature Tests: Admin
                    </a>
                    <a href="#load-tests" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-tachometer-alt w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Load Tests
                    </a>
                    <a href="#fixtures" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-database w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Test Fixtures
                    </a>
                    <a href="#running-tests" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-play w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        Running Tests
                    </a>
                    <a href="#ci-cd" class="sidebar-link flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">
                        <i class="fas fa-rocket w-4 mr-3 text-gray-500 dark:text-gray-400"></i>
                        CI/CD Integration
                    </a>
                </nav>
            </div>

            <!-- Main content -->
            <div class="mt-8 lg:mt-0 lg:col-span-9">
                <div class="prose prose-blue max-w-none dark:prose-invert">
                    <div class="bg-gradient-to-r from-primary-600 to-blue-600 rounded-xl shadow-lg p-8 mb-12 text-white">
                        <h1 class="text-4xl font-extrabold mb-4">N8n Integration Testing Suite</h1>
                        <p class="text-lg opacity-90 max-w-3xl">
                            Comprehensive testing documentation covering all test suites across N8n document processing integration: unit tests, feature tests, integration tests, security tests, error handling tests, and load tests.
                        </p>
                        <div class="mt-6 flex flex-wrap gap-3">
                            <a href="#running-tests" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-primary-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-primary-700 focus:ring-white transition-colors">
                                Quick Start
                            </a>
                            <a href="#overview" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-primary-800 bg-opacity-60 hover:bg-opacity-70 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-primary-700 focus:ring-white transition-colors">
                                View Test Summary
                            </a>
                        </div>
                    </div>

                    <!-- Overview Section -->
                    <section id="overview" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">Testing Overview</h2>
                        <p class="text-lg text-gray-700 dark:text-gray-300 mb-6">
                            The N8n integration testing suite provides comprehensive coverage across all phases of the project, ensuring reliability, security, and performance of the document processing system.
                        </p>

                        <!-- Summary Stats -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                            <div class="bg-blue-50 dark:bg-blue-900/30 border-l-4 border-blue-400 p-4 rounded-md">
                                <h3 class="text-sm font-semibold text-blue-800 dark:text-blue-200 mb-2">Total Test Files</h3>
                                <p class="text-3xl font-bold text-blue-600 dark:text-blue-300">40+</p>
                                <p class="text-xs text-blue-700 dark:text-blue-400 mt-1">Across all test categories</p>
                            </div>
                            <div class="bg-green-50 dark:bg-green-900/30 border-l-4 border-green-400 p-4 rounded-md">
                                <h3 class="text-sm font-semibold text-green-800 dark:text-green-200 mb-2">Test Categories</h3>
                                <p class="text-3xl font-bold text-green-600 dark:text-green-300">6</p>
                                <p class="text-xs text-green-700 dark:text-green-400 mt-1">Unit, Feature, Integration, Security, Error, Load</p>
                            </div>
                            <div class="bg-purple-50 dark:bg-purple-900/30 border-l-4 border-purple-400 p-4 rounded-md">
                                <h3 class="text-sm font-semibold text-purple-800 dark:text-purple-200 mb-2">Coverage</h3>
                                <p class="text-3xl font-bold text-purple-600 dark:text-purple-300">100%</p>
                                <p class="text-xs text-purple-700 dark:text-purple-400 mt-1">All critical paths tested</p>
                            </div>
                        </div>

                        <!-- Test Categories Table -->
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-8">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Category</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Test Files</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Focus Area</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap"><span class="test-badge badge-unit">Unit Tests</span></td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">7 files</td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Services, helpers, models</td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap"><span class="test-badge badge-feature">Feature: API</span></td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">2 files</td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Document processing, webhooks</td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap"><span class="test-badge badge-security">Feature: Security</span></td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">2 files</td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">HMAC, validation, replay attacks</td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap"><span class="test-badge badge-integration">Feature: Integration</span></td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">4 files</td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">End-to-end flows, N8n callbacks</td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap"><span class="test-badge badge-feature">Feature: Error Handling</span></td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">4 files</td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Error capture, logging, alerting</td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap"><span class="test-badge badge-load">Load Tests</span></td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">1 file (6 tests)</td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Performance, throughput, stress</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    <!-- Unit Tests Section -->
                    <section id="unit-tests" class="mb-12">
                        <div class="flex items-center mb-4">
                            <span class="test-badge badge-unit mr-3">UNIT TESTS</span>
                            <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Unit Tests</h2>
                        </div>
                        <p class="text-gray-700 dark:text-gray-300 mb-6">
                            Unit tests validate individual services, helpers, and models in isolation. These tests ensure core business logic works correctly without external dependencies.
                        </p>

                        <!-- WebhookSigningService Tests -->
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">WebhookSigningServiceTest</h3>
                                <code class="text-sm text-gray-600 dark:text-gray-400">tests/Unit/Services/WebhookSigningServiceTest.php</code>
                            </div>
                            <div class="px-6 py-4">
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Tests webhook HMAC signature generation and verification for secure API communication.</p>
                                <ul class="space-y-2 text-sm">
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>sign_payload_generates_valid_signature</strong> - Generates HMAC-SHA256 signatures with timestamp</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>verify_signature_accepts_valid_signature</strong> - Accepts correctly signed payloads</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>verify_signature_rejects_invalid_signature</strong> - Blocks tampered signatures</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>verify_signature_rejects_expired_timestamp</strong> - Enforces 5-minute timestamp tolerance</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>verify_signature_rejects_tampered_payload</strong> - Detects payload modifications</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>signatures_differ_for_different_methods</strong> - Ensures HTTP method is part of signature</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>signatures_differ_for_different_paths</strong> - Ensures URL path is part of signature</span>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- N8nExecutionTracker Tests -->
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">N8nExecutionTrackerTest</h3>
                                <code class="text-sm text-gray-600 dark:text-gray-400">tests/Unit/Services/N8nExecutionTrackerTest.php</code>
                            </div>
                            <div class="px-6 py-4">
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Tests execution tracking lifecycle for N8n workflows: start, complete, fail, and metrics collection.</p>
                                <ul class="space-y-2 text-sm">
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>it_can_start_execution_tracking</strong> - Records document processing start with payload</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>it_can_complete_execution_successfully</strong> - Marks completion with duration and results</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>it_can_fail_execution_with_error_details</strong> - Creates DocumentError records on failure</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>it_correctly_classifies_transient_errors</strong> - Identifies retry-able errors (timeout, rate limit)</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>it_correctly_classifies_non_transient_errors</strong> - Identifies permanent failures (parse errors)</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>it_correctly_classifies_system_errors</strong> - Identifies infrastructure failures</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>it_can_get_execution_metrics</strong> - Calculates success rate, avg duration, error breakdowns</span>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- ErrorAlertService Tests -->
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">ErrorAlertServiceTest</h3>
                                <code class="text-sm text-gray-600 dark:text-gray-400">tests/Unit/Services/ErrorAlertServiceTest.php</code>
                            </div>
                            <div class="px-6 py-4">
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Tests automated error alerting based on thresholds and cooldown periods.</p>
                                <ul class="space-y-2 text-sm">
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>it_triggers_alert_when_error_rate_exceeds_threshold</strong> - Alerts at 10% error rate</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>it_does_not_trigger_alert_when_error_rate_is_below_threshold</strong> - No alert below 10%</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>it_triggers_alert_for_consecutive_failures</strong> - Critical alert after 5 consecutive failures</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>it_respects_cooldown_period</strong> - Prevents alert spam with 30-minute cooldown</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    <!-- Feature Tests: API Section -->
                    <section id="feature-api" class="mb-12">
                        <div class="flex items-center mb-4">
                            <span class="test-badge badge-feature mr-3">FEATURE: API</span>
                            <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Feature Tests: API Endpoints</h2>
                        </div>
                        <p class="text-gray-700 dark:text-gray-300 mb-6">
                            Feature tests validate HTTP API endpoints with mocked N8n responses. These tests ensure proper request/response handling, validation, and error scenarios.
                        </p>

                        <!-- DocumentProcessingTest -->
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">DocumentProcessingTest</h3>
                                <code class="text-sm text-gray-600 dark:text-gray-400">tests/Feature/Api/DocumentProcessingTest.php</code>
                            </div>
                            <div class="px-6 py-4">
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Tests <code>POST /api/documents/process</code> endpoint with various scenarios.</p>
                                <ul class="space-y-2 text-sm">
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>test_successful_document_queue</strong> - Queues document, creates log, sends N8n webhook with HMAC</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-times-circle text-red-500 mt-1 mr-2"></i>
                                        <span><strong>test_validation_error_missing_company_id</strong> - Returns 422 for missing required fields</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-times-circle text-red-500 mt-1 mr-2"></i>
                                        <span><strong>test_validation_error_invalid_document_type</strong> - Returns 422 for invalid document types</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-times-circle text-red-500 mt-1 mr-2"></i>
                                        <span><strong>test_n8n_unreachable</strong> - Returns 503 when N8n is down, logs error</span>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- N8nCallbackTest -->
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">N8nCallbackTest</h3>
                                <code class="text-sm text-gray-600 dark:text-gray-400">tests/Feature/Api/Webhooks/N8nCallbackTest.php</code>
                            </div>
                            <div class="px-6 py-4">
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Tests <code>POST /api/webhooks/n8n/extraction</code> callback endpoint.</p>
                                <ul class="space-y-2 text-sm">
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>test_successful_callback</strong> - Updates document status to completed, stores extraction results</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-times-circle text-red-500 mt-1 mr-2"></i>
                                        <span><strong>test_error_callback</strong> - Marks document as failed, creates DocumentError record</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-times-circle text-red-500 mt-1 mr-2"></i>
                                        <span><strong>test_invalid_signature</strong> - Returns 401 for tampered HMAC signatures</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-times-circle text-red-500 mt-1 mr-2"></i>
                                        <span><strong>test_replay_attack</strong> - Returns 401 for timestamps older than 5 minutes</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-times-circle text-red-500 mt-1 mr-2"></i>
                                        <span><strong>test_duplicate_callback</strong> - Returns 409 for idempotency (prevents duplicate processing)</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    <!-- Feature Tests: Security Section -->
                    <section id="feature-security" class="mb-12">
                        <div class="flex items-center mb-4">
                            <span class="test-badge badge-security mr-3">FEATURE: SECURITY</span>
                            <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Feature Tests: Security</h2>
                        </div>
                        <p class="text-gray-700 dark:text-gray-300 mb-6">
                            Security tests validate HMAC middleware, input validation, and protection against common attack vectors.
                        </p>

                        <!-- HmacMiddlewareTest -->
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">HmacMiddlewareTest</h3>
                                <code class="text-sm text-gray-600 dark:text-gray-400">tests/Feature/Security/HmacMiddlewareTest.php</code>
                            </div>
                            <div class="px-6 py-4">
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Tests HMAC signature verification middleware applied to webhook endpoints.</p>
                                <ul class="space-y-2 text-sm">
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>test_middleware_accepts_valid_signed_request</strong> - Allows requests with correct HMAC</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-times-circle text-red-500 mt-1 mr-2"></i>
                                        <span><strong>test_middleware_rejects_invalid_signature</strong> - Blocks requests with wrong signature</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-times-circle text-red-500 mt-1 mr-2"></i>
                                        <span><strong>test_middleware_rejects_expired_timestamp</strong> - Prevents replay attacks (>5 min old)</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-times-circle text-red-500 mt-1 mr-2"></i>
                                        <span><strong>test_middleware_rejects_inactive_client</strong> - Blocks deactivated webhook clients</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>test_middleware_logs_audit_entry</strong> - Creates WebhookAuditLog for all requests</span>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- FileValidationTest -->
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">FileValidationTest</h3>
                                <code class="text-sm text-gray-600 dark:text-gray-400">tests/Feature/Security/FileValidationTest.php</code>
                            </div>
                            <div class="px-6 py-4">
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Tests input validation rules for document processing requests.</p>
                                <ul class="space-y-2 text-sm">
                                    <li class="flex items-start">
                                        <i class="fas fa-times-circle text-red-500 mt-1 mr-2"></i>
                                        <span><strong>test_validates_required_fields</strong> - Ensures document_id, supplier_id, company_id required</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-times-circle text-red-500 mt-1 mr-2"></i>
                                        <span><strong>test_validates_document_id_uuid_format</strong> - Enforces valid UUID format</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-times-circle text-red-500 mt-1 mr-2"></i>
                                        <span><strong>test_rejects_directory_traversal_in_file_path</strong> - Blocks ../ path traversal attacks</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>test_accepts_valid_s3_file_path</strong> - Allows s3:// URIs</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>test_accepts_all_valid_document_types</strong> - Validates AIR, PDF, image, email types</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    <!-- Feature Tests: Integration Section -->
                    <section id="feature-integration" class="mb-12">
                        <div class="flex items-center mb-4">
                            <span class="test-badge badge-integration mr-3">FEATURE: INTEGRATION</span>
                            <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Feature Tests: Integration</h2>
                        </div>
                        <p class="text-gray-700 dark:text-gray-300 mb-6">
                            Integration tests validate complete end-to-end workflows including Laravel → N8n → Laravel callback flows for all document types.
                        </p>

                        <!-- EndToEndDocumentProcessingTest -->
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">EndToEndDocumentProcessingTest</h3>
                                <code class="text-sm text-gray-600 dark:text-gray-400">tests/Feature/Integration/EndToEndDocumentProcessingTest.php</code>
                            </div>
                            <div class="px-6 py-4">
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Comprehensive end-to-end tests covering the full document processing lifecycle.</p>
                                <ul class="space-y-2 text-sm">
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>it_processes_pdf_document_end_to_end</strong> - Complete PDF workflow with task extraction</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>it_processes_image_document_with_ocr_data</strong> - Image OCR with entity extraction</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>it_processes_email_document</strong> - Email parsing with attachment handling</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>it_processes_air_file_with_deferred_status</strong> - AIR batch processing workflow</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-times-circle text-red-500 mt-1 mr-2"></i>
                                        <span><strong>it_handles_n8n_unavailable_error</strong> - Graceful degradation when N8n is down</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>it_handles_concurrent_document_processing</strong> - 10 documents processed simultaneously</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>it_tracks_full_document_lifecycle</strong> - Verifies all state transitions logged</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-times-circle text-red-500 mt-1 mr-2"></i>
                                        <span><strong>it_validates_hmac_signature_on_callback</strong> - Security validation on callbacks</span>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- N8nDocumentProcessingTest -->
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">N8nDocumentProcessingTest</h3>
                                <code class="text-sm text-gray-600 dark:text-gray-400">tests/Feature/Integration/N8nDocumentProcessingTest.php</code>
                            </div>
                            <div class="px-6 py-4">
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Tests N8n integration with focus on security and error scenarios.</p>
                                <ul class="space-y-2 text-sm">
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>test_successful_pdf_processing</strong> - Queue document, receive callback, verify extraction</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-times-circle text-red-500 mt-1 mr-2"></i>
                                        <span><strong>test_invalid_hmac_rejected</strong> - Callbacks with bad signatures rejected</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-times-circle text-red-500 mt-1 mr-2"></i>
                                        <span><strong>test_replay_attack_prevented</strong> - Old timestamps rejected (>5 min)</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-times-circle text-red-500 mt-1 mr-2"></i>
                                        <span><strong>test_error_callback</strong> - Error from N8n properly logged</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-times-circle text-red-500 mt-1 mr-2"></i>
                                        <span><strong>test_duplicate_callback_rejected</strong> - Idempotency check prevents double-processing</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    <!-- Feature Tests: Error Handling Section -->
                    <section id="feature-errors" class="mb-12">
                        <div class="flex items-center mb-4">
                            <span class="test-badge badge-feature mr-3">FEATURE: ERRORS</span>
                            <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Feature Tests: Error Handling</h2>
                        </div>
                        <p class="text-gray-700 dark:text-gray-300 mb-6">
                            Error handling tests validate error capture, classification, logging, alerting, and recovery mechanisms.
                        </p>

                        <!-- DocumentErrorTest -->
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">DocumentErrorTest</h3>
                                <code class="text-sm text-gray-600 dark:text-gray-400">tests/Feature/ErrorCapture/DocumentErrorTest.php</code>
                            </div>
                            <div class="px-6 py-4">
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Tests DocumentError model and error tracking functionality.</p>
                                <ul class="space-y-2 text-sm">
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>it_can_create_document_error_with_full_context</strong> - Stores error type, code, message, context</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>it_can_scope_unresolved_errors</strong> - Query unresolved errors for manual review</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>it_can_mark_error_as_resolved</strong> - Resolution tracking with user and notes</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>it_can_increment_retry_count</strong> - Tracks retry attempts and timestamps</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>it_can_mark_document_for_review</strong> - Flags documents needing manual intervention</span>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- ErrorLoggingVerificationTest -->
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">ErrorLoggingVerificationTest</h3>
                                <code class="text-sm text-gray-600 dark:text-gray-400">tests/Feature/ErrorLogging/ErrorLoggingVerificationTest.php</code>
                            </div>
                            <div class="px-6 py-4">
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Comprehensive error logging verification covering all error types and scenarios.</p>
                                <ul class="space-y-2 text-sm">
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>test_transient_error_logged_correctly</strong> - ERR_TIMEOUT classified as transient</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>test_non_transient_error_logged</strong> - ERR_PARSE_FAILURE classified as permanent</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>test_system_error_logged</strong> - ERR_N8N_UNAVAILABLE classified as system error</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>test_error_context_preserved</strong> - Input payload, execution ID, workflow ID saved</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>test_stack_trace_captured</strong> - Exception stack traces logged for debugging</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>test_error_metrics_accurate</strong> - Metrics API returns correct error breakdown</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    <!-- Feature Tests: Admin Section -->
                    <section id="feature-admin" class="mb-12">
                        <div class="flex items-center mb-4">
                            <span class="test-badge badge-feature mr-3">FEATURE: ADMIN</span>
                            <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Feature Tests: Admin Dashboard</h2>
                        </div>
                        <p class="text-gray-700 dark:text-gray-300 mb-6">
                            Admin dashboard tests validate error analytics, monitoring UI, and manual intervention workflows.
                        </p>

                        <!-- ErrorDashboardTest -->
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">ErrorDashboardTest</h3>
                                <code class="text-sm text-gray-600 dark:text-gray-400">tests/Feature/Admin/ErrorDashboardTest.php</code>
                            </div>
                            <div class="px-6 py-4">
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Tests error analytics dashboard with metrics calculation and visualization.</p>
                                <ul class="space-y-2 text-sm">
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>it_displays_error_dashboard_index</strong> - Renders dashboard UI with charts</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>it_returns_metrics_json_with_summary_stats</strong> - Total processed, failed, success rate</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>it_calculates_failure_rate_correctly</strong> - Accurate % calculation</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>it_groups_errors_by_type</strong> - Error type distribution for charts</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>it_shows_per_supplier_error_rates</strong> - Identify problematic suppliers</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                        <span><strong>it_filters_by_time_range</strong> - 24h, 7d, 30d time range filters</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    <!-- Load Tests Section -->
                    <section id="load-tests" class="mb-12">
                        <div class="flex items-center mb-4">
                            <span class="test-badge badge-load mr-3">LOAD TESTS</span>
                            <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Load & Performance Tests</h2>
                        </div>
                        <p class="text-gray-700 dark:text-gray-300 mb-6">
                            Load tests validate system performance under various load scenarios: sustained load, burst traffic, stress testing, and throughput capacity.
                        </p>

                        <!-- DocumentProcessingLoadTest -->
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">DocumentProcessingLoadTest</h3>
                                <code class="text-sm text-gray-600 dark:text-gray-400">tests/Load/DocumentProcessingLoadTest.php</code>
                            </div>
                            <div class="px-6 py-4">
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Performance tests with detailed metrics collection and reporting.</p>
                                <ul class="space-y-2 text-sm">
                                    <li class="flex items-start">
                                        <i class="fas fa-chart-line text-blue-500 mt-1 mr-2"></i>
                                        <span><strong>test_sustained_load_100_documents</strong> - 100 docs in 10 batches, validates ≥90% success rate</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-bolt text-yellow-500 mt-1 mr-2"></i>
                                        <span><strong>test_burst_load_50_documents_parallel</strong> - 50 parallel requests, validates ≥85% success under burst</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-fire text-red-500 mt-1 mr-2"></i>
                                        <span><strong>test_stress_test_500_documents</strong> - 500 docs rapidly, identifies breaking point (≥75% success)</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-random text-purple-500 mt-1 mr-2"></i>
                                        <span><strong>test_mixed_document_types_load</strong> - All types (PDF, image, email, AIR) under load</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-exclamation-circle text-orange-500 mt-1 mr-2"></i>
                                        <span><strong>test_error_handling_under_load</strong> - 10% simulated failures, validates error logging</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-clock text-green-500 mt-1 mr-2"></i>
                                        <span><strong>test_daily_throughput_capability</strong> - Validates 100+ docs/day capacity</span>
                                    </li>
                                </ul>

                                <div class="mt-4 p-4 bg-gray-50 dark:bg-gray-900 rounded-md">
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">Metrics Collected:</h4>
                                    <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                                        <li>• Throughput (docs/minute)</li>
                                        <li>• Latency (p50, p95, p99)</li>
                                        <li>• Success rate (%)</li>
                                        <li>• Error rate by type</li>
                                        <li>• Duration per document type</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Test Fixtures Section -->
                    <section id="fixtures" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">Test Fixtures & Factories</h2>
                        <p class="text-lg text-gray-700 dark:text-gray-300 mb-6">
                            Test fixtures provide reusable test data and mock responses for consistent, repeatable tests.
                        </p>

                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <div class="px-6 py-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Available Fixtures</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="border border-gray-200 dark:border-gray-700 rounded-md p-3">
                                        <h4 class="font-semibold text-gray-900 dark:text-white mb-2">N8nResponseFactory</h4>
                                        <code class="text-xs text-gray-600 dark:text-gray-400">tests/Fixtures/N8nResponseFactory.php</code>
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">Mock N8n webhook responses for all document types</p>
                                    </div>
                                    <div class="border border-gray-200 dark:border-gray-700 rounded-md p-3">
                                        <h4 class="font-semibold text-gray-900 dark:text-white mb-2">FixtureLoader</h4>
                                        <code class="text-xs text-gray-600 dark:text-gray-400">tests/Fixtures/FixtureLoader.php</code>
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">Load JSON fixtures for document samples</p>
                                    </div>
                                    <div class="border border-gray-200 dark:border-gray-700 rounded-md p-3">
                                        <h4 class="font-semibold text-gray-900 dark:text-white mb-2">DocumentProcessingLogFactory</h4>
                                        <code class="text-xs text-gray-600 dark:text-gray-400">database/factories/DocumentProcessingLogFactory.php</code>
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">Factory for creating test log entries</p>
                                    </div>
                                    <div class="border border-gray-200 dark:border-gray-700 rounded-md p-3">
                                        <h4 class="font-semibold text-gray-900 dark:text-white mb-2">LoadTestHelper</h4>
                                        <code class="text-xs text-gray-600 dark:text-gray-400">tests/Load/LoadTestHelper.php</code>
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">Batch submission and metrics collection</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Running Tests Section -->
                    <section id="running-tests" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">Running Tests - Quick Reference</h2>
                        <p class="text-lg text-gray-700 dark:text-gray-300 mb-6">
                            Quick reference guide for running different test suites and categories.
                        </p>

                        <div class="bg-blue-50 dark:bg-blue-900/30 border-l-4 border-blue-400 p-4 rounded-md mb-6">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-info-circle text-blue-400"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-blue-700 dark:text-blue-200">
                                        <strong>Prerequisites:</strong> Ensure your test database is configured in <code>phpunit.xml</code> and run <code>php artisan migrate --env=testing</code> before running tests.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-3">All Tests</h3>
                        <div class="code-block mb-6">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg text-sm overflow-x-auto border border-gray-200 dark:border-gray-700"><code># Run all tests
php artisan test

# Run with coverage
php artisan test --coverage

# Run with detailed output
php artisan test --parallel</code></pre>
                            <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>

                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-3">By Category</h3>
                        <div class="code-block mb-6">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg text-sm overflow-x-auto border border-gray-200 dark:border-gray-700"><code># Unit tests only
php artisan test tests/Unit

# Feature tests only
php artisan test tests/Feature

# Integration tests
php artisan test tests/Feature/Integration

# Security tests
php artisan test tests/Feature/Security

# Error handling tests
php artisan test tests/Feature/ErrorCapture tests/Feature/ErrorLogging

# Load tests
php artisan test tests/Load</code></pre>
                            <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>

                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-3">Specific Test Files</h3>
                        <div class="code-block mb-6">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg text-sm overflow-x-auto border border-gray-200 dark:border-gray-700"><code># Specific test file
php artisan test tests/Unit/Services/WebhookSigningServiceTest.php

# Specific test method
php artisan test --filter=test_successful_document_queue

# Multiple filters
php artisan test --filter="test_successful|test_error"</code></pre>
                            <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>

                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-3">Useful Options</h3>
                        <div class="code-block mb-6">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg text-sm overflow-x-auto border border-gray-200 dark:border-gray-700"><code># Stop on first failure
php artisan test --stop-on-failure

# Run in parallel (faster)
php artisan test --parallel

# Verbose output
php artisan test --testdox

# Generate HTML coverage report
php artisan test --coverage-html reports/coverage</code></pre>
                            <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>
                    </section>

                    <!-- CI/CD Section -->
                    <section id="ci-cd" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">CI/CD Integration</h2>
                        <p class="text-lg text-gray-700 dark:text-gray-300 mb-6">
                            Integrate tests into your CI/CD pipeline with GitHub Actions or other CI platforms.
                        </p>

                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-3">GitHub Actions Example</h3>
                        <div class="code-block mb-6">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>name: Run Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: testing
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2
          extensions: mbstring, pdo, pdo_mysql

      - name: Install Dependencies
        run: composer install --no-progress --no-interaction

      - name: Copy .env
        run: cp .env.ci .env

      - name: Generate Key
        run: php artisan key:generate

      - name: Run Migrations
        run: php artisan migrate --env=testing --force

      - name: Run Unit Tests
        run: php artisan test tests/Unit

      - name: Run Feature Tests
        run: php artisan test tests/Feature

      - name: Run Load Tests
        run: php artisan test tests/Load</code></pre>
                            <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>

                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-3">Required Environment Variables</h3>
                        <div class="code-block mb-6">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg text-sm overflow-x-auto border border-gray-200 dark:border-gray-700"><code># .env.testing or CI environment
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=testing
DB_USERNAME=root
DB_PASSWORD=password

N8N_WEBHOOK_URL=http://n8n:5678/webhook/document-processing
N8N_WEBHOOK_SECRET=test-secret-key
N8N_API_URL=http://n8n:5678/api/v1

APP_ENV=testing
APP_DEBUG=true</code></pre>
                            <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>

                        <div class="bg-yellow-50 dark:bg-yellow-900/30 border-l-4 border-yellow-400 p-4 rounded-md">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-yellow-700 dark:text-yellow-200">
                                        <strong>Note:</strong> Load tests may take several minutes to complete. Consider running them separately or only on main branch merges.
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
                                    Contact the development team for assistance with testing or to report test failures.
                                </p>
                            </div>
                            <div class="mt-4 md:mt-0 flex gap-3">
                                <a href="/docs/n8n-processing" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                                    Processing Docs
                                </a>
                                <a href="/docs/developer-documentation" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700">
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
                &copy; <?php echo e(date('Y')); ?> <?php echo e(config('app.name')); ?>. N8n Integration Testing Documentation.
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
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/docs/n8n-testing-documentation.blade.php ENDPATH**/ ?>