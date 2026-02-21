<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DOTW v1.0 Documentation Hub</title>
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
        body { font-family: 'Inter', sans-serif; }

        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background-color: rgba(156,163,175,0.5); border-radius: 4px; }
        * { scrollbar-width: thin; scrollbar-color: rgba(156,163,175,0.5) transparent; }

        .doc-card { transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease; }
        .doc-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px rgba(0,0,0,0.3); }
        .doc-card:hover .card-icon { transform: scale(1.1); }
        .card-icon { transition: transform 0.2s ease; }

        .step-number {
            width: 2rem; height: 2rem;
            display: flex; align-items: center; justify-content: center;
            border-radius: 50%; font-weight: 700; font-size: 0.875rem; flex-shrink: 0;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 dark:bg-gray-900 dark:text-gray-100 transition-colors duration-200 min-h-screen">

    <!-- Header -->
    <header class="bg-white dark:bg-gray-800 shadow-sm sticky top-0 z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-primary-600">
                    <i class="fas fa-hotel text-white text-lg"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white">DOTW v1.0 Documentation</h1>
                    <p class="text-xs text-gray-500 dark:text-gray-400">B2B Hotel Booking API — GraphQL · Multi-Tenant · n8n Automation</p>
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
                <a href="{{ url('/docs/n8n') }}" class="text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300">
                    <i class="fas fa-cogs mr-1"></i> N8n Docs
                </a>
                <a href="{{ url('/') }}" class="text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300">
                    <i class="fas fa-home mr-1"></i> Dashboard
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

        <!-- Hero -->
        <div class="text-center mb-12">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-primary-600/10 dark:bg-primary-500/10 mb-4">
                <i class="fas fa-hotel text-3xl text-primary-600 dark:text-primary-400"></i>
            </div>
            <h2 class="text-3xl font-extrabold text-gray-900 dark:text-white mb-3">DOTW v1.0 Documentation</h2>
            <p class="text-lg text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
                Complete developer reference for the DOTW B2B hotel booking integration.
                Everything you need to search, price, block, and book hotels via GraphQL.
            </p>
        </div>

        <!-- Doc Cards -->
        <section class="mb-16">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

                <!-- Overview -->
                <a href="{{ url('/docs/dotw/overview') }}" class="doc-card block bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden hover:border-primary-500 dark:hover:border-primary-500">
                    <div class="h-1 bg-blue-500"></div>
                    <div class="p-6">
                        <div class="flex items-start space-x-4 mb-4">
                            <div class="card-icon flex items-center justify-center w-12 h-12 rounded-lg bg-blue-500/10 dark:bg-blue-500/20 flex-shrink-0">
                                <i class="fas fa-book-open text-xl text-blue-500"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Overview & Quick Start</h3>
                                <span class="text-xs px-1.5 py-0.5 rounded bg-blue-500/10 text-blue-400 font-medium mt-1 inline-block">Start Here</span>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-5 leading-relaxed">
                            Executive summary, 5-minute quick start guide, and cross-reference index across all DOTW docs.
                        </p>
                        <div class="flex items-center text-primary-600 dark:text-primary-400 text-sm font-medium">
                            <span>View Documentation</span>
                            <i class="fas fa-arrow-right ml-2 text-xs"></i>
                        </div>
                    </div>
                </a>

                <!-- API Reference -->
                <a href="{{ url('/docs/dotw/api') }}" class="doc-card block bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden hover:border-purple-500 dark:hover:border-purple-500">
                    <div class="h-1 bg-purple-500"></div>
                    <div class="p-6">
                        <div class="flex items-start space-x-4 mb-4">
                            <div class="card-icon flex items-center justify-center w-12 h-12 rounded-lg bg-purple-500/10 dark:bg-purple-500/20 flex-shrink-0">
                                <i class="fas fa-code text-xl text-purple-500"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">GraphQL API Reference</h3>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-5 leading-relaxed">
                            All queries & mutations — getCities, searchHotels, getRoomRates, blockRates, createPreBooking with examples.
                        </p>
                        <div class="flex items-center text-primary-600 dark:text-primary-400 text-sm font-medium">
                            <span>View Documentation</span>
                            <i class="fas fa-arrow-right ml-2 text-xs"></i>
                        </div>
                    </div>
                </a>

                <!-- Integration Guide -->
                <a href="{{ url('/docs/dotw/integration') }}" class="doc-card block bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden hover:border-green-500 dark:hover:border-green-500">
                    <div class="h-1 bg-green-500"></div>
                    <div class="p-6">
                        <div class="flex items-start space-x-4 mb-4">
                            <div class="card-icon flex items-center justify-center w-12 h-12 rounded-lg bg-green-500/10 dark:bg-green-500/20 flex-shrink-0">
                                <i class="fas fa-plug text-xl text-green-500"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Integration Guide</h3>
                                <div class="flex gap-1.5 mt-1">
                                    <span class="text-xs px-1.5 py-0.5 rounded bg-green-500/10 text-green-400 font-medium">Admin UI</span>
                                    <span class="text-xs px-1.5 py-0.5 rounded bg-orange-500/10 text-orange-400 font-medium">n8n</span>
                                </div>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-5 leading-relaxed">
                            Admin UI walkthrough, n8n workflow setup, credential management, and API token generation.
                        </p>
                        <div class="flex items-center text-primary-600 dark:text-primary-400 text-sm font-medium">
                            <span>View Documentation</span>
                            <i class="fas fa-arrow-right ml-2 text-xs"></i>
                        </div>
                    </div>
                </a>

                <!-- Services -->
                <a href="{{ url('/docs/dotw/services') }}" class="doc-card block bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden hover:border-yellow-500 dark:hover:border-yellow-500">
                    <div class="h-1 bg-yellow-500"></div>
                    <div class="p-6">
                        <div class="flex items-start space-x-4 mb-4">
                            <div class="card-icon flex items-center justify-center w-12 h-12 rounded-lg bg-yellow-500/10 dark:bg-yellow-500/20 flex-shrink-0">
                                <i class="fas fa-cogs text-xl text-yellow-500"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Services Documentation</h3>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-5 leading-relaxed">
                            DotwService, CacheService, CircuitBreakerService, AuditService — all methods, config, and error handling.
                        </p>
                        <div class="flex items-center text-primary-600 dark:text-primary-400 text-sm font-medium">
                            <span>View Documentation</span>
                            <i class="fas fa-arrow-right ml-2 text-xs"></i>
                        </div>
                    </div>
                </a>

                <!-- Architecture -->
                <a href="{{ url('/docs/dotw/architecture') }}" class="doc-card block bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden hover:border-red-500 dark:hover:border-red-500 md:col-span-2 lg:col-span-2">
                    <div class="h-1 bg-red-500"></div>
                    <div class="p-6">
                        <div class="flex items-start space-x-4 mb-4">
                            <div class="card-icon flex items-center justify-center w-12 h-12 rounded-lg bg-red-500/10 dark:bg-red-500/20 flex-shrink-0">
                                <i class="fas fa-sitemap text-xl text-red-500"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Architecture & Data Models</h3>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-5 leading-relaxed">
                            System diagram, all 6 data models, ERD, booking flow state transitions, security & multi-tenant isolation.
                        </p>
                        <div class="flex items-center text-primary-600 dark:text-primary-400 text-sm font-medium">
                            <span>View Documentation</span>
                            <i class="fas fa-arrow-right ml-2 text-xs"></i>
                        </div>
                    </div>
                </a>

            </div>
        </section>

        <div class="border-t border-gray-200 dark:border-gray-700 mb-12"></div>

        <!-- Quick Start + Architecture side by side -->
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
                    <div class="flex items-start space-x-3">
                        <div class="step-number bg-primary-600 text-white mt-0.5">1</div>
                        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
                            Go to <a href="{{ url('/settings') }}" class="text-primary-500 hover:text-primary-400 font-medium underline decoration-primary-500/30">Settings → DOTW tab</a> and enter your DOTW credentials (username, password, company code, markup %).
                        </p>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="step-number bg-primary-600 text-white mt-0.5">2</div>
                        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
                            Generate an API token from the <a href="{{ url('/admin/dotw') }}" class="text-primary-500 hover:text-primary-400 font-medium underline decoration-primary-500/30">DOTW Admin → API Tokens</a> tab and copy it.
                        </p>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="step-number bg-primary-600 text-white mt-0.5">3</div>
                        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
                            Read the <a href="{{ url('/docs/dotw/api') }}" class="text-primary-500 hover:text-primary-400 font-medium underline decoration-primary-500/30">GraphQL API Reference</a> and make your first <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded font-mono">getCities</code> call with <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded font-mono">Authorization: Bearer &lt;token&gt;</code>.
                        </p>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="step-number bg-primary-600 text-white mt-0.5">4</div>
                        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
                            Follow the booking flow: <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded font-mono">searchHotels</code> → <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded font-mono">getRoomRates</code> → <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded font-mono">blockRates</code> → <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded font-mono">createPreBooking</code>.
                        </p>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="step-number bg-primary-600 text-white mt-0.5">5</div>
                        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
                            Set up n8n using the <a href="{{ url('/docs/dotw/integration') }}" class="text-primary-500 hover:text-primary-400 font-medium underline decoration-primary-500/30">Integration Guide</a> to automate hotel search workflows.
                        </p>
                    </div>
                </div>
            </div>

            <!-- System Overview -->
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-8">
                <div class="flex items-center space-x-3 mb-6">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-purple-500/10 dark:bg-purple-500/20">
                        <i class="fas fa-sitemap text-lg text-purple-500"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">System Overview</h3>
                </div>
                <div class="space-y-4">
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 mt-1.5"><div class="w-2 h-2 rounded-full bg-blue-500"></div></div>
                        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
                            <span class="font-semibold text-gray-900 dark:text-white">n8n → GraphQL:</span>
                            n8n sends hotel search requests to Laravel's <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded font-mono">/graphql</code> endpoint using a Sanctum Bearer token.
                        </p>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 mt-1.5"><div class="w-2 h-2 rounded-full bg-purple-500"></div></div>
                        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
                            <span class="font-semibold text-gray-900 dark:text-white">DotwService:</span>
                            Routes requests to the DOTW SOAP API with a 25s timeout, caching, and per-company credentials.
                        </p>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 mt-1.5"><div class="w-2 h-2 rounded-full bg-green-500"></div></div>
                        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
                            <span class="font-semibold text-gray-900 dark:text-white">Circuit Breaker:</span>
                            5 failures in 60s opens the circuit for 30s on <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded font-mono">searchHotels</code> to prevent cascade failures.
                        </p>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 mt-1.5"><div class="w-2 h-2 rounded-full bg-yellow-500"></div></div>
                        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
                            <span class="font-semibold text-gray-900 dark:text-white">Multi-Tenant:</span>
                            Each company has isolated encrypted credentials. All operations are scoped by <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded font-mono">company_id</code>.
                        </p>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 mt-1.5"><div class="w-2 h-2 rounded-full bg-red-500"></div></div>
                        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
                            <span class="font-semibold text-gray-900 dark:text-white">Audit Trail:</span>
                            Every API call is logged to <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded font-mono">dotw_audit_logs</code> with sanitized payloads and trace IDs.
                        </p>
                    </div>
                </div>
            </div>

        </section>

        <p class="text-center text-gray-400 dark:text-gray-600 text-sm">
            DOTW v1.0 · Soud Laravel Platform · Built {{ now()->format('Y') }}
        </p>

    </main>

    <script>
        if (localStorage.getItem('darkMode') === 'false') {
            document.documentElement.classList.remove('dark');
        }
        document.getElementById('darkModeToggle').addEventListener('click', () => {
            document.documentElement.classList.toggle('dark');
            localStorage.setItem('darkMode', document.documentElement.classList.contains('dark'));
        });
    </script>
</body>
</html>
