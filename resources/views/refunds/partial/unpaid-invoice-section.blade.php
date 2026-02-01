<fieldset @if($isReadOnly) disabled @endif>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 border border-gray-300 rounded-lg px-6 lg:px-10 py-12 bg-gray-50">
        <div class="lg:col-span-2 text-left bg-slate-50 rounded-2xl p-6 lg:p-10 calculation-section">
            <h3 class="text-lg font-semibold text-gray-800 mb-6">Original Task Calculation</h3>

            <div class="flex flex-col xl:flex-row xl:justify-between xl:items-center mb-8 space-y-6 xl:space-y-0 xl:space-x-6">
                <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm flex-1">
                    <label class="block font-semibold text-gray-700 mb-2">Task Selling Price</label>
                    <input readonly type="number" step="0.001"
                        name="tasks[{{ $loopIndex }}][original_invoice_price]"
                        value="{{ number_format($invoiceDetail->task_price, 3, '.', '') }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 font-medium text-gray-800">
                </div>

                <div class="flex items-center justify-center w-8 h-8 bg-gray-200 rounded-full">
                    <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4"/></svg>
                </div>

                <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm flex-1">
                    <label class="block font-semibold text-gray-700 mb-2">Task Cost Price</label>
                    <input readonly type="number" step="0.001"
                        name="tasks[{{ $loopIndex }}][original_task_cost]"
                        value="{{ number_format($invoiceDetail->supplier_price, 3, '.', '') }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 font-medium text-gray-800">
                </div>

                <div class="flex items-center justify-center w-8 h-8 bg-green-100 rounded-full">
                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10h14M5 14h14"/></svg>
                </div>

                <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm flex-1">
                    <label class="block font-semibold text-gray-700 mb-2">Task Profit</label>
                    <div class="px-3 py-2 bg-green-50 border border-green-200 rounded-lg text-green-700 font-semibold">
                        {{ number_format($invoiceDetail->markup_price, 3, '.', '') }}
                    </div>
                </div>
            </div>

            <h3 class="text-lg font-semibold text-gray-800 mb-6 mt-10">Refund Calculation</h3>

            @php
                $calculatedRefundCharge = $task->calculated_refund_charge ?? ($task->total - $task->total);
            @endphp

            <div class="flex flex-col xl:flex-row xl:justify-between xl:items-center mb-6 space-y-6 xl:space-y-0 xl:space-x-6">

                <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm flex-1">
                    <label class="block font-semibold text-gray-700 mb-2">Original Task (Cost Price)</label>
                    <div class="px-3 py-2 bg-blue-50 border border-blue-200 rounded-lg text-blue-700 font-semibold">
                        {{ number_format($task->total, 3, '.', '') }}
                    </div>
                </div>

                <div class="flex items-center justify-center w-8 h-8 bg-gray-200 rounded-full">
                    <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4"/></svg>
                </div>

                <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm flex-1">
                    <label class="block font-semibold text-gray-700 mb-2">Refund Task Price</label>
                    <div class="px-3 py-2 bg-blue-50 border border-blue-200 rounded-lg text-blue-700 font-semibold">
                        {{ number_format($task->total, 3, '.', '') }}
                    </div>
                </div>

                <div class="flex items-center justify-center w-8 h-8 bg-red-100 rounded-full">
                    <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10h14M5 14h14"/></svg>
                </div>

                <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm flex-1">
                    <label class="block font-semibold text-gray-700 mb-2">Supplier Charge</label>
                    <div class="px-3 py-2 bg-red-50 border border-red-200 rounded-lg text-red-700 font-semibold" id="supplierChargeDisplay_{{ $loopIndex }}">
                        {{ number_format($calculatedRefundCharge, 3, '.', '') }}
                    </div>
                    <input type="hidden" name="tasks[{{ $loopIndex }}][supplier_charge]" id="supplierChargeInput_{{ $loopIndex }}"
                        value="{{ number_format($calculatedRefundCharge, 3, '.', '') }}">
                </div>
            </div>

        </div>

        <div class="text-right bg-gray-50 rounded-2xl p-6 lg:p-10 min-w-0">
            <div class="font-bold text-gray-700 mb-2">Original Task Profit</div>
            <div class="text-2xl text-blue-600 font-bold mb-6" id="originalProfit_{{ $loopIndex }}">
                {{ number_format($invoiceDetail->markup_price, 3, '.', '') }}
            </div>

            <hr class="my-6">

            <div class="font-bold text-gray-700 mb-2">Supplier Charge</div>
            <div class="text-2xl text-red-500 font-bold mb-6" id="supplierCharge_{{ $loopIndex }}">
                {{ number_format($calculatedRefundCharge, 3, '.', '') }}
            </div>

            <hr class="my-6">

            <div class="font-bold text-gray-700 mb-2">New Profit</div>
            <div class="text-2xl text-green-600 font-bold mb-6" id="newAgentMarkup_{{ $loopIndex }}">0.00</div>
            <input type="hidden" name="tasks[{{ $loopIndex }}][new_task_profit]" id="newAgentMarkupInput_{{ $loopIndex }}" value="0.00">

            <hr class="my-6">

            <div class="font-bold text-gray-700 mb-2">Total Profit (Invoice Price)</div>
            <input type="number" step="0.001" name="tasks[{{ $loopIndex }}][total_refund_to_client]" id="invoicePriceInput_{{ $loopIndex }}"
                value="{{ old('invoice_price', $isEditing && $refundDetail ? number_format($refundDetail->total_refund_to_client, 3, '.', '')
                        : number_format($invoiceDetail->markup_price + $calculatedRefundCharge, 3, '.', '')) }}"
                class="w-full px-4 py-3 border border-indigo-300 rounded-lg bg-white text-right font-bold text-lg">

            <input type="hidden" name="tasks[{{ $loopIndex }}][original_task_profit]" value="{{ $invoiceDetail->markup_price }}">
            <input type="hidden" name="tasks[{{ $loopIndex }}][total_nett_refund_charge]" value="{{ $calculatedRefundCharge }}">
            <input type="hidden" name="tasks[{{ $loopIndex }}][refund_fee_to_client]" value="{{ $invoiceDetail->markup_price + $calculatedRefundCharge }}">
        </div>
    </div>
