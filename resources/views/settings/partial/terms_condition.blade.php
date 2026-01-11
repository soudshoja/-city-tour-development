<div class="flex items-center justify-between mb-6">
    
    <div>
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Terms & Regulation</h2>
        <p class="text-sm text-gray-500 mt-1">Manage terms and conditions templates for clients before proceeding to payment gateway</p>
    </div>
    <button @click="showCreateModal = true"
        class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        Add Template
    </button>
</div>

<!-- Loading State -->
<div x-show="loadingTemplates" class="flex items-center justify-center py-12">
    <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>
</div>

<!-- Empty State -->
<div x-show="!loadingTemplates && templates.length === 0" class="text-center py-12 bg-gray-50 dark:bg-gray-700 rounded-lg">
    <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
    </svg>
    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-200 mb-1">No templates yet</h3>
    <p class="text-sm text-gray-500 mb-4">Create your first terms and conditions template</p>
    <button @click="showCreateModal = true"
        class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        Add Template
    </button>
</div>

<!-- Language Filter Tabs -->
<div x-show="!loadingTemplates && templates.length > 0" class="mb-4">
    <div class="flex items-center gap-2">
        <span class="text-sm text-gray-500 mr-2">Filter by language:</span>
        <button @click="languageFilter = 'all'"
            :class="languageFilter === 'all' ? 'bg-gray-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
            class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors">
            All
            <span class="ml-1 px-1.5 py-0.5 rounded-full text-xs"
                :class="languageFilter === 'all' ? 'bg-gray-700' : 'bg-gray-200'"
                x-text="templates.length"></span>
        </button>
        <!-- Language Filter Tabs -->
        <button @click="languageFilter = 'EN'"
            :class="languageFilter === 'EN' ? 'bg-indigo-600 text-white' : 'bg-indigo-50 text-indigo-700 hover:bg-indigo-100'"
            class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors inline-flex items-center gap-1">
            <span>🇬🇧</span> English
            <span class="ml-1 px-1.5 py-0.5 rounded-full text-xs"
                :class="languageFilter === 'EN' ? 'bg-indigo-500' : 'bg-indigo-100'"
                x-text="templates.filter(t => t.language === 'EN').length"></span>
        </button>
        <button @click="languageFilter = 'ARB'"
            :class="languageFilter === 'ARB' ? 'bg-emerald-600 text-white' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'"
            class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors inline-flex items-center gap-1">
            <span>🇸🇦</span> Arabic
            <span class="ml-1 px-1.5 py-0.5 rounded-full text-xs"
                :class="languageFilter === 'ARB' ? 'bg-emerald-500' : 'bg-emerald-100'"
                x-text="templates.filter(t => t.language === 'ARB').length"></span>
        </button>
    </div>
</div>

<!-- Default Status Summary -->
<div x-show="!loadingTemplates && templates.length > 0" class="mt-4 grid grid-cols-2 gap-4 mb-8">
    <!-- English Default -->
    <div class="p-3 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg border border-indigo-100 dark:border-indigo-800">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-lg">🇬🇧</span>
                <span class="text-sm font-medium text-indigo-800 dark:text-indigo-200">English Default</span>
            </div>
            <template x-if="getDefaultForLanguage('EN')">
                <span class="text-xs font-medium text-indigo-600 bg-indigo-100 px-2 py-1 rounded-full" x-text="getDefaultForLanguage('EN').title"></span>
            </template>
            <template x-if="!getDefaultForLanguage('EN')">
                <span class="text-xs text-gray-500 italic">Not set</span>
            </template>
        </div>
    </div>

    <!-- Arabic Default -->
    <div class="p-3 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg border border-emerald-100 dark:border-emerald-800">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-lg">🇸🇦</span>
                <span class="text-sm font-medium text-emerald-800 dark:text-emerald-200">Arabic Default</span>
            </div>
            <template x-if="getDefaultForLanguage('ARB')">
                <span class="text-xs font-medium text-emerald-600 bg-emerald-100 px-2 py-1 rounded-full" x-text="getDefaultForLanguage('ARB').title"></span>
            </template>
            <template x-if="!getDefaultForLanguage('ARB')">
                <span class="text-xs text-gray-500 italic">Not set</span>
            </template>
        </div>
    </div>
</div>

