{{--
    W6.C.U "Supplier charge rule editor" (w6-brief.md "W6.U -- UI"). Replaces the old "Auto Extra
    Surcharge" card alongside the status-map card. Table of supplier_charge_rules for the current
    supplier+company, add/edit/deactivate row actions, and a "test a task" preview.
--}}
<div x-data="{ editingId: null, adding: false }" class="bg-white rounded-lg shadow-md p-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-coins text-blue-500"></i>
            Supplier charge rules
        </h2>
        <a href="{{ route('suppliers.charge-rules.defaults') }}" class="text-xs text-blue-600 hover:text-blue-800 font-medium">Company defaults &rarr;</a>
    </div>

    @if ($chargeRuleRows->isEmpty())
        <div class="text-sm text-gray-500 italic mb-3">No supplier-specific charge rules yet.</div>
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
                        <th class="text-center px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Comm.</th>
                        <th class="text-center px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Active</th>
                        @if ($canManageSupplier)
                            <th class="text-right px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($chargeRuleRows as $rule)
                        <tr class="hover:bg-blue-50/50">
                            <td class="px-3 py-2">{{ $rule->charge_kind }}</td>
                            <td class="px-3 py-2 text-gray-500">{{ $rule->service_type ?? 'any' }}</td>
                            <td class="px-3 py-2 text-gray-500">{{ $rule->channel ?? 'any' }}</td>
                            <td class="px-3 py-2">{{ $rule->basis }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format($rule->amount, 3) }}</td>
                            <td class="px-3 py-2">{{ $rule->recharge_policy }}</td>
                            <td class="px-3 py-2 text-center">{{ $rule->commissionable ? 'Yes' : 'No' }}</td>
                            <td class="px-3 py-2 text-center">
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $rule->active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-500' }}">{{ $rule->active ? 'Active' : 'Inactive' }}</span>
                            </td>
                            @if ($canManageSupplier)
                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                    <button type="button" @click="editingId = editingId === {{ $rule->id }} ? null : {{ $rule->id }}" class="text-blue-600 hover:text-blue-800 text-xs font-semibold mr-2">Edit</button>
                                    <form action="{{ route('suppliers.charge-rules.deactivate', $rule->id) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold {{ $rule->active ? 'text-red-600 hover:text-red-800' : 'text-emerald-600 hover:text-emerald-800' }}">{{ $rule->active ? 'Deactivate' : 'Reactivate' }}</button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                        @if ($canManageSupplier)
                            <tr x-show="editingId === {{ $rule->id }}" x-cloak>
                                <td colspan="9" class="px-3 py-3 bg-gray-50">
                                    @include('suppliers.partials.charge-rule-form', ['action' => route('suppliers.charge-rules.update', $rule->id), 'method' => 'PUT', 'rule' => $rule])
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($canManageSupplier)
        <div class="mb-4">
            <button type="button" @click="adding = !adding" class="bg-blue-100 text-blue-700 hover:bg-blue-200 font-medium text-xs px-3 py-1.5 rounded-lg transition">+ Add charge rule</button>
            <div x-show="adding" x-cloak class="mt-2 bg-gray-50 rounded-lg p-3">
                @include('suppliers.partials.charge-rule-form', ['action' => route('suppliers.charge-rules.store', $supplier->id), 'method' => 'POST', 'rule' => null])
            </div>
        </div>
    @else
        <div class="text-sm text-amber-700 flex items-center gap-2 bg-amber-50 border border-amber-200 rounded-md px-3 py-2 mb-4">
            <i class="fa-solid fa-circle-info"></i>
            <span>If you need to change a charge rule, please contact your system administrator.</span>
        </div>
    @endif

    {{-- "Test a task" preview -- pick a task or enter supplier/service_type/channel manually;
         computed amounts only, nothing posts. --}}
    <div x-data="{
            mode: 'manual', taskId: '', serviceType: 'flight', channel: '', fareAmount: 0, totalAmount: 0,
            rules: null, error: null,
            async test() {
                this.error = null; this.rules = null;
                const body = this.mode === 'task'
                    ? { task_id: this.taskId }
                    : { supplier_id: {{ $supplier->id }}, service_type: this.serviceType, channel: this.channel || null, fare_amount: this.fareAmount, total_amount: this.totalAmount };
                try {
                    const res = await fetch('{{ route('suppliers.charge-rules.test') }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: JSON.stringify(body)
                    });
                    const data = await res.json();
                    if (data.success) { this.rules = data.rules; }
                    else { this.error = data.message ?? 'Test failed.'; }
                } catch (e) { this.error = 'Network error.'; }
            }
        }" class="border-t border-gray-100 pt-4">
        <h3 class="text-sm font-semibold text-gray-700 mb-2">Test a task</h3>
        <div class="flex gap-3 mb-2 text-xs">
            <label class="flex items-center gap-1"><input type="radio" x-model="mode" value="manual"> Manual</label>
            <label class="flex items-center gap-1"><input type="radio" x-model="mode" value="task"> By task id</label>
        </div>
        <div x-show="mode === 'task'" class="flex gap-2 items-end mb-2">
            <input type="number" x-model="taskId" placeholder="Task ID" class="border border-gray-300 rounded px-2 py-1 text-sm w-32">
        </div>
        <div x-show="mode === 'manual'" class="flex flex-wrap gap-2 items-end mb-2">
            <input type="text" x-model="serviceType" placeholder="Service type" class="border border-gray-300 rounded px-2 py-1 text-sm w-28">
            <input type="text" x-model="channel" placeholder="Channel (optional)" class="border border-gray-300 rounded px-2 py-1 text-sm w-32">
            <input type="number" step="0.001" x-model.number="fareAmount" placeholder="Fare" class="border border-gray-300 rounded px-2 py-1 text-sm w-24">
            <input type="number" step="0.001" x-model.number="totalAmount" placeholder="Total" class="border border-gray-300 rounded px-2 py-1 text-sm w-24">
        </div>
        <button type="button" @click="test()" class="bg-gray-700 hover:bg-gray-800 text-white text-xs font-semibold px-3 py-1.5 rounded">Test</button>

        <div x-show="rules && rules.length === 0" x-cloak class="mt-2 text-sm text-gray-500">No rule resolves for this input.</div>
        <div x-show="rules && rules.length > 0" x-cloak class="mt-3 space-y-1">
            <template x-for="r in rules" :key="r.rule_id">
                <div class="flex justify-between bg-indigo-50 rounded-lg px-3 py-2 text-sm">
                    <span x-text="r.charge_kind + ' (' + r.basis + ', ' + r.recharge_policy + ')'"></span>
                    <span class="font-semibold" x-text="r.amount"></span>
                </div>
            </template>
        </div>
        <div x-show="error" x-cloak class="mt-2 text-sm text-red-700" x-text="error"></div>
    </div>
</div>