</fieldset>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const loopIndex = {{ $loopIndex }};
        const supplierChargeInput = document.getElementById(`supplierChargeInput_${loopIndex}`);
        const supplierChargeDisplay = document.getElementById(`supplierCharge_${loopIndex}`);
        const newAgentMarkupDisplay = document.getElementById(`newAgentMarkup_${loopIndex}`);
        const newAgentMarkupInput = document.getElementById(`newAgentMarkupInput_${loopIndex}`);
        const invoicePriceInput = document.getElementById(`invoicePriceInput_${loopIndex}`);
        const originalTaskProfit = parseFloat(`{{ $invoiceDetail->markup_price }}`);

        function recalcNewMarkup() {
            const supplierCharge = parseFloat(supplierChargeInput.value) || 0;
            const totalProfit = parseFloat(invoicePriceInput.value) || 0;
            const newAgentMarkup = totalProfit - (originalTaskProfit + supplierCharge);

            supplierChargeDisplay.textContent = supplierCharge.toFixed(3);
            newAgentMarkupDisplay.textContent = newAgentMarkup.toFixed(3);
            newAgentMarkupInput.value = newAgentMarkup.toFixed(3);

            newAgentMarkupDisplay.className = newAgentMarkup >= 0
                ? "text-2xl text-green-600 font-bold mb-6"
                : "text-2xl text-red-600 font-bold mb-6";

            updateOverallSummary?.();
        }

        supplierChargeInput.addEventListener('input', recalcNewMarkup);
        invoicePriceInput.addEventListener('input', recalcNewMarkup);

        recalcNewMarkup();

        setTimeout(() => {
            if (typeof updateOverallSummary === 'function') {
                updateOverallSummary();
            }
            window.dispatchEvent(new Event('refundTaskReady'));
        }, 400);
    });
</script>
