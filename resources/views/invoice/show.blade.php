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
  @vite(['resources/css/app.css', 'resources/css/style.css','resources/css/animate.css', 'resources/js/app.js'])

  <!-- Alpine.js -->
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3" defer></script>
  <script src="https://unpkg.com/@alpinejs/collapse@3.x.x/dist/collapse.min.js" defer></script>

  <!-- Scripts -->
  <script src="https://code.jquery.com/jquery-3.7.1.slim.js" integrity="sha256-UgvvN8vBkgO0luPSUl2s8TIlOSYRoGFAX4jlCIm9Adc=" crossorigin="anonymous"></script>

</head>

<body class="overflow-y-auto font-nunito antialiased bg-gray-100">

  @if(session('status'))
  <div class="bg-green-100 text-green-700 p-4 rounded mb-4">
    {{ session('status') }}
  </div>
  @endif

  @if(session('error'))
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
        <h2 class="text-xl font-bold text-gray-800">{{ $invoice->agent->branch->company->name}}</h2>
        <p class="text-sm text-gray-600">123 Main Street, City, Country</p>
        <p class="text-sm text-gray-600">{{ $invoice->agent->branch->company->phone}}</p>
        <p class="text-sm text-gray-600">{{ $invoice->agent->branch->company->email}}</p>
      </div>
    </div>

    <!-- Client Details -->
    <div class="mb-8">
      <h3 class="text-lg font-bold text-gray-800">Bill To:</h3>
      <p class="text-sm text-gray-600">{{ $invoice->client->name ?? 'N/A' }}</p>
      <p class="text-sm text-gray-600">{{ $invoice->client->address ?? 'N/A' }}</p>
      <p class="text-sm text-gray-600">{{ $invoice->client->email ?? 'N/A' }}</p>
    </div>

    <!-- Invoice Items -->
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
        @foreach($invoiceDetails as $detail)
        <tr class="text-sm text-gray-700">
          <td class="px-4 py-2 border">{{ $detail->task_description ?? 'N/A' }}</td>
          <td class="px-4 py-2 border">{{ $detail->quantity ?? 0 }}</td>
          <td class="px-4 py-2 border">{{ number_format($detail->task_price ?? 0, 2) }}</td>
          <td class="px-4 py-2 border">{{ number_format(($detail->quantity ?? 0) * ($detail->task_price ?? 0), 2) }}</td>
        </tr>
        <input type="hidden" name="selected_items[]" value="{{ $detail->id }}" form="paymentForm">
        @endforeach
      </tbody>
    </table>

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
      @if($invoice->status === 'unpaid')
      <form action="{{ route('whatsapp.send') }}" method="POST">
        @csrf
        <input type="hidden" name="client" value='{{ $invoice->client }}'>
        <input type="hidden" name="invoiceNumber" value='{{ $invoice->invoice_number}}'>
        <button type="submit" class="btn btn-primary">
          Send Invoice To Client
        </button>
      </form>
      <form id="paymentForm" action="{{ route('payment.create', ['invoiceNumber' => $invoice->invoice_number]) }}" method="POST">
        @csrf
        <input type="hidden" name="total_amount" value="{{ $invoice->amount }}">
        <input type="hidden" name="client_email" value="{{ $invoice->client->email }}">
        <input type="hidden" name="client_name" value="{{ $invoice->client->name }}">
        <input type="hidden" name="client_phone" value="{{ $invoice->client->phone }}">
        <input type="hidden" name="payment_method" value="payment_gateway">
        <button type="submit" id="payNowBtn" class="btn btn-primary">
          Pay Now
        </button>
        <div id="loadingSpinner" class="hidden mt-2">
          <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...
        </div>
      </form>
      @if(auth()->user() && (auth()->user()->role === 'admin' || auth()->user()->role === 'company' || auth()->user()->role === 'agent'))
      <div class="flex gap-2 mt-2" id="invoice-link">
        <p>
          {{ route('invoice.show', ['invoiceNumber' => $invoice->invoice_number]) }}
        </p>
        <button onclick="copyToClipboard('{{ route('invoice.show', ['invoiceNumber' => $invoice->invoice_number]) }}')">
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
        <p class="text-gray-600">John Doe, (123) 456-7890, john@example.com</p>
      </div>
      <div class="text-right">
        <p class="font-bold text-gray-800">Thank you for your business!</p>
      </div>
    </div>
  </div>


</body>

</html>