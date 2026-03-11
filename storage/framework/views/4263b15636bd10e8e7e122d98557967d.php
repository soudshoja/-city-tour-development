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
    <div class="container mx-auto p-6">
        <!-- Page Title -->
        <h1 class="text-3xl font-bold text-gray-700 mb-6">Edit Agent Details</h1>

        <!-- Edit Form -->
        <div class="bg-white shadow-md rounded-lg p-8">
            <form action="<?php echo e(route('agents.update', $agent->id)); ?>" method="POST">
                <?php echo csrf_field(); ?>
                <?php echo method_field('PUT'); ?>

                <!-- Name -->
                <div class="mb-6">
                    <label for="name" class="block text-gray-700 font-semibold mb-2">Name</label>
                    <input type="text" name="name" id="name" value="<?php echo e($agent->name); ?>" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                </div>

                <!-- Email -->
                <div class="mb-6">
                    <label for="email" class="block text-gray-700 font-semibold mb-2">Email</label>
                    <input type="email" name="email" id="email" value="<?php echo e($agent->email); ?>" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                </div>

                <!-- Phone Number -->
                <div class="mb-6">
                    <label for="phone_number" class="block text-gray-700 font-semibold mb-2">Phone Number</label>
                    <input type="text" name="phone_number" id="phone_number" value="<?php echo e($agent->phone_number); ?>" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                </div>

                <!-- Company -->
                <div class="mb-6">
                    <label for="branch_id" class="block text-gray-700 font-semibold mb-2">Branch</label>
                    <select name="branch_id" id="branch_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                        <?php $__currentLoopData = $branches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $branch): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($branch->id); ?>" <?php echo e($agent->branch_id == $branch->id ? 'selected' : ''); ?>><?php echo e($branch->name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <!-- Type -->
                <div class="mb-6">
                    <label for="type" class="block text-gray-700 font-semibold mb-2">Type</label>
                    <select name="type" id="type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                        <option value="staff" <?php echo e($agent->type == 'staff' ? 'selected' : ''); ?>>Staff</option>
                        <option value="manager" <?php echo e($agent->type == 'manager' ? 'selected' : ''); ?>>Manager</option>
                        <option value="admin" <?php echo e($agent->type == 'admin' ? 'selected' : ''); ?>>Admin</option>
                    </select>
                </div>

                <!-- Submit Button -->
                <div class="text-right">
                    <button type="submit"
                        class="bg-black text-white py-2 px-6 rounded-lg shadow hover:bg-indigo-600 transition duration-200">
                        Update Agent
                    </button>
                </div>
            </form>
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
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/agents/agentsEdit.blade.php ENDPATH**/ ?>