<!-- Templates Table -->
<div x-show="!loadingTemplates && filteredTemplates.length > 0" class="bg-gray-50 dark:bg-gray-700 rounded-lg overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-100 dark:bg-gray-600">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Template Name</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Language</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Created By</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Created At</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
            <template x-for="template in filteredTemplates" :key="template.id">
                <tr class="hover:bg-gray-100 dark:hover:bg-gray-650 transition-colors">
                    <!-- Template Name -->
                    <td class="px-4 py-4">
                        <div>
                            <p class="text-sm font-medium text-gray-800 dark:text-gray-200" x-text="template.title"></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-xs" x-text="template.content.substring(0, 60) + (template.content.length > 60 ? '...' : '')"></p>
                        </div>
                    </td>

                    <!-- Language -->
                    <td class="px-4 py-4">
                        <span x-show="template.language === 'EN'"
                            class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium bg-indigo-100 text-indigo-800 rounded-full">
                            <span>🇬🇧</span> English
                        </span>
                        <span x-show="template.language === 'ARB'"
                            class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium bg-emerald-100 text-emerald-800 rounded-full">
                            <span>🇸🇦</span> Arabic
                        </span>
                    </td>

                    <!-- Status -->
                    <td class="px-4 py-4">
                        <div class="flex items-center gap-2">
                            <span x-show="template.is_default"
                                class="inline-flex items-center px-2 py-0.5 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">
                                Default
                            </span>
                            <span x-show="template.is_active && !template.is_default"
                                class="inline-flex items-center px-2 py-0.5 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                                Active
                            </span>
                            <span x-show="!template.is_active"
                                class="inline-flex items-center px-2 py-0.5 text-xs font-medium bg-gray-200 text-gray-600 rounded-full">
                                Inactive
                            </span>
                        </div>
                    </td>

                    <!-- Created By -->
                    <td class="px-4 py-4">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 bg-blue-100 rounded-full flex items-center justify-center">
                                <span class="text-xs font-medium text-blue-600" x-text="template.created_by_name ? template.created_by_name.charAt(0).toUpperCase() : 'S'"></span>
                            </div>
                            <span class="text-sm text-gray-600 dark:text-gray-300" x-text="template.created_by_name || 'System'"></span>
                        </div>
                    </td>

                    <!-- Created At -->
                    <td class="px-4 py-4">
                        <p class="text-sm text-gray-600 dark:text-gray-300" x-text="formatDate(template.created_at)"></p>
                        <p class="text-xs text-gray-400" x-text="formatTime(template.created_at)"></p>
                    </td>

                    <!-- Actions -->
                    <td class="px-4 py-4">
                        <div class="flex items-center justify-end gap-1">
                            <!-- Set Default -->
                            <form x-show="!template.is_default && template.is_active"
                                :action="'{{ route('terms.templates.set-default', ['id' => 'TEMPLATE_ID']) }}'.replace('TEMPLATE_ID', template.id)"
                                method="POST" class="inline">
                                @csrf
                                <button type="submit"
                                    class="p-2 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors"
                                    :title="`Set as Default for ${template.language === 'en' ? 'English' : 'Arabic'}`">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                                    </svg>
                                </button>
                            </form>

                            <!-- View/Edit -->
                            <button @click="openEditModal(template)"
                                class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                title="Edit">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                </svg>
                            </button>

                            <!-- Toggle Active -->
                            <form :action="'{{ route('terms.templates.toggle-active', ['id' => 'TEMPLATE_ID']) }}'.replace('TEMPLATE_ID', template.id)"
                                method="POST" class="inline">
                                @csrf
                                <button type="submit"
                                    class="p-2 rounded-lg transition-colors"
                                    :class="template.is_active ? 'text-gray-400 hover:text-orange-600 hover:bg-orange-50' : 'text-orange-600 hover:bg-orange-50'"
                                    :title="template.is_active ? 'Deactivate' : 'Activate'">
                                    <svg x-show="template.is_active" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                                    </svg>
                                    <svg x-show="!template.is_active" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                </button>
                            </form>

                            <!-- Delete -->
                            <button x-show="!template.is_default"
                                @click="confirmDelete(template)"
                                class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                title="Delete">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </div>
                    </td>
                </tr>
            </template>
        </tbody>
    </table>
</div>

<!-- No results for filter -->
<div x-show="!loadingTemplates && templates.length > 0 && filteredTemplates.length === 0"
    class="text-center py-12 bg-gray-50 dark:bg-gray-700 rounded-lg">
    <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
    </svg>
    <p class="text-sm text-gray-500 mb-3">No templates found for <span class="font-medium" x-text="languageFilter === 'EN' ? 'English' : 'Arabic'"></span></p>
    <button @click="showCreateModal = true"
        class="inline-flex items-center gap-2 px-3 py-1.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        Add Template
    </button>
</div>

<!-- Require T&C Setting -->
<div x-show="!loadingTemplates" class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-600">
    <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
        <div>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Require T&C Acceptance</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Require clients to accept terms before payment</p>
        </div>
        <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" class="sr-only peer" id="require-tc" checked>
            <div class="w-10 h-5 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
        </label>
    </div>
</div>


