<x-app-layout>
    <div class="container mx-auto p-6">
        <h2 class="text-3xl font-bold">Create Refunds</h2>
        <form action="{{ route('refunds.store') }}" method="POST">
            @csrf

            <div class="mt-8 p-6 border rounded-lg bg-gray-50">
                <h3 class="text-xl font-bold mb-4">Refund Summary</h3>
                <div id="overall-summary-display" class="text-2xl font-bold text-right mb-4"></div>
                <div>
                    <label for="date" class="block text-gray-700 font-semibold mb-2">Refund Date</label>
                    <input type="date" name="date" id="date" value="{{ now()->toDateString() }}" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                </div>

                @php
                    $invoiceIds = $tasks->pluck('originalTask.invoiceDetail.invoice.id')->filter()->unique()->values();
                    $isSameInvoice = $invoiceIds->count() === 1;
                    $firstInvoiceStatus = optional($tasks->first()->originalTask->invoiceDetail->invoice)->status;
                    $isPaidInvoice = strtolower($firstInvoiceStatus) === 'paid';
                @endphp

                @if ($isSameInvoice)
                    @if ($isPaidInvoice)
                        <div class="mt-6 p-6 border rounded-lg bg-gray-50">
                            <h3 class="text-xl font-bold mb-4">Refund Method</h3>
                            <div>
                                <label for="method" class="block text-gray-700 font-semibold mb-2">Refund Method</label>
                                <select name="method" id="method"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300" required>
                                    <option value="">Select</option>
                                    <option value="Cash">Cash</option>
                                    <option value="Bank">Bank</option>
                                    <option value="Online">Online</option>
                                    <option value="Credit">
                                        {{ trim($tasks->first()->client->first_name . ' ' . ($tasks->first()->client->last_name ?? '')) }}'s Credit
                                    </option>
                                </select>
                            </div>
                        </div>
                    @else
                        <div class="mt-6">
                            @include('refunds.partial.payment-gateway-selection')
                        </div>
                    @endif
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-10">
                    <div>
                        <label for="remarks" class="block text-gray-700 font-semibold mb-2">Remarks</label>
                        <input type="text" name="remarks" id="remarks"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                    </div>

                    <div>
                        <label for="remarks_internal" class="block text-gray-700 font-semibold mb-2">Internal
                            Remarks</label>
                        <input type="text" name="remarks_internal" id="remarks_internal"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                    </div>
                </div>
                <!-- Reason -->
                <div class="mt-6">
                    <label for="reason" class="block text-gray-700 font-semibold mb-2">Reason</label>
                    <textarea name="reason" id="reason" rows="3"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300"></textarea>
                    @error('reason')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>
                <button type="submit" class="mt-6 px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition duration-300">Process Refund</button>
            </div>

            @if($uniqueClients->count() > 1)
                <div class="mt-6">
                    <label for="client_id" class="block text-gray-700 font-semibold mb-2">Select Client for Refund/Charges</label>
                    <select name="client_id" id="client_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                        @foreach($uniqueClients as $client)
                            <option value="{{ $client->id }}">{{ $client->first_name }} {{ $client->last_name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            @foreach($tasks as $task)
                <div class="task-refund-section bg-gray-50 border p-6 mt-8 rounded-lg shadow-sm">
                    <h3 class="text-xl font-bold mb-4">
                        Task {{ $task->reference }} - Original Invoice: {{ optional($task->originalTask->invoiceDetail->invoice)->invoice_number }}</h3>
                    <input type="hidden" name="tasks[{{ $loop->index }}][task_id]" value="{{ $task->id }}">
                    @php
                        $invoice = $task->originalTask?->invoiceDetail?->invoice;
                        $invoiceStatus = $invoice?->status;
                        $paymentType = $invoice?->payment_type;
                        $isPaid = $invoiceStatus === 'paid';
                    @endphp
                    <div class="mb-6 rounded-lg p-4 {{ $isPaid ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200' }}">
                        <div>
                            <div class="flex items-center gap-4 flex-wrap text-sm font-semibold">
                                <div class="{{ $isPaid ? 'text-green-700' : 'text-red-800' }}">Original Invoice Status: {{ ucfirst($invoiceStatus) }}</div>
                                @if ($paymentType)
                                    <span class="text-gray-400">|</span>
                                    <div class="{{ $isPaid ? 'text-green-700' : 'text-red-800' }}">Payment Type: {{ ucfirst($paymentType) }}</div>
                                @endif
                            </div>
                            @unless($isPaid)
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
                    </div>

                    @if ($invoiceStatus === 'paid')
                        @include('refunds.partial.paid-invoice-section', [
                            'task' => $task,
                            'invoiceDetail' => $task->originalTask->invoiceDetail,
                            'loopIndex' => $loop->index
                        ])
                    @elseif ($invoiceStatus === 'unpaid')
                        @include('refunds.partial.unpaid-invoice-section', [
                            'task' => $task,
                            'invoiceDetail' => $task->originalTask->invoiceDetail,
                            'loopIndex' => $loop->index
                        ])
                    @else
                        {{-- For partial, credit, or other cases --}}
                        @include('refunds.partial.unpaid-invoice-section', [
                            'task' => $task,
                            'invoiceDetail' => $task->originalTask->invoiceDetail,
                            'loopIndex' => $loop->index
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

        <script>
            function updateOverallSummary() {
                let totalNetRefund = 0;
                let totalCharges = 0;

                document.querySelectorAll(".task-refund-section").forEach(section => {
                    const statusText = section.querySelector(".text-green-700, .text-red-800")?.textContent.toLowerCase().trim() || '';
                    const isPaid = statusText.includes('paid') && !statusText.includes('unpaid');
                    const isUnpaid = statusText.includes('unpaid') || statusText.includes('partial');
                    const totalRefundToClientInput = section.querySelector('[name*="[total_refund_to_client]"]');
                    const invoicePriceInput = section.querySelector('[name*="[invoice_price]"]');

                    if (isPaid) {
                        if (totalRefundToClientInput) {
                            totalNetRefund += parseFloat(totalRefundToClientInput.value) || 0;
                        }
                    } else if (isUnpaid) {
                        if (invoicePriceInput) {
                            totalCharges += parseFloat(invoicePriceInput.value) || 0;
                        }
                    }
                });

                const overallSummaryDisplay = document.getElementById("overall-summary-display");
                if (totalNetRefund > 0) {
                    overallSummaryDisplay.textContent = `Total Refund to Client: ${totalNetRefund.toFixed(2)}`;
                    overallSummaryDisplay.classList.remove('text-red-500');
                    overallSummaryDisplay.classList.add('text-green-600');
                } else if (totalCharges > 0) {
                    overallSummaryDisplay.textContent = `Total Charges to Collect: ${totalCharges.toFixed(2)}`;
                    overallSummaryDisplay.classList.remove('text-green-600');
                    overallSummaryDisplay.classList.add('text-red-500');
                } else {
                    overallSummaryDisplay.textContent = `Total: 0.00`;
                    overallSummaryDisplay.classList.remove('text-green-600', 'text-red-500');
                }
            }

            document.addEventListener('DOMContentLoaded', () => {
                setTimeout(updateOverallSummary, 300);
            });

            document.addEventListener('input', e => {
                const name = e.target.name || '';
                if (name.includes('[refund_fee_to_client]') || name.includes('[new_task_profit]') ||
                    name.includes('[total_refund_to_client]') || name.includes('[invoice_price]') || name.includes('[supplier_charge]')) {
                    updateOverallSummary();
                }
            });
        </script>
    </div>
</x-app-layout>
