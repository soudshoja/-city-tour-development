<x-app-layout>

    <div class="bg-white rounded-lg shadow-md p-5">
    <div class="grid grid-cols-2 gap-2">

     
        @if (session('success') || session('error'))
            <div id="flash-message" class="alert 
                @if (session('success')) alert-success 
                @elseif (session('error')) alert-danger 
                @endif
                fixed-top-right">
                {{ session('success') ?? session('error') }}
            </div>
        @endif

        <div class="grid grid-cols-1 gap-2 mb-2">
        <div id="taskChart2"></div>
        </div>
        </div>
        <div class="grid grid-cols-4 gap-2 mb-2">
            <div class="bg-blue-500 text-white p-2 rounded-lg shadow">
                <h3 class="text-sm font-semibold">Total Invoice</h3>
                <p class="text-2xl font-bold">{{ $totalInvoiceCount }}</p>
            </div>
            <div class="bg-green-500 text-white p-2 rounded-lg shadow">
                <h3 class="text-sm font-semibold">Completed Tasks</h3>
                <p class="text-2xl font-bold"></p>
            </div>
            <div class="bg-gray-500 text-white p-2 rounded-lg shadow">
                <h3 class="text-sm font-semibold">Total Tasks</h3>
                <p class="text-2xl font-bold"></p>
            </div>
            <div class="bg-red-500 text-white p-2 rounded-lg shadow">
                <h3 class="text-sm font-semibold">Total Clients</h3>
                <p class="text-2xl font-bold"></p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-2 mb-2">
        <table>
            <thead>
                <tr>
                    <th>Invoice Number</th>
                    <th>Invoice Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($transactions as $transaction)
                    <tr>
                        <td>{{ $transaction['invoice_number'] }}</td>
                        <td>{{ $transaction['status'] }}</td>
                        <td>
                            <!-- View Invoice Button -->
                            <a href="{{ route('invoice.show', ['invoiceNumber' => $transaction['invoice_number']]) }}">
                                <button type="button" class="btn btn-primary">
                                    View Invoice
                                </button>
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        </div>
                @if ($message === 'Agent not found')
        <!-- Inline form to create agent profile -->
        <div class="mt-4">
            <h2>Create Agent Profile</h2>
            <form action="{{ route('create.agent.profile') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label for="phone_number">Phone Number</label>
                    <input type="text" class="form-control" id="phone_number" name="phone_number" required>
                </div>
                <div class="form-group">
                    <label for="company_id">Company ID</label>
                    <input type="text" class="form-control" id="company_id" name="company_id" required>
                </div>
                <div class="form-group">
                    <label for="type">Type</label>
                    <select class="form-control" id="type" name="type" required>
                        <option value="salary">Salary</option>
                        <option value="commission">Commission</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Create Profile</button>
            </form>
        </div>
    @endif
</div>


<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {

        var options2 = {
            chart: {
                type: 'pie',
                height: 350
            },
            series: [{{ $unpaidInvoiceCount }}, {{ $paidInvoiceCount }}],
            labels: ['UnPaid', 'Paid'], // Pie chart labels
            colors: ['#FF4560', '#00E396'], // Customize colors as needed
            title: {
                text: 'Paid vs Unpaid Invoices',
                align: 'center'
            }
        };


        var chart2 = new ApexCharts(document.querySelector("#taskChart2"), options2);
        chart2.render();
    });
</script>

</x-app-layout>