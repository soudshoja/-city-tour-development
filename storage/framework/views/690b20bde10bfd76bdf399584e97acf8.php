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

        <div class="flex flex-col lg:flex-row gap-4 lg:gap-6">
            <div class="flex-1 order-2 lg:order-1">
                <div class="bg-white rounded-lg shadow-sm border border-gray-100">
                    <div class="p-4 sm:p-5 border-b border-gray-100">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-lg font-semibold text-gray-800">Agents</h2>
                                    <p class="text-xs text-gray-500"><?php echo e($company->agents->count()); ?> total agents</p>
                                </div>
                            </div>
                            <a href="<?php echo e(route('users.create', ['company_id' => $company->id, 'role' => 'agent'])); ?>"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-gray-900 hover:bg-gray-800 text-white text-sm font-medium rounded-lg transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                <span>Add Agent</span>
                            </a>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <div class="max-h-[400px] sm:max-h-[500px] overflow-y-auto">
                            <table class="w-full">
                                <thead class="sticky top-0 bg-gray-50 border-b border-gray-100">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Name</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Amadeus ID</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Type</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php $__empty_1 = true; $__currentLoopData = $company->agents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $agent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                    <tr class="hover:bg-gray-50 transition px-4 py-3">
                                        <td>
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                                    <span class="text-xs font-medium text-blue-600"><?php echo e(strtoupper(substr($agent->name, 0, 2))); ?></span>
                                                </div>
                                                <div class="min-w-0">
                                                    <p class="text-sm font-medium text-gray-900 truncate"><?php echo e($agent->name); ?></p>
                                                    <p class="text-xs text-gray-500"><?php echo e($agent->phone_number ?? '—'); ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-sm text-gray-600">
                                            <?php echo e($agent->amadeus_id ?? '—'); ?>

                                        </td>
                                        <td>
                                            <?php if($agent->agentType): ?>
                                                <?php
                                                    $typeName = strtolower($agent->agentType->name ?? '');
                                                    $typeColors = [
                                                        'salary' => 'bg-gray-50 text-gray-700 border-gray-300',
                                                        'commission' => 'bg-blue-50 text-blue-700 border-blue-300',
                                                        'both-a' => 'bg-teal-50 text-teal-700 border-teal-300',
                                                        'both-b' => 'bg-amber-50 text-amber-700 border-amber-300',
                                                    ];
                                                    $colorClass = $typeColors[$typeName] ?? 'bg-gray-50 text-gray-700 border-gray-200';
                                                ?>
                                                <span class="inline-flex px-2 py-1 text-xs font-medium rounded border <?php echo e($colorClass); ?>">
                                                    <?php echo e($agent->agentType->name); ?>

                                                </span>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-sm">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                    <tr>
                                        <td colspan="4" class="px-4 py-12 text-center">
                                            <div class="flex flex-col items-center">
                                                <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center mb-3">
                                                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                                    </svg>
                                                </div>
                                                <p class="text-sm text-gray-500 mb-3">No agents found</p>
                                                <a href="<?php echo e(route('users.create', ['company_id' => $company->id, 'role' => 'agent'])); ?>"
                                                    class="text-sm text-blue-600 hover:text-blue-700 font-medium">
                                                    Add your first agent →
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="w-full lg:w-80 xl:w-96 flex-shrink-0 order-1 lg:order-2">
                <div class="bg-gradient-to-br from-[#4361ee] to-[#160f6b] rounded-lg shadow-sm overflow-hidden">
                    <div class="p-4 sm:p-5 border-b border-white/10">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-lg bg-white/20 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 sm:w-6 sm:h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="text-white font-semibold text-base sm:text-lg truncate"><?php echo e($company->name); ?></h3>
                                    <p class="text-white/70 text-xs sm:text-sm"><?php echo e($company->code); ?></p>
                                </div>
                            </div>
                            <a href="<?php echo e(route('companies.edit', $company->id)); ?>"
                                class="w-9 h-9 flex items-center justify-center rounded-lg bg-white/20 hover:bg-white/30 transition flex-shrink-0">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                </svg>
                            </a>
                        </div>
                    </div>

                    <div class="p-4 sm:p-5 space-y-3 sm:space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-white/90 text-sm">IATA Code</span>
                            <span class="text-white text-sm font-medium"><?php echo e($company->iata_code ?? '—'); ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-white/90 text-sm">Email</span>
                            <span class="text-white text-sm font-medium truncate ml-4 max-w-[180px]"><?php echo e($company->email ?? '—'); ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-white/90 text-sm">Phone</span>
                            <span class="text-white text-sm font-medium"><?php echo e($company->phone ?? '—'); ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-white/90 text-sm">Country</span>
                            <span class="text-white text-sm font-medium"><?php echo e($company->nationality->name ?? '—'); ?></span>
                        </div>
                        <div class="pt-2 border-t border-white/10">
                            <span class="text-white/90 text-sm block mb-1">Address</span>
                            <span class="text-white text-sm leading-relaxed"><?php echo e($company->address ?? '—'); ?></span>
                        </div>
                    </div>
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
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/companies/show.blade.php ENDPATH**/ ?>