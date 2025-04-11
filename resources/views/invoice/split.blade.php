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
                <p class="text-sm text-gray-600">Due Date: {{ $invoicePartial->expiry_date->format('d M, Y') }}</p>
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
            <p class="text-sm text-gray-600">{{ $invoicePartial->client->name ?? 'N/A' }}</p>
            <p class="text-sm text-gray-600">{{ $invoicePartial->client->address ?? 'N/A' }}</p>
            <p class="text-sm text-gray-600">{{ $invoicePartial->client->email ?? 'N/A' }}</p>
        </div>

        <!-- Invoice Items -->
        <h3 class="text-lg font-bold text-gray-800 mb-4">Split Payment ({{ $invoice->currency }})</h3>
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
                        <td class="px-4 py-2 border">{{ $detail->task_description ?? 'N/A' }}
                        <p>
                                    <br>Info: {{ $detail->task->additional_info }}
                                    <br>Type: {{ ucfirst($detail->task->type) }}
                                    <br>Venue: {{ $detail->task->venue }}
                                    <br>Note: {{ $detail->client_notes ?? 'N/A' }}
                         </p></td>
                        <td class="px-4 py-2 border">{{ $detail->quantity ?? 1 }}</td>
                        <td class="px-4 py-2 border">{{ number_format($invoicePartial->amount ?? 0, 2) }}</td>
                        <td class="px-4 py-2 border">
                            {{ number_format(($detail->quantity ?? 1) * ($invoicePartial->amount ?? 0), 2, '.', ',') }}

                        </td>
                    </tr>
                    <!--  <input type="hidden" name="selected_items[]" value="{{ $detail->id }}" form="paymentForm"> -->
                @endforeach
            </tbody>
        </table>

        <!-- Totals Section -->
        <div class="flex justify-end mb-8">
            <div class="w-1/3 text-sm">
                <div class="flex justify-between py-2 border-b border-gray-200">
                    <span>Subtotal:</span>
                    <span>{{ number_format($invoicePartial->amount, 2) }}</span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-200">
                    <span>Tax ({{ $invoice->tax_rate }}%):</span>
                    <span>{{ number_format($invoice->tax, 2) }}</span>
                </div>
                <div class="flex justify-between py-2 font-bold text-gray-800">
                    <span>Total:</span>
                    <span>{{ number_format($invoicePartial->amount, 2) }}</span>
                </div>
            </div>
        </div>

        <!-- Payment Details -->
        <div class="mb-8 inline-flex gap-2">
            @if ($invoicePartial->status === 'unpaid')
                @if(!auth()->check())
                <form action="{{ route('whatsapp.send') }}" method="POST">
                    @csrf
                    <input type="hidden" name="client" value='{{ $invoicePartial->client }}'>
                    <input type="hidden" name="invoiceNumber" value='{{ $invoicePartial->invoice_number }}'>
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
                    <input type="hidden" name="total_amount" value="{{ $invoicePartial->amount }}">
                    <input type="hidden" name="client_email" value="{{ $invoicePartial->client->email }}">
                    <input type="hidden" name="client_name" value="{{ $invoicePartial->client->name }}">
                    <input type="hidden" name="client_phone" value="{{ $invoicePartial->client->phone }}">
                    <input type="hidden" name="payment_method" value="{{ $invoicePartial->payment_gateway }}">
                    <button type="submit" id="payNowBtn"
                        class="city-light-yellow hover:text-[#004c9e] rounded-full flex items-center justify-center peer-checked:ring-2 peer-checked:ring-blue-500 peer-checked:bg-blue-100 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 transition gap-2 hover:bg-[#f7b14f] hover:shadow-xl hover:text-white">
                        Pay Now
                    </button>
                    <span id="totalAmountDisplay" class="text-lg font-semibold text-gray-800">
                            {{ number_format($invoicePartial->where('id', $invoicePartial->id)->where('status', 'unpaid')->sum('amount'), 2) }}
                    </span>
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
        </div>

        <!-- Signature Section -->
        <div class="flex justify-between items-center">
            <div class="text-sm">
                <p class="text-gray-600">If you have any questions about this invoice, please contact:</p>
                <p class="text-gray-600">{{ $invoice->agent->branch->company->name }},
                    {{ $invoice->agent->branch->company->phone }}, {{ $invoice->agent->branch->company->email }}</p>
            </div>
            <div class="text-right">
                <p class="font-bold text-gray-800">Thank you for your business!</p>
            </div>
        </div>
    </div>

    <script>
        let invoicePartial = @json($invoicePartial);
        addHiddenInput("invoice_partial_id[]", invoicePartial.id, paymentForm);
        console.log("split blade");

        function addHiddenInput(name, value, form) {
            // Check if the hidden input already exists
            let existingInput = form.querySelector(`input[name="${name}"][value="${value}"]`);
            if (!existingInput) {
                const hiddenInput = document.createElement("input");
                hiddenInput.type = "hidden";
                hiddenInput.name = name;
                hiddenInput.value = value;
                form.appendChild(hiddenInput);
            }
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
