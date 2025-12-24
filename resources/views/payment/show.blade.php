<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                {{ __('Payment Details') }}
            </h2>
            <div class="flex gap-2">
                <button onclick="window.print()"
                    class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-700">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Print
                </button>
                @if($payment->status === 'pending' || $payment->status === 'initiate')
                <button onclick="confirmCancel()"
                    class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Cancel
                </button>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto">
            @if (session('status'))
            <div class="p-4 mb-4 text-green-700 bg-green-100 rounded-lg dark:bg-green-900 dark:text-green-200">
                {{ session('status') }}
            </div>
            @endif

            @if (session('error'))
            <div class="p-4 mb-4 text-red-700 bg-red-100 rounded-lg dark:bg-red-900 dark:text-red-200">
                {{ session('error') }}
            </div>
            @endif

            <!-- Main Two Column Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

                <!-- LEFT COLUMN: Info, Bank Details, Tabs -->
                <div class="lg:col-span-2 space-y-4">

                    <!-- Info Section - Split into Customer Card and Payment Details Card -->

                    <!-- Customer Card -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-start justify-between mb-5">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Customer Details</h3>
                                <div class="flex items-center gap-3">
                                    @if($payment->client)
                                    <a href="{{ route('clients.show', $payment->client->id) }}" target="_blank"
                                        class="text-xs text-blue-600 hover:text-blue-700 font-medium px-3 py-1.5 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors">View Profile
                                    </a>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center text-white text-xl font-semibold flex-shrink-0">
                                    {{ strtoupper(substr($payment->client?->full_name ?? 'NA', 0, 2)) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h4 class="font-semibold text-gray-900 dark:text-gray-100">{{ $payment->client?->full_name ?? 'N/A' }}</h4>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 truncate">
                                        {{ $payment->client?->country_code }}{{ $payment->client?->phone ?? 'N/A' }} • {{ $payment->client?->email ?? 'N/A' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Details Card -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-5">Payment Details</h3>

                            <div class="grid grid-cols-3 gap-x-8 gap-y-4">
                                <!-- Row 1 -->
                                <div>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-1">Payment ID</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $payment->voucher_number }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-1">Payment Reference</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $payment->payment_reference ?? 'N/A' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-1">Method</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $payment->paymentMethod->english_name ?? $payment->payment_gateway ?? 'N/A' }}
                                    </p>
                                </div>

                                <!-- Row 2 -->
                                <div>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-1">From</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $payment->from ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-1">Pay To</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $payment->pay_to ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-1">Auth Code</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $payment->auth_code ?? 'N/A' }}</p>
                                </div>

                                <!-- Row 3: Dates -->
                                <div>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-1">Created Date</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $payment->created_at->format('d M Y, H:i') }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-1">Payment Date</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $payment->payment_date ? $payment->payment_date->format('d M Y, H:i') : 'N/A' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-1">Expiry</p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $payment->expiry_date ? $payment->expiry_date->format('d M Y, H:i') : 'N/A' }}
                                    </p>
                                </div>

                                <!-- Notes - Full Width -->
                                <div class="col-span-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                                    <p class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-1">Notes</p>
                                    @if($payment->notes)
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100 whitespace-pre-line">{{ $payment->notes }}</p>
                                    @else
                                    <p class="text-sm text-gray-400 dark:text-gray-500 italic">No notes available</p>
                                    @endif
                                </div>

                                <!-- Terms & Conditions - Full Width -->
                                <div class="col-span-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                                    <p class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-1">Terms & Conditions</p>
                                    @if($payment->terms_conditions)
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100 whitespace-pre-line max-h-32 overflow-y-auto p-3 bg-gray-50 dark:bg-gray-700 rounded-lg mt-3">{{ trim($payment->terms_conditions) }}</div>
                                    @else
                                    <p class="text-sm text-gray-400 dark:text-gray-500 italic">No terms & conditions specified</p>
                                    @endif
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- Bank Details (Optional) -->
                    @if($payment->account_number || $payment->bank_name)
                    <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg">
                        <div class="p-4">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-3">Bank Details</h3>
                            <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                                @if($payment->bank_name)
                                <div class="flex justify-between items-start">
                                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Bank:</span>
                                    <span class="text-sm text-gray-900 dark:text-gray-100 text-right">{{ $payment->bank_name }}</span>
                                </div>
                                @endif
                                @if($payment->account_number)
                                <div class="flex justify-between items-start">
                                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Account:</span>
                                    <span class="text-sm text-gray-900 dark:text-gray-100 text-right">{{ $payment->account_number }}</span>
                                </div>
                                @endif
                                @if($payment->swift_no)
                                <div class="flex justify-between items-start">
                                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400">SWIFT:</span>
                                    <span class="text-sm text-gray-900 dark:text-gray-100 text-right">{{ $payment->swift_no }}</span>
                                </div>
                                @endif
                                @if($payment->iban_no)
                                <div class="flex justify-between items-start">
                                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400">IBAN:</span>
                                    <span class="text-sm text-gray-900 dark:text-gray-100 text-right">{{ $payment->iban_no }}</span>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Tabs Section -->
                    <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                        <div class="border-b border-gray-200 dark:border-gray-700">
                            <nav class="flex -mb-px">
                                <button onclick="switchTab('items')"
                                    id="tab-items"
                                    class="px-6 py-3 text-sm font-medium text-blue-600 border-b-2 border-blue-600 dark:text-blue-400 dark:border-blue-400">
                                    Payment Item
                                </button>
                                <button onclick="switchTab('transactions')"
                                    id="tab-transactions"
                                    class="px-6 py-3 text-sm font-medium text-gray-500 border-b-2 border-transparent hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300">
                                    Transactions
                                </button>
                            </nav>
                        </div>

                        <div class="p-6">
                            <div id="content-items" class="tab-content">
                                <div class="py-12 text-center">
                                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <p class="text-lg font-medium text-gray-600 dark:text-gray-400">Payment Item Details</p>
                                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Coming soon...</p>
                                </div>
                            </div>

                            <div id="content-transactions" class="hidden tab-content">
                                @if($payment->paymentTransactions == null || $payment->paymentTransactions->isEmpty())
                                <div class="py-12 text-center">
                                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <p class="text-lg font-medium text-gray-600 dark:text-gray-400">No Transactions</p>
                                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No transaction records available for this payment.</p>
                                </div>
                                @else
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-900">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Transaction ID</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Payment Gateway</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Track ID</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Reference Number</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Expiry Date</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">URL</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                            @foreach($payment->paymentTransactions as $transaction)
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100 whitespace-nowrap">
                                                    {{ $transaction->transaction_id }}
                                                </td>
                                                <td class="px-4 py-3 text-sm whitespace-nowrap">
                                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                                        {{ strtolower($transaction->status) === 'paid' || strtolower($transaction->status) === 'successful' ||strtolower($transaction->status) === 'completed'   ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                                                           (strtolower($transaction->status) === 'pending' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 
                                                           'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200') }}">
                                                        {{ strtoupper($transaction->status) }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                                    {{ $transaction->paymentGateway->name }} - {{ $transaction->paymentMethod->english_name }}
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                                    {{ $transaction->track_id ?? 'N/A' }}
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                                    {{ $transaction->reference_number ?? 'N/A' }}
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                                    {{ $transaction->expiry_date ? $transaction->expiry_date->format('d M Y, H:i') : 'N/A' }}
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                                    @if($transaction->url)
                                                    @if(now()->lt($transaction->expiry_date))
                                                    <a href="{{ $transaction->url }}" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 hover:underline">View</a>
                                                    @else
                                                    <span class="text-gray-400 italic">Expired</span>
                                                    @endif
                                                    @else
                                                    N/A
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                                    {{ $transaction->notes ?? 'N/A' }}
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                @endif
                            </div>

                            <div id="content-history" class="hidden tab-content">
                                <div class="text-center">
                                    <div class="inline-block w-8 h-8 border-4 border-gray-300 rounded-full border-t-blue-600 animate-spin"></div>
                                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Loading history...</p>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- RIGHT COLUMN: Value & Actions -->
                <div class="lg:col-span-1 space-y-4">

                    <!-- Value Section -->
                    <div class="bg-gradient-to-br from-blue-50 to-white dark:from-gray-800 dark:to-gray-850 shadow-lg sm:rounded-lg">
                        <div class="p-6 h-full flex flex-col">
                            <div class="flex-1">
                                <div class="flex items-baseline justify-between mb-4">
                                    <div>
                                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Total Amount</p>
                                        <p class="text-4xl font-bold text-blue-600 dark:text-blue-400 font-extrabold">
                                            {{ number_format($payment->amount, 3) }} <span class="text-lg font-semibold text-gray-600 dark:text-gray-400">{{ $payment->currency }}</span>
                                        </p>
                                    </div>

                                    @php
                                    $statusConfig = [
                                    'completed' => ['label' => 'PAID', 'class' => 'text-green-800 bg-green-100 dark:bg-green-900 dark:text-green-200'],
                                    'pending' => ['label' => 'PENDING', 'class' => 'text-yellow-800 bg-yellow-100 dark:bg-yellow-900 dark:text-yellow-200'],
                                    'initiate' => ['label' => 'INITIATED', 'class' => 'text-blue-800 bg-blue-100 dark:bg-blue-900 dark:text-blue-200'],
                                    ];
                                    $status = $statusConfig[$payment->status] ?? ['label' => strtoupper($payment->status), 'class' => 'text-gray-800 bg-gray-100 dark:bg-gray-700 dark:text-gray-200'];
                                    @endphp

                                    <span class="px-3 py-1 text-xs rounded-full shadow-md font-bold {{ $status['class'] }}">
                                        {{ $status['label'] }}
                                    </span>
                                </div>

                                @if($payment->service_charge > 0 || $payment->tax > 0)
                                <div class="pt-4 mt-4 border-t border-blue-100 dark:border-gray-700 space-y-2">
                                    @if($payment->service_charge > 0)
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600 dark:text-gray-400">Service Charge:</span>
                                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ number_format($payment->service_charge, 3) }} {{ $payment->currency }}</span>
                                    </div>
                                    @endif
                                    @if($payment->tax > 0)
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600 dark:text-gray-400">Tax:</span>
                                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ number_format($payment->tax, 3) }} {{ $payment->currency }}</span>
                                    </div>
                                    @endif
                                </div>
                                @endif

                                <div class="pt-4 mt-4 border-t border-blue-100 dark:border-gray-700">
                                    <p class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-2">Payment Link</p>
                                    <div class="flex items-center gap-2 p-3 bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700">
                                        <a href="{{ route('payment.link.show', ['companyId' => $payment->agent->branch->company_id, 'voucherNumber' => $payment->voucher_number]) }}"
                                            target="_blank" class="flex-1 text-sm text-blue-600 truncate hover:text-blue-800 dark:text-blue-400">
                                            {{ route('payment.link.show', ['companyId' => $payment->agent->branch->company_id, 'voucherNumber' => $payment->voucher_number]) }}
                                        </a>
                                        <button onclick="copyToClipboard('{{ route('payment.link.show', ['companyId' => $payment->agent->branch->company_id, 'voucherNumber' => $payment->voucher_number]) }}')"
                                            class="p-2 text-gray-500 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900 rounded dark:text-gray-400 dark:hover:text-blue-400 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                            </svg>
                                        </button>
                                        <a href="{{ route('payment.link.show', ['companyId' => $payment->agent->branch->company_id, 'voucherNumber' => $payment->voucher_number]) }}" target="_blank"
                                            class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- More Options Section -->
                    <div class="bg-gradient-to-br from-purple-50 to-white dark:from-gray-800 dark:to-gray-850 shadow-lg sm:rounded-lg" style="overflow: visible;">
                        <div class="p-4 h-full flex flex-col" style="overflow: visible;">
                            <div class="flex items-center mb-4">
                                <div class="p-2 bg-purple-100 dark:bg-purple-900 rounded-lg mr-3">
                                    <svg class="w-6 h-6 text-purple-600 dark:text-purple-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                                    </svg>
                                </div>
                                <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100">Actions</h3>
                            </div>

                            <div class="flex-1 space-y-4" style="overflow: visible;">
                                <div class="flex flex-col gap-3" style="position: relative; overflow: visible;">
                                    <div>
                                        <p class="font-semibold text-gray-900 dark:text-gray-100">Send Payment</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Share link with customer</p>
                                    </div>
                                    <div class="relative inline-block text-left" style="z-index: 50;">
                                        <button type="button" onclick="toggleSendOptions()" id="sendOptionsButton"
                                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 transition-colors shadow-sm">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                            </svg>
                                            Send Link
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>

                                        <div id="sendOptionsDropdown" class="absolute right-0 left-0 hidden w-full mt-2 origin-top-right bg-white rounded-lg shadow-xl dark:bg-gray-700 ring-1 ring-black ring-opacity-5" style="z-index: 100;">
                                            <div class="py-1" role="menu" aria-orientation="vertical">
                                                <form action="" method="POST" class="block">
                                                    @csrf
                                                    <input type="hidden" name="client_id" value="{{ $payment->client_id }}">
                                                    <input type="hidden" name="payment_id" value="{{ $payment->id }}">
                                                    <input type="hidden" name="voucher_number" value="{{ $payment->voucher_number }}">
                                                    <button type="submit" class="flex items-center w-full px-4 py-3 text-sm text-gray-700 hover:bg-green-50 dark:text-gray-200 dark:hover:bg-gray-600 transition-colors">
                                                        <svg class="w-5 h-5 mr-3 text-green-500" fill="currentColor" viewBox="0 0 24 24">
                                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" />
                                                        </svg>
                                                        WhatsApp
                                                    </button>
                                                </form>
                                                <button onclick="copyPaymentLink()" class="flex items-center w-full px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-600 transition-colors">
                                                    <svg class="w-5 h-5 mr-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                    </svg>
                                                    Copy Link
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @if($payment->status != 'completed')
                                <div class="pt-3 border-t border-purple-100 dark:border-gray-700">
                                    <form id="reminderForm" action="" method="POST"
                                        x-data="{ 
                                            frequency: 'once',
                                            intervalPreset: 'every3days',
                                            repeatValue: 3,
                                            repeatUnit: 'days',
                                            maxReminders: 5,
                                            loading: false,
                                            sendToClient: true,
                                            sendToAgent: false,
                                            message: '',
                                            maxWords: 500,
                                            get wordCount() {
                                                return this.message.trim() === '' ? 0 : this.message.trim().split(/\s+/).length;
                                            },
                                            limitWords() {
                                                const words = this.message.trim().split(/\s+/);
                                                if (words.length > this.maxWords) {
                                                    this.message = words.slice(0, this.maxWords).join(' ');
                                                }
                                            },
                                            submitForm(e) {
                                                if (!this.sendToClient && !this.sendToAgent) {
                                                    e.preventDefault();
                                                    return;
                                                }
                                                this.loading = true;
                                            }
                                        }"
                                        @submit="submitForm($event)">
                                        @csrf
                                        <input type="hidden" name="payment_id" value="{{ $payment->id }}">
                                        <input type="hidden" name="client_id" value="{{ $payment->client?->id }}">
                                        <input type="hidden" name="agent_id" value="{{ $payment->client?->agent?->id }}">
                                        <input type="hidden" name="target_type" value="payment">

                                        <div class="flex flex-col gap-3">
                                            <div>
                                                <p class="font-semibold text-gray-900 dark:text-gray-100">Remind After</p>
                                                <p class="text-sm text-gray-500 dark:text-gray-400">Set payment reminder</p>
                                            </div>

                                            <!-- RECIPIENTS -->
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Send To</label>
                                                <div class="space-y-2">
                                                    <label class="flex items-center justify-between p-3 border rounded-lg cursor-pointer transition-all"
                                                        :class="sendToClient ? 'border-purple-500 bg-purple-50 dark:bg-purple-900/30' : 'border-gray-200 dark:border-gray-600'">
                                                        <div class="flex items-center gap-3">
                                                            <input type="checkbox" name="send_to_client" value="1" x-model="sendToClient"
                                                                class="w-4 h-4 text-purple-500 rounded border-gray-300 focus:ring-purple-500">
                                                            <div>
                                                                <p class="font-medium text-gray-700 dark:text-gray-200 text-sm">{{ strtoupper($payment->client?->full_name ?? 'N/A') }}</p>
                                                                <p class="text-xs text-gray-400">{{ $payment->client?->country_code }}{{ $payment->client?->phone }}</p>
                                                            </div>
                                                        </div>
                                                        <span class="text-xs text-purple-600 bg-purple-100 dark:bg-purple-900 dark:text-purple-300 px-2 py-0.5 rounded-full">Client</span>
                                                    </label>

                                                    <label class="flex items-center justify-between p-3 border rounded-lg cursor-pointer transition-all"
                                                        :class="sendToAgent ? 'border-yellow-500 bg-yellow-50 dark:bg-yellow-900/30' : 'border-gray-200 dark:border-gray-600'">
                                                        <div class="flex items-center gap-3">
                                                            <input type="checkbox" name="send_to_agent" value="1" x-model="sendToAgent"
                                                                class="w-4 h-4 text-yellow-500 rounded border-gray-300 focus:ring-yellow-500">
                                                            <div>
                                                                <p class="font-medium text-gray-700 dark:text-gray-200 text-sm">{{ strtoupper($payment->client?->agent?->name ?? 'N/A') }}</p>
                                                                <p class="text-xs text-gray-400">{{ $payment->client?->agent?->phone_number }}</p>
                                                            </div>
                                                        </div>
                                                        <span class="text-xs text-yellow-600 bg-yellow-100 dark:bg-yellow-900 dark:text-yellow-300 px-2 py-0.5 rounded-full">Agent</span>
                                                    </label>

                                                    <p x-show="!sendToClient && !sendToAgent" x-cloak class="text-xs text-red-500 mt-1">
                                                        <svg class="w-3 h-3 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                        </svg>
                                                        Please select at least one recipient
                                                    </p>
                                                </div>
                                            </div>

                                            <!-- MESSAGE -->
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Message</label>
                                                <textarea
                                                    name="message"
                                                    x-model="message"
                                                    @input="limitWords()"
                                                    rows="3"
                                                    class="w-full border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none resize-none"
                                                    placeholder="Enter your reminder message"></textarea>
                                                <div class="flex justify-end mt-1">
                                                    <p class="text-xs" :class="wordCount >= maxWords ? 'text-red-500 font-medium' : 'text-gray-400'">
                                                        <span x-text="wordCount"></span>/<span x-text="maxWords"></span> words
                                                        <span x-show="wordCount >= maxWords" class="ml-1">limit reached</span>
                                                    </p>
                                                </div>
                                            </div>

                                            <!-- Frequency Toggle -->
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Frequency</label>
                                                <div class="grid grid-cols-2 gap-2">
                                                    <button type="button" @click="frequency = 'once'"
                                                        :class="frequency === 'once' 
                        ? 'border-purple-500 bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300' 
                        : 'border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:border-gray-300'"
                                                        class="border-2 rounded-lg p-3 text-left transition">
                                                        <div class="flex items-center gap-2">
                                                            <div :class="frequency === 'once' ? 'border-purple-500' : 'border-gray-300 dark:border-gray-500'"
                                                                class="w-4 h-4 rounded-full border-2 flex items-center justify-center">
                                                                <div x-show="frequency === 'once'" class="w-2 h-2 bg-purple-500 rounded-full"></div>
                                                            </div>
                                                            <div>
                                                                <p class="font-medium text-sm">One-time</p>
                                                                <p class="text-xs text-gray-500 dark:text-gray-400">Send now only</p>
                                                            </div>
                                                        </div>
                                                    </button>

                                                    <button type="button" @click="frequency = 'auto'"
                                                        :class="frequency === 'auto' 
                        ? 'border-purple-500 bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300' 
                        : 'border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:border-gray-300'"
                                                        class="border-2 rounded-lg p-3 text-left transition">
                                                        <div class="flex items-center gap-2">
                                                            <div :class="frequency === 'auto' ? 'border-purple-500' : 'border-gray-300 dark:border-gray-500'"
                                                                class="w-4 h-4 rounded-full border-2 flex items-center justify-center">
                                                                <div x-show="frequency === 'auto'" class="w-2 h-2 bg-purple-500 rounded-full"></div>
                                                            </div>
                                                            <div>
                                                                <p class="font-medium text-sm">Auto-repeat</p>
                                                                <p class="text-xs text-gray-500 dark:text-gray-400">Schedule recurring</p>
                                                            </div>
                                                        </div>
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Recurring Options (shown when auto-repeat selected) -->
                                            <div x-show="frequency === 'auto'" x-collapse class="space-y-3">
                                                <!-- Preset Buttons -->
                                                <div class="flex flex-wrap gap-2">
                                                    <button type="button" @click="intervalPreset = 'daily'; repeatValue = 1; repeatUnit = 'days'"
                                                        :class="intervalPreset === 'daily' 
                        ? 'bg-gray-800 dark:bg-gray-200 text-white dark:text-gray-800' 
                        : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'"
                                                        class="px-3 py-1.5 rounded-full text-xs font-medium transition">
                                                        Daily
                                                    </button>
                                                    <button type="button" @click="intervalPreset = 'every3days'; repeatValue = 3; repeatUnit = 'days'"
                                                        :class="intervalPreset === 'every3days' 
                        ? 'bg-gray-800 dark:bg-gray-200 text-white dark:text-gray-800' 
                        : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'"
                                                        class="px-3 py-1.5 rounded-full text-xs font-medium transition">
                                                        Every 3 days
                                                    </button>
                                                    <button type="button" @click="intervalPreset = 'weekly'; repeatValue = 7; repeatUnit = 'days'"
                                                        :class="intervalPreset === 'weekly' 
                        ? 'bg-gray-800 dark:bg-gray-200 text-white dark:text-gray-800' 
                        : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'"
                                                        class="px-3 py-1.5 rounded-full text-xs font-medium transition">
                                                        Weekly
                                                    </button>
                                                </div>

                                                <!-- Custom Inputs -->
                                                <div class="grid grid-cols-2 gap-3">
                                                    <div>
                                                        <label class="text-xs text-gray-500 dark:text-gray-400 mb-1 block">Repeat Every</label>
                                                        <div class="flex gap-1">
                                                            <input type="number" x-model="repeatValue" min="1" max="30"
                                                                @input="intervalPreset = 'custom'"
                                                                class="w-14 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg px-2 py-2 text-center text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                                            <select x-model="repeatUnit"
                                                                @change="intervalPreset = 'custom'"
                                                                class="flex-1 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg px-2 py-2 text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                                                <option value="hours">Hours</option>
                                                                <option value="days">Days</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <label class="text-xs text-gray-500 dark:text-gray-400 mb-1 block">Max Reminders</label>
                                                        <input type="number" x-model="maxReminders" min="1" max="10"
                                                            class="w-full border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg px-2 py-2 text-center text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                                    </div>
                                                </div>

                                                <!-- Preview -->
                                                <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-3">
                                                    <p class="text-xs text-purple-600 dark:text-purple-400 font-medium">
                                                        <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                        <span x-text="maxReminders"></span> reminders, every <span x-text="repeatValue"></span> <span x-text="repeatUnit"></span>
                                                    </p>
                                                </div>
                                            </div>

                                            <!-- Hidden inputs for form submission -->
                                            <input type="hidden" name="frequency" :value="frequency">
                                            <input type="hidden" name="value" :value="repeatValue">
                                            <input type="hidden" name="unit" :value="repeatUnit">
                                            <input type="hidden" name="max_reminder" :value="frequency === 'auto' ? maxReminders : 1">

                                            <!-- Send Button -->
                                            <button type="submit"
                                                :disabled="loading || (!sendToClient && !sendToAgent)"
                                                class="w-full mt-3 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                                                <svg x-show="!loading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                                </svg>
                                                <svg x-show="loading" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                </svg>
                                                <span x-text="loading ? 'Scheduling...' : 'Send & Schedule'"></span>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.add('hidden');
            });

            document.querySelectorAll('[id^="tab-"]').forEach(btn => {
                btn.classList.remove('text-blue-600', 'border-blue-600', 'dark:text-blue-400', 'dark:border-blue-400');
                btn.classList.add('text-gray-500', 'border-transparent', 'dark:text-gray-400');
            });

            document.getElementById('content-' + tabName).classList.remove('hidden');

            const activeBtn = document.getElementById('tab-' + tabName);
            activeBtn.classList.remove('text-gray-500', 'border-transparent', 'dark:text-gray-400');
            activeBtn.classList.add('text-blue-600', 'border-blue-600', 'dark:text-blue-400', 'dark:border-blue-400');
        }

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('Payment URL copied to clipboard!');
            }).catch(err => {
                console.error('Failed to copy:', err);
            });
        }

        function confirmCancel() {
            if (confirm('Are you sure you want to cancel this payment?')) {
                window.location.href = `/payments/{{ $payment->id }}/cancel`;
            }
        }

        function toggleSendOptions() {
            const dropdown = document.getElementById('sendOptionsDropdown');
            dropdown.classList.toggle('hidden');
        }

        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('sendOptionsDropdown');
            const button = document.getElementById('sendOptionsButton');

            if (dropdown && button && !dropdown.contains(event.target) && !button.contains(event.target)) {
                dropdown.classList.add('hidden');
            }
        });

        function sendViaEmail() {
            alert('Email sending feature coming soon!');
            toggleSendOptions();
        }

        function sendViaSMS() {
            alert('SMS sending feature coming soon!');
            toggleSendOptions();
        }

        function copyPaymentLink() {
            const link = '{{ route("payment.link.show", ["companyId" => $payment->agent->branch->company_id, "voucherNumber" => $payment->voucher_number]) }}';
            navigator.clipboard.writeText(link).then(() => {
                alert('Payment link copied to clipboard!');
                toggleSendOptions();
            }).catch(err => {
                console.error('Failed to copy:', err);
            });
        }
    </script>
</x-app-layout>