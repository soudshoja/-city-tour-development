<div id="payment_gateway_section" class="mt-6 bg-white p-6 rounded-lg shadow-md">
    <h3 class="text-lg font-semibold mb-4 text-gray-800">Payment Gateway Selection</h3>

    <div class="mb-4">
        <label for="payment_gateway_option" class="block text-sm font-medium text-gray-700 mb-2">
            Choose Payment Gateway
        </label>
        @php
        $selectedPaymentGateway = isset($refund) ? strtolower($refund->invoice->invoicePartials->first()->payment_gateway) : '';
        @endphp
        <select id="payment_gateway_option" name="payment_gateway_option"
            class="border border-gray-300 p-2 rounded w-full focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            onchange="updatePaymentMethods()">
            <option value="">Select a Payment Gateway</option>
            @if(isset($paymentGateways))
            @foreach ($paymentGateways as $gateway)

            <option value="{{ $gateway->name }}"
                data-gateway-id="{{ $gateway->id }}"
                {{ $selectedPaymentGateway == strtolower(old('payment_gateway_option', $gateway->name)) ? 'selected' : '' }}>
                {{ $gateway->name }}
                @if(isset($gateway->gateway_fee) && $gateway->gateway_fee > 0)
                (Fee: {{ number_format($gateway->gateway_fee, 2) }})
                @endif
            </option>

            @endforeach
            @endif
        </select>
    </div>

    <div id="payment_method_section" class="mb-4" style="display: none;">
        <label for="payment_method_full" class="block text-sm font-medium text-gray-700 mb-2">
            Choose Payment Method
        </label>
        <select name="payment_method" id="payment_method_full"
            class="border border-gray-300 p-2 rounded w-full focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">Select Payment Method</option>
            <!-- Options will be populated by JavaScript -->
        </select>
    </div>

    <div id="gateway_fee_display" class="mb-4" style="display: none;">
        <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-blue-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                </svg>
                <div>
                    <p class="text-sm text-blue-800 font-medium">Gateway Fee Information</p>
                    <p id="gateway_fee_amount" class="text-sm text-blue-600"></p>
                </div>
            </div>
        </div>
    </div>

    <div id="auto_payment_notification" class="mb-4" style="display: none;">
        <div class="p-3 bg-green-50 border border-green-200 rounded-lg">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-green-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                </svg>
                <p class="text-sm text-green-800 font-medium">Auto Payment Enabled</p>
            </div>
        </div>
    </div>

    <!-- Hidden service charge input to send to backend -->
    <input type="hidden" name="service_charge" id="service_charge" value="{{ old('service_charge', isset($refund) ? ($refund->service_charge ?? 0) : 0) }}">
</div>

