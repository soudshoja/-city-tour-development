<x-app-layout>
    <div class="bg-gray-300 font-sans leading-normal tracking-normal h-screen flex">
        <div class="w-1/4 p-6 bg-white shadow-lg overflow-y-auto">
            <h2 class="text-xl font-bold mb-4">Chart of Accounts</h2>
            <ul id="coa-list" class="space-y-2">
                <li>
                    <button class="flex justify-between w-full text-left" onclick="toggleDetails('current-assets')">
                        <span class="current_asset">Current Assets</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <ul id="current-assets" class="details pl-4">
                        <li onclick="showDetails('cash-and-cash-equivalents')" class="cursor-pointer">Cash and Cash
                            Equivalents</li>
                        <li onclick="showDetails('accounts-receivable')" class="cursor-pointer">Accounts Receivable
                            <span id="receivable-count" class="text-sm text-gray-500">(0)</span></li>
                        <ul id="accounts-receivable-list" class="details pl-4 hidden">
                            <!-- Client names will be populated here -->
                        </ul>
                        <li onclick="showDetails('prepaid-expenses')" class="cursor-pointer">Prepaid Expenses</li>
                        <li onclick="showDetails('inventory')" class="cursor-pointer">Inventory</li>
                    </ul>
                </li>
                <li>
                    <button class="flex justify-between w-full text-left" onclick="toggleDetails('liabilities')">
                        <span class="liabilities">Liabilities</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <ul id="liabilities" class="details pl-4">
                        <li onclick="showDetails('current-liabilities')" class="cursor-pointer">Current Liabilities</li>
                        <li onclick="showDetails('long-term-liabilities')" class="cursor-pointer">Long-Term Liabilities
                        </li>
                        <li onclick="showDetails('accounts-payable')" class="cursor-pointer">Accounts Payable</li>
                    </ul>
                </li>
                <li>
                    <button class="flex justify-between w-full text-left" onclick="toggleDetails('equity')">
                        <span class="equity">Equity</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <ul id="equity" class="details pl-4">
                        <li onclick="showDetails('owners-equity')" class="cursor-pointer">Owner's Equity</li>
                    </ul>
                </li>
                <li>
                    <button class="flex justify-between w-full text-left" onclick="toggleDetails('income')">
                        <span class="income">Income</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <ul id="income" class="details pl-4">
                        <li onclick="showDetails('client-payments')" class="cursor-pointer">Client Payments <span
                                id="payment-count" class="text-sm text-gray-500">(0)</span></li>
                        <li onclick="showDetails('commission-income')" class="cursor-pointer">Commission Income</li>
                    </ul>
                </li>
                <li>
                    <button class="flex justify-between w-full text-left" onclick="toggleDetails('expenses')">
                        <span class="expenses">Expenses</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <ul id="expenses" class="details pl-4">
                        <li onclick="showDetails('operating-expenses')" class="cursor-pointer">Operating Expenses</li>
                    </ul>
                </li>
            </ul>
        </div>
        <div class="w-1/2 p-6 bg-white shadow-lg">
            <h2 id="details-title" class="text-xl font-bold mb-4">Details</h2>
            <div id="details-content" class="space-y-4">
                <p>Select an invoice to view details.</p>
            </div>
        </div>

        <div id="additional-info-column" class="w-1/4 p-6 bg-white shadow-lg hidden">
            <h2 class="text-xl font-bold mb-4">Additional Info</h2>
            <div id="additional-info" class="space-y-4"></div>
        </div>

        <!-- Add Invoice Modal -->
        <div id="add-invoice-modal" class="modal hidden">
            <div class="modal-content">
                <span class="close" onclick="closeModal('add-invoice-modal')">&times;</span>
                <h2>Add New Invoice</h2>
                <form id="invoice-form">
                    <label for="client">Client</label>
                    <select id="client" class="p-2 border rounded">
                        <!-- Dynamically populate clients -->
                    </select>
                    <label for="amount">Amount</label>
                    <input type="number" id="amount" class="p-2 border rounded" required>
                    <label for="due-date">Due Date</label>
                    <input type="date" id="due-date" class="p-2 border rounded" required>
                    <label for="description">Description</label>
                    <input type="text" id="description" class="p-2 border rounded">
                    <button type="submit" class="mt-4 bg-green-500 text-white p-2 rounded">Add Invoice</button>
                </form>
            </div>
        </div>

    </div>

    <script>
    let invoices = @json($invoices);


    let accountsPayable = [{
            id: 1,
            supplier: 'Supplier A',
            amount: 200,
            dueDate: '2024-10-30',
            status: 'unpaid',
            relatedInvoiceId: 1
        },
        {
            id: 2,
            supplier: 'Supplier B',
            amount: 150,
            dueDate: '2024-11-15',
            status: 'unpaid',
            relatedInvoiceId: 2
        }
    ];

    let payments = [{
        id: 1,
        invoiceId: 1,
        amount: 500
    }];

    function updateCounts() {
        document.getElementById('receivable-count').innerText =
            `(${invoices.filter(i => i.status === 'unpaid').length})`;
        document.getElementById('payment-count').innerText = `(${payments.length})`;
    }

    function toggleDetails(id) {
        const details = document.getElementById(id);
        details.style.display = details.style.display === 'block' ? 'none' : 'block';
    }

    function showDetails(category) {
        const detailsContent = document.getElementById('details-content');
        const title = document.getElementById('details-title');

        // Clear existing highlights
        document.querySelectorAll('#coa-list li').forEach(item => {
            item.classList.remove('bg-gray-200');
        });

        // Highlight the selected item
        const selectedItem = event.target;
        selectedItem.classList.add('bg-gray-200');

        switch (category) {
            case 'cash-and-cash-equivalents':
                title.innerText = 'Cash and Cash Equivalents';
                detailsContent.innerHTML = `
                    <p style="color:black;">Details about Cash and Cash Equivalents, including the management of cash flows and liquid assets.</p>
                    <input type="text" placeholder="Add New Account" class="p-2 border rounded">
                `;
                break;
            case 'accounts-receivable':
                title.innerText = 'Accounts Receivable';
                detailsContent.innerHTML = `
                    <p>Outstanding invoices that clients owe to the agency.</p>
                    <ul class="list-disc pl-6">
                             ${invoices
                            .filter(i => i.status === 'unpaid') // Filter for unpaid invoices
                            .map(i => `<li onclick="showInvoiceDetails(${i.id})" class="cursor-pointer">${i.client.name} - ${i.invoice_number} $${i.amount} (${i.status})</li>`)
                            .join('')}
                    </ul>
                    <input type="text" placeholder="Add New Receivable" class="p-2 border rounded">
                `;

                // clientList.innerHTML = `
                //     <h3 class="mt-4">Clients with Unpaid Invoices:</h3>
                //     <ul class="list-disc pl-6">
                //         ${[...new Set(invoices.filter(i => i.status === 'unpaid').map(i => i.client.name))]
                //             .map(clientName => `<li>${clientName}</li>`)
                //             .join('')}
                //     </ul>
                // `;

                break;
            case 'prepaid-expenses':
                title.innerText = 'Prepaid Expenses';
                detailsContent.innerHTML = `
                    <p>Details about Prepaid Expenses, covering costs paid in advance for services or benefits to be received in the future.</p>
                    <input type="text" placeholder="Add New Account" class="p-2 border rounded">
                `;
                break;
            case 'inventory':
                title.innerText = 'Inventory';
                detailsContent.innerHTML = `
                    <p>Details about Inventory, including travel packages and promotional materials on hand.</p>
                    <input type="text" placeholder="Add New Account" class="p-2 border rounded">
                `;
                break;
            case 'current-liabilities':
                title.innerText = 'Current Liabilities';
                detailsContent.innerHTML = `
                    <p>Details about Current Liabilities, including obligations that need to be settled within a year.</p>
                    <input type="text" placeholder="Add New Liability" class="p-2 border rounded">
                `;
                break;
            case 'long-term-liabilities':
                title.innerText = 'Long-Term Liabilities';
                detailsContent.innerHTML = `
                    <p>Details about Long-Term Liabilities, which includes obligations due beyond one year.</p>
                    <input type="text" placeholder="Add New Liability" class="p-2 border rounded">
                `;
                break;
            case 'accounts-payable':
                title.innerText = 'Accounts Payable';
                detailsContent.innerHTML = `
                    <p>Outstanding payments due to suppliers based on client invoices.</p>
                    <ul class="list-disc pl-6">
                        ${accountsPayable.map(a => {
                            const relatedInvoice = invoices.find(i => i.id === a.relatedInvoiceId);
                            return `<li>${a.supplier} - $${a.amount} (Due: ${a.dueDate}) - ${a.status} - Related Invoice: ${relatedInvoice ? relatedInvoice.client + ' - $' + relatedInvoice.amount : 'N/A'}</li>`;
                        }).join('')}
                    </ul>
                    <input type="text" placeholder="Add New Payable" class="p-2 border rounded">
                `;
                break
            case 'owners-equity':
                title.innerText = "Owner's Equity";
                detailsContent.innerHTML = `
                    <p>Details about Owner's Equity, which represents the owner's investment in the business.</p>
                    <input type="text" placeholder="Add New Equity" class="p-2 border rounded">
                `;
                break;
            case 'client-payments':
                title.innerText = 'Client Payments';
                detailsContent.innerHTML = `
                    <p>List of payments received from clients.</p>
                    <ul class="list-disc pl-6">
                        ${payments.map(p => `<li>Payment for Invoice ID ${p.invoiceId} - $${p.amount}</li>`).join('')}
                    </ul>
                    <input type="text" placeholder="Add New Payment" class="p-2 border rounded">
                `;
                break;
            case 'commission-income':
                title.innerText = 'Commission Income';
                detailsContent.innerHTML = `
                    <p>Details about Commission Income, including earnings from supplier commissions.</p>
                    <input type="text" placeholder="Add New Income" class="p-2 border rounded">
                `;
                break;
            case 'operating-expenses':
                title.innerText = 'Operating Expenses';
                detailsContent.innerHTML = `
                    <p>Details about Operating Expenses, encompassing all costs of running the agency.</p>
                    <input type="text" placeholder="Add New Expense" class="p-2 border rounded">
                `;
                break;
            default:
                title.innerText = 'Details';
                detailsContent.innerHTML = `<p>Select a category from the left to view details.</p>`;
                break;
        }
    }

    function openModal(modalId) {
        document.getElementById(modalId).classList.remove('hidden');
    }

    function closeModal(modalId) {
        document.getElementById(modalId).classList.add('hidden');
    }

    document.getElementById('invoice-form').addEventListener('submit', function(event) {
        event.preventDefault();
        const client = document.getElementById('client').value;
        const amount = document.getElementById('amount').value;
        const dueDate = document.getElementById('due-date').value;
        const description = document.getElementById('description').value;

        // Add new invoice to the invoices array
        invoices.push({
            id: invoices.length + 1,
            client: {
                name: client
            },
            amount: Number(amount),
            status: 'unpaid',
            dueDate,
            description
        });

        closeModal('add-invoice-modal');
        updateCounts();
        showDetails('accounts-receivable');
    });

    function showInvoiceDetails(invoiceId) {
        const invoice = invoices.find(i => i.id === invoiceId);
        const detailsContent = document.getElementById('details-content');
        const title = document.getElementById('details-title');
        const additionalInfo = document.getElementById('additional-info');
        const additionalInfoColumn = document.getElementById('additional-info-column');

        title.innerText = `Invoice Details for ${invoice.client.name}`;

        // Populate additional info section
        additionalInfo.innerHTML = `
            Invoice Details for ${invoice.client.name}
            <p><strong>Invoice ID:</strong> ${invoice.id}</p>
            <p><strong>Invoice Number:</strong> ${invoice.invoice_number}</p>
            <p><strong>Total Amount:</strong> $${invoice.amount}</p>
            <p><strong>Status:</strong> ${invoice.status}</p>
            <h3>Services Rendered</h3>
            <ul class="list-disc pl-6">
                ${(invoice.services && invoice.services.length > 0)
                    ? invoice.services.map(s => `<li>${s.description} - $${s.price}</li>`).join('')
                    : '<li>No services rendered for this invoice.</li>'}
            </ul>
            <h3>Payment Status</h3>
            <p>${invoice.paymentStatus ? 'Paid' : 'Unpaid'}</p>
            <button onclick="markAsPaid(${invoice.id})" class="mt-4 bg-green-500 text-white p-2 rounded">Mark as Paid</button>
        `;

        // Show the additional info column if there's content
        if (additionalInfo.innerHTML.trim() !== '') {
            additionalInfoColumn.classList.remove('hidden');
        } else {
            additionalInfoColumn.classList.add('hidden');
        }
    }


    // Initialize counts
    updateCounts();
    </script>
    <style>
    body {
        font-family: 'Arial', sans-serif;
    }

    .modal {
        position: fixed;
        z-index: 1;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.5);
    }

    .modal-content {
        background-color: #ffffff;
        margin: 15% auto;
        padding: 20px;
        border: 1px solid #888;
        width: 80%;
        border-radius: 8px;
    }

    .close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
    }

    .close:hover,
    .close:focus {
        color: #000;
        text-decoration: none;
        cursor: pointer;
    }

    h2 {
        color: #4A90E2;
        /* Header Color */
        font-size: 1.75rem;
        /* Larger font size for headers */
        margin-bottom: 1rem;
    }

    p,
    li {
        font-size: 1.125rem;
        /* Bigger font size for paragraphs */
        line-height: 1.5;
        color: #333;
        /* Darker text for readability */
    }

    .bg-gray-100 {
        background-color: #f7f9fc;
        /* Light background for the main layout */
    }

    .bg-white {
        background-color: #ffffff;
    }

    .bg-green-500 {
        background-color: #4CAF50;
        /* Green color for buttons */
    }

    .bg-green-500:hover {
        background-color: #45a049;
        /* Darker green on hover */
    }

    .p-2 {
        padding: 0.5rem;
    }

    .p-6 {
        padding: 1.5rem;
    }

    .border {
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .text-sm {
        font-size: 0.875rem;
    }

    .details {
        display: none;
        margin-top: 0.5rem;
    }

    .cursor-pointer {
        cursor: pointer;
    }

    .space-y-4>*+* {
        margin-top: 1rem;
    }

    /* Additional styling for lists and buttons */
    ul {
        list-style-type: none;
        padding-left: 0;
    }

    li {
        padding: 0.5rem;
        transition: background-color 0.2s ease;
    }

    li:hover {
        background-color: #e0e0e0;
        /* Light gray on hover */
    }

    button {
        padding: 0.5rem 1rem;
        border-radius: 4px;
        border: none;
        color: white;
        font-size: 1rem;
        /* Button font size */
        cursor: pointer;
    }

    .bg-gray-200 {
        background-color: #d1d5db;
        /* Highlight color for selected items */
    }

    .current_asset {
        color: #4CAF50;
        font-size: 20px;
    }

    .liabilities {
        color: #2196F3;
        font-size: 20px;
    }

    .equity {
        color: #FF9800;
        font-size: 20px;
    }

    .income {
        color: #9C27B0;
        font-size: 20px;
    }

    .expenses {
        color: #F44336;
        font-size: 20px;
    }
    </style>

</x-app-layout>