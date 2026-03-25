const PaymentSelection = (function() {
    'use strict';

    const paymentCache = {};
    const selectedPayments = {};

    /**
     * Load available payments for a client via AJAX
     */
    async function loadPaymentsForClient(clientId, callback) {
        if (paymentCache[clientId]) {
            callback(paymentCache[clientId]);
            return;
        }

        try {
            const response = await fetch('/invoice/available-payments', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ client_id: clientId })
            });

            const data = await response.json();

            if (data.success) {
                paymentCache[clientId] = data.payments;
                callback(data.payments);
            } else {
                console.error('[PaymentSelection] Error:', data.message);
                callback([]);
            }
        } catch (error) {
            console.error('[PaymentSelection] AJAX Error:', error);
            callback([]);
        }
    }

    function clearCache(clientId) {
        if (clientId) {
            delete paymentCache[clientId];
        } else {
            Object.keys(paymentCache).forEach(key => delete paymentCache[key]);
        }
    }

    /**
     * Read the installment amount directly from the DOM.
     * Single source of truth — no metadata, no baked values.
     */
    function getInstallmentAmount(rowId, rowIndex) {
        if (rowIndex !== undefined && rowIndex !== null) {
            const val = parseFloat(document.getElementById(`amount_${rowIndex}`)?.value);
            if (!isNaN(val) && val > 0) return val;
        }
        // Fallback: compute from invoice total (floor-based to match card creation)
        const mode = rowId.split('_')[0];
        const splitElId = mode === 'partial' ? 'split-into1' : 'split-into';
        const splitInto = parseInt(document.getElementById(splitElId)?.value) || 1;
        const total = parseFloat(document.getElementById('subTotal')?.value) || 0;
        return Math.floor((total / splitInto) * 1000) / 1000;
    }

    /**
     * Render credit voucher list inside a credit panel.
     * Used by BOTH partial and split flows.
     */
    function renderPaymentSelectionForPartial(container, payments, rowId, rowIndex, requiredAmount) {
        if (!payments || payments.length === 0) {
            container.innerHTML = `
                <div class="text-amber-600 text-sm p-3 bg-amber-50 rounded-lg border border-amber-200">
                    No available credit payments found for this client.
                </div>
            `;
            return;
        }

        if (!selectedPayments[rowId]) {
            selectedPayments[rowId] = {};
        }

        let html = '';

        payments.forEach(payment => {
            const creditId = payment.credit_id;
            const sourceType = payment.source_type;
            const referenceNumber = payment.reference_number;
            const paymentDate = payment.date || 'N/A';
            const availableBalance = parseFloat(payment.available_balance);

            const badgeHtml = sourceType === 'refund'
                ? `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800">Refund</span>`
                : `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Topup</span>`;

            html += `
                <label class="flex items-center justify-between p-2 bg-white rounded-lg hover:bg-gray-50 border border-gray-200 cursor-pointer">
                    <div class="flex items-center gap-2 flex-1">
                        <input type="checkbox"
                            class="payment-select-checkbox w-4 h-4 text-blue-600 rounded"
                            data-credit-id="${creditId}"
                            data-available="${availableBalance}"
                            data-voucher="${referenceNumber}"
                            data-source-type="${sourceType}"
                            data-row-id="${rowId}"
                            data-row-index="${rowIndex}"
                            onchange="PaymentSelection.handleCheckboxChange(this)">

                        <div class="flex flex-col flex-1">
                            <span class="font-medium text-sm text-gray-800">${referenceNumber}</span>
                            <div class="flex items-center gap-2 mt-1">
                                ${badgeHtml}
                                <span class="text-xs text-gray-500">${paymentDate}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-green-600">${availableBalance.toFixed(3)} KWD</span>

                        <input type="number"
                            class="payment-amount-field w-20 px-2 py-1 border rounded text-sm text-right"
                            data-credit-id="${creditId}"
                            data-row-id="${rowId}"
                            data-row-index="${rowIndex}"
                            placeholder="0.000"
                            step="0.001"
                            min="0"
                            max="${availableBalance}"
                            disabled
                            onchange="PaymentSelection.handleAmountChange(this)">
                    </div>
                </label>
            `;
        });

        container.innerHTML = html;
        updatePartialSummary(rowIndex);
    }

    /**
     * Update the "Selected: X.XXX KWD" display in the credit panel
     */
    function updatePartialSummary(rowIndex) {
        const total = getSelectedTotal(`partial_${rowIndex}`) || getSelectedTotal(`split_${rowIndex}`);

        const selectedEl = document.getElementById(`credit_selected_${rowIndex}`);
        if (selectedEl) {
            selectedEl.textContent = total.toFixed(3) + ' KWD';
            if (total > 0) {
                selectedEl.classList.remove('text-gray-900');
                selectedEl.classList.add('text-green-600');
            } else {
                selectedEl.classList.remove('text-green-600');
                selectedEl.classList.add('text-gray-900');
            }
        }
    }

    /**
     * Checkbox toggled — auto-fill amount based on remaining installment balance.
     * All context from data-* attributes, no parameters needed.
     */
    function handleCheckboxChange(checkbox) {
        const rowId    = checkbox.dataset.rowId;
        const rowIndex = checkbox.dataset.rowIndex !== undefined ? parseInt(checkbox.dataset.rowIndex) : undefined;
        const creditId = checkbox.dataset.creditId;

        const amountField = document.querySelector(
            `.payment-amount-field[data-credit-id="${creditId}"][data-row-id="${rowId}"]`
        );

        if (checkbox.checked) {
            amountField.disabled = false;

            const available    = parseFloat(checkbox.dataset.available);
            const required     = getInstallmentAmount(rowId, rowIndex);
            const currentTotal = getSelectedTotal(rowId);
            const remaining    = required - currentTotal;
            const autoAmount   = Math.min(available, Math.max(0, remaining));

            amountField.value = autoAmount.toFixed(3);

            if (!selectedPayments[rowId]) selectedPayments[rowId] = {};
            selectedPayments[rowId][creditId] = autoAmount;
        } else {
            amountField.disabled = true;
            amountField.value = '';
            if (selectedPayments[rowId]) {
                delete selectedPayments[rowId][creditId];
            }
        }

        if (rowIndex !== undefined) {
            updatePartialSummary(rowIndex);
        }
    }

    /**
     * Manual amount edit in a voucher row.
     * All context from data-* attributes, no parameters needed.
     */
    function handleAmountChange(input) {
        const rowId    = input.dataset.rowId;
        const rowIndex = input.dataset.rowIndex !== undefined ? parseInt(input.dataset.rowIndex) : undefined;
        const creditId = input.dataset.creditId;
        const maxAmount = parseFloat(input.max);
        let value = parseFloat(input.value) || 0;

        if (value > maxAmount) { value = maxAmount; input.value = value.toFixed(3); }
        if (value < 0) { value = 0; input.value = '0.000'; }

        if (!selectedPayments[rowId]) selectedPayments[rowId] = {};
        selectedPayments[rowId][creditId] = value;

        if (rowIndex !== undefined) {
            updatePartialSummary(rowIndex);
        }
    }

    function getSelectedTotal(rowId) {
        if (!selectedPayments[rowId]) return 0;
        return Object.values(selectedPayments[rowId]).reduce((sum, val) => sum + (val || 0), 0);
    }

    function getSelectedPaymentsForRow(rowId) {
        if (!selectedPayments[rowId]) return [];

        return Object.entries(selectedPayments[rowId])
            .filter(([_, amount]) => amount > 0)
            .map(([creditId, amount]) => ({
                credit_id: parseInt(creditId),
                amount: amount
            }));
    }

    function clearRowSelection(rowId) {
        delete selectedPayments[rowId];
    }

    /**
     * Load and render credit vouchers for an installment row.
     */
    function showForRow(modalType, rowIndex, clientId) {
        const rowId = `${modalType}_${rowIndex}`;
        const containerId = modalType === 'split'
            ? `credit_vouchers_split_${rowIndex}`
            : `credit_vouchers_${rowIndex}`;

        const container = document.getElementById(containerId);

        if (!container) {
            console.error('[PaymentSelection] Container not found:', containerId);
            return;
        }

        container.innerHTML = `
            <div class="text-center text-sm text-gray-500 py-2">
                <div class="animate-pulse">Loading payments...</div>
            </div>
        `;

        const required = getInstallmentAmount(rowId, rowIndex);

        loadPaymentsForClient(clientId, function(payments) {
            renderPaymentSelectionForPartial(container, payments, rowId, rowIndex, required);
        });
    }

    function hideForRow(modalType, rowIndex) {
        const rowId = `${modalType}_${rowIndex}`;
        const containerId = modalType === 'split'
            ? `credit_vouchers_split_${rowIndex}`
            : `credit_vouchers_${rowIndex}`;

        const container = document.getElementById(containerId);
        if (container) container.innerHTML = '';

        clearRowSelection(rowId);
    }

    /**
     * Full reset — call on modal open/close.
     */
    function clearAllSelections() {
        Object.keys(selectedPayments).forEach(key => delete selectedPayments[key]);
        Object.keys(paymentCache).forEach(key => delete paymentCache[key]);
    }

    return {
        loadPaymentsForClient,
        clearCache,
        renderPaymentSelectionForPartial,
        updatePartialSummary,
        handleCheckboxChange,
        handleAmountChange,
        getSelectedPaymentsForRow,
        clearRowSelection,
        showForRow,
        hideForRow,
        getSelectedTotal,
        clearAllSelections
    };
})();

window.PaymentSelection = PaymentSelection;