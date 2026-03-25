<x-app-layout>
    @php
        function sortUrl($type, $field, $currentSort, $currentDirection) {
            $newDirection = ($currentSort === $field && $currentDirection === 'asc') ? 'desc' : 'asc';
            $params = request()->query();
            
            if ($type === 'pl') {
                $params['ps'] = $field;
                $params['pd'] = $newDirection;
            } else {
                $params['is'] = $field;
                $params['id'] = $newDirection;
            }
            
            return request()->url() . '?' . http_build_query($params);
        }
    @endphp

    <div class="main-page-header">
        <div class="main-page-header-left">
            <h2 class="main-page-title">Outstanding</h2>
        </div>
    </div>

    <div x-data="{ 
            activeTab: localStorage.getItem('outstanding_tab') || 'payment_links',
            setTab(tab) {
                this.activeTab = tab;
                localStorage.setItem('outstanding_tab', tab);
            }
         }">
        <div class="main-tabs-bar">
            <button
                @click="setTab('payment_links')"
                class="main-tab-shape main-tab"
                :class="activeTab === 'payment_links' ? 'main-tab-active' : 'main-tab-inactive'">
                <div class="main-tab-content-wrapper">
                    <svg class="main-tab-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                    </svg>
                    Payment Links
                    <span class="main-tab-badge main-tab-badge-amber">{{ $totalPaymentLinks }}</span>
                </div>
            </button>

            <button
                @click="setTab('invoices')"
                class="main-tab-shape main-tab"
                :class="activeTab === 'invoices' ? 'main-tab-active' : 'main-tab-inactive'">
                <div class="main-tab-content-wrapper">
                    <svg class="main-tab-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Invoices
                    <span class="main-tab-badge main-tab-badge-red">{{ $totalInvoices }}</span>
                </div>
            </button>
        </div>

        <!-- Tab Content: Payment Links -->
        <div x-show="activeTab === 'payment_links'" x-cloak class="main-tab-content">
            <div class="main-section-header">
                <div>
                    <h3 class="main-section-title">Pending Payment Links</h3>
                    <p class="main-section-subtitle">{{ $totalPaymentLinks }} payment {{ Str::plural('link', $totalPaymentLinks) }} awaiting completion</p>
                </div>
            </div>

            <!-- Search Component -->
            <div class="mb-4">
                <x-search
                    :action="route('payment.outstanding')"
                    searchParam="search"
                    placeholder="Search by voucher number, client name, or agent name" />
            </div>

            <div class="main-table-container">
                <div class="main-table-scroll">
                    <table class="main-table">
                        <thead>
                            <tr class="main-table-thead">
                                <th class="main-table-th">
                                    <a href="{{ sortUrl('pl', 'voucher_number', $plSort, $plDirection) }}" class="main-sort-link">
                                        Voucher Number
                                        <span class="main-sort-icons">
                                            <svg class="main-sort-icon {{ $plSort === 'voucher_number' && $plDirection === 'asc' ? 'main-sort-icon-active' : '' }}" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 5l8 10H4l8-10z" />
                                            </svg>
                                            <svg class="main-sort-icon main-sort-icon-down {{ $plSort === 'voucher_number' && $plDirection === 'desc' ? 'main-sort-icon-active' : '' }}" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 19L4 9h16l-8 10z" />
                                            </svg>
                                        </span>
                                    </a>
                                </th>
                                <th class="main-table-th">Agent</th>
                                <th class="main-table-th">
                                    <a href="{{ sortUrl('pl', 'client_name', $plSort, $plDirection) }}" class="main-sort-link">
                                        Client
                                        <span class="main-sort-icons">
                                            <svg class="main-sort-icon {{ $plSort === 'client_name' && $plDirection === 'asc' ? 'main-sort-icon-active' : '' }}" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 5l8 10H4l8-10z" />
                                            </svg>
                                            <svg class="main-sort-icon main-sort-icon-down {{ $plSort === 'client_name' && $plDirection === 'desc' ? 'main-sort-icon-active' : '' }}" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 19L4 9h16l-8 10z" />
                                            </svg>
                                        </span>
                                    </a>
                                </th>
                                <th class="main-table-th">Contact</th>
                                <th class="main-table-th">Payment Type</th>
                                <th class="main-table-th">Amount</th>
                                <th class="main-table-th">Client Pay</th>
                                <th class="main-table-th">Created By</th>
                                <th class="main-table-th">Reference</th>
                                <th class="main-table-th-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if ($paymentLinks->isEmpty())
                            <tr>
                                <td colspan="10" class="main-table-empty">
                                    <div class="flex flex-col items-center">
                                        <svg class="main-table-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                        </svg>
                                        <span class="main-table-empty-title">{{ request('search') ? 'No results found' : 'All caught up!' }}</span>
                                        <span class="main-table-empty-subtitle">{{ request('search') ? 'Try a different search term' : 'No pending payment links' }}</span>
                                    </div>
                                </td>
                            </tr>
                            @else
                            @foreach ($paymentLinks as $payment)
                            <tr class="main-table-row" onclick="window.location.href='{{ route('payment.link.index', ['q' => $payment->voucher_number]) }}'">
                                <td class="main-table-td">
                                    <span class="main-table-td-link">{{ $payment->voucher_number }}</span>
                                </td>
                                <td class="main-table-td">{{ $payment->agent?->name ?? 'Not Set' }}</td>
                                <td class="main-table-td-bold">{{ $payment->client?->full_name ?? 'Not Set' }}</td>
                                <td class="main-table-td">{{ $payment->client ? $payment->client->country_code . $payment->client->phone : 'Not Set' }}</td>
                                <td class="main-table-td">
                                    @php
                                        $gateway = $payment->payment_gateway ?? 'Not Set';
                                        $method = $payment->paymentMethod->english_name ?? null;
                                    @endphp
                                    {{ $method ? "$gateway - $method" : $gateway }}
                                </td>
                                <td class="main-table-td-bold">{{ number_format($payment->amount, 3) }} KWD</td>
                                <td class="main-table-td-bold">{{ number_format($payment->amount + $payment->service_charge, 3) }} KWD</td>
                                <td class="main-table-td">{{ $payment->createdBy ? $payment->createdBy->name : 'N/A' }}</td>
                                <td class="main-table-td whitespace-nowrap">
                                    @php
                                        $payment_reference = match(true) {
                                            !empty($payment->myFatoorahPayment?->invoice_ref) => $payment->myFatoorahPayment->invoice_ref,
                                            !empty($payment->hesabePayment?->invoice_id) => $payment->hesabePayment->invoice_id,
                                            !empty($payment->payment_reference) => $payment->payment_reference,
                                            default => 'N/A'
                                        };
                                        $isTrimmed = strlen($payment_reference) > 15;
                                        $trimmedValue = \Illuminate\Support\Str::limit($payment_reference, 15);
                                    @endphp
                                    @if ($isTrimmed)
                                        <span x-data="{ showFullData: false }">
                                            <span x-show="!showFullData" @click.stop="showFullData = !showFullData" class="cursor-pointer hover:text-purple-700" data-tooltip-left="Click to expand">{{ $trimmedValue }}</span>
                                            <span x-show="showFullData" @click.stop="showFullData = !showFullData" class="cursor-pointer hover:text-purple-500">{{ $payment_reference }}</span>
                                        </span>
                                    @else
                                        <span>{{ $payment_reference }}</span>
                                    @endif
                                </td>
                                <td class="main-table-td-center">
                                    @php
                                        $statusClass = match(strtolower($payment->status)) {
                                            'pending' => 'main-badge-yellow',
                                            'initiate' => 'main-badge-blue',
                                            'failed' => 'main-badge-red',
                                            'cancelled' => 'main-badge-gray',
                                            default => 'main-badge-gray'
                                        };
                                    @endphp
                                    <span class="main-badge {{ $statusClass }}">{{ ucfirst($payment->status) }}</span>
                                </td>
                            </tr>
                            @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
                
                @if ($paymentLinks->hasPages())
                <div class="main-table-pagination">
                    {{ $paymentLinks->withQueryString()->links() }}
                </div>
                @endif
            </div>
        </div>

        <!-- Tab Content: Invoices -->
        <div x-show="activeTab === 'invoices'" x-cloak class="main-tab-content">
            <div class="main-section-header">
                <div>
                    <h3 class="main-section-title">Unpaid Invoices</h3>
                    <p class="main-section-subtitle">{{ $totalInvoices }} {{ Str::plural('invoice', $totalInvoices) }} awaiting payment</p>
                </div>
            </div>

            <!-- Search Component -->
            <div class="mb-4">
                <x-search
                    :action="route('payment.outstanding')"
                    searchParam="search"
                    placeholder="Search by invoice number, client name, or agent name" />
            </div>

            <div class="main-table-container">
                <div class="main-table-scroll">
                    <table class="main-table">
                        <thead>
                            <tr class="main-table-thead">
                                <th class="main-table-th">
                                    <a href="{{ sortUrl('inv', 'invoice_number', $invSort, $invDirection) }}" class="main-sort-link">
                                        Invoice Number
                                        <span class="main-sort-icons">
                                            <svg class="main-sort-icon {{ $invSort === 'invoice_number' && $invDirection === 'asc' ? 'main-sort-icon-active' : '' }}" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 5l8 10H4l8-10z" />
                                            </svg>
                                            <svg class="main-sort-icon main-sort-icon-down {{ $invSort === 'invoice_number' && $invDirection === 'desc' ? 'main-sort-icon-active' : '' }}" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 19L4 9h16l-8 10z" />
                                            </svg>
                                        </span>
                                    </a>
                                </th>
                                <th class="main-table-th">Agent</th>
                                <th class="main-table-th">Client</th>
                                <th class="main-table-th">Payment Type</th>
                                <th class="main-table-th">Net Amount</th>
                                <th class="main-table-th">Profit</th>
                                <th class="main-table-th">Invoice Amount</th>
                                <th class="main-table-th">Service Charges</th>
                                <th class="main-table-th">Client Pay</th>
                                <th class="main-table-th">
                                    <a href="{{ sortUrl('inv', 'created_at', $invSort, $invDirection) }}" class="main-sort-link">
                                        Created Date
                                        <span class="main-sort-icons">
                                            <svg class="main-sort-icon {{ $invSort === 'created_at' && $invDirection === 'asc' ? 'main-sort-icon-active' : '' }}" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 5l8 10H4l8-10z" />
                                            </svg>
                                            <svg class="main-sort-icon main-sort-icon-down {{ $invSort === 'created_at' && $invDirection === 'desc' ? 'main-sort-icon-active' : '' }}" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 19L4 9h16l-8 10z" />
                                            </svg>
                                        </span>
                                    </a>
                                </th>
                                <th class="main-table-th">
                                    <a href="{{ sortUrl('inv', 'invoice_date', $invSort, $invDirection) }}" class="main-sort-link">
                                        Invoice Date
                                        <span class="main-sort-icons">
                                            <svg class="main-sort-icon {{ $invSort === 'invoice_date' && $invDirection === 'asc' ? 'main-sort-icon-active' : '' }}" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 5l8 10H4l8-10z" />
                                            </svg>
                                            <svg class="main-sort-icon main-sort-icon-down {{ $invSort === 'invoice_date' && $invDirection === 'desc' ? 'main-sort-icon-active' : '' }}" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 19L4 9h16l-8 10z" />
                                            </svg>
                                        </span>
                                    </a>
                                </th>
                                <th class="main-table-th-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if ($invoices->isEmpty())
                            <tr>
                                <td colspan="12" class="main-table-empty">
                                    <div class="flex flex-col items-center">
                                        <svg class="main-table-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                        </svg>
                                        <span class="main-table-empty-title">{{ request('search') ? 'No results found' : 'All caught up!' }}</span>
                                        <span class="main-table-empty-subtitle">{{ request('search') ? 'Try a different search term' : 'No unpaid invoices' }}</span>
                                    </div>
                                </td>
                            </tr>
                            @else
                            @foreach ($invoices as $invoice)
                            <tr class="main-table-row" onclick="window.location.href='{{ route('invoices.index', ['search' => $invoice->invoice_number]) }}'">
                                <td class="main-table-td">
                                    <span class="main-table-td-link">{{ $invoice->invoice_number }}</span>
                                </td>
                                <td class="main-table-td-bold">{{ $invoice->agent?->name ?? 'Not Set' }}</td>
                                <td class="main-table-td-bold">{{ $invoice->client?->full_name ?? 'Not Set' }}</td>
                                <td class="main-table-td">{{ $invoice->payment_type ? ucwords($invoice->payment_type) : 'Not Set' }}</td>
                                <td class="main-table-td-bold">{{ number_format($invoice->invoiceDetails->sum('supplier_price'), 3) }} {{ $invoice->currency }}</td>
                                <td class="main-table-td-bold">{{ number_format($invoice->invoiceDetails->sum('profit'), 3) }} {{ $invoice->currency }}</td>
                                <td class="main-table-td-bold">{{ number_format($invoice->amount, 3) }} {{ $invoice->currency }}</td>
                                <td class="main-table-td-bold">{{ number_format($invoice->invoicePartials->sum('service_charge'), 3) }} {{ $invoice->currency }}</td>
                                <td class="main-table-td-bold">{{ number_format($invoice->client_pay, 3) }} {{ $invoice->currency }}</td>
                                <td class="main-table-td">{{ $invoice->created_at->format('d-m-Y H:i') }}</td>
                                <td class="main-table-td">{{ $invoice->invoice_date }}</td>
                                <td class="main-table-td-center">
                                    @php
                                        $statusClass = match(strtolower($invoice->status)) {
                                            'unpaid' => 'main-badge-red',
                                            'partial' => 'main-badge-amber',
                                            default => 'main-badge-gray'
                                        };
                                    @endphp
                                    <span class="main-badge {{ $statusClass }}">{{ ucfirst($invoice->status) }}</span>
                                </td>
                            </tr>
                            @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
                
                @if ($invoices->hasPages())
                <div class="main-table-pagination">
                    {{ $invoices->withQueryString()->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>