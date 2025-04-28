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
                <p class="text-sm text-gray-600">Invoice #{{ $invoice->invoice_number }}</p>
                <p class="text-sm text-gray-600">Date: {{ $invoice->created_at->format('d M, Y') }}</p>
            </div>
            <div class="text-right">
                <h2 class="text-xl font-bold text-gray-800">{{ $invoice->agent->branch->company->name }}</h2>
                <p class="text-sm text-gray-600">{{ $invoice->agent->branch->company->address }}</p>
                <p class="text-sm text-gray-600">{{ $invoice->agent->branch->company->phone }}</p>
                <p class="text-sm text-gray-600">{{ $invoice->agent->branch->company->email }}</p>
            </div>
        </div>

        <!-- Client Details -->
        <div class="mb-8">
            <h3 class="text-lg font-bold text-gray-800">Bill To:</h3>
            <p class="text-sm text-gray-600">{{ $invoice->client->name ?? 'N/A' }}</p>
            <p class="text-sm text-gray-600">{{ $invoice->client->address ?? 'N/A' }}</p>
            <p class="text-sm text-gray-600">{{ $invoice->client->email ?? 'N/A' }}</p>
        </div>

        @if ($invoice->payment_type === 'full')
            <!-- Full Payment Table -->
            <h3 class="text-lg font-bold text-gray-800 mb-4">Full Payment ({{ $invoice->currency }})</h3>
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
                    @foreach ($invoiceDetails as $detail)
                        <tr class="text-sm text-gray-700">
                            <td class="px-4 py-2 border">
                                {{ $detail->task_description ?? 'N/A' }}
                                <p>
                                    <br>Info: {{ $detail->task->additional_info }}
                                    <br>Type: {{ ucfirst($detail->task->type) }}
                                    <br>Venue: {{ $detail->task->venue }}
                                </p>
                            </td>
                            <td class="px-4 py-2 border">{{ $detail->quantity ?? 1 }}</td>
                            <td class="px-4 py-2 border">{{ number_format($detail->task_price ?? 0, 2) }}</td>
                            <td class="px-4 py-2 border">
                                {{ number_format(($detail->quantity ?? 1) * ($detail->task_price ?? 0), 2, '.', ',') }}

                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if ($invoice->payment_type === 'partial')
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

        @if ($invoice->payment_type === 'split')
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
                                <a href="{{ url('invoice/partial/' . $partial->invoice_number . '/' . $partial->client_id . '/' . $partial->id) }}"
                                    class="text-blue-500 underline" target="_blank">
                                    View Details
                                </a>
                            </td>
                            <td class="px-4 py-2 border">{{ $partial->client->name }}</td>
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




        <!-- Totals Section -->
        <div class="flex justify-end mb-8">
            <div class="w-1/3 text-sm">
                <div class="flex justify-between py-2 border-b border-gray-200">
                    <span>Subtotal:</span>
                    <span>{{ number_format($invoice->amount, 2) }}</span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-200">
                    <span>Tax ({{ $invoice->tax_rate }}%):</span>
                    <span>{{ number_format($invoice->tax, 2) }}</span>
                </div>
                <div class="flex justify-between py-2 font-bold text-gray-800">
                    <span>Total:</span>
                    <span>{{ number_format($invoice->amount, 2) }}</span>
                </div>
            </div>
        </div>

        <!-- Payment Details -->
        <div class="mb-8 inline-flex gap-2">
            @if ($invoice->status === 'unpaid' || $invoice->status === 'partial')
                @if (auth()->check())
                    <form action="{{ route('whatsapp.send') }}" method="POST">
                        @csrf
                        <input type="hidden" name="client" value='{{ $invoice->client }}'>
                        <input type="hidden" name="invoiceNumber" value='{{ $invoice->invoice_number }}'>
                        <button type="submit"
                            class="city-light-yellow hover:text-[#004c9e] rounded-full flex items-center justify-center peer-checked:ring-2 peer-checked:ring-blue-500 peer-checked:bg-blue-100 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 transition gap-2 hover:bg-[#f7b14f] hover:shadow-xl hover:text-white">
                            Send Invoice To Client
                        </button>
                    </form>
                @endif
                <form id="paymentForm"
                    action="{{ route('payment.create', ['invoiceNumber' => $invoice->invoice_number]) }}"
                    method="POST">
                    @csrf

                    <input type="hidden" id="totalAmountInput" name="total_amount"
                        value="{{ $invoicePartials->sum('amount') }}">
                    <input type="hidden" name="client_email" value="{{ $invoice->client->email }}">
                    <input type="hidden" name="client_name" value="{{ $invoice->client->name }}">
                    <input type="hidden" name="client_phone" value="{{ $invoice->client->phone }}">
                    <input type="hidden" name="payment_method" value="{{ $paymentGateway }}">

                    <div class="flex items-center gap-2">
                        @if ($invoice->payment_type !== 'split')
                            <button type="submit" id="payNowBtn"
                                class="city-light-yellow hover:text-[#004c9e] rounded-full flex items-center justify-center peer-checked:ring-2 peer-checked:ring-blue-500 peer-checked:bg-blue-100 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 transition gap-2 hover:bg-[#f7b14f] hover:shadow-xl hover:text-white">
                                Pay Now
                            </button>
                        @endif
                        <span id="totalAmountDisplay" class="text-lg font-semibold text-gray-800">
                            {{ number_format($invoicePartials->where('status', 'unpaid')->sum('amount'), 2) }}
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
            @else
                <div class="flex items-center gap-2">
                    <p><span class="text-green-600 font-bold">PAID</span></p>
                    @if ($invoice->status_next !== 'refund')
                        <p><a href="{{ route('invoices.refunds.create', $invoice->id) }}"
                                class="city-light-yellow hover:text-black rounded-full flex items-center justify-center peer-checked:ring-2 peer-checked:ring-blue-500 peer-checked:bg-blue-100 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 transition gap-2 hover:bg-[#f7b14f] hover:shadow-xl hover:text-white">
                                Refund Invoice
                            </a></p>
                    @endif
                </div>

            @endif
        </div>
        <!-- Payment pdf -->
        <!-- <div class="mb-8 inline-flex gap-2">
    @if ($invoice->status === 'unpaid' || $invoice->status === 'partial')
