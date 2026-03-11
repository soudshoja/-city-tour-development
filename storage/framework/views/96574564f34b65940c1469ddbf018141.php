<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'action',
    'searchParam',
    'placeholder' => 'Quick search',
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
    'action',
    'searchParam',
    'placeholder' => 'Quick search',
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars); ?>

<form action="<?php echo e($action); ?>" method="GET" class="flex flex-1 min-w-0 items-center gap-2">
    <?php $__currentLoopData = request()->except($searchParam, 'page'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php if(is_array($value)): ?>
            <?php $__currentLoopData = $value; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <input type="hidden" name="<?php echo e($key); ?>[]" value="<?php echo e($v); ?>">
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php else: ?>
            <input type="hidden" name="<?php echo e($key); ?>" value="<?php echo e($value); ?>">
        <?php endif; ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <div class="relative w-full lg:w-2/3">
        <input type="text" name="<?php echo e($searchParam); ?>" value="<?php echo e(request($searchParam)); ?>" id="searchInput"
            class="block w-full rounded-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 py-3 pl-4 pr-10 text-sm text-gray-900 dark:text-gray-100 focus:border-blue-600 dark:focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 ">
        <label for="searchInput" class="pointer-events-none absolute -top-2 left-3 inline-block bg-white dark:bg-gray-800 px-2 text-xs text-gray-500 dark:text-gray-400">
            <?php echo e($placeholder); ?>

        </label>
        <button type="submit" class="absolute right-1 top-1/2 -translate-y-1/2 inline-flex h-9 w-9 items-center justify-center rounded-full
            bg-blue-50 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300 hover:bg-blue-600 dark:hover:bg-blue-600 hover:text-white dark:hover:text-white transition">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none">
                <circle cx="11.5" cy="11.5" r="9.5" stroke="currentColor" stroke-width="1.5" opacity="0.6" />
                <path d="M18.5 18.5L22 22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
            </svg>
        </button>
    </div>
    <?php if(request($searchParam)): ?>
        <button type="button" onclick="window.location='<?php echo e($action); ?>?<?php echo e(http_build_query(request()->except($searchParam, 'page'))); ?>'"
            class="relative group bg-red-200 dark:bg-red-800 hover:bg-red-500 dark:hover:bg-red-600 text-black dark:text-white hover:text-white dark:hover:text-white w-9 h-9 flex items-center justify-center rounded-full 
            transition-all duration-300 opacity-100 scale-100 pointer-events-auto">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    <?php endif; ?>
</form><?php /**PATH /home/soudshoja/soud-laravel/resources/views/components/search.blade.php ENDPATH**/ ?>