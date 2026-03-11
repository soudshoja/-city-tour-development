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
    <div class="container mx-auto p-4">
        <h1 class="text-center font-semibold text-xl mb-4">Ledger</h1>

        <!-- Breadcrumb Navigation -->
        <nav class="mb-6">
            <ul class="flex space-x-2 rtl:space-x-reverse text-base md:text-lg sm:text-sm justify-center">
                <li>
                    <a href="<?php echo e(route('coa.index')); ?>" class="customBlueColor hover:underline">Chart of Account</a>
                </li>
                <li class="before:content-['/'] before:mr-1">
                    <a href="<?php echo e(route('coa.transaction')); ?>" class="customBlueColor hover:underline">Transactions</a>
                </li>
                <li class="before:content-['/'] before:mr-1">
                    <span>Ledger</span>
                </li>
            </ul>
        </nav>

        <!-- Journal Entries Table -->
        <div class="bg-white p-4 rounded shadow">
            <?php if($journalEntries->isEmpty()): ?>
                <p class="text-center text-gray-600">No journal entries found.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full border text-sm">
                        <thead>
                            <tr class="bg-gray-200 text-left text-sm text-gray-700">
                                <th class="py-2 px-4 text-center">Transaction ID</th>
                                <th class="py-2 px-4 text-center">Date</th>
                                <th class="py-2 px-4 text-left">Description</th>
                                <th class="py-2 px-4 text-center">Account</th>
                                <th class="py-2 px-4 text-center">Debit</th>
                                <th class="py-2 px-4 text-center">Credit</th>
                                <th class="py-2 px-4 text-center">Running Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $__currentLoopData = $journalEntries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr class="border-t hover:bg-gray-50">
                                    <td class="py-2 px-4 text-center font-medium">
                                        <?php echo e($entry->transaction_id); ?>

                                    </td>
                                    <td class="py-2 px-4 text-center">
                                        <?php echo e(\Carbon\Carbon::parse($entry->transaction_date ?? $entry->transaction?->transaction_date)?->format('Y-m-d') ?? 'N/A'); ?>

                                    </td>
                                    <td class="py-2 px-4 text-left">
                                        <?php echo e($entry->description ?? '-'); ?>

                                    </td>
                                    <td class="py-2 px-4 text-center">
                                        <a href="<?php echo e(route('journal-entries.show', ['accountId' => $entry->account->id])); ?>"
                                           class="text-blue-600 hover:underline">
                                            <?php echo e($entry->account->name); ?>

                                        </a>
                                    </td>
                                    <td class="py-2 px-4 text-center">
                                        <?php echo e(number_format($entry->debit, 2)); ?>

                                    </td>
                                    <td class="py-2 px-4 text-center">
                                        <?php echo e(number_format($entry->credit, 2)); ?>

                                    </td>
                                    <td class="py-2 px-4 text-center">
                                        <?php echo e($entry->running_balance !== null ? number_format($entry->running_balance, 2) : 'N/A'); ?>

                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
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
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/journal_entries/index.blade.php ENDPATH**/ ?>