<div class="container mx-auto px-4 py-8">

        {{-- Page Header --}}
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">WhatsApp AI &mdash; DOTW API Tokens</h1>
            <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">
                Manage per-company Sanctum tokens for n8n GraphQL integration. Tokens are generated per company's primary user account.
            </p>
        </div>

        {{-- One-time token reveal modal --}}
        @if($newTokenPlaintext)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60"
             x-data="{ copied: false }">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 max-w-lg w-full mx-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                    Token Generated &mdash; Company #{{ $newTokenCompanyId }}
                </h2>
                <p class="text-sm text-amber-600 dark:text-amber-400 mb-4">
                    Copy this token now. It will not be shown again after you close this dialog.
                </p>
                <div class="flex items-center gap-2 mb-6">
                    <input id="token-plaintext"
                           type="text"
                           readonly
                           value="{{ $newTokenPlaintext }}"
                           class="flex-1 border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 text-sm font-mono bg-gray-50 dark:bg-gray-900 dark:text-white focus:outline-none" />
                    <button
                        @click="
                            navigator.clipboard.writeText('{{ $newTokenPlaintext }}');
                            copied = true;
                            setTimeout(() => copied = false, 2500);
                        "
                        class="px-3 py-2 text-sm rounded-md bg-blue-600 hover:bg-blue-700 text-white transition-colors whitespace-nowrap">
                        <span x-show="!copied">Copy</span>
                        <span x-show="copied" x-cloak>Copied!</span>
                    </button>
                </div>
                <div class="flex justify-end">
                    <button wire:click="dismissToken"
                            class="px-4 py-2 text-sm rounded-md bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-500 transition-colors">
                        I've copied it &mdash; Close
                    </button>
                </div>
            </div>
        </div>
        @endif

        {{-- Token Table --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Company</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Primary User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Token (masked)</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($credentials as $cred)
                    @php
                        $user = $cred->company?->user;
                        $existingToken = $user?->tokens->first(); {{-- eager-loaded, filtered to dotw-n8n --}}
                        $hasToken = !is_null($existingToken);
                    @endphp
                    <tr>
                        {{-- Company --}}
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ $cred->company?->name ?? '—' }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">ID: {{ $cred->company_id }}</div>
                        </td>

                        {{-- DOTW Active Status --}}
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($cred->is_active)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                    Active
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                    Inactive
                                </span>
                            @endif
                        </td>

                        {{-- Primary User --}}
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                            {{ $user?->email ?? '—' }}
                            @if(!$user)
                                <span class="text-red-500 text-xs">(No user)</span>
                            @endif
                        </td>

                        {{-- Token (masked) --}}
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-500 dark:text-gray-400">
                            @if($hasToken)
                                {{ Str::mask($existingToken->token, '*', 4) }}
                            @else
                                <span class="text-gray-400 italic">No token</span>
                            @endif
                        </td>

                        {{-- Actions --}}
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <div class="flex items-center gap-2">
                                <button wire:click="generateToken({{ $cred->company_id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="generateToken({{ $cred->company_id }})"
                                        class="px-3 py-1.5 rounded-md text-xs font-medium bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white transition-colors">
                                    <span wire:loading.remove wire:target="generateToken({{ $cred->company_id }})">
                                        {{ $hasToken ? 'Regenerate' : 'Generate' }}
                                    </span>
                                    <span wire:loading wire:target="generateToken({{ $cred->company_id }})">
                                        Generating...
                                    </span>
                                </button>

                                @if($hasToken)
                                <button wire:click="revokeToken({{ $cred->company_id }})"
                                        wire:confirm="Revoke dotw-n8n token for {{ $cred->company?->name }}? n8n workflows using this token will stop working immediately."
                                        wire:loading.attr="disabled"
                                        wire:target="revokeToken({{ $cred->company_id }})"
                                        class="px-3 py-1.5 rounded-md text-xs font-medium bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white transition-colors">
                                    Revoke
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-gray-400 dark:text-gray-500 text-sm italic">
                            No companies have DOTW credentials configured yet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>
</div>
