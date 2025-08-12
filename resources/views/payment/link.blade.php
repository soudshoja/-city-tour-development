<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script>
        // Check localStorage for the dark mode setting before the page is fully loaded
        if (localStorage.getItem('darkMode') === 'true') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700&display=swap" rel="stylesheet">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="icon" type="image/x-icon" href="{{ asset('images/City0logo.svg') }}" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap"
        rel="stylesheet" />

    <!-- CSS -->
    @vite(['resources/css/app.css'])

    <style>
        input[type="checkbox"].disabled-checkbox {
            cursor: not-allowed;
            /* Change cursor to indicate it's not clickable */
            opacity: 0.6;
            /* Reduce opacity to indicate it's disabled */
            background-color: #e2e8f0;
            /* Light gray background to show it's disabled */
        }

        tr.disabled-row {
            cursor: not-allowed;
            /* Change cursor to indicate the row is not clickable */
            opacity: 0.6;
            /* Reduce opacity to indicate it's disabled */
            background-color: #e2e8f0;
            /* Light gray background to show the row is disabled */
        }

        /* Make the disabled checkbox also look like it's disabled */
        tr.disabled-row input[type="checkbox"] {
            cursor: not-allowed;
            /* Prevent interaction */
            opacity: 1;
            /* Keep checkbox opacity full */
        }
    </style>
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.slim.js"
        integrity="sha256-UgvvN8vBkgO0luPSUl2s8TIlOSYRoGFAX4jlCIm9Adc=" crossorigin="anonymous"></script>

</head>

