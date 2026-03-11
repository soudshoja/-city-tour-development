<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['type', 'color']));

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

foreach (array_filter((['type', 'color']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars); ?>

<div id="<?php echo e(strtolower($type)); ?>-modal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-lg p-6 max-w-md mx-auto">
        <h2 class="text-xl font-bold mb-4">Create <?php echo e($type); ?> Account</h2>
        <form id="<?php echo e(strtolower($type)); ?>-form" class="flex flex-col">
            <label for="accountName" class="mr-2 text-sm font-medium text-gray-700">Account Name</label>
            <input type="text" name="accountName" required class="block border border-gray-300 rounded-md p-2 w-full mb-4">

            <div class="flex items-center space-x-2">
                <button type="submit" style="background-color: #<?php echo e($color); ?>" class="text-white px-4 py-2 rounded">Create</button>
                <button type="button" class="close-modal bg-gray-300 text-gray-700 px-4 py-2 rounded">Cancel</button>
            </div>
        </form>
    </div>
</div><?php /**PATH /home/soudshoja/soud-laravel/resources/views/components/coa-modal.blade.php ENDPATH**/ ?>