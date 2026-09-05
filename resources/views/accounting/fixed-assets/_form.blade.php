{{--
    Shared create/edit form. `$asset` is null on create. `$basisFrozen` (edit only) marks
    cost/salvage/useful_life_months/in_service_date/asset_class/method read-only, mirroring
    FixedAssetService::assertBasisNotFrozen() (D4) exactly — this is presentation only, the
    server-side rejection lives entirely in the service and is never duplicated here.

    Deliberately absent from every field below: `status`. See FixedAssetController's hard
    constraint 1 — status changes only through Capitalise (on the show page) and Dispose.
--}}
@php
    $isEdit = $asset !== null;
@endphp

<div class="bg-white dark:bg-gray-800 rounded-lg BoxShadow p-6 max-w-3xl">
    @if ($isEdit && $basisFrozen)
        <div class="mb-6 rounded-lg border border-sky-300 dark:border-sky-700 bg-sky-50 dark:bg-sky-900/30 text-sky-800 dark:text-sky-200 px-4 py-3 text-sm">
            Depreciation has already posted for this asset. Cost, salvage, useful life, in-service date, class, and method are frozen — grayed-out fields below cannot be changed here.
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div class="md:col-span-2">
            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
            <input type="text" id="name" name="name" required maxlength="160"
                   value="{{ old('name', $asset->name ?? '') }}"
                   class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
        </div>

        <div>
            <label for="code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Asset tag / code <span class="text-gray-400">(optional)</span></label>
            <input type="text" id="code" name="code" maxlength="60"
                   value="{{ old('code', $asset->code ?? '') }}"
                   class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
        </div>

        <div>
            <label for="asset_class" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Class</label>
            @if ($isEdit && $basisFrozen)
                <input type="hidden" name="asset_class" value="{{ $asset->asset_class }}">
                <select id="asset_class" disabled
                        class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-900 text-gray-500 dark:text-gray-400 text-sm">
                    <option>{{ $classes[$asset->asset_class]['label'] ?? $asset->asset_class }}</option>
                </select>
            @else
                <select id="asset_class" name="asset_class" required
                        class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                    <option value="">Select a class</option>
                    @foreach ($classes as $key => $classConfig)
                        <option value="{{ $key }}" @selected(old('asset_class', $asset->asset_class ?? '') === $key)>{{ $classConfig['label'] }}</option>
                    @endforeach
                </select>
            @endif
        </div>

        <div>
            <label for="cost" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cost (KWD)</label>
            <input type="number" id="cost" name="cost" step="0.001" min="0.001" required
                   value="{{ old('cost', $asset->cost ?? '') }}"
                   @if($isEdit && $basisFrozen) readonly @endif
                   @class([
                       'w-full px-3 py-2 rounded-lg border text-sm',
                       'border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white' => ! ($isEdit && $basisFrozen),
                       'border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-900 text-gray-500 dark:text-gray-400' => $isEdit && $basisFrozen,
                   ])>
        </div>

        <div>
            <label for="salvage" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Salvage value (KWD)</label>
            <input type="number" id="salvage" name="salvage" step="0.001" min="0"
                   value="{{ old('salvage', $asset->salvage ?? 0) }}"
                   @if($isEdit && $basisFrozen) readonly @endif
                   @class([
                       'w-full px-3 py-2 rounded-lg border text-sm',
                       'border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white' => ! ($isEdit && $basisFrozen),
                       'border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-900 text-gray-500 dark:text-gray-400' => $isEdit && $basisFrozen,
                   ])>
        </div>

        <div>
            <label for="useful_life_months" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Useful life (months)</label>
            <input type="number" id="useful_life_months" name="useful_life_months" step="1" min="1" required
                   value="{{ old('useful_life_months', $asset->useful_life_months ?? '') }}"
                   @if($isEdit && $basisFrozen) readonly @endif
                   @class([
                       'w-full px-3 py-2 rounded-lg border text-sm',
                       'border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white' => ! ($isEdit && $basisFrozen),
                       'border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-900 text-gray-500 dark:text-gray-400' => $isEdit && $basisFrozen,
                   ])>
        </div>

        <div>
            <label for="method" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Method</label>
            <input type="hidden" name="method" value="{{ old('method', $asset->method ?? ($methods[0] ?? 'straight_line')) }}">
            <select id="method" disabled
                    class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-900 text-gray-500 dark:text-gray-400 text-sm">
                <option>{{ ucwords(str_replace('_', ' ', old('method', $asset->method ?? ($methods[0] ?? 'straight_line')))) }}</option>
            </select>
        </div>

        <div>
            <label for="acquisition_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Acquisition date</label>
            <input type="date" id="acquisition_date" name="acquisition_date" required
                   value="{{ old('acquisition_date', optional($asset->acquisition_date ?? null)->format('Y-m-d')) }}"
                   class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
        </div>

        <div>
            <label for="in_service_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">In-service date</label>
            <input type="date" id="in_service_date" name="in_service_date" required
                   value="{{ old('in_service_date', optional($asset->in_service_date ?? null)->format('Y-m-d')) }}"
                   @if($isEdit && $basisFrozen) readonly @endif
                   @class([
                       'w-full px-3 py-2 rounded-lg border text-sm',
                       'border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white' => ! ($isEdit && $basisFrozen),
                       'border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-900 text-gray-500 dark:text-gray-400' => $isEdit && $basisFrozen,
                   ])>
            <p class="text-xs text-gray-400 mt-1">Depreciation starts a full month here — no pro-rata days.</p>
        </div>

        <div>
            <label for="branch_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Branch <span class="text-gray-400">(optional)</span></label>
            <select id="branch_id" name="branch_id"
                    class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                <option value="">Company default</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((string) old('branch_id', $asset->branch_id ?? '') === (string) $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="supplier_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Supplier <span class="text-gray-400">(optional, informational)</span></label>
            <select id="supplier_id" name="supplier_id"
                    class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                <option value="">None</option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" @selected((string) old('supplier_id', $asset->supplier_id ?? '') === (string) $supplier->id)>{{ $supplier->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="md:col-span-2">
            <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes <span class="text-gray-400">(optional)</span></label>
            <textarea id="notes" name="notes" rows="3"
                      class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">{{ old('notes', $asset->notes ?? '') }}</textarea>
        </div>
    </div>

    <div class="flex items-center gap-3 mt-6">
        <button type="submit" class="px-4 py-2 rounded-lg text-sm font-medium bg-slate-800 text-white hover:bg-slate-700">
            {{ $isEdit ? 'Save changes' : 'Register asset' }}
        </button>
        <a href="{{ $isEdit ? route('accounting.fixed-assets.show', $asset) : route('accounting.fixed-assets.index') }}"
           class="px-4 py-2 rounded-lg text-sm font-medium text-gray-600 dark:text-gray-300 hover:underline">
            Cancel
        </a>
    </div>
</div>
