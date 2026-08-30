<x-app-layout>
    <div class="container mx-auto p-6">
        <h1 class="text-3xl font-bold text-gray-700 mb-6">Create Refund #{{ $refundNumber }}</h1>
        <form action="{{ route('refunds.store') }}" method="POST">
            @csrf

            <div class="mt-8 p-6 border rounded-lg bg-white">
                <h3 class="text-xl font-bold mb-4">Refund Summary</h3>
                <div id="overall-summary-display" class="text-2xl font-bold text-right mb-4"></div>

                @php

                    $firstTask = $tasks->first();
                    if (strtolower($firstTask->status) === 'refund') {
                        $firstInvoice = $firstTask->originalTask->invoiceDetail->invoice ?? null;
                    } else {
                        $firstInvoice = $firstTask->invoiceDetail->invoice ?? null;
                    }
                    $invoiceStatus = $firstInvoice?->status;
                    $isPaidInvoice = in_array(strtolower($invoiceStatus), ['paid', 'partial refund']);
                @endphp

                @if (($invoiceGroups ?? collect())->count() > 1)
                    {{-- W4.U §b — multi-invoice batch preview: one refund document per carrying
                         invoice will be created, sharing one refund_batch_id (w4-brief.md §4). --}}
                    <div class="mb-6 rounded-lg p-4 bg-indigo-50 border border-indigo-200">
                        <p class="text-sm font-semibold text-indigo-800 mb-2">
                            This selection spans {{ $invoiceGroups->count() }} invoices — {{ $invoiceGroups->count() }} separate refund documents will be created, linked as one batch.
                        </p>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-indigo-700">
                                        <th class="py-1 pr-4 font-medium">Invoice</th>
                                        <th class="py-1 pr-4 font-medium">Tasks</th>
                                        <th class="py-1 text-right font-medium">Client net (est.)</th>
                                    </tr>
                                </thead>
                                <tbody class="text-gray-700">
                                    @foreach ($invoiceGroups as $group)
                                        <tr class="border-t border-indigo-100">
                                            <td class="py-1 pr-4">{{ $group['invoice']?->invoice_number ?? '—' }}</td>
                                            <td class="py-1 pr-4">{{ $group['tasks']->pluck('reference')->join(', ') }}</td>
                                            <td class="py-1 text-right">{{ number_format($group['tasks']->sum('total'), 3) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

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
                        <select name="method" id="method" x-data="{}" @change="document.getElementById('disposition-hint').textContent = {
                                'Cash': 'Cash → payout voucher (PV, refund-out).',
                                'Bank': 'Bank → payout voucher (PV, refund-out).',
                                'Online': 'Online → gateway refund, settles when the gateway confirms.',
                                'Credit': 'Credit → added to the client\'s store credit (2632).'
                            }[$event.target.value] || ''"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300" required>
                            <option value="">Select</option>
                            <option value="Cash">Cash</option>
                            <option value="Bank">Bank</option>
                            <option value="Online">Online</option>
                            <option value="Credit">{{$firstTask->client->full_name }}'s Credit</option>
                        </select>
                        <p id="disposition-hint" class="text-xs text-gray-500 mt-1">
                            The method chosen above decides how the client net is disposed — it is never silently overwritten.
                        </p>

                        <label for="disposition" class="block text-gray-700 font-semibold mb-2 mt-4">Disposition override (optional)</label>
                        <select name="disposition" id="disposition"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                            <option value="">Use company default / method above</option>
                            <option value="credit">Credit to client account</option>
                            <option value="refund_out">Refund out (cash/bank)</option>
                            <option value="apply">Apply to another open invoice</option>
                        </select>

                        {{-- W4.U verify-fix (HIGH): disposition='apply' has no way to pick WHICH
                             open invoice to apply against without this field —
                             RefundPostingService::postDisposition() throws without it. Hidden
                             unless "Apply to another open invoice" is selected (toggled below). --}}
                        <div data-apply-field class="hidden">
                            <label for="applied_invoice_id" class="block text-gray-700 font-semibold mb-2 mt-4">Apply to invoice</label>
                            <select name="applied_invoice_id" id="applied_invoice_id"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                                <option value="">Select an open invoice…</option>
                                @foreach(($openInvoices ?? collect()) as $inv)
                                    <option value="{{ $inv->id }}">#{{ $inv->invoice_number }} — {{ ucfirst($inv->status) }} — {{ number_format($inv->amount ?? 0, 3) }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 mt-1">
                                @if(($openInvoices ?? collect())->isEmpty())
                                    This client has no other open invoice to apply the refund credit against.
                                @else
                                    Required when disposition is "Apply to another open invoice".
                                @endif
                            </p>
                        </div>
                    </div>
                @else
                    <div class="mt-6">
                        @include('refunds.partial.payment-gateway-selection')
                    </div>
                @endif

                {{-- W4.U verify-fix (LOW): w4-brief.md §4e "(i) always: Dr 5125 / Cr airline
                     payable — when an airline commission clawback amount is entered" — the field
                     the screen was missing entirely (RefundPostingService::postClawback() already
                     supports it). --}}
                <div class="mt-6">
                    <label for="airline_clawback_amount" class="block text-gray-700 font-semibold mb-2">Airline commission clawback amount (optional)</label>
                    <input type="number" step="0.001" min="0" name="airline_clawback_amount" id="airline_clawback_amount"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                    <p class="text-xs text-gray-500 mt-1">Leave blank when the airline is not clawing back commission on this refund.</p>
                </div>

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
                @php
                    $sourceTask = $task->computed_source_task;
                    $invoiceDetail = $task->computed_invoice_detail;
                    $taskInvoice = $task->computed_invoice;
                @endphp
                <div class="task-refund-section bg-gray-50 border p-6 mt-8 rounded-lg shadow-sm">
                    <input type="hidden" class="refund-status" value="{{ strtolower($taskInvoice?->status ?? 'unpaid') }}">
                    <h3 class="text-xl font-bold mb-4">Refund Task #{{ $task->reference }}</h3>
                    <input type="hidden" name="tasks[{{ $loop->index }}][task_id]" value="{{ $task->id }}">

                    <div class="bg-gradient-to-r from-blue-50 via-white to-blue-50 shadow-sm rounded-xl p-5 border border-blue-100 mb-6">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                                Task Info
                            </h3>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-y-1 text-gray-700 text-sm leading-relaxed">
                            <div><strong>Passenger Name:</strong> {{ $task->passenger_name ?? 'N/A' }}</div>

                            @switch($sourceTask->type ?? $task->type)
                                @case('flight')
                                    <div><strong>Ticket Number:</strong> {{ $sourceTask->ticket_number ?? 'N/A' }}</div>
                                    <div><strong>Route:</strong>
                                        {{ $sourceTask->flightDetails->countryFrom->name ?? '' }} ({{ $sourceTask->flightDetails->airport_from ?? '' }})
                                        →
                                        {{ $sourceTask->flightDetails->countryTo->name ?? '' }} ({{ $sourceTask->flightDetails->airport_to ?? '' }})
                                    </div>
                                    <div><strong>Departure Time:</strong> {{ $sourceTask->flightDetails->readable_departure_time ?? 'N/A' }}</div>
                                    <div><strong>Arrival Time:</strong> {{ $sourceTask->flightDetails->readable_arrival_time ?? 'N/A' }}</div>
                                    @break

                                @case('hotel')
                                    <div><strong>Hotel Name:</strong> {{ $sourceTask->hotelDetails->hotel->name ?? 'N/A' }}</div>
                                    <div><strong>Check-In:</strong> {{ $sourceTask->hotelDetails->readable_check_in ?? 'N/A' }}</div>
                                    <div><strong>Check-Out:</strong> {{ $sourceTask->hotelDetails->readable_check_out ?? 'N/A' }}</div>
                                    <div><strong>Room Type:</strong> {{ $sourceTask->hotelDetails->room_type ?? $sourceTask->hotelDetails->room_category ?? 'N/A' }}</div>
                                    @php
                                        $roomDetails = json_decode($sourceTask->hotelDetails->room_details ?? '{}', true);
                                        $passengerCount = count($roomDetails['passengers'] ?? []);
                                    @endphp
                                    <div><strong>Number of Pax:</strong> {{ $passengerCount ?: ($sourceTask->number_of_pax ?? 'N/A') }}</div>
                                    @break

                                @case('visa')
                                    <div><strong>Visa Type:</strong> {{ $sourceTask->visaDetails->visa_type ?? 'N/A' }}</div>
                                    <div><strong>Application #:</strong> {{ $sourceTask->visaDetails->application_number ?? 'N/A' }}</div>
                                    <div><strong>Expiry Date:</strong>
                                        {{ !empty($sourceTask->visaDetails->expiry_date)
                                            ? \Carbon\Carbon::parse($sourceTask->visaDetails->expiry_date)->format('d M Y') : 'N/A' }}
                                    </div>
                                    <div><strong>Entries:</strong> {{ $sourceTask->visaDetails->number_of_entries ?? 'N/A' }}</div>
                                    <div><strong>Stay Duration:</strong> {{ $sourceTask->visaDetails->stay_duration ?? 'N/A' }}</div>
                                    <div><strong>Issuing Country:</strong> {{ $sourceTask->visaDetails->issuing_country ?? 'N/A' }}</div>
                                    @break

                                @case('insurance')
                                    <div><strong>Insurance Type:</strong> {{ $sourceTask->insuranceDetails->insurance_type ?? 'N/A' }}</div>
                                    <div><strong>Destination:</strong> {{ $sourceTask->insuranceDetails->destination ?? 'N/A' }}</div>
                                    <div><strong>Plan Type:</strong> {{ $sourceTask->insuranceDetails->plan_type ?? 'N/A' }}</div>
                                    <div><strong>Duration:</strong> {{ $sourceTask->insuranceDetails->duration ?? 'N/A' }}</div>
                                    <div><strong>Package:</strong> {{ $sourceTask->insuranceDetails->package ?? 'N/A' }}</div>
                                    @break
                            @endswitch
                        </div>
                    </div>

                    <hr class="my-6">

                    @if (in_array(strtolower($taskInvoice?->status ?? ''), ['paid', 'partial refund']))
                        @include('refunds.partial.paid-invoice-section', [
                            'task' => $task,
                            'sourceTask' => $sourceTask,
                            'invoiceDetail' => $invoiceDetail,
                            'loopIndex' => $loop->index,
                            'refundDetail' => null,
                            'isEditing' => false,
                            'isReadOnly' => false,
                        ])
                    @else
                        @include('refunds.partial.unpaid-invoice-section', [
                            'task' => $task,
                            'sourceTask' => $sourceTask,
                            'invoiceDetail' => $invoiceDetail,
                            'loopIndex' => $loop->index,
                            'refundDetail' => null,
                            'isEditing' => false,
                            'isReadOnly' => false,
                        ])
                    @endif

                    <div class="mt-4">
                        <label class="block text-gray-700 font-semibold mb-2">Remarks for Task {{ $task->reference }}</label>
                        <input type="text" name="tasks[{{ $loop->index }}][remarks]"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg">
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

            // W4.U verify-fix (HIGH): show the "apply to invoice" picker only when disposition
            // is actually set to 'apply' — the field is meaningless (and its exists:invoices,id
            // rule would refuse an empty submit only when set) for the other two dispositions.
            var dispositionSelect = document.getElementById('disposition');
            var applyFields = document.querySelectorAll('[data-apply-field]');
            function toggleApplyFields() {
                var show = dispositionSelect && dispositionSelect.value === 'apply';
                applyFields.forEach(function (el) { el.classList.toggle('hidden', !show); });
            }
            if (dispositionSelect) {
                dispositionSelect.addEventListener('change', toggleApplyFields);
                toggleApplyFields();
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
