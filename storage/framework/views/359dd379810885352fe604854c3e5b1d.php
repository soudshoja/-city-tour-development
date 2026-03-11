<?php
$typeId = optional($user->agent)->type_id;
$showProfit = true; // All types show profit
$showCommission = in_array($typeId, [2, 3, 4]); // Types 2, 3, 4 show commission
$viewType = request('view_type', 'invoice');

$title = match($typeId) {
1 => 'Profit',
2 => 'Commission & Profit',
3,4 => 'Commission & Profit',
default => 'Commission & Profit'
};
?>
<section class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-md text-gray-900 dark:text-gray-200">
    <header class="flex flex-col md:flex-row justify-between mb-4 gap-4">
        <div class="flex-1">
            <div class="flex items-center justify-between mb-2">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    <?php echo e($user->name); ?>'s <?php echo e($title); ?>

                </h2>
                
                <!-- View Type Toggle -->
                <div class="flex bg-gray-100 rounded-lg p-1">
                    <a href="<?php echo e(route('profile.edit', array_merge(request()->only(['month']), ['tab' => 'Commission', 'view_type' => 'task']))); ?>"
                        class="px-3 py-1 rounded text-sm transition-colors <?php echo e($viewType === 'task' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:text-blue-600'); ?>">
                        By Task
                    </a>
                    <a href="<?php echo e(route('profile.edit', array_merge(request()->only(['month']), ['tab' => 'Commission', 'view_type' => 'invoice']))); ?>"
                        class="px-3 py-1 rounded text-sm transition-colors <?php echo e($viewType === 'invoice' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:text-blue-600'); ?>">
                        By Invoice
                    </a>
                </div>
            </div>
            
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                <?php if($viewType === 'task'): ?>
                    Review your earned <?php echo e(strtolower($title)); ?> along with details of the associated tasks.
                <?php else: ?>
                    Review your earned <?php echo e(strtolower($title)); ?> grouped by invoices.
                <?php endif; ?>
            </p>

            <form method="GET" action="<?php echo e(route('profile.edit')); ?>" class="flex flex-wrap items-center gap-2">
                <input type="hidden" name="tab" value="Commission">
                <input type="hidden" name="view_type" value="<?php echo e($viewType); ?>">
                <label for="month" class="sr-only">Month</label>
                <div class="flex items-center gap-1">
                    <input type="month" name="month" id="month" value="<?php echo e(request('month', now()->format('Y-m'))); ?>"
                        class="bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded px-2 py-1 text-sm text-gray-900 dark:text-white">
                    <button type="submit" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm">
                        Filter
                    </button>
                    <a href="<?php echo e(route('profile.edit', ['tab' => 'Commission', 'view_type' => $viewType])); ?>" 
                        class="px-3 py-1 bg-gray-500 hover:bg-gray-600 text-white rounded text-sm">
                        Reset
                    </a>
                </div>
            </form>
        </div>
    </header>

    <?php if($commissions && $commissions->count() > 0): ?>
    
    <!-- Summary Totals -->
    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-700 dark:to-gray-600 rounded-lg p-4 mb-6 border border-blue-200 dark:border-gray-600">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-3">Summary for <?php echo e(request('month', now()->format('Y-m'))); ?></h3>
        <div class="grid grid-cols-1 md:grid-cols-<?php echo e($showCommission ? '2' : '1'); ?> gap-4">
            <?php if($showProfit): ?>
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-blue-600 dark:text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                            Total Profit
                        </p>
                        <p class="text-2xl font-bold text-blue-600 dark:text-blue-400"><?php echo e($totalProfit); ?> KWD</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if($showCommission): ?>
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-green-600 dark:text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Commission</p>
                        <p class="text-2xl font-bold text-green-600 dark:text-green-400"><?php echo e($totalCommission); ?> KWD</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="overflow-x-auto" x-data="{ openRow: null }">
        <table class="min-w-full text-sm border border-gray-300 dark:border-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                <tr>
                    <?php if($viewType === 'task'): ?>
                        <th class="py-3 px-4 border-b text-center">Task Reference</th>
                        <th class="py-3 px-4 border-b text-center">Passenger</th>
                        <?php if($showProfit): ?>
                        <th class="py-3 px-4 border-b text-center">Total Profit (KWD)</th>
                        <?php endif; ?>
                        <?php if($showCommission): ?>
                        <th class="py-3 px-4 border-b text-center">Total Commission (KWD)</th>
                        <?php endif; ?>
                        <th class="py-3 px-4 border-b text-center">Description</th>
                        <th class="py-3 px-4 border-b text-center">Date</th>
                    <?php else: ?>
                        <th class="py-3 px-4 border-b text-center">Invoice Number</th>
                        <th class="py-3 px-4 border-b text-center">Task Count</th>
                        <?php if($showProfit): ?>
                        <th class="py-3 px-4 border-b text-center">Total Profit (KWD)</th>
                        <?php endif; ?>
                        <?php if($showCommission): ?>
                        <th class="py-3 px-4 border-b text-center">Total Commission (KWD)</th>
                        <?php endif; ?>
                        <th class="py-3 px-4 border-b text-center">Invoice Date</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $commissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php if($viewType === 'task'): ?>
                    
                    <tr class="cursor-pointer text-center"
                        :class="openRow === <?php echo e($index); ?> ? 'bg-blue-50 hover:bg-gray-50 dark:bg-blue-900 hover:dark:bg-blue-800' : 'hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-200'" 
                        @click="openRow === <?php echo e($index); ?> ? openRow = null : openRow = <?php echo e($index); ?>">
                        <td class="py-3 px-4 border-b"><?php echo e($item['task_reference']); ?></td>
                        <td class="py-3 px-4 border-b"><?php echo e($item['passenger_name']); ?></td>
                        <?php if($showProfit): ?>
                        <td class="py-3 px-4 border-b text-blue-700 font-semibold">
                            <?php echo e(number_format($item['task_profit'], 3)); ?>

                        </td>
                        <?php endif; ?>
                        <?php if($showCommission): ?>
                        <td class="py-3 px-4 border-b text-green-700 font-semibold">
                            <?php echo e(number_format($item['net_commission'], 3)); ?>

                        </td>
                        <?php endif; ?>
                        <?php
                        if ($showCommission && ! $showProfit) {
                        $description = "Commission on Task {$item['task_reference']}";
                        } elseif ($showProfit && ! $showCommission) {
                        $description = "Profit on Task {$item['task_reference']}";
                        } else {
                        $description = "Commission & Profit for Task {$item['task_reference']}";
                        }
                        ?>
                        <td class="py-3 px-4 border-b"><?php echo e($description); ?></td>
                        <td class="py-3 px-4 border-b">
                            <?php echo e(\Carbon\Carbon::parse($item['transaction_date'])->format('d-m-Y')); ?>

                        </td>
                    </tr>
                    <tr x-show="openRow === <?php echo e($index); ?>" x-cloak>
                        <td colspan="<?php echo e($showCommission ? '6' : '5'); ?>" class="bg-sky-50 dark:bg-gray-900 px-6 py-4 border-b dark:border-gray-700 text-sm text-gray-700 dark:text-gray-200 rounded-b-lg shadow-inner">
                            <div class="space-y-2">
                                <div class="flex justify-between items-center">
                                    <p class="text-base font-semibold text-gray-800">Task: <?php echo e($item['task_reference']); ?></p>
                                    <a href="<?php echo e(route('invoice.details', [ 'companyId' => $item['invoice']['company_id'], 'invoiceNumber' => $item['invoice']['number']])); ?>" class="text-blue-500 hover:underline" target="_blank">
                                        View Invoice <?php echo e($item['invoice']['number']); ?>

                                    </a>
                                </div>
                                <hr class="my-2">
                                
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-1">
                                    <?php if($item['task_details']): ?>
                                    <div><strong>Client:</strong> <?php echo e($item['task_details']['client_name'] ?? 'N/A'); ?></div>
                                    <div><strong>Supplier Pay Date:</strong> 
                                        <?php echo e($item['task_details']['supplier_pay_date'] ? \Carbon\Carbon::parse($item['task_details']['supplier_pay_date'])->format('d-m-Y') : 'N/A'); ?>

                                    </div>
                                    <?php endif; ?>
                                    <div><strong>Passenger:</strong> <?php echo e($item['passenger_name']); ?></div>
                                    <div><strong>Invoice Date:</strong> <?php echo e(\Carbon\Carbon::parse($item['invoice']['date'])->format('d-m-Y')); ?></div>
                                    <div><strong>Payment Type:</strong> <?php echo e($item['invoice']['payment_type']); ?></div>
                                    <div><strong>Invoice Total Profit:</strong> <?php echo e(number_format($item['invoice']['total_profit'], 3)); ?> KWD</div>
                                </div>

                                <?php if(isset($item['task_details']['flight_details'])): ?>
                                <div class="mt-4 p-4 rounded-lg bg-sky-100 dark:bg-gray-800 shadow-inner">
                                    <h4 class="font-semibold mb-2">Flight Details</h4>
                                    <div class="grid grid-cols-2 md:grid-cols-3 gap-1 text-sm">
                                        <div><strong>Flight No:</strong> <?php echo e($item['task_details']['flight_details']['flight_number'] ?? 'N/A'); ?></div>
                                        <div><strong>From:</strong> <?php echo e($item['task_details']['flight_details']['airport_from'] ?? 'N/A'); ?></div>
                                        <div><strong>To:</strong> <?php echo e($item['task_details']['flight_details']['airport_to'] ?? 'N/A'); ?></div>
                                        <div><strong>Departure:</strong> 
                                            <?php echo e(isset($item['task_details']['flight_details']['departure_time']) ? \Carbon\Carbon::parse($item['task_details']['flight_details']['departure_time'])->format('d-m-Y H:i') : 'N/A'); ?>

                                        </div>
                                        <div><strong>Arrival:</strong> 
                                            <?php echo e(isset($item['task_details']['flight_details']['arrival_time']) ? \Carbon\Carbon::parse($item['task_details']['flight_details']['arrival_time'])->format('d-m-Y H:i') : 'N/A'); ?>

                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if(isset($item['task_details']['hotel_details'])): ?>
                                <div class="mt-4 p-4 rounded-lg bg-sky-100 dark:bg-gray-800 shadow-inner">
                                    <h4 class="font-semibold mb-2">Hotel Details</h4>
                                    <div class="grid grid-cols-2 gap-1 text-sm">
                                        <div><strong>Hotel Name:</strong> <?php echo e($item['task_details']['hotel_details']['hotel']['name'] ?? 'N/A'); ?></div>
                                        <div><strong>Check-in:</strong> 
                                            <?php echo e(isset($item['task_details']['hotel_details']['check_in']) ? \Carbon\Carbon::parse($item['task_details']['hotel_details']['check_in'])->format('d-m-Y') : 'N/A'); ?>

                                        </div>
                                        <div><strong>Check-out:</strong> 
                                            <?php echo e(isset($item['task_details']['hotel_details']['check_out']) ? \Carbon\Carbon::parse($item['task_details']['hotel_details']['check_out'])->format('d-m-Y') : 'N/A'); ?>

                                        </div>
                                        <div><strong>Nights:</strong> <?php echo e($item['task_details']['hotel_details']['nights'] ?? 'N/A'); ?></div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    
                    <tr class="cursor-pointer text-center"
                        :class="openRow === <?php echo e($index); ?> ? 'bg-blue-50 hover:bg-gray-50 dark:bg-blue-900 hover:dark:bg-blue-800' : 'hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-200'" 
                        @click="openRow === <?php echo e($index); ?> ? openRow = null : openRow = <?php echo e($index); ?>">
                        <td class="py-3 px-4 border-b">
                            <a href="<?php echo e(route('invoice.details', ['companyId' => $item['company_id'], 'invoiceNumber' => $item['invoice_number']])); ?>" class="text-blue-500 hover:underline" target="_blank">
                                <?php echo e($item['invoice_number']); ?>

                            </a>
                        </td>
                        <td class="py-3 px-4 border-b"><?php echo e($item['task_count']); ?></td>
                        <?php if($showProfit): ?>
                        <td class="py-3 px-4 border-b text-blue-700 font-semibold">
                            <?php echo e(number_format($item['total_profit'], 3)); ?>

                        </td>
                        <?php endif; ?>
                        <?php if($showCommission): ?>
                        <td class="py-3 px-4 border-b text-green-700 font-semibold">
                            <?php echo e(number_format($item['total_commission'], 3)); ?>

                        </td>
                        <?php endif; ?>
                        <td class="py-3 px-4 border-b">
                            <?php echo e(\Carbon\Carbon::parse($item['invoice_date'])->format('d-m-Y')); ?>

                        </td>
                    </tr>
                    <tr x-show="openRow === <?php echo e($index); ?>" x-cloak>
                        <td colspan="<?php echo e($showCommission ? '5' : '4'); ?>" class="bg-sky-50 dark:bg-gray-900 px-6 py-4 border-b dark:border-gray-700 text-sm text-gray-700 dark:text-gray-200 rounded-b-lg shadow-inner">
                            <div class="space-y-2">
                                <div class="flex justify-between items-center">
                                    <p class="text-base font-semibold text-gray-800">Invoice <?php echo e($item['invoice_number']); ?></p>
                                    <a href="<?php echo e(route('invoice.edit', ['companyId' => $item['company_id'], 'invoiceNumber' => $item['invoice_number']])); ?>" class="text-blue-500 hover:underline" target="_blank">Edit Invoice</a>
                                </div>
                                <hr class="my-2">
                                
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-1 mb-4">
                                    <div><strong>Invoice Date:</strong> <?php echo e(\Carbon\Carbon::parse($item['invoice_date'])->format('d-m-Y')); ?></div>
                                    <div><strong>Total Tasks:</strong> <?php echo e($item['task_count']); ?></div>
                                    <div><strong>Total Profit:</strong> <?php echo e(number_format($item['total_profit'], 3)); ?> KWD</div>
                                    <?php if($showCommission): ?>
                                    <div><strong>Total Commission:</strong> <?php echo e(number_format($item['total_commission'], 3)); ?> KWD</div>
                                    <?php endif; ?>
                                </div>

                                <div class="mt-4">
                                    <h4 class="font-semibold mb-2">Tasks in this Invoice</h4>
                                    <div class="bg-white dark:bg-gray-800 rounded border">
                                        <?php $__currentLoopData = $item['tasks']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <div class="p-3 border-b last:border-b-0 text-sm">
                                            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                                <div><strong>Task:</strong> <?php echo e($task['task_reference']); ?></div>
                                                <div><strong>Passenger:</strong> <?php echo e($task['passenger_name']); ?></div>
                                                <div><strong>Price:</strong> <?php echo e(number_format($task['task_price'], 3)); ?> KWD</div>
                                                <div><strong>Profit:</strong> <?php echo e(number_format($task['markup_price'], 3)); ?> KWD</div>
                                            </div>
                                        </div>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
        <div class="mt-6 px-6">
            <?php echo e($commissions->appends(['tab' => 'Commission', 'view_type' => $viewType, 'month' => request('month')])->links()); ?>

        </div>
    </div>
    <?php else: ?>
    <div class="text-center py-8">
        <p class="text-gray-500 dark:text-gray-400">No commission data found for the selected month.</p>
    </div>
    <?php endif; ?>
</section>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/profile/partials/commission-list.blade.php ENDPATH**/ ?>