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
    <nav>
        <ul class="flex space-x-2 rtl:space-x-reverse pb-5 text-base md:text-lg sm:text-sm">
            <li>
                <a href="<?php echo e(route('dashboard')); ?>" class="customBlueColor hover:underline">Dashboard</a>
            </li>
            <li class="before:content-['/'] before:mr-1 ">
                <a href="<?php echo e(route('suppliers.index')); ?>" class="customBlueColor hover:underline">Supplier List</a>
            </li>
            <li class="before:content-['/'] before:mr-1 ">
                <span><?php echo e($supplier->name); ?></span>
            </li>
        </ul>
    </nav>
    <div class="p-2 bg-white rounded shadow">
        <?php $__currentLoopData = $companies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $company): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="flex justify-between items-center p-2 border-b border-gray-200">
            <div>
                <h2 class="text-lg font-semibold"><?php echo e($company->name); ?></h2>
                <p class="text-sm text-gray-500"><?php echo e($company->address); ?></p>
            </div>
            <div>
                <?php if($company->is_active): ?>
                <a href="<?php echo e(route('supplier-company.deactivate',['supplier_id' => $supplier , 'company_id' => $company])); ?>"
                 class="p-2 bg-red-500 hover:bg-red-600 rounded shadow text-white">Deactivate</a>
                <?php else: ?>
                <a href="<?php echo e(route('supplier-company.activate', ['supplier_id' => $supplier , 'company_id' => $company])); ?>"
                class="p-2 bg-green-500 hover:bg-green-600 rounded shadow text-white">activate</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
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
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/supplier-company/index.blade.php ENDPATH**/ ?>