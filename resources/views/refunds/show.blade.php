<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script>
        if (localStorage.getItem('darkMode') === 'true') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <title>{{ config('app.name', 'Laravel') }}</title>

    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="icon" type="image/x-icon" href="{{ asset('images/City0logo.svg') }}" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css'])
</head>

@php
    $isRtl = app()->getLocale() === 'ar';
    $textAlign = $isRtl ? 'text-right' : 'text-left';
    $textAlignReverse = $isRtl ? 'text-left' : 'text-right';

    $client = $refund->originalInvoice?->client ?? $refund->refundDetails->first()?->task?->client;
    $company = $refund->company;

    $isCollectCharges = !empty($refund->refund_invoice_id);
    $refundInvoice = $isCollectCharges ? $refund->invoice : null;

    $totalCredited = 0;
    $totalUsed = 0;
    $availableBalance = 0;
    $usageHistory = collect();
    
    if (!$isCollectCharges) {
        $totalCredited = \App\Models\Credit::where('refund_id', $refund->id)
            ->where('type', \App\Models\Credit::REFUND)
            ->sum('amount');
        
        $totalUsed = abs(\App\Models\Credit::where('refund_id', $refund->id)
            ->where('type', \App\Models\Credit::INVOICE)
            ->sum('amount'));
        
        $availableBalance = $totalCredited - $totalUsed;
        
        $usageHistory = \App\Models\Credit::where('refund_id', $refund->id)
            ->where('type', \App\Models\Credit::INVOICE)
            ->with(['invoice', 'invoicePartial'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    $totalOriginalPrice = $refund->refundDetails->sum('original_invoice_price');
    $totalRefundFee = $refund->refundDetails->sum('refund_fee_to_client');
    $totalRefundToClient = $refund->refundDetails->sum('total_refund_to_client');
    $totalSupplierCharge = $refund->refundDetails->sum('supplier_charge');
    $totalNewProfit = $refund->refundDetails->sum('new_task_profit');
@endphp

<body class="overflow-y-auto font-nunito antialiased bg-gray-100 py-10">

@if ($isCollectCharges)
    @if ($refund->status === 'completed' || $refundInvoice?->status === 'paid')
        <div class="max-w-4xl mx-auto bg-gradient-to-r from-[#1b3f20] to-[#1d832a] p-6 flex items-center text-white rounded-lg mb-4">
            <div class="flex items-center justify-between text-white w-full">
                <div>
                    <p class="text-3xl font-bold">REFUND CHARGES COLLECTED</p>
                    <p class="text-sm mt-1">Payment received for refund charges</p>
                </div>
                <div class="text-right">
                    <p class="text-sm">Amount Collected</p>
                    <p class="text-2xl font-bold">{{ number_format($refund?->total_nett_refund ?? $totalRefundToClient, 3) }} {{ $refund->originalInvoice?->currency ?? 'KWD' }}</p>
                </div>
            </div>
        </div>
    @else
        <div class="max-w-4xl mx-auto bg-gradient-to-r from-[#e65100] to-[#f57c00] p-6 flex items-center text-white rounded-lg mb-4">
            <div class="flex items-center justify-between text-white w-full">
                <div>
                    <p class="text-3xl font-bold">PENDING PAYMENT</p>
                    <p class="text-sm mt-1">Awaiting payment for refund charges</p>
                </div>
                <div class="text-right">
                    <p class="text-sm">Amount to Collect</p>
                    <p class="text-2xl font-bold">{{ number_format($refundInvoice?->amount ?? $totalRefundToClient, 3) }} {{ $refund->originalInvoice?->currency ?? 'KWD' }}</p>
                </div>
            </div>
        </div>
    @endif
@else
    @if ($refund->status === 'completed')
        <div class="max-w-4xl mx-auto bg-gradient-to-r from-[#1b3f20] to-[#1d832a] p-6 flex items-center text-white rounded-lg mb-4">
            <div class="flex items-center justify-between text-white w-full">
                <div>
                    <p class="text-3xl font-bold">REFUND COMPLETED</p>
                    <p class="text-sm mt-1">Credit has been added to client's account</p>
                </div>
                <div class="text-right">
                    <p class="text-sm">Available Balance</p>
                    <p class="text-2xl font-bold">{{ number_format($availableBalance, 3) }} {{ $refund->originalInvoice?->currency ?? 'KWD' }}</p>
                </div>
            </div>
        </div>
    @elseif ($refund->status === 'pending' || $refund->status === 'processed')
        <div class="max-w-4xl mx-auto bg-gradient-to-r from-yellow-500 to-yellow-600 p-6 flex items-center text-white rounded-lg mb-4">
            <div class="flex items-center justify-between text-white w-full">
                <div>
                    <p class="text-3xl font-bold">{{ strtoupper($refund->status) }}</p>
                    <p class="text-sm mt-1">Refund is being processed</p>
                </div>
                <div class="text-right">
                    <p class="text-sm">Estimated Credit</p>
                    <p class="text-2xl font-bold">{{ number_format($totalRefundToClient, 3) }} {{ $refund->originalInvoice?->currency ?? 'KWD' }}</p>
                </div>
            </div>
        </div>
    @endif
@endif

    <div class="max-w-4xl mx-auto p-8 bg-white shadow-lg rounded-lg">
        @if (session('status'))
            <div class="bg-green-100 text-green-700 p-4 rounded mb-4">{{ session('status') }}</div>
        @endif

        @if (session('error'))
            <div class="bg-red-100 text-red-700 p-4 rounded mb-4">{{ session('error') }}</div>
        @endif

        <div class="flex justify-between items-center mb-10">
            <div class="{{ $textAlign }}">
                <h1 class="text-2xl font-bold text-gray-800">REFUND VOUCHER</h1>
                <p class="text-sm text-gray-600">{{ $refund->refund_number }}</p>
                <p class="text-sm text-gray-600">Date: {{ $refund->created_at->format('d M Y') }}</p>
            </div>

            <div>
                <img class="w-auto h-[95px] object-contain" src="{{ $company?->logo ? Storage::url($company->logo) : asset('images/UserPic.svg') }}" alt="Company logo" />
            </div>
        </div>

        <div class="flex justify-between items-start mb-8">
            <div class="{{ $textAlign }}">
                <h3 class="text-lg font-bold text-gray-800 mb-1">
                    {{ $isCollectCharges ? 'Bill To' : 'Refund To' }}
                </h3>
                @if($client)
                    <p class="text-sm text-gray-600">{{ $client->full_name }}</p>
                    <p class="text-sm text-gray-600">
                        <a href="mailto:{{ $client->email }}" class="hover:underline hover:text-blue-600">
                            {{ $client->email }}
                        </a>
                    </p>
                    <p class="text-sm text-gray-600">
                        <a href="tel:{{ $client->country_code }}{{ $client->phone }}" class="hover:underline hover:text-blue-600">
                            {{ $client->country_code }}{{ $client->phone }}
                        </a>
                    </p>
                @else
                    <p class="text-sm text-gray-600">N/A</p>
                @endif
            </div>
            <div class="max-w-xs {{ $textAlignReverse }}">
                <h2 class="text-xl font-bold text-gray-800">{{ $company?->name ?? 'Company' }}</h2>
                <p class="text-sm text-gray-600 break-words">{{ $company?->address }}</p>
                <p class="text-sm text-gray-600">
                    <a href="mailto:{{ $company?->email }}" class="hover:underline hover:text-blue-600">
                        {{ $company?->email }}
                    </a>
                </p>
                <p class="text-sm text-gray-600">
                    <a href="tel:{{ $company?->phone }}" class="hover:underline hover:text-blue-600">
                        {{ $company?->phone }}
                    </a>
                </p>
            </div>
        </div>

        <table class="w-full text-sm {{ $textAlign }} text-gray-700 border border-gray-300 mb-5">
            <thead class="bg-gray-100">
                <tr>
                    <th colspan="2" class="py-3 px-4 text-lg font-semibold {{ $textAlign }}">Refund Details</th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-t border-gray-200">
                    <td class="py-3 px-4">Refund Number</td>
                    <td class="py-3 px-4 {{ $textAlignReverse }} font-semibold">{{ $refund->refund_number }}</td>
                </tr>
                <tr class="border-t border-gray-200">
                    <td class="py-3 px-4">Original Invoice</td>
                    <td class="py-3 px-4 {{ $textAlignReverse }}">
                        @if($refund->originalInvoice)
                            <a href="{{ route('invoice.show', ['companyId' => $company?->id, 'invoiceNumber' => $refund->originalInvoice->invoice_number]) }}" 
                            class="text-blue-600 hover:underline">
                                {{ $refund->originalInvoice->invoice_number }}
                            </a>
                        @else
                            N/A
                        @endif
                    </td>
                </tr>
                <tr class="border-t border-gray-200">
                    <td class="py-3 px-4">Refund Type</td>
                    <td class="py-3 px-4 {{ $textAlignReverse }}">
                        @if($isCollectCharges)
                            <span class="px-2 py-1 bg-orange-100 text-orange-700 rounded-full text-xs font-semibold">Collect Charges</span>
                        @else
                            <span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">Credit to Client</span>
                        @endif
                    </td>
                </tr>
                <tr class="border-t border-gray-200">
                    <td class="py-3 px-4">Refund Date</td>
                    <td class="py-3 px-4 {{ $textAlignReverse }}">{{ $refund->refund_date?->format('d M Y') ?? $refund->created_at->format('d M Y') }}</td>
                </tr>
            </tbody>
        </table>

        @if($refund->refundDetails && $refund->refundDetails->count() > 0)
        <div class="mb-8">
            <h3 class="text-lg font-bold text-gray-800 mb-3 {{ $textAlign }}">Refunded Items</h3>
            <div class="overflow-x-auto border border-gray-300 rounded-lg">
                <table class="w-full text-sm {{ $textAlign }} text-gray-700">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="py-3 px-4 font-semibold {{ $textAlign }}">Item Description</th>
                            <th class="py-3 px-4 font-semibold text-right">Original Price</th>
                            <th class="py-3 px-4 font-semibold text-right">Refund Charges</th>
                            <th class="py-3 px-4 font-semibold text-right">
                                {{ $isCollectCharges ? 'Amount to Collect' : 'Credit to Client' }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($refund->refundDetails as $detail)
                        <tr class="border-t border-gray-200">
                            <td class="py-3 px-4">
                                {{ $detail->task_description ?? $detail->task?->reference ?? $detail->task?->passenger_name ?? 'Task #' . $detail->task_id }}
                                @if($detail->task?->type)
                                    <span class="text-xs text-gray-500">({{ ucfirst($detail->task->type) }})</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right">{{ number_format($detail->original_invoice_price ?? 0, 3) }}</td>
                            <td class="py-3 px-4 text-right {{ $isCollectCharges ? 'text-gray-600' : 'text-red-600' }}">
                                {{ $isCollectCharges ? '' : '-' }}{{ number_format($detail->refund_fee_to_client ?? 0, 3) }}
                            </td>
                            <td class="py-3 px-4 text-right font-semibold text-green-600">
                                {{ number_format($detail->total_refund_to_client ?? 0, 3) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 border-t-2 border-gray-300">
                        <tr>
                            <td colspan="2"></td>
                            <td class="py-3 px-4 text-right font-bold text-gray-800">
                                {{ $isCollectCharges ? 'Total to Collect:' : 'Total Refund:' }}
                            </td>
                            <td class="py-3 px-4 text-right font-bold text-green-700">
                                {{ number_format($totalRefundToClient, 3) }} {{ $refund->originalInvoice?->currency ?? 'KWD' }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        @endif

        @php $totalOriginalTaskProfit = $refund->refundDetails->sum('original_task_profit'); @endphp
        @if($isCollectCharges)
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h4 class="text-sm font-semibold text-blue-800 mb-2">Invoice Summary</h4>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Invoice Number:</span>
                            @if($refundInvoice)
                                <a href="{{ route('invoice.show', ['companyId' => $company?->id, 'invoiceNumber' => $refundInvoice->invoice_number]) }}" 
                                class="text-blue-600 hover:underline font-semibold">
                                    {{ $refundInvoice->invoice_number }}
                                </a>
                            @else
                                <span class="text-gray-500">N/A</span>
                            @endif
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Invoice Amount:</span>
                            <span class="font-semibold">{{ number_format($refundInvoice?->amount ?? $totalRefundToClient, 3) }}</span>
                        </div>
                        <div class="flex justify-between pt-2 border-t border-blue-300">
                            <span class="font-bold text-gray-800">Status:</span>
                            @if($refundInvoice?->status === 'paid')
                                <span class="font-bold text-green-600">Paid ✓</span>
                            @else
                                <span class="font-bold text-yellow-600">{{ ucfirst($refundInvoice?->status ?? 'Pending') }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <h4 class="text-sm font-semibold text-gray-800 mb-2">Refund Breakdown</h4>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Original Task Profit:</span>
                            <span>{{ number_format($totalOriginalTaskProfit, 3) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Supplier Charge:</span>
                            <span>{{ number_format($totalSupplierCharge, 3) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">New Profit:</span>
                            <span>{{ number_format($totalNewProfit, 3) }}</span>
                        </div>
                        <div class="flex justify-between pt-2 border-t border-gray-300">
                            <span class="font-bold">Total to Collect:</span>
                            <span class="font-bold text-green-600">{{ number_format($totalRefundToClient, 3) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <h4 class="text-sm font-semibold text-green-800 mb-2">Credit Summary</h4>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Total Credited:</span>
                            <span class="font-semibold text-green-700">{{ number_format($totalCredited, 3) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Total Used:</span>
                            <span class="font-semibold text-red-600">-{{ number_format($totalUsed, 3) }}</span>
                        </div>
                        <div class="flex justify-between pt-2 border-t border-green-300">
                            <span class="font-bold text-gray-800">Available Balance:</span>
                            <span class="font-bold text-green-700">{{ number_format($availableBalance, 3) }}</span>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <h4 class="text-sm font-semibold text-gray-800 mb-2">Refund Breakdown</h4>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Original Invoice Price:</span>
                            <span>{{ number_format($totalOriginalPrice, 3) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Supplier Charges:</span>
                            <span class="text-red-600">-{{ number_format($totalSupplierCharge, 3) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Agency Fee:</span>
                            <span class="text-red-600">-{{ number_format($totalNewProfit, 3) }}</span>
                        </div>
                        <div class="flex justify-between pt-2 border-t border-gray-300">
                            <span class="font-bold">Credit to Client:</span>
                            <span class="font-bold text-green-600">{{ number_format($totalRefundToClient, 3) }}</span>
                        </div>
                    </div>
                </div>
            </div>
            @if($usageHistory->count() > 0)
                <div class="mb-8">
                    <h3 class="text-lg font-bold text-gray-800 mb-3 {{ $textAlign }}">Credit Usage History</h3>
                    <div class="overflow-x-auto border border-gray-300 rounded-lg">
                        <table class="w-full text-sm {{ $textAlign }} text-gray-700">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="py-3 px-4 font-semibold {{ $textAlign }}">Date</th>
                                    <th class="py-3 px-4 font-semibold {{ $textAlign }}">Invoice</th>
                                    <th class="py-3 px-4 font-semibold {{ $textAlign }}">Description</th>
                                    <th class="py-3 px-4 font-semibold {{ $textAlignReverse }}">Amount Used</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($usageHistory as $usage)
                                <tr class="border-t border-gray-200">
                                    <td class="py-3 px-4">{{ $usage->created_at->format('d M Y H:i') }}</td>
                                    <td class="py-3 px-4">
                                        @if($usage->invoice)
                                            <a href="{{ route('invoice.show', ['companyId' => $company?->id, 'invoiceNumber' => $usage->invoice->invoice_number]) }}" 
                                            class="text-blue-600 hover:underline">
                                                {{ $usage->invoice->invoice_number }}
                                            </a>
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <td class="py-3 px-4">{{ $usage->description ?? 'Payment applied' }}</td>
                                    <td class="py-3 px-4 {{ $textAlignReverse }} font-semibold text-red-600">
                                        {{ number_format(abs($usage->amount), 3) }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @elseif($totalCredited > 0)
                <div class="mb-8 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <p class="text-sm text-blue-700">
                        <svg class="inline w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                        This refund credit has not been used yet - Credit is available for future invoices.
                    </p>
                </div>
            @endif
        @endif

        <div class="space-y-2 text-center w-full mt-6">
            <div class="text-sm text-gray-600 w-full overflow-x-auto">
                <p>If you have any questions about this refund, please contact:</p>
                <p>
                    {{ $company?->name }}, 
                    <a href="tel:{{ $company?->phone }}" class="font-semibold hover:underline hover:text-blue-600">
                        {{ $company?->phone }}</a>,
                    <a href="mailto:{{ $company?->email }}" class="font-semibold hover:underline hover:text-blue-600">
                        {{ $company?->email }}
                    </a>
                </p>
            </div>
            <p class="text-gray-800 font-semibold mt-4">Thank you for your business!</p>
        </div>
    </div>
</body>

</html>