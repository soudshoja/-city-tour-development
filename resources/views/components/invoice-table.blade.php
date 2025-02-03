<div>
    <h3>Invoice List</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Invoice Number</th>
                <th>Total Amount</th>
                <th>Status</th>
                <th>Agent</th>
                <th>Client</th>
            </tr>
        </thead>
        <tbody>
            {{-- Loop through the invoices --}}
            @foreach ($invoices as $invoice)
                <tr>
                    <td>{{ $invoice['id'] }}</td>
                    <td>{{ $invoice['invoice_number'] }}</td>
                    <td>${{ number_format($invoice['total_amount'], 2) }}</td>
                    <td>{{ ucfirst($invoice['status']) }}</td>
                    <td>{{ $invoice['agentId'] }}</td>
                    <td>{{ $invoice['clientId'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
