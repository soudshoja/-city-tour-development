<x-app-layout>
    <ul class="flex space-x-2 rtl:space-x-reverse pb-5 text-base md:text-lg sm:text-sm">
        <li>
            <a href="{{ route('dashboard') }}" class="customBlueColor hover:underline"> Dashboard</a>
        </li>
        <li class="before:content-['/'] before:mr-1 ">
            <span>Settings</span>
        </li>
    </ul>
    <div id="setting-index" class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4">
        <div class="p-2 grid grid-cols-1">
            <p class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                Invoices
            </p>
            <hr class="my-2">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="p-2 bg-gray-50 shadow rounded flex justify-between gap-2">
                    <p>
                        Invoice Expiry Day(s) Default:
                    </p>
                    <input type="number" name="invoice-expiry-default" id="invoice-expiry-default" class="border border-gray-300 rounded-md p-2 w-24" min="0" max="365" />
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const expiryInput = document.getElementById('invoice-expiry-default');
            const settingIndex = document.getElementById('setting-index');
            expiryInput.value = '{{ $invoiceExpiryDefault }}';

            customSuccessAlert = document.getElementById('custom-success-alert');
            customErrorAlert = document.getElementById('custom-error-alert');

            console.log('Custom Success Alert:', customSuccessAlert);
            console.log('Custom Error Alert:', customErrorAlert);

            expiryInput.addEventListener('change', function () {
                const newValue = expiryInput.value;
                fetch('{{ route('settings.invoice.update-expiry') }}', {
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
                    console.log('Invoice expiry updated successfully:', data);
                    if (customSuccessAlert) {
                        customSuccessAlert.classList.remove('hidden');

                        const successMsg = customSuccessAlert.querySelector('p');
                        successMsg.innerHTML = 'Invoice expiry updated successfully';
                        settingIndex.appendChild(customSuccessAlert);

                        setTimeout(() => {
                            customSuccessAlert.classList.add('hidden');
                        }, 3000);
                        
                    }
                })
                .catch(error => {
                    console.error('Error updating invoice expiry:', error);
                    alert('Error updating invoice expiry');
                    if (customErrorAlert) {
                        customErrorAlert.classList.remove('hidden');

                        const errorMsg = customErrorAlert.querySelector('p');
                        errorMsg.innerHTML = 'Error updating invoice expiry';
                        settingIndex.appendChild(customErrorAlert);

                        setTimeout(() => {
                            customErrorAlert.classList.add('hidden');
                        }, 3000);
                    }
                });
            });


        });
    </script>
</x-app-layout>