@push('styles')
    @vite(['resources/css/client/show.css'])
@endpush
<x-app-layout>
    <div class="client-show-main" x-data="{ editClientModal: false }">
        {{-- Breadcrumb --}}
        <nav class="client-show-breadcrumb">
            <a href="{{ route('clients.index') }}" class="client-show-breadcrumb-link">Clients</a>
            <span class="client-show-breadcrumb-separator">/</span>
            <span class="client-show-breadcrumb-current">{{ $client->full_name }}</span>
        </nav>

        {{-- Page Header --}}
        <div class="client-show-header">
            <div class="client-show-header-left">
                <div class="client-show-avatar">
                    {{ strtoupper(substr($client->first_name ?? $client->name, 0, 1)) }}{{ strtoupper(substr($client->last_name ?? '', 0, 1)) }}
                </div>
                <div>
                    <h1 class="client-show-name">{{ $client->full_name }}</h1>
                    <div class="client-show-status-row">
                        @php
                            $statusStyles = [
                                'active' => 'bg-green-100 text-green-700',
                                'inactive' => 'bg-gray-100 text-gray-600',
                                'suspended' => 'bg-red-100 text-red-700',
                            ];
                        @endphp
                        <span class="client-show-status-badge {{ $statusStyles[$client->status] ?? 'bg-gray-100 text-gray-600' }}">
                            {{ ucfirst($client->status) }}
                        </span>
                        @if($client->civil_no)
                        <span class="client-show-civil-no">Civil: {{ $client->civil_no }}</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="client-show-header-actions">
                <a href="{{ route('clients.credits', $client->id) }}" target="_blank"
                    class="client-show-btn-credit-ledger">
                    <svg class="client-show-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Credit Ledger
                </a>
                <button type="button" @click="editClientModal = true"
                    class="client-show-btn-edit-client">
                    <svg class="client-show-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Edit Client
                </button>
            </div>
        </div>

        {{-- Stats Overview Cards --}}
        <div class="client-show-stats-grid">
            {{-- Credit Balance --}}
            <div class="stat-card client-show-stat-card">
                <div class="client-show-stat-header">
                    <span class="client-show-stat-label">Credit Balance</span>
                    <div class="client-show-stat-icon bg-blue-50">
                        <svg class="client-show-icon-sm text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                    </div>
                </div>
                <p class="client-show-stat-value {{ $balanceCredit >= 0 ? 'text-blue-600' : 'text-red-600' }}">{{ number_format($balanceCredit, 2) }}</p>
                <p class="client-show-stat-currency">KWD</p>
            </div>

            {{-- Paid --}}
            <div class="stat-card client-show-stat-card">
                <div class="client-show-stat-header">
                    <span class="client-show-stat-label">Total Paid</span>
                    <div class="client-show-stat-icon bg-green-50">
                        <svg class="client-show-icon-sm text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <p class="client-show-stat-value text-green-600">{{ number_format($paid, 2) }}</p>
                <p class="client-show-stat-currency">KWD</p>
            </div>

            {{-- Unpaid --}}
            <div class="stat-card client-show-stat-card">
                <div class="client-show-stat-header">
                    <span class="client-show-stat-label">Unpaid</span>
                    <div class="client-show-stat-icon bg-red-50">
                        <svg class="client-show-icon-sm text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <p class="client-show-stat-value text-red-600">{{ number_format($unpaid, 2) }}</p>
                <p class="client-show-stat-currency">KWD</p>
            </div>

            {{-- Tasks & Invoices --}}
            <div class="stat-card client-show-stat-card">
                <div class="client-show-stat-header">
                    <span class="client-show-stat-label">Activity</span>
                    <div class="client-show-stat-icon bg-purple-50">
                        <svg class="client-show-icon-sm text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                </div>
                <div class="client-show-stat-activity">
                    <div>
                        <p class="client-show-stat-value text-gray-900" id="taskCountStat">{{ $taskCount }}</p>
                        <p class="client-show-stat-sub-label">Tasks</p>
                    </div>
                    <div class="client-show-stat-divider"></div>
                    <div>
                        <p class="client-show-stat-value text-gray-900" id="invoiceCountStat">{{ $invoiceCount }}</p>
                        <p class="client-show-stat-sub-label">Invoices</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Main Content Grid --}}
        <div class="client-show-content-grid">
            {{-- Left: Client Information --}}
            <div class="client-show-sidebar">
                {{-- Contact Details Card --}}
                <div class="client-show-card">
                    <div class="client-show-card-header">
                        <h3 class="client-show-card-title">Contact Details</h3>
                    </div>
                    <div class="client-show-card-body">
                        <div class="client-show-contact-item">
                            <div class="client-show-contact-icon">
                                <svg class="client-show-icon-sm text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="client-show-field-label">Email</p>
                                <p class="client-show-field-value-truncate">{{ $client->email ?: 'N/A' }}</p>
                            </div>
                        </div>
                        <div class="client-show-contact-item">
                            <div class="client-show-contact-icon">
                                <svg class="client-show-icon-sm text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="client-show-field-label">Phone</p>
                                <p class="client-show-field-value">{{ $client->country_code ? $client->country_code . ' ' : '' }}{{ $client->phone ?: 'N/A' }}</p>
                            </div>
                        </div>
                        <div class="client-show-contact-item">
                            <div class="client-show-contact-icon">
                                <svg class="client-show-icon-sm text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="client-show-field-label">Address</p>
                                <p class="client-show-field-value">{{ $client->address ?: 'N/A' }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Agent Information Card --}}
                <div class="client-show-card">
                    <div class="client-show-card-header">
                        <h3 class="client-show-card-title">Agent Information</h3>
                    </div>
                    <div class="client-show-card-body">
                        <div>
                            <p class="client-show-owner-label">Owner Agent</p>
                            <div class="client-show-owner-row">
                                <div class="client-show-owner-avatar">
                                    <span class="client-show-owner-avatar-text">{{ $client->agent ? strtoupper(substr($client->agent->name, 0, 1)) : '?' }}</span>
                                </div>
                                <span class="client-show-owner-name">{{ $client->agent ? $client->agent->name : 'No owner' }}</span>
                            </div>
                        </div>
                        <div class="client-show-agent-separator">
                            <p class="client-show-agent-label">Assigned Agents</p>
                            @if($client->agents->isEmpty())
                                <span class="client-show-no-agents">No agents assigned</span>
                            @else
                                <div class="client-show-agent-list">
                                    @foreach($client->agents as $assignedAgent)
                                    <span class="client-show-agent-tag">
                                        <span class="w-1.5 h-1.5 rounded-full {{ ['bg-emerald-400','bg-sky-400','bg-amber-400','bg-rose-400','bg-violet-400'][$loop->index % 5] }}"></span>
                                        {{ $assignedAgent->name }}
                                    </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Credit Overview Card --}}
                <div class="client-show-card">
                    <div class="client-show-card-header-flex">
                        <h3 class="client-show-card-title">Credit Overview</h3>
                        <div class="client-show-header-actions">
                            @if ($balanceCredit > 0)
                            <div x-data="{ clientCreditRefund: false }">
                                <button @click="clientCreditRefund = true"
                                    class="client-show-credit-manage">
                                    Manage
                                </button>
                                {{-- Credit Assign/Refund Modal --}}
                                <div class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-75 z-10"
                                    x-show="clientCreditRefund" x-cloak>
                                    <div @click.away="clientCreditRefund = false"
                                        class="client-show-modal-content-sm"
                                        x-data="{ activeTab: 'assign' }">
                                        <div class="client-show-modal-header-row">
                                            <div>
                                                <h2 class="client-show-modal-title-sm">Client Credit</h2>
                                                <p class="client-show-modal-subtitle">Manage client credit in one place</p>
                                            </div>
                                            <button @click="clientCreditRefund = false"
                                                class="client-show-modal-close-inline">&times;</button>
                                        </div>
                                        <div class="client-show-tab-bar">
                                            <button class="client-show-tab-btn"
                                                :class="activeTab === 'assign' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-gray-700'"
                                                @click="activeTab = 'assign'">Assign</button>
                                            <button class="client-show-tab-btn"
                                                :class="activeTab === 'refund' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-gray-700'"
                                                @click="activeTab = 'refund'">Refund</button>
                                        </div>
                                        {{-- Assign Tab --}}
                                        <div x-show="activeTab === 'assign'">
                                            <div>
                                                <label class="client-show-form-label-sm">Assigned Agent</label>
                                                <select name="agent" id="agent" x-model="agent"
                                                    class="client-show-form-select-full"
                                                    required>
                                                    <option value="" selected disabled hidden>Select Assigned Agent</option>
                                                    <option value="Soud Shoja">Soud Shoja</option>
                                                </select>
                                            </div>
                                            <div class="mt-4">
                                                <label class="client-show-form-label-sm">Amount to Assign</label>
                                                <input type="text" name="amount" min="0" step="0.01" max="{{ $balanceCredit }}"
                                                    placeholder="Enter assign amount"
                                                    class="client-show-form-input-credit">
                                            </div>
                                            <div class="client-show-modal-actions">
                                                <button type="button" @click="clientCreditRefund = false"
                                                    class="client-show-btn-cancel">Cancel</button>
                                                <button type="submit"
                                                    class="client-show-btn-primary-sm"
                                                    {{ $balanceCredit == 0 ? 'disabled' : '' }}>Assign</button>
                                            </div>
                                        </div>
                                        {{-- Refund Tab --}}
                                        <div x-show="activeTab === 'refund'">
                                            <form action="{{ route('clients.refund', $client->id) }}" method="POST" class="space-y-4">
                                                @csrf
                                                @if ($agents->count() > 1)
                                                <select name="agent_id" id="agent_id"
                                                    class="client-show-form-select-credit">
                                                    @foreach ($agents as $agent)
                                                    <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                                                    @endforeach
                                                </select>
                                                @else
                                                <input type="hidden" name="agent_id" value="{{ $agents[0]->id }}">
                                                @endif
                                                <div>
                                                    <label class="client-show-form-label-sm">Refund to Client</label>
                                                    <input type="text" name="amount" min="0" step="0.01" max="{{ $balanceCredit }}"
                                                        placeholder="Enter refund amount"
                                                        class="client-show-form-input-credit">
                                                </div>
                                                <div class="client-show-modal-actions-refund">
                                                    <button type="button" @click="clientCreditRefund = false"
                                                        class="client-show-btn-cancel">Cancel</button>
                                                    <button type="submit"
                                                        class="client-show-btn-primary-sm"
                                                        {{ $balanceCredit == 0 ? 'disabled' : '' }}>Refund</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endif
                            <a href="{{ route('clients.credits', $client->id) }}" target="_blank"
                                class="client-show-credit-view-all">View All</a>
                        </div>
                    </div>
                    <div class="client-show-card-body-p5">
                        {{-- Credit Summary Row --}}
                        <div class="client-show-credit-summary-grid">
                            <div class="client-show-credit-summary-item bg-green-50">
                                <p class="client-show-credit-summary-label text-green-600">In</p>
                                <p class="client-show-credit-summary-value text-green-700">{{ number_format($creditTotalIn, 2) }}</p>
                            </div>
                            <div class="client-show-credit-summary-item bg-red-50">
                                <p class="client-show-credit-summary-label text-red-600">Out</p>
                                <p class="client-show-credit-summary-value text-red-700">{{ number_format($creditTotalOut, 2) }}</p>
                            </div>
                            <div class="client-show-credit-summary-item {{ $balanceCredit >= 0 ? 'bg-blue-50' : 'bg-red-50' }}">
                                <p class="client-show-credit-summary-label {{ $balanceCredit >= 0 ? 'text-blue-600' : 'text-red-600' }}">Net</p>
                                <p class="client-show-credit-summary-value {{ $balanceCredit >= 0 ? 'text-blue-700' : 'text-red-700' }}">{{ number_format($balanceCredit, 2) }}</p>
                            </div>
                        </div>

                        {{-- Recent Transactions --}}
                        @if($recentCredits->count() > 0)
                        <div class="space-y-2">
                            <p class="client-show-credit-transactions-title">Recent Transactions</p>
                            @foreach($recentCredits as $credit)
                            <div class="client-show-credit-tx-row {{ !$loop->last ? 'border-b border-gray-50' : '' }}">
                                <div class="client-show-credit-tx-left">
                                    @php
                                        $typeColors = [
                                            'Invoice' => 'bg-red-100 text-red-700',
                                            'Topup' => 'bg-green-100 text-green-700',
                                            'Refund' => 'bg-sky-100 text-sky-700',
                                            'Invoice Refund' => 'bg-amber-100 text-amber-700',
                                        ];
                                        $typeColor = $typeColors[$credit->type] ?? 'bg-gray-100 text-gray-700';
                                    @endphp
                                    <span class="client-show-credit-type-badge {{ $typeColor }}">
                                        {{ $credit->type }}
                                    </span>
                                    <span class="client-show-credit-tx-desc" title="{{ $credit->description }}">
                                        {{ Str::limit($credit->description, 25) }}
                                    </span>
                                </div>
                                <span class="client-show-credit-tx-amount {{ $credit->amount >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $credit->amount >= 0 ? '+' : '' }}{{ number_format($credit->amount, 2) }}
                                </span>
                            </div>
                            @endforeach
                        </div>
                        @else
                        <p class="client-show-credit-empty">No credit transactions yet</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Right: Tasks & Invoices --}}
            <div class="client-show-main-col">
                {{-- Tasks List --}}
                <div class="client-show-card">
                    <div class="client-show-card-header-flex">
                        <div class="client-show-section-title-row">
                            <h3 class="client-show-card-title">Tasks</h3>
                            <span id="taskCount" class="client-show-count-badge">{{ $taskCount }}</span>
                        </div>
                    </div>
                    <div id="tasksContainer" class="overflow-x-auto">
                        <div class="client-show-loading">
                            <div class="client-show-spinner"></div>
                            <p class="client-show-loading-text">Loading tasks...</p>
                        </div>
                    </div>
                </div>

                {{-- Invoices List --}}
                <div class="client-show-card">
                    <div class="client-show-card-header-flex">
                        <div class="client-show-section-title-row">
                            <h3 class="client-show-card-title">Invoices</h3>
                            <span id="invoiceCount" class="client-show-count-badge">{{ $invoiceCount }}</span>
                        </div>
                    </div>
                    <div id="invoicesContainer" class="overflow-x-auto">
                        <div class="client-show-loading">
                            <div class="client-show-spinner"></div>
                            <p class="client-show-loading-text">Loading invoices...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Payment Links Section (Full Width) --}}
        <div class="client-show-payments-section">
            <div class="client-show-card-header-flex">
                <div class="client-show-section-title-row">
                    <h3 class="client-show-card-title">Payment Links</h3>
                    <span id="paymentCount" class="client-show-count-badge">...</span>
                </div>
                <a href="{{ route('payment.link.create') }}"
                    class="client-show-btn-create-payment">
                    <svg class="client-show-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Create Payment Link
                </a>
            </div>
            <div id="paymentsContainer" class="overflow-x-auto">
                <div class="client-show-loading">
                    <div class="client-show-spinner"></div>
                    <p class="client-show-loading-text">Loading payment links...</p>
                </div>
            </div>
        </div>

        {{-- Edit Client Modal --}}
        <div x-cloak x-show="editClientModal" class="client-show-modal-overlay">
            <div @click.away="editClientModal = false" class="client-show-modal-content-lg">
                <button @click="editClientModal = false" class="client-show-modal-close">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="client-show-icon-close">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
                <h2 class="client-show-modal-title">Edit Client Details</h2>

                <form action="{{ route('clients.update', $client->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="client-show-form-grid">
                        <div class="client-show-form-col">
                            <div>
                                <label for="first_name" class="client-show-form-label">First Name</label>
                                <input type="text" name="first_name" id="first_name" value="{{ $client->first_name }}"
                                    class="client-show-form-input"
                                    placeholder="First Name" required>
                            </div>
                            <div>
                                <label for="middle_name" class="client-show-form-label">Middle Name</label>
                                <input type="text" name="middle_name" id="middle_name" value="{{ $client->middle_name }}"
                                    class="client-show-form-input"
                                    placeholder="Middle Name">
                            </div>
                            <div>
                                <label for="last_name" class="client-show-form-label">Last Name</label>
                                <input type="text" name="last_name" id="last_name" value="{{ $client->last_name }}"
                                    class="client-show-form-input"
                                    placeholder="Last Name">
                            </div>
                            <div>
                                <label for="civil_no" class="client-show-form-label">Civil No</label>
                                <input type="text" name="civil_no" id="civil_no" value="{{ $client->civil_no }}"
                                    class="client-show-form-input"
                                    placeholder="Client Civil No">
                            </div>
                            <div>
                                <label for="email" class="client-show-form-label">Email</label>
                                <input type="email" name="email" id="email" value="{{ $client->email }}"
                                    class="client-show-form-input"
                                    placeholder="Client Email">
                            </div>
                        </div>

                        <div class="client-show-form-col">
                            <div>
                                <label for="country_code" class="client-show-form-label">Country Code</label>
                                <select name="country_code" id="country_code"
                                    class="client-show-form-input">
                                    @foreach ($countries as $country)
                                    <option value="{{ $country->dialing_code }}"
                                        {{ $client->country_code == $country->dialing_code ? 'selected' : '' }}>
                                        {{ $country->name }} ({{ $country->dialing_code }})
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="phone" class="client-show-form-label">Phone</label>
                                <input type="text" name="phone" id="phone" value="{{ $client->phone }}"
                                    class="client-show-form-input"
                                    placeholder="Client Phone">
                            </div>
                            <div>
                                <label for="address" class="client-show-form-label">Address</label>
                                <textarea name="address" id="address" rows="3"
                                    class="client-show-form-textarea"
                                    placeholder="Client Address">{{ $client->address }}</textarea>
                            </div>

                            @can('assignOwnerAgent', App\Models\Client::class)
                            <div>
                                <x-searchable-dropdown
                                    name="agent_id"
                                    :items="isset($agents) ? $agents->map(fn($a) => ['id' => $a->id, 'name' => $a->name]) : []"
                                    :selectedId="$client->agent_id"
                                    :placeholder="$client->agent ? $client->agent->name : 'Select Owner Agent'"
                                    :selectedName="$client->agent ? $client->agent->name : null"
                                    label="Client Owner (Agent)" />
                                <p class="client-show-form-hint">The agent who created/owns this client</p>
                            </div>
                            @endcan

                            <div>
                                <label for="status" class="client-show-form-label">Status</label>
                                <select name="status" id="status"
                                    class="client-show-form-input">
                                    <option value="active" {{ $client->status == 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="inactive" {{ $client->status == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                    <option value="suspended" {{ $client->status == 'suspended' ? 'selected' : '' }}>Suspended</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    @can('assignAgents', $client)
                    <div class="client-show-agent-manager-section">
                        <div class="w-full" x-data="agentManager({
                            selectedAgents: {{ $client->agents->pluck('id')->toJson() }},
                            availableAgents: {{ isset($agents) ? $agents->map(function($agent) { return ['id' => $agent->id, 'name' => $agent->name]; })->toJson() : $client->agents->map(function($agent) { return ['id' => $agent->id, 'name' => $agent->name]; })->toJson() }} })">
                            <label class="client-show-form-label-agents">Assigned Agents</label>

                            <div class="mb-4">
                                <div class="client-show-agent-chips-box">
                                    <template x-for="agentId in selectedAgents" :key="agentId">
                                        <div class="client-show-agent-chip">
                                            <span x-text="getAgentName(agentId)"></span>
                                            <button type="button" @click="removeAgent(agentId)" class="client-show-agent-chip-remove">
                                                <svg class="client-show-icon-xs" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </template>
                                    <div x-show="selectedAgents.length === 0" class="client-show-agent-empty-text">
                                        No agents assigned
                                    </div>
                                </div>
                            </div>

                            @if(isset($agents))
                            <div class="relative" x-data="{
                                open: false,
                                search: '',
                                openDropdown() { this.open = true; this.$nextTick(() => this.$refs.searchInput.focus()); },
                                closeDropdown() { this.open = false; this.search = ''; },
                                getFilteredAgents() {
                                    const available = getAvailableAgents();
                                    if (!this.search) return available;
                                    return available.filter(agent => agent.name.toLowerCase().includes(this.search.toLowerCase()));
                                }
                            }">
                                <button type="button" @click="openDropdown()"
                                    class="client-show-agent-dropdown-btn">
                                    <span class="client-show-agent-dropdown-label">Add Agent</span>
                                    <svg class="client-show-icon-sm text-gray-400 transition-transform" :class="{ 'rotate-180': open }" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                    </svg>
                                </button>
                                <div x-show="open" x-transition @click.away="closeDropdown()"
                                    class="client-show-agent-dropdown-panel">
                                    <div class="client-show-agent-search-wrap">
                                        <div class="relative">
                                            <input type="text" x-ref="searchInput" x-model="search" placeholder="Search agents..."
                                                class="client-show-agent-search-input"
                                                @keydown.escape="closeDropdown()" @click.stop>
                                            <div class="client-show-agent-search-icon-wrap">
                                                <svg class="client-show-icon-sm text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="client-show-agent-dropdown-scroll">
                                        <template x-for="agent in getFilteredAgents()" :key="agent.id">
                                            <button type="button" @click="addAgent(agent.id); closeDropdown()"
                                                class="client-show-agent-dropdown-item">
                                                <span x-text="agent.name"></span>
                                            </button>
                                        </template>
                                        <div x-show="getFilteredAgents().length === 0 && search !== ''" class="client-show-agent-dropdown-empty">
                                            No agents found matching "<span x-text="search"></span>"
                                        </div>
                                        <div x-show="getAvailableAgents().length === 0 && search === ''" class="client-show-agent-dropdown-empty">
                                            All agents are already assigned
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <template x-for="(agentId, index) in selectedAgents" :key="`agent-${agentId}-${index}`">
                                <input type="hidden" :name="`agent_ids[${index}]`" :value="agentId">
                            </template>
                            @else
                            <div class="client-show-agent-fallback">
                                Agent management not available on this page. Please use the client edit page to modify agents.
                            </div>
                            @endif
                        </div>
                    </div>
                    @endcan

                    <div class="client-show-form-footer">
                        <button type="submit" class="client-show-btn-primary">
                            Update Client
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const CLIENT_ID = {{ $client->id }};
        const CSRF_TOKEN = '{{ csrf_token() }}';
        const IS_ADMIN_OR_COMPANY = {{ (auth()->user()->role?->name === 'admin' || auth()->user()->role?->name === 'company') ? 'true' : 'false' }};
        const AGENTS_JSON = @json($agents->map(fn($a) => ['id' => $a->id, 'name' => $a->name]));

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                const toast = document.createElement('div');
                toast.className = 'fixed top-4 right-4 bg-green-600 text-white px-5 py-3 rounded-xl shadow-lg z-50 flex items-center gap-3';
                toast.innerHTML = `
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span class="font-medium text-sm">Link copied!</span>
                `;
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 3000);
            }).catch(() => alert('Could not copy. Please try again.'));
        }

        function agentManager(data) {
            return {
                selectedAgents: data.selectedAgents || [],
                availableAgents: data.availableAgents || [],
                addAgent(agentId) { if (!this.selectedAgents.includes(agentId)) this.selectedAgents.push(agentId); },
                removeAgent(agentId) { this.selectedAgents = this.selectedAgents.filter(id => id !== agentId); },
                getAgentName(agentId) { const a = this.availableAgents.find(a => a.id === agentId); return a ? a.name : 'Unknown'; },
                getAvailableAgents() { return this.availableAgents.filter(a => !this.selectedAgents.includes(a.id)); }
            }
        }

        function esc(str) {
            const d = document.createElement('div');
            d.textContent = str;
            return d.innerHTML;
        }

        function statusBadge(status, map) {
            const s = (status || '').toLowerCase();
            const cls = map[s] || 'bg-gray-50 text-gray-700 border-gray-200';
            return `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border ${cls}">${esc(status.charAt(0).toUpperCase() + status.slice(1).toLowerCase())}</span>`;
        }

        const taskStatusMap = {
            pending: 'bg-yellow-50 text-yellow-700 border-yellow-200',
            completed: 'bg-green-50 text-green-700 border-green-200',
            cancelled: 'bg-red-50 text-red-700 border-red-200',
        };

        const paymentStatusMap = {
            pending: 'bg-yellow-50 text-yellow-700 border-yellow-200',
            completed: 'bg-green-50 text-green-700 border-green-200',
            failed: 'bg-red-50 text-red-700 border-red-200',
            cancelled: 'bg-gray-100 text-gray-600 border-gray-200',
        };

        function renderEmpty(icon, msg) {
            return `<div class="py-12 text-center"><svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="${icon}" /></svg><p class="text-gray-400 text-sm">${msg}</p></div>`;
        }

        function renderCancellationPolicy(cp) {
            if (Array.isArray(cp) && cp.length > 0) {
                return cp.map(p => {
                    if (typeof p === 'object' && p !== null) {
                        const parts = Object.entries(p).map(([k,v]) => `${esc(k)}: ${esc(String(v))}`).join(', ');
                        return `<div class="text-xs bg-gray-50 rounded p-1.5"><span class="text-gray-600">${parts}</span></div>`;
                    }
                    return `<span class="text-xs text-gray-600">${esc(String(p))}</span>`;
                }).join('');
            }
            return `<span class="text-xs text-gray-600">${esc(cp ?? '')}</span>`;
        }

        function loadTasks() {
            fetch(`/clients/ajax/${CLIENT_ID}/tasks`)
                .then(r => r.json())
                .then(tasks => {
                    const container = document.getElementById('tasksContainer');
                    document.getElementById('taskCount').textContent = tasks.length;
                    document.getElementById('taskCountStat').textContent = tasks.length;

                    if (tasks.length === 0) {
                        container.innerHTML = renderEmpty('M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'No tasks found for this client');
                        return;
                    }

                    let html = `<div class="max-h-[400px] overflow-y-auto"><table class="w-full text-sm"><thead class="bg-gray-50 sticky top-0"><tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Cancellation Policy</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr></thead><tbody class="divide-y divide-gray-100">`;

                    tasks.forEach(t => {
                        html += `<tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3 text-gray-900 font-medium">${esc(t.reference ?? '')}-${esc(t.additional_info ?? '')} ${esc(t.venue ?? '')}</td>
                            <td class="px-5 py-3"><div class="space-y-1">${renderCancellationPolicy(t.cancellation_policy)}</div></td>
                            <td class="px-5 py-3">${statusBadge(t.status || 'pending', taskStatusMap)}</td>
                            <td class="px-5 py-3 text-center" x-data="{ open: false }">
                                <button @click="open = true" class="p-1.5 rounded-lg hover:bg-gray-100 transition-colors inline-flex">
                                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                </button>
                                <div x-cloak x-show="open" class="fixed inset-0 flex items-center justify-center bg-gray-800/60 backdrop-blur-sm z-10">
                                    <div @click.away="open = false" class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-md">
                                        <div class="flex items-center justify-between mb-5">
                                            <h3 class="text-lg font-bold text-gray-800">Edit Task</h3>
                                            <button @click="open = false" class="text-gray-400 hover:text-red-500 text-xl">&times;</button>
                                        </div>
                                        <form action="/tasks/update/${t.id}" method="POST" class="space-y-4">
                                            <input type="hidden" name="_token" value="${CSRF_TOKEN}">
                                            <input type="hidden" name="_method" value="PUT">
                                            <input type="hidden" name="id" value="${t.id}">
                                            <div><label class="block text-xs font-medium text-gray-600 mb-1">Reference</label>
                                            <input type="text" name="reference" value="${esc(t.reference ?? '')}" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></div>
                                            <div><label class="block text-xs font-medium text-gray-600 mb-1">Additional Info</label>
                                            <input type="text" name="additional_info" value="${esc(t.additional_info ?? '')}" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></div>
                                            <div><label class="block text-xs font-medium text-gray-600 mb-1">Venue</label>
                                            <input type="text" name="venue" value="${esc(t.venue ?? '')}" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></div>
                                            <div><label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                                            <select name="status" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                <option value="pending" ${(t.status||'').toLowerCase()==='pending'?'selected':''}>Pending</option>
                                                <option value="completed" ${(t.status||'').toLowerCase()==='completed'?'selected':''}>Completed</option>
                                                <option value="cancelled" ${(t.status||'').toLowerCase()==='cancelled'?'selected':''}>Cancelled</option>
                                            </select></div>
                                            <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg text-sm transition">Update Task</button>
                                        </form>
                                    </div>
                                </div>
                            </td></tr>`;
                    });
                    html += '</tbody></table></div>';
                    container.innerHTML = html;
                })
                .catch(() => {
                    document.getElementById('tasksContainer').innerHTML = '<div class="py-6 text-center text-red-400 text-sm">Failed to load tasks</div>';
                });
        }

        function loadInvoices() {
            fetch(`/clients/ajax/${CLIENT_ID}/invoices`)
                .then(r => r.json())
                .then(invoices => {
                    const container = document.getElementById('invoicesContainer');
                    document.getElementById('invoiceCount').textContent = invoices.length;
                    document.getElementById('invoiceCountStat').textContent = invoices.length;

                    if (invoices.length === 0) {
                        container.innerHTML = renderEmpty('M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'No invoices found for this client');
                        return;
                    }

                    const agentOpts = AGENTS_JSON.map(a => `<option value="${a.id}">${esc(a.name)}</option>`).join('');

                    let html = `<div class="max-h-[400px] overflow-y-auto"><table class="w-full text-sm"><thead class="bg-gray-50 sticky top-0"><tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Invoice #</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Agent</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr></thead><tbody class="divide-y divide-gray-100">`;

                    invoices.forEach(inv => {
                        const showUrl = inv.company_id ? `/invoice/${inv.company_id}/${encodeURIComponent(inv.invoice_number)}` : '#';
                        const isPaid = (inv.status || '').toLowerCase() === 'paid';
                        const statusHtml = isPaid
                            ? '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-200">Paid</span>'
                            : `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-red-50 text-red-700 border border-red-200">${esc((inv.status||'Unpaid').charAt(0).toUpperCase()+(inv.status||'unpaid').slice(1))}</span>`;
                        const amt = parseFloat(inv.amount || 0).toFixed(3);

                        const selectedAgentOpts = AGENTS_JSON.map(a =>
                            `<option value="${a.id}" ${a.id == inv.agent_id ? 'selected' : ''}>${esc(a.name)}</option>`
                        ).join('');

                        html += `<tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3"><a href="${showUrl}" class="text-blue-600 hover:text-blue-800 font-medium" target="_blank">${esc(inv.invoice_number)}</a></td>
                            <td class="px-5 py-3 font-medium text-gray-900">${amt} <span class="text-gray-400 text-xs">KWD</span></td>
                            <td class="px-5 py-3">${statusHtml}</td>
                            <td class="px-5 py-3 text-gray-600">${esc(inv.agent_name)}</td>
                            <td class="px-5 py-3 text-center" x-data="{ invoiceModal: false }">
                                <button @click="invoiceModal = true" class="p-1.5 rounded-lg hover:bg-gray-100 transition-colors inline-flex">
                                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                </button>
                                <div x-cloak x-show="invoiceModal" class="fixed inset-0 flex items-center justify-center bg-gray-800/60 backdrop-blur-sm z-10">
                                    <div @click.away="invoiceModal = false" class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-md">
                                        <div class="flex items-center justify-between mb-5">
                                            <h3 class="text-lg font-bold text-gray-800">Edit Invoice</h3>
                                            <button @click="invoiceModal = false" class="text-gray-400 hover:text-red-500 text-xl">&times;</button>
                                        </div>
                                        <form action="/invoice/${inv.id}" method="POST" class="space-y-4">
                                            <input type="hidden" name="_token" value="${CSRF_TOKEN}">
                                            <input type="hidden" name="_method" value="PUT">
                                            <input type="hidden" name="id" value="${inv.id}">
                                            <div><label class="block text-xs font-medium text-gray-600 mb-1">Amount</label>
                                            <input type="text" name="amount" value="${inv.amount}" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></div>
                                            <div><label class="block text-xs font-medium text-gray-600 mb-1">Agent</label>
                                            <select name="agent_id" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">${selectedAgentOpts}</select></div>
                                            <div><label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                                            <select name="status" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                <option value="paid" ${(inv.status||'').toLowerCase()==='paid'?'selected':''}>Paid</option>
                                                <option value="partial" ${(inv.status||'').toLowerCase()==='partial'?'selected':''}>Partial</option>
                                                <option value="unpaid" ${(inv.status||'').toLowerCase()==='unpaid'?'selected':''}>Unpaid</option>
                                            </select></div>
                                            <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg text-sm transition">Update Invoice</button>
                                        </form>
                                    </div>
                                </div>
                            </td></tr>`;
                    });
                    html += '</tbody></table></div>';
                    container.innerHTML = html;
                })
                .catch(() => {
                    document.getElementById('invoicesContainer').innerHTML = '<div class="py-6 text-center text-red-400 text-sm">Failed to load invoices</div>';
                });
        }

        function loadPayments() {
            fetch(`/clients/ajax/${CLIENT_ID}/payments`)
                .then(r => r.json())
                .then(payments => {
                    const container = document.getElementById('paymentsContainer');
                    document.getElementById('paymentCount').textContent = payments.length;

                    if (payments.length === 0) {
                        container.innerHTML = renderEmpty('M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1', 'No payment links found');
                        return;
                    }

                    let html = `<div class="max-h-[500px] overflow-y-auto"><table class="w-full text-sm"><thead class="bg-gray-50 sticky top-0"><tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Voucher</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Agent</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Gateway</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Notes</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Created</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Created By</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Reference</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr></thead><tbody class="divide-y divide-gray-100">`;

                    payments.forEach(p => {
                        const payUrl = `/payment/${p.id}/details`;
                        const amt = parseFloat(p.amount || 0).toFixed(3);
                        const createdAt = IS_ADMIN_OR_COMPANY ? esc(p.created_at) : esc(p.created_at_short);
                        const ref = esc(p.reference || 'N/A');
                        const refDisplay = ref.length > 18 ? ref.substring(0, 18) + '...' : ref;
                        const notes = esc(p.notes || 'No Notes');
                        const notesDisplay = notes.length > 30 ? notes.substring(0, 30) + '...' : notes;
                        const isPending = (p.status || '').toLowerCase() === 'pending';

                        let actionsHtml = `
                            <form action="/resayil/share-payment-link" method="POST" class="inline">
                                <input type="hidden" name="_token" value="${CSRF_TOKEN}">
                                <input type="hidden" name="client_id" value="${p.client_id}">
                                <input type="hidden" name="payment_id" value="${p.id}">
                                <input type="hidden" name="voucher_number" value="${esc(p.voucher_number || '')}">
                                <button type="submit" class="flex items-center gap-2 w-full px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                    <svg class="w-4 h-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V4a2 2 0 10-4 0v1.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
                                    Send Link
                                </button>
                            </form>
                            <button onclick="copyToClipboard('${payUrl}')" class="flex items-center gap-2 w-full px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                <svg class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16h8M8 12h8m-6 8h6a2 2 0 002-2V7a2 2 0 00-2-2H9m-2 0H7a2 2 0 00-2 2v12a2 2 0 002 2h2V5z" /></svg>
                                Copy Link
                            </button>
                            <a href="${payUrl}" target="_blank" class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                <svg class="w-4 h-4 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                View Invoice
                            </a>`;

                        if (isPending) {
                            actionsHtml += `
                                <div class="border-t border-gray-100"></div>
                                <form action="/payment/link/delete/${p.id}" method="POST" class="inline">
                                    <input type="hidden" name="_token" value="${CSRF_TOKEN}">
                                    <input type="hidden" name="_method" value="DELETE">
                                    <button type="submit" class="flex items-center gap-2 w-full px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" /></svg>
                                        Delete
                                    </button>
                                </form>`;
                        }

                        html += `<tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3"><a href="${payUrl}" target="_blank" class="text-blue-600 hover:text-blue-800 font-medium">${esc(p.voucher_number || '')}</a></td>
                            <td class="px-4 py-3 text-gray-700 font-medium">${esc(p.agent_name)}</td>
                            <td class="px-4 py-3 text-gray-600">${esc(p.gateway)}</td>
                            <td class="px-4 py-3 text-gray-500 max-w-[200px] truncate" title="${notes}">${notesDisplay}</td>
                            <td class="px-4 py-3 text-right font-semibold text-gray-900">${amt}</td>
                            <td class="px-4 py-3 text-gray-500 whitespace-nowrap">${createdAt}</td>
                            <td class="px-4 py-3 text-gray-600">${esc(p.created_by)}</td>
                            <td class="px-4 py-3"><span class="text-gray-700 font-medium" title="${ref}">${refDisplay}</span></td>
                            <td class="px-4 py-3 text-center">${statusBadge(p.status || 'pending', paymentStatusMap)}</td>
                            <td class="px-4 py-3 text-center">
                                <div x-data="{ open: false }" class="relative inline-block text-left">
                                    <button @click="open = !open" @click.outside="open = false" class="p-1.5 rounded-lg hover:bg-gray-100 transition-colors">
                                        <svg class="w-5 h-5 text-gray-500" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a1.5 1.5 0 110-3 1.5 1.5 0 010 3zM10 13a1.5 1.5 0 110-3 1.5 1.5 0 010 3zM10 20a1.5 1.5 0 110-3 1.5 1.5 0 010 3z" /></svg>
                                    </button>
                                    <div x-cloak x-show="open" x-transition class="absolute right-0 mt-2 z-50 w-48 bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden">
                                        ${actionsHtml}
                                    </div>
                                </div>
                            </td></tr>`;
                    });
                    html += '</tbody></table></div>';
                    container.innerHTML = html;
                })
                .catch(() => {
                    document.getElementById('paymentsContainer').innerHTML = '<div class="py-6 text-center text-red-400 text-sm">Failed to load payment links</div>';
                });
        }

        document.addEventListener('DOMContentLoaded', function() {
            loadTasks();
            loadInvoices();
            loadPayments();
        });
    </script>
</x-app-layout>
