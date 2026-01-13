const PaymentSelection = (function() {
    'use strict';

    const paymentCache = {};
    
    const selectedPayments = {};

    /**
     * Load available payments for a client via AJAX
     * @param {number} clientId - The client ID
     * @param {function} callback - Callback with payments data
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
     * Clear cache for a specific client (call after payment is used)
     * @param {number} clientId - The client ID
     */
    function clearCache(clientId) {
        if (clientId) {
            delete paymentCache[clientId];
        } else {
            // Clear all cache
            Object.keys(paymentCache).forEach(key => delete paymentCache[key]);
        }
    }

    /**
     * Render payment selection UI for a row
     * @param {HTMLElement} container - Container element to render into
     * @param {Array} payments - Available payments
     * @param {string} rowId - Unique row identifier
     * @param {number} requiredAmount - Amount needed for this row
     * @param {function} onSelectionChange - Callback when selection changes
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
            const paymentId = payment.payment.id;
            const voucherNumber = payment.payment.voucher_number;
            const paymentDate = payment.payment.payment_date || 'N/A';
            const availableBalance = parseFloat(payment.available_balance);

            html += `
                <div class="payment-item flex items-center gap-2 p-2 bg-white border rounded hover:bg-gray-50">
                    <input type="checkbox" 
                        class="payment-select-checkbox"
                        data-payment-id="${paymentId}"
                        data-available="${availableBalance}"
                        data-voucher="${voucherNumber}"
                        data-row-id="${rowId}"
                        onchange="PaymentSelection.handleCheckboxChange(this, '${rowId}', ${requiredAmount})">
                    <div class="flex-1 text-sm">
                        <span class="font-medium">${voucherNumber}</span>
                        <span class="text-gray-500 text-xs ml-1">(${paymentDate})</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="text-xs text-green-600">${availableBalance.toFixed(3)}</span>
                        <input type="number" 
                            class="payment-amount-field w-20 px-1 py-0.5 border rounded text-xs text-right"
                            data-payment-id="${paymentId}"
                            data-row-id="${rowId}"
                            placeholder="0.000"
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

        container.dataset.onSelectionChange = onSelectionChange ? onSelectionChange.name : '';
    }

    function handleCheckboxChange(checkbox, rowId, requiredAmount) {
        const paymentId = checkbox.dataset.paymentId;
        const amountField = document.querySelector(
            `.payment-amount-field[data-payment-id="${paymentId}"][data-row-id="${rowId}"]`
        );

        if (checkbox.checked) {
            amountField.disabled = false;
           
            const available = parseFloat(checkbox.dataset.available);
            const currentTotal = getSelectedTotal(rowId);
            const remaining = requiredAmount - currentTotal;
            const autoAmount = Math.min(available, Math.max(0, remaining));
            amountField.value = autoAmount.toFixed(3);
            
            if (!selectedPayments[rowId]) selectedPayments[rowId] = {};
            selectedPayments[rowId][paymentId] = autoAmount;
        } else {
            amountField.disabled = true;
            amountField.value = '';
            if (selectedPayments[rowId]) {
                delete selectedPayments[rowId][paymentId];
            }
        }

        updateSummary(rowId, requiredAmount);
    }

    function handleAmountChange(input, rowId, requiredAmount) {
        const paymentId = input.dataset.paymentId;
        const maxAmount = parseFloat(input.max);
        let value = parseFloat(input.value) || 0;

        if (value > maxAmount) {
            value = maxAmount;
            input.value = value.toFixed(3);
        }
        if (value < 0) {
            value = 0;
            input.value = '0.000';
        }

        if (!selectedPayments[rowId]) selectedPayments[rowId] = {};
        selectedPayments[rowId][paymentId] = value;

        updateSummary(rowId, requiredAmount);
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

    /**
     * Get selected payments for a row
     * @param {string} rowId - Row identifier
     * @returns {Array} Array of {payment_id, amount}
     */
    function getSelectedPaymentsForRow(rowId) {
        if (!selectedPayments[rowId]) return [];
        
        return Object.entries(selectedPayments[rowId])
            .filter(([_, amount]) => amount > 0)
            .map(([paymentId, amount]) => ({
                payment_id: parseInt(paymentId),
                amount: amount
            }));
    }

    function clearRowSelection(rowId) {
        if (selectedPayments[rowId]) {
            delete selectedPayments[rowId];
        }
    }

    /**
     * Show payment selection for a specific row when Credit is selected
     * @param {string} modalType - 'partial' or 'split'
     * @param {number} rowIndex - Row index
     * @param {number} clientId - Client ID
     * @param {number} requiredAmount - Amount required for this row
     */
    function showForRow(modalType, rowIndex, clientId, requiredAmount) {
        const rowId = `${modalType}_${rowIndex}`;
        
        let container = document.getElementById(`payment-selection-${rowId}`);
        
        if (!container) {
            container = document.createElement('div');
            container.id = `payment-selection-${rowId}`;
            container.className = 'payment-selection-container';
            
            if (modalType === 'split') {
                
                const gatewaySelect = document.getElementById(`payment_gateway_${rowIndex}`);
                if (gatewaySelect) {
                    const currentRow = gatewaySelect.closest('tr');
                    if (currentRow) {
                        const newRow = document.createElement('tr');
                        newRow.id = `payment-selection-row-${rowId}`;
                        newRow.innerHTML = `<td colspan="7" class="px-4 py-2 bg-blue-50">${container.outerHTML}</td>`;
                        currentRow.parentNode.insertBefore(newRow, currentRow.nextSibling);
                        
                        container = document.getElementById(`payment-selection-${rowId}`);
                    }
                }
            } else {
                
                const gatewaySelect = document.getElementById(`payment_gateway1_${rowIndex}`);
                if (gatewaySelect) {
                    gatewaySelect.parentNode.insertBefore(container, gatewaySelect.nextSibling);
                }
            }
        }

        if (!container) {
            console.error('[PaymentSelection] Could not find container for row:', rowId);
            return;
        }

        container.innerHTML = `
            <div class="p-2 bg-blue-50 border border-blue-200 rounded mt-2">
                <div class="animate-pulse text-center text-sm text-gray-500">Loading payments...</div>
            </div>
        `;
        container.style.display = 'block';

        loadPaymentsForClient(clientId, function(payments) {
            renderPaymentSelection(container, payments, rowId, requiredAmount, null);
        });
    }

    /**
     * Hide payment selection for a row
     */
    function hideForRow(modalType, rowIndex) {
        const rowId = `${modalType}_${rowIndex}`;
        
        if (modalType === 'split') {
            // For split: remove the entire payment selection row
            const selectionRow = document.getElementById(`payment-selection-row-${rowId}`);
            if (selectionRow) {
                selectionRow.remove();
            }
        } else {
            // For partial: just hide the container
            const container = document.getElementById(`payment-selection-${rowId}`);
            if (container) {
                container.style.display = 'none';
                container.innerHTML = '';
            }
        }
        
        clearRowSelection(rowId);
    }

    // Public API
    return {
        loadPaymentsForClient,
        clearCache,
        renderPaymentSelection,
        handleCheckboxChange,
        handleAmountChange,
        getSelectedPaymentsForRow,
        clearRowSelection,
        showForRow,
        hideForRow,
        getSelectedTotal
    };
})();

// Make it globally accessible
window.PaymentSelection = PaymentSelection;
