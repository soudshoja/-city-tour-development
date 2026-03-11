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
<!-- Include Tom Select CSS -->
<link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script>

<div class="container mx-auto p-4">
        <div class="flex justify-center items-center">
            <h3 class="text-2xl font-semibold text-blue-600 mb-4"><?php echo e($company->name); ?> (Company)</h3>
        </div>

 <div id="payables" class="tab-content">
    <div class="text-center font-bold text-2xl mb-4">
        <h1>Accounts Manager</h1>
    </div>

    <!-- Search and Payables Table -->
    <div class="grid grid-cols-12 gap-4">
        <!-- Search Payables Section -->
        <div class="col-span-6 bg-gray-100 p-4 rounded-md shadow-md">
        <h2 class="text-lg font-semibold mb-2">SEARCH</h2>
    <!-- Collapsible Filters -->
    <div>
        <!-- Search Input -->
        <div class="mb-2">
            <label class="block text-sm">Search by Invoice ID or Vendor or Clients:</label>
            <input type="text" class="w-full p-2 border rounded-md" placeholder="Enter Search">
        </div>
        
        <!-- Advanced Filters Toggle -->
        <div class="flex justify-between items-center mb-2">
            <label class="text-sm font-semibold">Advanced Filters:</label>
            <button id="toggleFilters" class="text-blue-500 text-sm">Show/Hide</button>
        </div>
        
        <!-- Advanced Filters Section -->
        <div id="filtersSection" class="hidden space-y-3">
            <!-- Date Range Filter -->
            <div>
                <label class="block text-sm">Date Range:</label>
                <div class="flex gap-2">
                    <input type="date" class="w-full p-2 border rounded-md" placeholder="Start Date">
                    <input type="date" class="w-full p-2 border rounded-md" placeholder="End Date">
                </div>
            </div>

            <!-- Dropdown Filters -->
            <div class="grid grid-cols-2 gap-2">
                <!-- Branch Filter -->
                <div>
                    <label class="block text-sm">Branch:</label>
                    <select class="w-full p-2 border rounded-md">
                        <option value="">All Branches</option>
                        <option value="branch1">Branch 1</option>
                        <option value="branch2">Branch 2</option>
                    </select>
                </div>
                
                <!-- Agent Filter -->
                <div>
                    <label class="block text-sm">Agent:</label>
                    <select class="w-full p-2 border rounded-md">
                        <option value="">All Agents</option>
                        <option value="agent1">Agent 1</option>
                        <option value="agent2">Agent 2</option>
                    </select>
                </div>

                <!-- Client Filter -->
                <div>
                    <label class="block text-sm">Client:</label>
                    <select class="w-full p-2 border rounded-md">
                        <option value="">All Clients</option>
                        <option value="client1">Client 1</option>
                        <option value="client2">Client 2</option>
                    </select>
                </div>

                <!-- Supplier Filter -->
                <div>
                    <label class="block text-sm">Supplier:</label>
                    <select class="w-full p-2 border rounded-md">
                        <option value="">All Suppliers</option>
                        <option value="supplier1">Supplier 1</option>
                        <option value="supplier2">Supplier 2</option>
                    </select>
                </div>
            </div>

            <!-- Payment Status Filter -->
            <div>
                <label class="block text-sm">Payment Status:</label>
                <select class="w-full p-2 border rounded-md">
                    <option value="">All Statuses</option>
                    <option value="unpaid">Unpaid</option>
                    <option value="partially_paid">Partially Paid</option>
                    <option value="paid">Paid</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Buttons Section -->
    <div class="flex justify-between items-center mt-4">
        <button class="bg-gray-300 text-black px-4 py-2 rounded-md">Reset</button>
        <button class="bg-blue-500 text-white px-4 py-2 rounded-md">Apply Filters</button>
    </div>
    <?php $__currentLoopData = $companySummary; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $branch): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php if($branch->total_credits > 0): ?>
                <div class="branch border border-gray-200 p-4 rounded-lg" x-data="{ open: false }">
                    <div class="flex justify-between items-center">
                        <h4 class="text-xl font-semibold text-blue-700 cursor-pointer" @click="open = !open">
                            <?php echo e($branch->name); ?> (Branch)
                        </h4>
                        <div class="text-sm text-gray-700 flex space-x-4">
                            <p>Credits: <span class="font-semibold text-green-500">$<?php echo e(number_format($branch->total_credits, 2)); ?></span></p>
                            <p>Debits: <span class="font-semibold text-red-500">$<?php echo e(number_format($branch->total_debits, 2)); ?></span></p>
                            <p>Balance: <span class="font-semibold text-blue-500">$<?php echo e(number_format($branch->balance, 2)); ?></span></p>
                        </div>
                    </div>

                    <!-- Agents -->
                    <div class="ml-4 mt-4 space-y-3" x-show="open" x-transition>
                        <?php $__currentLoopData = $branch->agents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $agent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php if($agent->total_credits > 0): ?>
                                <div class="agent bg-gray-50 border border-gray-100 rounded-lg p-4" x-data="{ openAgent: false }">
                                    <div class="flex justify-between items-center">
                                        <h5 class="text-lg font-semibold text-green-600 cursor-pointer" @click="openAgent = !openAgent">
                                            <?php echo e($agent->name); ?> (Agent)
                                        </h5>
                                        <div class="text-sm text-gray-700 flex space-x-4">
                                            <p>Credits: <span class="font-semibold text-green-500">$<?php echo e(number_format($agent->total_credits, 2)); ?></span></p>
                                            <p>Debits: <span class="font-semibold text-red-500">$<?php echo e(number_format($agent->total_debits, 2)); ?></span></p>
                                            <p>Balance: <span class="font-semibold text-blue-500">$<?php echo e(number_format($agent->balance, 2)); ?></span></p>
                                        </div>
                                    </div>

                                    <!-- Clients -->
                                    <div class="ml-4 mt-4 space-y-3" x-show="openAgent" x-transition>
                                        <?php $__currentLoopData = $agent->clients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $client): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <?php if($client->total_credits > 0): ?>
                                                <div class="client bg-white border border-gray-100 rounded-lg p-4" x-data="{ openClient: false }">
                                                    <div class="flex justify-between items-center">
                                                        <p class="text-md font-medium text-gray-700 cursor-pointer" @click="openClient = !openClient">
                                                            <?php echo e($client->full_name); ?> (Client)
                                                        </p>
                                                        <div class="text-sm text-gray-700 flex space-x-4">
                                                            <p>Credits: <span class="font-semibold text-green-500">$<?php echo e(number_format($client->total_credits, 2)); ?></span></p>
                                                            <p>Debits: <span class="font-semibold text-red-500">$<?php echo e(number_format($client->total_debits, 2)); ?></span></p>
                                                            <p>Balance: <span class="font-semibold text-blue-500">$<?php echo e(number_format($client->balance, 2)); ?></span></p>
                                                        </div>
                                                    </div>

                                                    <!-- Invoices and Transactions Table -->
                                                    <div class="mt-3" x-show="openClient" x-transition>
                                                        <h6 class="text-sm font-semibold mb-2 text-gray-600">Invoices and Transactions:</h6>
                                                        
                                                        <?php $__currentLoopData = $client->invoices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invoice): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                            <?php if($invoice->total_credits > 0): ?>
                                                                <div class="invoice bg-gray-50 p-4 rounded-lg mb-4">
                                                                    <h6 class="font-semibold text-blue-600">Invoice #<?php echo e($invoice->invoice_number); ?></h6>
                                                                    <div class="text-sm text-gray-700 flex space-x-4">
                                                                        <p>Date: <span class="font-semibold text-green-500"><?php echo e($invoice->invoice_date); ?></span></p>
                                                                        <p>Amount: <span class="font-semibold text-red-500">$<?php echo e($invoice->amount); ?></span></p>
                                                                        <p>Status: <span class="font-semibold text-blue-500"><?php echo e($invoice->status); ?></span></p>
                                                                    </div>

                                                                    <!-- General Ledgers Table grouped by invoice -->
                                                                    <table class="table-auto w-full text-sm bg-gray-50 border rounded-lg mt-3">
                                                                        <thead>
                                                                            <tr class="bg-gray-200 text-gray-600">
                                                                                <th class="px-4 py-2 text-left">Description</th>
                                                                                <th class="px-4 py-2 text-left">Type</th>
                                                                                <th class="px-4 py-2 text-left">Task Price</th>
                                                                                <th class="px-4 py-2 text-left">Invoice Price</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            <?php $__currentLoopData = $invoice->invoiceDetails; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invoiceDetail): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                                                <?php if($invoiceDetail->task): ?>
                                                                                    <tr class="border-b text-gray-600 cursor-pointer hover:bg-gray-100 transition duration-200 ease-in-out"
                                                                                        onclick="showLedgerDetails(<?php echo e($invoiceDetail->id); ?>)"
                                                                                        onmousedown="this.classList.add('bg-blue-100')" 
                                                                                        onmouseup="setTimeout(() => this.classList.remove('bg-blue-100'), 150)">
                                                                                        <td class="px-4 py-2"><?php echo e($invoiceDetail->task->reference); ?> - <?php echo e($invoiceDetail->task->type); ?> <?php echo e($invoiceDetail->task->additional_info); ?> (<?php echo e($invoiceDetail->task->venue); ?>)</td>
                                                                                        <td class="px-4 py-2"><?php echo e($invoiceDetail->task->type); ?></td>
                                                                                        <td class="px-4 py-2 text-right">$<?php echo e(number_format($invoiceDetail->task->total, 2)); ?></td>
                                                                                        <td class="px-4 py-2 text-right">$<?php echo e(number_format($invoiceDetail->task_price, 2)); ?></td>
                                                                                    </tr>
                                                                                <?php endif; ?>
                                                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                            <?php endif; ?>
                                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>

        <!-- Payable Details Section -->
        <div class="col-span-6 bg-gray-100 p-4 rounded-md shadow-md">
            <!-- Vendor Details -->
               <div id="paymentVoucherPanel" style="display: none;">
                    <div  class="col-span-6 bg-gray-100 p-4 rounded-md shadow-md text-sm">
                        <h2 class="text-base font-semibold mb-2"> Account Payable</h2>


                        <!-- Items Section -->
                        <h3 class="text-base font-semibold mb-2">Items</h3>
                        <table id="itemsTable" class="table-auto w-full text-xs bg-gray-50 border rounded-lg mt-3">
                            <thead>
                                <tr class="bg-gray-200 text-gray-600">
                                    <th class="px-2 py-1">Account Name</th>
                                    <th class="px-2 py-1">Description</th>
                                    <th class="px-2 py-1">Credit</th>
                                    <th class="px-2 py-1">Debit</th>
                                    <th class="px-2 py-1">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="payableTableBody">
                                <!-- Rows will be added here dynamically -->
                            </tbody>
                        </table>

                        <button class="bg-blue-500 text-white px-4 py-2 rounded-md mt-4 text-xs" onclick="addItemRow()">Add Item</button>

                        <button class="bg-green-500 text-white px-4 py-2 rounded-md mt-4 text-xs">Save/Submit Voucher</button>
                    </div>

                    <div  class="col-span-6 bg-gray-100 p-4 rounded-md shadow-md text-sm">
                        <h2 class="text-base font-semibold mb-2"> Account Receivable</h2>

                        <!-- Selected Ledger Details -->
                        <!-- <div id="selectedLedgerDetails" class="mb-4">
                            <div>
                                <label class="block text-xs">Account Type:</label>
                                <input type="text" class="w-full p-2 border rounded-md text-xs" id="accountTypeInput" disabled>
                            </div>

                            <div id="payToField" style="display: none;">
                                <label class="block text-xs">Pay To:</label>
                                <input type="text" class="w-full p-2 border rounded-md text-xs" id="payTo" disabled>
                            </div>


                            <div id="receivedFromField" style="display: none;">
                                <label class="block text-xs">Received From:</label>
                                <input type="text" class="w-full p-2 border rounded-md text-xs" id="receivedFrom" disabled>
                            </div>
                            <div>
                                <label class="block text-xs">Amount:</label>
                                <input type="text" class="w-full p-2 border rounded-md text-xs" id="accountBalanceInput" disabled>
                            </div>
                        </div> -->

                        <!-- Items Section -->
                        <h3 class="text-base font-semibold mb-2">Items</h3>
                        <table id="itemsTable" class="table-auto w-full text-xs bg-gray-50 border rounded-lg mt-3">
                            <thead>
                                <tr class="bg-gray-200 text-gray-600">
                                    <th class="px-2 py-1">Account Name</th>
                                    <th class="px-2 py-1">Description</th>
                                    <th class="px-2 py-1">Credit</th>
                                    <th class="px-2 py-1">Debit</th>
                                    <th class="px-2 py-1">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="receivableTableBody">
                                <!-- Rows will be added here dynamically -->
                            </tbody>
                           </table>

                            <button class="bg-blue-500 text-white px-4 py-2 rounded-md mt-4 text-xs" onclick="addItemRow()">Add Item</button>

                            <button class="bg-green-500 text-white px-4 py-2 rounded-md mt-4 text-xs">Save/Submit Voucher</button>
                      </div>

                     </div>


                </div>
            </div>
        </div>
    </div>

    <script>
           const accounts = <?php echo json_encode($accounts, 15, 512) ?>;
    // JavaScript for toggling filters section
    document.getElementById('toggleFilters').addEventListener('click', function() {
        const filtersSection = document.getElementById('filtersSection');
        filtersSection.classList.toggle('hidden');
    });

    const tabs = document.querySelectorAll('.tab-button');
        const contents = document.querySelectorAll('.tab-content');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                // Remove active class from all tabs and hide all content
                tabs.forEach(t => t.classList.remove('active'));
                contents.forEach(c => c.classList.add('hidden'));

                // Add active class to clicked tab and show its content
                tab.classList.add('active');
                document.getElementById(tab.getAttribute('data-tab')).classList.remove('hidden');
            });
        });

        let totalAmount = 0; // To store the total amount of all items

    function showLedgerDetails(id) {

        const JournalEntrys = <?php echo json_encode($JournalEntrys, 15, 512) ?>;
        const ledgers = JournalEntrys.filter(ledger => ledger.invoice_detail_id === id);

                    // Get the table body elements for payable and receivable
            const payableTableBody = document.getElementById('payableTableBody');
            const receivableTableBody = document.getElementById('receivableTableBody');

        ledgers.forEach(ledger => {
            if (ledger.type === 'payable') {
                // Add to payable table
                addLedgerToTable(payableTableBody, ledger.name, ledger.description, ledger.credit, ledger.debit);
            } else if (ledger.type === 'receivable') {
                // Add to receivable table
                addLedgerToTable(receivableTableBody, ledger.name, ledger.description, ledger.credit, ledger.debit);
            }
        });


            // Show the payment voucher panel
            document.getElementById('paymentVoucherPanel').style.display = 'block';
    }


    // Function to add ledger to the respective table
   function addLedgerToTable(tableBody, name, description, credit, debit) {
    // If the table is empty, create the first row
    if (!tableBody.hasChildNodes() || !tableBody.firstElementChild) {
        const newRow = document.createElement('tr');
        newRow.classList.add('border-b', 'text-gray-600');

        newRow.innerHTML = `
            <td class="px-4 py-2 w-1/5">
                <input type="text" class="w-full p-2 border rounded-md text-xs" placeholder="Account Name" value="${name}" disabled>
            </td>
            <td class="px-4 py-2 w-2/5">
                <input type="text" class="w-full p-2 border rounded-md text-xs" placeholder="Description" value="${description}" disabled>
            </td>
            <td class="px-4 py-2 w-1/6">
                <input type="number" class="w-full p-2 border rounded-md text-xs" placeholder="Credit" value="${credit}" oninput="updateTotalAmount()">
            </td>
            <td class="px-4 py-2 w-1/6">
                <input type="number" class="w-full p-2 border rounded-md text-xs" placeholder="Debit" value="${debit}" oninput="updateTotalAmount()">
            </td>
            <td class="px-4 py-2 w-1/6">
                <!-- Add actions if needed -->
            </td>
        `;

        tableBody.appendChild(newRow);
    } else {
        // If the table already has rows, update the first row
        const firstRow = tableBody.firstElementChild;
        firstRow.querySelector('input[placeholder="Account Name"]').value = name;
        firstRow.querySelector('input[placeholder="Description"]').value = description;
        firstRow.querySelector('input[placeholder="Credit"]').value = credit;
        firstRow.querySelector('input[placeholder="Debit"]').value = debit;
    }
}


