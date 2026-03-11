<?php if($task->originalTask?->status === 'confirmed' && $task->status === 'issued' && $task->originalTask->invoiceDetail): ?>
<?php
    $invoice = $task->originalTask->invoiceDetail->invoice;
    $invoiceDetail = $task->originalTask->invoiceDetail;
    $isPaid = $invoice->status === 'paid';
    $oldSupplierCost = $task->originalTask->total ?? 0;
    $newSupplierCost = $task->total ?? 0;
    $taskPrice = $invoiceDetail->task_price ?? 0;
    $oldProfit = $taskPrice - $oldSupplierCost;
    $newProfit = $taskPrice - $newSupplierCost;
    $profitDifference = $newProfit - $oldProfit;
    $isLoss = $newProfit < 0;
    $hasPriceChange = $oldSupplierCost != $newSupplierCost;
?>
<li x-data="{ confirmIssue: false }">
    <button
        @click="confirmIssue = !confirmIssue"
        class="flex w-full items-center gap-3 px-4 py-2 text-left transition-colors hover:bg-gray-100 dark:hover:bg-gray-800">
        <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M7 8L3 12L7 16M17 8L21 12L17 16M14 4L10 20" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <span>Switch Invoice Task</span>
    </button>

    <div x-show="confirmIssue"
        x-cloak
        x-transition.opacity.duration.200ms
        @click="confirmIssue = false"
        class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm">
    </div>

    <div x-show="confirmIssue"
        x-cloak
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div @click.stop class="relative w-full max-w-lg rounded-xl bg-white p-6 shadow-2xl dark:bg-gray-800">

            <div class="mb-5 flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/30">
                    <svg class="size-5 text-amber-600 dark:text-amber-400" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M7 8L3 12L7 16M17 8L21 12L17 16M14 4L10 20" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Switch Invoice Task</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Reference: <?php echo e($task->reference); ?></p>
                </div>
            </div>

            <div class="mb-5 rounded-lg bg-blue-50 p-4 dark:bg-blue-900/20">
                <p class="text-sm text-blue-800 dark:text-blue-300">
                    <strong>What happened:</strong> An invoice was created when this booking was in <span class="font-semibold">"Confirm"</span> status.
                    Now, the ticket has been <span class="font-semibold">"Issued"</span> and registered as a new task in the system.
                </p>
            </div>

            <div class="mb-5 space-y-3">
                <a
                    href="<?php echo e(route('invoice.details', [$invoice->agent->branch->company_id, $invoice->invoice_number])); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900/50 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors block cursor-pointer">
                    <div class="mb-2 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <svg class="size-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                            </svg>
                            <span class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Invoice</span>
                        </div>
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium <?php echo e($isPaid ? 'bg-green-100 text-green-700 dark:bg-green-800/50 dark:text-green-300' : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-800/50 dark:text-yellow-300'); ?>">
                            <?php echo e(ucfirst($invoice->status)); ?>

                        </span>
                    </div>
                    <p class="text-base font-semibold text-gray-900 dark:text-white"><?php echo e($invoice->invoice_number); ?></p>
                </a>

                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-800/50 dark:bg-red-900/20">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-xs font-medium uppercase tracking-wide text-red-600 dark:text-red-400">Currently Linked</span>
                            <span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-800/50 dark:text-red-300">
                                <?php echo e(ucfirst($task->originalTask->status)); ?>

                            </span>
                        </div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">Task #<?php echo e($task->originalTask->id); ?></p>
                        <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($task->originalTask->reference); ?></p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Cost: <span class="font-medium"><?php echo e(number_format($oldSupplierCost, 3)); ?> <?php echo e($invoice->currency ?? 'KWD'); ?></span></p>
                    </div>

                    <div class="rounded-lg border border-green-200 bg-green-50 p-3 dark:border-green-800/50 dark:bg-green-900/20">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-xs font-medium uppercase tracking-wide text-green-600 dark:text-green-400">Switch To</span>
                            <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-800/50 dark:text-green-300">
                                <?php echo e(ucfirst($task->status)); ?>

                            </span>
                        </div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">Task #<?php echo e($task->id); ?></p>
                        <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($task->reference); ?></p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Cost: <span class="font-medium"><?php echo e(number_format($newSupplierCost, 3)); ?> <?php echo e($invoice->currency ?? 'KWD'); ?></span></p>
                    </div>
                </div>

                <?php if($isPaid && $hasPriceChange): ?>
                <div class="rounded-lg border <?php echo e($isLoss ? 'border-red-300 bg-red-50 dark:border-red-700 dark:bg-red-900/30' : 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/50'); ?> p-3">
                    <p class="text-xs font-medium uppercase tracking-wide <?php echo e($isLoss ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400'); ?> mb-2">Profit Impact</p>
                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Selling Price</p>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white"><?php echo e(number_format($taskPrice, 3)); ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Old Profit</p>
                            <p class="text-sm font-semibold <?php echo e($oldProfit >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'); ?>"><?php echo e(number_format($oldProfit, 3)); ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">New Profit</p>
                            <p class="text-sm font-semibold <?php echo e($newProfit >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'); ?>"><?php echo e(number_format($newProfit, 3)); ?></p>
                        </div>
                    </div>
                    <?php if($isLoss): ?>
                    <div class="mt-3 flex items-center gap-2 rounded-lg bg-red-100 p-2 dark:bg-red-800/50">
                        <svg class="size-5 text-red-600 dark:text-red-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <p class="text-xs font-medium text-red-700 dark:text-red-300">Warning: This will result in a LOSS of <?php echo e(number_format(abs($newProfit), 3)); ?> <?php echo e($invoice->currency ?? 'KWD'); ?></p>
                    </div>
                    <?php elseif($profitDifference < 0): ?>
                    <div class="mt-3 flex items-center gap-2 rounded-lg bg-amber-100 p-2 dark:bg-amber-800/50">
                        <svg class="size-5 text-amber-600 dark:text-amber-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <p class="text-xs font-medium text-amber-700 dark:text-amber-300">Profit will decrease by <?php echo e(number_format(abs($profitDifference), 3)); ?> <?php echo e($invoice->currency ?? 'KWD'); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <p class="mb-5 text-sm text-gray-700 dark:text-gray-300">
                Do you want to switch the invoice to use the <strong class="text-green-600 dark:text-green-400">Issued</strong> task instead of the <strong class="text-red-600 dark:text-red-400">Confirm</strong> task?
            </p>

            <div class="flex items-center justify-end gap-3">
                <button
                    @click="confirmIssue = false"
                    type="button"
                    class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600 dark:focus:ring-gray-600">
                    Cancel
                </button>
                <form method="POST" action="<?php echo e(route('tasks.switchInvoice', $task)); ?>" class="inline-block">
                    <?php echo csrf_field(); ?>
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 rounded-lg <?php echo e($isPaid && $hasPriceChange && $isLoss ? 'bg-red-600 hover:bg-red-700 focus:ring-red-500' : 'bg-blue-600 hover:bg-blue-700 focus:ring-blue-500'); ?> px-4 py-2 text-sm font-medium text-white transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                        </svg>
                        <?php echo e($isPaid && $hasPriceChange && $isLoss ? 'Switch Anyway' : 'Switch Invoice'); ?>

                    </button>
                </form>
            </div>
        </div>
    </div>
</li>
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/tasks/partial/confirm-issue.blade.php ENDPATH**/ ?>