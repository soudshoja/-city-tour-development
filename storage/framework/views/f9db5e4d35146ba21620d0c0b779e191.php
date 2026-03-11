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
    <div class="flex justify-between items-center gap-5 my-3">
        <div class="flex items-center gap-5">
            <h2 class="text-3xl font-bold">Companies</h2>
            <div data-tooltip="Number of companies"
                class="relative w-12 h-12 flex items-center justify-center DarkBGcolor rounded-full shadow-sm">
                <span class="text-xl font-bold text-white"><?php echo e($companies->total()); ?></span>
            </div>
        </div>
        <div class="flex items-center gap-5">
            <div data-tooltip-left="Reload"
                class="rotate refresh-icon relative w-12 h-12 flex items-center justify-center bg-[#b1c0db] hover:bg-gray-300 rounded-full shadow-sm cursor-pointer"
                onclick="window.location.reload()">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                    <path fill="currentColor"
                        d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.66 7.66 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.16 9.16 0 0 0 12.08 2.25" />
                    <path fill="currentColor"
                        d="M20.841 10.467a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.381 3.83-3.651 6.833-7.644 6.833a7.7 7.7 0 0 1-6.565-3.644a.75.75 0 1 0-1.276.788a9.2 9.2 0 0 0 7.84 4.356c4.809 0 8.766-3.66 9.151-8.333H22a.75.75 0 0 0 .527-1.284z"
                        opacity=".5" />
                </svg>
            </div>
            <a href="<?php echo e(route('users.create', ['openForm' => 'companyForm'])); ?>">
                <div data-tooltip-left="Create new company"
                    class="relative w-12 h-12 flex items-center justify-center btn-success rounded-full shadow-sm cursor-pointer">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#fff" d="M16 8h-2v3h-3v2h3v3h2v-3h3v-2h-3M2 12c0-2.79 1.64-5.2 4-6.32V3.5C2.5 4.76 0 8.09 0 12s2.5 7.24 6 8.5v-2.18C3.64 17.2 2 14.79 2 12m13-9c-4.96 0-9 4.04-9 9s4.04 9 9 9s9-4.04 9-9s-4.04-9-9-9m0 16c-3.86 0-7-3.14-7-7s3.14-7 7-7s7 3.14 7 7s-3.14 7-7 7" />
                    </svg>
                </div>
            </a>
        </div>
    </div>

    <div class="panel rounded-lg">
        <?php if (isset($component)) { $__componentOriginal9b33c063a2222f59546ad2a2a9a94bc6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9b33c063a2222f59546ad2a2a9a94bc6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.search','data' => ['action' => route('companies.list'),'searchParam' => 'q','placeholder' => 'Quick search for company']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('search'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['action' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('companies.list')),'searchParam' => 'q','placeholder' => 'Quick search for company']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9b33c063a2222f59546ad2a2a9a94bc6)): ?>
<?php $attributes = $__attributesOriginal9b33c063a2222f59546ad2a2a9a94bc6; ?>
<?php unset($__attributesOriginal9b33c063a2222f59546ad2a2a9a94bc6); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9b33c063a2222f59546ad2a2a9a94bc6)): ?>
<?php $component = $__componentOriginal9b33c063a2222f59546ad2a2a9a94bc6; ?>
<?php unset($__componentOriginal9b33c063a2222f59546ad2a2a9a94bc6); ?>
<?php endif; ?>

        <div class="dataTable-wrapper mt-4">
            <div class="dataTable-container h-max">
                <table class="table-hover dataTable-table">
                    <thead>
                        <tr class="p-3 text-left text-md font-bold text-gray-500">
                            <th>Name</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>Code</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $companies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $company): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr class="transition-colors duration-150 p-3 text-sm font-semibold text-gray-600">
                            <td><?php echo e($company->name); ?></td>
                            <td><?php echo e($company->email); ?></td>
                            <td><?php echo e($company->phone ?? 'N/A'); ?></td>
                            <td>
                                <span class="whitespace-nowrap px-2 py-1 rounded text-xs font-medium border border-blue-500 text-blue-500 bg-blue-50">
                                    <?php echo e($company->code); ?>

                                </span>
                            </td>
                            <td class="text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('view', $company)): ?>
                                    <a data-tooltip-left="View company" href="<?php echo e(route('companies.show', $company->id)); ?>" class="text-sm font-medium">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                            viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z" />
                                            <circle cx="12" cy="12" r="3" />
                                        </svg>
                                    </a>
                                    <?php endif; ?>
                                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('update', $company)): ?>
                                    <a data-tooltip-left="Edit company" href="<?php echo e(route('companies.edit', $company->id)); ?>" class="text-sm font-medium">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                                            <path fill="none" stroke="#00ab55" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                d="m4.144 16.735l.493-3.425a.97.97 0 0 1 .293-.587l9.665-9.664a1.03 1.03 0 0 1 .973-.281a5.1 5.1 0 0 1 2.346 1.372a5.1 5.1 0 0 1 1.384 2.346a1.07 1.07 0 0 1-.282.973l-9.664 9.664a1.17 1.17 0 0 1-.598.294l-3.437.492a1.044 1.044 0 0 1-1.173-1.184m8.633-11.846l4.41 4.398M3.79 21.25h16.42"
                                                opacity=".5" />
                                        </svg>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="5" class="text-center p-3 text-sm font-semibold text-gray-600">
                                No companies found
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (isset($component)) { $__componentOriginal41032d87daf360242eb88dbda6c75ed1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal41032d87daf360242eb88dbda6c75ed1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.pagination','data' => ['data' => $companies]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('pagination'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['data' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($companies)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal41032d87daf360242eb88dbda6c75ed1)): ?>
<?php $attributes = $__attributesOriginal41032d87daf360242eb88dbda6c75ed1; ?>
<?php unset($__attributesOriginal41032d87daf360242eb88dbda6c75ed1); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal41032d87daf360242eb88dbda6c75ed1)): ?>
<?php $component = $__componentOriginal41032d87daf360242eb88dbda6c75ed1; ?>
<?php unset($__componentOriginal41032d87daf360242eb88dbda6c75ed1); ?>
<?php endif; ?>
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
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/companies/list.blade.php ENDPATH**/ ?>