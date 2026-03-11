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
        <h1 class="text-center mb-2 font-semibold text-xl">Accounts Reconciliation Report</h1>
        <div class="flex justify-center items-center bg-gray-100">
            <div class="w-full max-w-screen-xl p-6 my-2">
                
                <form method="GET" id="filterForm" action="<?php echo e(route('reports.acc-reconcile')); ?>"
                    class="mb-4 flex flex-wrap gap-4 items-end">

                    <div class="flex-1 min-w-[200px]">
                        <label for="from" class="block text-sm font-medium">Date From:</label>
                        <input type="date" id="from" name="from" value="<?php echo e(old('from', $from)); ?>"
                            class="border border-gray-300 rounded w-full px-2 py-1 h-10" required />
                    </div>

                    <div class="flex-1 min-w-[200px]">
                        <label for="to" class="block text-sm font-medium">Date To:</label>
                        <input type="date" id="to" name="to" value="<?php echo e(old('to', $to)); ?>"
                            class="border border-gray-300 rounded w-full px-2 py-1 h-10" required />
                    </div>

                    <div class="flex-1 min-w-[250px]">
                        <label for="supplier" class="block text-sm font-medium">Supplier:</label>
                        <input type="text" id="supplier" name="supplier" value="<?php echo e(old('supplier', $supplier)); ?>"
                            class="border border-gray-300 rounded w-full px-2 py-1 h-10" list="supplierList"
                            placeholder="Search supplier name..." />
                        <datalist id="supplierList">
                            <?php $__currentLoopData = $suppliers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sup): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($sup->name); ?>">
                                    <?php echo e($sup->name); ?>

                                </option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </datalist>
                    </div>

                    <div class="flex-1 min-w-[150px]">
                        <label for="reconciled" class="block text-sm font-medium">Reconciled:</label>
                        <select id="reconciled" name="reconciled"
                            class="border border-gray-300 rounded w-full px-2 py-1 h-10">
                            <option value="both"
                                <?php echo e(old('reconciled', $reconciled ?? 'both') == 'both' ? 'selected' : ''); ?>>All</option>
                            <option value="yes"
                                <?php echo e(old('reconciled', $reconciled ?? '') == 'yes' ? 'selected' : ''); ?>>Reconciled
                            </option>
                            <option value="no" <?php echo e(old('reconciled', $reconciled ?? '') == 'no' ? 'selected' : ''); ?>>
                                No Reconciled</option>
                        </select>
                    </div>

                    <div class="flex-none">
                        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Filter</button>
                        <button type="button" class="bg-gray-300 px-4 py-2 rounded"
                            onclick="resetSupplierAndSubmit()">Reset</button>
                    </div>
                </form>


                
                <div class="flex gap-2">
                    <div class="border w-full p-4 rounded bg-white text text-gray-700">
                        <?php if($from && $to): ?>
                            <p><strong>Report Period:</strong> <?php echo e($from); ?> to <?php echo e($to); ?></p>
                        <?php elseif(!$from && !$to): ?>
                            <p><strong>Note:</strong> Showing all transactions (no date filter applied).</p>
                        <?php endif; ?>

                        <?php if($supplier): ?>
                            <p><strong>Filtered by Supplier:</strong>
                                <?php echo e(\App\Models\Supplier::where('name', $supplier)->value('name') ?? 'Unknown Supplier'); ?>

                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if(session('error')): ?>
            <div class="text-red-600 mb-4"><?php echo e(session('error')); ?></div>
        <?php endif; ?>

        <div class="p-4 bg-white">
            <?php if($transactions->isEmpty()): ?>
                <p>No transactions found for the selected criteria.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white text-sm">
                        <thead>
                            <tr class="bg-gray-100 text-left text-sm font-medium text-gray-600">
                                <th class="py-2 px-4">Date</th>
                                <th class="py-2 px-4">Account</th>
                                <th class="py-2 px-4">Supplier</th>
                                <th class="py-2 px-4">Description</th>
                                <th class="py-2 px-4 text-right">Debit (KWD)</th>
                                <th class="py-2 px-4 text-right">Credit (KWD)</th>
                                <th class="px-4 py-2 text-center">
                                    Reconciled
                                    <input type="checkbox" id="select-all" onclick="toggleSelectAll(this)"
                                        class="ml-2 align-middle">
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                                $trx_amount = 0;
                            ?>
                            <?php $__currentLoopData = $transactions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tx): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr class="border-t text-sm">
                                    <td class="py-2 px-4">
                                        <?php echo e(\Carbon\Carbon::parse($tx->transaction_date)->format('Y-m-d')); ?></td>
                                    <td class="py-2 px-4"><?php echo e($tx->account->code); ?> - <?php echo e($tx->account->name); ?></td>
                                    <td class="py-2 px-4"><?php echo e($tx->supplier->name ?? ($tx->name ?? 'N/A')); ?></td>
                                    <td class="py-2 px-4"><?php echo e($tx->description ?? '-'); ?></td>
                                    <td class="py-2 px-4 text-right"><?php echo e(number_format($tx->debit, 2)); ?></td>
                                    <td class="py-2 px-4 text-right"><?php echo e(number_format($tx->credit, 2)); ?></td>
                                    <td class="px-4 py-2 text-center">
                                        <?php if($tx->reconciled): ?>
                                            Yes
                                        <?php else: ?>
                                            No
                                            <input type="checkbox" name="reconcile_ids[]" value="<?php echo e($tx->id); ?>"
                                                class="reconcile-checkbox ml-2 align-middle"
                                                data-account-id="<?php echo e($tx->account->id); ?>"
                                                data-remarks="<?php echo e($tx->description ?? ''); ?>"
                                                data-credit="<?php echo e($tx->debit ?? 0); ?>"
                                                data-debit="<?php echo e($tx->credit ?? 0); ?>">
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php
                                    $trx_amount = $trx_amount + $tx->credit;
                                ?>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                </div>

                <form id="bank-payment-form" method="POST" action="<?php echo e(route('bank-payments.store')); ?>">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="company_id" value="<?php echo e(auth()->user()->company->id); ?>">
                    <input type="hidden" name="branch_id" value="<?php echo e(auth()->user()->branch->id ?? auth()->user()->accountant->branch_id); ?>">
                    <input type="hidden" name="docdate" value="<?php echo e(now()->format('Y-m-d')); ?>">
                    <input type="hidden" name="bankpaymentref" value="PV-<?php echo e(now()->timestamp); ?>">
                    <input type="hidden" name="bankpaymenttype" value="PaymentByDate">
                    <input type="hidden" name="pay_to"
                        value="<?php echo e($transactions->first()->account->name ?? 'N/A'); ?>">
                    <input type="hidden" name="remarks_create" value="Auto reconciliation payment.">
                    <input type="hidden" name="internal_remarks" value="">
                    <input type="hidden" name="remarks_fl" value="">
                    <input type="hidden" id="form-items" name="items" />
                    <input type="hidden" name="amount" value="<?php echo e($trx_amount ?? 0); ?>" id="amount">
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded mt-10">Submit for
                        Reconcile</button>
                </form>
            <?php endif; ?>

        </div>

        <script>
            function resetSupplierAndSubmit() {
                const form = document.getElementById('filterForm');
                const supplierInput = document.getElementById('supplier');
                if (form && supplierInput) {
                    supplierInput.value = '';
                    form.submit();
                }
            }

            function toggleSelectAll(source) {
                const checkboxes = document.querySelectorAll('.reconcile-checkbox');
                checkboxes.forEach(cb => cb.checked = source.checked);
            }

            document.getElementById('bank-payment-form').addEventListener('submit', function(e) {
                const form = this;
                const selectedCheckboxes = document.querySelectorAll('.reconcile-checkbox:checked');

                if (selectedCheckboxes.length === 0) {
                    alert('Please select at least one transaction to reconcile.');
                    e.preventDefault();
                    return;
                }

                // Show confirmation dialog
                const confirmed = confirm('Are you sure you want to proceed with reconciliation?');
                if (!confirmed) {
                    e.preventDefault(); // Cancel form submission if user clicks Cancel
                    return;
                }

                // Proceed to build hidden inputs and append them as before
                const container = document.createElement('div');
                container.id = 'dynamic-items-container';

                let totalCredit = 0;
                const grouped = {};

                selectedCheckboxes.forEach(checkbox => {
                    const accountId = checkbox.getAttribute('data-account-id');
                    const remarks = checkbox.getAttribute('data-remarks') || '';
                    const dataCredit = parseFloat(checkbox.getAttribute('data-credit') || 0);
                    const dataDebit = parseFloat(checkbox.getAttribute('data-debit') || 0);

                    if (!grouped[accountId]) {
                        grouped[accountId] = {
                            account_id: accountId,
                            remarks: remarks,
                            credit: 0,
                            debit: 0,
                            journal_entry_ids: [],
                        };
                    }

                    grouped[accountId].credit += dataCredit;
                    grouped[accountId].debit += dataDebit;
                    grouped[accountId].journal_entry_ids.push(checkbox.value);
                    totalCredit += dataCredit;
                });

                let index = 0;
                for (const [accountId, data] of Object.entries(grouped)) {
                    const fields = {
                        account_id: data.account_id,
                        remarks: data.remarks,
                        credit: data.credit,
                        debit: Math.abs(data.credit - data.debit).toFixed(2),
                        cheque_date: '<?php echo e(now()->format('Y-m-d')); ?>',
                        exchange_rate: 1,
                        currency: 'KWD',
                        transaction_id: data.journal_entry_ids.join(','),
                        type_selector: 'account',
                    };

                    for (const [key, value] of Object.entries(fields)) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = `items[${index}][${key}]`;
                        input.value = value;
                        container.appendChild(input);
                    }

                    index++;
                }

                document.getElementById('amount').value = totalCredit.toFixed(2);
                form.appendChild(container);
            });
        </script>

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
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/reports/acc-reconcile.blade.php ENDPATH**/ ?>