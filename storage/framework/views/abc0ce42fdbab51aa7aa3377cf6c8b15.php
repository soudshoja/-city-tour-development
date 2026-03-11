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
    <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6">
        <nav class="flex items-center space-x-2 rtl:space-x-reverse text-sm mb-4 sm:mb-6 overflow-x-auto">
            <a href="<?php echo e(route('companies.list')); ?>" class="text-gray-500 hover:text-gray-700 transition whitespace-nowrap">Companies</a>
            <span class="text-gray-400">&gt;</span>
            <span class="text-blue-600 font-medium truncate max-w-[200px] sm:max-w-none"><?php echo e($company->name); ?></span>
        </nav>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sm:p-8 lg:p-10">
            <div class="flex items-center gap-3 mb-8 pb-6 border-b border-gray-100">
                <div class="w-12 h-12 sm:w-14 sm:h-14 bg-indigo-100 rounded-full flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 sm:w-7 sm:h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                </div>
                <div>
                    <h2 class="text-xl sm:text-2xl font-semibold text-gray-800">Company Information</h2>
                    <p class="text-sm sm:text-base text-gray-500">Update company details</p>
                </div>
            </div>

            <form action="<?php echo e(route('companies.update', $company->id)); ?>" method="POST">
                <?php echo csrf_field(); ?>
                <?php echo method_field('PUT'); ?>

                <div class="space-y-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
                        <input type="text" name="name" id="name" value="<?php echo e(old('name', $company->name)); ?>" required
                            class="w-full px-3 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-colors"
                            placeholder="Enter company name">
                        <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <p class="mt-1 text-sm text-red-500"><?php echo e($message); ?></p>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 sm:gap-6">
                        <div>
                            <label for="code" class="block text-sm font-medium text-gray-700 mb-1">Company Code</label>
                            <input type="text" name="code" id="code" value="<?php echo e(old('code', $company->code)); ?>" required
                                class="w-full px-3 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-colors"
                                placeholder="e.g., KWIKT2727">
                            <?php $__errorArgs = ['code'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                <p class="mt-1 text-sm text-red-500"><?php echo e($message); ?></p>
                            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>

                        <div>
                            <label for="country_id" class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                            <?php if (isset($component)) { $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $attributes; } ?>
<?php $component = App\View\Components\SearchableDropdown::resolve(['name' => 'country_id','items' => $countries->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->values(),'placeholder' => 'Select Country'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('searchable-dropdown'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\SearchableDropdown::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['selectedId' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(old('country_id', $company->country_id)),'selectedName' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($company->nationality->name ?? null)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8)): ?>
<?php $attributes = $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8; ?>
<?php unset($__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8)): ?>
<?php $component = $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8; ?>
<?php unset($__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8); ?>
<?php endif; ?>
                            <?php $__errorArgs = ['country_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                <p class="mt-1 text-sm text-red-500"><?php echo e($message); ?></p>
                            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                        <input type="text" name="phone" id="phone" value="<?php echo e(old('phone', $company->phone)); ?>"
                            class="w-full px-3 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-colors"
                            placeholder="e.g., +965 94136799">
                        <p class="mt-2 text-xs text-gray-400 flex items-center gap-1.5">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>Primary contact number for this company</span>
                        </p>
                        <?php $__errorArgs = ['phone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <p class="mt-1 text-sm text-red-500"><?php echo e($message); ?></p>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>

                    <div>
                        <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Business Address</label>
                        <textarea name="address" id="address" rows="3"
                            class="w-full px-3 py-2.5 bg-white border border-gray-300 rounded-lg text-sm text-gray-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-colors resize-none"
                            placeholder="Enter full business address"><?php echo e(old('address', $company->address)); ?></textarea>
                        <?php $__errorArgs = ['address'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <p class="mt-1 text-sm text-red-500"><?php echo e($message); ?></p>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>
                </div>

                <div class="mt-10 pt-6 border-t border-gray-100 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-3">
                    <a href="<?php echo e(route('companies.list')); ?>" 
                        class="w-full sm:w-auto px-6 py-2.5 text-center text-sm text-gray-600 hover:text-gray-800 font-medium transition border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-green-600 hover:bg-green-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow transition duration-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Update Company
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
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/companies/companiesEdit.blade.php ENDPATH**/ ?>