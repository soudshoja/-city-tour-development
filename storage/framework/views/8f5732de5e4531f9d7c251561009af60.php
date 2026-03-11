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
    <div class="mb-6 grid gap-6 xl:grid-cols-3">
        <div class="w-full md:w-96 md:max-w-full mx-auto">
            <div class="p-6 border border-gray-300 sm:rounded-md">
                <form method="POST" action="<?php echo e(route('agents.import')); ?>" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <label class="block mb-6">
                        <span class="text-gray-700">Upload Agents Details</span>
                        <input
                            required
                            name="excel_file"
                            type="file"
                            class="block w-full mt-1 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                        />
                    </label>
                    <div class="mb-6">
                        <button type="submit" class="h-10 px-5 text-indigo-100 bg-indigo-700 rounded-lg transition-colors duration-150 focus:shadow-outline hover:bg-indigo-800">
                            Apply
                        </button>
                    </div>
                </form>
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
<?php endif; ?>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/agents/agentsUpload.blade.php ENDPATH**/ ?>