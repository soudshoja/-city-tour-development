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

      <div class="border-b mb-4">
            <ul class="flex justify-center space-x-4" id="tabs">
                <li>
                    <button 
                        class="tab-button text-gray-600 font-semibold pb-2 px-4 border-b-2 border-transparent hover:text-blue-500 hover:border-blue-500 active" 
                        data-tab="payables">Payables</button>
                </li>
                <li>
                    <button 
                        class="tab-button text-gray-600 font-semibold pb-2 px-4 border-b-2 border-transparent hover:text-blue-500 hover:border-blue-500" 
                        data-tab="receivables">Receivables</button>
                </li>
            </ul>
        </div>

 <div id="payables" class="tab-content">
    <div class="text-center font-bold text-2xl mb-4">
        <h1>Accounts Payable Manager</h1>
    </div>

    <!-- Search and Payables Table -->
    <div class="grid grid-cols-12 gap-4">
        <!-- Search Payables Section -->
        <div class="col-span-6 bg-gray-100 p-4 rounded-md shadow-md">
        <h2 class="text-lg font-semibold mb-2">SEARCH PAYABLES</h2>
    <!-- Collapsible Filters -->
    <div>
        <!-- Search Input -->
        <div class="mb-2">
            <label class="block text-sm">Search by Invoice ID or Vendor:</label>
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

            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="text-left py-2 px-2">Branch</th>
                        <th class="text-left py-2 px-2">Agent</th>
                        <th class="text-left py-2 px-2">Due Date</th>
                        <th class="text-right py-2 px-2">Amount Due</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Example Rows -->
                    <tr>
                        <td class="py-2 px-2">Branch 1</td>
                        <td class="py-2 px-2">Agent A</td>
                        <td class="py-2 px-2">2024-11-30</td>
                        <td class="py-2 px-2 text-right">$2,145.00</td>
                    </tr>
                    <tr>
                        <td class="py-2 px-2">Branch 2</td>
                        <td class="py-2 px-2">Agent B</td>
                        <td class="py-2 px-2">2024-12-05</td>
                        <td class="py-2 px-2 text-right">$154.00</td>
                    </tr>
                    <!-- Populate rows dynamically with a database -->
                </tbody>
            </table>
        </div>

        <!-- Payable Details Section -->
        <div class="col-span-6 bg-gray-100 p-4 rounded-md shadow-md">
            <!-- Vendor Details -->
            <div class="col-span-6 bg-gray-100 p-4 rounded-md shadow-md">
                <h2 class="text-lg font-semibold mb-2">Vendor Details</h2>
                <div class="mb-2">
                    <label class="block text-sm">Vendor Name:</label>
                    <input type="text" class="w-full p-2 border rounded-md" placeholder="Supplier Name">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="mb-2">
                        <label class="block text-sm">Address:</label>
                        <input type="text" class="w-full p-2 border rounded-md" placeholder="Vendor Address">
                    </div>
                    <div class="mb-2">
                        <label class="block text-sm">City:</label>
                        <input type="text" class="w-full p-2 border rounded-md" placeholder="City">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="block text-sm">Email:</label>
                    <input type="email" class="w-full p-2 border rounded-md" placeholder="vendor@example.com">
                </div>
                <div class="mb-2">
                    <label class="block text-sm">Phone:</label>
                    <input type="text" class="w-full p-2 border rounded-md" placeholder="(123) 456-7890">
                </div>
                <button class="bg-green-500 text-white px-4 py-2 rounded-md mt-2">Save/Update Vendor</button>
            </div>

            <!-- Hierarchy Section -->
            <div class="mt-6 bg-gray-100 p-4 rounded-md shadow-md">
                <h2 class="text-lg font-semibold mb-2">Payable Hierarchy</h2>
                <div class="mb-2">
                    <label class="block text-sm">Branch:</label>
                    <select class="w-full p-2 border rounded-md">
                        <option>Select Branch</option>
                        <!-- Populate dynamically -->
                    </select>
                </div>
                <div class="mb-2">
                    <label class="block text-sm">Agent:</label>
                    <select class="w-full p-2 border rounded-md">
                        <option>Select Agent</option>
                        <!-- Populate dynamically -->
                    </select>
                </div>
                <div class="mb-2">
                    <label class="block text-sm">Client:</label>
                    <select class="w-full p-2 border rounded-md">
                        <option>Select Client</option>
                        <!-- Populate dynamically -->
                    </select>
                </div>
            </div>

            <!-- Invoice Details -->
            <div class="mt-6 bg-gray-100 p-4 rounded-md shadow-md">
                <h2 class="text-lg font-semibold mb-2">Invoice Details</h2>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2 px-2">Invoice Number</th>
                            <th class="text-left py-2 px-2">Task Description</th>
                            <th class="text-left py-2 px-2">Supplier</th>
                            <th class="text-right py-2 px-2">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Example Rows -->
                        <tr>
                            <td class="py-2 px-2">INV-001</td>
                            <td class="py-2 px-2">Task A</td>
                            <td class="py-2 px-2">Supplier X</td>
                            <td class="py-2 px-2 text-right">$500.00</td>
                        </tr>
                        <tr>
                            <td class="py-2 px-2">INV-002</td>
                            <td class="py-2 px-2">Task B</td>
                            <td class="py-2 px-2">Supplier Y</td>
                            <td class="py-2 px-2 text-right">$300.00</td>
                        </tr>
                        <!-- Populate dynamically with a database -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>
<script>
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
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/coa/payment.blade.php ENDPATH**/ ?>