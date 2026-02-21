<div x-data="{ activeTab: '{{ $activeTab }}' }" class="flex min-h-[500px] bg-white dark:bg-gray-800 rounded-xl shadow-sm">

    {{-- Left sidebar nav --}}
    <div class="w-56 border-r border-gray-200 dark:border-gray-700 p-4 flex-shrink-0">
        <nav class="space-y-1">

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

        </nav>
    </div>

    {{-- Content area --}}
    <div class="flex-1 p-6">

        {{-- Tab 1: Credentials --}}
        <div x-show="activeTab === 'credentials'" x-cloak>
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">DOTW Credentials</h2>

            @if($isSuperAdmin)
                {{-- Super Admin sees info message only --}}
                <div class="rounded-lg border border-blue-200 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-700 p-4 max-w-xl">
                    <div class="flex gap-3">
                        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-blue-800 dark:text-blue-200">Super Admin</p>
                            <p class="text-sm text-blue-700 dark:text-blue-300 mt-1">
                                To configure DOTW credentials for a company, use the API endpoint:
                            </p>
                            <code class="block mt-2 text-xs bg-blue-100 dark:bg-blue-900 text-blue-900 dark:text-blue-100 rounded px-3 py-2 font-mono">
                                POST /api/admin/companies/{id}/dotw-credentials
                            </code>
                        </div>
                    </div>
                </div>
            @else
                {{-- Company Admin sees credentials form --}}
                @if(session('credentials_saved'))
                    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-700 p-3 text-sm text-green-800 dark:text-green-200">
                        {{ session('credentials_saved') }}
                    </div>
                @endif

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
            @endif
        </div>

        {{-- Tab 2: Audit Logs --}}
        <div x-show="activeTab === 'audit-logs'" x-cloak>
            @livewire(\App\Http\Livewire\Admin\DotwAuditLogIndex::class)
        </div>

        {{-- Tab 3: API Tokens (Super Admin only) --}}
        <div x-show="activeTab === 'api-tokens'" x-cloak>
            @if($isSuperAdmin)
                @livewire(\App\Http\Livewire\Admin\DotwApiTokenIndex::class)
            @endif
        </div>

    </div>
</div>
