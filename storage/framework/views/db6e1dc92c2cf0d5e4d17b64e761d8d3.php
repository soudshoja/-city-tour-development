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
    <style>
        /* Table Styling */
        .bank-payment-table {
            font-size: 12px;
            border: 1px solid #ddd;
            /* Light grey border */
            width: 100%;
            text-align: center;
        }

        .bank-payment-table th,
        .bank-payment-table td {
            padding: 2px !important;
            vertical-align: middle;
            border: 1px solid #ddd !important;
            /* Light grey border for all cells */
            min-width: 80px;
            /* Ensuring a consistent column width */
            text-align: center;
            /* Center content */
        }

        /* Centering input fields */
        .bank-payment-table input,
        .bank-payment-table select {
            font-size: 12px;
            padding: 1px 5px;
            height: 28px;
            width: 100%;
            /* Make inputs fill the cell */
            border: 1px solid #ccc;
            /* Slightly darker grey for inputs */
            border-radius: 6px;
            /* Rounded corners */
            text-align: left;
            /* Center text inside input fields */
        }

        /* Button Styling */
        .btn-sm {
            padding: 4px 8px;
            font-size: 12px;
        }

        /* Add New Record Button */
        .add-record-container {
            margin-top: 15px;
            /* Adds spacing after table */
            text-align: right;
            /* Align button to right */
        }

        .btn-lightblue {
            background-color: lightblue;
            box-shadow: none !important;
            border: none;
        }
    </style>

    <div class="panel h-full overflow-hidden border-0 p-0">
        <div class="min-h-[80px] bg-gradient-to-r from-[#160f6b] to-[#4361ee] p-6 flex items-center text-white">
            <div class="flex items-center justify-between text-white">
                <p class="text-2xl">Receipt Voucher</p>
            </div>
        </div>

        <div class="flex flex-col gap-2.5 xl:flex-row">
            <div class="panel flex-1 px-0 py-6 ltr:lg:mr-6 rtl:lg:ml-6">
                <div class="flex flex-wrap justify-between px-4">

                    <div class="mb-6 w-full lg:w-1/2">
                        <div class="mt-6 space-y-1 text-gray-800 dark:text-gray-400">
                            <?php if (isset($component)) { $__componentOriginal40b9bc8bbe72b013cda6958fd160ce72 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal40b9bc8bbe72b013cda6958fd160ce72 = $attributes; } ?>
