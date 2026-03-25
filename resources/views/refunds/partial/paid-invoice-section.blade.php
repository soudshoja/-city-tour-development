<fieldset @if($isReadOnly) disabled @endif>
    <div class="border border-gray-300 rounded-lg px-10 py-12 bg-gray-50">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Original Task (Cost Price) -->
            <div>
                <label class="block text-gray-700 font-semibold">Original Task (Cost Price)</label>
                <input readonly type="number" step="0.001" name="tasks[{{ $loopIndex }}][original_task_cost]" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50"
                    value="{{ number_format($invoiceDetail->task_price - $invoiceDetail->markup_price, 3, '.', '') }}">
            </div>

            <!-- Original Task Profit -->
            <div>
                <label class="block text-gray-700 font-semibold">Original Task Profit</label>
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>
                    </svg>
                    <input readonly type="number" step="0.001" name="tasks[{{ $loopIndex }}][original_task_profit]"
                        value="{{ number_format($invoiceDetail->markup_price, 3, '.', '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50">
                </div>
            </div>

            <!-- Original Task Selling Price -->
            <div>
                <label class="block text-gray-700 font-semibold">Original Task Selling Price</label>
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 10h14M5 14h14"></path>
                    </svg>
                    <input readonly type="number" step="0.001" name="tasks[{{ $loopIndex }}][original_invoice_price]"
                        value="{{ number_format($invoiceDetail->task_price, 3, '.', '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50">
                </div>
            </div>

            <div class="col-span-full"><hr class="my-4"></div>

            @php
                $originalTaskCost = $sourceTask->total ?? ($invoiceDetail->task_price - $invoiceDetail->markup_price);
                $refundTaskCost = $task->original_task_total ?? $task->total;
            @endphp

            <!-- Refund Fee to Client -->
            <div>
                <label class="block text-gray-700 font-semibold mb-2">Refund Fee to Client</label>
                <input type="number" step="0.001" name="tasks[{{ $loopIndex }}][refund_fee_to_client]" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white"
                    value="{{ old('tasks.' . $loopIndex . '.refund_fee_to_client', ($isEditing && isset($refundDetail) && $refundDetail)
                        ? number_format($refundDetail->refund_fee_to_client ?? 0, 3, '.', '') : number_format($task->refund_charge ?? 0, 3, '.', '')) }}">
            </div>

            <!-- Refund Task Supplier Charges -->
            <div>
                <label class="block text-gray-700 font-semibold mb-2">Supplier Charges</label>
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4"></path>
                    </svg>
                    <input readonly type="number" step="0.001" name="tasks[{{ $loopIndex }}][supplier_charge]" 
                        value="{{ number_format($originalTaskCost - $refundTaskCost, 3, '.', '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50">
                </div>
            </div>

            <!-- New Profit -->
            <div>
                <label class="block text-gray-700 font-semibold mb-2">New Profit</label>
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 10h14M5 14h14"></path>
                    </svg>
                    <input type="number" step="0.001" name="tasks[{{ $loopIndex }}][new_task_profit]" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white"
                        value="{{ old('tasks.' . $loopIndex . '.new_task_profit', $isEditing && $refundDetail 
                            ? number_format($refundDetail->new_task_profit, 3, '.', '') : number_format(0, 3, '.', '')) }}">
                </div>
            </div>

            <!-- Refund Task (Cost Price) -->
            <div>
                <label class="block text-gray-700 font-semibold mb-2">Refund Task (Cost Price)</label>
                <input readonly type="number" step="0.001" name="tasks[{{ $loopIndex }}][refund_task_cost_price]" 
                       value="{{ number_format($refundTaskCost, 3, '.', '') }}" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50">
            </div>

            <!-- New Profit (repeated for layout) -->
            <div>
                <label class="block text-gray-700 font-semibold mb-2">New Profit</label>
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4"></path>
                    </svg>
                    <input readonly type="number" step="0.001" name="tasks[{{ $loopIndex }}][new_profit_display]" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50">
                </div>
            </div>

            <!-- Total Refund to Client -->
            <div>
                <label class="block text-gray-700 font-semibold mb-2">Total Refund to Client</label>
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 10h14M5 14h14"></path>
                    </svg>
                    <input type="number" step="0.001" name="tasks[{{ $loopIndex }}][total_refund_to_client]" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white">
                </div>
            </div>
            <input type="hidden" name="tasks[{{ $loopIndex }}][total_nett_refund_charge]" value="0" class="total-net-refund-charge">
        </div>
    </div>
