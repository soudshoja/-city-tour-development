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
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Bulk Invoice Upload</h1>
                <p class="text-gray-600">Upload an Excel file to create multiple invoices at once</p>
            </div>

            <!-- Instructions Card -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-8">
                <h2 class="text-lg font-semibold text-blue-900 mb-4">How it works:</h2>
                <ol class="list-decimal list-inside space-y-2 text-blue-800">
                    <li>Download the Excel template using the button below</li>
                    <li>Fill in your task details (one task per row)</li>
                    <li>Upload the completed Excel file</li>
                    <li>Preview and approve the invoices to be created</li>
                </ol>
            </div>

            <!-- Download Template Button -->
            <div class="mb-8">
                <a href="<?php echo e(route('bulk-invoices.template')); ?>" 
                   class="inline-flex items-center px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg shadow transition">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Download Excel Template
                </a>
            </div>

            <!-- Upload Form -->
            <div class="bg-white rounded-lg shadow-lg p-8">
                <h2 class="text-xl font-semibold text-gray-900 mb-6">Upload Completed Excel File</h2>

                <form action="<?php echo e(route('bulk-invoices.upload')); ?>" method="POST" enctype="multipart/form-data" class="space-y-6">
                    <?php echo csrf_field(); ?>

                    <!-- Agent Selection (for non-agent users) -->
                    <?php if(!$isAgent): ?>
                    <div>
                        <?php if (isset($component)) { $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $attributes; } ?>
<?php $component = App\View\Components\SearchableDropdown::resolve(['label' => 'Select Agent *','name' => 'agent_id','items' => json_encode($agents),'placeholder' => 'Select which agent these invoices should be created for','maxResults' => 10] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('searchable-dropdown'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\SearchableDropdown::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
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
                        <p class="mt-2 text-sm text-gray-500">Select which agent these invoices should be created for</p>

                        <?php $__errorArgs = ['agent_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <p class="mt-2 text-sm text-red-600"><?php echo e($message); ?></p>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>
                    <?php endif; ?>

                    <!-- File Upload -->
                    <div>
                        <label for="file" class="block text-sm font-medium text-gray-700 mb-2">
                            Excel File *
                        </label>
                        <input type="file" 
                               id="file" 
                               name="file" 
                               accept=".xlsx,.xls"
                               required
                               class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 p-3">
                        <p class="mt-2 text-sm text-gray-500">Accepted formats: .xlsx, .xls</p>
                        
                        <?php $__errorArgs = ['file'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <p class="mt-2 text-sm text-red-600"><?php echo e($message); ?></p>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>

                    <!-- Error Messages -->
                    <?php if($errors->any()): ?>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                            <h3 class="text-sm font-medium text-red-800 mb-2">Please fix the following errors:</h3>
                            <ul class="list-disc list-inside text-sm text-red-700">
                                <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <li><?php echo e($error); ?></li>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <!-- Submit Button -->
                    <div class="flex items-center justify-end space-x-4">
                        <a href="<?php echo e(route('dashboard')); ?>" 
                           class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium rounded-lg transition">
                            Cancel
                        </a>
                        <button type="submit" 
                                class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg shadow transition flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                            </svg>
                            Upload & Preview
                        </button>
                    </div>
                </form>
            </div>

            <!-- Help Section -->
            <div class="mt-8 bg-gray-50 rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Need Help?</h3>
                <div class="space-y-2 text-sm text-gray-700">
                    <p><strong>Common Issues:</strong></p>
                    <ul class="list-disc list-inside ml-4 space-y-1">
                        <li>Make sure all required columns are filled</li>
                        <li>Client phone numbers must match existing clients in the system</li>
                        <li>Task types must be one of: flight, hotel, visa, insurance, etc.</li>
                        <li>Amounts should be numbers without currency symbols</li>
                    </ul>
                </div>
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
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/bulk-invoice/upload.blade.php ENDPATH**/ ?>