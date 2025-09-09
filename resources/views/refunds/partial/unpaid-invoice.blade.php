<style>
    .calculation-section {
        display: none;
    }

    @media (min-width: 768px) {
        .calculation-section {
            display: block !important;
        }
    }

    .calculation-section.mobile-show {
        display: block !important;
    }
</style>
@php
$isEditing = isset($refund) && $refund;
$formAction = $isEditing
? route('refunds.update', ['task' => $task, 'refund' => $refund])
: route('refunds.store-unpaid');
$formMethod = $isEditing ? 'PUT' : 'POST';
$isLocked = $isEditing && in_array(strtolower(optional($refund->invoice)->status), ['paid']);
@endphp

<form action="{{ $formAction }}" method="POST">
    @if($isEditing)
    @method('PUT')
    @endif
    @csrf
    @if($isLocked)
        <div class="mb-4 p-3 rounded bg-green-50 border border-green-200 text-green-800">
            This refund is locked because the refund invoice has been marked as <strong>Paid</strong>.
            You can no longer edit it.
        </div>
    @endif
    <fieldset @if($isLocked) disabled @endif>
    <input type="hidden" name="task_id" value="{{ $task->id }}">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
        <div>
            <label for="date" class="block text-gray-700 font-semibold mb-2">Date</label>
            <input type="date" name="date" id="date"
                value="{{ old('date', $isEditing ? $refund->date : now()->toDateString()) }}" required
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
            @error('date')
            <span class="text-red-500 text-sm">{{ $message }}</span>
            @enderror
        </div>
        <div class="mb-6">
            <label for="reference" class="block text-gray-700 font-semibold mb-2">Reference</label>
            <input type="text" name="reference" id="reference"
                value="{{ old('reference', $isEditing ? $refund->reference : '') }}"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 border border-gray-300 rounded-lg px-6 lg:px-10 py-10 lg:py-20 bg-gray-50">
        <div class="lg:col-span-2 text-left bg-slate-50 rounded-2xl p-4 lg:p-8 calculation-section">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Original Task Calculation</h3>
            <div class="flex flex-col xl:flex-row xl:justify-between xl:items-center mb-6 space-y-4 xl:space-y-0 xl:space-x-4">
                <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm flex-1">
                    <label for="original_invoice_price" class="block font-semibold text-gray-700 mb-2">Original Invoice Price</label>
                    <input readonly type="number" step="0.01" name="original_invoice_price"
                        id="original_invoice_price" value="{{ old('original_invoice_price', $isEditing && $refund ?
                            number_format($refund->airline_nett_fare, 2, '.', '') : number_format($invoiceDetail->task_price, 2, '.', '')) }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 font-medium text-gray-800" readonly>
                </div>

                <div class="flex items-center justify-center w-8 h-8 bg-gray-200 rounded-full">
                    <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4"></path>
                    </svg>
                </div>

                <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm flex-1">
                    <label for="original_task_price" class="block font-semibold text-gray-700 mb-2">Original Task (Cost Price)</label>
                    <input readonly type="number" step="0.01" name="original_task_price"
                        id="original_task_price"
                        value="{{ old('original_task_price', number_format($invoiceDetail->supplier_price, 2, '.', '') ?? '') }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 font-medium text-gray-800" readonly>
                </div>

                <div class="flex items-center justify-center w-8 h-8 bg-green-100 rounded-full">
                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 10h14M5 14h14"></path>
                    </svg>
                </div>

                <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm flex-1">
                    <label class="block font-semibold text-gray-700 mb-2">Original Task Profit</label>
                    <div class="px-3 py-2 bg-green-50 border border-green-200 rounded-lg text-green-700 font-semibold">
                        {{ number_format($refund->original_task_profit ?? $invoiceDetail->markup_price, 2, '.', '') }}
                    </div>
                </div>
            </div>

            <h3 class="text-lg font-semibold text-gray-800 mb-4 mt-8">Refund Calculation</h3>
            <div class="flex flex-col xl:flex-row xl:justify-between xl:items-center mb-6 space-y-4 xl:space-y-0 xl:space-x-4">
                <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm flex-1">
                    <label class="block font-semibold text-gray-700 mb-2">Original Task (Cost Price)</label>
                    <div class="px-3 py-2 bg-blue-50 border border-blue-200 rounded-lg text-blue-700 font-semibold">
                        {{ $task->originalTask->total }}
                    </div>
                </div>

                <div class="flex items-center justify-center w-8 h-8 bg-gray-200 rounded-full">
                    <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4"></path>
                    </svg>
                </div>

                <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm flex-1">
                    <label class="block font-semibold text-gray-700 mb-2">Refund Task Price</label>
                    <div class="px-3 py-2 bg-blue-50 border border-blue-200 rounded-lg text-blue-700 font-semibold">
                        {{ $task->total }}
                    </div>
                </div>

                <div class="flex items-center justify-center w-8 h-8 bg-red-100 rounded-full">
                    <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 10h14M5 14h14"></path>
                    </svg>
                </div>

                <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm flex-1">
                    <label class="block font-semibold text-gray-700 mb-2">Supplier Charge</label>
                    <div class="px-3 py-2 bg-red-50 border border-red-200 rounded-lg text-red-700 font-semibold">
                        {{ $task->refund_charge }}
                    </div>
                </div>
            </div>
        </div>
        <!-- PROFIT SECTION -->
        <div class="text-right bg-gray-50 rounded-2xl p-4 lg:p-8 min-w-0">
            <div class="font-bold text-gray-700 mb-2">Original Task Profit</div>
            <div class="text-2xl text-blue-600 font-bold mb-4" id="originalProfit">
                {{ number_format($invoiceDetail->markup_price, 2, '.', '') }}
            </div>
            <hr class="my-4">
            <div class="font-bold text-gray-700 mb-2">Supplier Charge</div>
            <div class="text-2xl text-red-500 font-bold mb-4" id="supplierCharge">
                {{ number_format($task->refund_charge, 2, '.', '') }}
            </div>
            <hr class="my-4">
            <div class="font-bold text-gray-700 mb-2">New Agent Markup</div>
            <div class="text-2xl text-green-600 font-bold mb-4" id="newAgentMarkup">
                {{ number_format($isEditing && $refund ? $refund->new_task_profit : ($task->total - $invoiceDetail->markup_price - $task->refund_charge), 2, '.', '') }}
            </div>
            <hr class="my-4">
            <div class="font-bold text-gray-700 mb-2">Total Profit (Invoice Price)</div>
            <input type="number" step="0.01" name="invoice_price" id="invoicePriceInput"
                value="{{ old('invoice_price', $isEditing && $refund ?
                    number_format($refund->airline_nett_fare - $refund->total_nett_refund, 2, '.', '') : (isset($refundInvoiceDetail) ? number_format($refundInvoiceDetail->amount, 2, '.', '') : number_format($invoiceDetail->markup_price + $task->refund_charge, 2, '.', ''))) }}"
                class="w-full px-4 py-2 border border-indigo-300 rounded-lg bg-white text-right font-bold text-lg" />

            <!-- Hidden inputs to send calculated values to backend -->
            <input type="hidden" name="original_task_profit" id="originalTaskProfitInput"
                value="{{ old('original_task_profit', $isEditing && $refund ? $refund->original_task_profit : number_format($invoiceDetail->markup_price, 2, '.', '')) }}">
            <input type="hidden" name="supplier_charge" id="supplierChargeInput"
                value="{{ old('supplier_charge', $isEditing && $refund ? $refund->service_charge : number_format($task->refund_charge, 2, '.', '')) }}">
            <input type="hidden" name="new_agent_markup" id="newAgentMarkupInput"
                value="{{ old('new_agent_markup', $isEditing && $refund ? $refund->new_task_profit : number_format($task->total - $invoiceDetail->markup_price - $task->refund_charge, 2, '.', '')) }}">
        </div>
        <!-- Mobile toggle button -->
        <button type="button" class="md:hidden mt-4 px-4 py-2 bg-indigo-500 text-white rounded-lg" onclick="toggleCalculation()">Show Details</button>
    </div>

    <!-- Additional Fields Section -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">
        <div>
            <label for="remarks" class="block text-gray-700 font-semibold mb-2">Remarks</label>
            <input type="text" name="remarks" id="remarks" value="{{ old('remarks', $isEditing ? $refund->remarks : '') }}"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
        </div>

        <div>
            <label for="remarks_internal" class="block text-gray-700 font-semibold mb-2">Internal Remarks</label>
            <input type="text" name="remarks_internal" id="remarks_internal"
                value="{{ old('remarks_internal', $isEditing ? $refund->remarks_internal : '') }}"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
        </div>
    </div>

    <div class="mt-6">
        <label for="reason" class="block text-gray-700 font-semibold mb-2">Reason</label>
        <textarea name="reason" id="reason" rows="3"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">{{ old('reason', $isEditing ? $refund->reason : '') }}</textarea>
        @error('reason')
        <span class="text-red-500 text-sm">{{ $message }}</span>
        @enderror
    </div>

    @include('refunds.partial.payment-gateway-selection')

    </fieldset>
    @if($isLocked)
        <button type="button" class="mt-6 px-6 py-3 bg-gray-300 text-gray-600 font-semibold rounded-lg cursor-not-allowed" disabled>
            Update Refund
        </button>
    @else
        <button type="submit" class="mt-6 px-6 py-3 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 transition duration-300">
            {{ $isEditing ? 'Update Refund' : 'Submit Refund' }}
        </button>
    @endif
</form>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var invoiceInput = document.getElementById('invoicePriceInput');
        var originalProfit = parseFloat(document.getElementById('originalProfit').textContent.replace(/,/g, '')) || 0;
        var supplierCharge = parseFloat(document.getElementById('supplierCharge').textContent.replace(/,/g, '')) || 0;
        var markupEl = document.getElementById('newAgentMarkup');
        var newAgentMarkupInput = document.getElementById('newAgentMarkupInput');

        function updateMarkup() {
            var invoiceVal = parseFloat(invoiceInput.value) || 0;
            var markup = invoiceVal - originalProfit - supplierCharge;
            // Fix floating point precision and negative zero
            markup = Math.round(markup * 100) / 100;
            if (markup === -0) markup = 0;
            markupEl.textContent = markup.toFixed(2);
            // Update hidden input field
            newAgentMarkupInput.value = markup.toFixed(2);
        }
        invoiceInput.addEventListener('input', updateMarkup);
        updateMarkup();
    });

    function toggleCalculation() {
        var calc = document.querySelector('.calculation-section');
        if (calc) {
            calc.classList.toggle('mobile-show');
        }
    }
</script>