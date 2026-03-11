<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'companyLogo' => asset('images/UserPic.svg'),
    'width' => '100',
    'height' => '75',
    'class' => '',
    'alt' => 'Logo'
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
    'companyLogo' => asset('images/UserPic.svg'),
    'width' => '100',
    'height' => '75',
    'class' => '',
    'alt' => 'Logo'
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars); ?>

<img 
    id="logo" 
    src="<?php echo e($companyLogo); ?>" 
    alt="<?php echo e($alt); ?>" 
    width="<?php echo e($width); ?>" 
    height="<?php echo e($height); ?>"
    <?php echo e($attributes->merge(['class' => $class])); ?>

>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/components/application-logo.blade.php ENDPATH**/ ?>