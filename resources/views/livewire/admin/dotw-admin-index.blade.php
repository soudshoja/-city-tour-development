<div x-data="{ activeTab: '{{ $activeTab }}' }" class="flex min-h-[500px] bg-white dark:bg-gray-800 rounded-xl shadow-sm">

    {{-- Left sidebar nav --}}
    <div class="w-56 border-r border-gray-200 dark:border-gray-700 p-4 flex-shrink-0">
        <nav class="space-y-1">

            {{-- Dashboard --}}
            <button
                @click="activeTab = 'dashboard'"
                :class="activeTab === 'dashboard'
                    ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600'
                    : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'"
                class="w-full flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-all">
                {{-- Heroicons outline: chart-bar --}}
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                Dashboard
            </button>

            {{-- Bookings --}}
            <button
                @click="activeTab = 'bookings'"
                :class="activeTab === 'bookings'
                    ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600'
                    : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'"
                class="w-full flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-all">
                {{-- Heroicons outline: calendar --}}
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                Bookings
            </button>

            {{-- Errors --}}
            <button
                @click="activeTab = 'errors'"
                :class="activeTab === 'errors'
                    ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600'
                    : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'"
                class="w-full flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-all">
                {{-- Heroicons outline: exclamation-triangle --}}
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                Errors
            </button>

            {{-- Divider --}}
            <div class="my-2 border-t border-gray-200 dark:border-gray-700"></div>

            {{-- Credentials --}}
            <button
                @click="activeTab = 'credentials'"
                :class="activeTab === 'credentials'
                    ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600'
                    : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'"
                class="w-full flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-all">
                {{-- Heroicons outline: key --}}
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                </svg>
                Credentials
            </button>

            {{-- Audit Logs --}}
            <button
                @click="activeTab = 'audit-logs'"
                :class="activeTab === 'audit-logs'
                    ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600'
                    : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'"
                class="w-full flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-all">
                {{-- Heroicons outline: document-text --}}
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Audit Logs
            </button>

            {{-- API Tokens — Super Admin only --}}
            @if($isSuperAdmin)
            <button
                @click="activeTab = 'api-tokens'"
                :class="activeTab === 'api-tokens'
                    ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600'
                    : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'"
                class="w-full flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-all">
                {{-- Heroicons outline: code-bracket --}}
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5"/>
                </svg>
                API Tokens
            </button>
            @endif

            {{-- Divider before Documentation --}}
            <div class="my-2 border-t border-gray-200 dark:border-gray-700"></div>

            {{-- Documentation --}}
            <button
                @click="activeTab = 'documentation'"
                :class="activeTab === 'documentation'
                    ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600'
                    : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'"
                class="w-full flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-all">
                {{-- Heroicons outline: book-open --}}
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 6.253v13m0-13C6.5 6.253 2 10.998 2 17.25m20-11.002c0 5.251-4.5 9.999-10 9.999M21 12a9 9 0 10-18 0 9 9 0 0018 0z"/>
                </svg>
                Documentation
            </button>

        </nav>
    </div>

    {{-- Content area --}}
    <div class="flex-1 p-6">

        {{-- Tab: Dashboard --}}
        <div x-show="activeTab === 'dashboard'" x-cloak>
            @livewire('admin.dotw-dashboard-tab')
        </div>

        {{-- Tab: Bookings --}}
        <div x-show="activeTab === 'bookings'" x-cloak>
            @livewire('admin.dotw-booking-lifecycle-tab')
        </div>

        {{-- Tab: Errors --}}
        <div x-show="activeTab === 'errors'" x-cloak>
            @livewire(\App\Http\Livewire\Admin\DotwErrorTrackerTab::class)
        </div>

        {{-- Tab 1: Credentials --}}
        <div x-show="activeTab === 'credentials'" x-cloak>
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">DOTW Credentials</h2>

            @if(session('credentials_saved'))
                <div class="mb-4 rounded-lg border border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-700 p-3 text-sm text-green-800 dark:text-green-200">
                    {{ session('credentials_saved') }}
                </div>
            @endif

            {{-- Company selector for Super Admin --}}
            @if($isSuperAdmin)
                <div class="mb-6">
                    <label for="selected_company_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Select Company
                    </label>
                    <select
                        id="selected_company_id"
                        wire:model.live="selected_company_id"
                        class="w-full max-w-md rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">-- Choose a company --</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}">{{ $company->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            {{-- Credentials form (shown for all when company is selected) --}}
            @if($isSuperAdmin ? $selected_company_id : true)
                <form wire:submit.prevent="saveCredentials" class="max-w-lg space-y-4">

                    <div>
                        <label for="dotw_username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            DOTW Username
                        </label>
                        <input
                            id="dotw_username"
                            type="text"
                            wire:model="dotw_username"
                            placeholder="Leave blank to keep existing"
                            autocomplete="off"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                        @error('dotw_username')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="dotw_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            DOTW Password
                        </label>
                        <input
                            id="dotw_password"
                            type="password"
                            wire:model="dotw_password"
                            placeholder="Leave blank to keep existing"
                            autocomplete="new-password"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                        @error('dotw_password')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="dotw_company_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            DOTW Company Code
                        </label>
                        <input
                            id="dotw_company_code"
                            type="text"
                            wire:model="dotw_company_code"
                            autocomplete="off"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                        @error('dotw_company_code')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="markup_percent" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Markup %
                        </label>
                        <input
                            id="markup_percent"
                            type="number"
                            wire:model="markup_percent"
                            step="0.01"
                            min="0"
                            max="100"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                        @error('markup_percent')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Toggles Section --}}
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-4 mt-4">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Product Tracks</h3>

                        <div class="space-y-3">
                            {{-- B2B Track --}}
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input
                                    type="checkbox"
                                    wire:model="b2b_enabled"
                                    class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                >
                                <span class="text-sm text-gray-700 dark:text-gray-300">Enable B2B Track</span>
                            </label>

                            {{-- B2C Track --}}
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input
                                    type="checkbox"
                                    wire:model="b2c_enabled"
                                    class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                >
                                <span class="text-sm text-gray-700 dark:text-gray-300">Enable B2C Track</span>
                            </label>

                            {{-- Active Status --}}
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input
                                    type="checkbox"
                                    wire:model="is_active"
                                    class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                >
                                <span class="text-sm text-gray-700 dark:text-gray-300">Credentials Active</span>
                            </label>
                        </div>
                    </div>

                    <div class="pt-2">
                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 text-sm font-medium transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7"/>
                            </svg>
                            Save Credentials
                        </button>
                    </div>

                </form>
            @else
                <p class="text-gray-500 dark:text-gray-400 text-sm">Select a company from the dropdown above to manage its credentials.</p>
            @endif
        </div>

        {{-- Tab 2: Audit Logs --}}
        <div x-show="activeTab === 'audit-logs'" x-cloak>
            @livewire('admin.dotw-audit-log-index')
        </div>

        {{-- Tab 3: API Tokens (Super Admin only) --}}
        <div x-show="activeTab === 'api-tokens'" x-cloak>
            @if($isSuperAdmin)
                {{-- @livewire(\App\Http\Livewire\Admin\DotwApiTokenIndex::class) --}}
                <p class="text-gray-500">API Tokens management coming soon...</p>
            @endif
        </div>

        {{-- Tab 4: Documentation --}}
        <div x-show="activeTab === 'documentation'" x-cloak dir="ltr" style="direction: ltr !important; text-align: left !important;" class="text-left ltr:text-left">
            <div class="max-w-4xl">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-6">DOTW API Documentation</h2>

                <div class="space-y-6">

                    {{-- Section 1: API Configuration --}}
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                        <h3 class="text-md font-semibold text-gray-800 dark:text-gray-100 mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            API Configuration
                        </h3>

                        <div class="space-y-3 text-sm text-gray-700 dark:text-gray-300">
                            <div>
                                <p class="font-medium text-gray-800 dark:text-gray-200">Base URL:</p>
                                <div class="flex items-center gap-2 mt-1">
                                    <code class="flex-1 bg-gray-100 dark:bg-gray-900 px-3 py-2 rounded text-xs font-mono break-all">{{ url('/api/dotwai') }}</code>
                                    <button @click="navigator.clipboard.writeText('{{ url('/api/dotwai') }}')" class="px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 rounded text-xs hover:bg-blue-200 dark:hover:bg-blue-800 transition">Copy</button>
                                </div>
                            </div>

                            <div class="border-t border-gray-200 dark:border-gray-700 pt-3">
                                <p class="font-medium text-gray-800 dark:text-gray-200">Authentication:</p>
                                <p class="text-gray-600 dark:text-gray-400 mt-2">All endpoints (except <code class="bg-gray-100 dark:bg-gray-900 px-1 rounded text-xs">/health</code> and <code class="bg-gray-100 dark:bg-gray-900 px-1 rounded text-xs">/payment_callback</code>) require a phone number to identify the client company.</p>
                                <p class="text-gray-600 dark:text-gray-400 mt-2">Send the phone number in one of three ways:</p>
                                <ul class="list-disc list-inside text-gray-600 dark:text-gray-400 mt-2 space-y-1 ml-2 [direction:ltr] [text-align:left]">
                                    <li style="text-align: left;">In request body: <code class="bg-gray-100 dark:bg-gray-900 px-1 rounded text-xs">{"telephone": "+96550000000"}</code></li>
                                    <li style="text-align: left;">In query string: <code class="bg-gray-100 dark:bg-gray-900 px-1 rounded text-xs">?telephone=+96550000000</code></li>
                                    <li style="text-align: left;">In header: <code class="bg-gray-100 dark:bg-gray-900 px-1 rounded text-xs">X-DotwAI-Phone: +96550000000</code></li>
                                </ul>
                            </div>

                            <div class="border-t border-gray-200 dark:border-gray-700 pt-3">
                                <p class="font-medium text-gray-800 dark:text-gray-200">Request Format:</p>
                                <ul class="list-disc list-inside text-gray-600 dark:text-gray-400 mt-2 space-y-1 ml-2 [direction:ltr] [text-align:left]">
                                    <li style="text-align: left;">Content-Type: <code class="bg-gray-100 dark:bg-gray-900 px-1 rounded text-xs">application/json</code></li>
                                    <li style="text-align: left;">Dates: <code class="bg-gray-100 dark:bg-gray-900 px-1 rounded text-xs">YYYY-MM-DD</code> format</li>
                                    <li style="text-align: left;">Timestamps: ISO 8601 format</li>
                                </ul>
                            </div>

                            <div class="border-t border-gray-200 dark:border-gray-700 pt-3 bg-blue-50 dark:bg-blue-900/20 p-3 rounded">
                                <p class="font-medium text-gray-800 dark:text-gray-200">How It Works:</p>
                                <p class="text-gray-600 dark:text-gray-400 text-xs mt-2">The phone number is resolved to a company account, DOTW credentials are fetched, and the request is processed on behalf of that company. This allows your automation tool or integration to identify clients by phone number without needing separate API keys per user.</p>
                            </div>
                        </div>
                    </div>

                    {{-- Section 2: Available Endpoints --}}
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                        <h3 class="text-md font-semibold text-gray-800 dark:text-gray-100 mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            Available Endpoints
                        </h3>

                        <div class="space-y-4">

                            {{-- Search Endpoints --}}
                            <div x-data="{ open: true }" class="border border-gray-100 dark:border-gray-600 rounded-lg">
                                <button @click="open = !open" class="w-full flex items-center justify-between p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                    <span class="font-medium text-gray-800 dark:text-gray-200">Search & Discovery</span>
                                    <svg x-show="!open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                                    </svg>
                                    <svg x-show="open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                                    </svg>
                                </button>

                                <div x-show="open" class="border-t border-gray-100 dark:border-gray-600 p-4 space-y-6 bg-gray-50 dark:bg-gray-900/30">

                                    {{-- POST /search_hotels --}}
                                    <div class="space-y-2">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-block px-2 py-1 bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 rounded text-xs font-semibold">POST</span>
                                            <code class="text-sm font-mono text-gray-800 dark:text-gray-200">/search_hotels</code>
                                        </div>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Search available hotels by city, dates, occupancy, and filters.</p>
                                        <details class="text-xs">
                                            <summary class="cursor-pointer font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100">View Details</summary>
                                            <div class="mt-2 space-y-2 bg-white dark:bg-gray-800 p-3 rounded border border-gray-200 dark:border-gray-700">
                                                <div>
                                                    <p class="font-medium text-gray-700 dark:text-gray-300">Required Parameters:</p>
                                                    <code class="block bg-gray-100 dark:bg-gray-900 p-2 rounded mt-1 text-xs overflow-x-auto">city, check_in, check_out, occupancy</code>
                                                </div>
                                                <div>
                                                    <p class="font-medium text-gray-700 dark:text-gray-300">Optional Parameters:</p>
                                                    <code class="block bg-gray-100 dark:bg-gray-900 p-2 rounded mt-1 text-xs overflow-x-auto">hotel_name, star_rating, meal_type, refundable_only, price_min, price_max</code>
                                                </div>
                                                <div>
                                                    <p class="font-medium text-gray-700 dark:text-gray-300">Example Request:</p>
                                                    <pre class="bg-gray-100 dark:bg-gray-900 p-2 rounded mt-1 text-xs overflow-x-auto"><code>{
  "telephone": "+96550000000",
  "city": "Dubai",
  "check_in": "2026-04-01",
  "check_out": "2026-04-05",
  "occupancy": [{"adults": 2, "children": 0}],
  "star_rating": 4,
  "meal_type": "Breakfast"
}</code></pre>
                                                </div>
                                                <div>
                                                    <p class="font-medium text-gray-700 dark:text-gray-300">Response:</p>
                                                    <pre class="bg-gray-100 dark:bg-gray-900 p-2 rounded mt-1 text-xs overflow-x-auto"><code>{
  "success": true,
  "data": {
    "hotels": [
      {
        "hotel_id": "12345",
        "name": "Burj Al Arab",
        "star_rating": 5,
        "check_in": "2026-04-01",
        "check_out": "2026-04-05",
        "rates": [
          {
            "rate_id": "R001",
            "room_type": "Suite",
            "meal_plan": "Breakfast",
            "price_per_night": 250.00,
            "total_price": 1000.00,
            "currency": "KWD",
            "cancellation_type": "Free"
          }
        ]
      }
    ]
  }
}</code></pre>
                                                </div>
                                            </div>
                                        </details>
                                    </div>

                                    {{-- POST /get_hotel_details --}}
                                    <div class="space-y-2">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-block px-2 py-1 bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 rounded text-xs font-semibold">POST</span>
                                            <code class="text-sm font-mono text-gray-800 dark:text-gray-200">/get_hotel_details</code>
                                        </div>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Get room types, rates, and cancellation policies for a specific hotel.</p>
                                        <details class="text-xs">
                                            <summary class="cursor-pointer font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100">View Details</summary>
                                            <div class="mt-2 space-y-2 bg-white dark:bg-gray-800 p-3 rounded border border-gray-200 dark:border-gray-700">
                                                <div>
                                                    <p class="font-medium text-gray-700 dark:text-gray-300">Required Parameters:</p>
                                                    <code class="block bg-gray-100 dark:bg-gray-900 p-2 rounded mt-1 text-xs overflow-x-auto">hotel_id, check_in, check_out, occupancy</code>
                                                </div>
                                                <div>
                                                    <p class="font-medium text-gray-700 dark:text-gray-300">Example Request:</p>
                                                    <pre class="bg-gray-100 dark:bg-gray-900 p-2 rounded mt-1 text-xs overflow-x-auto"><code>{
  "telephone": "+96550000000",
  "hotel_id": "12345",
  "check_in": "2026-04-01",
  "check_out": "2026-04-05",
  "occupancy": [{"adults": 2, "children": 0}]
}</code></pre>
                                                </div>
                                            </div>
                                        </details>
                                    </div>

                                    {{-- GET /get_cities --}}
                                    <div class="space-y-2">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-block px-2 py-1 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded text-xs font-semibold">GET</span>
                                            <code class="text-sm font-mono text-gray-800 dark:text-gray-200">/get_cities</code>
                                        </div>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">List available destination cities (optionally filtered by country code).</p>
                                        <details class="text-xs">
                                            <summary class="cursor-pointer font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100">View Details</summary>
                                            <div class="mt-2 space-y-2 bg-white dark:bg-gray-800 p-3 rounded border border-gray-200 dark:border-gray-700">
                                                <div>
                                                    <p class="font-medium text-gray-700 dark:text-gray-300">Optional Parameters:</p>
                                                    <code class="block bg-gray-100 dark:bg-gray-900 p-2 rounded mt-1 text-xs">country_code</code>
                                                </div>
                                                <div>
                                                    <p class="font-medium text-gray-700 dark:text-gray-300">Example Request:</p>
                                                    <code class="block bg-gray-100 dark:bg-gray-900 p-2 rounded mt-1 text-xs">/get_cities?telephone=+96550000000&country_code=AE</code>
                                                </div>
                                            </div>
                                        </details>
                                    </div>

                                </div>
                            </div>

                            {{-- Booking Endpoints --}}
                            <div x-data="{ open: false }" class="border border-gray-100 dark:border-gray-600 rounded-lg">
                                <button @click="open = !open" class="w-full flex items-center justify-between p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                    <span class="font-medium text-gray-800 dark:text-gray-200">Booking & Confirmation</span>
                                    <svg x-show="!open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                                    </svg>
                                    <svg x-show="open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                                    </svg>
                                </button>

                                <div x-show="open" class="border-t border-gray-100 dark:border-gray-600 p-4 space-y-6 bg-gray-50 dark:bg-gray-900/30">

                                    {{-- POST /prebook_hotel --}}
                                    <div class="space-y-2">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-block px-2 py-1 bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 rounded text-xs font-semibold">POST</span>
                                            <code class="text-sm font-mono text-gray-800 dark:text-gray-200">/prebook_hotel</code>
                                        </div>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Lock a hotel rate (prebook) before confirming with passenger details. Prebooked rates expire after 30 minutes.</p>
                                    </div>

                                    {{-- POST /confirm_booking --}}
                                    <div class="space-y-2">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-block px-2 py-1 bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 rounded text-xs font-semibold">POST</span>
                                            <code class="text-sm font-mono text-gray-800 dark:text-gray-200">/confirm_booking</code>
                                        </div>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Confirm a prebooked rate with passenger names and special requests.</p>
                                    </div>

                                    {{-- POST /payment_link --}}
                                    <div class="space-y-2">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-block px-2 py-1 bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 rounded text-xs font-semibold">POST</span>
                                            <code class="text-sm font-mono text-gray-800 dark:text-gray-200">/payment_link</code>
                                        </div>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Generate a payment link for B2C booking completion (valid for 48 hours).</p>
                                    </div>

                                </div>
                            </div>

                            {{-- Cancellation Endpoints --}}
                            <div x-data="{ open: false }" class="border border-gray-100 dark:border-gray-600 rounded-lg">
                                <button @click="open = !open" class="w-full flex items-center justify-between p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                    <span class="font-medium text-gray-800 dark:text-gray-200">Cancellation & Refunds</span>
                                    <svg x-show="!open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                                    </svg>
                                    <svg x-show="open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                                    </svg>
                                </button>

                                <div x-show="open" class="border-t border-gray-100 dark:border-gray-600 p-4 space-y-4 bg-gray-50 dark:bg-gray-900/30">
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Cancellations are a 2-step process: first request cancellation, then confirm based on penalties.</p>
                                    <div class="space-y-4">
                                        <div class="space-y-2">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-block px-2 py-1 bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 rounded text-xs font-semibold">POST</span>
                                                <code class="text-sm font-mono text-gray-800 dark:text-gray-200">/cancel_booking</code>
                                            </div>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Request cancellation; returns refund amount and penalty.</p>
                                        </div>
                                        <div class="space-y-2">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-block px-2 py-1 bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 rounded text-xs font-semibold">POST</span>
                                                <code class="text-sm font-mono text-gray-800 dark:text-gray-200">/confirm_cancellation</code>
                                            </div>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Confirm the cancellation after showing the penalty to the user.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Account & Status Endpoints --}}
                            <div x-data="{ open: false }" class="border border-gray-100 dark:border-gray-600 rounded-lg">
                                <button @click="open = !open" class="w-full flex items-center justify-between p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                    <span class="font-medium text-gray-800 dark:text-gray-200">Account, Status & History</span>
                                    <svg x-show="!open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                                    </svg>
                                    <svg x-show="open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                                    </svg>
                                </button>

                                <div x-show="open" class="border-t border-gray-100 dark:border-gray-600 p-4 space-y-4 bg-gray-50 dark:bg-gray-900/30">
                                    <div class="space-y-4">
                                        <div class="space-y-2">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-block px-2 py-1 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded text-xs font-semibold">GET</span>
                                                <code class="text-sm font-mono text-gray-800 dark:text-gray-200">/balance</code>
                                            </div>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Check company B2B credit balance.</p>
                                        </div>
                                        <div class="space-y-2">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-block px-2 py-1 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded text-xs font-semibold">GET</span>
                                                <code class="text-sm font-mono text-gray-800 dark:text-gray-200">/statement</code>
                                            </div>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Generate company statement (transactions, payments, balance).</p>
                                        </div>
                                        <div class="space-y-2">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-block px-2 py-1 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded text-xs font-semibold">GET</span>
                                                <code class="text-sm font-mono text-gray-800 dark:text-gray-200">/booking_status</code>
                                            </div>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Check status of a specific booking.</p>
                                        </div>
                                        <div class="space-y-2">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-block px-2 py-1 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded text-xs font-semibold">GET</span>
                                                <code class="text-sm font-mono text-gray-800 dark:text-gray-200">/booking_history</code>
                                            </div>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">List all bookings for the company.</p>
                                        </div>
                                        <div class="space-y-2">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-block px-2 py-1 bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 rounded text-xs font-semibold">POST</span>
                                                <code class="text-sm font-mono text-gray-800 dark:text-gray-200">/resend_voucher</code>
                                            </div>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Resend booking confirmation voucher.</p>
                                        </div>
                                        <div class="space-y-2">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-block px-2 py-1 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded text-xs font-semibold">GET</span>
                                                <code class="text-sm font-mono text-gray-800 dark:text-gray-200">/download_voucher</code>
                                            </div>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Download booking confirmation as PDF.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- System Endpoints --}}
                            <div x-data="{ open: false }" class="border border-gray-100 dark:border-gray-600 rounded-lg">
                                <button @click="open = !open" class="w-full flex items-center justify-between p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                    <span class="font-medium text-gray-800 dark:text-gray-200">System & Utility</span>
                                    <svg x-show="!open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                                    </svg>
                                    <svg x-show="open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                                    </svg>
                                </button>

                                <div x-show="open" class="border-t border-gray-100 dark:border-gray-600 p-4 space-y-4 bg-gray-50 dark:bg-gray-900/30">
                                    <div class="space-y-4">
                                        <div class="space-y-2">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-block px-2 py-1 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded text-xs font-semibold">GET</span>
                                                <code class="text-sm font-mono text-gray-800 dark:text-gray-200">/health</code>
                                            </div>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Health check endpoint (no authentication required). Use to verify API is online.</p>
                                        </div>
                                        <div class="space-y-2">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-block px-2 py-1 bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200 rounded text-xs font-semibold">ANY</span>
                                                <code class="text-sm font-mono text-gray-800 dark:text-gray-200">/payment_callback</code>
                                            </div>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Payment gateway callback URL (no authentication required). Use this URL in payment gateway configuration.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    {{-- Section 3: AI System Message (Super Admin Only) --}}
                    @if($isSuperAdmin)
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                        <h3 class="text-md font-semibold text-gray-800 dark:text-gray-100 mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5h.01"/>
                            </svg>
                            AI Agent System Message
                        </h3>

                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            Use this system message to configure your automation tool's AI agent. This message defines the assistant's role, available tools, and conversation style for hotel booking assistance.
                        </p>

                        <div class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto mb-3">
                            <pre class="text-xs font-mono whitespace-pre-wrap break-words">{{ file_get_contents(config('dotwai.system_message_path')) }}</pre>
                        </div>

                        <button @click="navigator.clipboard.writeText(document.querySelector('.dotw-system-message')?.textContent || '{{ addslashes(file_get_contents(config('dotwai.system_message_path'))) }}')" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm font-medium transition">
                            Copy System Message
                        </button>
                    </div>
                    @endif

                    {{-- Section 4: Webhook Events --}}
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                        <h3 class="text-md font-semibold text-gray-800 dark:text-gray-100 mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                            </svg>
                            Webhook Events
                        </h3>

                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            The system fires webhook events at key moments in the booking lifecycle. Configure your webhook endpoint in the admin panel to receive these events.
                        </p>

                        <div class="space-y-4">
                            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4">
                                <p class="text-sm font-medium text-gray-800 dark:text-gray-200 mb-2">Webhook Configuration:</p>
                                <code class="block text-xs bg-white dark:bg-gray-800 p-2 rounded text-gray-800 dark:text-gray-200">DOTWAI_WEBHOOK_URL=https://your-automation-tool.com/webhook</code>
                            </div>

                            <div class="space-y-3">
                                <p class="font-medium text-gray-800 dark:text-gray-200">Supported Events:</p>
                                @foreach(config('dotwai.webhook_events', []) as $event)
                                    <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-900/30 rounded border border-gray-200 dark:border-gray-700">
                                        <div class="w-2 h-2 bg-green-500 rounded-full flex-shrink-0"></div>
                                        <div>
                                            <p class="text-sm font-mono text-gray-800 dark:text-gray-200">{{ $event }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="bg-gray-100 dark:bg-gray-900 p-4 rounded-lg mt-4">
                                <p class="font-medium text-gray-800 dark:text-gray-200 mb-2 text-sm">Example Webhook Payload:</p>
                                <pre class="text-xs overflow-x-auto"><code>{
  "event": "payment_completed",
  "timestamp": "2026-03-25T15:30:00Z",
  "booking_id": "BK123456",
  "company_id": 42,
  "data": {
    "amount": 1000.00,
    "currency": "KWD",
    "hotel_name": "Burj Al Arab",
    "check_in": "2026-04-01",
    "check_out": "2026-04-05",
    "guest_name": "John Doe"
  }
}</code></pre>
                            </div>
                        </div>
                    </div>

                    {{-- Section 5: Configuration Reference (Super Admin Only) --}}
                    @if($isSuperAdmin)
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                        <h3 class="text-md font-semibold text-gray-800 dark:text-gray-100 mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                            </svg>
                            Configuration Reference
                        </h3>

                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            Key configuration values from the system config. You can override these per-company in the Credentials tab.
                        </p>

                        <div class="space-y-3">
                            <div class="bg-gray-50 dark:bg-gray-900/30 p-3 rounded border border-gray-200 dark:border-gray-700">
                                <p class="text-sm font-mono text-gray-800 dark:text-gray-200"><span class="font-semibold">search_results_limit:</span> {{ config('dotwai.search_results_limit') }} hotels per search</p>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-900/30 p-3 rounded border border-gray-200 dark:border-gray-700">
                                <p class="text-sm font-mono text-gray-800 dark:text-gray-200"><span class="font-semibold">search_cache_ttl:</span> {{ config('dotwai.search_cache_ttl') }} seconds</p>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-900/30 p-3 rounded border border-gray-200 dark:border-gray-700">
                                <p class="text-sm font-mono text-gray-800 dark:text-gray-200"><span class="font-semibold">prebook_expiry_minutes:</span> {{ config('dotwai.prebook_expiry_minutes') }} minutes</p>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-900/30 p-3 rounded border border-gray-200 dark:border-gray-700">
                                <p class="text-sm font-mono text-gray-800 dark:text-gray-200"><span class="font-semibold">payment_link_expiry_hours:</span> {{ config('dotwai.payment_link_expiry_hours') }} hours</p>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-900/30 p-3 rounded border border-gray-200 dark:border-gray-700">
                                <p class="text-sm font-mono text-gray-800 dark:text-gray-200"><span class="font-semibold">default_payment_gateway:</span> {{ config('dotwai.default_payment_gateway') }}</p>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-900/30 p-3 rounded border border-gray-200 dark:border-gray-700">
                                <p class="text-sm font-mono text-gray-800 dark:text-gray-200"><span class="font-semibold">default_currency:</span> {{ config('dotwai.display_currency') }}</p>
                            </div>
                            <div class="bg-blue-50 dark:bg-blue-900/20 p-3 rounded border border-blue-200 dark:border-blue-700">
                                <p class="text-sm text-gray-800 dark:text-gray-200"><span class="font-semibold">B2B/B2C Tracks:</span> Can be enabled/disabled per company in the Credentials tab</p>
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- Section 6: Integration Templates (Super Admin Only) --}}
                    @if($isSuperAdmin)
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                        <h3 class="text-md font-semibold text-gray-800 dark:text-gray-100 mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Integration Templates
                        </h3>

                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                            Pre-configured HTTP Request templates. Download and import these JSON files into your automation tool for quick API integration.
                        </p>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {{-- Search & Discovery Category --}}
                            <div class="col-span-1 md:col-span-2">
                                <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-3 text-blue-700 dark:text-blue-400">Search & Discovery</h4>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                    <a href="{{ asset('downloads/api-templates/search_hotels.json') }}" download class="flex flex-col items-center justify-center p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                                        <svg class="w-6 h-6 text-blue-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                        </svg>
                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200">Search Hotels</p>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">POST</span>
                                    </a>
                                    <a href="{{ asset('downloads/api-templates/get_hotel_details.json') }}" download class="flex flex-col items-center justify-center p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                                        <svg class="w-6 h-6 text-blue-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                        </svg>
                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200">Hotel Details</p>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">POST</span>
                                    </a>
                                    <a href="{{ asset('downloads/api-templates/get_cities.json') }}" download class="flex flex-col items-center justify-center p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                                        <svg class="w-6 h-6 text-green-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                        </svg>
                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200">Get Cities</p>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">GET</span>
                                    </a>
                                </div>
                            </div>

                            {{-- Booking & Confirmation Category --}}
                            <div class="col-span-1 md:col-span-2">
                                <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-3 text-green-700 dark:text-green-400">Booking & Confirmation</h4>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                    <a href="{{ asset('downloads/api-templates/prebook_hotel.json') }}" download class="flex flex-col items-center justify-center p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                                        <svg class="w-6 h-6 text-yellow-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                        </svg>
                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200">Prebook Hotel</p>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">POST</span>
                                    </a>
                                    <a href="{{ asset('downloads/api-templates/confirm_booking.json') }}" download class="flex flex-col items-center justify-center p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                                        <svg class="w-6 h-6 text-yellow-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                        </svg>
                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200">Confirm Booking</p>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">POST</span>
                                    </a>
                                    <a href="{{ asset('downloads/api-templates/payment_link.json') }}" download class="flex flex-col items-center justify-center p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                                        <svg class="w-6 h-6 text-yellow-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                        </svg>
                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200">Payment Link</p>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">POST</span>
                                    </a>
                                </div>
                            </div>

                            {{-- Booking Management Category --}}
                            <div class="col-span-1 md:col-span-2">
                                <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-3 text-purple-700 dark:text-purple-400">Booking Management</h4>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                    <a href="{{ asset('downloads/api-templates/cancel_booking.json') }}" download class="flex flex-col items-center justify-center p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                                        <svg class="w-6 h-6 text-red-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                        </svg>
                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200">Cancel Booking</p>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">POST</span>
                                    </a>
                                    <a href="{{ asset('downloads/api-templates/booking_status.json') }}" download class="flex flex-col items-center justify-center p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                                        <svg class="w-6 h-6 text-green-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                        </svg>
                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200">Booking Status</p>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">GET</span>
                                    </a>
                                    <a href="{{ asset('downloads/api-templates/booking_history.json') }}" download class="flex flex-col items-center justify-center p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                                        <svg class="w-6 h-6 text-green-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                        </svg>
                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200">Booking History</p>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">GET</span>
                                    </a>
                                </div>
                            </div>

                            {{-- Vouchers & Statements Category --}}
                            <div class="col-span-1 md:col-span-2">
                                <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-3 text-orange-700 dark:text-orange-400">Vouchers & Statements</h4>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                    <a href="{{ asset('downloads/api-templates/resend_voucher.json') }}" download class="flex flex-col items-center justify-center p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                                        <svg class="w-6 h-6 text-yellow-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                        </svg>
                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200">Resend Voucher</p>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">POST</span>
                                    </a>
                                    <a href="{{ asset('downloads/api-templates/download_voucher.json') }}" download class="flex flex-col items-center justify-center p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                                        <svg class="w-6 h-6 text-green-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                        </svg>
                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200">Download Voucher</p>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">GET</span>
                                    </a>
                                    <a href="{{ asset('downloads/api-templates/statement.json') }}" download class="flex flex-col items-center justify-center p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                                        <svg class="w-6 h-6 text-green-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                        </svg>
                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200">Statement</p>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">GET</span>
                                    </a>
                                </div>
                            </div>

                            {{-- Account Category --}}
                            <div class="col-span-1 md:col-span-2">
                                <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-3 text-indigo-700 dark:text-indigo-400">Account</h4>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                    <a href="{{ asset('downloads/api-templates/balance.json') }}" download class="flex flex-col items-center justify-center p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                                        <svg class="w-6 h-6 text-green-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                        </svg>
                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200">Account Balance</p>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">GET</span>
                                    </a>
                                    <a href="{{ asset('downloads/api-templates/health.json') }}" download class="flex flex-col items-center justify-center p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                                        <svg class="w-6 h-6 text-green-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                        </svg>
                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200">Health Check</p>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">GET</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- Help Section --}}
                    <div class="border border-amber-200 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 rounded-lg p-6">
                        <h3 class="text-md font-semibold text-amber-900 dark:text-amber-100 mb-3 flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Need Help?
                        </h3>
                        <div class="text-sm text-amber-900 dark:text-amber-100 space-y-2">
                            <p>This documentation covers the REST API endpoints. To get started:</p>
                            <ol class="list-decimal list-inside space-y-1 ml-2">
                                <li>Visit the <strong>Credentials</strong> tab to configure your DOTW account</li>
                                <li>Test the <code class="bg-white dark:bg-gray-800 px-1 rounded text-xs">/health</code> endpoint to verify the API is accessible</li>
                                <li>Use <code class="bg-white dark:bg-gray-800 px-1 rounded text-xs">/get_cities</code> to list available destinations</li>
                                <li>Try <code class="bg-white dark:bg-gray-800 px-1 rounded text-xs">/search_hotels</code> with a phone number to search for hotels</li>
                                <li>Integrate the endpoints into your automation tool or custom application</li>
                            </ol>
                            <p class="mt-4 text-xs">For questions about the system or configuration, contact the DOTW module administrator.</p>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>
</div>
