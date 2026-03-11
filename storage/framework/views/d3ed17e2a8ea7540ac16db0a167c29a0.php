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
    <nav class="flex items-center space-x-2 rtl:space-x-reverse text-sm mb-4 sm:mb-6 overflow-x-auto">
        <a href="<?php echo e(route('users.index')); ?>" class="text-gray-500 hover:text-gray-700 transition whitespace-nowrap">Users</a>
        <span class="text-gray-400">&gt;</span>
        <span class="text-blue-600 font-medium truncate max-w-[200px] sm:max-w-none"><?php echo e($user->name); ?></span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Assign Role</h2>
                    <p class="text-sm text-gray-500">Select a role for this user</p>
                </div>
            </div>

            <form action="<?php echo e(route('users.role', $user)); ?>" method="POST">
                <?php echo csrf_field(); ?>
                <?php echo method_field('PUT'); ?>
                <input type="hidden" name="user_id" value="<?php echo e($user->id); ?>">
                <input type="hidden" name="company_id" value="<?php echo e(auth()->user()->company->id ?? ''); ?>">

                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 lg:grid-cols-3 xl:grid-cols-5 gap-3 mb-6">
                    <?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php if($role->id != 1): ?>
                            <label class="relative cursor-pointer">
                                <input type="radio" name="role_id" value="<?php echo e($role->id); ?>" 
                                    data-role-name="<?php echo e(strtolower($role->name)); ?>" class="role-radio peer sr-only"
                                    <?php if($user->roles->contains($role)): ?> checked <?php endif; ?>>
                                <div class="flex items-center justify-center px-4 py-3 rounded-lg border-2 border-gray-200 
                                    peer-checked:border-blue-500 peer-checked:bg-blue-50 hover:border-gray-300 hover:bg-gray-50 transition-all">
                                    <span class="text-sm font-medium text-gray-700 peer-checked:text-blue-700">
                                        <?php echo e(ucfirst($role->name)); ?>

                                    </span>
                                </div>
                                <div class="absolute -top-1 -right-1 w-5 h-5 bg-blue-500 rounded-full items-center justify-center hidden peer-checked:flex">
                                    <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </label>
                        <?php endif; ?>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>

                <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors flex items-center justify-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Update Role
                </button>
            </form>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">User Information</h2>
                    <p class="text-sm text-gray-500">Update user details</p>
                </div>
            </div>

            <form action="<?php echo e(route('users.updateInfo', $user)); ?>" method="POST" id="info-form">
                <?php echo csrf_field(); ?>
                <?php echo method_field('PUT'); ?>
                <input type="hidden" name="user_id" value="<?php echo e($user->id); ?>">
                <input type="hidden" name="source_role" id="source_role" value="<?php echo e(strtolower($userRole ?? '')); ?>">

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                        <input type="text" name="name" id="info-name" class="form-input w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" 
                            value="<?php echo e($user->name); ?>" placeholder="Enter name">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" id="info-email" class="form-input w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" 
                            value="<?php echo e($user->email); ?>" placeholder="Enter email">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                        <?php if(isset($userRole) && in_array($userRole, ['accountant'])): ?>
                            <div class="flex gap-2">
                                <div class="w-28">
                                    <input type="text" name="country_code" id="info-country-code" 
                                        class="form-input w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" 
                                        value="<?php echo e($countryCode ?? ''); ?>" placeholder="+965">
                                </div>
                                <div class="flex-1">
                                    <input type="text" name="phone" id="info-phone" class="form-input w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" 
                                        value="<?php echo e($phone ?? ''); ?>" placeholder="Phone number">
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 inline-block mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Country code and phone number are separate for <?php echo e($userRole); ?>s
                            </p>
                        <?php else: ?>
                            <input type="text" name="phone" id="info-phone" class="form-input w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" 
                                value="<?php echo e($phone ?? ''); ?>" placeholder="Phone number with country code">
                        <?php endif; ?>
                    </div>

                    <div class="relative py-2">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-200"></div>
                        </div>
                        <div class="relative flex justify-center">
                            <span class="bg-white px-3 text-sm text-gray-500">Security</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            New Password
                            <span class="text-xs text-gray-400 font-normal ml-1">(leave blank to keep current)</span>
                        </label>
                        <input type="password" name="info-new-password" id="info-new-password" 
                            class="form-input w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" placeholder="••••••••">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                        <input type="password" name="info-new-password_confirmation" 
                            class="form-input w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" placeholder="••••••••">
                    </div>
                </div>

                <button type="submit" class="w-full sm:w-auto mt-6 px-6 py-2.5 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 transition-colors flex items-center justify-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Update Info
                </button>
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
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/users/edit.blade.php ENDPATH**/ ?>