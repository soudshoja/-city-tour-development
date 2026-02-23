const PaymentSelection = (function() {
    'use strict';

    const paymentCache = {};
    
    const selectedPayments = {};

    /**
     * Load available payments for a client via AJAX
     */
    async function loadPaymentsForClient(clientId, callback) {
        if (paymentCache[clientId]) {
            console.log('[PaymentSelection] Using cached payments for client:', clientId);
            callback(paymentCache[clientId]);
            return;
        }

        console.log('[PaymentSelection] Loading payments for client:', clientId);

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
                console.log('[PaymentSelection] Loaded payments:', data.payments);
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

    /**
     * Clear cache for a specific client
     */
    function clearCache(clientId) {
        if (clientId) {
            delete paymentCache[clientId];
        } else {
            Object.keys(paymentCache).forEach(key => delete paymentCache[key]);
        }
    }

    /**
     * Render payment selection UI for SPLIT payments (table row style)
     */
    function renderPaymentSelection(container, payments, rowId, requiredAmount, onSelectionChange) {
        if (!payments || payments.length === 0) {
            container.innerHTML = `
                <div class="text-amber-600 text-sm p-2 bg-amber-50 rounded">
                    No available credit payments found for this client.
                </div>
            `;
            return;
        }

        if (!selectedPayments[rowId]) {
            selectedPayments[rowId] = {};
        }

        let html = `
            <div class="payment-selection-wrapper" data-row-id="${rowId}">
                <div class="text-xs text-gray-600 mb-2">Select which payment(s) to use for this portion:</div>
                <div class="payment-items space-y-1 max-h-32 overflow-y-auto">
        `;

        payments.forEach((payment, index) => {
            const creditId = payment.credit_id;
            const sourceType = payment.source_type;
            const referenceNumber = payment.reference_number;
            const paymentDate = payment.date || 'N/A';
            const availableBalance = parseFloat(payment.available_balance);
        
            const badgeHtml = sourceType === 'refund'
                ? `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800">
                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/>
                    </svg>
                    Refund
                </span>`
                : `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd"/>
                    </svg>
                    Topup
                </span>`;
        
            html += `
                <div class="payment-item flex items-center justify-between p-3 bg-gray-50 rounded hover:bg-gray-100">
                    <label class="flex items-center flex-1 cursor-pointer gap-3">
                        <input type="checkbox"
                            class="payment-select-checkbox"
                            data-credit-id="${creditId}"
                            data-available="${availableBalance}"
                            data-voucher="${referenceNumber}"
                            data-source-type="${sourceType}"
                            data-row-id="${rowId}"
                            onchange="PaymentSelection.handleCheckboxChange(this, '${rowId}', ${requiredAmount})">
        
                        <div class="flex flex-col">
                            <span class="font-medium text-gray-800">${referenceNumber}</span>
                            <div class="flex items-center gap-2 mt-1">
                                ${badgeHtml}
                                <span class="text-xs text-gray-500">${paymentDate}</span>
                            </div>
                        </div>
                    </label>
        
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-green-600">${availableBalance.toFixed(3)} KWD</span>
                        <input type="number"
                            class="payment-amount-field w-24 px-2 py-1 border rounded text-sm text-right"
                            data-credit-id="${creditId}"
                            data-row-id="${rowId}"
                            placeholder="Amount"
                            step="0.001"
                            min="0"
                            max="${availableBalance}"
                            disabled
                            onchange="PaymentSelection.handleAmountChange(this, '${rowId}', ${requiredAmount})">
                    </div>
                </div>
            `;
        });

        html += `
                </div>
                <div class="flex items-center gap-4 mt-2 text-xs text-gray-500">
                    <div class="flex items-center gap-1">
                        <span class="w-3 h-3 bg-green-100 rounded"></span>
                        <span>Credit Topup</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="w-3 h-3 bg-orange-100 rounded"></span>
                        <span>From Refund</span>
                    </div>
                </div>
                <div class="payment-selection-summary mt-2 pt-2 border-t text-xs">
                    <div class="flex justify-between">
                        <span>Selected:</span>
                        <span class="selected-total font-medium" data-row-id="${rowId}">0.000 KWD</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Required:</span>
                        <span class="required-total">${requiredAmount.toFixed(3)} KWD</span>
                    </div>
                    <div class="status-message mt-1 text-xs" data-row-id="${rowId}"></div>
                </div>
            </div>
        `;

        container.innerHTML = html;
    }

    /**
     * Render payment selection UI for PARTIAL payments (credit panel style)
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

        payments.forEach((payment, index) => {
            const creditId = payment.credit_id;
            const sourceType = payment.source_type;
            const referenceNumber = payment.reference_number;
            const paymentDate = payment.date || 'N/A';
            const availableBalance = parseFloat(payment.available_balance);
        
            const badgeHtml = sourceType === 'refund'
                ? `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800">
                    Refund
                </span>`
                : `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                    Topup
                </span>`;
        
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
                            onchange="PaymentSelection.handleCheckboxChange(this, '${rowId}', ${requiredAmount}, ${rowIndex})">
        
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
                            placeholder="0.000"
                            step="0.001"
                            min="0"
                            max="${availableBalance}"
                            disabled
                            onchange="PaymentSelection.handleAmountChange(this, '${rowId}', ${requiredAmount}, ${rowIndex})">
                    </div>
                </label>
            `;
        });

        container.innerHTML = html;

        // Update the summary section
        updatePartialSummary(rowIndex, requiredAmount);
    }

    /**
     * Update summary for partial payment credit panel
     */
    function updatePartialSummary(rowIndex, requiredAmount) {
        const rowId = `partial_${rowIndex}`;
        const total = getSelectedTotal(rowId);
        
        const selectedEl = document.getElementById(`credit_selected_${rowIndex}`);
        
        if (selectedEl) {
            selectedEl.textContent = total.toFixed(3) + ' KWD';
            
            // Add visual feedback
            if (total > 0) {
                selectedEl.classList.remove('text-gray-900');
                selectedEl.classList.add('text-green-600');
            } else {
                selectedEl.classList.remove('text-green-600');
                selectedEl.classList.add('text-gray-900');
            }
        }
    }  
    
    function handleCheckboxChange(checkbox, rowId, requiredAmount, rowIndex) {
        const creditId = checkbox.dataset.creditId;
        const amountField = document.querySelector(
            `.payment-amount-field[data-credit-id="${creditId}"][data-row-id="${rowId}"]`
        );

        // FIX: Get the CURRENT amount from the input field instead of using the initial requiredAmount
        let currentRequiredAmount = requiredAmount;
        if (rowIndex !== undefined) {
            const amountInput = document.getElementById(`amount_${rowIndex}`);
            if (amountInput) {
                currentRequiredAmount = parseFloat(amountInput.value) || requiredAmount;
            }
        }

        if (checkbox.checked) {
            amountField.disabled = false;
        
            const available = parseFloat(checkbox.dataset.available);
            const currentTotal = getSelectedTotal(rowId);
            const remaining = currentRequiredAmount - currentTotal;
            const autoAmount = Math.min(available, Math.max(0, remaining));
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

        // Update summary based on modal type - use CURRENT amount
        if (rowIndex !== undefined) {
            updatePartialSummary(rowIndex, currentRequiredAmount);
        } else {
            updateSummary(rowId, currentRequiredAmount);
        }
    }

    function handleAmountChange(input, rowId, requiredAmount, rowIndex) {
        const creditId = input.dataset.creditId;
        const maxAmount = parseFloat(input.max);
        let value = parseFloat(input.value) || 0;
        
        // Validate max
        if (value > maxAmount) {
            value = maxAmount;
            input.value = value.toFixed(3);
        }
        if (value < 0) {
            value = 0;
            input.value = '0.000';
        }
        
        // Update internal state
        if (!selectedPayments[rowId]) selectedPayments[rowId] = {};
        selectedPayments[rowId][creditId] = value;
        
        // Get new total
        const totalSelected = getSelectedTotal(rowId);
        
        // Update installment for PARTIAL payments
        if (rowIndex !== undefined) {
            const amountInput = document.getElementById(`amount_${rowIndex}`);
            const badge = document.getElementById(`card_amount_badge_${rowIndex}`);
            const selectedDisplay = document.getElementById(`credit_selected_${rowIndex}`);
            
            // Update amount input (only if readonly/credit-controlled)
            if (amountInput && amountInput.readOnly) {
                amountInput.value = totalSelected.toFixed(3);
            }
            
            // Update badge
            if (badge) {
                badge.textContent = `${totalSelected.toFixed(3)} KWD`;
            }
            
            // Update selected display
            if (selectedDisplay) {
                selectedDisplay.textContent = `${totalSelected.toFixed(3)} KWD`;
            }
            
            // Update global state
            if (typeof creditUsed !== 'undefined') {
                creditUsed[rowIndex] = totalSelected;
            }
            
            // Recalculate other installments
            if (typeof recalculateAfterCreditSelection === 'function') {
                recalculateAfterCreditSelection(rowIndex, totalSelected);
            }
            
            updatePartialSummary(rowIndex, requiredAmount);
        } else {
            updateSummary(rowId, requiredAmount);
        }
        
        // Update footer totals
        if (typeof updateTotalPartialPayment === 'function') {
            updateTotalPartialPayment();
        }
    }

    function getSelectedTotal(rowId) {
        if (!selectedPayments[rowId]) return 0;
        return Object.values(selectedPayments[rowId]).reduce((sum, val) => sum + (val || 0), 0);
    }

    function updateSummary(rowId, requiredAmount) {
        const total = getSelectedTotal(rowId);
        const totalEl = document.querySelector(`.selected-total[data-row-id="${rowId}"]`);
        const statusEl = document.querySelector(`.status-message[data-row-id="${rowId}"]`);

        if (totalEl) {
            totalEl.textContent = total.toFixed(3) + ' KWD';
        }

        if (statusEl) {
            const diff = total - requiredAmount;
            if (diff < 0) {
                statusEl.textContent = `⚠️ Short by ${Math.abs(diff).toFixed(3)} KWD`;
                statusEl.className = 'status-message mt-1 text-xs text-red-600';
            } else if (diff > 0) {
                statusEl.textContent = `ℹ️ Excess ${diff.toFixed(3)} KWD stays in credit`;
                statusEl.className = 'status-message mt-1 text-xs text-amber-600';
            } else {
                statusEl.textContent = '✓ Exact match';
                statusEl.className = 'status-message mt-1 text-xs text-green-600';
            }
        }
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
        if (selectedPayments[rowId]) {
            delete selectedPayments[rowId];
        }
    }

    /**
     * Show payment selection for a specific row
     */
    function showForRow(modalType, rowIndex, clientId, requiredAmount) {
        const rowId = `${modalType}_${rowIndex}`;
        
        let container;
        
        if (modalType === 'partial') {
            container = document.getElementById(`credit_vouchers_${rowIndex}`);
        } else if (modalType === 'split') {
            // ← Use the same ID that createCreditSelectionPanel generates
            container = document.getElementById(`credit_vouchers_split_${rowIndex}`);
        }

        if (!container) {
            console.error('[PaymentSelection] Could not find container for row:', rowId, 
                '— looked for:', modalType === 'split' 
                    ? `credit_vouchers_split_${rowIndex}` 
                    : `credit_vouchers_${rowIndex}`);
            return;
        }

        // Show loading state
        container.innerHTML = `
            <div class="text-center text-sm text-gray-500 py-2">
                <div class="animate-pulse">Loading payments...</div>
            </div>
        `;

        // Load and render payments
        loadPaymentsForClient(clientId, function(payments) {
            if (modalType === 'partial') {
                renderPaymentSelectionForPartial(container, payments, rowId, rowIndex, requiredAmount);
            } else {
                renderPaymentSelectionForPartial(container, payments, rowId, rowIndex, requiredAmount);
                // ↑ Use partial renderer for split too — it renders into the credit panel correctly
            }
        });
    }

    function hideForRow(modalType, rowIndex) {
        const rowId = `${modalType}_${rowIndex}`;
        
        if (modalType === 'split') {
            const container = document.getElementById(`credit_vouchers_split_${rowIndex}`);
            if (container) container.innerHTML = '';
        } else if (modalType === 'partial') {
            const container = document.getElementById(`credit_vouchers_${rowIndex}`);
            if (container) container.innerHTML = '';
        }
        
        clearRowSelection(rowId);
    }

    function clearAllSelections() {
        // Clear all internal selection state
        Object.keys(selectedPayments).forEach(key => delete selectedPayments[key]);
        // Clear cache so fresh data loads next time
        Object.keys(paymentCache).forEach(key => delete paymentCache[key]);
    }

    // Public API
    return {
        loadPaymentsForClient,
        clearCache,
        renderPaymentSelection,
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

// Make it globally accessible
window.PaymentSelection = PaymentSelection;