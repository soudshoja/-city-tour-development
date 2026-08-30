{{-- Shared add/edit form for supplier_charge_rules -- used by charge-rule-card.blade.php and the
     company-defaults page (charge-rules-defaults.blade.php) so the two never drift apart. --}}
<form action="{{ $action }}" method="POST" class="grid grid-cols-2 md:grid-cols-4 gap-2 items-end">
    @csrf
    @if ($method === 'PUT')
        @method('PUT')
    @endif
    <div>
        <label class="text-xs text-gray-500 block">Service type</label>
        <input type="text" name="service_type" value="{{ $rule->service_type ?? '' }}" placeholder="any" class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
    </div>
    <div>
        <label class="text-xs text-gray-500 block">Channel</label>
        <input type="text" name="channel" value="{{ $rule->channel ?? '' }}" placeholder="any" class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
    </div>
    <div>
        <label class="text-xs text-gray-500 block">Charge kind</label>
        <select name="charge_kind" class="w-full border border-gray-300 rounded px-2 py-1 text-sm" required>
            @foreach (['iata_fee','rounding','service_fee','booking_fee','card_surcharge','resort_fee','other'] as $k)
                <option value="{{ $k }}" @selected(($rule->charge_kind ?? null) === $k)>{{ $k }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="text-xs text-gray-500 block">Basis</label>
        <select name="basis" class="w-full border border-gray-300 rounded px-2 py-1 text-sm" required>
            @foreach (['fixed','percent_of_fare','percent_of_total','per_passenger','per_segment'] as $b)
                <option value="{{ $b }}" @selected(($rule->basis ?? 'fixed') === $b)>{{ $b }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="text-xs text-gray-500 block">Amount</label>
        <input type="number" step="0.001" min="0" name="amount" value="{{ $rule->amount ?? 0 }}" class="w-full border border-gray-300 rounded px-2 py-1 text-sm" required>
    </div>
    <div>
        <label class="text-xs text-gray-500 block">Recharge policy</label>
        <select name="recharge_policy" class="w-full border border-gray-300 rounded px-2 py-1 text-sm" required>
            @foreach (['absorb','recharge_client','recharge_agent'] as $p)
                <option value="{{ $p }}" @selected(($rule->recharge_policy ?? 'absorb') === $p)>{{ $p }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="text-xs text-gray-500 block">Tax code</label>
        <input type="text" name="tax_code" value="{{ $rule->tax_code ?? '' }}" class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
    </div>
    <div class="flex items-center gap-2">
        <label class="text-xs text-gray-500 flex items-center gap-1">
            <input type="checkbox" name="commissionable" value="1" @checked($rule->commissionable ?? false)>
            Commissionable
        </label>
    </div>
    <div class="col-span-2 md:col-span-1">
        <label class="text-xs text-gray-500 block">Label (optional)</label>
        <input type="text" name="label" value="{{ $rule->label ?? '' }}" class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
    </div>
    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-3 py-1.5 rounded">{{ $method === 'PUT' ? 'Save' : 'Add' }}</button>
</form>
