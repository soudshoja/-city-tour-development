<x-app-layout>
    <div class="container mx-auto p-6">
        <h1 class="text-3xl font-bold text-gray-700 mb-6">Refund tasks #{{ $task->reference }}</h1>

        <div class="bg-white shadow-md rounded-lg p-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- tasks Info -->
                <div class="bg-gradient-to-br from-blue-100 to-white shadow-md rounded-lg p-4">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b border-purple-200 pb-2">Tasks Info
                    </h3>
                    <p class="mb-2"><strong>Tasks Reference No:</strong> {{ $task->reference }}</p>
                    <p class="mb-2"><strong>Type:</strong> {{ ucwords($task->type) }}</p>
                    @if ($task->type === 'flight')
                    <p class="mb-2"><strong>Ticket Number:</strong>
                        {{ $task->ticket_number }}
                    </p>
                    <p class="my-2">
                        {{ $task->originalTask->flightDetails->readable_time_range ?? 'NA' }}
                    </p>
                    <p>
                        <span class="font-bold">Departure Time:</span> {{ $task->originalTask->flightDetails->readable_departure_time ?? 'NA'  }}
                    </p>
                    <p>
                        <span class="font-bold">Arrival Time:</span> {{ $task->originalTask->flightDetails->readable_arrival_time ?? 'NA'  }}
                    </p>

                    @elseif($task->type === 'hotel')
                    <p class="mb-2"><strong>Room Ref:</strong>
                        {{ $task->originalTask->hotelDetails->room_reference ?? 'N/A' }}
                    </p>
                    <p class="mb-2"><strong>Check-in:</strong>
                        {{ $task->originalTask->hotelDetails->readable_check_in ?? 'N/A' }}
                    </p>
                    <p class="mb-2"><strong>Check-out:</strong>
                        {{ $task->originalTask->hotelDetails->readable_check_out ?? 'N/A' }}
                    </p>
                    <p>
                        {{ $task->originalTask->hotelDetails->hotel->name ?? 'N/A' }}
                    </p>
                    @endif
                    <p class="mb-2"><strong>Refund Date:</strong> {{ now()->format('d-m-Y') }}</p>
                    <p class="mb-2"><strong>Refund Amount:</strong> KWD{{ number_format($task->total, 2) }}</p>
                    <p class="mb-2">
                        <strong>Status:</strong>
                        <span
                            class="badge whitespace-nowrap px-2 py-1 rounded text-sm font-medium
                                 {{ $task->status === 'refund' ? 'badge-outline-danger' : '' }}
                                {{ $task->status === null ? 'badge-outline-danger' : '' }}">
                            {{ $task->status === null ? 'Not Set' : ucwords($task->status) }}

                        </span>

                    </p>
                </div>

                <!-- Client Info -->
                <div class="bg-gradient-to-br from-blue-100 to-white shadow-md rounded-lg p-4">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b border-purple-200 pb-2">Client Info
                    </h3>
                    <p class="mb-2"><strong>Name:</strong> {{ $task->client_name ?? 'N/A' }}</p>
                    <p class="mb-2"><strong>Email:</strong> {{ $task->client_name ?? 'N/A' }}</p>
                    <br>
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b border-purple-200 pb-2">Agent Info</h3>
                    <p class="mb-2"><strong>Name:</strong> {{ $task->agent->name ?? 'N/A' }}</p>
                    <p class="mb-2"><strong>Email:</strong> {{ $task->agent->email ?? 'N/A' }}</p>
                </div>
            </div>

            <hr class="my-6">

            <div class="mb-6 rounded-lg p-4 {{ $invoicePaid ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200' }}">
                <div>
                    <div class="font-semibold {{ $invoicePaid ? 'text-green-700' : 'text-red-800' }}">Invoice Status: {{ $invoicePaid ? 'Paid' : 'Unpaid' }}</div>
                    @if(!$invoicePaid)
                    <div class="text-sm mt-1 text-red-900">
                        <span class="inline-block mt-1 rounded bg-white px-2 py-1 border border-red-300">
                            <span class="font-semibold">Total Refund to Client</span>
                            =
                            <span class="underline">Original Task Profit</span>
                            +
                            <span class="underline">Refund Task Supplier Charges</span>
                            +
                            <span class="underline">New Profit</span>
                        </span>
                    </div>
                    @endif
                </div>
            </div>
            @if($invoicePaid)

            @include('refunds.partial.paid-invoice')

            @else

            @include('refunds.partial.unpaid-invoice')

            @endif

            @if ($errors->any())
            <div class="mt-4 text-red-500 text-sm">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const originalTaskPriceInput = document.getElementById('original_task_price');
                const originalRefundAmountInput = document.getElementById('original_refund_amount');
                const newTaskProfitInput = document.getElementById('new_task_profit');
                const serviceChargeInput = document.getElementById('service_charge');
                const totalNettRefundInput = document.getElementById('total_nett_refund');

                let isUpdating = false;

                function parse(input) {
                    return parseFloat(input.value) || 0;
                }

                function initializeFields() {
                    const taskPrice = parse(originalTaskPriceInput);
                    const originalRefund = parse(originalRefundAmountInput);

                    const serviceCharge = taskPrice - originalRefund;
                    const newProfit = 0;
                    const totalRefund = originalRefund - newProfit;

                    serviceChargeInput.value = serviceCharge.toFixed(2);
                    newTaskProfitInput.value = newProfit.toFixed(2);
                    totalNettRefundInput.value = totalRefund.toFixed(2);
                }

                function updateFromNewProfit() {
                    if (isUpdating) return;
                    isUpdating = true;

                    const taskPrice = parse(originalTaskPriceInput);
                    const originalRefund = parse(originalRefundAmountInput);
                    const newProfit = parse(newTaskProfitInput);

                    const totalRefund = originalRefund - newProfit;
                    const serviceCharge = taskPrice - totalRefund;

                    totalNettRefundInput.value = totalRefund.toFixed(2);
                    serviceChargeInput.value = serviceCharge.toFixed(2);

                    isUpdating = false;
                }

                function updateFromServiceCharge() {
                    if (isUpdating) return;
                    isUpdating = true;

                    const taskPrice = parse(originalTaskPriceInput);
                    const originalRefund = parse(originalRefundAmountInput);
                    const serviceCharge = parse(serviceChargeInput);

                    const totalRefund = taskPrice - serviceCharge;
                    const newProfit = originalRefund - totalRefund;

                    totalNettRefundInput.value = totalRefund.toFixed(2);
                    newTaskProfitInput.value = newProfit.toFixed(2);

                    isUpdating = false;
                }

                function updateFromTotalNettRefund() {
                    if (isUpdating) return;
                    isUpdating = true;

                    const taskPrice = parse(originalTaskPriceInput);
                    const originalRefund = parse(originalRefundAmountInput);
                    const totalRefund = parse(totalNettRefundInput);

                    const serviceCharge = taskPrice - totalRefund;
                    const newProfit = originalRefund - totalRefund;

                    serviceChargeInput.value = serviceCharge.toFixed(2);
                    newTaskProfitInput.value = newProfit.toFixed(2);

                    isUpdating = false;
                }

                // Initialize values on page load
                initializeFields();

                // Event bindings
                newTaskProfitInput.addEventListener('input', updateFromNewProfit);
                serviceChargeInput.addEventListener('input', updateFromServiceCharge);
                totalNettRefundInput.addEventListener('input', updateFromTotalNettRefund);
            });
        </script>

</x-app-layout>