<?php $component = App\View\Components\ApplicationLogo::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('application-logo'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\ApplicationLogo::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'custom-logo-size']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal40b9bc8bbe72b013cda6958fd160ce72)): ?>
<?php $attributes = $__attributesOriginal40b9bc8bbe72b013cda6958fd160ce72; ?>
<?php unset($__attributesOriginal40b9bc8bbe72b013cda6958fd160ce72); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal40b9bc8bbe72b013cda6958fd160ce72)): ?>
<?php $component = $__componentOriginal40b9bc8bbe72b013cda6958fd160ce72; ?>
<?php unset($__componentOriginal40b9bc8bbe72b013cda6958fd160ce72); ?>
<?php endif; ?>
                            <?php if($companies): ?>
                                <div class="pl-2">
                                    <h3><?php echo e($companies->name); ?></h3>
                                    <p><?php echo nl2br(e($companies->address)); ?></p>
                                    <p><?php echo e($companies->email); ?></p>
                                    <p><?php echo e($companies->phone); ?></p>
                                </div>
                            <?php endif; ?>
                            <input type="hidden" id="company_id" name="company_id" value="<?php echo e($companies->id); ?>">
                        </div>
                    </div>

                    <div class="mb-6 w-full lg:w-1/2 lg:max-w-fit mt-5">
                        <div class="flex items-center gap-x-6">
                            <label for="receiptvoucherref" class="mb-0 flex-1">Ref <span class="text-red-500">*</span></label>
                            <input required readonly id="receiptvoucherref" value="<?php echo e(old('receiptvoucherref', $receiptvoucher->reference_number)); ?>" type="text"
                                name="receiptvoucherref" class="form-input w-2/3 bg-gray-100 text-gray-800 border-gray-300" />
                        </div>
                        <div class="flex items-center gap-x-4 mt-4">
                            <label class="mb-0 flex-1 ltr:mr-2 rtl:ml-2">Payment Type <span class="text-red-500">*</span></label>
                            <input value="<?php echo e(old('reference_type', $receiptvoucher->reference_type)); ?>" type="text"
                                readonly class="form-input w-2/3 lg:w-[250px] bg-gray-100 text-gray-800 border-gray-300" value="<?php echo e($receiptvoucher->reference_type); ?>" />
                        </div>

                        <?php if($receiptvoucher->reference_type === 'Refund'): ?>
                            <div class="flex items-center gap-x-4 mt-4">
                                <label class="mb-0 flex-1 ltr:mr-2 rtl:ml-2">Refund Number</label>
                                <input type="text" readonly class="form-input w-2/3 lg:w-[250px] bg-gray-100 text-gray-800 border-gray-300"
                                    value="<?php echo e(trim(\Illuminate\Support\Str::after($receiptvoucher->description, '|'))); ?>" />
                            </div>
                        <?php endif; ?>

                        <div class="flex items-center gap-x-6 mt-4">
                            <label for="branch_id" class="mb-0 flex-1">Branch <span class="text-red-500">*</span></label>
                            <select readonly id="branch_id" name="branch_id" class="form-input w-2/3  bg-gray-100 text-gray-800 border-gray-300">
                                <?php $__currentLoopData = $branches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $branch): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($branch->id); ?>"
                                        <?php echo e(old('branch_id', $receiptvoucher->branch_id) == $branch->id ? 'selected' : ''); ?>>
                                        <?php echo e($branch->name); ?>

                                    </option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>

                        <div class="mt-4 flex items-center gap-x-6">
                            <label for="docdate" class="mb-0 flex-1">Doc Date <span class="text-red-500">*</span></label>
                            <input readonly id="docdate" type="date" name="docdate" class="form-input w-2/3  bg-gray-100 text-gray-800 border-gray-300"
                                value="<?php echo e(old('docdate', isset($receiptvoucher->date) ? \Carbon\Carbon::parse($receiptvoucher->date)->format('Y-m-d') : '')); ?>" />
                        </div>
                    </div>
                </div>

                <hr class="my-6 border-[#e0e6ed] dark:border-[#1b2e4b]" />

                <div class="mt-8 px-4">
                    <div class="flex flex-col justify-between lg:flex-row gap-x-4">
                        <div class="mb-6 w-full lg:w-1/2 ltr:lg:mr-6 rtl:lg:ml-6">
                            <div class="text-lg font-semibold">Receipt Voucher</div>
                            <div class="mt-4 flex items-center gap-x-4">
                                <label for="pay_to" class="mb-0 w-1/3 ">Receive From<span class="text-red-500">*</span></label>
                                <input readonly id="pay_to" type="text" name="pay_to" list="supplierList" placeholder="Enter Payee Name"
                                    value="<?php echo e(old('pay_to', $receiptvoucher->name)); ?>" class="form-input flex-1  bg-gray-100 text-gray-800 border-gray-300" />
                                <datalist id="supplierList">
                                    <?php $__currentLoopData = $suppliers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $supplier): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($supplier->name); ?>">[<?php echo e($supplier->id); ?>]
                                            <?php echo e($supplier->name); ?>

                                        </option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </datalist>
                            </div>
                        </div>

                        <div class="w-full lg:w-1/2">
                            <div class="text-lg font-semibold">Remarks</div>

                            <div class="mt-4 flex items-center gap-x-4">
                                <label for="remarks_create_label" class="mb-0 w-1/3 ltr:mr-2 rtl:ml-2">Remarks<span class="text-red-500">*</span></label>
                                <input readonly id="remarks_create" type="text" name="remarks_create"
                                    class="form-input flex-1  bg-gray-100 text-gray-800 border-gray-300" placeholder="Enter Remarks"
                                    value="<?php echo e(old('remarks_create', trim(\Illuminate\Support\Str::before($receiptvoucher->description, '|')))); ?>" />

                            </div>

                            <div class="mt-4 flex items-center gap-x-4">
                                <label for="internal_remarks" class="mb-0 w-1/3 ltr:mr-2 rtl:ml-2">Internal Remarks</label>
                                <input readonly id="internal_remarks" type="text" name="internal_remarks" class="form-input flex-1  bg-gray-100 text-gray-800 border-gray-300"
                                    placeholder="Enter Internal Remarks" value="<?php echo e(old('internal_remarks', $receiptvoucher->remarks_internal)); ?>" />
                            </div>

                            <div class="mt-4 flex items-center gap-x-4">
                                <label for="remarks_fl" class="mb-0 w-1/3 ltr:mr-2 rtl:ml-2">Remarks FL</label>
                                <input readonly id="remarks_fl" type="text" name="remarks_fl" class="form-input flex-1 bg-gray-100 text-gray-800 border-gray-300"
                                    placeholder="Enter Remarks FL" value="<?php echo e(old('remarks_fl', $receiptvoucher->remarks_fl)); ?>" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="table table-bordered bank-payment-table mt-10 w-full">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice Number</th>
                                <th>A/C</th>
                                <th>Remarks</th>
                                <th>Currency</th>
                                <th>Exchange Rate</th>
                                <th>Amount</th>
                                <th>Debit</th>
                                <th>Credit</th>
                                <th>Cheque No</th>
                                <th>Cheque Date</th>
                                <th>Bank Name</th>
                                <th>Auth No</th>
                                <th>Branch</th>
                                <th>Balance</th>
                            </tr>
                        </thead>
                        <tbody id="paymentTable">
                            <?php $__currentLoopData = $JournalEntrys; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr>
                                    <td>
                                        <?php echo e($entry->transaction->invoiceReceipt?->invoice?->invoice_number ?? $entry->reference_number); ?>

                                        <input type="hidden" readonly name="items[<?php echo e($index); ?>][invoice_number]"
                                            value="<?php echo e($entry->transaction->invoiceReceipt?->invoice?->invoice_number ?? $entry->reference_number); ?>">
                                    </td>
                                    <td>
                                        <?php echo e($entry->account ? '[' . $entry->account->root->name . '] [' . $entry->account->code . '] ' . $entry->account->name : 'N/A'); ?>

                                        <input readonly type="hidden" name="items[<?php echo e($index); ?>][account_id]"
                                            value="<?php echo e(old("items.$index.account_id", $entry->account_id)); ?>" />
                                        <?php if($entry->reconciled == 2): ?>
                                            <button type="button" class="btn btn-lightblue btn-sm p-10"
                                                onclick="fetchJournalEntries(<?php echo e($entry->id); ?>)">
                                                View Reconciled Item
                                            </button>

                                            <button type="button" class="mt-1 btn btn-warning text-black btn-sm p-10"
                                                onclick="declineReconcileJournalEntries(<?php echo e($entry->id); ?>)">
                                                Decline & Remove Reconcile
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                    <td><input readonly type="text" name="items[<?php echo e($index); ?>][description]"
                                            value="<?php echo e(old("items.$index.description", $entry->description)); ?>" />
                                    </td>
                                    <td><input readonly type="text" name="items[<?php echo e($index); ?>][currency]"
                                            value="<?php echo e(old("items.$index.currency", $entry->currency)); ?>" />
                                    </td>
                                    <td><input readonly type="text"
                                            name="items[<?php echo e($index); ?>][exchange_rate]"
                                            value="<?php echo e(old("items.$index.exchange_rate", $entry->exchange_rate)); ?>" />
                                    </td>
                                    <td><input readonly type="text" name="items[<?php echo e($index); ?>][amount]"
                                            value="<?php echo e(old("items.$index.amount", $entry->amount)); ?>" />
                                    </td>
                                    <td><input readonly type="text" name="items[<?php echo e($index); ?>][debit]"
                                            value="<?php echo e(old("items.$index.debit", $entry->debit)); ?>" />
                                    </td>
                                    <td><input readonly type="text" name="items[<?php echo e($index); ?>][credit]"
                                            value="<?php echo e(old("items.$index.credit", $entry->credit)); ?>" />
                                    </td>
                                    <td><input readonly type="text" name="items[<?php echo e($index); ?>][cheque_no]"
                                            value="<?php echo e(old("items.$index.cheque_no", $entry->cheque_no)); ?>" />
                                    </td>
                                    <td><input readonly type="text" name="items[<?php echo e($index); ?>][cheque_date]"
                                            value="<?php echo e(old("items.$index.cheque_date", $entry->cheque_date)); ?>" />
                                    </td>
                                    <td><input readonly type="text" name="items[<?php echo e($index); ?>][bank_name]"
                                            value="<?php echo e(old("items.$index.bank_name", $entry->bank_name)); ?>" />
                                    </td>
                                    <td><input readonly type="text" name="items[<?php echo e($index); ?>][auth_no]"
                                            value="<?php echo e(old("items.$index.auth_no", $entry->auth_no)); ?>" />
                                    </td>
                                    <td><input readonly type="text" name="items[<?php echo e($index); ?>][branch_name]"
                                            value="<?php echo e(old("items.$index.branch_name", $entry->branch_name)); ?>" />
                                    </td>
                                    <td><input readonly type="text" name="items[<?php echo e($index); ?>][balance]"
                                            value="<?php echo e(old("items.$index.balance", $entry->balance)); ?>" />
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                </div>

                <div class="panel overflow-hidden mt-10">
                    <div class="relative">
                        <div class="grid grid-cols-2 gap-6 md:grid-cols-3">
                            <div class="mt-2">
                                <div class="text-primary">Total Debit</div>
                                <div class="mt-2 text-2xl font-semibold">
                                    <span id="total_debit">0.000</span>
                                </div>
                            </div>

                            <div class="mt-2">
                                <div class="text-primary">Total Credit</div>
                                <div class="mt-2 text-2xl font-semibold">
                                    <span id="total_credit">0.000</span>
                                </div>
                            </div>

                            <div class="mt-2">
                                <div class="text-primary">Difference</div>
                                <div class="mt-2 text-2xl font-semibold">
                                    <span id="total_difference">0.000</span>
                                </div>
                            </div>

                        </div>
                        <div class="absolute -bottom-12 right-12 h-36 w-36">
                            <svg id="correct" width="36" height="36" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg" class="h-full w-full text-success opacity-20">
                                <circle opacity="0.5" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="1.5" />
                                <path d="M8.5 12.5L10.5 14.5L15.5 9.5" stroke="currentColor" stroke-width="1.5"
                                    stroke-linecap="round" stroke-linejoin="round" />
                            </svg>

                            <svg id="false" width="36" height="36" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg" class="h-full w-full text-danger opacity-20">
                                <circle opacity="0.5" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="1.5" />
                                <path d="M12 7V13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                <circle cx="12" cy="16" r="1" fill="currentColor" />
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-center w-full">
                    <a href="<?php echo e(route('receipt-voucher.index')); ?>"
                        class="btn btn-info px-6 py-2 w-48 rounded-lg text-center">Receipt Voucher List</a>
                </div>
            </div>
        </div>
    </div>

    <div id="journalEntriesModal"
        class="fixed inset-0 bg-gray-800 bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
        <div class="bg-white rounded-lg p-4 sm:p-6 w-full max-w-3xl shadow-xl">
            <h2 class="text-lg font-bold mb-4">Reconciled Journal Entries</h2>

            <div id="journal-entries-content" class="text-sm overflow-x-auto">
                <p class="text-gray-500">Loading entries...</p>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <button onclick="closeJournalEntriesModal()"
                    class="bg-gray-300 px-4 py-2 rounded w-full sm:w-auto">Close</button>
            </div>
        </div>
    </div>

    <script>
        const suppliers = <?php echo json_encode($suppliers, 15, 512) ?>;
        const accpayreceives = <?php echo json_encode($accpayreceives, 15, 512) ?>;

        document.addEventListener("DOMContentLoaded", function() {
            const totalDebitEl = document.getElementById("total_debit");
            const totalCreditEl = document.getElementById("total_credit");
            const totalDifferenceEl = document.getElementById("total_difference");
            const addItemButton = document.getElementById("addItem");

            let items = [];

            // Update a specific field (when editing a row)
            window.updateField = function(index, field, value) {
                if (["debit", "credit", "amount", "exchange_rate", "balance"].includes(field)) {
                    items[index][field] = parseFloat(value) || 0;
                } else {
                    items[index][field] = value;
                }
                updateTotals();
            };

            // Update totals
            function updateTotals() {
                let totalDebit = 0;
                let totalCredit = 0;

                document.querySelectorAll('input[name^="items"]').forEach(function(input) {
                    if (input.name.includes('[debit]')) {
                        totalDebit += parseFloat(input.value) || 0;
                    }
                    if (input.name.includes('[credit]')) {
                        totalCredit += parseFloat(input.value) || 0;
                    }
                });

                const diff = totalDebit - totalCredit;

                totalDebitEl.textContent = totalDebit.toFixed(3);
                totalCreditEl.textContent = totalCredit.toFixed(3);
                totalDifferenceEl.textContent = diff.toFixed(3);

                const correctIcon = document.getElementById('correct');
                const falseIcon = document.getElementById('false');

                if (correctIcon && falseIcon) {
                    correctIcon.style.display = (diff === 0) ? 'block' : 'none';
                    falseIcon.style.display = (diff !== 0) ? 'block' : 'none';
                }
            }

            // Event: Update totals on input change
            document.addEventListener('input', function(e) {
                if (e.target.matches(
                        'input[name^="items"][name$="[debit]"], input[name^="items"][name$="[credit]"]')) {
                    updateTotals();
                }
            });

            // Ensure totals update when everything is loaded
            window.addEventListener('load', function() {
                updateTotals();
            });
        });

        function fetchJournalEntries(entryId) {
            console.log(entryId);

            const content = document.getElementById('journal-entries-content');
            content.innerHTML = '<p class="text-gray-500">Loading entries...</p>';

            if (!entryId) {
                content.innerHTML = '<p class="text-gray-500">No entries found.</p>';
                openJournalEntriesModal();
                return;
            }

            fetch(`/bank-payments/fetch-journals-view?id=${entryId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.length === 0) {
                        content.innerHTML = '<p class="text-gray-500">No entries found.</p>';
                    } else {
                        let totalDebit = 0;
                        let totalCredit = 0;
                        let count = 1;
                        let table = `
                    <div class="overflow-x-auto max-h-[500px] overflow-y-auto border border-gray-50">
                        <table class="min-w-full table-auto text-sm">
                            <thead class="bg-gray-100 sticky top-0">
                                <tr>
                                    <th class="border px-2 py-1">#</th>
                                    <th class="border px-2 py-1">Date</th>
                                    <th class="border px-2 py-1">Name</th>
                                    <th class="border px-2 py-1">Description</th>
                                    <th class="border px-2 py-1 text-right">Debit</th>
                                    <th class="border px-2 py-1 text-right">Credit</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                        data.forEach(entry => {
                            totalDebit += parseFloat(entry.debit);
                            totalCredit += parseFloat(entry.credit);
                            table += `
                        <tr>
                            <td class="border px-2 py-1">${count}</td>
                            <td class="border px-2 py-1">${entry.transaction_date}</td>
                            <td class="border px-2 py-1">${entry.name}</td>
                            <td class="border px-2 py-1">${entry.description}</td>
                            <td class="border px-2 py-1 text-right">${parseFloat(entry.debit).toFixed(3)}</td>
                            <td class="border px-2 py-1 text-right">${parseFloat(entry.credit).toFixed(3)}</td>
                        </tr>
                    `;
                            count++;
                        });

                        // Total row
                        table += `
                        <tr class="font-semibold bg-gray-50">
                            <td class="border px-2 py-1 text-right" colspan="4">Total</td>
                            <td class="border px-2 py-1 text-right">${totalDebit.toFixed(3)}</td>
                            <td class="border px-2 py-1 text-right">${totalCredit.toFixed(3)}</td>
                        </tr>
                    </tbody>
                    </table>
                </div>
                `;

                        content.innerHTML = table;
                    }
                    openJournalEntriesModal();
                })
                .catch(error => {
                    console.error('Error fetching journal entries:', error);
                    content.innerHTML = '<p class="text-red-500">Error loading entries.</p>';
                    openJournalEntriesModal();
                });
        }

        function openJournalEntriesModal() {
            document.getElementById('journalEntriesModal').classList.remove('hidden');
        }

        function closeJournalEntriesModal() {
            document.getElementById('journalEntriesModal').classList.add('hidden');
        }

        function declineReconcileJournalEntries(transactionId) {

            console.log(transactionId);
            if (!confirm('Are you sure you want to decline & remove this reconciliation?')) {
                return;
            }

            fetch(`/bank-payments/${transactionId}/decline-reconcile`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({})
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Reconciliation declined successfully.');
                        location.reload(); // reload to reflect changes
                    } else {
                        alert('Failed to decline reconciliation.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while declining reconciliation.');
                });
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
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/receipt-voucher/edit.blade.php ENDPATH**/ ?>