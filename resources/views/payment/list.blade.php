<x-app-layout>
    <div class="container">
    <div class="mb-5 flex flex-col md:flex-row justify-between items-center w-full space-y-4 md:space-y-0">
        <h3 class="text-2xl font-bold text-gray-700 mb-4">Agent Invoices Detail</h3>
        <a href="{{ route('agents.show', ['id' => $agent->id]) }}" class="text-blue-500 text-xs underline hover:text-blue-700">
            Back to Agent Overview
        </a>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p><strong>Name:</strong> {{ $agent->name }}</p>
                        <p><strong>Email:</strong> {{ $agent->email }}</p>
                    </div>
                    <div>
                        <p><strong>Phone:</strong> {{ $agent->phone_number }}</p>
                        <p><strong>Company:</strong> {{ $agent->company->name }}</p>
                    </div>
                    <div>
                        <p><strong>Type:</strong> {{ $agent->type }}</p>
                    </div>
                </div>

            <!-- Search input on the right -->
            <div class="w-full md:w-auto">
                <input type="text" placeholder="Search..."
                    class="w-full md:w-auto pr-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent placeholder:text-gray-500" />
            </div>
        </div>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Invoice Number</th>
                    <th>Client</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoices as $invoice)
                <tr>
                    <td>{{ $invoice->invoice_number }}</td>
                    <td>{{ $invoice->client->name }}</td>
                    <td>{{ $invoice->amount }}</td>
                    <td>
                        <span class="badge {{ $invoice->status == 'unpaid' ? 'badge-danger' : ($invoice->status == 'paid' ? 'badge-success' : 'badge-warning') }}">
                            {{ ucfirst($invoice->status) }}
                        </span>
                    </td>
                    <td>{{ $invoice->created_at }}</td>
                    <td>
                       <button  href="/invoice/{{$invoice->invoice_number}}" class="btn btn-primary mt-2">View</button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

              <div class="mt-4">
                    {{ $invoices->appends(['section' => 'invoices'])->links() }}
                </div>
</x-app-layout>