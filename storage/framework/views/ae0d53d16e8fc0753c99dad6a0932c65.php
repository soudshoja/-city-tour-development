<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>" class="<?php echo e(session('theme') === 'dark' ? 'dark' : ''); ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    <?php echo $__env->make('layouts.links', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    <title><?php echo e(config('app.name', 'Laravel')); ?></title>

    <!-- CSS -->
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css']); ?>
    <?php echo app('Illuminate\Foundation\Vite')(['resources/js/jsbyNisma.js', 'resources/js/app.js', 'resources/js/tools.js']); ?>

    <?php echo $__env->yieldPushContent('styles'); ?>

    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::styles(); ?>

    <script src="<?php echo e(asset('js/nice-select2.js')); ?>"></script>
    <!-- Scripts -->

    <?php echo RecaptchaV3::initJs(); ?>

</head>

<body>
    <?php echo $__env->make('layouts.alert', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    <!-- Top Navigation -->
    <div>
        <?php echo $__env->make('layouts.navigation', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    </div>
    <!-- ./Top Navigation -->

    <!-- Page Content -->
    <main>
        <div class="container mx-auto max-w-screen overflow-hidden">
            <div class="flex flex-col lg:flex-row md:flex-row">
                
                <?php echo $__env->make('layouts.sidebar', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

                <!-- Main Content -->
                <div class="Main p-5">
                    <?php echo e($slot); ?>

                    <?php echo $__env->make('layouts.footer', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                </div>
            </div>
        </div>


    </main>

    <?php if(session('tbo.url') === null && request()->routeIs('suppliers.tbo.index')): ?>
    <?php echo $__env->make('suppliers.credential-modal', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    <?php endif; ?>

    <!-- Global Duplicate Client Warning Modal -->
    <?php if (isset($component)) { $__componentOriginal150a841716326e07b9124fa9c69c24e8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal150a841716326e07b9124fa9c69c24e8 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.duplicate-client-warning','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('duplicate-client-warning'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal150a841716326e07b9124fa9c69c24e8)): ?>
<?php $attributes = $__attributesOriginal150a841716326e07b9124fa9c69c24e8; ?>
<?php unset($__attributesOriginal150a841716326e07b9124fa9c69c24e8); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal150a841716326e07b9124fa9c69c24e8)): ?>
<?php $component = $__componentOriginal150a841716326e07b9124fa9c69c24e8; ?>
<?php unset($__componentOriginal150a841716326e07b9124fa9c69c24e8); ?>
<?php endif; ?>

    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::scripts(); ?>



</body>

</html><?php /**PATH /home/soudshoja/soud-laravel/resources/views/components/layouts/app.blade.php ENDPATH**/ ?>