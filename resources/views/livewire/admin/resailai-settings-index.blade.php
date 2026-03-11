<div class="flex min-h-[500px] bg-white dark:bg-gray-800 rounded-xl shadow-sm">

    {{-- Left sidebar nav --}}
    <div class="w-56 border-r border-gray-200 dark:border-gray-700 p-4 flex-shrink-0">
        <nav class="space-y-1">
            {{-- Settings Tab --}}
            <button
                @click="activeTab = 'settings'"
                :class="activeTab === 'settings'
                    ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600'
                    : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'"
                class="w-full flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-all">
                {{-- Heroicons outline: gears --}}
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Settings
            </button>
        </nav>
    </div>

    {{-- Content area --}}
    <div class="flex-1 p-6">

        {{-- Settings Tab Content --}}
        <div x-show="activeTab === 'settings'" x-cloak>
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">ResailAI Settings</h2>

            @if($isSuperAdmin)
                {{-- Super Admin: company selector --}}
                <div class="mb-6">
                    <label for="selected_company_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Select Company
                    </label>
                    <select
                        id="selected_company_id"
                        wire:model.live="selectedCompanyId"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Select a company --</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}">{{ $company->name }}</option>
                        @endforeach
                    </select>
                    @error('selectedCompanyId')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                    @if($selectedCompanyId)
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                            Editing settings for: <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $companies->firstWhere('id', $selectedCompanyId)->name }}</span>
                        </p>
                    @endif
                </div>
            @endif

            <form wire:submit.prevent="saveSettings" class="max-w-xl space-y-4">

                @if(session('resailai_settings_saved'))
                    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-700 p-3 text-sm text-green-800 dark:text-green-200">
                        {{ session('resailai_settings_saved') }}
                    </div>
                @endif

                <div>
                    <label for="webhook_url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Webhook URL
                    </label>
                    <input
                        id="webhook_url"
                        type="text"
                        wire:model="webhook_url"
                        placeholder="https://your-n8n-webhook-url.com/resailai/callback"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                    @error('webhook_url')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        The URL where ResailAI will send PDF documents for processing.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <input
                        id="enabled"
                        type="checkbox"
                        wire:model="enabled"
                        class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    >
                    <label for="enabled" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        Enable ResailAI Processing
                    </label>
                </div>

                @error('enabled')
                    <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror

                <div class="pt-2">
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 text-sm font-medium transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M5 13l4 4L19 7"/>
                        </svg>
                        Save Settings
                    </button>
                </div>

            </form>
        </div>

    </div>
</div>
