<x-app-layout>
    <div class="flex items-center gap-3 my-3">
        <a href="{{ route('suppliers.index') }}" class="text-gray-400 hover:text-gray-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        </a>
        <h2 class="text-2xl font-bold">Charge rules — company defaults</h2>
    </div>
    <p class="text-sm text-gray-500 mb-4">Rows with no specific supplier (supplier_id null) -- apply to every supplier on a matching service_type/channel until a supplier-specific rule exists.</p>

    <div x-data="{ editingId: null, adding: false }" class="bg-white rounded-lg shadow-md p-6">
        @if ($rows->isEmpty())
            <div class="text-sm text-gray-500 italic mb-3">No company-wide default rules yet.</div>
        @else
            <div class="overflow-x-auto border border-gray-200 rounded-lg mb-3">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Kind</th>
                            <th class="text-left px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Service</th>
                            <th class="text-left px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Channel</th>
                            <th class="text-left px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Basis</th>
                            <th class="text-right px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Amount</th>
                            <th class="text-left px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Recharge</th>
                            <th class="text-center px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Active</th>
                            <th class="text-right px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($rows as $rule)
                            <tr class="hover:bg-blue-50/50">
                                <td class="px-3 py-2">{{ $rule->charge_kind }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $rule->service_type ?? 'any' }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $rule->channel ?? 'any' }}</td>
                                <td class="px-3 py-2">{{ $rule->basis }}</td>
                                <td class="px-3 py-2 text-right font-mono">{{ number_format($rule->amount, 3) }}</td>
                                <td class="px-3 py-2">{{ $rule->recharge_policy }}</td>
                                <td class="px-3 py-2 text-center">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $rule->active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-500' }}">{{ $rule->active ? 'Active' : 'Inactive' }}</span>
                                </td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                    <button type="button" @click="editingId = editingId === {{ $rule->id }} ? null : {{ $rule->id }}" class="text-blue-600 hover:text-blue-800 text-xs font-semibold mr-2">Edit</button>
                                    <form action="{{ route('suppliers.charge-rules.deactivate', $rule->id) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold {{ $rule->active ? 'text-red-600 hover:text-red-800' : 'text-emerald-600 hover:text-emerald-800' }}">{{ $rule->active ? 'Deactivate' : 'Reactivate' }}</button>
                                    </form>
                                </td>
                            </tr>
                            <tr x-show="editingId === {{ $rule->id }}" x-cloak>
                                <td colspan="8" class="px-3 py-3 bg-gray-50">
                                    @include('suppliers.partials.charge-rule-form', ['action' => route('suppliers.charge-rules.update', $rule->id), 'method' => 'PUT', 'rule' => $rule])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <button type="button" @click="adding = !adding" class="bg-blue-100 text-blue-700 hover:bg-blue-200 font-medium text-xs px-3 py-1.5 rounded-lg transition">+ Add company-wide rule</button>
        <div x-show="adding" x-cloak class="mt-2 bg-gray-50 rounded-lg p-3">
            {{-- No supplier in the URL on this page -- posts to a dedicated create-default action
                 (SupplierController::chargeRuleStoreDefault()) that always writes supplier_id=null,
                 rather than reusing chargeRuleStore()'s {supplier} route-bound action. --}}
            <form action="{{ route('suppliers.charge-rules.create-default') }}" method="POST" class="grid grid-cols-2 md:grid-cols-4 gap-2 items-end">
                @csrf
                <input type="text" name="service_type" placeholder="any" class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
                <input type="text" name="channel" placeholder="any" class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
                <select name="charge_kind" class="w-full border border-gray-300 rounded px-2 py-1 text-sm" required>
                    @foreach (['iata_fee','rounding','service_fee','booking_fee','card_surcharge','resort_fee','other'] as $k)
                        <option value="{{ $k }}">{{ $k }}</option>
                    @endforeach
                </select>
                <select name="basis" class="w-full border border-gray-300 rounded px-2 py-1 text-sm" required>
                    @foreach (['fixed','percent_of_fare','percent_of_total','per_passenger','per_segment'] as $b)
                        <option value="{{ $b }}">{{ $b }}</option>
                    @endforeach
                </select>
                <input type="number" step="0.001" min="0" name="amount" placeholder="Amount" class="w-full border border-gray-300 rounded px-2 py-1 text-sm" required>
                <select name="recharge_policy" class="w-full border border-gray-300 rounded px-2 py-1 text-sm" required>
                    @foreach (['absorb','recharge_client','recharge_agent'] as $p)
                        <option value="{{ $p }}">{{ $p }}</option>
                    @endforeach
                </select>
                <label class="text-xs text-gray-500 flex items-center gap-1"><input type="checkbox" name="commissionable" value="1"> Commissionable</label>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-3 py-1.5 rounded">Add</button>
            </form>
        </div>
    </div>
</x-app-layout>
