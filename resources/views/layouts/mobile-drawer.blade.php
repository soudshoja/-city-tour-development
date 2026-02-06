<div x-data="{
    mobileDrawerOpen: false,
    activeMenu: null,
    notification: false,
    open: false,
    iataWallet: false,
    touchStartX: 0,
    touchCurrentX: 0,
    isDragging: false,
    swipeThreshold: 80,

    handleTouchStart(e) {
        this.touchStartX = e.touches[0].clientX;
        this.touchCurrentX = this.touchStartX;
        this.isDragging = true;
    },

    handleTouchMove(e) {
        if (!this.isDragging) return;
        this.touchCurrentX = e.touches[0].clientX;
        const diff = this.touchCurrentX - this.touchStartX;
        if (diff < 0) {
            this.$refs.drawer.style.transform = `translateX(${diff}px)`;
            this.$refs.backdrop.style.opacity = Math.max(0, 1 + (diff / 300));
        }
    },

    handleTouchEnd() {
        if (!this.isDragging) return;
        this.isDragging = false;
        const diff = this.touchCurrentX - this.touchStartX;

        this.$refs.drawer.style.transform = '';
        this.$refs.backdrop.style.opacity = '';

        if (diff < -this.swipeThreshold) {
            this.mobileDrawerOpen = false;
        }
    }
}"
    @open-mobile-drawer.window="mobileDrawerOpen = true"
    @keydown.escape.window="mobileDrawerOpen = false">

    <div x-show="mobileDrawerOpen"
        x-cloak
        x-ref="backdrop"
        @click="mobileDrawerOpen = false"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="mobile-drawer-backdrop">
    </div>

    <div x-show="mobileDrawerOpen"
        x-cloak
        x-ref="drawer"
        @touchstart="handleTouchStart"
        @touchmove="handleTouchMove"
        @touchend="handleTouchEnd"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="-translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        class="mobile-drawer">

        <div class="mobile-drawer-header">
            <a href="{{ route('dashboard') }}" class="mobile-drawer-logo">
                <x-application-logo width="40" height="40" class="rounded-full" />
                <span class="mobile-drawer-brand">{{ $companyName }}</span>
            </a>
            <button @click="mobileDrawerOpen = false" class="mobile-drawer-close">
                <x-icons.close class="w-6 h-6" />
            </button>
        </div>

        <div class="mobile-drawer-content">
            <a href="{{ route('dashboard') }}" class="mobile-drawer-item">
                <x-icons.dashboard />
                <span>Dashboard</span>
            </a>

            <div class="mobile-drawer-divider"></div>

            @can('viewAny', 'App\Models\Task')
            <div class="mobile-drawer-accordion">
                <button @click="activeMenu = activeMenu === 'tasks' ? null : 'tasks'" class="mobile-drawer-accordion-btn">
                    <div class="flex items-center gap-3">
                        <x-icons.tasks />
                        <span>Tasks</span>
                    </div>
                    <x-icons.chevron-down class="w-4 h-4 transition-transform duration-200" x-bind:class="activeMenu === 'tasks' ? 'rotate-180' : ''" />
                </button>
                <div x-show="activeMenu === 'tasks'" x-collapse class="mobile-drawer-submenu">
                    <a href="{{ route('tasks.index') }}" class="mobile-drawer-subitem">Tasks List</a>
                    @can('viewAny', App\Models\Payment::class)
                    <a href="{{ route('payment.outstanding') }}" class="mobile-drawer-subitem">Outstanding Payments</a>
                    @endcan
                </div>
            </div>
            @endcan

            @can('viewAny', 'App\Models\CoaCategory')
            <div class="mobile-drawer-accordion">
                <button @click="activeMenu = activeMenu === 'finances' ? null : 'finances'" class="mobile-drawer-accordion-btn">
                    <div class="flex items-center gap-3">
                        <x-icons.finances />
                        <span>Finances</span>
                    </div>
                    <x-icons.chevron-down class="w-4 h-4 transition-transform duration-200" x-bind:class="activeMenu === 'finances' ? 'rotate-180' : ''" />
                </button>
                <div x-show="activeMenu === 'finances'" x-collapse class="mobile-drawer-submenu">
                    <a href="{{ route('coa.index') }}" class="mobile-drawer-subitem">Chart of Account</a>
                    <a href="{{ route('bank-payments.index') }}" class="mobile-drawer-subitem">Payment Voucher</a>
                    <a href="{{ route('receipt-voucher.index') }}" class="mobile-drawer-subitem">Receipt Voucher</a>
                    <a href="{{ route('receivable-details.receivable-create') }}" class="mobile-drawer-subitem">Receivable</a>
                    <a href="{{ route('payable-details.payable-create') }}" class="mobile-drawer-subitem">Payable</a>
                    @can('viewCompanySummary', 'App\Models\Account')
                    <a href="{{ route('accounting.index') }}" class="mobile-drawer-subitem">Accounting</a>
                    @endcan
                </div>
            </div>
            @endcan

            @can('viewAny', 'App\Models\Invoice')
            <div class="mobile-drawer-accordion">
                <button @click="activeMenu = activeMenu === 'invoices' ? null : 'invoices'" class="mobile-drawer-accordion-btn">
                    <div class="flex items-center gap-3">
                        <x-icons.invoices />
                        <span>Invoices</span>
                    </div>
                    <x-icons.chevron-down class="w-4 h-4 transition-transform duration-200" x-bind:class="activeMenu === 'invoices' ? 'rotate-180' : ''" />
                </button>
                <div x-show="activeMenu === 'invoices'" x-collapse class="mobile-drawer-submenu">
                    <a href="{{ route('invoices.index') }}" class="mobile-drawer-subitem">Invoices List</a>
                    <a href="{{ route('invoices.link') }}" class="mobile-drawer-subitem">Invoices Link</a>
                    @can('viewAny', 'App\Models\Payment')
                    <a href="{{ route('payment.link.index') }}" class="mobile-drawer-subitem">Payment Link</a>
                    @endcan
                    @can('viewAny', 'App\Models\Refund')
                    <a href="{{ route('refunds.index') }}" class="mobile-drawer-subitem">Refund</a>
                    @endcan
                    @can('viewAny', 'App\Models\AutoBilling')
                    <a href="{{ route('auto-billing.index') }}" class="mobile-drawer-subitem">Auto Billing</a>
                    @endcan
                    <a href="{{ route('reminder.index') }}" class="mobile-drawer-subitem">Reminder</a>
                </div>
            </div>
            @endcan

            @can('viewAny', 'App\Models\User')
            <div class="mobile-drawer-accordion">
                <button @click="activeMenu = activeMenu === 'users' ? null : 'users'" class="mobile-drawer-accordion-btn">
                    <div class="flex items-center gap-3">
                        <x-icons.users />
                        <span>Users</span>
                    </div>
                    <x-icons.chevron-down class="w-4 h-4 transition-transform duration-200" x-bind:class="activeMenu === 'users' ? 'rotate-180' : ''" />
                </button>
                <div x-show="activeMenu === 'users'" x-collapse class="mobile-drawer-submenu">
                    <a href="{{ route('users.index') }}" class="mobile-drawer-subitem">Users List</a>
                    @can('viewAny', 'App\Models\Company')
                    <a href="{{ route('companies.list') }}" class="mobile-drawer-subitem">Companies List</a>
                    @endcan
                    @can('viewAny', App\Models\Branch::class)
                    <a href="{{ route('branches.index') }}" class="mobile-drawer-subitem">Branches List</a>
                    @endcan
                    @can('viewAny', App\Models\Agent::class)
                    <a href="{{ route('agents.index') }}" class="mobile-drawer-subitem">Agents List</a>
                    @endcan
                    @can('viewAny', App\Models\Client::class)
                    <a href="{{ route('clients.index') }}" class="mobile-drawer-subitem">Clients List</a>
                    @endcan
                </div>
            </div>
            @endcan

            @can('viewAny', 'App\Models\Report')
            <div class="mobile-drawer-accordion">
                <button @click="activeMenu = activeMenu === 'reports' ? null : 'reports'" class="mobile-drawer-accordion-btn">
                    <div class="flex items-center gap-3">
                        <x-icons.reports />
                        <span>Reports</span>
                    </div>
                    <x-icons.chevron-down class="w-4 h-4 transition-transform duration-200" x-bind:class="activeMenu === 'reports' ? 'rotate-180' : ''" />
                </button>
                <div x-show="activeMenu === 'reports'" x-collapse class="mobile-drawer-submenu">
                    <a href="{{ route('reports.paid-report') }}" class="mobile-drawer-subitem">Paid Acc Pay/Receive</a>
                    <a href="{{ route('reports.unpaid-report') }}" class="mobile-drawer-subitem">Unpaid Acc Pay/Receive</a>
                    @can('viewProfitLoss', 'App\Models\Report')
                    <a href="{{ route('reports.profit-loss') }}" class="mobile-drawer-subitem">Profit & Loss</a>
                    @endcan
                    @can('viewSettlement', 'App\Models\Report')
                    <a href="{{ route('reports.settlements') }}" class="mobile-drawer-subitem">Bank Settlement</a>
                    @endcan
                    @can('viewAny', 'App\Models\CoaCategory')
                    <a href="{{ route('coa.transaction') }}" class="mobile-drawer-subitem">Transaction List</a>
                    @endcan
                    @can('viewCreditors', 'App\Models\Report')
                    <a href="{{ route('reports.creditors') }}" class="mobile-drawer-subitem">Creditors Report</a>
                    @endcan
                    @can('viewDailySales', 'App\Models\Report')
                    <a href="{{ route('reports.daily-sales') }}" class="mobile-drawer-subitem">Daily Sales</a>
                    @endcan
                    @can('viewTaskReport', 'App\Models\Report')
                    <a href="{{ route('reports.tasks') }}" class="mobile-drawer-subitem">Task Report</a>
                    @endcan
                    @can('viewClientReport', 'App\Models\Report')
                    <a href="{{ route('reports.client') }}" class="mobile-drawer-subitem">Client Report</a>
                    @endcan
                </div>
            </div>
            @endcan

            <div class="mobile-drawer-accordion">
                <button @click="activeMenu = activeMenu === 'settings' ? null : 'settings'" class="mobile-drawer-accordion-btn">
                    <div class="flex items-center gap-3">
                        <x-icons.settings />
                        <span>Settings</span>
                    </div>
                    <x-icons.chevron-down class="w-4 h-4 transition-transform duration-200" x-bind:class="activeMenu === 'settings' ? 'rotate-180' : ''" />
                </button>
                <div x-show="activeMenu === 'settings'" x-collapse class="mobile-drawer-submenu">
                    <a href="{{ route('settings.index') }}" class="mobile-drawer-subitem">Settings</a>
                    @can('manage-system-settings')
                    <a href="{{ route('system-settings.index') }}" class="mobile-drawer-subitem">System Settings</a>
                    @endcan
                    @can('viewAny', App\Models\Supplier::class)
                    <a href="{{ route('suppliers.index') }}" class="mobile-drawer-subitem">Suppliers</a>
                    @endcan
                    @can('viewAny', App\Models\Role::class)
                    <a href="{{ route('role.index') }}" class="mobile-drawer-subitem">Manage Roles</a>
                    @endcan
                    <a href="#" class="mobile-drawer-subitem">Documentations</a>
                    <a href="#" class="mobile-drawer-subitem">Help</a>
                    @can('viewAny', App\Models\CurrencyExchange::class)
                    <a href="{{ route('exchange.index') }}" class="mobile-drawer-subitem">Currency Exchange</a>
                    <a href="{{ route('exchange.histories.all') }}" class="mobile-drawer-subitem">Exchange History</a>
                    @endcan
                </div>
            </div>
        </div>

        <div class="mobile-drawer-footer">
            <div class="first-section">
                @if(auth()->user()->role_id == \App\Models\Role::ADMIN)
                <div class="mobile-drawer-company">
                    <x-sidebar-company
                        :companies="$sidebarCompanies ?? collect()"
                        :currentCompanyId="$currentCompanyId ?? 1" />
                </div>
                @endif

                @can('viewAny', App\Models\CurrencyExchange::class)
                <div class="mobile-drawer-currency-exchange"
                    x-data="currencyConverter({ companyId: window.APP_COMPANY_ID, convertUrl: '{{ route('exchange.convert') }}'})">
                    <button @click="showModal = true" class="currency-exchange-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <circle cx="12" cy="12" r="10" />
                            <path d="M8 15c0 2 2 3 4 3s4-1 4-3-2-3-4-3-4-1-4-3 2-3 4-3 4 1 4 3" />
                            <path d="M12 6v12" />
                        </svg>
                        <span>Currency Exchange</span>
                    </button>

                    <template x-teleport="body">
                        <div x-show="showModal" x-cloak
                            x-init="$watch('showModal', value => {
                                if (value) {
                                    $nextTick(() => {
                                        const fromEl = document.getElementById('mobileFromSelect');
                                        const toEl = document.getElementById('mobileToSelect');
                                        if (!from && fromEl) from = fromEl.value;
                                        if (!to && toEl) to = toEl.value;
                                        convertIfReady();
                                    });
                                }
                            })"
                            @click.self="showModal = false"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                            class="fixed inset-0 z-[9999] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm">
                            <div @click.stop
                                class="bg-white dark:bg-gray-800 rounded-lg shadow-2xl w-[calc(100vw-2rem)] max-w-sm max-h-[80vh] overflow-visible">

                                <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
                                    <div>
                                        <h2 class="text-lg font-bold text-gray-800 dark:text-white">Currency Exchange</h2>
                                        <p class="text-gray-500 dark:text-gray-400 text-xs">Quick currency conversion</p>
                                    </div>
                                    <button @click="showModal = false" class="text-gray-400 hover:text-red-500 text-2xl">&times;</button>
                                </div>

                                <div class="p-4 max-h-[calc(80vh-4rem)] overflow-y-auto">
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Amount</label>
                                        <input type="text" x-model.number="amount"
                                            @input.debounce.400ms="convertIfReady"
                                            class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md px-4 py-3 text-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                    </div>

                                    <div class="mb-4 space-y-3">
                                        <div>
                                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">From</label>
                                            <select id="mobileFromSelect" x-model="from" @change="convertIfReady"
                                                class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md px-3 py-2 text-sm">
                                                @foreach($allIso ?? [] as $code)
                                                @php $c = $currencies[$code] ?? null; @endphp
                                                <option value="{{ $code }}">{{ $code }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="flex justify-center">
                                            <button type="button" @click="swap(); convertIfReady()" class="p-2 rounded-full border bg-white dark:bg-gray-600 shadow">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                                </svg>
                                            </button>
                                        </div>

                                        <div>
                                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">To</label>
                                            <select id="mobileToSelect" x-model="to" @change="convertIfReady"
                                                class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md px-3 py-2 text-sm">
                                                @foreach($allIso ?? [] as $code)
                                                @php $c = $currencies[$code] ?? null; @endphp
                                                <option value="{{ $code }}">{{ $code }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <template x-if="ready">
                                        <div class="text-center py-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                            <p class="text-sm text-gray-600 dark:text-gray-300" x-text="`${format(amount)} ${from} =`"></p>
                                            <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                                                <span x-text="format(converted)"></span>
                                                <span x-text="to"></span>
                                            </p>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                                <p x-text="`1 ${from} = ${parseFloat(rate).toFixed(4)} ${to}`"></p>
                                            </div>
                                        </div>
                                    </template>

                                    <template x-if="error">
                                        <div class="mt-2 text-center text-red-500 text-sm" x-text="error"></div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                @endcan

                <div class="mobile-drawer-profile-actions">
                    <div @click="notification = true">
                        <div class="mobile-drawer-action-btn w-full">
                            <x-icons.notification />
                            <span>Notifications</span>
                        </div>
                        <div
                            x-show="notification"
                            x-cloak
                            class="notification-wrapper">
                            <div
                                @click.away="notification = false"
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 transform scale-90"
                                x-transition:enter-end="opacity-100 transform scale-100"
                                x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-start="opacity-100 transform scale-100"
                                x-transition:leave-end="opacity-0 transform scale-90"
                                class="profile-notification-dropdown">
                                <div class="profile-notification-header">
                                    <h2 class="profile-notification-title">
                                        Notifications
                                    </h2>

                                    <!-- Close button -->
                                    <button type="button" @click.stop="notification = false" aria-label="Close">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="profile-notification-close-icon">
                                            <path d="M14.5 9.50002L9.5 14.5M9.49998 9.5L14.5 14.5" stroke="" stroke-width="1.5" stroke-linecap="round" />
                                            <path d="M7 3.33782C8.47087 2.48697 10.1786 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 10.1786 2.48697 8.47087 3.33782 7" stroke="" stroke-width="1.5" stroke-linecap="round" />
                                        </svg>
                                    </button>
                                </div>

                                <div class="profile-notification-list">
                                    <livewire:notification />
                                </div>

                                <div class="profile-notification-footer">
                                    <a
                                        href="javascript:void(0);"
                                        wire:click="markAllAsRead"
                                        class="profile-notification-mark-read">
                                        Mark all as read
                                    </a>

                                    <a
                                        href="{{ route('notifications.index') }}"
                                        class="profile-notification-view-all">
                                        View all notifications
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div x-ref="walletTrigger"
                        x-init="$watch('iataWallet', value => { if (value) checkAndLoadWalletData($refs.walletTrigger); })"
                        class="flex-1">
                        <button @click="iataWallet = !iataWallet" class="mobile-drawer-action-btn w-full">
                            <x-heroicon-o-wallet class="w-5 h-5" />
                            <span>Wallet</span>
                        </button>

                        <div x-show="iataWallet" x-cloak class="wallet-wrapper">
                            <div @click.away="iataWallet = false"
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 transform scale-90"
                                x-transition:enter-end="opacity-100 transform scale-100"
                                x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-start="opacity-100 transform scale-100"
                                x-transition:leave-end="opacity-0 transform scale-90"
                                class="profile-wallet-dropdown">
                            <div class="profile-wallet-iata-header">
                                <div class="profile-wallet-header-row">
                                    <h5 class="profile-wallet-heading">
                                        <x-icons.wallet class="profile-wallet-heading-icon" />
                                        IATA Company Wallet
                                    </h5>
                                    <button @click.stop="checkAndLoadWalletData($refs.walletTrigger, true)" class="profile-wallet-reload-btn" title="Reload">
                                        <x-icons.refresh class="profile-wallet-reload-icon" />
                                        Reload
                                    </button>
                                </div>
                                <div class="iata-info profile-wallet-info"></div>
                            </div>
                            <div class="jazeera-section profile-wallet-jazeera-section">
                                <div class="profile-wallet-header-row">
                                    <h5 class="profile-wallet-heading">
                                        <x-icons.wallet class="profile-wallet-jazeera-heading-icon" />
                                        Jazeera Airways Credit
                                    </h5>
                                </div>
                                <div class="jazeera-info profile-wallet-info"></div>
                            </div>
                            </div>
                        </div>
                    </div>
                </div>

                <a href="{{ route('profile.edit') }}" class="mobile-drawer-user-card">
                    <div class="mobile-drawer-user-avatar {{ $color }}">
                        <x-icons.user-avatar class="w-6 h-6" />
                    </div>
                    <div class="mobile-drawer-user-info">
                        <span class="mobile-drawer-user-name">{{ Auth::user()->name }}</span>
                        <span class="mobile-drawer-user-email">{{ Auth::user()->email }}</span>
                    </div>
                    <x-icons.edit class="mobile-drawer-edit-icon" />
                </a>

            </div>
            <div class="mobile-drawer-utilities">
                <button id="mobileThemeToggle" class="mobile-drawer-theme-btn">
                    <x-icons.theme-light id="mobileLightIcon" />
                    <span>Theme</span>
                </button>

                <form method="POST" action="{{ route('logout') }}" class="mobile-drawer-logout-form">
                    @csrf
                    <button type="submit" class="mobile-drawer-logout-btn">
                        <x-icons.logout />
                        <span>Sign Out</span>
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const mobileThemeToggle = document.getElementById('mobileThemeToggle');
        if (mobileThemeToggle) {
            mobileThemeToggle.addEventListener('click', function() {
                const themeButton = document.getElementById('themeButton');
                if (themeButton) {
                    themeButton.click();
                }
            });
        }
    });
</script>