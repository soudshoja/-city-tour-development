@php
    $supplier = $supplier ?? new \App\Models\Supplier();
@endphp
<div x-data="{
        hasHotel: {{ $supplier->has_hotel ? 'true' : 'false' }},
        hasFlight: {{ $supplier->has_flight ? 'true' : 'false' }},
        hasVisa: {{ $supplier->has_visa ? 'true' : 'false' }},
        hasInsurance: {{ $supplier->has_insurance ? 'true' : 'false' }},
        hasTour: {{ $supplier->has_tour ? 'true' : 'false' }},
        hasCruise: {{ $supplier->has_cruise ? 'true' : 'false' }},
        hasCar: {{ $supplier->has_car ? 'true' : 'false' }},
        hasRail: {{ $supplier->has_rail ? 'true' : 'false' }},
        hasEsim: {{ $supplier->has_esim ? 'true' : 'false' }},
        hasEvent: {{ $supplier->has_event ? 'true' : 'false' }},
        hasLounge: {{ $supplier->has_lounge ? 'true' : 'false' }},
        hasFerry: {{ $supplier->has_ferry ? 'true' : 'false' }},
        hotelChannel: '{{ old('hotel_channel', ($supplier->is_online === null ? '' : ($supplier->is_online ? 'online' : 'offline'))) }}',
        isManual: {{ $supplier->is_manual ? 'true' : 'false' }},
    }" class="mt-2">
    <span class="text-sm font-medium text-gray-700 dark:text-gray-300 mr-3 whitespace-nowrap shrink-0">Service Type</span>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-24 gap-y-2" @click.stop>
        @foreach([
            'hasHotel' => ['label' => 'Has Hotel', 'name' => 'has_hotel', 'extra' => "if(!hasHotel) hotelChannel='';"],
            'hasFlight' => ['label' => 'Has Flight', 'name' => 'has_flight'],
            'hasVisa' => ['label' => 'Has Visa', 'name' => 'has_visa'],
            'hasInsurance' => ['label' => 'Has Insurance', 'name' => 'has_insurance'],
            'hasTour' => ['label' => 'Has Tour', 'name' => 'has_tour'],
            'hasCruise' => ['label' => 'Has Cruise', 'name' => 'has_cruise'],
            'hasCar' => ['label' => 'Has Car', 'name' => 'has_car'],
            'hasRail' => ['label' => 'Has Rail', 'name' => 'has_rail'],
            'hasEsim' => ['label' => 'Has Esim', 'name' => 'has_esim'],
            'hasEvent' => ['label' => 'Has Event', 'name' => 'has_event'],
            'hasLounge' => ['label' => 'Has Lounge', 'name' => 'has_lounge'],
            'hasFerry' => ['label' => 'Has Ferry', 'name' => 'has_ferry'],
        ] as $var => $config)
        <div class="flex items-center justify-between p-2 rounded-lg" @click.stop>
            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $config['label'] }}</span>
            <button type="button"
                @click="{{ $var }} = !{{ $var }}; {{ $config['extra'] ?? '' }}"
                :aria-pressed="{{ $var }}.toString()"
                class="w-11 h-6 rounded-full relative transition"
                :class="{{ $var }} ? 'bg-blue-600' : 'bg-gray-200'">
                <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition"
                    :class="{{ $var }} ? 'translate-x-5' : ''"></span>
            </button>
            <template x-if="{{ $var }}">
                <input type="hidden" name="{{ $config['name'] }}" value="1">
            </template>
        </div>
        @endforeach
    </div>

    <div x-cloak x-show="hasHotel" class="mt-2" @click.stop>
        <div class="flex flex-col md:flex-row md:items-end gap-6">
            <div class="flex flex-col">
                <label for="hotel_channel" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hotel Supplier Mode</label>
                <select name="hotel_channel" x-model="hotelChannel" :disabled="!hasHotel"
                    class="block h-10 w-64 md:w-72 min-w-[16rem] border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded px-3 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
                    <option value="" disabled>Select mode</option>
                    <option value="online">Online</option>
                    <option value="offline">Offline</option>
                </select>
                <template x-if="hasHotel">
                    <input type="hidden" name="is_online" :value="hotelChannel === 'online' ? 1 : 0">
                </template>
            </div>
            <div class="flex flex-col">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Manual Supplier</label>
                <button type="button" @click="isManual = !isManual"
                    :aria-pressed="isManual.toString()"
                    class="w-11 h-6 rounded-full relative transition"
                    :class="isManual ? 'bg-blue-600' : 'bg-gray-200'">
                    <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition"
                        :class="isManual ? 'translate-x-5' : ''"></span>
                </button>
                <template x-if="isManual">
                    <input type="hidden" name="is_manual" value="1">
                </template>
            </div>
        </div>
    </div>

    <div class="mt-4" @click.stop>
        <label for="agency_commission" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Agency Commission (%)</label>
        <input type="number" name="agency_commission" id="agency_commission" min="0" max="100" step="0.01"
            value="{{ old('agency_commission', $supplier->agency_commission) }}" placeholder="e.g. 25"
            class="block h-10 w-64 md:w-72 min-w-[16rem] border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded px-3 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
        <p class="text-xs text-gray-400 mt-1">When set, the system records each task's net = retail price minus this %.</p>
    </div>

    {{--
        CT-A3 wave 1 — owner ruling R-CT3, 2026-09-09: "need to pay are the one guaranteed to be
        paid not hold or some supplier confirmed so this needs to be done based on the status of
        supplier which we set on supplier aspect". This is that setting. App\Services\Accounting\
        SupplierPayableRule reads it and App\Services\Accounting\TaskIssuancePayableService acts
        on it; the task statuses each option covers live in
        config('accounting.supplier_payable.triggers'), never in code.
    --}}
    <div class="mt-4" @click.stop>
        <label for="payable_trigger" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Supplier payable becomes due</label>
        <select name="payable_trigger" id="payable_trigger"
            class="block h-10 w-64 md:w-72 min-w-[16rem] border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded px-3 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
            @php($currentTrigger = old('payable_trigger', $supplier->payable_trigger ?: config('accounting.supplier_payable.default_trigger', 'on_issue')))
            <option value="on_supplier_confirm" @selected($currentTrigger === 'on_supplier_confirm')>On supplier confirmation</option>
            <option value="on_issue" @selected($currentTrigger === 'on_issue')>On issue / ticketing (default)</option>
            <option value="on_voucher" @selected($currentTrigger === 'on_voucher')>Only once a voucher is raised</option>
            <option value="manual" @selected($currentTrigger === 'manual')>Never — raise the payable manually</option>
        </select>
        <p class="text-xs text-gray-400 mt-1">
            The earliest task status at which this supplier's cost is a guaranteed liability. Held and
            unconfirmed tasks never accrue. Changing this does not restate tasks already posted.
        </p>
    </div>

    <div class="mt-4" @click.stop>
        <label class="inline-flex items-center gap-2">
            <input type="hidden" name="payable_hold" value="0">
            <input type="checkbox" name="payable_hold" id="payable_hold" value="1"
                @checked(old('payable_hold', (bool) $supplier->payable_hold))
                class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-blue-600 focus:ring-blue-400">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Hold supplier payable accrual</span>
        </label>
        <p class="text-xs text-gray-400 mt-1">
            Suspends automatic accrual for this supplier at every status, whatever the setting above says.
        </p>
    </div>
</div>
