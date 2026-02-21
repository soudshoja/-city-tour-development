<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DOTW v1.0 Documentation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen">
    <div class="max-w-4xl mx-auto px-6 py-16">

        <!-- Header -->
        <div class="mb-12 text-center">
            <div class="flex items-center justify-center mb-4">
                <img src="https://webbeds.com/wp-content/uploads/2023/05/WebBeds-Logo-white.svg"
                     alt="Webbeds / DOTW" class="h-10 mr-4" onerror="this.style.display='none'">
            </div>
            <h1 class="text-4xl font-bold text-white mb-3">DOTW v1.0 Documentation</h1>
            <p class="text-gray-400 text-lg">B2B Hotel Booking API — GraphQL · Multi-Tenant · n8n Automation</p>
            <div class="mt-4 flex items-center justify-center gap-3 text-sm text-gray-500">
                <span class="bg-green-900/50 text-green-400 px-3 py-1 rounded-full border border-green-800">v1.0 STABLE</span>
                <span class="bg-blue-900/50 text-blue-400 px-3 py-1 rounded-full border border-blue-800">Laravel 11</span>
                <span class="bg-purple-900/50 text-purple-400 px-3 py-1 rounded-full border border-purple-800">GraphQL · Sanctum</span>
            </div>
        </div>

        <!-- Doc cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

            <a href="{{ url('/docs/dotw/overview') }}"
               class="group block bg-gray-900 border border-gray-800 rounded-xl p-6 hover:border-blue-600 hover:bg-gray-800 transition-all duration-200">
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 bg-blue-600/20 rounded-lg flex items-center justify-center shrink-0">
                        <i class="fas fa-book-open text-blue-400"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-white group-hover:text-blue-400 transition-colors">Overview & Quick Start</h2>
                        <p class="text-gray-400 text-sm mt-1">Master document — executive summary, quick start guide, and cross-reference index.</p>
                    </div>
                </div>
            </a>

            <a href="{{ url('/docs/dotw/api') }}"
               class="group block bg-gray-900 border border-gray-800 rounded-xl p-6 hover:border-purple-600 hover:bg-gray-800 transition-all duration-200">
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 bg-purple-600/20 rounded-lg flex items-center justify-center shrink-0">
                        <i class="fas fa-code text-purple-400"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-white group-hover:text-purple-400 transition-colors">GraphQL API Reference</h2>
                        <p class="text-gray-400 text-sm mt-1">All queries &amp; mutations — getCities, searchHotels, getRoomRates, blockRates, createPreBooking.</p>
                    </div>
                </div>
            </a>

            <a href="{{ url('/docs/dotw/integration') }}"
               class="group block bg-gray-900 border border-gray-800 rounded-xl p-6 hover:border-green-600 hover:bg-gray-800 transition-all duration-200">
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 bg-green-600/20 rounded-lg flex items-center justify-center shrink-0">
                        <i class="fas fa-plug text-green-400"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-white group-hover:text-green-400 transition-colors">Integration Guide</h2>
                        <p class="text-gray-400 text-sm mt-1">Admin UI walkthrough, n8n workflow setup, credential management, API token generation.</p>
                    </div>
                </div>
            </a>

            <a href="{{ url('/docs/dotw/services') }}"
               class="group block bg-gray-900 border border-gray-800 rounded-xl p-6 hover:border-yellow-600 hover:bg-gray-800 transition-all duration-200">
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 bg-yellow-600/20 rounded-lg flex items-center justify-center shrink-0">
                        <i class="fas fa-cogs text-yellow-400"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-white group-hover:text-yellow-400 transition-colors">Services Documentation</h2>
                        <p class="text-gray-400 text-sm mt-1">DotwService, CacheService, CircuitBreakerService, AuditService — all methods &amp; config.</p>
                    </div>
                </div>
            </a>

            <a href="{{ url('/docs/dotw/architecture') }}"
               class="group block bg-gray-900 border border-gray-800 rounded-xl p-6 hover:border-red-600 hover:bg-gray-800 transition-all duration-200 md:col-span-2">
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 bg-red-600/20 rounded-lg flex items-center justify-center shrink-0">
                        <i class="fas fa-sitemap text-red-400"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-white group-hover:text-red-400 transition-colors">Architecture & Data Models</h2>
                        <p class="text-gray-400 text-sm mt-1">System diagram, all 6 data models, ERD, booking flow state transitions, security &amp; multi-tenant isolation.</p>
                    </div>
                </div>
            </a>

        </div>

        <p class="text-center text-gray-600 text-sm mt-12">DOTW v1.0 · Soud Laravel Platform · {{ now()->format('Y') }}</p>
    </div>
</body>
</html>
