<div
    x-cloak
    x-show="credentialModal"
    class="fixed inset-0 z-50 flex items-center justify-center"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0">
    
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-gray-900 bg-opacity-50" @click="credentialModal = false"></div>
    
    <!-- Modal Content -->
    <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md mx-4 z-10"
        @click.stop>
        
        <!-- Modal Header -->
        <div class="p-5 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg text-left font-semibold text-gray-900 dark:text-white">
                        Credentials for {{ $supplier->name }}
                    </h2>
                    <p class="italic text-xs text-left text-gray-500 dark:text-gray-400 mt-1">
                        Configure the supplier API credentials
                    </p>
                </div>
                <button @click="credentialModal = false" 
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            
            @if($supplier->credentials->isEmpty())
            <div class="mt-3 flex items-center gap-2 text-amber-600 bg-amber-50 dark:bg-amber-900/20 px-3 py-2 rounded-md text-sm">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span>No credentials configured yet</span>
            </div>
            @endif
        </div>
        
        <form id="store-credential_{{ $supplier->id }}" action="{{ route('credentials.store') }}" method="POST">
            @csrf
            <input type="hidden" name="supplier_id" value="{{ $supplier->id }}">
            <input type="hidden" name="company_id" value="{{ auth()->user()->company->id }}">
            <input type="hidden" name="type" value="{{ $supplier->auth_type }}">
            
            <div class="p-5 space-y-4">
                <!-- Auth Type Badge -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Authentication Type
                    </label>
                    <div class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium
                        {{ $supplier->auth_type == 'oauth' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400' : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' }}">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                        </svg>
                        {{ ucfirst($supplier->auth_type) }}
                    </div>
                </div>
                
                <!-- Basic Auth Fields -->
                <div class="{{ $supplier->auth_type == 'oauth' ? 'hidden' : '' }} space-y-4">
                    <div>
                        <label for="username_{{ $supplier->id }}" class="block text-sm text-left font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Username
                        </label>
                        <input type="text" 
                            name="username" 
                            id="username_{{ $supplier->id }}" 
                            placeholder="Enter username"
                            value="{{ old('username') ?? $supplier->credentials->first()?->username }}"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm
                                focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                                dark:bg-gray-700 dark:text-white
                                placeholder-gray-400 dark:placeholder-gray-500 transition">
                    </div>
                    
                    <div>
                        <label for="password_{{ $supplier->id }}" class="block text-sm text-left font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Password
                        </label>
                        <input type="password" 
                            name="password" 
                            id="password_{{ $supplier->id }}" 
                            placeholder="Enter password"
                            value="{{ old('password') ?? $supplier->credentials->first()?->password }}"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm
                                focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                                dark:bg-gray-700 dark:text-white
                                placeholder-gray-400 dark:placeholder-gray-500 transition">
                    </div>
                </div>
                
                <!-- OAuth Fields -->
                <div class="{{ $supplier->auth_type == 'basic' ? 'hidden' : '' }} space-y-4">
                    <div>
                        <label for="client_id_{{ $supplier->id }}" class="block text-sm text-left font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Client ID
                        </label>
                        <input type="text" 
                            name="client_id" 
                            id="client_id_{{ $supplier->id }}" 
                            value="{{ old('client_id') ?? $supplier->credentials->first()?->client_id }}"
                            placeholder="Enter client ID"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm
                                focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                                dark:bg-gray-700 dark:text-white
                                placeholder-gray-400 dark:placeholder-gray-500 transition">
                    </div>
                    
                    <div>
                        <label for="client_secret_{{ $supplier->id }}" class="block text-sm text-left font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Client Secret
                        </label>
                        <input type="password" 
                            name="client_secret" 
                            id="client_secret_{{ $supplier->id }}" 
                            value="{{ old('client_secret') ?? $supplier->credentials->first()?->client_secret }}"
                            placeholder="Enter client secret"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm
                                focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                                dark:bg-gray-700 dark:text-white
                                placeholder-gray-400 dark:placeholder-gray-500 transition">
                    </div>
                </div>
                
                <!-- Resayil Group ID -->
                <div>
                    <label for="group_id_{{ $supplier->id }}" class="block text-sm text-left font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Resayil Group ID
                    </label>
                    <input type="text" 
                        name="group_id"
                        id="group_id_{{ $supplier->id }}" 
                        placeholder="Enter Resayil Group ID"
                        value="{{ old('group_id') ?? $supplier->supplierCompanies->first()?->group_id }}"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm
                            focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                            dark:bg-gray-700 dark:text-white
                            placeholder-gray-400 dark:placeholder-gray-500 transition">
                </div>
            </div>
            
            <!-- Modal Footer -->
            <div class="px-5 py-4 flex items-center justify-between gap-3">
                <button type="button"
                    @click="credentialModal = false"
                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 
                        border border-gray-300 dark:border-gray-600 rounded-lg
                        hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                    Cancel
                </button>
                <button type="submit"
                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg
                        hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition">
                    Save Credentials
                </button>
            </div>
        </form>
    </div>
</div>