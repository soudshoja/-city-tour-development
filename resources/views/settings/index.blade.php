<x-app-layout>
    @push('styles')
        @vite(['resources/css/settings/main.css', 'resources/css/settings/index.css', 'resources/css/settings/notification.css', 'resources/css/settings/agent-loss.css'])
    @endpush
    <nav class="setting-breadcrumb">
        <a href="{{ route('dashboard') }}" class="setting-breadcrumb-link">{{ __('general.dashboard') }}</a>
        <span class="setting-breadcrumb-sep">&gt;</span>
        <span class="setting-breadcrumb-current">{{ __('general.settings') }}</span>
    </nav>

    <div class="setting-container">

        <div id="setting-index" x-data="settingsPage()" x-init="init()" class="setting-layout">
            <!-- Mobile Sidebar Toggle -->
            <button @click="sidebarOpen = !sidebarOpen" class="setting-sidebar-toggle">
                <span x-text="tabLabels[activeTab]"></span>
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"
                     :style="sidebarOpen && 'transform: rotate(180deg)'">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>

            <!-- Internal Sidebar -->
            <div class="setting-sidebar"
                 :class="{'setting-sidebar-open': sidebarOpen}">
                <nav class="setting-sidebar-nav">
                    <!-- Invoice -->
                    <!-- <button
                        @click="saveTab('invoice')"
                        :class="{'setting-sidebar-btn-active': activeTab === 'invoice'}"
                        class="setting-sidebar-btn">
                        <svg class="setting-sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Invoice
                    </button> -->

                    <!-- Payment -->
                    <button
                        @click="saveTab('payment')"
                        :class="{'setting-sidebar-btn-active': activeTab === 'payment'}"
                        class="setting-sidebar-btn">
                        <svg class="setting-sidebar-icon" stroke="currentColor" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M2 11C2 8.17157 2 6.75736 2.87868 5.87868C3.75736 5 5.17157 5 8 5H13C15.8284 5 17.2426 5 18.1213 5.87868C19 6.75736 19 8.17157 19 11C19 13.8284 19 15.2426 18.1213 16.1213C17.2426 17 15.8284 17 13 17H8C5.17157 17 3.75736 17 2.87868 16.1213C2 15.2426 2 13.8284 2 11Z" stroke-width="1.5" />
                            <path d="M19.0001 8.07617C19.9751 8.17208 20.6315 8.38885 21.1214 8.87873C22.0001 9.75741 22.0001 11.1716 22.0001 14.0001C22.0001 16.8285 22.0001 18.2427 21.1214 19.1214C20.2427 20.0001 18.8285 20.0001 16.0001 20.0001H11.0001C8.17163 20.0001 6.75742 20.0001 5.87874 19.1214C5.38884 18.6315 5.17208 17.9751 5.07617 17" stroke-width="1.5" />
                            <path d="M13 11C13 12.3807 11.8807 13.5 10.5 13.5C9.11929 13.5 8 12.3807 8 11C8 9.61929 9.11929 8.5 10.5 8.5C11.8807 8.5 13 9.61929 13 11Z" stroke-width="1.5" />
                            <path d="M16 13L16 9" stroke-width="1.5" stroke-linecap="round" />
                            <path d="M5 13L5 9" stroke-width="1.5" stroke-linecap="round" />
                        </svg>
                        {{ __('settings.payment') }}
                    </button>

                    <!-- Terms & Regulation -->
                    <button
                        @click="saveTab('terms')"
                        :class="{'setting-sidebar-btn-active': activeTab === 'terms'}"
                        class="setting-sidebar-btn">
                        <svg class="setting-sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                        </svg>
                        {{ __('settings.terms_regulations') }}
                    </button>

                    @can('viewAny', 'App\Models\Charge')
                    <button
                        @click="saveTab('charges')"
                        :class="{'setting-sidebar-btn-active': activeTab === 'charges'}"
                        class="setting-sidebar-btn">
                        <svg class="setting-sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                        </svg>
                        {{ __('settings.payment_gateways') }}
                    </button>
                    @endcan

                    @can('viewPaymentMethodGroup', 'App\Models\PaymentMethod')
                    <button
                        @click="saveTab('payment-methods')"
                        :class="{'setting-sidebar-btn-active': activeTab === 'payment-methods'}"
                        class="setting-sidebar-btn">
                        <svg class="setting-sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                        </svg>
                        {{ __('settings.payment_methods') }}
                    </button>
                    @endcan

                    @can('viewAgentCharges', 'App\Models\Setting')
                    <button
                        @click="saveTab('agent-charges')"
                        :class="{'setting-sidebar-btn-active': activeTab === 'agent-charges'}"
                        class="setting-sidebar-btn">
                        <svg class="setting-sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        {{ __('settings.agent_charges') }}
                    </button>
                    @endcan

                    @can('viewAgentLoss', 'App\Models\Setting')
                    <button
                        @click="saveTab('agent-loss')"
                        :class="{'setting-sidebar-btn-active': activeTab === 'agent-loss'}"
                        class="setting-sidebar-btn">
                        <svg class="setting-sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                        </svg>
                        {{ __('settings.agent_loss') }}
                    </button>
                    @endcan

                    @can('viewNotifications', 'App\Models\Setting')
                    <button
                        @click="saveTab('notifications')"
                        :class="{'setting-sidebar-btn-active': activeTab === 'notifications'}"
                        class="setting-sidebar-btn">
                        <svg class="setting-sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                        {{ __('settings.notifications') }}
                    </button>
                    @endcan
                </nav>
            </div>

            <!-- Content Area -->
            <div class="setting-content">

                <!-- <div x-show="activeTab === 'invoice'" x-cloak>
                    @include('settings.partial.invoice')
                </div> -->
                <div x-show="activeTab === 'payment'" x-cloak>
                    @include('settings.partial.payment')
                </div>
                <div x-show="activeTab === 'terms'" x-cloak>
                    @include('settings.partial.terms_condition')
                </div>
                @can('viewAny', 'App\Models\Charge')
                <div x-show="activeTab === 'charges'" x-cloak x-ref="chargesTab">
                    @include('settings.partial.charges')
                </div>
                @endcan
                @can('viewPaymentMethodGroup', 'App\Models\PaymentMethod')
                <div x-show="activeTab === 'payment-methods'" x-cloak>
                    @include('settings.partial.payment_methods')
                </div>
                @endcan
                @can('viewAgentCharges', 'App\Models\Setting')
                <div x-show="activeTab === 'agent-charges'" x-cloak>
                    @include('settings.partial.agent_charges')
                </div>
                @endcan
                @can('viewAgentLoss', 'App\Models\Setting')
                <div x-show="activeTab === 'agent-loss'" x-cloak>
                    @include('settings.partial.agent_loss')
                </div>
                @endcan
                @can('viewNotifications', 'App\Models\Setting')
                <div x-show="activeTab === 'notifications'" x-cloak>
                    @include('settings.partial.notifications')
                </div>
                @endcan
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {

            console.log("Active Tab: {{ $activeTab }}");

            if ("{{ $activeTab }}" === 'charges') {
                window.Alpine && Alpine.nextTick(() => {
                    window.dispatchEvent(new CustomEvent('charges-tab-loaded'));
                });
            } else if ("{{ $activeTab }}" === 'payment') {
                window.Alpine && Alpine.nextTick(() => {
                    window.dispatchEvent(new CustomEvent('payment-tab-loaded'));
                });
            } else if ("{{ $activeTab }}" === 'payment-methods') {
                window.Alpine && Alpine.nextTick(() => {
                    window.dispatchEvent(new CustomEvent('payment-methods-tab-loaded'));
                });
            } else if ("{{ $activeTab }}" === 'agent-charges') {
                window.Alpine && Alpine.nextTick(() => {
                    window.dispatchEvent(new CustomEvent('agent-charges-tab-loaded'));
                });
            } else if ("{{ $activeTab }}" === 'agent-loss') {
                window.Alpine && Alpine.nextTick(() => {
                    window.dispatchEvent(new CustomEvent('agent-loss-tab-loaded'));
                });
            } else if ("{{ $activeTab }}" === 'notifications') {
                window.Alpine && Alpine.nextTick(() => {
                    window.dispatchEvent(new CustomEvent('notifications-tab-loaded'));
                });
            }
        });

        function settingsPage() {
            return {
                activeTab: "{{ $activeTab }}",
                companyId: "{{ $companyId }}",
                sidebarOpen: false,
                tabLabels: {
                    'payment': '{{ __('settings.payment') }}',
                    'terms': '{{ __('settings.terms_regulations') }}',
                    'charges': '{{ __('settings.payment_gateways') }}',
                    'payment-methods': '{{ __('settings.payment_methods') }}',
                    'agent-charges': '{{ __('settings.agent_charges') }}',
                    'agent-loss': '{{ __('settings.agent_loss') }}',
                    'notifications': '{{ __('settings.notifications') }}',
                },

                init() {
                    // Load data for the active tab on page load
                    if (this.activeTab === 'terms') {
                        this.loadTemplates();
                    }
                },

                saveTab(tab) {
                    this.activeTab = tab;
                    this.sidebarOpen = false;
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

                    if (tab === 'terms') {
                        this.loadTemplates();
                    } else if (tab === 'charges') {
                        window.dispatchEvent(new CustomEvent('charges-tab-loaded'));
                    } else if (tab === 'payment') {
                        window.dispatchEvent(new CustomEvent('payment-tab-loaded'));
                    } else if (tab === 'payment-methods') {
                        window.dispatchEvent(new CustomEvent('payment-methods-tab-loaded'));
                    } else if (tab === 'agent-charges') {
                        window.dispatchEvent(new CustomEvent('agent-charges-tab-loaded'));
                    } else if (tab === 'agent-loss') {
                        window.dispatchEvent(new CustomEvent('agent-loss-tab-loaded'));
                    } else if (tab === 'notifications') {
                        window.dispatchEvent(new CustomEvent('notifications-tab-loaded'));
                    }
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
                    if (this.companyId) {
                        params.append('company_id', this.companyId);
                    }

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