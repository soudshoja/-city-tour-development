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

            <!-- First Row: Value & More Options -->
            <div class="grid grid-cols-1 gap-4 mb-4 lg:grid-cols-2">
                <!-- Value Section -->
                <div class="bg-gradient-to-br from-blue-50 to-white dark:from-gray-800 dark:to-gray-850 shadow-lg sm:rounded-lg">
                    <div class="p-6 h-full flex flex-col">
                        <div class="flex items-center mb-6">
                            <div class="p-2 bg-blue-100 dark:bg-blue-900 rounded-lg mr-3">
                                <svg class="w-6 h-6 text-blue-600 dark:text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100">Payment Value</h3>
                        </div>

                        <div class="flex-1">
                            <div class="flex items-baseline justify-between mb-4">
                                <div>
                                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Total Amount</p>
                                    <p class="text-4xl font-bold text-blue-600 dark:text-blue-400">
                                        {{ number_format($payment->amount, 3) }}
                                    </p>
                                    <p class="text-lg font-semibold text-gray-600 dark:text-gray-400">{{ $payment->currency }}</p>
                                </div>
                                @if($payment->completed)
                                <span class="px-3 py-1 text-sm font-semibold text-green-800 bg-green-100 rounded-full dark:bg-green-900 dark:text-green-200">
                                    PAID
                                </span>
                                @endif
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

                            @if($payment->payment_url)
                            <div class="pt-4 mt-4 border-t border-blue-100 dark:border-gray-700">
                                <p class="text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase mb-2">Payment Link</p>
                                <div class="flex items-center gap-2 p-3 bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700">
                                    <a href="{{ route('payment.link.show', ['companyId' => $payment->agent->branch->company_id, 'voucherNumber' => $payment->voucher_number]) }}" 
                                       target="_blank" class="flex-1 text-sm text-blue-600 truncate hover:text-blue-800 dark:text-blue-400">
                                        {{ route('payment.link.show', ['companyId' => $payment->agent->branch->company_id, 'voucherNumber' => $payment->voucher_number]) }}
                                    </a>
                                    <button onclick="copyToClipboard('{{ route('payment.link.show', ['companyId' => $payment->agent->branch->company_id, 'voucherNumber' => $payment->voucher_number]) }}')"
                                        class="p-2 text-gray-500 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900 rounded dark:text-gray-400 dark:hover:text-blue-400 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
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
                            @endif
                        </div>
                    </div>
                </div>

                <!-- More Options Section -->
                <div class="bg-gradient-to-br from-purple-50 to-white dark:from-gray-800 dark:to-gray-850 shadow-lg sm:rounded-lg" style="overflow: visible;">
                    <div class="p-4 h-full flex flex-col" style="overflow: visible;">
                        <div class="flex items-center mb-4">
                            <div class="p-2 bg-purple-100 dark:bg-purple-900 rounded-lg mr-3">
                                <svg class="w-6 h-6 text-purple-600 dark:text-purple-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100">Actions</h3>
                        </div>

                        <div class="flex-1 space-y-4" style="overflow: visible;">
                            <div class="flex justify-between items-center" style="position: relative; overflow: visible;">
                                <div>
                                    <p class="font-semibold text-gray-900 dark:text-gray-100">Send Payment</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Share link with customer</p>
                                </div>
                                <div class="relative inline-block text-left" style="z-index: 50;">
                                    <button type="button" onclick="toggleSendOptions()" id="sendOptionsButton"
                                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700 mt-3 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 transition-colors shadow-sm">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                        </svg>
                                        Send Link
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </button>

                                    <div id="sendOptionsDropdown" class="absolute right-0 hidden w-56 mt-2 origin-top-right bg-white rounded-lg shadow-xl dark:bg-gray-700 ring-1 ring-black ring-opacity-5" style="z-index: 100;">
                                        <div class="py-1" role="menu" aria-orientation="vertical">
                                            <form action="{{ route('resayil.share-payment-link') }}" method="POST" class="block">
                                                @csrf
                                                <input type="hidden" name="client_id" value="{{ $payment->client_id }}">
                                                <input type="hidden" name="payment_id" value="{{ $payment->id }}">
                                                <input type="hidden" name="voucher_number" value="{{ $payment->voucher_number }}">
                                                <button type="submit" class="flex items-center w-full px-4 py-3 text-sm text-gray-700 hover:bg-green-50 dark:text-gray-200 dark:hover:bg-gray-600 transition-colors">
                                                    <svg class="w-5 h-5 mr-3 text-green-500" fill="currentColor" viewBox="0 0 24 24">
                                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                                    </svg>
                                                    WhatsApp
                                                </button>
                                            </form>
                                            <button onclick="copyPaymentLink()" class="flex items-center w-full px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-600 transition-colors">
                                                <svg class="w-5 h-5 mr-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                                </svg>
                                                Copy Link
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-3 border-t border-purple-100 dark:border-gray-700">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <p class="font-semibold text-gray-900 dark:text-gray-100">Remind After</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Set payment reminder</p>
                                    </div>
                                    <span class="px-3 py-1 text-sm font-medium text-purple-700 bg-purple-100 rounded-full dark:bg-purple-900 dark:text-purple-200">
                                        Coming Soon
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Info Section -->
            <div class="mb-4">
                <div class="bg-white dark:bg-gray-800 shadow-lg sm:rounded-lg">
                    <div class="p-4">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">Payment Information</h3>
                        
                        <div class="grid grid-cols-1 gap-10 lg:grid-cols-3">
                            <!-- Basic Info Sub-panel -->
                            <div class="col-span-2 p-3 rounded-lg shadow-lg p-6 bg-gradient-to-br from-blue-50 to-white ">
                                <h4 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-3">Basic</h4>
                                <div class="space-y-2">
                                    <div class="flex justify-between items-start">
                                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">ID/Reference</span>
                                        <span class="text-sm font-bold text-gray-900 dark:text-gray-100 text-right">
                                            {{ $payment->voucher_number }} @if($payment->payment_reference) / {{ $payment->payment_reference }} @endif
                                        </span>
                                    </div>

                                    <div class="flex justify-between items-start">
                                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Status</span>
                                        <span class="inline-flex items-center">
                                            @php
                                            $statusColors = [
                                            'completed' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                            'pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                            'initiate' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                            'failed' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                            ];
                                            $statusClass = $statusColors[$payment->status] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
                                            @endphp
                                            <span class="px-2 py-1 text-sm font-medium rounded-full {{ $statusClass }}">
                                                {{ ucfirst($payment->status) }}
                                            </span>
                                        </span>
                                    </div>

                                    <div class="flex justify-between items-start">
                                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Method</span>
                                        <span class="text-sm font-bold text-gray-900 dark:text-gray-100 text-right">
                                            {{ $payment->paymentMethod->english_name ?? $payment->payment_gateway ?? 'N/A' }}
                                        </span>
                                    </div>

                                    <div class="flex justify-between items-start">
                                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">From</span>
                                        <span class="text-sm font-bold text-gray-900 dark:text-gray-100 text-right">{{ $payment->from ?? 'N/A' }}</span>
                                    </div>

                                    <div class="flex justify-between items-start">
                                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Pay To</span>
                                        <span class="text-sm font-bold text-gray-900 dark:text-gray-100 text-right">{{ $payment->pay_to ?? 'N/A' }}</span>
                                    </div>

                                    @if($payment->auth_code)
                                    <div class="flex justify-between items-start">
                                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Auth Code</span>
                                        <span class="text-sm font-bold text-gray-900 dark:text-gray-100 text-right">{{ $payment->auth_code }}</span>
                                    </div>
                                    @endif
                                </div>

                                <!-- Dates Sub-section -->
                                <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                                    <h5 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Dates</h5>
                                    <div class="space-y-2">
                                        <div class="flex justify-between items-start">
                                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Created</span>
                                            <span class="text-sm font-bold text-gray-900 dark:text-gray-100 text-right">{{ $payment->created_at->format('d/m/Y H:i') }}</span>
                                        </div>

                                        <div class="flex justify-between items-start">
                                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Payment</span>
                                            <span class="text-sm font-bold text-gray-900 dark:text-gray-100 text-right">
                                                {{ $payment->payment_date ? $payment->payment_date->format('d/m/Y H:i') : 'N/A' }}
                                            </span>
                                        </div>

                                        @if($payment->expiry_date)
                                        <div class="flex justify-between items-start">
                                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Expiry</span>
                                            <span class="text-sm font-bold text-gray-900 dark:text-gray-100 text-right">
                                                {{ $payment->expiry_date->format('d/m/Y H:i') }}
                                            </span>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>


                            <!-- Customer Info Sub-panel -->
                            <div class="p-3 rounded-lg shadow-lg p-6 bg-gradient-to-br from-blue-50 to-white">
                                <h4 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-3">Customer</h4>
                                <div class="space-y-4">
                                    <div class="flex flex-col justify-between items-start">
                                        <span class="text-xs font-medium text-gray-600 dark:text-gray-400">Reference</span>
                                        <div class="flex flex-col items-center gap-2">
                                            <span class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ $payment->invoice_reference ?? 'N/A' }}</span>
                                            @if($payment->invoice)
                                            <a href="{{ route('invoice.show', ['companyId' => $payment->agent?->branch?->company_id, 'invoiceNumber' => $payment->invoice->invoice_number]) }}"
                                                class="text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                                <span class="text-sm">Edit</span>
                                            </a>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="flex  flex-col justify-between items-start">
                                        <span class="text-xs font-medium text-gray-600 dark:text-gray-400">Name</span>
                                        <span class="text-sm font-bold text-gray-900 dark:text-gray-100 text-right">{{ $payment->client?->full_name ?? 'N/A' }}</span>
                                    </div>

                                    <div class="flex  flex-col justify-between items-start">
                                        <span class="text-xs font-medium text-gray-600 dark:text-gray-400">Mobile</span>
                                        <span class="text-sm font-bold text-gray-900 dark:text-gray-100 text-right">{{ $payment->client?->phone ?? 'N/A' }}</span>
                                    </div>

                                    <div class="flex flex-col  justify-between items-start">
                                        <span class="text-xs font-medium text-gray-600 dark:text-gray-400">Email</span>
                                        <span class="text-sm  font-bold text-gray-900 dark:text-gray-100 text-right">{{ $payment->client?->email ?? 'N/A' }}</span>
                                    </div>

                                    <div class="flex justify-between items-start">
                                        <span class="text-xs font-medium text-gray-600 dark:text-gray-400">Notes</span>
                                        <span class="text-sm font-bold text-gray-900 dark:text-gray-100 text-right">
                                            @if($payment->notes)
                                            <button onclick="showNotes()" class="rounded-lg bg-blue-500 text-white px-3 py-1 hover:bg-blue-600">
                                                View
                                            </button>
                                            @else
                                            N/A
                                            @endif
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bank Details (Optional) -->
            @if($payment->account_number || $payment->bank_name)
            <div class="mb-4">
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
                        <!-- <button onclick="switchTab('history')"
                            id="tab-history"
                            class="px-6 py-3 text-sm font-medium text-gray-500 border-b-2 border-transparent hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300">
                            Payment History
                        </button> -->
                    </nav>
                </div>

                <div class="p-6">
                    <div id="content-items" class="tab-content">
                        <div class="py-12 text-center">
                            <svg class="w-16 h-16 mx-auto mb-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="text-lg font-medium text-gray-600 dark:text-gray-400">Payment Item Details</p>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Coming soon...</p>
                        </div>
                    </div>

                    <div id="content-transactions" class="hidden tab-content">
                        <div class="py-12 text-center">
                            <svg class="w-16 h-16 mx-auto mb-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="text-lg font-medium text-gray-600 dark:text-gray-400">Transaction Details</p>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Coming soon...</p>
                        </div>
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
    </div>

    @if($payment->notes)
    <div id="notesModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" aria-hidden="true" onclick="hideNotes()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl dark:bg-gray-800 sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="px-4 pt-5 pb-4 bg-white dark:bg-gray-800 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:mt-0 sm:text-left">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100" id="modal-title">
                                Internal Notes
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $payment->notes }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" onclick="hideNotes()"
                        class="inline-flex justify-center w-full px-4 py-2 text-base font-medium text-white bg-blue-600 border border-transparent rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

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

        function showNotes() {
            document.getElementById('notesModal').classList.remove('hidden');
        }

        function hideNotes() {
            document.getElementById('notesModal').classList.add('hidden');
        }

        function confirmCancel() {
            if (confirm('Are you sure you want to cancel this payment?')) {
                // Add your cancel payment logic here
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