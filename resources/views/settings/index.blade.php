<x-app-layout>
    <ul class="flex space-x-2 rtl:space-x-reverse pb-5 text-base md:text-lg sm:text-sm">
        <li>
            <a href="{{ route('dashboard') }}" class="customBlueColor hover:underline">Dashboard</a>
        </li>
        <li class="before:content-['/'] before:mr-1">
            <span>Settings</span>
        </li>
    </ul>

    <div class="grid bg-white dark:bg-gray-800 rounded-xl shadow-sm gap-2">

        <x-admin-card title="setting" :companyId="$companyId" />

        <div id="setting-index" x-data="settingsPage()" x-init="init()" class="flex min-h-[500px] overflow-x-auto">
            <!-- Internal Sidebar -->
            <div class="w-56 border-r border-gray-200 dark:border-gray-700 p-4 flex-shrink-0">
                <nav class="space-y-1">
                    <!-- Invoice -->
                    <button
                        @click="saveTab('invoice')"
                        :class="activeTab === 'invoice' 
                        ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600' 
                        : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'"
                        class="w-full flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Invoice
                    </button>

                    <!-- Payment -->
                    <button
                        @click="saveTab('payment')"
                        :class="activeTab === 'payment' 
                        ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600' 
                        : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'"
                        class="w-full flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-all">
                        <svg class="stroke-black" width="24" height="24" stroke="currentColor" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M2 11C2 8.17157 2 6.75736 2.87868 5.87868C3.75736 5 5.17157 5 8 5H13C15.8284 5 17.2426 5 18.1213 5.87868C19 6.75736 19 8.17157 19 11C19 13.8284 19 15.2426 18.1213 16.1213C17.2426 17 15.8284 17 13 17H8C5.17157 17 3.75736 17 2.87868 16.1213C2 15.2426 2 13.8284 2 11Z"  stroke-width="1.5" />
                            <path d="M19.0001 8.07617C19.9751 8.17208 20.6315 8.38885 21.1214 8.87873C22.0001 9.75741 22.0001 11.1716 22.0001 14.0001C22.0001 16.8285 22.0001 18.2427 21.1214 19.1214C20.2427 20.0001 18.8285 20.0001 16.0001 20.0001H11.0001C8.17163 20.0001 6.75742 20.0001 5.87874 19.1214C5.38884 18.6315 5.17208 17.9751 5.07617 17"  stroke-width="1.5" />
                            <path d="M13 11C13 12.3807 11.8807 13.5 10.5 13.5C9.11929 13.5 8 12.3807 8 11C8 9.61929 9.11929 8.5 10.5 8.5C11.8807 8.5 13 9.61929 13 11Z"  stroke-width="1.5" />
                            <path d="M16 13L16 9"  stroke-width="1.5" stroke-linecap="round" />
                            <path d="M5 13L5 9"  stroke-width="1.5" stroke-linecap="round" />
                        </svg>
                        Payment
                    </button>

                    <!-- Terms & Regulation -->
                    <button
                        @click="saveTab('terms')"
                        :class="activeTab === 'terms' 
                        ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600' 
                        : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'"
                        class="w-full flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                        </svg>
                        Terms & Regulation
                    </button>

                    <!-- Charges / Payment Gateways -->
                    <button
                        @click="saveTab('charges')"
                        :class="activeTab === 'charges' 
                        ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600' 
                        : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'"
                        class="w-full flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                        </svg>
                        Payment Gateways
                    </button>

                    <!-- Payment Methods Selection -->
                    <button
                        @click="saveTab('payment-methods')"
                        :class="activeTab === 'payment-methods' 
                        ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600' 
                        : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700'"
                        class="w-full flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                        </svg>
                        Payment Methods
                    </button>
                </nav>
            </div>

            <!-- Content Area -->
            <div class="flex-1 p-6">

                <div x-show="activeTab === 'invoice'" x-cloak>
                    @include('settings.partial.invoice')
                </div>
                <div x-show="activeTab === 'payment'" x-cloak>
                    @include('settings.partial.payment')
                </div>
                <div x-show="activeTab === 'terms'" x-cloak>
                    @include('settings.partial.terms_condition')
                </div>

                <div x-show="activeTab === 'charges'" x-cloak>
                    @include('settings.partial.charges')
                </div>

                <div x-show="activeTab === 'payment-methods'" x-cloak>
                    @include('settings.partial.payment_methods')
                </div>

            </div>
        </div>
    </div>

    <script>
        function settingsPage() {
            return {
                activeTab: "{{ $activeTab }}",
                companyId: "{{ $companyId }}",

                init() {
                    // Load data for the active tab on page load
                    if (this.activeTab === 'terms') {
                        this.loadTemplates();
                    }
                },

                saveTab(tab) {
                    this.activeTab = tab;
                    fetch('{{ route("settings.save-tab") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            tab: tab,
                            company_id: this.companyId
                        })
                    });
                },

                // Terms & Conditions
                templates: [],
                loadingTemplates: false,
                languageFilter: 'all',

                // Modals
                showCreateModal: false,
                showEditModal: false,
                showDeleteModal: false,
                editingTemplate: {
                    title: '',
                    language: 'en',
                    content: '',
                    is_default: false
                },
                deletingTemplate: {
                    title: '',
                    id: null
                },

                // Filter tabs for terms
                get filteredTemplates() {
                    if (this.languageFilter === 'all') {
                        return this.templates;
                    }
                    return this.templates.filter(t => t.language === this.languageFilter);
                },

                getDefaultForLanguage(language) {
                    return this.templates.find(t => t.language === language && t.is_default);
                },

                async loadTemplates() {
                    if (this.templates.length > 0) return;

                    const params = new URLSearchParams();
                    @if(request('company_id'))
                    params.append('company_id', '{{ request("company_id") }}');
                    @endif

                    this.loadingTemplates = true;

                    const url = '{{ route("terms.templates.index") }}' + '?' + params.toString();
                    try {
                        const response = await fetch(url, {
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            }
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.templates = data.templates;
                        }
                    } catch (error) {
                        console.error('Error loading templates:', error);
                    } finally {
                        this.loadingTemplates = false;
                    }
                },

                openEditModal(template) {
                    this.editingTemplate = {
                        ...template
                    };
                    this.showEditModal = true;
                },

                confirmDelete(template) {
                    this.deletingTemplate = template;
                    this.showDeleteModal = true;
                },

                formatDate(dateString) {
                    return new Date(dateString).toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric'
                    });
                },

                formatTime(dateString) {
                    return new Date(dateString).toLocaleTimeString('en-US', {
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                }
            }
        }
    </script>
</x-app-layout>