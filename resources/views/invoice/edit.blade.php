<x-app-layout>
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
    <div class="flex justify-between items-center mb-8">
    <div>
      <h3 class="text-lg font-bold text-gray-800">Bill To:</h3>
      <p class="text-sm text-gray-600">{{ $invoice->client->name ?? 'N/A' }}</p>
      <p class="text-sm text-gray-600">{{ $invoice->client->address ?? 'N/A' }}</p>
      <p class="text-sm text-gray-600">{{ $invoice->client->email ?? 'N/A' }}</p>
    </div>
      <div class="text-right">
        <h3 class="text-lg font-bold text-gray-800">Payment Detail:</h3>
        <p class="text-sm text-gray-600"><span>Payment Date: </span>{{ $payment->payment_date ?? 'N/A' }}</p>
        <p class="text-sm text-gray-600"><span>Payment Ref: </span>{{ $payment->voucher_number ?? 'N/A' }}</p>
        <p class="text-sm text-gray-600"><span>Payment Method: </span>{{ $payment->payment_method ?? 'N/A' }}</p>
      </div>
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

    <div class="relative flex items-center h-12">
                    <form id="uploadTaskForm" action="{{ route('tasksupload.import') }}" method="POST"
                        enctype="multipart/form-data" class="inline-flex">
                        @csrf
                        <input id="pdfInput" type="file" accept=".pdf" name="task_file" class="hidden"
                            onchange="uploadTask()" />

                        <button id="uploadTaskButton" type="button"
                            onclick="document.getElementById('pdfInput').click();"
                            class="h-full flex items-center px-4 py-2 bg-black text-white rounded-lg hover:bg-gray-700 focus:outline-none">
                            <svg class="w-5 h-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v16m8-8H4" />
                            </svg>
                            <span>Attached Payment Receipt</span>
                        </button>
                    </form>
                </div>

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

  <script>
                    function uploadTask() {
                        console.log(document.getElementById('loadingScreen'));
                        document.getElementById('loadingScreen').style.display = 'block';
                        // Check if a file has been selected
                        const fileInput = document.getElementById('pdfInput');
                        if (fileInput.files.length > 0) {
                            // Submit the form once a file is selected
                            document.getElementById('uploadTaskForm').submit();
                        }
                    }
 </script>
</x-app-layout>