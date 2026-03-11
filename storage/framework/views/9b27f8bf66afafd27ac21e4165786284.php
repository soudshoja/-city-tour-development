<?php echo $__env->make('layouts.mobile-drawer', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

<header x-data>
    <div class="navigation-brand">
        <p class="text-center text-background">
            CityTourApp
        </p>
    </div>
    <div class="navigation-bar">
        <button @click="$dispatch('open-mobile-drawer')" class="navigation-mobile-menu-btn">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
            
        <div class="navigation-main">
            <a href="<?php echo e(route('dashboard')); ?>" class="navigation-logo">
                <?php if (isset($component)) { $__componentOriginal40b9bc8bbe72b013cda6958fd160ce72 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal40b9bc8bbe72b013cda6958fd160ce72 = $attributes; } ?>
<?php $component = App\View\Components\ApplicationLogo::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('application-logo'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\ApplicationLogo::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'h-20 w-auto']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal40b9bc8bbe72b013cda6958fd160ce72)): ?>
<?php $attributes = $__attributesOriginal40b9bc8bbe72b013cda6958fd160ce72; ?>
<?php unset($__attributesOriginal40b9bc8bbe72b013cda6958fd160ce72); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal40b9bc8bbe72b013cda6958fd160ce72)): ?>
<?php $component = $__componentOriginal40b9bc8bbe72b013cda6958fd160ce72; ?>
<?php unset($__componentOriginal40b9bc8bbe72b013cda6958fd160ce72); ?>
<?php endif; ?>
            </a>

            <div class="hidden md:block" id="responsiveMenu">
                <?php echo $__env->make('layouts.menu', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            </div>

        </div>

        <div x-data="{
            toggle: false,
            open: false,
            iataWallet: false
            }"
            class="navigation-profile">
            <?php echo $__env->make('layouts.profile', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        </div>
    </div>
</header>

<script>
    let walletData = null;
    let walletSessionExpiry = null;
    const WALLET_SESSION_DURATION = 60000;
</script>


<style>
    .text-background {
        background-image: url("<?php echo e(asset('images/bgCity.jpg')); ?>");
    }
</style><?php /**PATH /home/soudshoja/soud-laravel/resources/views/layouts/navigation.blade.php ENDPATH**/ ?>