<!-- Create Template Modal -->
<div x-show="showCreateModal" x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col mx-4"
        @click.away="showCreateModal = false">

        <form action="{{ route('terms.templates.store') }}" method="POST">
            @csrf

            @if($companyId)
            <input type="hidden" name="company_id" value="{{ $companyId }}">
            @endif

            <!-- Modal Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Create New Template</h3>
                    <p class="text-sm text-gray-500 mt-0.5">Add a new terms and conditions template</p>
                </div>
                <button type="button" @click="showCreateModal = false"
                    class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="flex-1 overflow-y-auto px-6 py-4">
                <div class="space-y-4">
                    <!-- Template Name & Language Row -->
                    <div class="grid grid-cols-3 gap-4">
                        <!-- Template Name -->
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
                                Template Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="title" required
                                class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('title') border-red-500 @enderror"
                                placeholder="e.g., Standard Terms, Tour Package Terms"
                                value="{{ old('title') }}">
                            @error('title')
                            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Language -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
                                Language <span class="text-red-500">*</span>
                            </label>
                            <select name="language" required
                                class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('language') border-red-500 @enderror">
                                <option value="EN" {{ old('language', 'EN') === 'EN' ? 'selected' : '' }}>🇬🇧 English</option>
                                <option value="ARB" {{ old('language') === 'ARB' ? 'selected' : '' }}>🇸🇦 Arabic</option>
                            </select>
                            @error('language')
                            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Template Content -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
                            Terms & Conditions Content <span class="text-red-500">*</span>
                        </label>
                        <textarea name="content" rows="12" required
                            class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('content') border-red-500 @enderror"
                            placeholder="Enter your terms and conditions here...">{{ old('content') }}</textarea>
                        @error('content')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                        <p class="text-xs text-gray-400 mt-1">
                            <span class="font-medium">Tip:</span> Use numbered lists and clear headings for better readability
                        </p>
                    </div>

                    <!-- Set as Default -->
                    <div class="flex items-center gap-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                        <input type="checkbox" name="is_default" value="1" id="create-is-default"
                            class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500"
                            {{ old('is_default') ? 'checked' : '' }}>
                        <div>
                            <label for="create-is-default" class="text-sm font-medium text-gray-700 dark:text-gray-200 cursor-pointer">Set as default template</label>
                            <p class="text-xs text-gray-500">This will be the default template for the selected language</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 rounded-b-xl">
                <button type="button" @click="showCreateModal = false"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button type="submit"
                    class="px-5 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                    Create Template
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Template Modal -->
<div x-show="showEditModal" x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col mx-4"
        @click.away="showEditModal = false">

        <form :action="'{{ route('terms.templates.update', ['id' => 'TEMPLATE_ID']) }}'.replace('TEMPLATE_ID', editingTemplate?.id)" method="POST">
            @csrf
            @method('PUT')
            
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Edit Template</h3>
                    <p class="text-sm text-gray-500 mt-0.5">Update your terms and conditions template</p>
                </div>
                <button type="button" @click="showEditModal = false"
                    class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="flex-1 overflow-y-auto px-6 py-4">
                <div class="space-y-4">
                    <!-- Template Name & Language Row -->
                    <div class="grid grid-cols-3 gap-4">
                        <!-- Template Name -->
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
                                Template Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="title" required x-model="editingTemplate.title"
                                class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="e.g., Standard Terms, Tour Package Terms">
                        </div>

                        <!-- Language -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
                                Language <span class="text-red-500">*</span>
                            </label>
                            <select name="language" required x-model="editingTemplate.language"
                                class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="EN">🇬🇧 English</option>
                                <option value="ARB">🇸🇦 Arabic</option>
                            </select>
                        </div>
                    </div>

                    <!-- Template Content -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
                            Terms & Conditions Content <span class="text-red-500">*</span>
                        </label>
                        <textarea name="content" rows="12" required x-model="editingTemplate.content"
                            class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Enter your terms and conditions here..."></textarea>
                        <p class="text-xs text-gray-400 mt-1">
                            <span class="font-medium">Tip:</span> Use numbered lists and clear headings for better readability
                        </p>
                    </div>

                    <!-- Current Default Status Info -->
                    <div x-show="editingTemplate?.is_default" class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-100">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                            </svg>
                            <span class="text-sm font-medium text-blue-700">This is the default template for <span x-text="editingTemplate?.language === 'EN' ? 'English' : 'Arabic'"></span></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 rounded-b-xl">
                <button type="button" @click="showEditModal = false"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button type="submit"
                    class="px-5 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                    Update Template
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div x-show="showDeleteModal" x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-md mx-4 p-6"
        @click.away="showDeleteModal = false">

        <form :action="'{{ route('terms.templates.destroy', ['id' => 'TEMPLATE_ID']) }}'.replace('TEMPLATE_ID', deletingTemplate?.id)" method="POST">
            @csrf
            @method('DELETE')

            <div class="flex items-center justify-center w-14 h-14 bg-red-100 rounded-full mx-auto mb-4">
                <svg class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 text-center mb-2">Delete Template</h3>
            <p class="text-sm text-gray-500 text-center mb-6">
                Are you sure you want to delete "<span class="font-semibold text-gray-700" x-text="deletingTemplate?.title"></span>"?
                <br><span class="text-red-500">This action cannot be undone.</span>
            </p>
            <div class="flex items-center justify-center gap-3">
                <button type="button" @click="showDeleteModal = false"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button type="submit"
                    class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors">
                    Delete
                </button>
            </div>
        </form>
    </div>
</div>