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

    <!-- Form and Image Section -->
    <div class="flex justify-center items-center overflow-y-auto">
        <div
            class="mt-10 flex flex-col lg:flex-row justify-between items-stretch w-full max-w-7xl bg-white dark:bg-gray-800 shadow-lg rounded-lg overflow-hidden">

            <!-- Image Section -->
            <div class="w-full lg:w-2/5 h-96 lg:h-auto">
                <img src="<?php echo e(asset('images/registeruser.jpg')); ?>" alt="User Registration"
                    class="w-full h-full object-cover" />
            </div>

            <!-- Form Section -->
            <div class="w-full lg:w-3/5 p-8 flex items-center justify-center">
                <div class="w-full">
                    <h2 class="text-3xl font-semibold text-gray-700 dark:text-gray-200 text-center mb-6">Add New
                        Company</h2>

                    <!-- Registration Form -->

                    <form method="POST" action="<?php echo e(route('companies.store')); ?>" class="p-2">
                        <?php echo csrf_field(); ?>

                        <!-- Company Details -->

                        <div class="mb-6">
                            <label for="company_name" class="block text-gray-700 text-sm font-medium mb-2">Company Name</label>
                            <input type="text" name="name" id="company_name" class="form-control w-full rounded-lg border border-gray-300 p-3 focus:outline-none focus:border-blue-500" required>
                        </div>
                        <div class="mb-6">
                            <label for="email" class="block text-gray-700 text-sm font-medium mb-2">Company Email</label>
                            <input type="email" name="email" id="email" class="form-control w-full rounded-lg border border-gray-300 p-3 focus:outline-none focus:border-blue-500" required>
                        </div>
                        <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-6">

                            <div class="mb-6">
                                <label for="code" class="block text-gray-700 text-sm font-medium mb-2">Company Code</label>
                                <input type="text" name="code" id="code" class="form-control w-full rounded-lg border border-gray-300 p-3 focus:outline-none focus:border-blue-500" required>
                            </div>

                            <div class="mb-6">
                                <label for="country_id" class="block text-gray-700 text-sm font-medium mb-2">Select Country</label>
                                <select name="country_id" id="country_id" class="form-control w-full rounded-lg border border-gray-300 p-3 focus:outline-none focus:border-blue-500" required>
                                    <option value="" disabled selected>Select a country</option>
                                    <?php $__currentLoopData = $countries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $country): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($country->id); ?>"><?php echo e($country->name); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            </div>

                        </div>

                        <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="address" class="block text-gray-700 text-sm font-medium mb-2">Address</label>
                                <input type="text" name="address" id="address" class="form-control w-full rounded-lg border border-gray-300 p-3 focus:outline-none focus:border-blue-500">
                            </div>
                            <div>
                                <label for="phone" class="block text-gray-700 text-sm font-medium mb-2">Phone</label>
                                <input type="text" name="phone" id="phone" class="form-control w-full rounded-lg border border-gray-300 p-3 focus:outline-none focus:border-blue-500">
                            </div>
                        </div>

                        <!-- Set Password -->
                        <h2 class="text-lg font-semibold mb-4 mt-8">Set Password</h2>

                        <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="password" class="block text-gray-700 text-sm font-medium mb-2">Password</label>
                                <input type="password" name="password" id="password" class="form-control w-full rounded-lg border border-gray-300 p-3 focus:outline-none focus:border-blue-500" required>
                            </div>
                            <div>
                                <label for="password_confirmation" class="block text-gray-700 text-sm font-medium mb-2">Confirm Password</label>
                                <input type="password" name="password_confirmation" id="password_confirmation" class="form-control w-full rounded-lg border border-gray-300 p-3 focus:outline-none focus:border-blue-500" required>
                            </div>
                        </div>

                        <!-- status -->
                        <div class="mb-6">
                            <label for="status" class="block text-gray-700 text-sm font-medium mb-2">Status</label>
                            <select name="status" id="status" class="form-control w-full rounded-lg border border-gray-300 p-3 focus:outline-none focus:border-blue-500" required>
                                <option value="" disabled selected>Select a status</option>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>

                        <div class="flex items-center justify-between mt-8">
                            <button type="submit"
                                class="justify-center text-center text-gray-700 CirtbgYellow hover:bg-[#F7BE38]/90 focus:ring-4 focus:outline-none focus:ring-[#F7BE38]/50 font-medium rounded-lg px-5 py-2.5 text-center inline-flex items-center dark:focus:ring-[#F7BE38]/50 City-me-2 mb-2 w-full border-0 uppercase shadow-[0_10px_20px_-10px_rgba(67,97,238,0.44)]">
                                Add Company
                            </button>
                        </div>
                    </form>
                    <!-- ./Registration Form -->


                </div>
            </div>
        </div>
    </div>

    <?php if(session('success')): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: '<?php echo e(session('
            success ')); ?>',
            timer: 3000,
            showConfirmButton: false
        });
    </script>
    <?php endif; ?>



 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/admin/companiesNew.blade.php ENDPATH**/ ?>