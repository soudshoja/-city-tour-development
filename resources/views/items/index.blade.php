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
        @if ($message)
            @php
                $messageClasses = [
                    'success' => 'bg-green-100 border-l-4 border-green-400 text-green-700',
                    'error' => 'bg-red-100 border-l-4 border-red-400 text-red-700',
                    'warning' => 'bg-yellow-100 border-l-4 border-yellow-400 text-yellow-700',
                    'info' => 'bg-blue-100 border-l-4 border-blue-400 text-blue-700'
                ];
                $messageClass = $messageClasses[$status] ?? 'bg-gray-100 border-l-4 border-gray-400 text-gray-700';
            @endphp
            <div class="{{ $messageClass }} p-2 rounded-lg inline-block my-2">
                {{ $message }}
            </div>
        @endif

        <div class="grid grid-cols-2 gap-2 mb-2">
        <div id="taskChart"></div>
        </div>
        <div class="grid grid-cols-1 gap-2 mb-2">
        <div id="taskChart2"></div>
        </div>
        </div>
        <div class="grid grid-cols-4 gap-2 mb-2">
            <div class="bg-blue-500 text-white p-2 rounded-lg shadow">
                <h3 class="text-sm font-semibold">Pending Tasks</h3>
                <p class="text-2xl font-bold">{{ $pendingTasksCount }}</p>
            </div>
            <div class="bg-green-500 text-white p-2 rounded-lg shadow">
                <h3 class="text-sm font-semibold">Completed Tasks</h3>
                <p class="text-2xl font-bold">{{ $completedTasksCount }}</p>
            </div>
            <div class="bg-gray-500 text-white p-2 rounded-lg shadow">
                <h3 class="text-sm font-semibold">Total Tasks</h3>
                <p class="text-2xl font-bold">{{ $totalTasksCount }}</p>
            </div>
            <div class="bg-red-500 text-white p-2 rounded-lg shadow">
                <h3 class="text-sm font-semibold">Total Clients</h3>
                <p class="text-2xl font-bold">{{ $totalClientsCount }}</p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-2 mb-2">
        <table>
           <thead>
                <tr>
                    <th>Task Details</th>
                    <th>Task Status</th>
                </tr>
            </thead>
            <tbody>
            <!-- Display items here -->
            @foreach ($items as $item)
                <tr>
                    <td>{{ $item['task_description'] }}</td>
                    <td>{{ $item['task_status'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
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
        var options = {
            chart: {
                type: 'bar',
                height: 350
            },
            series: [{
                name: 'Tasks',
                data: [{{ $pendingTasksCount }}, {{ $completedTasksCount }}]
            }],
            xaxis: {
                categories: ['Pending', 'Completed']
            },
            colors: ['#FF4560', '#00E396'], // Customize colors as needed
            title: {
                text: 'Pending vs Completed Tasks',
                align: 'center'
            }
        };

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


        
        var chart = new ApexCharts(document.querySelector("#taskChart"), options);
        chart.render();

        var chart2 = new ApexCharts(document.querySelector("#taskChart2"), options2);
        chart2.render();
    });
</script>

</x-app-layout>