// Function to add a new item row to the payment voucher
function addItemRow() {
    const tableBody = document.getElementById('itemsTableBody');

    const newRow = document.createElement('tr');
    newRow.classList.add('border-b', 'text-gray-600');

    newRow.innerHTML = `
        <td class="px-4 py-2">
            <select class="w-full p-2 border rounded-md account-select" placeholder="Select Account Name">
                ${accounts.map(account => `<option value="${account.id}">${account.name}</option>`).join('')}
            </select>
        </td>
        <td class="px-4 py-2 w-2/5">
            <input type="text" class="w-full p-2 border rounded-md text-xs" placeholder="Description">
        </td>
        <td class="px-4 py-2 w-1/6">
            <input type="number" class="w-full p-2 border rounded-md text-xs" placeholder="Credit" oninput="updateTotalAmount()">
        </td>
        <td class="px-4 py-2 w-1/6">
            <input type="number" class="w-full p-2 border rounded-md text-xs" placeholder="Debit" oninput="updateTotalAmount()">
        </td>
        <td class="px-4 py-2 w-1/6">
            <button type="button" class="bg-red-500 text-black px-4 py-2 rounded-md text-xs" onclick="removeItemRow(this)">Remove</button>
        </td>
    `;

    tableBody.appendChild(newRow);

    const selectElement = newRow.querySelector('.account-select');
    new TomSelect(selectElement, {
        create: false,
        sortField: {
            field: 'text',
            direction: 'asc'
        }
    });

}

