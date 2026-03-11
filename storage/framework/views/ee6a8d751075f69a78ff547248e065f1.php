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
    <div class="container mx-auto p-5">
        <h1 class="text-3xl font-bold mb-4">Client Management Dashboard</h1>
        
        <!-- Clients Form -->
        <h2 class="text-2xl font-semibold mt-5">Clients</h2>
        <form class="bg-white p-4 rounded shadow-md">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="clientName" class="block text-sm font-medium text-gray-700">Name</label>
                    <input type="text" id="clientName" placeholder="Client Name" class="mt-1 p-2 block w-full border border-gray-300 rounded-md">
                </div>
                <div>
                    <label for="clientPhone" class="block text-sm font-medium text-gray-700">Phone</label>
                    <input type="text" id="clientPhone" placeholder="Client Phone" class="mt-1 p-2 block w-full border border-gray-300 rounded-md">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <div>
                    <label for="clientEmail" class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" id="clientEmail" placeholder="Client Email" class="mt-1 p-2 block w-full border border-gray-300 rounded-md">
                </div>
                <div>
                    <label for="clientNote" class="block text-sm font-medium text-gray-700">Note</label>
                    <input type="text" id="clientNote" placeholder="Notes" class="mt-1 p-2 block w-full border border-gray-300 rounded-md">
                </div>
            </div>
            <button type="submit" class="mt-4 bg-blue-600 text-white py-2 px-4 rounded">Add Client</button>
        </form>

        <!-- Agents Form -->
        <h2 class="text-2xl font-semibold mt-10">Agents</h2>
        <form class="bg-white p-4 rounded shadow-md">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="agentName" class="block text-sm font-medium text-gray-700">Name</label>
                    <input type="text" id="agentName" placeholder="Agent Name" class="mt-1 p-2 block w-full border border-gray-300 rounded-md">
                </div>
                <div>
                    <label for="agentPhone" class="block text-sm font-medium text-gray-700">Phone</label>
                    <input type="text" id="agentPhone" placeholder="Agent Phone" class="mt-1 p-2 block w-full border border-gray-300 rounded-md">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <div>
                    <label for="agentEmail" class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" id="agentEmail" placeholder="Agent Email" class="mt-1 p-2 block w-full border border-gray-300 rounded-md">
                </div>
                <div>
                    <label for="agentCompany" class="block text-sm font-medium text-gray-700">Company</label>
                    <input type="text" id="agentCompany" placeholder="Company Name" class="mt-1 p-2 block w-full border border-gray-300 rounded-md">
                </div>
            </div>
            <button type="submit" class="mt-4 bg-blue-600 text-white py-2 px-4 rounded">Add Agent</button>
        </form>

        <!-- Accounts Form -->
        <h2 class="text-2xl font-semibold mt-10">Accounts</h2>
        <form class="bg-white p-4 rounded shadow-md">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="accountName" class="block text-sm font-medium text-gray-700">Name</label>
                    <input type="text" id="accountName" placeholder="Account Name" class="mt-1 p-2 block w-full border border-gray-300 rounded-md">
                </div>
                <div>
                    <label for="accountDescription" class="block text-sm font-medium text-gray-700">Description</label>
                    <input type="text" id="accountDescription" placeholder="Description" class="mt-1 p-2 block w-full border border-gray-300 rounded-md">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <div>
                    <label for="accountBalance" class="block text-sm font-medium text-gray-700">Balance</label>
                    <input type="number" id="accountBalance" placeholder="Account Balance" class="mt-1 p-2 block w-full border border-gray-300 rounded-md">
                </div>
                <div>
                    <label for="accountLevel" class="block text-sm font-medium text-gray-700">Level</label>
                    <input type="number" id="accountLevel" placeholder="Account Level" class="mt-1 p-2 block w-full border border-gray-300 rounded-md">
                </div>
            </div>
            <button type="submit" class="mt-4 bg-blue-600 text-white py-2 px-4 rounded">Add Account</button>
        </form>

        <!-- Transactions Form -->
        <h2 class="text-2xl font-semibold mt-10">Transactions</h2>
        <form class="bg-white p-4 rounded shadow-md">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="transactionClientId" class="block text-sm font-medium text-gray-700">Client ID</label>
                    <input type="number" id="transactionClientId" placeholder="Client ID" class="mt-1 p-2 block w-full border border-gray-300 rounded-md">
                </div>
                <div>
                    <label for="transactionAmount" class="block text-sm font-medium text-gray-700">Amount</label>
                    <input type="number" step="0.01" id="transactionAmount" placeholder="Transaction Amount" class="mt-1 p-2 block w-full border border-gray-300 rounded-md">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <div>
                    <label for="transactionDate" class="block text-sm font-medium text-gray-700">Transaction Date</label>
                    <input type="datetime-local" id="transactionDate" class="mt-1 p-2 block w-full border border-gray-300 rounded-md">
                </div>
                <div>
                    <label for="transactionType" class="block text-sm font-medium text-gray-700">Transaction Type</label>
                    <select id="transactionType" class="mt-1 p-2 block w-full border border-gray-300 rounded-md">
                        <option value="debit">Debit</option>
                        <option value="credit">Credit</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="mt-4 bg-blue-600 text-white py-2 px-4 rounded">Add Transaction</button>
        </form>

        <!-- General Ledgers Form -->
        <h2 class="text-2xl font-semibold mt-10">General Ledgers</h2>
        <form class="bg-white p-4 rounded shadow-md">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="ledgerAccountId" class="block text-sm font-medium text-gray-700">Account ID</label>
                    <input type="number" id="ledgerAccountId" placeholder="Account ID" class="mt-1 p-2 block w-full border border-gray-300 rounded-md">
                </div>
                <div>
                    <label for="ledgerDescription" class="block text-sm font-medium text-gray-700">Description</label>
                    <input type="text" id="ledgerDescription" placeholder="Description" class="mt-1 p-2 block w-full border border-gray-300 rounded-md">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <div>
                    <label for="ledgerDebit" class="block text-sm font-medium text-gray-700">Debit</label>
                    <input type="number" step="0.01" id="ledgerDebit" placeholder="Debit Amount" class="mt-1 p-2 block w-full border border-gray-300 rounded-md">
                </div>
                <div>
                    <label for="ledgerCredit" class="block text-sm font-medium text-gray-700">Credit</label>
                    <input type="number" step="0.01" id="ledgerCredit" placeholder="Credit Amount" class="mt-1 p-2 block w-full border border-gray-300 rounded-md">
                </div>
            </div>
            <button type="submit" class="mt-4 bg-blue-600 text-white py-2 px-4 rounded">Add Ledger Entry</button>
        </form>
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
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/reports/clientmgmt.blade.php ENDPATH**/ ?>