<script>
    // Payment gateway data from backend
    const paymentGateways = @json($paymentGateways ?? []);
    const paymentMethods = @json($paymentMethods ?? []);
    let old_payment_gateway = "{{ old('payment_gateway_option', isset($refund) ? strtolower($refund->invoice->invoicePartials->first()->payment_gateway) : '') }}";
    let old_payment_method = "{{ old('payment_method', isset($refund) ? (int)($refund->invoice->invoicePartials->first()->payment_method ?? 0) : '') }}";
    
    // Selected values for editing mode
    const selectedPaymentMethod = {{ isset($refund) ? (int)($refund->invoice->invoicePartials->first()->payment_method ?? 0) : 0 }};

    // Function to update the service charge input
    function updateServiceCharge(amount) {
        const serviceChargeInput = document.getElementById('service_charge');
        if (serviceChargeInput) {
            serviceChargeInput.value = parseFloat(amount).toFixed(2);
        }
    }

    function updatePaymentMethods() {
        const gatewaySelect = document.getElementById('payment_gateway_option');
        const paymentMethodSection = document.getElementById('payment_method_section');
        const paymentMethodSelect = document.getElementById('payment_method_full');
        const gatewayFeeDisplay = document.getElementById('gateway_fee_display');
        const gatewayFeeAmount = document.getElementById('gateway_fee_amount');
        const autoPaymentNotification = document.getElementById('auto_payment_notification');

        const selectedGateway = gatewaySelect.value;

        // Reset payment method dropdown
        paymentMethodSelect.innerHTML = '<option value="">Select Payment Method</option>';

        // Hide all notifications initially
        paymentMethodSection.style.display = 'none';
        gatewayFeeDisplay.style.display = 'none';
        autoPaymentNotification.style.display = 'none';

        // Reset service charge
        updateServiceCharge(0);

        if (selectedGateway) {
            // Find the selected gateway data
            const gateway = paymentGateways.find(g => g.name === selectedGateway);

            if (gateway) {
                // Update service charge with gateway fee
                let serviceCharge = gateway.gateway_fee ? parseFloat(gateway.gateway_fee) : 0;
                updateServiceCharge(serviceCharge);

                // Show gateway fee if available
                if (gateway.gateway_fee && gateway.gateway_fee > 0) {
                    gatewayFeeAmount.textContent = `Gateway fee: ${parseFloat(gateway.gateway_fee).toFixed(2)}`;
                    gatewayFeeDisplay.style.display = 'block';
                }

                // Handle MyFatoorah specific logic
                if (selectedGateway.toLowerCase() === 'myfatoorah') {
                    // Show payment method section for MyFatoorah
                    paymentMethodSection.style.display = 'block';

                    // Populate payment methods for the selected company
                    const companyId = gateway.company_id;
                    const filteredMethods = paymentMethods.filter(method =>
                        method.company_id === companyId && method.type === 'myfatoorah'
                    );

                    filteredMethods.forEach(method => {
                        const option = document.createElement('option');
                        option.value = method.id;
                        option.textContent = method.english_name;
                        
                        // Set selected if this matches the saved payment method
                        if (selectedPaymentMethod && selectedPaymentMethod == method.id) {
                            option.selected = true;
                        }
                        
                        if (method.gateway_fee && method.gateway_fee > 0) {
                            option.textContent += ` (Fee: ${parseFloat(method.gateway_fee).toFixed(2)})`;
                        }
                        paymentMethodSelect.appendChild(option);
                    });

                    if (!paymentMethodSelect.value && filteredMethods.length) {
                        paymentMethodSelect.value = String(filteredMethods[0].id);
                    }
                    if (paymentMethodSelect.value) {
                        paymentMethodSelect.dispatchEvent(new Event('change'));
                    }
                } else {
                    // For other gateways (like Tap), show auto payment notification
                    autoPaymentNotification.style.display = 'block';
                }
            }
        }
    }

    // Update gateway fee when payment method changes (for MyFatoorah)
    document.getElementById('payment_method_full').addEventListener('change', function() {
        const selectedMethodId = this.value;
        const gatewayFeeDisplay = document.getElementById('gateway_fee_display');
        const gatewayFeeAmount = document.getElementById('gateway_fee_amount');
        const selectedGateway    = document.getElementById('payment_gateway_option').value;
        let feeValue = 0;

        if (selectedMethodId) {
            const method = paymentMethods.find(m => m.id == selectedMethodId);
            feeValue = method && method.gateway_fee ? parseFloat(method.gateway_fee) : 0;
            gatewayFeeAmount.textContent = feeValue > 0 ? `Payment method fee: ${feeValue.toFixed(2)}` : `No additional fee`;
        } else {
            const gateway = paymentGateways.find(g => g.name === selectedGateway);
            feeValue = gateway && gateway.gateway_fee ? parseFloat(gateway.gateway_fee) : 0;
            gatewayFeeAmount.textContent = feeValue > 0 ? `Gateway fee: ${feeValue.toFixed(2)}` : `No additional fee`;
        }

        gatewayFeeDisplay.style.display = feeValue > 0 ? 'block' : 'none';
        updateServiceCharge(feeValue);
    });

    // Initialize the payment gateway selection when page loads (for editing mode)
    document.addEventListener('DOMContentLoaded', function() {
        updatePaymentMethods(); // This will set up the initial state based on selected gateway
        
        // If we're in editing mode and there's a selected payment method, update the service charge accordingly
        const gatewaySelect = document.getElementById('payment_gateway_option');
        const paymentMethodSelect = document.getElementById('payment_method_full');
        
        if (gatewaySelect.value && paymentMethodSelect.value) {
            // Trigger the payment method change event to set the correct service charge
            paymentMethodSelect.dispatchEvent(new Event('change'));
        }
    });
</script>