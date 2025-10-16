<x-app-layout>
    <div x-data="{ createModal: false }">
        <div class="flex justify-between items-center my-4">
            <div class="flex items-center gap-5">
                <h2 class="text-3xl font-bold">Auto Billing Settings</h2>
                <div data-tooltip="Number of Rules"
                    class="relative w-12 h-12 flex items-center justify-center DarkBGcolor rounded-full shadow-sm">
                    <span class="text-xl font-bold text-white">{{ $rules->count() }}</span>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <button onclick="location.reload()" data-tooltip-left="Reload"
                    class="rotate refresh-icon relative w-12 h-12 flex items-center justify-center bg-[#b1c0db] hover:bg-gray-300 rounded-full shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="currentColor"
                            d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.66 7.66 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.16 9.16 0 0 0 12.08 2.25" />
                        <path fill="currentColor"
                            d="M20.841 10.467a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.381 3.83-3.651 6.833-7.644 6.833a7.7 7.7 0 0 1-6.565-3.644a.75.75 0 1 0-1.276.788a9.2 9.2 0 0 0 7.84 4.356c4.809 0 8.766-3.66 9.151-8.333H22a.75.75 0 0 0 .527-1.284z"
                            opacity=".5" />
                    </svg>
                </button>
                <button @click="createModal = true"
                    class="relative w-12 h-12 flex items-center justify-center btn-success rounded-full shadow-sm cursor-pointer"
                    data-tooltip-left="Add new rule">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#fff"
                            d="M16 8h-2v3h-3v2h3v3h2v-3h3v-2h-3M2 12c0-2.79 1.64-5.2 4-6.32V3.5C2.5 4.76 0 8.09 0 12s2.5 7.24 6 8.5v-2.18C3.64 17.2 2 14.79 2 12m13-9c-4.96 0-9 4.04-9 9s4.04 9 9 9s9-4.04 9-9s-4.04-9-9-9m0 16c-3.86 0-7-3.14-7-7s3.14-7 7-7s7 3.14 7 7s-3.14 7-7 7">
                        </path>
                    </svg>
                </button>
            </div>
        </div>
        <div class="panel bg-white rounded-lg shadow p-4">
            <table class="table-auto w-full border-collapse">
                <thead>
                    <tr class="bg-gray-100 text-gray-700">
                        <th class="p-3 text-center text-base font-bold text-gray-500">Created By</th>
                        <th class="p-3 text-center text-base font-bold text-gray-500">Agent</th>
                        <th class="p-3 text-center text-base font-bold text-gray-500">Issued By</th>
                        <th class="p-3 text-center text-base font-bold text-gray-500">Client</th>
                        <th class="p-3 text-center text-base font-bold text-gray-500">Invoice Surcharge</th>
                        <th class="p-3 text-center text-base font-bold text-gray-500">Payment Type</th>
                        <th class="p-3 text-center text-base font-bold text-gray-500">Invoice Time</th>
                        <th class="p-3 text-center text-base font-bold text-gray-500">WhatsApp Auto-Send</th>
                        <th class="p-3 text-center text-base font-bold text-gray-500">Status</th>
                        <th class="p-3 text-center text-base font-bold text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rules as $rule)
                    <tr class="border-b hover:bg-gray-50 text-center text-gray-700">
                        <td class="px-3 py-2 text-sm font-medium">{{ $rule->created_by ?? '-' }}</td>
                        <td class="px-3 py-2 text-sm font-medium">{{ $rule->agent->name ?? '-' }}</td>
                        <td class="px-3 py-2 text-sm font-medium">{{ $rule->issued_by ?? '-' }}</td>
                        <td class="px-3 py-2 text-sm font-semibold">{{ $rule->client->full_name ?? '-' }}</td>
                        <td class="px-3 py-2 text-sm">{{ number_format($rule->add_amount, 2) }}</td>
                        <td class="px-3 py-2 text-sm">
                            {{ $rule->gateway?->name ?? '-' }}
                            @if($rule->method)
                                - {{ $rule->method->english_name }}
                            @endif
                        </td>
                        <td class="px-3 py-2 text-sm">
                            {{ \Carbon\Carbon::parse($rule->invoice_time_company)->format('H:i A') }}
                        </td>
                        <td class="px-3 py-2">
                            @if($rule->auto_send_whatsapp)
                                <span class="inline-flex px-2.5 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Yes</span>
                            @else
                                <span class="inline-flex px-2.5 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">No</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-sm">
                            @if($rule->is_active)
                                <span class="inline-flex px-2.5 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Active</span>
                            @else
                                <span class="inline-flex px-2.5 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">Inactive</span>
                            @endif
                        </td>
                        <td class="text-sm flex items-center gap-3">
                            <div x-data="{ 
                                    editModal: false, 
                                    currentRule: {}, 
                                    openEdit(rule) {
                                        this.currentRule = rule;
                                        this.editModal = true;
                                    }
                                }">
                                <button type="button" @click='openEdit(@json($rule))'
                                    class="inline-flex items-center gap-2 px-2 py-1 rounded-full bg-blue-50 text-blue-600 border border-blue-200 hover:bg-blue-100 hover:text-blue-700 transition duration-200 ease-in-out shadow-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15.232 5.232l3.536 3.536M9 11l6.768-6.768a2.5 2.5 0 113.536 3.536L12.536 14.5H9v-3.5z" />
                                    </svg>
                                    <span>Edit</span>
                                </button>
                                <template x-teleport="body">
                                    <div x-cloak x-show="editModal" class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-40 backdrop-blur-sm transition">
                                        <form :action="'{{ route('auto-billing.update', '') }}/' + currentRule.id" method="POST"
                                            class="inline-flex flex-col gap-4 items-center w-full">
                                            @csrf
                                            @method('PUT')
                                            <div @click.away="editModal = false"
                                                class="w-full sm:max-w-screen-sm mx-4 bg-white rounded-md border p-6 relative overflow-y-auto max-h-[90vh]">
                                                <div class="flex items-start justify-between mb-2">
                                                    <div>
                                                        <h2 class="text-xl font-bold text-gray-800">Edit Auto Billing Rule</h2>
                                                        <p class="text-gray-600 italic text-xs mt-1">
                                                            Update the fields below to modify this auto billing rule.
                                                        </p>
                                                    </div>
                                                    <button type="button" @click="editModal = false"
                                                        class="absolute top-2 right-2 p-2 text-gray-400 hover:text-red-500 text-2xl">
                                                        &times;
                                                    </button>
                                                </div>
                                                <div class="flex flex-col gap-6">
                                                    <div class="flex flex-col sm:flex-row gap-4">
                                                        <div class="flex-1">
                                                            <label class="block text-sm font-medium text-gray-700">Created By</label>
                                                            <input type="text" name="created_by" x-model="currentRule.created_by"
                                                                class="border border-gray-300 p-2 rounded-md w-full text-base"
                                                                placeholder="Enter Created By">
                                                        </div>
                                                        <div class="flex-1">
                                                            <label class="block text-sm font-medium text-gray-700">Agent</label>
                                                            <x-searchable-dropdown
                                                                name="agent_id"
                                                                :items="$agents->map(fn($a) => ['id' => $a->id, 'name' => $a->name])->values()"
                                                                :selectedId="null"
                                                                placeholder="Select Agent"
                                                                x-bind:selected-id="currentRule.agent_id"
                                                                :selectedId="$rule->agent->id ?? null"
                                                                :selectedName="$rule->agent->name ?? null" />
                                                        </div>
                                                    </div>
                                                    <div class="flex flex-col sm:flex-row gap-4">
                                                        <div class="flex-1">
                                                            <label class="block text-sm font-medium text-gray-700">Issued By</label>
                                                            <input type="text" name="issued_by" x-model="currentRule.issued_by"
                                                                class="border border-gray-300 p-2 rounded-md w-full text-base"
                                                                placeholder="Enter Issued By">
                                                        </div>
                                                        <div class="flex-1 min-w-0">
                                                            <label class="block text-sm font-medium text-gray-700">
                                                                Client <span class="text-red-500">*</span>
                                                            </label>
                                                            <x-searchable-dropdown
                                                                name="client_id"
                                                                :items="$clients->map(fn($c) => [
                                                                    'id' => $c->id,
                                                                    'name' => $c->full_name . ' - ' . $c->phone
                                                                ])->values()"
                                                                placeholder="Select Client"
                                                                :selectedId="$rule->client->id ?? null"
                                                                :selectedName="$rule->client->full_name . ' - ' . $rule->client->phone ?? null" />
                                                        </div>
                                                    </div>
                                                    <div class="flex flex-col sm:flex-row gap-4">
                                                        <div class="flex-1">
                                                            <label class="block text-sm font-medium text-gray-700">Payment Gateway</label>
                                                            <select name="gateway_id" id="gateway-select-edit"
                                                                class="border border-gray-300 p-2 rounded-md w-full text-base">
                                                                <template x-for="gateway in {{ $paymentGateways->toJson() }}" :key="gateway.id">
                                                                    <option :value="gateway.id" x-text="gateway.name"
                                                                        :selected="gateway.id == currentRule.gateway_id"></option>
                                                                </template>
                                                            </select>
                                                        </div>
                                                        <div class="flex-1">
                                                            <label class="block text-sm font-medium text-gray-700">Payment Method</label>
                                                            <select name="method_id" id="method-select-edit"
                                                                class="border border-gray-300 p-2 rounded-md w-full text-base">
                                                                <template x-for="method in {{ $paymentMethods->toJson() }}" :key="method.id">
                                                                    <option :value="method.id" x-text="method.english_name"
                                                                        :selected="method.id == currentRule.method_id"></option>
                                                                </template>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="flex flex-col sm:flex-row gap-4">
                                                        <div class="flex-1">
                                                            <label class="block text-sm font-medium text-gray-700">Invoice Surcharge</label>
                                                            <input type="number" min="1" name="add_amount"
                                                                x-model="currentRule.add_amount"
                                                                class="border border-gray-300 p-2 rounded-md w-full text-base">
                                                        </div>
                                                        <div class="flex-1">
                                                            <label class="block text-sm font-medium text-gray-700">Invoice Time</label>
                                                            <input type="time" name="invoice_time_company"
                                                                x-model="currentRule.invoice_time_company"
                                                                class="border border-gray-300 p-2 rounded-md w-full text-base">
                                                        </div>
                                                    </div>
                                                    <div class="flex items-center justify-start gap-10 mt-4">
                                                        <label class="relative inline-flex items-center cursor-pointer">
                                                            <input type="checkbox" name="auto_send_whatsapp" value="1" x-bind:checked="currentRule.auto_send_whatsapp" class="sr-only peer">
                                                            <div class="w-10 h-5 bg-gray-200 rounded-full peer peer-checked:bg-blue-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                                                                after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-5"></div>
                                                            <span class="ml-3 text-sm font-medium text-gray-700">Auto share invoice to WhatsApp</span>
                                                        </label>
                                                        <label class="relative inline-flex items-center cursor-pointer">
                                                            <input type="checkbox" name="is_active" value="1" x-bind:checked="currentRule.is_active" class="sr-only peer">
                                                            <div class="w-10 h-5 bg-gray-200 rounded-full peer peer-checked:bg-green-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                                                                after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-5"></div>
                                                            <span class="ml-3 text-sm font-medium text-gray-700">Rule Active</span>
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="mt-6 flex flex-col sm:flex-row justify-between gap-4">
                                                    <button type="button" @click="editModal = false" class="px-6 py-2 text-gray-700 font-semibold rounded-full bg-gray-200 hover:bg-gray-300 transition">
                                                        Cancel
                                                    </button>
                                                    <button type="submit" class="w-full sm:w-auto px-6 py-2 text-white font-semibold rounded-full bg-blue-600 hover:bg-blue-700 transition">
                                                        Update
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </template>
                            </div>
                            <div x-data="{ showConfirm: false }">
                                <button type="button" @click="showConfirm = true"
                                    class="inline-flex items-center gap-2 px-2 py-1 rounded-full bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 hover:text-red-700 transition duration-200 ease-in-out shadow-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7h6m2 0a2 2 0 00-2-2H9a2 2 0 00-2 2m12 0H3" />
                                    </svg>
                                    <span>Delete</span>
                                </button>
                                <template x-teleport="body">
                                    <div x-show="showConfirm" x-cloak x-transition.opacity
                                        class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-40 backdrop-blur-sm">
                                        <div x-transition.scale @click.away="showConfirm = false" class="bg-white rounded-xl shadow-xl px-8 py-8 w-full max-w-sm text-center">
                                            <h2 class="text-xl font-semibold text-gray-900 mb-4">Delete Auto Billing Setting?</h2>
                                            <div class="border-t border-gray-200 mb-6"></div>
                                            <p class="text-base font-medium text-gray-600 leading-snug mb-6">
                                                Are you sure you want to delete this auto billing setting?
                                                <br>
                                                <span class="block text-sm text-gray-500 leading-snug mt-1">
                                                    This will stop automatic invoice generation for the selected client.
                                                </span>
                                            </p>
                                            <div class="border-t border-gray-200 mb-6"></div>
                                            <div class="flex justify-center gap-4">
                                                <button @click="showConfirm = false" class="px-6 py-2.5 rounded-full font-medium text-gray-700 bg-gray-200 hover:bg-gray-300 transition">
                                                    Cancel
                                                </button>
                                                <form action="{{ route('auto-billing.destroy', $rule->id) }}" method="POST">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="px-6 py-2.5 rounded-full font-medium text-white bg-red-600 hover:bg-red-700 transition">
                                                        Yes, Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="text-center text-gray-400 py-3">No auto-billing rules configured yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <template x-teleport="body">
            <div x-cloak x-show="createModal" class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-40 backdrop-blur-sm transition">
                <form action="{{ route('auto-billing.store') }}" method="POST"
                    class="inline-flex flex-col gap-4 items-center w-full">
                    @csrf
                    <div @click.away="createModal=false"
                        class="w-full sm:max-w-screen-sm mx-4 bg-white rounded-md border p-6 relative overflow-y-auto max-h-[90vh]">
                        <div class="flex items-start justify-between mb-2">
                            <div>
                                <h2 class="text-xl font-bold text-gray-800">Create Auto Billing Rule</h2>
                                <p class="text-gray-600 italic text-xs mt-1">
                                    Please complete the fields below to enable automatic billing for a client.
                                </p>
                            </div>
                            <button type="button" @click="createModal=false"
                                    class="absolute top-2 right-2 p-2 text-gray-400 hover:text-red-500 text-2xl">
                                &times;
                            </button>
                        </div>
                        <div class="flex flex-col gap-6">
                            <div class="flex flex-col sm:flex-row gap-4">
                                <div class="flex-1 min-w-0">
                                    <label class="block text-sm font-medium text-gray-700">Created By</label>
                                    <input type="text" name="created_by" class="border border-gray-300 p-2 rounded-md w-full text-base" placeholder="Enter Creted By">
                                    <p class="text-xs text-gray-500 mt-1">The office ID that created the booking</p>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <label class="block text-sm font-medium text-gray-700">Agent</label>
                                    <x-searchable-dropdown
                                        name="agent_id"
                                        :items="$agents->map(fn($a) => ['id' => $a->id, 'name' => $a->name])->values()"
                                        :selectedId="old('agent_id', $selectedAgentId ?? null)"
                                        placeholder="Select Agent" />
                                    <p class="text-xs text-gray-500 mt-1">Only one agent can be assigned per client</p>
                                </div>
                            </div>
                            <div class="flex flex-col sm:flex-row gap-4">
                                <div class="flex-1 min-w-0">
                                    <label class="block text-sm font-medium text-gray-700">Issued By</label>
                                    <input type="text" name="issued_by" class="border border-gray-300 p-2 rounded-md w-full text-base" placeholder="Enter Issued By">
                                    <p class="text-xs text-gray-500 mt-1">The office ID that issued the task</p>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <label class="block text-sm font-medium text-gray-700">
                                        Client <span class="text-red-500">*</span>
                                    </label>
                                    <x-searchable-dropdown
                                        name="client_id"
                                        :items="$clients->map(fn($c) => [
                                            'id' => $c->id,
                                            'name' => $c->full_name . ' - ' . $c->phone
                                        ])->values()"
                                        placeholder="Select Client" />
                                    <p class="text-xs text-gray-500 mt-1">Choose the specific client for this rule</p>
                                </div>
                            </div>
                            <div class="flex flex-col sm:flex-row gap-4">
                                <div class="flex-1 min-w-0">
                                    <label class="block text-sm font-medium text-gray-700">
                                        Payment Gateway <span class="text-red-500">*</span>
                                    </label>
                                    <select name="gateway_id" id="gateway-select-create"
                                        class="border border-gray-300 p-2 rounded-md w-full text-base" required>
                                        <option value="" disabled selected>Select Gateway</option>
                                        @foreach ($paymentGateways as $gateway)
                                            <option value="{{ $gateway->id }}">{{ $gateway->name }}</option>
                                        @endforeach
                                    </select>
                                    <p class="text-xs text-gray-500 mt-1">Select which payment gateway to use.</p>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <label class="block text-sm font-medium text-gray-700">
                                        Payment Method
                                    </label>
                                    <select name="method_id" id="method-select-create"
                                        class="border border-gray-300 p-2 rounded-md w-full text-base" disabled>
                                        <option value="">Select Method</option>
                                    </select>
                                    <p class="text-xs text-gray-500 mt-1">Shown only if the gateway supports multiple methods</p>
                                </div>
                            </div>
                            <div class="flex flex-col sm:flex-row gap-4">
                                <div class="flex-1 min-w-0">
                                    <label class="block text-sm font-medium text-gray-700">
                                        Invoice Surcharge <span class="text-red-500">*</span>
                                    </label>
                                    <input type="number" min=1 name="add_amount" value="1" class="border border-gray-300 p-2 rounded-md w-full text-base">
                                    <p class="text-xs text-gray-500 mt-1">Additional surcharge to add to the invoice total</p>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <label class="block text-sm font-medium text-gray-700">
                                        Invoice Time <span class="text-red-500">*</span>
                                    </label>
                                    <input type="time" name="invoice_time_company" class="border border-gray-300 p-2 rounded-md w-full text-base" required>
                                    <p class="text-xs text-gray-500 mt-1">
                                        Company timezone:
                                        <strong>{{ $companyTimezone }}</strong><br>
                                        Current company time:
                                        <span id="company-current-time" class="font-semibold text-gray-700"></span>
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center justify-star">
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="auto_send_whatsapp" value="1" id="auto_send" class="sr-only peer">
                                    <div class="w-10 h-5 bg-gray-200 rounded-full peer peer-checked:bg-blue-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                                        after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-5"></div>
                                    <span class="ml-3 text-sm font-medium text-gray-700">Auto share invoice to WhatsApp</span>
                                </label>
                            </div>
                        </div>
                        <div class="mt-6 flex flex-col sm:flex-row justify-between gap-4">
                            <button type="button" @click="createModal=false"
                                class="px-6 py-2 text-gray-700 font-semibold rounded-full bg-gray-200 hover:bg-gray-300 transition">
                                Cancel
                            </button>
                            <button type="submit"
                                class="w-full sm:w-auto px-6 py-2 text-white font-semibold rounded-full bg-blue-600 hover:bg-blue-700 transition">
                                Save
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </template>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const companyTz = @json(trim($companyTimezone));
            document.addEventListener('click', function (e) {
                if (e.target.closest('[x-show="createModal"]') || e.target.closest('[data-tooltip-left="Add new rule"]')) {
                    setTimeout(initCompanyClock, 200);
                }
            });
            function initCompanyClock() {
                const timeElem = document.getElementById('company-current-time');
                if (!timeElem) return;

                function updateCompanyTime() {
                    try {
                        const now = new Date();
                        const options = {
                            timeZone: companyTz,
                            hour: '2-digit',
                            minute: '2-digit',
                            second: '2-digit',
                            hour12: true
                        };
                        timeElem.textContent = new Intl.DateTimeFormat('en-US', options).format(now);
                    } catch (err) {
                        console.error('Timezone error:', err);
                        timeElem.textContent = 'Unavailable';
                    }
                }

                updateCompanyTime();
                setInterval(updateCompanyTime, 1000);
            }

            const form = document.querySelector('form[action="{{ route('auto-billing.store') }}"]');
            if (form) {
                form.addEventListener('submit', function (e) {
                    const createdBy = form.querySelector('[name="created_by"]').value.trim();
                    const issuedBy = form.querySelector('[name="issued_by"]').value.trim();
                    const agentId = form.querySelector('[name="agent_id"]').value.trim();
                    const clientId = form.querySelector('[name="client_id"]').value.trim();
                    const addAmount = parseFloat(form.querySelector('[name="add_amount"]').value);
                    const invoiceTime = form.querySelector('[name="invoice_time_company"]').value.trim();

                    let errors = [];

                    if (!invoiceTime) errors.push("Invoice time is required.");
                    if (isNaN(addAmount) || addAmount < 1) errors.push("Surcharge must be at least 1.");
                    if (!clientId) errors.push("Client is required.");

                    if (!createdBy && !issuedBy && !agentId) {
                        errors.push("At least one of 'Created By', 'Issued By', or 'Agent' must be filled.");
                    }

                    if (errors.length) {
                        e.preventDefault();
                        alert(errors.join("\n"));
                    }
                });
            }

            document.addEventListener('alpine:init', () => {
                document.addEventListener('click', (e) => {
                    if (e.target.closest('[x-show="createModal"]')) {
                        initGatewayMethodLogic();
                    } else if (e.target.closest('[x-show="editModal"]')) {
                        initUpdateGatewayMethodLogic();
                    }
                });
            });

            const gateways = @json($paymentGateways);
            const paymentMethods = @json($paymentMethods);

            const methodsByGateway = paymentMethods.reduce((acc, method) => {
                if (!method.gateway_id) return acc;
                (acc[method.gateway_id] ||= []).push(method);
                return acc;
            }, {});

            function initGatewayMethodLogic() {
                const gateways = @json($paymentGateways);
                const paymentMethods = @json($paymentMethods);

                function gwKey(s) {
                    return (s || '').toString().trim().toLowerCase().replace(/[\s_-]+/g, '');
                }

                const methodsByGateway = paymentMethods.reduce((acc, method) => {
                    const key = gwKey(method.type ?? method.gateway ?? '');
                    if (!key) return acc;
                    (acc[key] ||= []).push(method);
                    return acc;
                }, {});

                const gatewaySelect = document.getElementById('gateway-select-create');
                const methodSelect = document.getElementById('method-select-create');
                if (!gatewaySelect || !methodSelect) return;

                const methodLabel = document.querySelector('label[for="method-select-create"]') 
                    || methodSelect.closest('div').querySelector('label');

                let methodNote = methodSelect.nextElementSibling;
                if (!methodNote || !methodNote.classList.contains('text-xs')) {
                    methodNote = document.createElement('p');
                    methodNote.className = 'text-xs text-gray-500 mt-1';
                    methodSelect.insertAdjacentElement('afterend', methodNote);
                }

                function renderMethodOptions(selectEl, methods) {
                    selectEl.innerHTML = methods.map(m =>
                        `<option value="${m.id}">${m.english_name}</option>`
                    ).join('');
                }

                gatewaySelect.addEventListener('change', () => {
                    const selectedGateway = gateways.find(g => g.id == gatewaySelect.value);
                    const key = gwKey(selectedGateway?.name || selectedGateway?.type || '');
                    const methods = methodsByGateway[key] || [];

                    if (methodLabel) {
                        const existingStar = methodLabel.querySelector('.text-red-500');
                        if (existingStar) existingStar.remove();
                    }

                    if (methods.length > 0) {
                        renderMethodOptions(methodSelect, methods);
                        methodSelect.disabled = false;
                        methodSelect.classList.remove('bg-gray-100', 'text-gray-400');
                        methodNote.textContent = 'Select one of the supported methods for this gateway';

                        if (methodLabel) {
                            const star = document.createElement('span');
                            star.className = 'text-red-500';
                            star.textContent = ' *';
                            methodLabel.appendChild(star);
                        }
                    } else {
                        methodSelect.innerHTML = '<option value="">No specific method required</option>';
                        methodSelect.disabled = true;
                        methodSelect.classList.add('bg-gray-100', 'text-gray-400');
                        methodNote.textContent = 'This gateway does not require selecting a method';
                    }
                });
            }

            function initUpdateGatewayMethodLogic() {
                const gateways = @json($paymentGateways);
                const paymentMethods = @json($paymentMethods);

                function gwKey(s) {
                    return (s || '').toString().trim().toLowerCase().replace(/[\s_-]+/g, '');
                }

                const methodsByGateway = paymentMethods.reduce((acc, method) => {
                    const key = gwKey(method.type ?? method.gateway ?? '');
                    if (!key) return acc;
                    (acc[key] ||= []).push(method);
                    return acc;
                }, {});

                const gatewaySelect = document.getElementById('gateway-select-edit');
                const methodSelect = document.getElementById('method-select-edit');
                if (!gatewaySelect || !methodSelect) return;

                function renderMethodOptions(selectEl, methods) {
                    selectEl.innerHTML = methods.map(m =>
                        `<option value="${m.id}">${m.english_name}</option>`
                    ).join('');
                }

                function filterMethodsForSelectedGateway() {
                    const selectedGateway = gateways.find(g => g.id == gatewaySelect.value);
                    const key = gwKey(selectedGateway?.name || selectedGateway?.type || '');
                    const methods = methodsByGateway[key] || [];

                    if (methods.length > 0) {
                        renderMethodOptions(methodSelect, methods);
                        methodSelect.disabled = false;
                        methodSelect.classList.remove('bg-gray-100', 'text-gray-400');
                    } else {
                        methodSelect.innerHTML = '<option value="">No specific method required</option>';
                        methodSelect.disabled = true;
                        methodSelect.classList.add('bg-gray-100', 'text-gray-400');
                    }
                }

                filterMethodsForSelectedGateway();
                gatewaySelect.addEventListener('change', filterMethodsForSelectedGateway);
            }
        });
    </script>
</x-app-layout>