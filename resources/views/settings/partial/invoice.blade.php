<div class="mb-6">
    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Invoice Settings</h2>
    <p class="text-sm text-gray-500 mt-1">Configure default invoice settings</p>
</div>

<div class="space-y-4">
    @can('settingCompanyInvoice', 'App\Models\Setting')
    <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
        <div>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Invoice Expiry Day(s) Default</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Number of days before an invoice expires</p>
        </div>
        <input type="number" name="invoice-expiry-default" id="invoice-expiry-default"
            class="w-20 border border-gray-300 rounded-lg px-3 py-2 text-center text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            min="0" max="365" />
    </div>
    @endcan
    <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
        <div>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Invoice WhatsApp Notification</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Send WhatsApp notification to client upon successful payment</p>
        </div>
        <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" id="invoice-whatsapp-notification" class="sr-only peer">
            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
        </label>
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

        // Handle WhatsApp notification toggle
        const whatsappCheckbox = document.getElementById('invoice-whatsapp-notification');
        if (whatsappCheckbox) {
            whatsappCheckbox.checked = {{ $invoiceWhatsappSetting ? 'true' : 'false' }};

            whatsappCheckbox.addEventListener('change', function() {
                const isEnabled = whatsappCheckbox.checked;
                fetch('{{ route("user-settings.update") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            key: 'invoice_whatsapp_notification',
                            value: isEnabled
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        const customSuccessAlert = document.getElementById('custom-success-ajax-alert');
                        if (customSuccessAlert) {
                            customSuccessAlert.classList.remove('hidden');
                            const successMsg = customSuccessAlert.querySelector('p');
                            if (successMsg) successMsg.innerHTML = 'WhatsApp notification setting updated successfully';
                        }
                        setTimeout(() => customSuccessAlert.classList.add('hidden'), 3000);
                    })
                    .catch(error => {
                        console.error('Error updating WhatsApp notification:', error);
                    });
            });
        }
    });
</script>