<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\AppLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
    <div class="flex justify-between">
        <ul class="flex space-x-2 rtl:space-x-reverse pb-5 px-5 text-base md:text-lg sm:text-sm">
            <li>
                <a href="<?php echo e(route('dashboard')); ?>" class="customBlueColor hover:underline">Dashboard</a>
            </li>
            <li class="before:content-['/'] before:mr-1 ">
                <a href="<?php echo e(route('suppliers.index')); ?>" class="customBlueColor hover:underline">Suppliers List</a>
            </li>
            <li class="before:content-['/'] before:mr-1">
                <span>TBO Holidays</span>
            </li>
        </ul>
        <div class="inline-flex gap-2">
            <a href="<?php echo e(route('suppliers.tbo.reset')); ?> " class="bg-red-500 text-white font-semibold p-2 my-2 rounded-md text-center"> Reset Credentials </a>
            <a href="<?php echo e(route('suppliers.tbo.prebook.index')); ?>" class="bg-blue-500 text-white font-semibold p-2 my-2 rounded-md text-center "> Prebook </a>
            <a href="<?php echo e(route('suppliers.tbo.book.index')); ?>" class="bg-blue-500 text-white font-semibold p-2 my-2 rounded-md text-center"> Book Rooms </a>
            <?php if (\Illuminate\Support\Facades\Blade::check('role', 'admin')): ?>
                <?php if (isset($component)) { $__componentOriginald411d1792bd6cc877d687758b753742c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald411d1792bd6cc877d687758b753742c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.primary-button','data' => ['class' => 'p-2 my-2 h-max','onclick' => 'window.location.href=\''.e(route('suppliers.tbo.all-destinations')).'\'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('primary-button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'p-2 my-2 h-max','onclick' => 'window.location.href=\''.e(route('suppliers.tbo.all-destinations')).'\'']); ?>
                    Get All Destinations
                 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald411d1792bd6cc877d687758b753742c)): ?>
<?php $attributes = $__attributesOriginald411d1792bd6cc877d687758b753742c; ?>
<?php unset($__attributesOriginald411d1792bd6cc877d687758b753742c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald411d1792bd6cc877d687758b753742c)): ?>
<?php $component = $__componentOriginald411d1792bd6cc877d687758b753742c; ?>
<?php unset($__componentOriginald411d1792bd6cc877d687758b753742c); ?>
<?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php if(session('tbo.url') == env('TBO_URL')): ?>
    <div class="w-full bg-red-200 text-red-500 p-2 rounded-md mb-2">
        Careful!!! You Are You Using Live Credentials !
    </div>
    <?php endif; ?>
    <?php echo $__env->make('suppliers.tbo.past_booking', ['pastBookings' => $pastBookings, 'startDate' => $startDate, 'endDate' => $endDate], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    <div class="">
        <div class="bg-white p-4 dark:bg-gray-600 overflow-hidden shadow-sm rounded-lg font-semibold">
            COUNTRY AVAILABLE
            <div class="px-2 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
                <?php if($countries->isEmpty()): ?>
                <div class="p-2 bg-gray-200 dark:bg-gray-800 rounded-md text-center text-sm w-full">No Country Found</div>
                <?php else: ?>
                <?php $__currentLoopData = $countries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $country): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <a href="<?php echo e(route('suppliers.tbo.city-list', [ 'countryCode' => $country['Code']])); ?>" class="p-2 bg-gradient-to-r from-gray-800 to-gray-500  dark:to-blue-600 rounded-md text-center text-sm text-white w-full"><?php echo e($country['Name']); ?></a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <?php endif; ?>
            </div>
            <div class="mt-4">
                <?php echo e($countries->links('pagination::tailwind')); ?>

            </div>
        </div>
    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/suppliers/tbo/index.blade.php ENDPATH**/ ?>