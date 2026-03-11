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
    <h1 class="text-center mb-2 font-semibold text-xl">Unpaid Accounts Payable & Receivable Report</h1>

    <div class="flex justify-center items-center bg-gray-100">
        <form method="GET" action="<?php echo e(route('reports.unpaid-report')); ?>"
            class="p-6 my-2 w-full md:w-full lg:w-full flex flex-col gap-4 bg-white rounded shadow">

            <!-- Input Fields Section -->
            <div class="grid grid-cols-12 gap-4">
                <div class="flex flex-col col-span-6 lg:col-span-2">
                    <label for="start_date" class="font-medium text-sm mb-1">Start Date:</label>
                    <input type="date" name="start_date" id="start_date"
                        value="<?php echo e($startDate ? date('Y-m-d', strtotime($startDate)) : ''); ?>"
                        class="border rounded px-2 py-2 focus:outline-none focus:ring focus:ring-blue-300">
                </div>
                <div class="flex flex-col col-span-6 lg:col-span-2">
                    <label for="end_date" class="font-medium text-sm mb-1">End Date:</label>
                    <input type="date" name="end_date" id="end_date"
                        value="<?php echo e($endDate ? date('Y-m-d', strtotime($endDate)) : ''); ?>"
                        class="border rounded px-2 py-2 focus:outline-none focus:ring focus:ring-blue-300">
                </div>
                <div class="flex flex-col col-span-6 lg:col-span-2">
                    <label for="branch_id" class="font-medium text-sm mb-1">Filter by Branch:</label>
                    <select name="branch_id" id="branch_id"
                        class="border rounded px-7 py-2 focus:outline-none focus:ring focus:ring-blue-300">
                        <option value="">All Branches</option>
                        <?php $__currentLoopData = $branches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $branch): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($branch->id); ?>" <?php echo e($branchId == $branch->id ? 'selected' : ''); ?>>
                                <?php echo e(ucfirst($branch->name)); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div class="flex flex-col col-span-6 lg:col-span-3">
                    <label for="account_id" class="font-medium text-sm mb-1">Filter by Account:</label>
                    <select name="account_id" id="account_id"
                        class="border rounded px-7 py-2 focus:outline-none focus:ring focus:ring-blue-300">
                        <?php $__currentLoopData = $allAccounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $account): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($account->id); ?>" <?php echo e($accountId == $account->id ? 'selected' : ''); ?>>
                                <?php echo e(ucfirst($account->name)); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <?php
                    $selectedType = request()->input('type_id', '');
                ?>
                <div class="flex flex-col col-span-6 lg:col-span-3">
                    <label for="type_id" class="font-medium text-sm mb-1">Filter by Type:</label>
                    <select name="type_id" id="type_id"
                        class="border rounded px-7 py-2 focus:outline-none focus:ring focus:ring-blue-300">
                        <option value="" disabled <?php echo e(empty($selectedType) ? 'selected' : ''); ?>>Select Report Type
                        </option>
                        <option value="payable" <?php echo e($selectedType == 'payable' ? 'selected' : ''); ?>>Payable only
                        </option>
                        <option value="receivable" <?php echo e($selectedType == 'receivable' ? 'selected' : ''); ?>>Receivable
                            only</option>
                    </select>
                </div>
            </div>

            <!-- Button Section (Centered) -->
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="resetReportFilters()"
                    class="px-4 py-2 border border-gray-300 text-gray-700 text-sm rounded-md hover:bg-gray-100 transition-all duration-150">
                    Reset
                </button>
                <button id="submit-account-filter" type="submit"
                    class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition-all duration-150">
                    Filter
                </button>
            </div>


            <div class="flex gap-2">
                <div class="border w-full p-2 rounded">

                    <?php if($startDate && $endDate): ?>
                        <p>Report for the period: <?php echo e($startDate); ?> to <?php echo e($endDate); ?></p>
                    <?php elseif(!$startDate && !$endDate): ?>
                        <p>Showing all transactions (no date filter applied).</p>
                    <?php endif; ?>

                    <?php if($branchId): ?>
                        <p>Filtered by Branch: <?php echo e(\App\Models\Branch::find($branchId)->name ?? 'Unknown Branch'); ?></p>
                    <?php endif; ?>
                    <?php if($supplierId): ?>
                        <p>Filtered by Supplier:
                            <?php echo e(\App\Models\Supplier::find($supplierId)->name ?? 'Unknown Supplier'); ?>

                        </p>
                    <?php endif; ?>
                    <?php if($selectedType): ?>
                        <p>Filtered by Type: <?php echo e(ucfirst($selectedType)); ?></p>
                    <?php endif; ?>


                </div>
            </div>

        </form>
    </div>


    <div class="p-4 bg-white rounded shadow">
        
        <div id="account_payable"
            class="<?php echo e($selectedType == 'payable' ? '' : ($selectedType == 'receivable' ? 'hidden' : '')); ?> p-3 mt-4 border shadow">
            <h2 class="font-bold">Accounts Payable Transactions <span class="font-normal">(Account ID:
                    <?php echo e($accountPayable->code ?? 'CI12300'); ?>)</span></h2>

            <?php
                $totalDebitPayable = 0;
                $totalCreditPayable = 0;
                $totalAllPayable = 0;
                $totalDebitReceivable = 0;
                $totalCreditReceivable = 0;
                $totalAllReceivable = 0;
            ?>

            <?php if($payableTransactions->isNotEmpty()): ?>
                <table border="1" style="border-collapse: collapse; width: 100%; text-align: left;">
                    <thead>
                        <tr>
                            <th style="width:60px; padding: 8px; border: 1px solid #ddd;">No.</th>
                            <th style="width:220px; style=" padding: 8px; border: 1px solid #ddd;">Transaction Date</th>
                            <th style="padding: 8px; border: 1px solid #ddd;">Description</th>
                            <th style="width:160px; padding: 8px; border: 1px solid #ddd;">Debit</th>
                            <th style="width:160px; padding: 8px; border: 1px solid #ddd;">Credit</th>
                            <th style="width:160px; padding: 8px; border: 1px solid #ddd;">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__currentLoopData = $payableTransactions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $transaction): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php
                                $totalDebitPayable += $transaction->debit;
                                $totalCreditPayable += $transaction->credit;
                                $totalAllPayable = $totalDebitPayable - $totalCreditPayable;
                            ?>
                            <tr>
                                <td style="padding: 8px; border: 1px solid #ddd;"><?php echo e($loop->iteration); ?></td>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    <?php echo e($transaction->transaction_date ? \Carbon\Carbon::parse($transaction->transaction_date)->format('d-M-Y') : ''); ?>

                                </td>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    <p><strong><?php echo e($transaction->description); ?></strong>
                                    </p>
                                    <?php if(!empty($transaction->task?->additional_info)): ?>
                                        <p>Additional info: <?php echo e($transaction->task->additional_info); ?></p>
                                    <?php endif; ?>

                                    <?php if(!empty($transaction->task?->reference)): ?>
                                        <p>Ref: <?php echo e($transaction->task->reference); ?></p>
                                    <?php endif; ?>

                                    <?php if(!empty($transaction->task?->client_name)): ?>
                                        <p>Client: <?php echo e($transaction->task->client_name); ?></p>
                                    <?php endif; ?>

                                    <?php if(!empty($transaction->task?->flightDetails?->departure_time)): ?>
                                        <p>Flight details:
                                            <?php echo e(\Carbon\Carbon::parse($transaction->task->flightDetails->departure_time)->format('Y-m-d H:i')); ?>

                                            -
                                            <?php echo e(\Carbon\Carbon::parse($transaction->task->flightDetails->arrival_time)->format('Y-m-d H:i')); ?>

                                        </p>
                                    <?php endif; ?>

                                    <?php
                                        $hotelDetails = $transaction->task?->hotelDetails;
                                        $roomDetails =
                                            $hotelDetails && $hotelDetails->room_details
                                                ? json_decode($hotelDetails->room_details, true)
                                                : null;
                                    ?>

                                    <?php if(!empty($roomDetails)): ?>
                                        <p><strong>Hotel details:</strong></p>
                                        <ul>
                                            <li>Name: <?php echo e($roomDetails['name'] ?? 'n/a'); ?></li>
                                            <li>Info: <?php echo e($roomDetails['info'] ?? 'n/a'); ?></li>
                                            <li>Type: <?php echo e($roomDetails['type'] ?? 'n/a'); ?></li>
                                            <li>Check-in: <?php echo e($hotelDetails->check_in ?? 'n/a'); ?></li>
                                            <li>Check-out: <?php echo e($hotelDetails->check_out ?? 'n/a'); ?>

                                            </li>
                                        </ul>
                                    <?php endif; ?>

                                </td>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    <?php echo e(number_format($transaction->debit, 2)); ?>

                                </td>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    <?php echo e(number_format($transaction->credit, 2)); ?>

                                </td>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    <?php echo e(number_format($totalAllPayable, 2)); ?>

                                    
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-red-500">No Accounts Payable transactions found for the selected period.</p>
            <?php endif; ?>
        </div>
        <div id="account_receivable"
            class="<?php echo e($selectedType == 'receivable' ? '' : ($selectedType == 'payable' ? 'hidden' : '')); ?> p-3 mt-4 border shadow">
            <h2 class="font-bold">Accounts Receivable Transactions <span class="font-normal">(Account ID:
                    <?php echo e($receivableAccount->code ?? 'CI12301'); ?>)</span></h2>
            <?php if($receivableTransactions->isNotEmpty()): ?>
                <table border="1" style="border-collapse: collapse; width: 100%; text-align: left;">
                    <thead>
                        <tr>
                            <th style="width:60px; padding: 8px; border: 1px solid #ddd;">No.</th>
                            <th style="width:220px; style=" padding: 8px; border: 1px solid #ddd;">Transaction Date</th>
                            <th style="padding: 8px; border: 1px solid #ddd;">Description</th>
                            <th style="width:160px; padding: 8px; border: 1px solid #ddd;">Debit</th>
                            <th style="width:160px; padding: 8px; border: 1px solid #ddd;">Credit</th>
                            <th style="width:160px; padding: 8px; border: 1px solid #ddd;">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__currentLoopData = $receivableTransactions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $transaction): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php
                                $totalDebitReceivable += $transaction->debit;
                                $totalCreditReceivable += $transaction->credit;
                                $totalAllReceivable = $totalDebitReceivable - $totalCreditReceivable;
                            ?>

                            <tr>
                                <td style="padding: 8px; border: 1px solid #ddd;"><?php echo e($loop->iteration); ?></td>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    <?php echo e($transaction->transaction_date ? Carbon\Carbon::parse($transaction->transaction_date)->format('d-M-Y') : ''); ?>

                                </td>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    <p><?php echo e($transaction->description); ?>

                                    </p>
                                    <?php if($transaction->invoice && !empty($transaction->invoice->invoice_number)): ?>
                                        <p>
                                            <small>Ref:
                                                <?php echo e($transaction->type_reference_id ?? $transaction->invoice->invoice_number); ?>

                                                <a target="_blank"
                                                    href="<?php echo e(route('invoice.show', ['companyId' => $transaction->company_id, 'invoiceNumber' => $transaction->invoice->invoice_number])); ?>"
                                                    class="text-blue-500 ml-0">
                                                    🔍
                                                </a>
                                            </small>
                                        </p>
                                    <?php endif; ?>

                                </td>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    <?php echo e(number_format($transaction->debit, 2)); ?>

                                </td>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    <?php echo e(number_format($transaction->credit, 2)); ?>

                                </td>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    <?php echo e(number_format($totalAllReceivable, 2)); ?>

                                    
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-red-500">No Accounts Receivable transactions found for the selected period.</p>
            <?php endif; ?>
        </div>

        <div class="p-3 mt-4 border shadow">
            <h2 class="flex justify-start">
                <h2 class="font-bold">Outstanding Balances</h2>
            </h2>
            <div class="flex gap-2">
                <div class="border w-full p-2 rounded">
                    <h3>Accounts Payable</h3>
                    <p><strong>Outstanding Balance: <?php echo e(number_format($totalAllPayable, 2)); ?></strong></p>
                </div>
                <div class="border w-full p-2 rounded">
                    <h3>Accounts Receivable</h3>
                    <p><strong>Outstanding Balance: <?php echo e(number_format($totalAllReceivable, 2)); ?></strong></p>
                </div>
            </div>
        </div>
    </div>
    <script>
    let filterType = document.getElementById('type_id');
    let filterButton = document.getElementById('submit-account-filter');
    let accountSelect = document.getElementById('account_id');

    filterType.addEventListener('change', (event) => {
        let type_id = event.target.value;

        // Show loading while fetching
        accountSelect.innerHTML = '<option value="" disabled>Loading...</option>';
        filterButton.innerHTML = 'Loading...';
        filterButton.classList.add('cursor-not-allowed');
        filterButton.disabled = true;

        fetch(`<?php echo e(route('reports.account-list')); ?>?type_id=${type_id}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
            })
            .then(response => response.json())
            .then(data => {
                accountSelect.innerHTML = ''; // Clear all existing options

                if (data.length === 0) {
                    accountSelect.innerHTML = '<option value="">No accounts available</option>';
                    return;
                }

                data.forEach((account, index) => {
                    const option = document.createElement('option');
                    option.value = account.id;
                    option.textContent = account.name;

                    // Select the first account by default if user hasn't chosen manually
                    if (index === 0) {
                        option.selected = true;
                    }

                    accountSelect.appendChild(option);
                });
            })
            .catch(error => {
                accountSelect.innerHTML = '<option value="">Error loading accounts</option>';
                console.error(error);
            })
            .finally(() => {
                filterButton.innerHTML = 'Filter';
                filterButton.classList.remove('cursor-not-allowed');
                filterButton.disabled = false;
            });
    });
    function resetReportFilters() {
        document.getElementById('start_date').value = '';
        document.getElementById('end_date').value = '';
        document.getElementById('branch_id').selectedIndex = 0;
        document.getElementById('type_id').selectedIndex = 0;

        // Trigger type change so account list resets too
        document.getElementById('type_id').dispatchEvent(new Event('change'));

        // Wait a bit before submitting to allow accounts to reload
        setTimeout(() => {
            document.getElementById('submit-account-filter').click();
        }, 300); // adjust delay if needed
    }
</script>

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
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/reports/unpaid-report.blade.php ENDPATH**/ ?>