<form action="{{ route('whatsapp.send') }}" method="POST">
        @csrf
        <input type="hidden" name="client" value='{{ json_encode($invoice->client) }}'>
        <input type="hidden" name="invoiceNumber" value='{{ $invoice->invoice_number }}'>
        <button type="submit"
            class="city-light-yellow hover:text-[#004c9e] rounded-full flex items-center justify-center peer-checked:ring-2 peer-checked:ring-blue-500 peer-checked:bg-blue-100 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 transition gap-2 hover:bg-[#f7b14f] hover:shadow-xl hover:text-white">
            Send Invoice To Client
        </button>
    </form>
    <form id="paymentForm" action="{{ route('whatsapp.send') }}" method="POST">
        @csrf
        <input type="hidden" id="totalAmountInput" name="total_amount" value="{{ $invoicePartials->sum('amount') }}">
        <input type="hidden" name="client" value='{{ json_encode($invoice->client) }}'>
        <input type="hidden" name="invoiceNumber" value='{{ $invoice->invoice_number }}'>
        <input type="hidden" name="client_email" value="{{ $invoice->client->email }}">
        <input type="hidden" name="client_name" value="{{ $invoice->client->name }}">
        <input type="hidden" name="client_phone" value="{{ $invoice->client->phone }}">
        <input type="hidden" name="payment_method" value="{{ $paymentGateway }}">

        <div class="flex items-center gap-2">
            <button type="submit" id="payNowBtn"
                class="city-light-yellow hover:text-[#004c9e] rounded-full flex items-center justify-center peer-checked:ring-2 peer-checked:ring-blue-500 peer-checked:bg-blue-100 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 transition gap-2 hover:bg-[#f7b14f] hover:shadow-xl hover:text-white">
                Pay Now
            </button>
            <span id="totalAmountDisplay" class="text-lg font-semibold text-gray-800">
                {{ number_format($invoicePartials->where('status', 'unpaid')->sum('amount'), 2) }}
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
@else
<span class="text-green-600 font-bold">PAID</span>
@endif
</div> -->
        <!-- Signatdiure Section -->
        <div class="flex justify-between items-center">
            <div class="text-sm">
                <p class="text-gray-600">If you have any questions about this invoice, please contact:</p>
                <p class="text-gray-600">{{ $invoice->agent->branch->company->name }},
                    {{ $invoice->agent->branch->company->phone }}, {{ $invoice->agent->branch->company->email }}
                </p>
            </div>
            <div class="text-right">
                <p class="font-bold text-gray-800">Thank you for your business!</p>
            </div>
        </div>
    </div>
    @if ($invoice->is_client_credit)
        <div class="max-w-4xl mx-auto p-8 bg-white shadow-lg rounded-lg mt-6 text-center">
            <p class="text-lg font-semibold text-green-500">
                This is invoice paid by client credit.
            </p>
        </div>
    @else
        @if ($invoice->status === 'paid' || $invoice->status === 'partial')
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
                            @foreach ($paidPartials as $partial)
                                <tr class="text-sm text-gray-700">
                                    <td class="px-4 py-2 border">{{ $partial->payment->voucher_number ?? 'N/A' }}</td>
                                    <td class="px-4 py-2 border">{{ $partial->payment->payment_reference ?? 'N/A' }}
                                    </td>
                                    <td class="px-4 py-2 border">
                                        {{ $partial->payment ? \Carbon\Carbon::parse($partial->payment->payment_date)->format('d M, Y H:i') : 'N/A' }}
                                    </td>
                                    <td class="px-4 py-2 border">{{ $partial->payment_gateway }}</td>
                                    <td class="px-4 py-2 border">{{ number_format($partial->amount ?? 0, 2) }}</td>
                                </tr>
                            @endforeach
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
                        <p class="text-lg text-gray-600">We appreciate your business! A confirmation email has been
                            sent to
                            your address.</p>
                    </div>
                </div>



            </div>
        @endif
    @endif

    <script>
        let invoice = @json($invoice);
        let invoicePartials = @json($invoicePartials);

        console.log('invoice', invoice);
        console.log('invoicePartials', invoicePartials);

        // Calculate the total paid amount from invoicePartials
        let totalPaidAmount = invoicePartials.filter(partial => partial.status === 'paid')
            .reduce((sum, partial) => sum + parseFloat(partial.amount), 0);


        // Calculate balance
        let balance = invoice.amount - totalPaidAmount;

        let balanceElement = document.getElementById('balance');
        if (balanceElement) {
            balanceElement.textContent = balance.toFixed(2);
        }

        const totalAmountDisplay = document.getElementById("totalAmountDisplay");
        const paymentForm = document.getElementById('paymentForm');
        const totalAmountInput = document.getElementById("totalAmountInput");
        const checkboxes = document.querySelectorAll(".partial-checkbox");

        if (invoice.payment_type === 'full') {

            console.log('full');
            // Ensure there’s only one hidden input for the 'full' payment type
            addHiddenInput("invoice_partial_id[]", invoicePartials[0]?.id, paymentForm);
        } else if (invoice.payment_type === 'partial' || invoice.payment_type === 'split') {

            console.log('partials');


            checkboxes.forEach((checkbox) => {

                const partialId = checkbox.value;

                if (checkbox.disabled) {
                    console.log('disable');
                    checkbox.checked = false; // Disabled checkboxes should remain checked
                } else {
                    console.log('cheked');
                    checkbox.checked = true; // Set all non-disabled checkboxes to checked by default
                    addHiddenInput("invoice_partial_id[]", partialId, paymentForm); // Add hidden input
                }

                ///addHiddenInput("invoice_partial_id[]", partialId, paymentForm); // Add corresponding hidden input

                calculateTotal();

                checkbox.addEventListener("change", (event) => {
                    const partialId = event.target.value;
                    console.log(partialId);
                    if (event.target.checked) {
                        // Add hidden input if checkbox is checked
                        addHiddenInput("invoice_partial_id[]", partialId, paymentForm);
                    } else {
                        // Remove hidden input if checkbox is unchecked
                        removeHiddenInput("invoice_partial_id[]", partialId, paymentForm);
                    }

                    calculateTotal();
                });
            });

        }


        function addHiddenInput(name, value, form) {
            // Check if the hidden input already exists
            console.log(name);
            let existingInput = form.querySelector(`input[name="${name}"][value="${value}"]`);
            if (!existingInput) {
                const hiddenInput = document.createElement("input");
                hiddenInput.type = "hidden";
                hiddenInput.name = name;
                hiddenInput.value = value;
                form.appendChild(hiddenInput);
            }
        }


        // Utility to remove hidden inputs
        function removeHiddenInput(name, value, form) {
            let existingInput = form.querySelector(`input[name="${name}"][value="${value}"]`);
            if (existingInput) {
                existingInput.remove();
            }
        }


        function calculateTotal() {
            let total = 0;
            checkboxes.forEach((checkbox) => {
                if (checkbox.checked) {
                    total += parseFloat(checkbox.dataset.amount || 0);
                }
            });
            totalAmountInput.value = total.toFixed(2); // Update the hidden input field
            totalAmountDisplay.textContent = total.toFixed(2);
            console.log(totalAmountInput.value);
        }


        $(document).ready(function() {
            let selectedTotal = 0;
            const selectedItems = [];

            $('.item-select').change(function() {
                const itemId = $(this).data('id');
                const itemTotal = parseFloat($(this).data('total'));

                if (this.checked) {
                    selectedTotal += itemTotal;
                    selectedItems.push(itemId);
                } else {
                    selectedTotal -= itemTotal;
                    const index = selectedItems.indexOf(itemId);
                    if (index > -1) selectedItems.splice(index, 1);
                }

                $('#selectedTotal').text(selectedTotal.toFixed(2));
                $('#selectedItems').val(selectedItems.join(','));
                $('#totalAmount').val(selectedTotal.toFixed(2));
            });
        });
    </script>
</body>

</html>
