<x-app-layout>
<div class="container">
    <h2>Transaction Report</h2>
    <form action="{{ route('reports.index') }}" method="GET" class="mb-4">
        <label for="report_type">Select Report Type:</label>
        <select name="report_type" id="report_type" class="form-control" onchange="this.form.submit()">
            <option value="agents" {{ $reportType === 'agents' ? 'selected' : '' }}>Agents</option>
            <option value="clients" {{ $reportType === 'clients' ? 'selected' : '' }}>Clients</option>
            <option value="suppliers" {{ $reportType === 'suppliers' ? 'selected' : '' }}>Suppliers</option>
        </select>
    </form>

    @if(!empty($reportData))
        @foreach($reportData as $report)
            <h3>{{ $report['name'] }}</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Transaction Type</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['transactions'] as $transaction)
                        <tr>
                            <td>{{ $transaction->transaction_date }}</td>
                            <td>{{ ucfirst($transaction->type) }}</td>
                            <td>${{ $transaction->amount }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p><strong>Balance: </strong>${{ $report['balance'] }}</p>
        @endforeach
    @else
        <p>No transactions available for the selected report type.</p>
    @endif
</div>


</x-app-layout>