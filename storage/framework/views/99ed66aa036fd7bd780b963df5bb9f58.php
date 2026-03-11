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

        
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">WhatsApp AI &mdash; DOTW Audit Logs</h1>
            <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">Live log of all DOTW hotel API operations linked to WhatsApp conversations</p>
        </div>

        
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6">
            <div class="flex flex-wrap gap-3 items-end">

                
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Operation</label>
                    <select wire:model.live="filterOperation"
                        class="border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 text-sm bg-white dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">All</option>
                        <option value="search">search</option>
                        <option value="rates">rates</option>
                        <option value="block">block</option>
                        <option value="book">book</option>
                    </select>
                </div>

                
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Message ID</label>
                    <input type="text" wire:model.live.debounce.400ms="filterMessageId" placeholder="Search message ID..."
                        class="border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 text-sm bg-white dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 w-48" />
                </div>

                
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">From</label>
                    <input type="date" wire:model.live="filterDateFrom"
                        class="border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 text-sm bg-white dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500" />
                </div>

                
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">To</label>
                    <input type="date" wire:model.live="filterDateTo"
                        class="border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 text-sm bg-white dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500" />
                </div>

                
                <?php if($isSuperAdmin): ?>
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Company ID</label>
                    <input type="text" wire:model.live.debounce.400ms="filterCompanyId" placeholder="Company ID..."
                        class="border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 text-sm bg-white dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 w-32" />
                </div>
                <?php endif; ?>

                
                <div>
                    <button wire:click="resetFilters"
                        class="px-4 py-2 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 text-sm rounded-md hover:bg-gray-300 dark:hover:bg-gray-500 transition-colors">
                        Reset Filters
                    </button>
                </div>

            </div>
        </div>

        
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <?php if($isSuperAdmin): ?>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">ID</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Company</th>
                            <?php endif; ?>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Message ID</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Quote ID</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Operation</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Payloads</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Created</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">

                        <?php $__empty_1 = true; $__currentLoopData = $logs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">

                                <?php if($isSuperAdmin): ?>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300"><?php echo e($log->id); ?></td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300"><?php echo e($log->company_id ?? '—'); ?></td>
                                <?php endif; ?>

                                <td class="px-4 py-3 font-mono text-gray-700 dark:text-gray-300"
                                    title="<?php echo e($log->resayil_message_id); ?>">
                                    <?php echo e($log->resayil_message_id ? \Illuminate\Support\Str::limit($log->resayil_message_id, 24, '…') : '—'); ?>

                                </td>

                                <td class="px-4 py-3 font-mono text-gray-700 dark:text-gray-300"
                                    title="<?php echo e($log->resayil_quote_id); ?>">
                                    <?php echo e($log->resayil_quote_id ? \Illuminate\Support\Str::limit($log->resayil_quote_id, 24, '…') : '—'); ?>

                                </td>

                                <td class="px-4 py-3">
                                    <?php
                                        $badgeClass = match($log->operation_type) {
                                            'search' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                            'rates'  => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                            'block'  => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
                                            'book'   => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                            default  => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
                                        };
                                    ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold <?php echo e($badgeClass); ?>">
                                        <?php echo e($log->operation_type); ?>

                                    </span>
                                </td>

                                <td class="px-4 py-3">
                                    <button wire:click="toggleRow(<?php echo e($log->id); ?>)"
                                        class="px-3 py-1 text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                                        <?php echo e($expandedRow === $log->id ? 'Hide' : 'View'); ?>

                                    </button>
                                </td>

                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                    <?php echo e($log->created_at ? $log->created_at->format('Y-m-d H:i:s') : '—'); ?>

                                </td>

                            </tr>

                            
                            <?php if($expandedRow === $log->id): ?>
                            <tr class="bg-gray-50 dark:bg-gray-900">
                                <td colspan="<?php echo e($isSuperAdmin ? 7 : 5); ?>" class="px-4 py-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1 uppercase tracking-wider">Request Payload</p>
                                            <pre class="bg-gray-900 text-green-400 text-xs p-3 rounded overflow-auto max-h-80"><?php echo e(json_encode($log->request_payload, JSON_PRETTY_PRINT)); ?></pre>
                                        </div>
                                        <div>
                                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1 uppercase tracking-wider">Response Payload</p>
                                            <pre class="bg-gray-900 text-green-400 text-xs p-3 rounded overflow-auto max-h-80"><?php echo e(json_encode($log->response_payload, JSON_PRETTY_PRINT)); ?></pre>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>

                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr>
                                <td colspan="<?php echo e($isSuperAdmin ? 7 : 5); ?>" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                                    No audit logs yet. Logs appear here after DOTW GraphQL operations are executed.
                                </td>
                            </tr>
                        <?php endif; ?>

                    </tbody>
                </table>
            </div>

            
            <?php if($logs->hasPages()): ?>
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                <?php echo e($logs->links()); ?>

            </div>
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
<?php endif; ?>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/livewire/admin/dotw-audit-log-index.blade.php ENDPATH**/ ?>