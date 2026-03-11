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


    <div>

        <!-- first row -->
        <div class="mb-5">
            <div class="flex space-x-2">
                <div class="text-4xl font-bold text-gray-800"><?php echo e($companiesCount); ?></div>
                <h3 class="text-sm font-medium text-gray-500 mt-5">Companies</h3>

                <!-- <div class="relative">
                    <div class="bg-lime-200 absolute -top-2 -right-3 text-xs font-bold text-gray-900 
                                    rounded-full px-2 py-0.5">+3
                    </div>
                </div> -->
            </div>

        </div>




        <div class="w-full flex gap-5 mb-5">
            <div class="w-[95%]">
                <!-- table -->
                <div class="overflow-x-auto bg-white rounded-lg shadow-md p-4">
                    <table class="min-w-full border-collapse">
                        <thead class="bg-gray-200 text-left text-gray-600 text-sm uppercase font-bold">
                            <tr>
                                <th class="px-4 py-3">
                                    <input type="checkbox" class="form-checkbox" />
                                </th>
                                <th class="px-4 py-3">Name</th>
                                <th class="px-4 py-3">Email</th>
                                <th class="px-4 py-3">Contact</th>
                                <th class="px-4 py-3">Code</th>
                                <th class="px-4 py-3">Region</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <?php $__currentLoopData = $companies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $company): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tbody class="text-gray-700">
                            <tr class="border-b">
                                <td class="px-4 py-3">
                                    <input type="checkbox" class="form-checkbox" />
                                </td>
                                <td class="px-4 py-3"><?php echo e($company->name); ?></td>
                                <td class="px-4 py-3"><?php echo e($company->email); ?></td>
                                <td class="px-4 py-3"><?php echo e($company->phone); ?></td>
                                <!-- code -->
                                <td class="px-4 py-3">
                                    <span class="text-xs font-semibold text-purple-700 bg-purple-100 rounded-full px-2 py-0.5"><?php echo e($company->code); ?></span>
                                </td>
                                <td class="px-4 py-3"><?php echo e($company->nationality ? $company->nationality->name : 'N/A'); ?></td>

                                <td class="px-4 py-3">
                                    <svg id="toggle-<?php echo e($company->id); ?>" class="toggle-svg cursor-pointer"
                                        viewBox="0 0 44 24" width="44" height="24"
                                        onclick="toggleStatus(<?php echo e($company->id); ?>, '<?php echo e($company->status); ?>')"
                                        data-status="<?php echo e($company->status); ?>">
                                        <rect id="rect-<?php echo e($company->id); ?>" width="44" height="24" rx="12"
                                            fill="<?php echo e($company->status == 1 ? '#00ab55' : '#ccc'); ?>"></rect>
                                        <circle id="circle-<?php echo e($company->id); ?>"
                                            cx="<?php echo e($company->status == 0 ? '32' : '12'); ?>" cy="12" r="10" fill="white">
                                        </circle>
                                    </svg>
                                </td>
                                <td class="px-4 py-3">
                                    actions
                                </td>

                            </tr>


                        </tbody>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </table>
                </div>


                <!--./ table -->
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
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/admin/companiesList.blade.php ENDPATH**/ ?>