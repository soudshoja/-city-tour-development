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
        <p class="text-sm text-gray-600">{{ $invoice->agent->branch->company->address}}</p>
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

        @if($invoice->payment_type === 'full')
    <!-- Full Payment Table -->
    <h3 class="text-lg font-bold text-gray-800 mb-4">Full Payment</h3>
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
        @endforeach
      </tbody>
    </table>
    @endif

    @if($invoice->payment_type === 'partial')
    <!-- Partial Payment Table -->
    <h3 class="text-lg font-bold text-gray-800 mb-4">Partial Payment</h3>

    <div class="mb-4">
      <h4 class="text-lg font-bold text-gray-800">Task Descriptions</h4>
      <ul class="list-disc pl-6">
        @foreach($invoiceDetails as $detail)
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
          <th class="px-4 py-2 border">Select</th>
          <th class="px-4 py-2 border">Expiry Date</th>
          <th class="px-4 py-2 border">Amount</th>
        </tr>
      </thead>
      <tbody>
        @foreach($invoicePartials as $partial)
        <tr class="text-sm text-gray-700">
          <td class="px-4 py-2 border">
            <input type="checkbox" name="selected_partials[]" value="{{ $partial->id }}" form="paymentForm">
          </td>
          <td class="px-4 py-2 border">{{ \Carbon\Carbon::parse($partial->expiry_date)->format('d M, Y') ?? 'N/A' }}</td>
          <td class="px-4 py-2 border">{{ number_format($partial->amount ?? 0, 2) }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @endif

    @if($invoice->payment_type === 'split')
    <!-- Split Payment Table -->
    <h3 class="text-lg font-bold text-gray-800 mb-4">Split Payment</h3>

    <div class="mb-4">
      <h4 class="text-lg font-bold text-gray-800">Task Descriptions</h4>
      <ul class="list-disc pl-6">
        @foreach($invoiceDetails as $detail)
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
          <th class="px-4 py-2 border">Expiry Date</th>
          <th class="px-4 py-2 border">Amount</th>
        </tr>
      </thead>
      <tbody>
        @foreach($invoicePartials as $partial)
        <tr class="text-sm text-gray-700">

          <td class="px-4 py-2 border">
              <a href="{{ url('invoice/partial/' . $partial->invoice_number . '/' . $partial->client_id) }}" class="text-blue-500 underline" target="_blank">
                  View Details
              </a>
          </td>
          <td class="px-4 py-2 border">{{ \Carbon\Carbon::parse($partial->expiry_date)->format('d M, Y') ?? 'N/A' }}</td>
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
      @if($invoice->status === 'unpaid')
      <span class="text-nn  -600 font-bold">UNPAID</span>
      @else
      <span class="text-green-600 font-bold">PAID</span>
      @endif
    </div>

    <!-- Signature Section -->
    <div class="flex justify-between items-center">
      <div class="text-sm">
        <p class="text-gray-600">If you have any questions about this invoice, please contact:</p>
        <p class="text-gray-600">{{ $invoice->agent->branch->company->name}}, {{ $invoice->agent->branch->company->phone}}, {{ $invoice->agent->branch->company->email}}</p>
      </div>
      <div class="text-right">
        <p class="font-bold text-gray-800">Thank you for your business!</p>
      </div>
    </div>
  </div>

  <script>
    $(document).ready(function () {
      let selectedTotal = 0;
      const selectedItems = [];

      $('.item-select').change(function () {
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