<x-app-layout>
    <div class="container">
        <div class="flex justify-between items-center w-full mb-3">
            <div class="bg-gray-200 p-2.5 rounded flex-grow">
                <h2><strong>Invoice</strong></h2>
            </div>
        </div>
        @if (session('status'))
        <div class="bg-green-500 text-white p-4 rounded mb-4">
            {{ session('status') }}
        </div>
        @endif

        @foreach ($invoices as $status => $groupedInvoices)
        <h2>
            @if ($status == 'unpaid')
            <span class="badge badge-danger">Unpaid</span>
            @elseif ($status == 'paid')
            <span class="badge badge-success">Paid</span>
            @else
            <span class="badge badge-warning">Overdue</span>
            @endif
        </h2>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Client</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($groupedInvoices as $invoice)
                <tr>
                    <td>{{ $invoice->id }}</td>
                    <td>{{ $invoice->client->name }}</td>
                    <td>{{ $invoice->amount }}</td>
                    <td>
                        <span class="badge {{ $invoice->status == 'unpaid' ? 'badge-danger' : ($invoice->status == 'paid' ? 'badge-success' : 'badge-warning') }}">
                            {{ ucfirst($invoice->status) }}
                        </span>
                    </td>
                    <td>{{ $invoice->created_at }}</td>
                    <td>
                        <form action="{{ route('invoices.updateStatus', $invoice->id) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <select name="status" class="form-control">
                                <option value="unpaid" {{ $invoice->status == 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                                <option value="paid" {{ $invoice->status == 'paid' ? 'selected' : '' }}>Paid</option>
                                <option value="overdue" {{ $invoice->status == 'overdue' ? 'selected' : '' }}>Overdue</option>
                            </select>
                            <button type="submit" class="btn btn-primary mt-2">Update Status</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endforeach
</x-app-layout>