<x-app-layout>
    <div class="mb-4">
        <h1 class="text-center mb-4 font-semibold text-2xl">Payment Gateways Report</h1>
        <p class="text-center text-gray-600">View all paid invoices with payment types and wallet top-up sources</p>
    </div>

    <!-- Filter Section -->
    <div class="flex justify-center items-center bg-gray-100 mb-4">
        <form method="GET" action="{{ route('reports.payment-gateways') }}"
            class="p-6 w-full flex flex-col gap-4 bg-white rounded shadow">

            <!-- Input Fields Section -->
            <div class="grid grid-cols-12 gap-4">
                <div class="flex flex-col col-span-6 lg:col-span-2">
                    <label for="start_date" class="font-medium text-sm mb-1">Start Date:</label>
                    <input type="date" name="start_date" id="start_date"
                        value="{{ $startDate ?? '' }}"
                        class="border rounded px-3 py-2 focus:outline-none focus:ring focus:ring-blue-300">
                </div>
                <div class="flex flex-col col-span-6 lg:col-span-2">
                    <label for="end_date" class="font-medium text-sm mb-1">End Date:</label>
                    <input type="date" name="end_date" id="end_date"
                        value="{{ $endDate ?? '' }}"
                        class="border rounded px-3 py-2 focus:outline-none focus:ring focus:ring-blue-300">
                </div>
                <div class="flex flex-col col-span-6 lg:col-span-4">
                    <label for="client_id" class="font-medium text-sm mb-1">Filter by Client:</label>
                    <x-ajax-searchable-dropdown
                        name="client_id"
                        :selectedId="$selectedClient->id ?? ''"
                        :selectedName="$selectedClient->full_name ?? $selectedClient->name ?? ''"
                        dataId=""
                        ajaxUrl="{{ route('clients.ajax.search') }}"
                        placeholder="Select Client" />
                </div>
                <div class="flex flex-col col-span-6 lg:col-span-4">
                    <label for="payment_gateway" class="font-medium text-sm mb-1">Filter by Payment Gateway:</label>
                    <select name="payment_gateway" id="payment_gateway"
                        class="border rounded px-3 py-2 focus:outline-none focus:ring focus:ring-blue-300">
                        <option value="">All Gateways</option>
                        @foreach ($paymentGateways as $gateway)
                        <option value="{{ $gateway }}" {{ $selectedPaymentGateway == $gateway ? 'selected' : '' }}>
                            {{ ucfirst($gateway) }}
                        </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Button Section -->
            <div class="flex justify-end gap-3 mt-4">
                <button type="button" onclick="window.location.href='{{ route('reports.payment-gateways') }}'"
                    class="px-4 py-2 border border-gray-300 text-gray-700 text-sm rounded-md hover:bg-gray-100 transition-all duration-150">
                    Reset
                </button>
                <button type="submit"
                    class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition-all duration-150">
                    Filter
                </button>
                <a href="{{ route('reports.payment-gateways.pdf', request()->query()) }}"
                    class="px-4 py-2 bg-green-600 text-white text-sm rounded-md hover:bg-green-700 transition-all duration-150 inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Download PDF
                </a>
            </div>

            <!-- Filter Summary -->
            <div class="border rounded p-3 bg-gray-50">
                @if ($startDate && $endDate)
                <p class="text-sm text-gray-700"><strong>Period:</strong> {{ date('M d, Y', strtotime($startDate)) }} to {{ date('M d, Y', strtotime($endDate)) }}</p>
                @elseif ($startDate)
                <p class="text-sm text-gray-700"><strong>From:</strong> {{ date('M d, Y', strtotime($startDate)) }}</p>
                @elseif ($endDate)
                <p class="text-sm text-gray-700"><strong>Until:</strong> {{ date('M d, Y', strtotime($endDate)) }}</p>
                @else
                <p class="text-sm text-gray-700">Showing all records (no date filter applied)</p>
                @endif

                @if ($selectedClient)
                <p class="text-sm text-gray-700"><strong>Client:</strong> {{$selectedClient->full_name}}</p>
                @endif

                @if ($selectedPaymentGateway)
                <p class="text-sm text-gray-700"><strong>Gateway:</strong> {{ ucfirst($selectedPaymentGateway) }}</p>
                @endif
            </div>
        </form>
    </div>

    @php
    $hasData = $paginatedData->count() > 0;
    @endphp

    @if($hasData)
    <!-- Gateway Summary Section -->
    <div class="bg-white rounded shadow mb-6 p-4">
        <h2 class="text-xl font-bold mb-4 text-gray-800">
            Gateway Summary
        </h2>

        <div class="overflow-x-auto">
            <table class="w-full border-collapse">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Gateway</th>
                        <th class="border border-gray-300 px-4 py-2 text-center font-semibold">Transactions</th>
                        <th class="border border-gray-300 px-4 py-2 text-right font-semibold">Gross Amount (KWD)</th>
                        <th class="border border-gray-300 px-4 py-2 text-right font-semibold">Total Charges (KWD)</th>
                        <th class="border border-gray-300 px-4 py-2 text-right font-semibold">Net to Receive (KWD)</th>
                        <th class="border border-gray-300 px-4 py-2 text-right font-semibold">Avg Charge %</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($gatewaySummary['gateways'] as $gateway => $data)
                    <tr class="hover:bg-gray-50">
                        <td class="border border-gray-300 px-4 py-2">
                            <span class="inline-block px-2 py-1 rounded text-xs bg-gray-200 text-gray-800 font-medium">
                                {{ ucfirst($gateway) }}
                            </span>
                        </td>
                        <td class="border border-gray-300 px-4 py-2 text-center font-semibold">{{ $data['transactions'] }}</td>
                        <td class="border border-gray-300 px-4 py-2 text-right font-semibold">{{ number_format($data['gross_amount'], 2) }}</td>
                        <td class="border border-gray-300 px-4 py-2 text-right font-semibold">{{ number_format($data['total_charges'], 2) }}</td>
                        <td class="border border-gray-300 px-4 py-2 text-right font-semibold">{{ number_format($data['net_to_receive'], 2) }}</td>
                        <td class="border border-gray-300 px-4 py-2 text-right font-semibold">{{ number_format($data['avg_charge_percent'], 2) }}%</td>
                    </tr>
                    @endforeach
                    <!-- Totals Row -->
                    <tr class="bg-gray-100 font-bold">
                        <td class="border border-gray-300 px-4 py-2">TOTAL</td>
                        <td class="border border-gray-300 px-4 py-2 text-center">{{ $gatewaySummary['totals']['transactions'] }}</td>
                        <td class="border border-gray-300 px-4 py-2 text-right">{{ number_format($gatewaySummary['totals']['gross_amount'], 2) }}</td>
                        <td class="border border-gray-300 px-4 py-2 text-right">{{ number_format($gatewaySummary['totals']['total_charges'], 2) }}</td>
                        <td class="border border-gray-300 px-4 py-2 text-right">{{ number_format($gatewaySummary['totals']['net_to_receive'], 2) }}</td>
                        <td class="border border-gray-300 px-4 py-2 text-right">{{ number_format($gatewaySummary['totals']['avg_charge_percent'], 2) }}%</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- Combined Payments Section -->
    <div class="bg-white rounded shadow mb-6 p-4">
        <h2 class="text-xl font-bold mb-4 text-gray-800">
            Payments & Transactions
        </h2>

        @if($hasData)
        <div class="overflow-x-auto">
            <table class="w-full border-collapse">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border border-gray-300 px-3 py-2 text-center font-semibold w-12">#</th>
                        <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Reference</th>
                        <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Type</th>
                        <th class="border border-gray-300 px-4 py-2 text-right font-semibold">Amount (KWD)</th>
                        <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Payment Source</th>
                        <th class="border border-gray-300 px-4 py-2 text-right font-semibold">Charges (KWD)</th>
                        <th class="border border-gray-300 px-4 py-2 text-right font-semibold">Net to Receive (KWD)</th>
                        <th class="border border-gray-300 px-4 py-2 text-left font-semibold">Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($paginatedData as $index => $item)
                    @php
                    $rowNumber = $paginatedData->firstItem() + $index;
                    $isInvoice = $item['type'] === 'invoice';
                    $record = $item['data'];

                    if ($isInvoice) {
                        // Invoice processing
                        $amount = $record->amount;
                        $reference = $record->invoice_number;
                        $typeName = 'Invoice';
                        $dateValue = $record->paid_date;
                        $dateFormat = $dateValue ? date('M d, Y', strtotime($dateValue)) : 'N/A';

                        $totalCharges = $record->invoicePartials->sum('gateway_fee') ?? 0;
                        $paymentGateway = 'N/A';
                        $paymentMethods = collect();

                        foreach($record->invoicePartials as $partial) {
                            if($paymentGateway === 'N/A' && !empty($partial->payment_gateway)) {
                                $paymentGateway = $partial->payment_gateway;
                            }
                            if($partial->paymentMethod) {
                                $paymentMethods->push($partial->paymentMethod);
                            }
                        }
                        $paymentMethods = $paymentMethods->unique('id');

                        $methodNames = $paymentMethods->map(fn($m) => $m->english_name ?? $m->arabic_name ?? 'Unknown')->implode(', ');
                        $paymentSource = ucfirst($paymentGateway) . ($methodNames ? ' (' . $methodNames . ')' : '');
                    } else {
                        // Payment processing
                        $amount = $record->amount;
                        $reference = $record->voucher_number;
                        $typeName = 'Payment';
                        $dateValue = $record->payment_date;
                        $dateFormat = $dateValue ? date('M d, Y H:i', strtotime($dateValue)) : 'N/A';

                        $totalCharges = $record->gateway_fee ?? 0;
                        $paymentGateway = $record->payment_gateway ?? 'N/A';
                        $methodName = $record->paymentMethod
                            ? ($record->paymentMethod->english_name ?? $record->paymentMethod->arabic_name ?? 'Unknown')
                            : '';
                        $paymentSource = ucfirst($paymentGateway) . ($methodName ? ' (' . $methodName . ')' : '');
                    }

                    $netToReceive = $amount - $totalCharges;

                    // Format charge display
                    $chargeDisplay = number_format($totalCharges, 2);
                    if($totalCharges > 0 && $amount > 0) {
                        $percent = ($totalCharges / $amount) * 100;
                        $chargeDisplay .= $percent >= 0.1 ? ' (' . number_format($percent, 2) . '%)' : ' (fixed)';
                    }
                    @endphp
                    <tr class="hover:bg-gray-50 transition">
                        <td class="border border-gray-300 px-3 py-2 text-center text-sm text-gray-500">{{ $rowNumber }}</td>
                        <td class="border border-gray-300 px-4 py-2 font-mono text-sm">{{ $reference }}</td>
                        <td class="border border-gray-300 px-4 py-2 text-sm">{{ $typeName }}</td>
                        <td class="border border-gray-300 px-4 py-2 text-right font-semibold">{{ number_format($amount, 2) }}</td>
                        <td class="border border-gray-300 px-4 py-2 text-sm">{{ $paymentSource }}</td>
                        <td class="border border-gray-300 px-4 py-2 text-right font-semibold">{{ $chargeDisplay }}</td>
                        <td class="border border-gray-300 px-4 py-2 text-right font-semibold">{{ number_format($netToReceive, 2) }}</td>
                        <td class="border border-gray-300 px-4 py-2 text-sm">{{ $dateFormat }}</td>
                    </tr>

                    {{-- Show invoice partials breakdown for Split/Partial invoices --}}
                    @if($isInvoice && in_array($record->invoice_type ?? '', ['Split', 'Partial']) && $record->invoicePartials->count() > 1)
                    <tr class="bg-gray-50">
                        <td colspan="8" class="border border-gray-300 px-4 py-3">
                            <details class="cursor-pointer">
                                <summary class="font-semibold text-gray-700 text-sm">View Payment Breakdown</summary>
                                <table class="w-full mt-3 border border-gray-200 rounded">
                                    <thead>
                                        <tr class="bg-gray-200">
                                            <th class="px-3 py-2 text-left text-sm">Amount</th>
                                            <th class="px-3 py-2 text-left text-sm">Status</th>
                                            <th class="px-3 py-2 text-left text-sm">Payment Source</th>
                                            <th class="px-3 py-2 text-right text-sm">Charges</th>
                                            <th class="px-3 py-2 text-right text-sm">Net to Receive</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($record->invoicePartials as $partial)
                                        @if(in_array($partial->payment_gateway, $systemDefaultGateways))
                                        @php
                                        $pMethodName = $partial->paymentMethod ? ($partial->paymentMethod->english_name ?? $partial->paymentMethod->arabic_name ?? 'Unknown') : '';
                                        $pSource = ucfirst($partial->payment_gateway ?? 'N/A') . ($pMethodName ? ' (' . $pMethodName . ')' : '');
                                        $pFee = $partial->gateway_fee ?? 0;
                                        $pChargeDisplay = number_format($pFee, 2);
                                        if($pFee > 0 && $partial->amount > 0) {
                                            $pPercent = ($pFee / $partial->amount) * 100;
                                            $pChargeDisplay .= $pPercent >= 0.1 ? ' (' . number_format($pPercent, 2) . '%)' : ' (fixed)';
                                        }
                                        @endphp
                                        <tr class="border-t border-gray-200">
                                            <td class="px-3 py-2">{{ number_format($partial->amount, 2) }}</td>
                                            <td class="px-3 py-2 text-sm">{{ ucfirst($partial->status) }}</td>
                                            <td class="px-3 py-2">{{ $pSource }}</td>
                                            <td class="px-3 py-2 text-right font-semibold">{{ $pChargeDisplay }}</td>
                                            <td class="px-3 py-2 text-right font-semibold">{{ number_format($partial->amount - $pFee, 2) }}</td>
                                        </tr>
                                        @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </details>
                        </td>
                    </tr>
                    @endif
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-gray-100 font-bold">
                        <td class="border border-gray-300 px-3 py-2"></td>
                        <td class="border border-gray-300 px-4 py-2" colspan="2">GRAND TOTAL</td>
                        <td class="border border-gray-300 px-4 py-2 text-right">{{ number_format($gatewaySummary['totals']['gross_amount'] ?? 0, 2) }}</td>
                        <td class="border border-gray-300 px-4 py-2"></td>
                        <td class="border border-gray-300 px-4 py-2 text-right">{{ number_format($gatewaySummary['totals']['total_charges'] ?? 0, 2) }}</td>
                        <td class="border border-gray-300 px-4 py-2 text-right">{{ number_format($gatewaySummary['totals']['net_to_receive'] ?? 0, 2) }}</td>
                        <td class="border border-gray-300 px-4 py-2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-4">
            <x-pagination :data="$paginatedData->withQueryString()"/>
        </div>

        <!-- Summary Statistics -->
        <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white border border-gray-200 rounded p-4">
                <p class="text-xs text-gray-600 uppercase tracking-wide">Total Invoices</p>
                <p class="text-2xl font-bold text-gray-800">{{ $allInvoices->count() }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded p-4">
                <p class="text-xs text-gray-600 uppercase tracking-wide">Invoices Amount</p>
                <p class="text-2xl font-bold text-gray-800">{{ number_format($allInvoices->sum('amount'), 2) }} KWD</p>
            </div>
            <div class="bg-white border border-gray-200 rounded p-4">
                <p class="text-xs text-gray-600 uppercase tracking-wide">Total Credit Payments</p>
                <p class="text-2xl font-bold text-gray-800">{{ $walletTopUps->count() }}</p>
            </div>
            <div class="bg-white border border-gray-200 rounded p-4">
                <p class="text-xs text-gray-600 uppercase tracking-wide">Credit Payments Amount</p>
                <p class="text-2xl font-bold text-gray-800">{{ number_format($walletTopUps->sum('amount'), 2) }} KWD</p>
            </div>
        </div>
        @else
        <div class="bg-yellow-50 border border-yellow-200 rounded p-4 text-center">
            <p class="text-yellow-800">No transactions found matching the selected filters.</p>
        </div>
        @endif
    </div>

    <!-- Grand Summary Section -->
    <div class="mt-6 bg-gray-50 rounded shadow p-6 border border-gray-200">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Overall Summary</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
                <p class="text-xs text-gray-600 uppercase tracking-wide">Total Transactions</p>
                <p class="text-3xl font-bold text-gray-800">{{ ($gatewaySummary['totals']['transactions'] ?? 0) }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-600 uppercase tracking-wide">Combined Amount</p>
                <p class="text-3xl font-bold text-gray-800">{{ number_format($gatewaySummary['totals']['gross_amount'] ?? 0, 2) }} KWD</p>
            </div>
            <div>
                <p class="text-xs text-gray-600 uppercase tracking-wide">Total Charges</p>
                <p class="text-3xl font-bold text-gray-800">{{ number_format($gatewaySummary['totals']['total_charges'] ?? 0, 2) }} KWD</p>
            </div>
            <div>
                <p class="text-xs text-gray-600 uppercase tracking-wide">Net to Receive</p>
                <p class="text-3xl font-bold text-gray-800">{{ number_format($gatewaySummary['totals']['net_to_receive'] ?? 0, 2) }} KWD</p>
            </div>
        </div>
    </div>
</x-app-layout>

<style>
    details summary::marker {
        color: #6b7280;
    }

    details summary {
        outline: none;
    }

    details[open] summary {
        color: #374151;
    }
</style>
