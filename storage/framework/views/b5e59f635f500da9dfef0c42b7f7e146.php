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
        <div id="receivables" class="tab-content">
            <div class="text-center font-bold text-2xl mb-6">
                <h1>Receivable Details</h1>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-md border border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold mb-4 text-gray-800 dark:text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        List of Receivable Record
                    </h2>

                    <div class="max-h-[calc(100vh-200px)] overflow-y-auto overflow-x-auto border border-gray-200 dark:border-gray-600 rounded-lg">
                        <?php if($JournalEntrysReceivable->isNotEmpty()): ?>
                            <?php $__currentLoopData = $JournalEntrysReceivable; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type => $ledgers): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <div class="sticky top-0 bg-green-50 dark:bg-green-900/30 px-4 py-2 border-b border-gray-200 dark:border-gray-600">
                                    <h3 class="text-md font-bold text-green-600 dark:text-green-400"><?php echo e(ucfirst($type)); ?></h3>
                                </div>

                                <div class="hidden sm:block">
                                    <table class="w-full text-sm">
                                        <thead class="bg-gray-50 dark:bg-gray-700 sticky top-10">
                                            <tr class="border-b border-gray-200 dark:border-gray-600">
                                                <th width="40%" class="text-left py-3 px-3 font-medium text-gray-600 dark:text-gray-300">Description</th>
                                                <th width="12%" class="text-right py-3 px-3 font-medium text-gray-600 dark:text-gray-300">Debit</th>
                                                <th width="12%" class="text-right py-3 px-3 font-medium text-gray-600 dark:text-gray-300">Credit</th>
                                                <th width="12%" class="text-right py-3 px-3 font-medium text-gray-600 dark:text-gray-300">Balance</th>
                                                <th width="24%" class="text-right py-3 px-3 font-medium text-gray-600 dark:text-gray-300">Agent/Client</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                            <?php $__currentLoopData = $ledgers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ledger): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                                    <td class="py-3 px-3">
                                                        <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($ledger->transaction_date); ?></p>
                                                        <p class="text-sm text-gray-800 dark:text-gray-200 mt-1"><?php echo e($ledger->description); ?></p>
                                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                            Ref: 
                                                            <?php if(!empty($ledger->type_reference_id)): ?>
                                                                <span class="text-blue-600 dark:text-blue-400"><?php echo e($ledger->referenceAccount->name ?? 'N/A'); ?></span>
                                                            <?php elseif($ledger->invoice && $ledger->invoice->invoice_number): ?>
                                                                <span class="text-blue-600 dark:text-blue-400"><?php echo e($ledger->invoice->invoice_number); ?></span>
                                                                <a target="_blank"
                                                                    href="<?php echo e(route('invoice.show', ['companyId' => $ledger->company_id, 'invoiceNumber' => $ledger->invoice->invoice_number])); ?>"
                                                                    class="text-blue-500 hover:text-blue-700 ml-1">🔍</a>
                                                            <?php else: ?>
                                                                <span class="text-gray-400">N/A</span>
                                                            <?php endif; ?>
                                                        </p>
                                                    </td>
                                                    <td class="py-3 px-3 text-right text-red-600 dark:text-red-400 font-medium">
                                                        <?php echo e(number_format($ledger->debit, 2)); ?>

                                                    </td>
                                                    <td class="py-3 px-3 text-right text-green-600 dark:text-green-400 font-medium">
                                                        <?php echo e(number_format($ledger->credit, 2)); ?>

                                                    </td>
                                                    <td class="py-3 px-3 text-right font-bold text-gray-800 dark:text-gray-200">
                                                        <?php if($ledger->balance > 0): ?>
                                                            -<?php echo e(number_format($ledger->balance, 2)); ?>

                                                        <?php elseif($ledger->balance < 0): ?>
                                                            <?php echo e(number_format(abs($ledger->balance), 2)); ?>

                                                        <?php else: ?>
                                                            <?php echo e(number_format($ledger->balance, 2)); ?>

                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="py-3 px-3 text-right text-gray-600 dark:text-gray-400">
                                                        <?php echo e($ledger->name ?? 'N/A'); ?>

                                                    </td>
                                                </tr>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="sm:hidden divide-y divide-gray-100 dark:divide-gray-700">
                                    <?php $__currentLoopData = $ledgers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ledger): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <div class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                            <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($ledger->transaction_date); ?></p>
                                            <p class="text-sm text-gray-800 dark:text-gray-200 mt-1"><?php echo e($ledger->description); ?></p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                Ref: 
                                                <?php if(!empty($ledger->type_reference_id)): ?>
                                                    <span class="text-blue-600"><?php echo e($ledger->referenceAccount->name ?? 'N/A'); ?></span>
                                                <?php elseif($ledger->invoice && $ledger->invoice->invoice_number): ?>
                                                    <span class="text-blue-600"><?php echo e($ledger->invoice->invoice_number); ?></span>
                                                    <a target="_blank" href="<?php echo e(route('invoice.show', ['companyId' => $ledger->company_id, 'invoiceNumber' => $ledger->invoice->invoice_number])); ?>" class="text-blue-500 ml-1">🔍</a>
                                                <?php else: ?>
                                                    <span class="text-gray-400">N/A</span>
                                                <?php endif; ?>
                                            </p>
                                            <div class="flex justify-between items-center mt-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                                                <div class="flex gap-4 text-sm">
                                                    <span class="text-red-600 font-medium">D: <?php echo e(number_format($ledger->debit, 2)); ?></span>
                                                    <span class="text-green-600 font-medium">C: <?php echo e(number_format($ledger->credit, 2)); ?></span>
                                                </div>
                                                <div class="text-right">
                                                    <p class="font-bold text-gray-800 dark:text-gray-200">
                                                        <?php if($ledger->balance > 0): ?>
                                                            -<?php echo e(number_format($ledger->balance, 2)); ?>

                                                        <?php elseif($ledger->balance < 0): ?>
                                                            <?php echo e(number_format(abs($ledger->balance), 2)); ?>

                                                        <?php else: ?>
                                                            <?php echo e(number_format($ledger->balance, 2)); ?>

                                                        <?php endif; ?>
                                                    </p>
                                                    <p class="text-xs text-gray-500"><?php echo e($ledger->name ?? 'N/A'); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <?php else: ?>
                            <div class="p-8 text-center">
                                <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <p class="text-gray-500 dark:text-gray-400 mt-2">No transactions found.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-md border border-gray-200 dark:border-gray-700 h-fit">
                    <h2 class="text-lg font-semibold mb-4 text-gray-800 dark:text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Add Receivable Record
                    </h2>

                    <?php if($errors->any()): ?>
                        <div class="mb-4 p-4 text-red-800 bg-red-100 dark:bg-red-900/30 dark:text-red-400 rounded-lg">
                            <ul class="list-disc list-inside space-y-1">
                                <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <li><?php echo e($error); ?></li>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form action="<?php echo e(route('receivable-details.receivable-store')); ?>" method="POST" class="space-y-6">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="company_id" value="<?php echo e($companyId); ?>">

                        <div>
                            <label class="block font-medium text-sm mb-2 text-gray-700 dark:text-gray-300">
                                Branch Name <span class="text-red-500">*</span>
                            </label>
                            <?php if (isset($component)) { $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $attributes; } ?>
