<div id="account-<?php echo e($account->id); ?>" class="rounded-lg shadow-lg hover:shadow-xl transition-all duration-300 ease-in-out transform hover:translate-y-1" data-level="<?php echo e($account->level); ?>">
    <div class="p-4 flex justify-between items-center text-base font-semibold cursor-pointer shadow-sm hover:bg-gray-100 dark:hover:bg-gray-600 transition-all duration-300 ease-in-out rounded-t-lg"
        onclick="toggleTable('table-<?php echo e($account->id); ?>', '<?php echo e($account->id); ?>')">
        <div class="flex items-center gap-2">
            <span class="text-gray-900 dark:text-white"><?php echo e($account->name); ?></span>
            <svg id="arrow-<?php echo e($account->id); ?>" class="w-5 h-5 text-gray-400 transition-transform duration-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </div>
        <p class="<?php if($account->balance > 0): ?> text-red-500 <?php else: ?> text-green-500 <?php endif; ?>">
            <?php echo e(number_format($account->balance, 2)); ?>

        </p>
    </div>
    <div id="table-<?php echo e($account->id); ?>" class="hidden px-4 pt-4 pb-4">
        <div class="space-y-3">
            <?php if(isset($account->childAccounts) && !empty($account->childAccounts)): ?>
            <?php $__currentLoopData = $account->childAccounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $subChild): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php echo $__env->make('reports.account-child', ['account' => $subChild], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php endif; ?>

            <?php if($account->journalEntries->isEmpty() && empty($account->childAccounts)): ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left border border-gray-800 dark:border-gray-600">
                    <thead class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                        <tr>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-center">Transaction Date</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-center">Issued Date</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 w-1/6 text-center">Client Name</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 w-1/6 text-center">Reference</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 w-1/6 text-center">Status</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-center">Description</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-center">Debit</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-center">Credit</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-center">Running Balance</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-900 dark:text-gray-100">
                        <tr>
                            <td colspan="10" class="text-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-center">No transactions available</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left border border-gray-300 dark:border-gray-600">
                    <?php if($account->journalEntries->isNotEmpty()): ?>
                    <thead class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                        <tr>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 w-1/6 text-center">Transaction Date</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 w-1/6 text-center">Client Name</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 w-1/6 text-center">Reference</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 w-1/6 text-center">Status</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 w-1/4 text-center">Description</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 w-1/6 text-center">Debit</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 w-1/6 text-center">Credit</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 whitespace-nowrap text-center">Running Balance</th>
                            <th class="px-4 py-2 border border-gray-300 dark:border-gray-600 whitespace-nowrap text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-900 dark:text-gray-100">
                        <?php $__currentLoopData = $account->journalEntries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $journalEntry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php if($journalEntry->transaction !== null): ?>
                        <tr class="hover:bg-gray-200 dark:hover:bg-gray-900 transition">
                            <td class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-center">
                                <span class="text-gray-900 dark:text-white font-semibold">
                                    <?php if($journalEntry->transaction->transaction_date): ?>
                                        <?php echo e($journalEntry->transaction->formatted_date); ?>

                                    <?php else: ?>
                                        Not Set
                                    <?php endif; ?>
                                </span>  
                            </td>
                            <td class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-center">
                                <?php if($journalEntry->task && $journalEntry->task->client_name): ?>
                                    <?php echo e($journalEntry->task->client_name); ?>

                                <?php else: ?>
                                    Not Set
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-center">
                                <?php if($journalEntry->task && $journalEntry->task->reference): ?>
                                    <?php echo e($journalEntry->task->reference); ?>

                                <?php else: ?>
                                    Not Set
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-center">
                                <?php if($journalEntry->task && $journalEntry->task->status): ?>
                                    <?php echo e(ucfirst($journalEntry->task->status)); ?>

                                <?php else: ?>
                                    Not Set
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-center">
                                <?php if($journalEntry->task): ?>
                                    <?php if($journalEntry->task->type === 'flight'): ?>
                                        <div class="flex justify-between items-center gap-4 text-center text-sm">
                                            <div class="flex flex-col items-center">
                                                <span class="font-bold text-base">
                                                    <?php echo e($journalEntry->task->flightDetails ? $journalEntry->task->flightDetails->departure_time : '-'); ?>

                                                </span>
                                                <span class="text-gray-600 text-sm">
                                                    <?php echo e($journalEntry->task->flightDetails ? $journalEntry->task->flightDetails->airport_from : '-'); ?>

                                                </span>
                                            </div>
                                            <div class="text-blue-700 text-lg"> ✈ </div>
                                            <div class="flex flex-col items-center">
                                                <span class="font-bold text-base">
                                                    <?php echo e($journalEntry->task->flightDetails ? $journalEntry->task->flightDetails->arrival_time : '-'); ?>

                                                </span>
                                                <span class="text-gray-600 text-sm">
                                                    <?php echo e($journalEntry->task->flightDetails ? $journalEntry->task->flightDetails->airport_to : '-'); ?>

                                                </span>
                                            </div>
                                        </div>
                                    <?php elseif($journalEntry->task->type === 'hotel'): ?>
                                        <div class="flex items-start gap-2 text-sm text-left">
                                            <div class="pt-1">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path d="M8 21V7a1 1 0 011-1h6a1 1 0 011 1v14M3 21v-4a1 1 0 011-1h4a1 1 0 011 1v4m10 0v-6a1 1 0 011-1h2a1 1 0 011 1v6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            </div>
                                            <div class="flex flex-col truncate">
                                                <div class="truncate max-w-[140px]" title="<?php echo e($journalEntry->task->hotelDetails->hotel->name ?? '-'); ?>">
                                                    <?php echo e($journalEntry->task->hotelDetails->hotel->name ?? '-'); ?>

                                                </div>
                                                <div class="text-sm text-gray-500 whitespace-nowrap">
                                                    <?php echo e($journalEntry->task->hotelDetails->check_in ?? '-'); ?> - <?php echo e($journalEntry->task->hotelDetails->check_out ?? '-'); ?>

                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div><?php echo e($journalEntry->task->additional_info ?? '-'); ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="text-gray-500 italic">No task linked</div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-center"><?php echo e(number_format($journalEntry->debit, 2)); ?></td>
                            <td class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-center"><?php echo e(number_format($journalEntry->credit, 2)); ?></td>
                            <td class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-center"><?php echo e(number_format($journalEntry->balance, 2)); ?></td>
                            </td>
                            <td class="px-4 py-2 border border-gray-300 dark:border-gray-600">
                                <a href="<?php echo e(route('journal-entries.index', $journalEntry->transaction->id)); ?>"
                                    class="text-center inline-flex items-center bg-blue-500 hover:bg-blue-700 text-white font-semibold py-1 px-2 rounded-lg transition duration-300 ease-in-out transform hover:scale-105"
                                    target="_blank" rel="noopener noreferrer" title="View Transaction">    
                                    View Transaction
                                </a>                            
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                    <?php endif; ?>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function applyBackgroundClass(accountDiv, level) {
        const lightClasses = [
            'bg-white',
            'bg-gray-100',
            'bg-gray-200',
            'bg-gray-300',
            'bg-gray-400'
        ];

        const darkClasses = [
            'dark:bg-gray-800',
            'dark:bg-gray-700',
            'dark:bg-gray-600',
            'dark:bg-gray-500',
            'dark:bg-gray-400'
        ];

        const lightClass = lightClasses[Math.min(level - 3, lightClasses.length - 3)] || 'bg-gray-100';
        const darkClass = darkClasses[Math.min(level - 3, darkClasses.length - 3)] || 'dark:bg-gray-700';
        accountDiv.classList.add(lightClass, darkClass);
    }

    document.addEventListener("DOMContentLoaded", function() {
        let accountDivs = document.querySelectorAll('[id^="account-"]');

        accountDivs.forEach(function(accountDiv) {
            let level = accountDiv.getAttribute('data-level');
            applyBackgroundClass(accountDiv, level);
        });
    });
</script><?php /**PATH /home/soudshoja/soud-laravel/resources/views/reports/account-child.blade.php ENDPATH**/ ?>