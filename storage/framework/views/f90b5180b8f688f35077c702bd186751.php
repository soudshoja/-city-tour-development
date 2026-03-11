<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
'selectedId' => '',
'selectedName' => ''
]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter(([
'selectedId' => '',
'selectedName' => ''
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars); ?>
<div
    x-data="searchableDropdown({ 
        items: <?php echo e($items ?? '[]'); ?>, 
        selectedId: '<?php echo e($selectedId ?? ''); ?>',
        selectedName: '<?php echo e($selectedName ?? ''); ?>',
        name: '<?php echo e($name ?? 'dropdown'); ?>',
        placeholder: '<?php echo e($placeholder ?? 'Select an option'); ?>'
    })"
    x-init="init()"
    class="w-full">
    <div class="relative">
        <?php if($label): ?>
        <label class="block mb-1 text-sm font-medium text-gray-700"><?php echo e($label); ?></label>
        <?php endif; ?>

        <button type="button"
        @click="open = !open; if(open) { $nextTick(() => focusSearch($refs)) }"
        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-left bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-colors">
            <span class="truncate block w-full" :class="selectedName ? 'text-gray-900' : 'text-gray-400'" x-text="selectedName || placeholder"></span>
        </button>

        <input type="hidden" name="<?php echo e($name); ?>" :value="selectedId">
        <?php if($items): ?>
        <div x-cloak x-show="open" @click.away="open = false"
            class="absolute bg-white z-10 border border-gray-300 w-full max-h-48 overflow-y-auto rounded-lg shadow-lg mt-1">
            <div class="px-2 py-2">
                <input type="text"
                    x-ref="searchInput"
                    x-model="search"
                    @input="filterOptions"
                    :placeholder="placeholder"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-black focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
            </div>

            <template x-for="(option, index) in filtered.slice(0, <?php echo e($maxResults ?? 10); ?>)" :key="option.id + '-' + index">
                <div @click="select(option)"
                    class="px-3 py-2 hover:bg-gray-100 cursor-pointer text-sm"
                    x-html="highlightMatch(option.name)">
                </div>
            </template>
        </div>
        <?php endif; ?>
    </div>
</div><?php /**PATH /home/soudshoja/soud-laravel/resources/views/components/searchable-dropdown.blade.php ENDPATH**/ ?>