<?php $component = App\View\Components\SearchableDropdown::resolve(['name' => 'branch_id','items' => $branches->map(fn($b) => [
                                    'id' => $b->id, 
                                    'name' => $b->name . ($b->address ? ' (' . $b->address . ')' : '')
                                ])->values(),'placeholder' => 'Select Branch'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('searchable-dropdown'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\SearchableDropdown::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['selectedId' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(null)]); ?>
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
                        </div>

                        <div>
                            <label class="block font-medium text-sm mb-2 text-gray-700 dark:text-gray-300">
                                Account Name <span class="text-red-500">*</span>
                            </label>
                            <?php if (isset($component)) { $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $attributes; } ?>
<?php $component = App\View\Components\SearchableDropdown::resolve(['name' => 'account_id','items' => $accounts->map(fn($a) => ['id' => $a->id, 'name' => $a->name . ' (Level ' . $a->level . ')'])->values(),'placeholder' => 'Search Account'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('searchable-dropdown'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\SearchableDropdown::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['selectedId' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(null)]); ?>
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
                        </div>

                        <div>
                            <label class="block font-medium text-sm mb-2 text-gray-700 dark:text-gray-300">
                                Agent/Client Name <span class="text-red-500">*</span>
                            </label>
                            <?php if (isset($component)) { $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $attributes; } ?>
<?php $component = App\View\Components\SearchableDropdown::resolve(['name' => 'name','items' => $agentsClients->map(fn($a) => [
                                    'id' => $a['name'], 'name' => $a['name'] . ' (' . ucfirst($a['type']) . ')', 'key' => $a['type'] . '_' . $a['id']
                                ])->unique('id')->values(),'placeholder' => 'Search Agent/Client'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('searchable-dropdown'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\SearchableDropdown::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['selectedId' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(null)]); ?>
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
                        </div>

                        <div>
                            <label class="block font-medium text-sm mb-2 text-gray-700 dark:text-gray-300">
                                Company's Bank Account <span class="text-red-500">*</span>
                            </label>
                            <?php if (isset($component)) { $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $attributes; } ?>
<?php $component = App\View\Components\SearchableDropdown::resolve(['name' => 'bank_account','items' => $bankAccounts->map(fn($b) => ['id' => $b->id, 'name' => $b->name])->values(),'placeholder' => 'Select Bank Account'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('searchable-dropdown'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\SearchableDropdown::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['selectedId' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(null)]); ?>
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
                        </div>

                        <div>
                            <label class="block font-medium text-sm mb-2 text-gray-700 dark:text-gray-300">
                                Invoice Number
                            </label>
                            <?php if (isset($component)) { $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $attributes; } ?>
<?php $component = App\View\Components\SearchableDropdown::resolve(['name' => 'invoice_id','items' => $invoices->map(fn($i) => [
                                    'id' => $i->id, 
                                    'name' => '#' . $i->invoice_number . ' - ' . number_format($i->amount, 3) . ' KWD' . ($i->client ? ' (' . trim($i->client->first_name . ' ' . $i->client->last_name) . ')' : '')
                                ])->values(),'placeholder' => 'Search Invoice'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('searchable-dropdown'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\SearchableDropdown::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['selectedId' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(null)]); ?>
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
                        </div>

                        <div>
                            <label class="block font-medium text-sm mb-2 text-gray-700 dark:text-gray-300">
                                Transaction Date <span class="text-red-500">*</span>
                            </label>
                            <input type="datetime-local" name="transaction_date"
                                class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2.5 text-sm bg-white dark:bg-gray-700 dark:text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-colors"
                                required>
                        </div>

                        <div>
                            <label class="block font-medium text-sm mb-2 text-gray-700 dark:text-gray-300">
                                Description <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="description" placeholder="Enter payment description"
                                class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2.5 text-sm bg-white dark:bg-gray-700 dark:text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-colors"
                                required>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block font-medium text-sm mb-2 text-gray-700 dark:text-gray-300">
                                    Amount (KWD) <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400 text-sm">KWD</span>
                                    <input type="number" step="0.001" value="0.000" name="amount"
                                        class="w-full border border-gray-300 dark:border-gray-600 rounded-lg pl-12 pr-3 py-2.5 text-sm bg-white dark:bg-gray-700 dark:text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-colors"
                                        required>
                                </div>
                            </div>

                            <div>
                                <label class="block font-medium text-sm mb-2 text-gray-700 dark:text-gray-300">
                                    Type <span class="text-red-500">*</span>
                                </label>
                                <?php if (isset($component)) { $__componentOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald32d6e5ccefff34d2bfa91c7f668faf8 = $attributes; } ?>
<?php $component = App\View\Components\SearchableDropdown::resolve(['name' => 'type','items' => collect([
                                        ['id' => 'receivable', 'name' => 'Receivable'],
                                        ['id' => 'income', 'name' => 'Income']
                                    ]),'placeholder' => 'Select Type'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('searchable-dropdown'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\SearchableDropdown::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['selectedId' => 'receivable','selectedName' => 'Receivable']); ?>
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
                            </div>
                        </div>

                        <button type="submit" 
                            class="w-full bg-green-600 hover:bg-green-700 active:bg-green-800 text-white py-3 rounded-lg text-sm font-medium transition-all duration-200 flex items-center justify-center gap-2 shadow-sm hover:shadow-md">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Submit Receivable Record
                        </button>
                    </form>
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
<?php endif; ?><?php /**PATH /home/soudshoja/soud-laravel/resources/views/accounting/receivable-create.blade.php ENDPATH**/ ?>