<body class="overflow-y-auto font-nunito antialiased bg-gray-100">

    @if (session('status'))
    <div class="bg-green-100 text-green-700 p-4 rounded mb-4">
        {{ session('status') }}
    </div>
    @endif

    @if (session('error'))
    <div class="bg-red-100 text-red-700 p-4 rounded mb-4">
        {{ session('error') }}
    </div>
    @endif

    <div class="max-w-4xl mx-auto p-8 bg-white shadow-lg rounded-lg">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">INVOICE</h1>
                <p class="text-sm text-gray-600">Payment Voucher #{{$payment->voucher_number}}</p>
                <p class="text-sm text-gray-600">Date: {{ $payment->created_at->format('d M, Y') }}</p>
            </div>
            <div class="text-right">
                <h2 class="text-xl font-bold text-gray-800"></h2>
                <p class="text-sm text-gray-600"></p>
                <p class="text-sm text-gray-600"></p>
                <p class="text-sm text-gray-600"></p>
            </div>
        </div>

        <!-- Client Details -->
        <div class="mb-8">
            <h3 class="text-lg font-bold text-gray-800">Bill To:</h3>
            <p class="text-sm text-gray-600">

            </p>
            <p class="text-sm text-gray-600"></p>
            <p class="text-sm text-gray-600"></p>
        </div>

        @if (false)
        <!-- Full Payment Table -->
        <h3 class="text-lg font-bold text-gray-800 mb-4">Full Payment ()</h3>
        <table class="min-w-full mb-8 border border-gray-200">
            <thead>
                <tr class="bg-gray-200 text-gray-600 text-sm font-bold">
                    <th class="px-4 py-2 border">Item Description</th>
                    <th class="px-4 py-2 border">Quantity</th>
                    <th class="px-4 py-2 border">Price</th>
                    <th class="px-4 py-2 border">Total</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
        @endif

        @if (false)
        <!-- Partial Payment Table -->
        <h3 class="text-lg font-bold text-gray-800 mb-4">Partial Payment ({{ $invoice->currency }})</h3>

        <div class="mb-4">
            <h4 class="text-lg font-bold text-gray-800">Task Descriptions</h4>
            <ul class="list-disc pl-6">
                @foreach ($invoiceDetails as $detail)
                <li class="text-sm text-gray-700">
                    <strong>{{ $detail->task_description ?? 'N/A' }}</strong>:
                    {{ $detail->quantity ?? 0 }} (Note: {{ $detail->client_notes ?? 'N/A' }})
                </li>
                @endforeach
            </ul>
        </div>

        <table class="min-w-full mb-8 border border-gray-200">
            <thead>
                <tr class="bg-gray-200 text-gray-600 text-sm font-bold">
                    <th class="px-4 py-2 border">Select</th>
                    <th class="px-4 py-2 border">Expiry Date</th>
                    <th class="px-4 py-2 border">Status</th>
                    <th class="px-4 py-2 border">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoicePartials as $partial)
                <tr class="text-sm text-gray-700 @if ($partial->status === 'paid') disabled-row @endif">
                    <td class="px-4 py-2 border">
                        <input type="checkbox" class="partial-checkbox" name="selected_partials[]"
                            value="{{ $partial->id }}" data-amount="{{ $partial->amount }}"
                            @if ($partial->status === 'paid') disabled
                        checked
                        class="disabled-checkbox" @endif
                        @if ($partial->status !== 'paid') checked @endif>
                    </td>
                    <td class="px-4 py-2 border">
                        {{ \Carbon\Carbon::parse($partial->expiry_date)->format('d M, Y') ?? 'N/A' }}
                    </td>
                    <td class="px-4 py-2 border">{{ $partial->status }}</td>
                    <td class="px-4 py-2 border">{{ number_format($partial->amount ?? 0, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        @if (false)
        <!-- Split Payment Table -->
        <h3 class="text-lg font-bold text-gray-800 mb-4">Split Payment ({{ $invoice->currency }})</h3>

        <div class="mb-4">
            <h4 class="text-lg font-bold text-gray-800">Task Descriptions</h4>
            <ul class="list-disc pl-6">
                @foreach ($invoiceDetails as $detail)
                <li class="text-sm text-gray-700">
                    <strong>{{ $detail->task_description ?? 'N/A' }}</strong>:
                    {{ $detail->quantity ?? 0 }}
                </li>
                @endforeach
            </ul>
        </div>

        <table class="min-w-full mb-8 border border-gray-200">
            <thead>
                <tr class="bg-gray-200 text-gray-600 text-sm font-bold">
                    <th class="px-4 py-2 border">Link</th>
                    <th class="px-4 py-2 border">Client</th>
                    <th class="px-4 py-2 border">Expiry Date</th>
                    <th class="px-4 py-2 border">Status</th>
                    <th class="px-4 py-2 border">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoicePartials as $partial)
                <tr class="text-sm text-gray-700">

                    <td class="px-4 py-2 border">
                        <a href="{{ url('invoice/partial/' . $partial->invoice_number . '/' . $partial->client_id. '/' . $partial->id) }}"
                            class="text-blue-500 underline" target="_blank">
                            View Details
                        </a>
                    </td>
                    <td class="px-4 py-2 border">{{ $partial->client->first_name }}</td>
                    <td class="px-4 py-2 border">
                        {{ \Carbon\Carbon::parse($partial->expiry_date)->format('d M, Y') ?? 'N/A' }}
                    </td>
                    <td class="px-4 py-2 border">{{ $partial->status }}</td>
                    <td class="px-4 py-2 border">{{ number_format($partial->amount ?? 0, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        <!-- Payment Details -->
        <div class="mb-8 inline-flex gap-2">
            <form action="" method="POST">
                @csrf
                <input type="hidden" name="client" value=''>
                <input type="hidden" name="invoiceNumber" value=''>
                <button type="submit"
                    class="city-light-yellow hover:text-[#004c9e] rounded-full flex items-center justify-center peer-checked:ring-2 peer-checked:ring-blue-500 peer-checked:bg-blue-100 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 transition gap-2 hover:bg-[#f7b14f] hover:shadow-xl hover:text-white">
                    Send Invoice To Client
                </button>
            </form>
            <form id="paymentForm"
                action=""
                method="POST">
                @csrf

                <input type="hidden" id="totalAmountInput" name="total_amount"
                    value="">
                <input type="hidden" name="client_email" value="">
                <input type="hidden" name="client_name" value="">
                <input type="hidden" name="client_phone" value="">
                <input type="hidden" name="payment_method" value="">

                <div class="flex items-center gap-2">
                    <button type="submit" id="payNowBtn"
                        class="city-light-yellow hover:text-[#004c9e] rounded-full flex items-center justify-center peer-checked:ring-2 peer-checked:ring-blue-500 peer-checked:bg-blue-100 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 transition gap-2 hover:bg-[#f7b14f] hover:shadow-xl hover:text-white">
                        Pay Now
                    </button>
                    <span id="totalAmountDisplay" class="text-lg font-semibold text-gray-800">
                        {{ number_format(10,2) }}
                    </span>
                </div>
                <div id="loadingSpinner" class="hidden mt-2">
                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                    Processing...
                </div>
            </form>

            @if (auth()->user() &&
            (auth()->user()->role === 'admin' || auth()->user()->role === 'company' || auth()->user()->role === 'agent'))
            <div class="flex gap-2 mt-2" id="invoice-link">
                <p>
                    {{ route('invoice.show', ['invoiceNumber' => $invoice->invoice_number]) }}
                </p>
                <button
                    onclick="copyToClipboard('{{ route('invoice.show', ['invoiceNumber' => $invoice->invoice_number]) }}')">
                    <img src="{{ asset('images/svg/copy.svg') }}" alt="Copy Link" class="w-4 h-4">
                </button>

            </div>
            @endif
            <!-- <span class="text-green-600 font-bold">PAID</span> -->
        </div>
        <div class="flex justify-between items-center">
            <div class="text-sm">
                <p class="text-gray-600">If you have any questions about this invoice, please contact:</p>
                <p class="text-gray-600">,

                </p>
            </div>
            <div class="text-right">
                <p class="font-bold text-gray-800">Thank you for your business!</p>
            </div>
        </div>
    </div>
    @if (false)
    <div class="max-w-4xl mx-auto p-8 bg-white shadow-lg rounded-lg mt-6">
        <div class="invoice">
            <div class="payment-status bg-green-100 p-6 rounded-lg mt-4">
                <h3 class="text-xl font-semibold text-green-700 mb-2">Payment Receipt</h3>
            </div>

            <table class="min-w-full mb-8 border border-gray-200">
                <thead>
                    <tr class="bg-gray-200 text-gray-600 text-sm font-bold">
                        <th class="px-4 py-2 border">Receipt #</th>
                        <th class="px-4 py-2 border">Reference</th>
                        <th class="px-4 py-2 border">Payment Date</th>
                        <th class="px-4 py-2 border">Payment Gateway</th>
                        <th class="px-4 py-2 border">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="text-sm text-gray-700">
                        <td class="px-4 py-2 border">Payment Voucher</td>
                        <td class="px-4 py-2 border">Payment reference</td>
                        <td class="px-4 py-2 border"> payment date </td>
                        <td class="px-4 py-2 border">payment gateway</td>
                        <td class="px-4 py-2 border">amount</td>
                    </tr>
                </tbody>
            </table>

            <div class="flex justify-end mb-8">
                <div class="w-1/3 text-sm">
                    <div class="flex justify-between py-2 border-b border-gray-200">
                        <span>Balance:</span>
                        <span id="balance"></span>
                    </div>
                </div>
            </div>


            <div class="thank-you mt-6 bg-gray-100 p-6 rounded-lg">
                <h4 class="text-xl font-semibold text-gray-800 mb-2">Thank You for Your Payment!</h4>
                <p class="text-lg text-gray-600">We appreciate your business! A confirmation email has been sent to
                    your address.</p>
            </div>
        </div>



    </div>
    @endif

</body>

</html>