<x-app-layout>
    <div x-data="{ createModal: false }" class="container mx-auto px-6 py-4">

        {{-- Page Header --}}
        <div class="flex justify-between items-center my-4">
            <div class="flex items-center gap-4">
                <h2 class="text-3xl font-bold">Auto Billing Settings</h2>
                <div class="w-10 h-10 flex items-center justify-center bg-violet-600 text-white rounded-full shadow-sm">
                    <span class="font-semibold text-lg">{{ $rules->count() }}</span>
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

        {{-- Company Timezone --}}
        <p class="text-sm mb-6 text-gray-600">
            Company Timezone:
            <strong>{{ $company->country->timezone ?? 'Asia/Kuala_Lumpur' }}</strong>
        </p>

        {{-- Table --}}
        <div class="panel bg-white rounded-lg shadow p-4">
            <table class="table-auto w-full border-collapse">
                <thead>
                    <tr class="bg-gray-100 text-gray-700 text-sm">
                        <th class="px-3 py-2 text-left">Created By</th>
                        <th class="px-3 py-2 text-left">Agents</th>
                        <th class="px-3 py-2 text-left">Issued By</th>
                        <th class="px-3 py-2 text-left">Client</th>
                        <th class="px-3 py-2 text-left">Amount</th>
                        <th class="px-3 py-2 text-left">Time</th>
                        <th class="px-3 py-2 text-left">Auto send WhatsApp</th>
                        <th class="px-3 py-2 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rules as $rule)
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-3 py-2 text-sm text-gray-700">{{ implode(', ', $rule->created_by_list ?? []) }}</td>
                        <td class="px-3 py-2 text-sm text-gray-700">
                            @php
                            $agentNames = \App\Models\Agent::whereIn('id', $rule->agent_ids ?? [])->pluck('name')->toArray();
                            @endphp
                            {{ implode(', ', $agentNames) }}
                        </td>
                        <td class="px-3 py-2 text-sm text-gray-700">{{ implode(', ', $rule->issued_by_list ?? []) }}</td>
                        <td class="px-3 py-2 text-sm text-gray-700">{{ $rule->client->full_name ?? '-' }}</td>
                        <td class="px-3 py-2 text-sm">{{ number_format($rule->add_amount, 2) }}</td>
                        <td class="px-3 py-2 text-sm">{{ $rule->invoice_time_company }}</td>
                        <td class="px-3 py-2 text-center">
                            @if($rule->auto_send_whatsapp)
                            <span class="inline-flex px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Yes</span>
                            @else
                            <span class="inline-flex px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">No</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 flex gap-2">
                            <a href="{{ route('auto-billing.edit', $rule->id) }}" class="text-blue-600 hover:text-blue-800 text-sm">Edit</a>
                            <form action="{{ route('auto-billing.destroy', $rule->id) }}" method="POST" onsubmit="return confirm('Delete this rule?')">
                                @csrf @method('DELETE')
                                <button class="text-red-600 hover:text-red-800 text-sm">Delete</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-gray-400 py-3">No auto-billing rules configured yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div x-cloak x-show="createModal" x-transition
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-800 bg-opacity-40 backdrop-blur-sm">
            <div class="bg-white rounded-lg shadow-lg w-full max-w-3xl p-6 relative" @click.away="createModal=false">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Create Auto Billing Rule</h3>
                    <button @click="createModal=false" class="text-gray-400 hover:text-red-500 text-2xl leading-none">&times;</button>
                </div>

                <form action="{{ route('auto-billing.store') }}" method="POST" class="space-y-4">
                    @csrf

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Created By (GDS Office ID)</label>
                            <select name="created_by_list[]" class="select2-tags w-full" multiple></select>
                            <small class="text-gray-500 text-xs">Type and press Enter (e.g. KWIKT211N)</small>
                        </div>
                        <x-multi-picker
                            label="Agents"
                            name="agent_ids"
                            :items="$agents->map(fn($a) => ['id' => $a->id, 'name' => $a->name])->values()"
                            :preselected="collect(old('agent_ids', $selectedAgentIds ?? []))->map(fn($v)=>(int)$v)->all()"
                            allLabel="All agents"
                            placeholder="Select agents"
                        />
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Issued By (GDS Office ID)</label>
                            <select name="issued_by_list[]" class="select2-tags w-full" multiple></select>
                            <small class="text-gray-500 text-xs">Type and press Enter (e.g. KWIKT2843)</small>
                        </div>
                        <x-searchable-dropdown
                            label="Client"
                            name="client_id"
                            :items="$clients->map(fn($c) => [
                                'id' => $c->id,
                                'name' => $c->full_name . ' - ' . $c->phone
                            ])"
                            placeholder="Select Client"
                        />
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Add Amount</label>
                            <input type="number" step="0.01" name="add_amount" value="1" class="border rounded-md px-3 py-2 w-full">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Invoice Time</label>
                            <input type="time" name="invoice_time_company" class="border rounded-md px-3 py-2 w-full" required>
                        </div>
                        <div class="flex items-center mt-5">
                            <input type="checkbox" name="auto_send_whatsapp" id="auto_send" value="1"
                                class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <label for="auto_send" class="ml-2 text-sm text-gray-700">Auto WhatsApp</label>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" @click="createModal=false" class="px-4 py-2 bg-gray-300 rounded-md">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-violet-600 text-white rounded-md hover:bg-violet-700">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        $(document).ready(function() {
            $('.select2').select2({
                width: '100%',
                placeholder: 'Select value',
                allowClear: true
            });
            $('.select2-tags').select2({
                tags: true,
                width: '100%',
                tokenSeparators: [',', ' ']
            });
        });
    </script>
    @endpush
</x-app-layout>