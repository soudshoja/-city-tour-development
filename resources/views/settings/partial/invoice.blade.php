<div class="mb-6">
    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Invoice Settings</h2>
    <p class="text-sm text-gray-500 mt-1">Configure default invoice settings</p>
</div>

<div class="space-y-4">
    <!-- Invoice Expiry -->
    <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
        <div>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Invoice Expiry Day(s) Default</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Number of days before an invoice expires</p>
        </div>
        <input type="number" name="invoice-expiry-default" id="invoice-expiry-default"
            class="w-20 border border-gray-300 rounded-lg px-3 py-2 text-center text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            min="0" max="365" />
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const expiryInput = document.getElementById('invoice-expiry-default');
        if (expiryInput) {
            expiryInput.value = '{{ $invoiceExpiryDefault }}';

            expiryInput.addEventListener('change', function() {
                const newValue = expiryInput.value;
                fetch('{{ route("settings.invoice.update-expiry") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            invoice_expiry_default: newValue
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        const customSuccessAlert = document.getElementById('custom-success-ajax-alert');
                        if (customSuccessAlert) {
                            customSuccessAlert.classList.remove('hidden');
                            const successMsg = customSuccessAlert.querySelector('p');
                            if (successMsg) successMsg.innerHTML = 'Invoice expiry updated successfully';
                        }
                        setTimeout(() => customSuccessAlert.classList.add('hidden'), 3000);
                    })
                    .catch(error => {
                        console.error('Error updating invoice expiry:', error);
                    });
            });
        }
    });
</script>