// Function to update item amount when quantity or unit price changes
function updateItemAmount(input) {
    const row = input.closest('tr');
    const quantityInput = row.querySelector('input[type="number"]:nth-child(2)');
    const unitPriceInput = row.querySelector('input[type="number"]:nth-child(3)');
    const amountInput = row.querySelector('input[type="text"]:nth-child(4)');

    const quantity = parseFloat(quantityInput.value) || 0;
    const unitPrice = parseFloat(unitPriceInput.value) || 0;
    const amount = quantity * unitPrice;

    // Update the amount field
    amountInput.value = amount.toFixed(2);

    // Recalculate total amount
    calculateTotalAmount();
}

// Function to remove an item row
function removeItemRow(button) {
    const row = button.closest('tr');
    row.remove();

    // Recalculate total amount after removing the item
    calculateTotalAmount();
}

// Function to calculate total amount of all items
function calculateTotalAmount() {
    let total = 0;
    const rows = document.querySelectorAll('#itemsTableBody tr');
    rows.forEach(row => {
        const amountInput = row.querySelector('input[type="text"]');
        total += parseFloat(amountInput.value) || 0;
    });

    // Update total amount input
    const totalAmountInput = document.getElementById('totalAmountInput');
    totalAmountInput.value = total.toFixed(2);
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
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/accounting/summary.blade.php ENDPATH**/ ?>