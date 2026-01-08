<div>
    <div class="mb-6">
        <h2 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">WhatsApp</h2>
        <p class="text-xs text-gray-500 mt-1 italic">Powered By Resayil</p>
    </div>

    @if(session('success'))
    <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
        {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
        {{ session('error') }}
    </div>
    @endif

    <form action="{{ route('system-settings.send-whatsapp-pdf') }}" method="POST" class="space-y-6">
        @csrf

        <div class="grid grid-cols-1 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                    Select Payment
                </label>
                <div class="relative">
                    <input type="text" id="whatsappPaymentSearchDisplay" 
                        placeholder="Search payment..." 
                        class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500 cursor-pointer bg-white"
                        onclick="toggleWhatsAppPaymentDropdown()" readonly>
                    <input type="hidden" name="payment_id" id="whatsappPaymentId" required>
                    <div class="absolute right-3 top-3 pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div id="whatsappPaymentDropdown" class="hidden absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-72 overflow-hidden">
                        <div class="p-2 border-b bg-gray-50">
                            <input type="text" id="whatsappPaymentSearchInput" placeholder="Type to search..."
                                class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500"
                                onkeyup="filterWhatsAppPaymentOptions()">
                        </div>
                        <div id="whatsappPaymentOptions" class="overflow-y-auto max-h-56">
                            @foreach($payments as $payment)
                            <div class="whatsapp-payment-option px-4 py-3 hover:bg-green-50 cursor-pointer border-b border-gray-100"
                                data-id="{{ $payment->id }}"
                                data-voucher="{{ strtolower($payment->voucher_number) }}"
                                data-client="{{ strtolower($payment->client->full_name ?? '') }}"
                                data-phone="{{ $payment->client->phone ?? '' }}"
                                data-country-code="{{ $payment->client->country_code ?? '+60' }}"
                                onclick="selectWhatsAppPayment('{{ $payment->id }}', '{{ $payment->voucher_number }}', '{{ $payment->client->phone ?? '' }}', '{{ $payment->client->country_code ?? '+60' }}')">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-medium text-gray-800">{{ $payment->voucher_number }}</p>
                                        <p class="text-sm text-gray-500">{{ $payment->client->full_name ?? 'N/A' }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-semibold text-green-600">{{ number_format($payment->amount, 2) }} {{ $payment->currency }}</p>
                                        <p class="text-xs text-gray-400">{{ $payment->created_at->format('d M Y') }}</p>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @error('payment_id')
                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                    Country Code
                </label>
                <input type="text" name="country_code" id="countryCode"
                    class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500"
                    placeholder="+60" value="+60">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                    Phone Number
                </label>
                <input type="text" name="phone" id="phoneNumber"
                    class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500"
                    placeholder="193058463">
            </div>
        </div>

        <div class="flex items-center gap-4">
            <button type="submit"
                class="px-6 py-2.5 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors flex items-center gap-2">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                </svg>
                Send PDF via WhatsApp
            </button>

            <button type="button" onclick="downloadPdf()"
                class="px-6 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition-colors flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Download PDF
            </button>
        </div>
    </form>
</div>

<script>
    function toggleWhatsAppPaymentDropdown() {
        const dropdown = document.getElementById('whatsappPaymentDropdown');
        dropdown.classList.toggle('hidden');
        if (!dropdown.classList.contains('hidden')) {
            document.getElementById('whatsappPaymentSearchInput').focus();
        }
    }

    function filterWhatsAppPaymentOptions() {
        const searchValue = document.getElementById('whatsappPaymentSearchInput').value.toLowerCase();
        const options = document.querySelectorAll('.whatsapp-payment-option');
        
        options.forEach(option => {
            const voucher = option.getAttribute('data-voucher');
            const client = option.getAttribute('data-client');
            if (voucher.includes(searchValue) || client.includes(searchValue)) {
                option.classList.remove('hidden');
            } else {
                option.classList.add('hidden');
            }
        });
    }

    function selectWhatsAppPayment(id, voucher, phone, countryCode) {
        document.getElementById('whatsappPaymentId').value = id;
        document.getElementById('whatsappPaymentSearchDisplay').value = voucher;
        document.getElementById('whatsappPaymentDropdown').classList.add('hidden');
        
        // Auto-fill phone number and country code
        if (phone) {
            document.getElementById('phoneNumber').value = phone;
        }
        if (countryCode) {
            document.getElementById('countryCode').value = countryCode;
        }
    }

    function downloadPdf() {
        const paymentId = document.getElementById('whatsappPaymentId').value;
        if (!paymentId) {
            alert('Please select a payment first');
            return;
        }
        window.open('{{ route("system-settings.download-pdf") }}?payment_id=' + paymentId, '_blank');
    }

    document.addEventListener('click', function(event) {
        const dropdown = document.getElementById('whatsappPaymentDropdown');
        const searchDisplay = document.getElementById('whatsappPaymentSearchDisplay');
        
        if (dropdown && searchDisplay && !dropdown.contains(event.target) && event.target !== searchDisplay) {
            dropdown.classList.add('hidden');
        }
    });
</script>
