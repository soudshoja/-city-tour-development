<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['class' => 'w-5 h-5']));

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

foreach (array_filter((['class' => 'w-5 h-5']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars); ?>

<svg <?php echo e($attributes->merge(['class' => $class])); ?> xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
    <g fill="none" stroke="currentColor" stroke-width="1.5">
        <circle cx="9" cy="6" r="4" />
        <path stroke-linecap="round" d="M15 9a3 3 0 1 0 0-6" />
        <ellipse cx="9" cy="17" rx="7" ry="4" />
        <path stroke-linecap="round" d="M18 14c1.754.385 3 1.359 3 2.5c0 1.03-1.014 1.923-2.5 2.37" />
    </g>
</svg>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/components/icons/users.blade.php ENDPATH**/ ?>