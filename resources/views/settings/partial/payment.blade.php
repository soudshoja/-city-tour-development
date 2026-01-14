<div x-data="paymentTab()" x-init="init()">
    <div class="mb-6">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Payment Settings</h2>
        <p class="text-sm text-gray-500 mt-1">Configure default payment settings</p>
    </div>

    <div class="space-y-4">
        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
            <div>
                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Payment WhatsApp Notification</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Send WhatsApp notification to client upon successful payment</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" id="payment-whatsapp-notification" x-model="paymentWhatsappSetting" @change="updateSetting('payment_whatsapp_notification', $event.target.checked)" class="sr-only peer">
                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
            </label>
        </div>
    </div>
</div>
<script>
    function paymentTab() {
        return {
            paymentWhatsappSetting: false,
            loaded: false,

            init() {
                if (!this.loaded) {
                    this.getSettings();
                    this.loaded = true;
                }
            },

            async getSettings() {
                try {
                    const response = await fetch('{{ route("user-settings.get") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            keys: ['payment_whatsapp_notification']
                        })
                    });
                    const data = await response.json();

                    this.paymentWhatsappSetting = data.settings['payment_whatsapp_notification'] || false;
                } catch (error) {
                    console.error('Error fetching settings:', error);
                }
            },

            async updateSetting(key, value) {
                try {
                    const response = await fetch('{{ route("user-settings.update") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            key: key,
                            value: value
                        })
                    });
                    const data = await response.json();
                    console.log(data);
                } catch (error) {
                    console.error('Error updating setting:', error);
                }
            }
        }
    }
</script>