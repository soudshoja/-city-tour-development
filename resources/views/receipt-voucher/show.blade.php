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

    <title>{{ config('app.name', 'Laravel') }}</title>

    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="icon" type="image/x-icon" href="{{ asset('images/City0logo.svg') }}" />

    @vite(['resources/css/app.css'])
</head>

@php
    $transaction = $invoiceReceipt->transaction;
    $invoice = $invoiceReceipt->invoice;
    $company = \App\Models\Company::find($transaction->company_id);
    $branch = \App\Models\Branch::find($transaction->branch_id);

    $client = $invoice->client;

    $amount = (float) $invoiceReceipt->amount;
    $status = $invoiceReceipt->status ?? 'pending';
    
    $isApproved = $status === 'approved';
    $isPending = $status === 'pending';
@endphp

<body class="overflow-y-auto font-nunito antialiased bg-gray-100">
    @if (session('status'))
        <div class="bg-green-100 text-green-700 p-4 rounded mb-4">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="bg-red-100 text-red-700 p-4 rounded mb-4">{{ session('error') }}</div>
    @endif

    @if ($isApproved)
        <div class="max-w-4xl mx-auto bg-gradient-to-r from-[#1b3f20] to-[#1d832a] p-6 my-2 text-white rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-3xl font-bold">PAYMENT RECEIVED</p>
                    <p class="text-sm mt-1">This receipt voucher has been approved</p>
                </div>
                <div class="text-right">
                    <p class="text-sm">Amount Received</p>
                    <p class="text-2xl font-bold">{{ number_format($amount, 3) }} KWD</p>
                </div>
            </div>
        </div>
    @elseif ($isPending)
        <div class="max-w-4xl mx-auto bg-gradient-to-r from-yellow-500 to-yellow-600 p-6 text-white rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-3xl font-bold">PENDING APPROVAL</p>
                    <p class="text-sm mt-1">This receipt voucher is awaiting approval</p>
                </div>
                <div class="text-right">
                    <p class="text-sm">Amount</p>
                    <p class="text-2xl font-bold">{{ number_format($amount, 3) }} KWD</p>
                </div>
            </div>
        </div>
    @else
        <div class="max-w-4xl mx-auto bg-gradient-to-r from-gray-500 to-gray-600 p-6 text-white rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-3xl font-bold">{{ strtoupper($status) }}</p>
                    <p class="text-sm mt-1">Receipt voucher status</p>
                </div>
                <div class="text-right">
                    <p class="text-sm">Amount</p>
                    <p class="text-2xl font-bold">{{ number_format($amount, 3) }} KWD</p>
                </div>
            </div>
        </div>
    @endif

    <div class="max-w-4xl mx-auto p-8 bg-white shadow-lg rounded-lg mt-2">
        <div class="flex justify-between items-center mb-6">
            <div class="text-left">
                <h1 class="text-2xl font-bold text-gray-800">RECEIPT VOUCHER</h1>
                <p class="text-sm text-gray-600">{{ $transaction->reference_number }}</p>
                <p class="text-sm text-gray-600">Date: {{ $transaction->transaction_date ? \Carbon\Carbon::parse($transaction->transaction_date)->format('d M Y') : $transaction->created_at->format('d M Y') }}</p>
            </div>

            <div>
                @if($company?->logo)
                    <img class="w-auto h-[90px] object-contain" src="{{ Storage::url($company->logo) }}" alt="Company logo" />
                @endif
                <p class="text-base font-semibold text-right">{{ $company?->name }}</p>
            </div>
        </div>

        <div class="flex justify-between items-start mb-8">
            <div class="text-left">
                <h3 class="text-lg font-bold text-gray-800 mb-1">Received From</h3>
                @if($client)
                    <p class="text-sm text-gray-600">{{ $client->full_name ?? $client->name }}</p>
                    @if($client->email)
                        <p class="text-sm text-gray-600">
                            <a href="mailto:{{ $client->email }}" class="hover:underline hover:text-blue-600">
                                {{ $client->email }}
                            </a>
                        </p>
                    @endif
                    @if($client->phone)
                        <p class="text-sm text-gray-600">
                            <a href="tel:{{ $client->country_code }}{{ $client->phone }}" class="hover:underline hover:text-blue-600">
                                {{ $client->country_code ?? '' }}{{ $client->phone }}
                            </a>
                        </p>
                    @endif
                @else
                    <p class="text-sm text-gray-600">{{ $transaction->name ?? 'N/A' }}</p>
                @endif
            </div>
            <div class="max-w-xs text-right">
                <h2 class="text-xl font-bold text-gray-800">{{ $company?->name ?? 'Company' }}</h2>
                <p class="text-sm text-gray-600 break-words">{{ $company?->address }}</p>
                @if($company?->email)
                    <p class="text-sm text-gray-600">
                        <a href="mailto:{{ $company->email }}" class="hover:underline hover:text-blue-600">
                            {{ $company->email }}
                        </a>
                    </p>
                @endif
                @if($company?->phone)
                    <p class="text-sm text-gray-600">
                        <a href="tel:{{ $company->phone }}" class="hover:underline hover:text-blue-600">
                            {{ $company->phone }}
                        </a>
                    </p>
                @endif
            </div>
        </div>

        <table class="w-full text-sm text-left text-gray-700 border border-gray-300 mb-6">
            <thead class="bg-gray-100">
                <tr>
                    <th colspan="2" class="py-3 px-4 text-lg font-semibold text-left">Receipt Details</th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-t border-gray-200">
                    <td class="py-3 px-4 w-1/3">Receipt Number</td>
                    <td class="py-3 px-4 text-right font-semibold">{{ $transaction->reference_number }}</td>
                </tr>
                <tr class="border-t border-gray-200">
                    <td class="py-3 px-4">Transaction Date</td>
                    <td class="py-3 px-4 text-right">
                        {{ $transaction->transaction_date ? \Carbon\Carbon::parse($transaction->transaction_date)->format('d M Y, h:i A')
                            : $transaction->created_at->format('d M Y, h:i A') }}
                    </td>
                </tr>
                <tr class="border-t border-gray-200">
                    <td class="py-3 px-4">Payment Method</td>
                    <td class="py-3 px-4 text-right">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                            Cash
                        </span>
                    </td>
                </tr>
                <tr class="border-t border-gray-200">
                    <td class="py-3 px-4">Status</td>
                    <td class="py-3 px-4 text-right">
                        @if($isApproved)
                            <span class="px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                Approved
                            </span>
                        @elseif($isPending)
                            <span class="px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                Pending
                            </span>
                        @else
                            <span class="px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800">
                                {{ ucfirst($status) }}
                            </span>
                        @endif
                    </td>
                </tr>
                <tr class="border-t border-gray-200">
                    <td class="py-3 px-4">Total Received</td>
                    <td class="py-3 px-4 text-right">
                        <span class="font-bold text-green-700">{{ number_format($amount, 3) }} KWD</span>
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="mb-6">
            <h3 class="text-lg font-bold text-gray-800 mb-3">Payment For</h3>
            <div class="overflow-x-auto border border-gray-300 rounded-lg">
                <table class="w-full text-sm text-left text-gray-700">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="py-3 px-4 font-semibold">Invoice Number</th>
                            <th class="py-3 px-4 font-semibold">Client</th>
                            <th class="py-3 px-4 font-semibold text-right">Invoice Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-t border-gray-200">
                            <td class="py-3 px-4">
                                <a href="{{ route('invoice.show', ['companyId' => $company?->id, 'invoiceNumber' => $invoice->invoice_number]) }}" 
                                   class="text-blue-600 hover:underline font-semibold">
                                    {{ $invoice->invoice_number }}
                                </a>
                            </td>
                            <td class="py-3 px-4">
                                {{ $invoice->client?->full_name ?? $invoice->client?->name ?? 'N/A' }}
                            </td>
                            <td class="py-3 px-4 text-right">{{ number_format($invoice->amount ?? $invoice->sub_amount, 3) }} KWD</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-2 text-center w-full mt-6 pt-6 border-t border-gray-200">
            <div class="text-sm text-gray-600 w-full">
                <p>If you have any questions about this receipt, please contact:</p>
                <p>
                    {{ $company?->name }}, 
                    @if($company?->phone)
                        <a href="tel:{{ $company->phone }}" class="font-semibold hover:underline hover:text-blue-600">
                            {{ $company->phone }}</a>,
                    @endif
                    @if($company?->email)
                        <a href="mailto:{{ $company->email }}" class="font-semibold hover:underline hover:text-blue-600">
                            {{ $company->email }}
                        </a>
                    @endif
                </p>
            </div>
            <p class="text-gray-800 font-semibold mt-4">Thank you for your payment!</p>
        </div>
    </div>
</body>

</html>