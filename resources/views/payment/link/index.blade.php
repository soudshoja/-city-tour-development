<x-app-layout>
    @push('styles')
        @vite(['resources/css/payment-link/index.css'])
    @endpush

    <div class="pl-header">
        <div class="pl-header-left">
            <h2 class="pl-title">Payment Links</h2>
            <div data-tooltip="Number of payments" class="pl-count-badge DarkBGcolor">
                <span>{{ $payments->total() + $importedPayments->total() }}</span>
            </div>
        </div>
        <div class="pl-header-right">
            <div data-tooltip-left="Reload" class="rotate refresh-icon pl-action-btn pl-refresh-btn" onclick="window.location.reload()">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                    <path fill="currentColor"
                        d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.66 7.66 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.16 9.16 0 0 0 12.08 2.25" />
                    <path fill="currentColor"
                        d="M20.841 10.467a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.381 3.83-3.651 6.833-7.644 6.833a7.7 7.7 0 0 1-6.565-3.644a.75.75 0 1 0-1.276.788a9.2 9.2 0 0 0 7.84 4.356c4.809 0 8.766-3.66 9.151-8.333H22a.75.75 0 0 0 .527-1.284z"
                        opacity=".5" />
                </svg>
            </div>

            <div x-data="{ showImportModal: false }">
                <button @click="showImportModal = true" data-tooltip-left="Import from file"
                    class="pl-action-btn pl-import-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                        <polyline points="17 8 12 3 7 8"/>
                        <line x1="12" y1="3" x2="12" y2="15"/>
                    </svg>
                </button>

                <template x-teleport="body">
                    <div x-cloak x-show="showImportModal" class="pl-modal-overlay">
                        <div class="pl-modal" @click.outside="showImportModal = false">
                            <div class="flex items-center justify-between mb-6">
                                <div>
                                    <h2 class="pl-modal-title">Import Payments from File</h2>
                                    <p class="pl-modal-subtitle">Upload an Excel or CSV file exported from your payment gateway</p>
                                </div>
                                <button @click="showImportModal = false" class="pl-modal-close">&times;</button>
                            </div>
                            <form action="{{ route('payment.link.import.file') }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                <div class="mb-4">
                                    <label class="pl-modal-label">Payment Gateway</label>
                                    <select name="gateway" required class="pl-modal-select">
                                        <option value="" disabled selected>Select Gateway</option>
                                        @foreach ($can_import as $gw)
                                            <option value="{{ $gw->name }}">{{ $gw->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-6">
                                    <label class="pl-modal-label">File</label>
                                    <input type="file" name="file" accept=".xlsx,.csv,.xls" required
                                        class="pl-file-input file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:bg-blue-50 file:text-blue-700 file:cursor-pointer hover:file:bg-blue-100">
                                    <p class="pl-file-hint">Accepted: .xlsx, .csv, .xls</p>
                                </div>
                                <div class="flex justify-between">
                                    <button type="button" @click="showImportModal = false"
                                        class="pl-btn-cancel">Cancel</button>
                                    <button type="submit"
                                        class="pl-btn-primary">Import</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </template>
            </div>

            <a id="export-payment-links-btn" data-tooltip-left="Export to Excel"
                class="pl-action-btn pl-import-btn" style="cursor:pointer;">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
            </a>

            <a href="{{ route('payment.link.create') }}">
                <div data-tooltip-left="Create payment link"
                    class="pl-action-btn pl-create-btn btn-success">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#fff"
                            d="M16 8h-2v3h-3v2h3v3h2v-3h3v-2h-3M2 12c0-2.79 1.64-5.2 4-6.32V3.5C2.5 4.76 0 8.09 0 12s2.5 7.24 6 8.5v-2.18C3.64 17.2 2 14.79 2 12m13-9c-4.96 0-9 4.04-9 9s4.04 9 9 9s9-4.04 9-9s-4.04-9-9-9m0 16c-3.86 0-7-3.14-7-7s3.14-7 7-7s7 3.14 7 7s-3.14 7-7 7" />
                    </svg>
                </div>
            </a>
        </div>
    </div>

    <div x-data="{
            activeTab: localStorage.getItem('payment_link_tab') || 'payment_links',
            setTab(tab) {
                this.activeTab = tab;
                localStorage.setItem('payment_link_tab', tab);
            }
         }">
        <div class="main-tabs-bar">
            <button @click="setTab('payment_links')" class="main-tab-shape main-tab main-tab-active"
                :class="{ 'main-tab-active': activeTab === 'payment_links', 'main-tab-inactive': activeTab !== 'payment_links' }">
                <div class="main-tab-content-wrapper">
                    <svg class="main-tab-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                    </svg>
                    Payment Links
                    <span class="main-tab-badge main-tab-badge-amber">{{ $payments->total() }}</span>
                </div>
            </button>

            <button @click="setTab('imported')" class="main-tab-shape main-tab main-tab-inactive"
                :class="{ 'main-tab-active': activeTab === 'imported', 'main-tab-inactive': activeTab !== 'imported' }">
                <div class="main-tab-content-wrapper">
                    <svg class="main-tab-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                    </svg>
                    Imported
                    <span class="main-tab-badge main-tab-badge-red">{{ $importedPayments->total() }}</span>
                </div>
            </button>
        </div>

        <div x-show="activeTab === 'payment_links'" class="main-tab-content">
            <div x-data="{ openFilters: false }">
                <div class="pl-toolbar md:flex-nowrap">
                    <x-search
                        :action="route('payment.link.index')"
                        searchParam="search"
                        placeholder="Quick search for payments" />

                    <div class="shrink-0 flex items-center gap-2">
                        <span class="pl-date-label">Select a date:</span>
                        <input type="text" id="payment-date-range" class="pl-date-input" placeholder="Choose date range">
                    </div>

                    <button @click="openFilters = !openFilters" class="pl-filter-btn">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M4 6h16M7 12h10M10 18h4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Filters
                        @if (!empty($filters)) <span class="pl-filter-count">{{ collect($filters)->filter()->count() }}</span> @endif
                    </button>
                </div>

                <form id="date-filter-form" action="{{ route('payment.link.index') }}" method="GET" class="hidden">
                    <input type="hidden" name="search" value="{{ request('search') }}">
                    <input type="hidden" name="filter[date_from]" id="date_from" value="{{ data_get($filters, 'date_from') }}">
                    <input type="hidden" name="filter[date_to]" id="date_to" value="{{ data_get($filters, 'date_to') }}">
                    <input type="hidden" name="filter[client_id]" value="{{ data_get($filters, 'client_id') }}">
                    <input type="hidden" name="filter[agent_id]" value="{{ data_get($filters, 'agent_id') }}">
                    <input type="hidden" name="filter[created_by]" value="{{ data_get($filters, 'created_by') }}">
                    <input type="hidden" name="filter[payment_gateway]" value="{{ data_get($filters, 'payment_gateway') }}">
                    <input type="hidden" name="filter[status]" value="{{ data_get($filters, 'status') }}">
                </form>

                <div x-show="openFilters" x-cloak x-transition class="pl-filter-panel">
                    <div class="pl-filter-header">
                        <span class="pl-filter-title">Filter payments</span>
                        <button @click="openFilters = false" class="pl-filter-hide-btn">Hide</button>
                    </div>
                    <form action="{{ route('payment.link.index') }}" method="GET" class="px-4 pt-4">
                        <input type="hidden" name="search" value="{{ request('search') }}" />
                        <input type="hidden" name="filter[date_from]" value="{{ data_get($filters, 'date_from') }}">
                        <input type="hidden" name="filter[date_to]" value="{{ data_get($filters, 'date_to') }}">

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                            <x-searchable-dropdown
                                name="filter[client_id]"
                                :items="$clients->map(fn($c) => [
                                    'id' => $c->id,
                                    'name' => $c->full_name . ' - ' . $c->phone
                                ])"
                                :placeholder="'Select clients'"
                                :selectedName="optional($clients->firstWhere('id', data_get($filters,'client_id')))->name"
                                label="Client" />

                            <x-searchable-dropdown
                                name="filter[agent_id]"
                                :items="$agents->map(fn($a) => ['id' => $a->id, 'name' => $a->name])"
                                :placeholder="'Select agents'"
                                :selectedName="optional($agents->firstWhere('id', data_get($filters,'agent_id')))->name"
                                label="Agent" />

                            <x-searchable-dropdown
                                name="filter[created_by]"
                                :items="$users->map(fn($u) => ['id' => $u->id, 'name' => $u->name])"
                                :placeholder="'Select users'"
                                :selectedName="optional($users->firstWhere('id', data_get($filters,'created_by')))->name"
                                label="Created By" />

                            <x-searchable-dropdown
                                name="filter[payment_gateway]"
                                :items="$paymentGateways->map(fn($g) => ['id' => $g->name, 'name' => $g->name])"
                                :placeholder="'Select gateways'"
                                :selectedName="data_get($filters,'payment_gateway')"
                                label="Payment Gateway" />

                            <x-searchable-dropdown
                                name="filter[status]"
                                :items="collect($status)->map(fn($s) => ['id' => $s, 'name' => ucfirst($s)])"
                                :placeholder="'Select status'"
                                :selectedName="data_get($filters,'status') ? ucfirst(data_get($filters,'status')) : null"
                                label="Status" />
                        </div>
                        <div class="pl-filter-footer">
                            <a href="{{ route('payment.link.index', array_filter(['search' => request('search'), 'clear' => 1])) }}"
                                class="pl-filter-clear">
                                Clear
                            </a>
                            <button type="submit" class="pl-filter-apply">Apply Filters</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="pl-col-header">
                <div class="col-span-4">Payment Details</div>
                <div class="col-span-2">Client & Agent</div>
                <div class="col-span-2">Payment Methods</div>
                <div class="col-span-2">Amount Details</div>
                <div class="col-span-2 text-center">Actions</div>
            </div>

            <div class="pl-table">
                @forelse ($payments as $index => $payment)
                <div class="pl-row {{ $index % 2 === 0 ? 'pl-row-even' : 'pl-row-odd' }}">
                    <div class="pl-row-grid">
                        <div class="md:col-span-1 xl:col-span-4 space-y-2">
                            <div class="flex items-center gap-2 flex-wrap">
                                <a href="{{ route('payment.show', $payment->id) }}" target="_blank" class="pl-voucher-link">
                                    {{ $payment->voucher_number }}
                                </a>
                                <button type="button" onclick="copyToClipboard('{{ route('payment.show', $payment->id) }}')" class="pl-copy-btn" data-tooltip="Copy link">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                    </svg>
                                </button>
                                @php
                                    $statusClass = match(strtolower($payment->status)) {
                                        'pending' => 'pl-status-pending',
                                        'completed' => 'pl-status-completed',
                                        'initiate' => 'pl-status-initiate',
                                        default => 'pl-status-default'
                                    };
                                @endphp
                                <span class="pl-status {{ $statusClass }}">{{ ucfirst($payment->status) }}</span>
                            </div>
                            <div class="pl-meta-grid">
                                @if ($payment->notes)
                                    <div class="pl-meta-item pl-meta-item-full">
                                        <svg class="pl-meta-icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                        <div>
                                            <span class="pl-meta-label">Notes</span>
                                            <span class="pl-meta-value">{{ $payment->notes }}</span>
                                        </div>
                                    </div>
                                @endif
                                <div class="pl-meta-item">
                                    <svg class="pl-meta-icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                    <div>
                                        <span class="pl-meta-label">Created</span>
                                        <span class="pl-meta-value">{{ $payment->created_at?->format('d M Y H:i') ?? 'N/A' }}</span>
                                    </div>
                                </div>
                                <div class="pl-meta-item">
                                    <svg class="pl-meta-icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                    <div>
                                        <span class="pl-meta-label">Paid</span>
                                        <span class="pl-meta-value">{{ $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('d M Y H:i') : 'Not Yet' }}</span>
                                    </div>
                                </div>
                                <div class="pl-meta-item">
                                    <svg class="pl-meta-icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                    <div>
                                        <span class="pl-meta-label">Created By</span>
                                        <span class="pl-meta-value">{{ $payment->createdBy?->name ?? 'N/A' }}</span>
                                    </div>
                                </div>
                                @if (($paymentRef = $payment->myFatoorahPayment?->invoice_ref ?? $payment->payment_reference) !== null)
                                    <div class="pl-meta-item">
                                        <svg class="pl-meta-icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                                        <div>
                                            <span class="pl-meta-label">Reference</span>
                                            <span class="pl-meta-value">{{ $paymentRef }}</span>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="md:col-span-1 xl:col-span-2 space-y-3">
                            <div>
                                <div class="pl-section-label">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="8.5" cy="7" r="4"></circle>
                                        <line x1="20" y1="8" x2="20" y2="14"></line>
                                        <line x1="23" y1="11" x2="17" y2="11"></line>
                                    </svg>
                                    <span class="pl-label">Agent</span>
                                </div>
                                <span class="pl-name">{{ $payment->agent?->name ?? 'Not Set' }}</span>
                            </div>
                            <div>
                                <div class="pl-section-label">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="12" cy="7" r="4"></circle>
                                    </svg>
                                    <span class="pl-label">Client</span>
                                </div>
                                @if ($payment->client)
                                    <a href="{{ route('clients.show', $payment->client_id) }}" class="pl-client-link">
                                        {{ $payment->client->full_name }}
                                    </a>
                                    <div class="pl-phone">{{ $payment->client->phone_number }}</div>
                                @else
                                    <span class="pl-na">N/A</span>
                                @endif
                            </div>
                        </div>

                        <div class="md:col-span-1 xl:col-span-2">
                            <div class="flex flex-wrap gap-1.5">
                                @if ($payment->availablePaymentMethodGroups->isNotEmpty())
                                    @foreach ($payment->availablePaymentMethodGroups as $group)
                                    @php
                                    $methodCss = match($group->name) {
                                        'KNET' => 'pl-method-knet',
                                        'Visa/Mastercard' => 'pl-method-visa',
                                        'Apple Pay' => 'pl-method-apple',
                                        'Samsung Pay' => 'pl-method-samsung',
                                        default => 'pl-method-default',
                                    };
                                    @endphp
                                    <div class="pl-method-tag {{ $methodCss }}">
                                        <span>{{ $group->name }}</span>
                                    </div>
                                    @endforeach
                                @elseif ($payment->paymentMethod)
                                    @php
                                    $singleMethodCss = match($payment->paymentMethod->english_name) {
                                        'KNET' => 'pl-method-knet',
                                        'VISA/MASTER', 'Visa/MasterCard' => 'pl-method-visa',
                                        'Apple Pay' => 'pl-method-apple',
                                        'Samsung Pay' => 'pl-method-samsung',
                                        default => 'pl-method-default',
                                    };
                                    @endphp
                                    <div class="pl-method-tag {{ $singleMethodCss }}">
                                        <span>{{ $payment->paymentMethod->english_name }}</span>
                                    </div>
                                @else
                                    <span class="pl-method-none">No method selected</span>
                                @endif
                            </div>
                        </div>

                        <div class="md:col-span-1 xl:col-span-2">
                            <div class="pl-amount-grid">
                                <div class="pl-amount-label">Net Amount:</div>
                                <div class="pl-amount-value">{{ number_format($payment->amount, 3) }} {{ $payment->currency ?? 'KWD' }}</div>
                                <div class="pl-amount-label">Service Charge:</div>
                                <div class="pl-amount-value">{{ number_format($payment->service_charge ?? 0, 3) }} {{ $payment->currency ?? 'KWD' }}</div>
                                <div class="pl-amount-divider"></div>
                                <div class="pl-amount-total-label">Client Pay:</div>
                                <div class="pl-amount-total">
                                    {{ number_format($payment->amount + ($payment->service_charge ?? 0), 3) }}
                                    <span class="pl-currency">{{ $payment->currency ?? 'KWD' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="md:col-span-2 xl:col-span-2 pl-actions"
                             x-data="{ editPaymentLink: false }" @keydown.escape.window="editPaymentLink = false">
                            <form action="{{ route('resayil.share-payment-link') }}" method="POST" class="inline-block">
                                @csrf
                                <input type="hidden" name="client_id" value="{{ $payment->client_id }}">
                                <input type="hidden" name="payment_id" value="{{ $payment->id }}">
                                <input type="hidden" name="voucher_number" value="{{ $payment->voucher_number }}">
                                <button type="submit" data-tooltip="Send Link"
                                    class="pl-action pl-action-send">
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V4a2 2 0 10-4 0v1.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                    </svg>
                                </button>
                            </form>

                            <a data-tooltip="View Invoice" target="_blank"
                                href="{{ route('payment.link.show', ['companyId' => $payment->agent?->branch?->company_id, 'voucherNumber' => $payment->voucher_number]) }}"
                                class="pl-action pl-action-view">
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                    <path d="M12 4c-4.182 0-7.028 2.5-8.725 4.704C2.425 9.81 2 10.361 2 12c0 1.64.425 2.191 1.275 3.296C4.972 17.5 7.818 20 12 20s7.028-2.5 8.725-4.704C21.575 14.19 22 13.639 22 12c0-1.64-.425-2.191-1.275-3.296C19.028 6.5 16.182 4 12 4Z"/>
                                </svg>
                            </a>

                            <form action="{{ route('payment.link.payment.activation', $payment->id) }}" method="POST" class="inline-block">
                                @csrf
                                @if ($payment->status !== 'completed' && !$payment->is_disabled)
                                    <button data-tooltip="Disable Link"
                                        class="pl-action pl-action-lock">
                                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <rect x="5" y="11" width="14" height="10" rx="2" ry="2" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 11V7a5 5 0 00-10 0v4" />
                                        </svg>
                                    </button>
                                @elseif ($payment->status !== 'completed' && $payment->is_disabled)
                                    <button data-tooltip="Enable Link"
                                        class="pl-action pl-action-lock-disabled">
                                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <rect x="5" y="11" width="14" height="10" rx="2" ry="2" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 8a5 5 0 10-10 0v1" />
                                        </svg>
                                    </button>
                                @endif
                            </form>

                            @if ($payment->status !== 'completed')
                            <button @click="editPaymentLink = true" data-tooltip="Edit"
                                class="pl-action pl-action-edit">
                                <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path d="m4.144 16.735.493-3.425a.97.97 0 0 1 .293-.587l9.665-9.664a1.03 1.03 0 0 1 .973-.281 5.1 5.1 0 0 1 2.346 1.372 5.1 5.1 0 0 1 1.384 2.346 1.07 1.07 0 0 1-.282.973l-9.664 9.664a1.17 1.17 0 0 1-.598.294l-3.437.492a1.044 1.044 0 0 1-1.173-1.184"/>
                                </svg>
                            </button>
                            <form action="{{ route('payment.link.delete', $payment->id) }}" method="POST" class="inline-block">
                                @csrf
                                @method('DELETE')
                                <button type="submit" data-tooltip="Delete"
                                    class="pl-action pl-action-delete"
                                    onclick="return confirm('Are you sure you want to delete this payment link?')">
                                    <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <path d="M20.5 6H3.5M18.833 8.5l-.46 6.9c-.177 2.654-.265 3.981-1.13 4.79-.865.81-2.196.81-4.856.81h-.774c-2.66 0-3.991 0-4.856-.81-.865-.809-.954-2.136-1.13-4.79L5.166 8.5"/>
                                        <path d="M6.5 6c.056 0 .084 0 .11-.001.823-.021 1.55-.544 1.83-1.319.008-.024.017-.05.035-.103l.097-.29a1.77 1.77 0 0 1 .18-.48c.219-.42.625-.713 1.094-.788.117-.018.248-.018.51-.018h3.29c.261 0 .392 0 .51.018.468.075.874.367 1.093.788.07.134.112.258.18.48l.097.29.035.103c.28.775 1.006 1.298 1.83 1.32.025 0 .053 0 .109 0"/>
                                    </svg>
                                </button>
                            </form>
                            @endif

                            <template x-teleport="body">
                                <div x-cloak x-show="editPaymentLink" class="pl-modal-overlay">
                                    <div class="pl-modal" @click.outside="editPaymentLink = false">
                                        <div class="flex items-center justify-between mb-6">
                                            <div>
                                                <h2 class="pl-modal-title">Edit Payment Link Details</h2>
                                                <p class="pl-modal-subtitle">Please update the payment link details to ensure accurate information</p>
                                            </div>
                                            <button @click="editPaymentLink = false" class="absolute top-2 right-2 pl-modal-close">&times;</button>
                                        </div>
                                        @if($payment->status === 'initiate')
                                        <div class="mb-4 pl-warning-box">
                                            <div class="flex items-start gap-3">
                                                <svg class="w-5 h-5 text-yellow-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                                </svg>
                                                <div class="flex-1">
                                                    <p class="pl-warning-title">Payment Status: Initiate</p>
                                                    <p class="pl-warning-text mb-2">The following fields cannot be edited:</p>
                                                    <ul class="pl-warning-text list-disc list-inside space-y-1">
                                                        <li>Client Info</li>
                                                        <li>Payment Amount</li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                        @endif
                                        <form action="{{ route('payment.link.update', $payment->id) }}" method="POST">
                                            @csrf
                                            @method('PUT')
                                            @unlessrole('agent')
                                            <div class="mb-4">
                                                <x-searchable-dropdown name="agent_id"
                                                    :items="$agents->map(fn($a) => ['id' => $a->id, 'name' => $a->name])"
                                                    :placeholder="$payment->agent?->name ?? 'Select an Agent'"
                                                    :selectedName="$payment->agent?->name" label="Agent" />
                                            </div>
                                            @else
                                                <input type="hidden" name="agent_id" value="{{ auth()->user()->agent->id }}">
                                            @endunlessrole

                                            @if($payment->status === 'initiate')
                                                <div class="mb-4">
                                                    <label class="pl-modal-label">Client</label>
                                                    <div class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-500 bg-gray-100">
                                                        {{ $payment->client ? $payment->client->full_name . ' - ' . $payment->client->phone_number : 'N/A' }}
                                                    </div>
                                                    <input type="hidden" name="client_id" value="{{ $payment->client?->id }}">
                                                </div>
                                            @else
                                                <div class="mb-4">
                                                    <x-searchable-dropdown name="client_id"
                                                        :items="$clients->map(fn($c) => ['id' => $c->id, 'name' => $c->full_name . ' - ' . $c->phone])"
                                                        :placeholder="$payment->client?->full_name ?? 'Select a Client'"
                                                        :selectedName="$payment->client?->full_name" label="Client" />
                                                    <input type="hidden" name="client_id_fallback" value="{{ $payment->client?->id }}">
                                                </div>

                                                <label for="phone_{{ $payment->client_id }}" class="pl-modal-label">Phone Number</label>
                                                <div class="flex gap-3 mb-4">
                                                    <div class="w-2/5">
                                                        <x-searchable-dropdown name="dial_code"
                                                            :items="\App\Models\Country::all()->map(fn($country) => ['id' => $country->dialing_code, 'name' => $country->dialing_code . ' ' . $country->name])"
                                                            :placeholder="$payment->client?->country_code ?? 'Select Dial Code'"
                                                            :selectedName="$payment->client?->country_code" :showAllOnOpen="true" />
                                                        <input type="hidden" name="dial_code_fallback" value="{{ $payment->client?->country_code }}">
                                                    </div>
                                                    <div class="w-3/5">
                                                        <input type="text" name="phone" id="phone_{{ $payment->client_id }}" value="{{ $payment->client?->phone }}"
                                                            placeholder="Phone Number" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-gray-900 bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none" required />
                                                    </div>
                                                </div>
                                            @endif

                                            @if($payment->paymentItems?->isNotEmpty())
                                                <div class="pl-advance-box">
                                                    <svg class="pl-advance-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    <div>
                                                        <p class="pl-advance-title">Advance Payment Detected</p>
                                                        <p class="pl-advance-text">
                                                            Amount modification is not available here. Please visit the
                                                            <span class="pl-advance-link">payment details page</span> to update the amount.
                                                        </p>
                                                    </div>
                                                </div>
                                            @else
                                                <div class="mb-4">
                                                    <label for="amount" class="pl-modal-label">Amount</label>
                                                    <input type="text" name="amount" id="amount" value="{{ $payment->amount }}"
                                                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none {{ $payment->status === 'initiate' ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : 'bg-white text-gray-900' }}"
                                                        {{ $payment->status === 'initiate' ? 'disabled' : '' }}>
                                                </div>
                                            @endif

                                            @if ($payment->availablePaymentMethodGroups?->isNotEmpty())
                                                <div class="mb-4">
                                                    <label class="pl-modal-label mb-2">Payment Method</label>
                                                    <div class="flex flex-wrap gap-x-6 gap-y-2">
                                                        @foreach ($paymentMethodChose as $chose)
                                                        <label for="edit_pmg_{{ $payment->id }}_{{ $chose->paymentMethodGroup->id }}" class="flex items-center gap-2 text-sm pl-amount-label cursor-pointer">
                                                            <input type="checkbox" name="payment_method_groups[]" value="{{ $chose->paymentMethodGroup->id }}"
                                                                id="edit_pmg_{{ $payment->id }}_{{ $chose->paymentMethodGroup->id }}"
                                                                {{ $payment->availablePaymentMethodGroups->contains('id', $chose->paymentMethodGroup->id) ? 'checked' : '' }}
                                                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                            {{ $chose->paymentMethodGroup->name }}
                                                        </label>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @else
                                                <div class="mb-4" x-data="{
                                                    selectedGateway: '{{ $payment->payment_gateway ?? '' }}',
                                                    gatewaysWithMethods: @js($paymentGateways->filter(fn($g) => $g->methods->isNotEmpty())->pluck('name')->toArray()),
                                                    hasMethod() { return this.gatewaysWithMethods.includes(this.selectedGateway); }
                                                }">
                                                    <div :class="hasMethod() ? 'grid grid-cols-1 md:grid-cols-2 gap-6' : 'block'">
                                                        <div>
                                                            <label class="pl-modal-label">Payment Gateway</label>
                                                            <select name="payment_gateway" class="pl-modal-select mt-1" x-model="selectedGateway">
                                                                <option value="" disabled>Select Payment Gateway</option>
                                                                @foreach ($paymentGateways as $gw)
                                                                <option value="{{ $gw->name }}" @if ($payment->payment_gateway === $gw->name) selected @endif>{{ $gw->name }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        @foreach($paymentGateways as $gw)
                                                        @if($gw->methods->isNotEmpty())
                                                        <template x-if="selectedGateway === '{{ $gw->name }}'">
                                                            <div x-cloak>
                                                                <label class="pl-modal-label">{{ $gw->name }} Methods</label>
                                                                <select name="payment_method_id" class="pl-modal-select mt-1">
                                                                    <option value="" disabled>Select Method</option>
                                                                    @foreach ($gw->methods as $m)
                                                                    <option value="{{ $m->id }}" @if ($payment->payment_method_id === $m->id) selected @endif>{{ $m->english_name }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                        </template>
                                                        @endif
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif

                                            <div class="mb-4">
                                                <label class="pl-modal-label mb-1.5">Invoice Language</label>
                                                <div x-data="{ language: '{{ $payment->language ?? 'EN' }}' }" class="pl-lang-toggle">
                                                    <input type="hidden" name="language" :value="language">
                                                    <button type="button" @click="language = 'EN'"
                                                        :class="language === 'EN' ? 'pl-lang-active' : 'pl-lang-inactive'" class="pl-lang-btn">
                                                        <span class="pl-lang-code">GB</span> English</button>
                                                    <button type="button" @click="language = 'ARB'"
                                                        :class="language === 'ARB' ? 'pl-lang-active' : 'pl-lang-inactive'" class="pl-lang-btn">
                                                        <span class="pl-lang-code">SA</span> العربية</button>
                                                </div>
                                            </div>

                                            <div class="flex justify-between">
                                                <button type="button" @click="editPaymentLink = false" class="pl-btn-cancel">Cancel</button>
                                                <button type="submit" class="pl-btn-primary">Update</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
                @empty
                <div class="pl-empty">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <p class="pl-empty-title">No payment links found</p>
                    <p class="pl-empty-text">Create a new payment link to get started</p>
                </div>
                @endforelse
            </div>

            @if ($payments->hasPages())
                <div class="mt-4">
                    {{ $payments->links() }}
                </div>
            @endif
        </div>

        <div x-show="activeTab === 'imported'" x-cloak class="main-tab-content">
            <div class="pl-toolbar md:flex-nowrap">
                <x-search
                    :action="route('payment.link.index')"
                    searchParam="search"
                    placeholder="Quick search for payments" />

                <div class="shrink-0 flex items-center gap-2">
                    <span class="pl-date-label">Select a date:</span>
                    <input type="text" id="imported-date-range" class="pl-date-input" placeholder="Choose date range">
                </div>
            </div>

            <form id="imported-date-filter-form" action="{{ route('payment.link.index') }}" method="GET" class="hidden">
                <input type="hidden" name="search" value="{{ request('search') }}" />
                <input type="hidden" name="filter[date_from]" id="imported_date_from" value="{{ data_get($filters, 'date_from') }}">
                <input type="hidden" name="filter[date_to]" id="imported_date_to" value="{{ data_get($filters, 'date_to') }}">
            </form>

            <div class="imp-col-header">
                <div class="col-span-4">Payment Info</div>
                <div class="col-span-2">Method & Agent</div>
                <div class="col-span-3">Amount</div>
                <div class="col-span-3 text-center">Action</div>
            </div>

            <div class="pl-table">
                @forelse ($importedPayments as $index => $imported)
                <div class="pl-row {{ $index % 2 === 0 ? 'pl-row-even' : 'pl-row-odd' }}">
                    <div class="imp-row-grid">
                        <div class="md:col-span-1 xl:col-span-4 space-y-1.5">
                            <div class="flex items-center gap-2">
                                <span class="imp-id">#IMP-{{ $imported->id }}</span>
                            </div>
                            <div class="pl-meta-grid">
                                <div class="pl-meta-item">
                                    <svg class="pl-meta-icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                    <div>
                                        <span class="pl-meta-label">Created</span>
                                        <span class="pl-meta-value">{{ $imported->created_at?->format('d M Y H:i') ?? 'N/A' }}</span>
                                    </div>
                                </div>
                                <div class="pl-meta-item">
                                    <svg class="pl-meta-icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                    <div>
                                        <span class="pl-meta-label">Paid</span>
                                        <span class="pl-meta-value">{{ $imported->payment_date ? \Carbon\Carbon::parse($imported->payment_date)->format('d M Y H:i') : 'N/A' }}</span>
                                    </div>
                                </div>
                                @if ($ref = $imported->myFatoorahPayment?->invoice_ref ?? $imported->tapPayment?->tap_id ?? $imported->payment_reference)
                                    <div class="pl-meta-item">
                                        <svg class="pl-meta-icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                                        <div>
                                            <span class="pl-meta-label">Reference</span>
                                            <span class="pl-meta-value">{{ $ref }}</span>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="md:col-span-1 xl:col-span-2 space-y-2">
                            <div>
                                @if ($imported->paymentMethod)
                                    @php
                                    $importedMethodCss = match($imported->paymentMethod->english_name) {
                                        'KNET' => 'pl-method-knet',
                                        'VISA/MASTER', 'Visa/MasterCard' => 'pl-method-visa',
                                        'Apple Pay' => 'pl-method-apple',
                                        'Samsung Pay' => 'pl-method-samsung',
                                        default => 'pl-method-default',
                                    };
                                    @endphp
                                    <div class="pl-method-tag {{ $importedMethodCss }}">
                                        <span>{{ $imported->payment_gateway ? $imported->payment_gateway . ' - ' : '' }}{{ $imported->paymentMethod->english_name }}</span>
                                    </div>
                                @elseif ($imported->payment_gateway)
                                    <div class="pl-method-tag pl-method-tag-orange">
                                        <span>{{ $imported->payment_gateway }}</span>
                                    </div>
                                @endif
                            </div>
                            <div>
                                <div class="pl-section-label">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="12" cy="7" r="4"></circle>
                                    </svg>
                                    <span class="pl-label">Agent</span>
                                </div>
                                <span class="pl-name">{{ $imported->agent?->name ?? 'Not Set' }}</span>
                            </div>
                        </div>

                        <div class="md:col-span-1 xl:col-span-3">
                            <div class="pl-amount-grid">
                                <div class="pl-amount-label">Net Amount:</div>
                                <div class="pl-amount-value">{{ number_format($imported->amount, 3) }} {{ $imported->currency ?? 'KWD' }}</div>
                                <div class="pl-amount-label">Gateway Fee:</div>
                                <div class="pl-amount-value">{{ number_format($imported->service_charge ?? 0, 3) }} {{ $imported->currency ?? 'KWD' }}</div>
                                <div class="pl-amount-divider"></div>
                                <div class="pl-amount-total-label">Total:</div>
                                <div class="pl-amount-total">
                                    {{ number_format($imported->amount + ($imported->service_charge ?? 0), 3) }}
                                    <span class="pl-currency">{{ $imported->currency ?? 'KWD' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="md:col-span-1 xl:col-span-3 pl-actions">
                            @if (!$imported->client_id)
                            <div x-data="{ showAssign: false, selectedAgentId: '{{ $imported->agent_id ?? '' }}' }"
                                @dropdown-select.window="if ($event.detail.name === 'agent_id_{{ $imported->id }}') selectedAgentId = $event.detail.value">
                                <button @click="showAssign = true" class="imp-assign-btn">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line>
                                    </svg>
                                    {{ $imported->agent_id ? 'Assign Client' : 'Assign' }}
                                </button>

                                <template x-teleport="body">
                                    <div x-cloak x-show="showAssign" class="pl-modal-overlay">
                                        <div class="pl-modal" @click.outside="showAssign = false">
                                            <div class="flex items-center justify-between mb-4">
                                                <div>
                                                    <h2 class="pl-modal-title" style="font-size:1.125rem;">{{ $imported->agent_id ? 'Assign Client' : 'Assign Agent & Client' }}</h2>
                                                    <p class="pl-modal-subtitle" style="font-style:normal;">#IMP-{{ $imported->id }} &mdash; {{ number_format($imported->amount + ($imported->service_charge ?? 0), 3) }} {{ $imported->currency ?? 'KWD' }}</p>
                                                </div>
                                                <button @click="showAssign = false" class="pl-modal-close">&times;</button>
                                            </div>
                                            <form action="{{ route('payment.link.import.assign-client', $imported->id) }}" method="POST">
                                                @csrf
                                                @if (!$imported->agent_id)
                                                <div class="mb-4">
                                                    <x-searchable-dropdown name="agent_id_{{ $imported->id }}"
                                                        :items="$agents->map(fn($a) => ['id' => $a->id, 'name' => $a->name])"
                                                        :placeholder="'Search for agent'"
                                                        label="Agent" />
                                                    <input type="hidden" name="agent_id" :value="selectedAgentId">
                                                </div>
                                                @endif
                                                <div class="mb-4">
                                                    <x-ajax-searchable-dropdown
                                                        name="client_id"
                                                        :ajaxUrl="route('clients.ajax.search')"
                                                        :dataId="$imported->agent_id ?? ''"
                                                        :watchDropdown="'agent_id_' . $imported->id"
                                                        :placeholder="'Search for client'"
                                                        displayColumn="full_name"
                                                        :columns="['full_name', 'phone_number']"
                                                        label="Client" />
                                                </div>
                                                <div class="mb-4 pl-info-box">
                                                    <p class="pl-info-text">
                                                        @if (!$imported->agent_id)
                                                            Assigning an agent and client will generate a voucher number, create a credit entry and journal entries (COA) using the paid date as transaction date.
                                                        @else
                                                            Assigning a client will generate a voucher number, create a credit entry and journal entries (COA) using the paid date as transaction date.
                                                        @endif
                                                    </p>
                                                </div>
                                                <div class="flex justify-between">
                                                    <button type="button" @click="showAssign = false" class="pl-btn-cancel">Cancel</button>
                                                    <button type="submit" class="pl-btn-primary">Assign</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            @else
                            <span class="pl-assigned-badge">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                                Assigned
                            </span>
                            @endif
                        </div>
                    </div>
                </div>
                @empty
                <div class="pl-empty">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    <p class="pl-empty-title">{{ request('search') ? 'No results found' : 'No imported payments yet' }}</p>
                    <p class="pl-empty-text">{{ request('search') ? 'Try a different search term' : 'Use the import button to upload a gateway file' }}</p>
                </div>
                @endforelse
            </div>

            @if ($importedPayments->hasPages())
                <div class="mt-4">
                    {{ $importedPayments->links() }}
                </div>
            @endif
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const dateFromInput = document.getElementById('date_from');
            const dateToInput = document.getElementById('date_to');
            const dateFilterForm = document.getElementById('date-filter-form');
            const dateFrom = dateFromInput ? dateFromInput.value : '';
            const dateTo = dateToInput ? dateToInput.value : '';

            const dateRangeInput = document.getElementById('payment-date-range');
            if (dateRangeInput) {
                flatpickr("#payment-date-range", {
                    mode: "range",
                    dateFormat: "Y-m-d",
                    defaultDate: (dateFrom && dateTo) ? [dateFrom, dateTo] : null,
                    onChange: function(selectedDates, dateStr, instance) {
                        if (selectedDates.length === 2) {
                            dateFromInput.value = instance.formatDate(selectedDates[0], 'Y-m-d');
                            dateToInput.value = instance.formatDate(selectedDates[1], 'Y-m-d');
                            setTimeout(() => { dateFilterForm.submit(); }, 100);
                        } else if (selectedDates.length === 0) {
                            dateFromInput.value = '';
                            dateToInput.value = '';
                            setTimeout(() => { dateFilterForm.submit(); }, 100);
                        }
                    }
                });
            }

            const importedDateFrom = document.getElementById('imported_date_from');
            const importedDateTo = document.getElementById('imported_date_to');
            const importedDateForm = document.getElementById('imported-date-filter-form');
            const importedDateRange = document.getElementById('imported-date-range');
            if (importedDateRange) {
                flatpickr("#imported-date-range", {
                    mode: "range",
                    dateFormat: "Y-m-d",
                    defaultDate: (dateFrom && dateTo) ? [dateFrom, dateTo] : null,
                    onChange: function(selectedDates, dateStr, instance) {
                        if (selectedDates.length === 2) {
                            importedDateFrom.value = instance.formatDate(selectedDates[0], 'Y-m-d');
                            importedDateTo.value = instance.formatDate(selectedDates[1], 'Y-m-d');
                            setTimeout(() => { importedDateForm.submit(); }, 100);
                        } else if (selectedDates.length === 0) {
                            importedDateFrom.value = '';
                            importedDateTo.value = '';
                            setTimeout(() => { importedDateForm.submit(); }, 100);
                        }
                    }
                });
            }
        });

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                const toast = document.createElement('div');
                toast.className = 'pl-toast alert alert-success';
                toast.innerHTML = `
                    <span class="mr-4">Link copied to clipboard!</span>
                    <button type="button" class="text-white font-bold" onclick="this.parentElement.remove()">&times;</button>
                `;
                document.body.appendChild(toast);
                setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => { toast.remove(); }, 300); }, 2500);
            }).catch(function(err) {
                console.error('Copy failed:', err);
                alert('Could not copy. Please try again.');
            });
        }
        document.getElementById('export-payment-links-btn')?.addEventListener('click', function() {
            const params = new URLSearchParams();
            const filters = @json($filters ?? []);

            if (filters.client_id) params.set('client_id', filters.client_id);
            if (filters.agent_id) params.set('agent_id', filters.agent_id);
            if (filters.created_by) params.set('created_by', filters.created_by);
            if (filters.payment_gateway) params.set('payment_gateway', filters.payment_gateway);
            if (filters.status) params.set('status', filters.status);
            if (filters.date_from) params.set('date_from', filters.date_from);
            if (filters.date_to) params.set('date_to', filters.date_to);
            if (filters.payment_method_id) params.set('payment_method_id', filters.payment_method_id);

            const search = new URLSearchParams(window.location.search).get('search');
            if (search) params.set('search', search);

            const url = "{{ route('payment.link.export') }}" + (params.toString() ? '?' + params.toString() : '');
            window.location.href = url;
        });
    </script>
</x-app-layout>