<div x-data="paymentMethodsTab()" x-init="init()">
    <div x-show="loading" class="flex justify-center items-center py-12">
        <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span class="ml-2 text-gray-600">Loading payment methods...</span>
    </div>

    <div x-show="!loading" x-cloak>
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">Payment Method Selection</h3>
                <p class="text-sm text-gray-500 mt-1">Choose which gateway to use for each payment method type</p>
            </div>
        </div>

        <!-- Payment Method Groups -->
        <form method="POST" action="{{ route('payment-method.set-group') }}" x-ref="paymentMethodForm">
            @csrf
            <div class="space-y-6">
                <template x-for="group in groups" :key="group.id">
                    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                        <!-- Group Header -->
                        <div class="flex justify-between items-center px-4 py-3 bg-gray-50 border-b border-gray-200">
                            <h4 class="font-semibold text-gray-800" x-text="group.name"></h4>
                            <template x-if="selectedMethods[group.id]">
                                @can('managePaymentMethodGroup', 'App\Models\PaymentMethod')
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox"
                                        :data-choice-id="choiceIds[group.id]"
                                        :data-group-id="group.id"
                                        class="sr-only peer"
                                        :checked="enabledGroups[group.id]"
                                        @change="toggleGroup($event, group.id)">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                    <span class="ml-3 text-sm font-medium text-gray-700">Enabled</span>
                                </label>
                                @else
                                <span :class="enabledGroups[group.id] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'" class="inline-flex px-2 py-1 text-xs font-medium rounded-full" x-text="enabledGroups[group.id] ? 'Enabled' : 'Disabled'"></span>
                                @endcan
                            </template>
                            <template x-if="!selectedMethods[group.id]">
                                <span class="text-xs text-gray-500">Select a method to enable</span>
                            </template>
                        </div>

                        <!-- Group Options -->
                        <div class="p-4 space-y-2">
                            <template x-for="(methods, gatewayName) in getMethodsByGateway(group)" :key="gatewayName">
                                <label class="flex items-center gap-3 p-3 border rounded-lg transition-colors @can('managePaymentMethodGroup', 'App\Models\PaymentMethod') cursor-pointer hover:bg-gray-50 @else cursor-default @endcan"
                                    :class="{'bg-gray-100 cursor-not-allowed opacity-60': !methods[0].is_active, 'border-blue-500 bg-blue-50': selectedMethods[group.id] == methods[0].id}">
                                    <input
                                        type="radio"
                                        :name="'payment_method_group_' + group.id"
                                        :value="methods[0].id"
                                        :checked="selectedMethods[group.id] == methods[0].id"
                                        :disabled="!methods[0].is_active @cannot('managePaymentMethodGroup', 'App\Models\PaymentMethod') @endcannot"
                                        @change="selectedMethods[group.id] = methods[0].id"
                                        class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                                    <span class="flex-1 font-medium text-gray-700" x-text="gatewayName"></span>
                                    <template x-if="!methods[0].is_active">
                                        <span class="text-xs text-red-600">(Inactive - Activate first)</span>
                                    </template>
                                </label>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <template x-if="groups.length === 0">
                <div class="bg-white border border-gray-200 rounded-lg p-8 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                    </svg>
                    <p class="mt-2 text-gray-500">No payment method groups available</p>
                    <p class="text-sm text-gray-400">Configure payment gateways first in the Payment Gateways tab</p>
                </div>
            </template>

            @can('managePaymentMethodGroup', 'App\Models\PaymentMethod')
            <template x-if="groups.length > 0">
                <div class="mt-6">
                    <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Save Selection
                    </button>
                </div>
            </template>
            @endcan
        </form>
    </div>
</div>

<script>
    function paymentMethodsTab() {
        return {
            groups: [],
            loading: false,
            selectedMethods: {},
            enabledGroups: {},
            choiceIds: {},
            companyId: "{{ $companyId }}",

            init() {
               window.addEventListener('payment-methods-tab-loaded', () => {
                   this.loadPaymentMethods();
               });
            },

            async loadPaymentMethods() {
                if (this.groups.length > 0) return;

                this.loading = true;

                let url = '{{ route("settings.payment-methods") }}';
                if (this.companyId) {
                    url += '?company_id=' + this.companyId;
                }
                
                try {
                    const response = await fetch(url, {
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                    const data = await response.json();
                    if (data.success) {
                        this.groups = data.paymentMethodGroups;
                        this.selectedMethods = data.selectedMethods;
                        this.enabledGroups = data.enabledGroups;
                        this.choiceIds = data.choiceIds;
                    }
                } catch (error) {
                    console.error('Error loading payment methods:', error);
                } finally {
                    this.loading = false;
                }
            },

            getMethodsByGateway(group) {
                const methods = group.payment_methods || [];
                const grouped = {};
                methods.forEach(method => {
                    const gatewayName = method.charge ? method.charge.name : 'Unknown';
                    if (!grouped[gatewayName]) {
                        grouped[gatewayName] = [];
                    }
                    grouped[gatewayName].push(method);
                });
                return grouped;
            },

            async toggleGroup(event, groupId) {
                const choiceId = this.choiceIds[groupId];
                if (!choiceId) return;

                const url = '{{ route("payment-method.toggle-enable", ["id" => "CHOICE_ID"]) }}'.replace('CHOICE_ID', choiceId);
                
                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                    const data = await response.json();
                    if (data.success) {
                        this.enabledGroups[groupId] = data.is_enabled;
                    } else {
                        event.target.checked = !event.target.checked;
                    }
                } catch (error) {
                    console.error('Error:', error);
                    event.target.checked = !event.target.checked;
                }
            }
        }
    }
</script>