</fieldset>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const loopIndex = {{ $loopIndex }};
        const refundFeeToClientInput = document.querySelector(`[name="tasks[${loopIndex}][refund_fee_to_client]"]`);
        const supplierChargeInput = document.querySelector(`[name="tasks[${loopIndex}][supplier_charge]"]`);
        const newProfitInput = document.querySelector(`[name="tasks[${loopIndex}][new_task_profit]"]`);
        const newProfitDisplay = document.querySelector(`[name="tasks[${loopIndex}][new_profit_display]"]`);
        const totalRefundToClientInput = document.querySelector(`[name="tasks[${loopIndex}][total_refund_to_client]"]`);
        const refundTaskCostPriceInput = document.querySelector(`[name="tasks[${loopIndex}][refund_task_cost_price]"]`);
        const totalNetRefundChargeInput = document.querySelector(`[name="tasks[${loopIndex}][total_nett_refund_charge]"]`);
        const originalTaskCost = {{ $originalTaskCost ?? 0 }};
        let initialized = false; // track whether auto-fill already happened

        function safeParse(v) {
            const n = parseFloat(v);
            return isNaN(n) ? 0 : n;
        }

        function calculateFromRefundFee() {
            const refundFeeRaw = refundFeeToClientInput.value.trim();
            const supplierCharge = safeParse(supplierChargeInput.value);
            const refundCost = safeParse(refundTaskCostPriceInput.value);

            // ✅ Auto-fill only once when the page first loads
            if (!initialized && (refundFeeRaw === "" || parseFloat(refundFeeRaw) === 0)) {
                refundFeeToClientInput.value = supplierCharge.toFixed(3);
                initialized = true;
            }

            const refundFee = safeParse(refundFeeToClientInput.value);
            const newProfit = refundFee - supplierCharge;
            newProfitInput.value = newProfit.toFixed(3);
            newProfitDisplay.value = newProfit.toFixed(3);

            const totalRefund = refundCost - newProfit;
            totalRefundToClientInput.value = totalRefund.toFixed(3);
            totalNetRefundChargeInput.value = totalRefund.toFixed(3);

            if (typeof updateOverallSummary === 'function') {
                updateOverallSummary();
            }
        }

        function calculateFromNewProfit() {
            const supplierCharge = safeParse(supplierChargeInput.value);
            const newProfit = safeParse(newProfitInput.value);
            const refundCost = safeParse(refundTaskCostPriceInput.value);

            const refundFee = supplierCharge + newProfit;
            refundFeeToClientInput.value = refundFee.toFixed(3);
            newProfitDisplay.value = newProfit.toFixed(3);

            const totalRefund = refundCost - newProfit;
            totalRefundToClientInput.value = totalRefund.toFixed(3);
            totalNetRefundChargeInput.value = totalRefund.toFixed(3);

            if (typeof updateOverallSummary === 'function') {
                updateOverallSummary();
            }
        }

        function calculateFromRefundToClient() {
            const totalRefundToClient = safeParse(totalRefundToClientInput.value);
            const refundTaskCost = safeParse(refundTaskCostPriceInput.value);

            let newProfit = totalRefundToClient - refundTaskCost;

            newProfit = newProfit * -1;

            newProfitInput.value = newProfit.toFixed(3);
            newProfitDisplay.value = newProfit.toFixed(3);

            const supplierCharge = safeParse(supplierChargeInput.value);
            const refundFee = supplierCharge + newProfit;
            refundFeeToClientInput.value = refundFee.toFixed(3);

            if (typeof updateOverallSummary === 'function') {
                updateOverallSummary();
            }
        }

        refundFeeToClientInput.addEventListener('input', () => {
            initialized = true; // stop auto-fill after first manual change
            calculateFromRefundFee();
        });

        supplierChargeInput.addEventListener('input', calculateFromRefundFee);
        newProfitInput.addEventListener('input', calculateFromNewProfit);
        totalRefundToClientInput.addEventListener('input', calculateFromRefundToClient);

        setTimeout(calculateFromRefundFee, 150);

        setTimeout(() => {
            if (typeof updateOverallSummary === 'function') {
                updateOverallSummary();
            }
            window.dispatchEvent(new Event('refundTaskReady'));
        }, 400);
    });
</script>
