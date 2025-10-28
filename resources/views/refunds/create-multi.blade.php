<x-app-layout>
    <div class="container mx-auto p-6">
        <h1 class="text-3xl font-bold text-gray-700 mb-6">Create Refund #{{ $refundNumber }}</h1>
        <form action="{{ route('refunds.store') }}" method="POST">
            @csrf

            <div class="mt-8 p-6 border rounded-lg bg-white">
                <h3 class="text-xl font-bold mb-4">Refund Summary</h3>
                <div id="overall-summary-display" class="text-2xl font-bold text-right mb-4"></div>

                @php
                    $invoiceIds = $tasks->pluck('originalTask.invoiceDetail.invoice.id')->filter()->unique()->values();
                    $invoiceStatus = optional($tasks->first()->originalTask->invoiceDetail->invoice)->status;
                    $isPaidInvoice = strtolower($invoiceStatus) === 'paid';
                    $firstInvoice = $tasks->first()->originalTask->invoiceDetail->invoice ?? null;
                    $firstTask = $tasks->first();
                @endphp

                @if ($firstInvoice)
                    <div class="mb-6 rounded-lg p-4 {{ $isPaidInvoice ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200' }}">
                        <div class="flex items-center gap-4 flex-wrap text-sm font-semibold">
                            <div class="{{ $isPaidInvoice ? 'text-green-700' : 'text-red-800' }}">
                                Original Invoice: #{{ $firstInvoice->invoice_number }}
                            </div>
                            <span class="text-gray-400">|</span>
                            <div class="{{ $isPaidInvoice ? 'text-green-700' : 'text-red-800' }}">
                                Original Invoice Status: {{ ucfirst($invoiceStatus) }}
                            </div>
                            @if ($firstInvoice?->payment_type)
                                <span class="text-gray-400">|</span>
                                <div class="{{ $isPaidInvoice ? 'text-green-700' : 'text-red-800' }}">
                                    Payment Type: {{ ucfirst($firstInvoice?->payment_type) }}
                                </div>
                            @endif
                        </div>
                        @unless($isPaidInvoice)
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
                        @endunless
                    </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div class="bg-gradient-to-br from-blue-50 to-white shadow-sm rounded-xl p-4 border border-blue-100">
                        <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5.121 17.804A13.937 13.937 0 0112 15c2.905 0 5.584.93 7.879 2.804M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Client Info
                        </h3>
                        @if($uniqueClients->count() > 1)
                            <label for="client_id" class="block text-gray-700 font-semibold mb-1">Select Client</label>
                            <select name="client_id" id="client_id"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300 mb-3">
                                @foreach($uniqueClients as $client)
                                    <option value="{{ $client->id }}">{{ $client->first_name }} {{ $client->last_name }}</option>
                                @endforeach
                            </select>
                        @endif
                        <p class="mb-1"><strong>Name:</strong> {{ $firstTask->client_name ?? ($firstTask->client->full_name ?? 'N/A') }}</p>
                        <p class="mb-1"><strong>Email:</strong> {{ $firstTask->client->email ?? 'N/A' }}</p>
                    </div>

                    <div class="bg-gradient-to-br from-purple-50 to-white shadow-sm rounded-xl p-4 border border-purple-100">
                        <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5.121 17.804A13.937 13.937 0 0112 15c2.905 0 5.584.93 7.879 2.804M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Agent Info
                        </h3>
                        <p class="mb-1"><strong>Name:</strong> {{ $firstTask->agent->name ?? 'N/A' }}</p>
                        <p class="mb-1"><strong>Email:</strong> {{ $firstTask->agent->email ?? 'N/A' }}</p>
                    </div>
                </div>
                <div>
                    <label for="date" class="block text-gray-700 font-semibold mb-2">Refund Date</label>
                    <input type="date" name="date" id="date" value="{{ now()->toDateString() }}" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                </div>

                @if ($isPaidInvoice)
                    <div class="mt-6 p-6 border rounded-lg bg-gray-50">
                        <h3 class="text-xl font-bold mb-4">Refund Method</h3>
                        <label for="method" class="block text-gray-700 font-semibold mb-2">Refund Method</label>
                        <select name="method" id="method"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300" required>
                            <option value="">Select</option>
                            <option value="Cash">Cash</option>
                            <option value="Bank">Bank</option>
                            <option value="Online">Online</option>
                            <option value="Credit">{{$firstTask->client->full_name }}'s Credit</option>
                        </select>
                    </div>
                @else
                    <div class="mt-6">
                        @include('refunds.partial.payment-gateway-selection')
                    </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-10">
                    <div>
                        <label for="remarks" class="block text-gray-700 font-semibold mb-2">Remarks</label>
                        <input type="text" name="remarks" id="remarks"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                    </div>
                    <div>
                        <label for="remarks_internal" class="block text-gray-700 font-semibold mb-2">Internal Remarks</label>
                        <input type="text" name="remarks_internal" id="remarks_internal"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                    </div>
                </div>

                <div class="mt-6">
                    <label for="reason" class="block text-gray-700 font-semibold mb-2">Reason</label>
                    <textarea name="reason" id="reason" rows="3"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300"></textarea>
                    @error('reason')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>

                <button type="submit"
                    class="mt-6 px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition duration-300">
                    Process Refund
                </button>
            </div>


            @foreach($tasks as $task)
                <div class="task-refund-section bg-gray-50 border p-6 mt-8 rounded-lg shadow-sm">
                    <input type="hidden" class="refund-status" value="{{ strtolower($task->originalTask->invoiceDetail->invoice->status) }}">
                    <h3 class="text-xl font-bold mb-4">Refund Task #{{ $task->reference }}</h3>
                    <input type="hidden" name="tasks[{{ $loop->index }}][task_id]" value="{{ $task->id }}">

                    <div class="bg-gradient-to-r from-blue-50 via-white to-blue-50 shadow-sm rounded-xl p-5 border border-blue-100 mb-6">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v1m0 14v1m8-8h1M4 12H3m15.364-6.364l.707.707M6.343 17.657l-.707.707m12.728 0l.707-.707M6.343 6.343l-.707-.707" />
                                </svg>
                                Task Info
                            </h3>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-y-1 text-gray-700 text-sm leading-relaxed">
                            <div><strong>Passenger Name:</strong> {{ $task->passenger_name ?? 'N/A' }}</div>

                            @switch($task->type)
                                @case('flight')
                                    <div><strong>Ticket Number:</strong> {{ $task->ticket_number ?? 'N/A' }}</div>
                                    <div><strong>Route:</strong>
                                        {{ $task->originalTask->flightDetails->countryFrom->name ?? '' }} ({{ $task->originalTask->flightDetails->airport_from ?? '' }})
                                        →
                                        {{ $task->originalTask->flightDetails->countryTo->name ?? '' }} ({{ $task->originalTask->flightDetails->airport_to ?? '' }})
                                    </div>
                                    <div><strong>Departure Time:</strong> {{ $task->originalTask->flightDetails->readable_departure_time ?? 'N/A' }}</div>
                                    <div><strong>Arrival Time:</strong> {{ $task->originalTask->flightDetails->readable_arrival_time ?? 'N/A' }}</div>
                                    @break

                                @case('hotel')
                                    <div><strong>Hotel Name:</strong> {{ $task->originalTask->hotelDetails->hotel->name ?? 'N/A' }}</div>
                                    <div><strong>Check-In:</strong> {{ $task->originalTask->hotelDetails->readable_check_in ?? 'N/A' }}</div>
                                    <div><strong>Check-Out:</strong> {{ $task->originalTask->hotelDetails->readable_check_out ?? 'N/A' }}</div>
                                    <div><strong>Room Type:</strong> 
                                        {{ $task->originalTask->hotelDetails->room_type ?? $task->originalTask->hotelDetails->room_category ?? 'N/A' }}</div>
                                    @php
                                        $roomDetails = json_decode($task->originalTask->hotelDetails->room_details ?? '{}', true);
                                        $passengerCount = count($roomDetails['passengers'] ?? []);
                                    @endphp
                                    <div><strong>Number of Pax:</strong> {{ $passengerCount ?: ($task->number_of_pax ?? 'N/A') }}</div>
                                    @break

                                @case('visa')
                                    <div><strong>Visa Type:</strong> {{ $task->originalTask->visaDetails->visa_type ?? 'N/A' }}</div>
                                    <div><strong>Application #:</strong> {{ $task->originalTask->visaDetails->application_number ?? 'N/A' }}</div>
                                    <div><strong>Expiry Date:</strong>
                                        {{ !empty($task->originalTask->visaDetails->expiry_date)
                                            ? \Carbon\Carbon::parse($task->originalTask->visaDetails->expiry_date)->format('d M Y') : 'N/A' }}
                                    </div>
                                    <div><strong>Entries:</strong> {{ $task->originalTask->visaDetails->number_of_entries ?? 'N/A' }}</div>
                                    <div><strong>Stay Duration:</strong> {{ $task->originalTask->visaDetails->stay_duration ?? 'N/A' }}</div>
                                    <div><strong>Issuing Country:</strong> {{ $task->originalTask->visaDetails->issuing_country ?? 'N/A' }}</div>
                                    @break

                                @case('insurance')
                                    <div><strong>Insurance Type:</strong> {{ $task->originalTask->insuranceDetails->insurance_type ?? 'N/A' }}</div>
                                    <div><strong>Destination:</strong> {{ $task->originalTask->insuranceDetails->destination ?? 'N/A' }}</div>
                                    <div><strong>Plan Type:</strong> {{ $task->originalTask->insuranceDetails->plan_type ?? 'N/A' }}</div>
                                    <div><strong>Duration:</strong> {{ $task->originalTask->insuranceDetails->duration ?? 'N/A' }}</div>
                                    <div><strong>Package:</strong> {{ $task->originalTask->insuranceDetails->package ?? 'N/A' }}</div>
                                    <div><strong>Document Ref:</strong> {{ $task->originalTask->insuranceDetails->document_reference ?? 'N/A' }}</div>
                                    <div><strong>Paid Leaves:</strong> {{ $task->originalTask->insuranceDetails->paid_leaves ?? 'N/A' }}</div>
                                    @break
                            @endswitch
                        </div>
                    </div>
                    <hr class="my-6">

                    @if ($task->originalTask?->invoiceDetail?->invoice?->status === 'paid')
                        @include('refunds.partial.paid-invoice-section', [
                            'task' => $task,
                            'invoiceDetail' => $task->originalTask->invoiceDetail,
                            'loopIndex' => $loop->index,
                            'refundDetail' => null,
                            'isEditing' => false,
                            'isReadOnly' => false,
                        ])
                    @elseif ($task->originalTask?->invoiceDetail?->invoice?->status === 'unpaid')
                        @include('refunds.partial.unpaid-invoice-section', [
                            'task' => $task,
                            'invoiceDetail' => $task->originalTask->invoiceDetail,
                            'loopIndex' => $loop->index,
                            'refundDetail' => null,
                            'isEditing' => false,
                            'isReadOnly' => false,
                        ])
                    @else
                        {{-- For partial, credit, or other cases --}}
                        @include('refunds.partial.unpaid-invoice-section', [
                            'task' => $task,
                            'invoiceDetail' => $task->originalTask->invoiceDetail,
                            'loopIndex' => $loop->index,
                            'refundDetail' => null,
                            'isEditing' => false,
                            'isReadOnly' => false,
                        ])
                    @endif
                    <div class="mt-4">
                        <label for="tasks[{{ $loop->index }}][remarks]" class="block text-gray-700 font-semibold mb-2">Remarks for Task {{ $task->reference }}</label>
                        <input type="text" name="tasks[{{ $loop->index }}][remarks]" id="tasks[{{ $loop->index }}][remarks]"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                    </div>
                </div>
            @endforeach
        </form>
    </div>
    <script>
        function updateOverallSummary() {
            let totalNetRefund = 0;
            let totalCharges = 0;

            document.querySelectorAll(".task-refund-section").forEach(section => {
                const status = section.querySelector('.refund-status')?.value || 'unpaid';
                const isPaid = status === 'paid';
                const isUnpaid = status === 'unpaid' || status === 'partial';
                const totalRefundToClientInput = section.querySelector('[name*="[total_refund_to_client]"]');

                if (isPaid) {
                    if (totalRefundToClientInput) {
                        totalNetRefund += parseFloat(totalRefundToClientInput.value) || 0;
                    }
                } else if (isUnpaid) {
                    if (totalRefundToClientInput) {
                        totalCharges += parseFloat(totalRefundToClientInput.value) || 0;
                    }
                }
            });

            const overallSummaryDisplay = document.getElementById("overall-summary-display");
            if (totalNetRefund > 0) {
                overallSummaryDisplay.innerHTML = `
                    <div class="inline-flex items-center justify-end text-green-700">
                        <span class="text-2xl font-extrabold">Total Refund to Client: ${totalNetRefund.toFixed(2)} KWD</span>
                    </div>`;
                overallSummaryDisplay.className =
                    "transition-all duration-300 ease-in-out text-right mb-6 p-5 rounded-xl border-2 border-green-300 bg-green-50 shadow-sm";
            } else if (totalCharges > 0) {
                overallSummaryDisplay.innerHTML = `
                    <div class="inline-flex items-center justify-end text-red-600">
                        <span class="text-xl font-extrabold">Total Charges to Collect: ${totalCharges.toFixed(2)} KWD</span>
                    </div>`;
                overallSummaryDisplay.className =
                    "transition-all duration-300 ease-in-out text-right mb-6 p-5 rounded-xl border-2 border-red-300 bg-red-50 shadow-sm";
            } else {
                overallSummaryDisplay.innerHTML = `
                    <span class="text-gray-600 text-xl italic">No refund or charges calculated yet.</span>`;
                overallSummaryDisplay.className =
                    "transition-all duration-300 ease-in-out text-right mb-6 p-5 rounded-xl border bg-gray-50 shadow-sm";
            }
        }

        window.addEventListener('refundTaskReady', function () {
            if (typeof updateOverallSummary === 'function') {
                updateOverallSummary();
            }
        });

        document.addEventListener('DOMContentLoaded', function () {
            if (typeof updateOverallSummary === 'function') {
                updateOverallSummary();
            }
        });

        document.addEventListener('input', e => {
            const name = e.target.name || '';
            if (name.includes('[refund_fee_to_client]') || name.includes('[new_task_profit]') ||
                name.includes('[total_refund_to_client]') || name.includes('[supplier_charge]')) {
                updateOverallSummary();
            }
        });
    </script>
</x-app-layout>
