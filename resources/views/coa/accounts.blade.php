<x-app-layout>


    <script>
    document.addEventListener('DOMContentLoaded', function() {
        calculateTotals();
    });

    function filterAccounts(query) {
        // Get all list items (the account categories in the sidebar)
        const items = document.querySelectorAll('#coa-list > li');

        // Convert the query to lowercase to make the search case-insensitive
        const lowerCaseQuery = query.toLowerCase();

        // Loop through each list item (account category)
        items.forEach(item => {
            // Get the category name (the span inside the button)
            const accountName = item.querySelector('span').textContent.toLowerCase();

            // Check if the account name includes the query string
            if (accountName.includes(lowerCaseQuery)) {
                item.style.display = 'block'; // Show the item if it matches
            } else {
                item.style.display = 'none'; // Hide the item if it doesn't match
            }
        });
    }

    // Function for received  and spented payments

    function calculateTotals() {
        // Calculate total received from the payments array
        const totalReceived = payments.reduce((sum, payment) => sum + payment.amount, 0);

        // Calculate total spent from the accountsPayable array
        const totalSpent = accountsPayable.reduce((sum, payable) => sum + payable.amount, 0);

        // Calculate the available balance
        const availableBalance = totalReceived - totalSpent;

        // Display the totals in the respective elements by their IDs
        document.getElementById('total-received').textContent = `$${totalReceived.toFixed(2)}`;
        document.getElementById('total-spent').textContent = `$${totalSpent.toFixed(2)}`;

        // Display the available balance in the wallet balance section
        document.getElementById('wallet-balance').textContent = `$${availableBalance.toFixed(2)}`;
    }
    </script>

    <!-- Breadcrumbs -->
    <ul class="flex space-x-2  pb-5 text-base md:text-lg sm:text-sm">
        <li>
            <a href="{{ route('dashboard') }}" class="customBlueColor hover:underline">Dashboard</a>
        </li>
        <li class="before:content-['/'] before:mr-1 ">
            <span>Chart of Account</span>
        </li>
    </ul>
    <!-- ./Breadcrumbs -->



    @if(isset($error))
    <div class="alert alert-danger">{{ $error }}</div>
    @else
    <!-- Display your content as usual -->
    @endif



    <div class="font-sans leading-normal tracking-normal flex flex-shrink-0">
        <div class="w-1/4 p-6 bg-gray-300 rounded-lg shadow-lg overflow-y-auto m-2">
            <!-- Search Input Field -->
            <div class="COA"> <input type="text" style="background-color: #23327a47;"
                    class="text-black w-full p-2 border rounded-lg mb-4" placeholder="Search..."
                    onkeyup="filterAccounts(this.value)"></div>




            <ul id="coa-list" class="space-y-2">
                <li>
                    <button class="flex justify-between w-full text-left" onclick="toggleDetails('current-assets')">
                        <span class="txtDarkColor">Current Assets</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <ul id="current-assets" class="details pl-4">
                        <li onclick="showDetails('cash-and-cash-equivalents')" class="cursor-pointer">Cash and Cash
                            Equivalents</li>
                        <li onclick="showDetails('accounts-receivable')" class="cursor-pointer">Accounts Receivable
                            <span id="receivable-count" class="text-sm text-gray-500">(0)</span>
                        </li>
                        <ul id="accounts-receivable-list" class="details pl-4 hidden">
                            <!-- Client names will be populated here -->
                        </ul>
                        <li onclick="showDetails('prepaid-expenses')" class="cursor-pointer">Prepaid Expenses</li>
                        <li onclick="showDetails('inventory')" class="cursor-pointer">Inventory</li>
                    </ul>
                </li>
                <li>
                    <button class="flex justify-between w-full text-left" onclick="toggleDetails('liabilities')">
                        <span class="txtDarkColor">Liabilities</span>
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
                        <span class="txtDarkColor">Equity</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <ul id="equity" class="details pl-4">
                        <li onclick="showDetails('owners-equity')" class="cursor-pointer">Owner's Equity</li>
                    </ul>
                </li>
                <li>
                    <button class="flex justify-between w-full text-left" onclick="toggleDetails('income')">
                        <span class="txtDarkColor">Income</span>
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
                        <span class="txtDarkColor">Expenses</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <ul id="expenses" class="details pl-4">
                        <li onclick="showDetails('operating-expenses')" class="cursor-pointer">Operating Expenses</li>
                    </ul>
                </li>
            </ul>
        </div>
        <div class="w-1/2 m-2">
            <div class="mb-3 bg-gray-300 rounded-lg shadow-lg">
                <div class="panel h-full overflow-hidden border-0 p-0">
                    <div class="min-h-[190px] bg-gradient-to-r from-[#4361ee] to-[#160f6b] p-6">
                        <div class="mb-6 flex items-center justify-between">
                            <div class="flex items-center rounded-full bg-black/50 p-1 font-semibold text-white pr-3 ">
                                <x-application-logo
                                    class="block h-8 w-8 rounded-full border-2 border-white/50 object-cover mr-1 " />

                                <h3 class="px-2">{{ Auth::user()->name }}</h3>
                            </div>
                            <button type="button"
                                class="flex h-9 w-9 items-center justify-between rounded-md bg-black text-white hover:opacity-80 ml-auto ">
                                <svg class="m-auto h-6 w-6" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"
                                    fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                </svg>
                            </button>
                        </div>
                        <div class="flex items-center justify-between">
                            <p class="text-xl text-white">Wallet Balance</p>
                            <h5 class="text-2xl ml-auto text-white">
                                <span id="wallet-balance">0.00</span>
                                <!-- This will be updated with the calculated balance -->
                            </h5>
                        </div>

                    </div>
                    <div class="mb-5 -mt-12 grid grid-cols-2 gap-2 px-8">
                        <div class="rounded-md bg-white px-4 py-2.5 shadow dark:bg-[#060818]">
                            <span class="mb-4 flex items-center justify-between dark:text-white">Received
                                <svg class="h-4 w-4 text-success" viewBox="0 0 24 24" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M19 15L12 9L5 15" stroke="currentColor" stroke-width="1.5"
                                        stroke-linecap="round" stroke-linejoin="round"></path>
                                </svg>
                            </span>
                            <div id="total-received"
                                class="btn w-full border-0 bg-[#ebedf2] py-1 text-base text-[#515365] shadow-none dark:bg-black dark:text-[#bfc9d4]">
                                $0.00
                            </div>
                        </div>

                        <div class="rounded-md bg-white px-4 py-2.5 shadow dark:bg-[#060818]">
                            <span class="mb-4 flex items-center justify-between dark:text-white">Spent
                                <svg class="h-4 w-4 text-danger" viewBox="0 0 24 24" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M19 9L12 15L5 9" stroke="currentColor" stroke-width="1.5"
                                        stroke-linecap="round" stroke-linejoin="round"></path>
                                </svg>
                            </span>
                            <div id="total-spent"
                                class="btn w-full border-0 bg-[#ebedf2] py-1 text-base text-[#515365] shadow-none dark:bg-black dark:text-[#bfc9d4]">
                                $0.00
                            </div>
                        </div>

                    </div>

                </div>
            </div>
            <div class="MaxCOAHight overflow-y-auto p-6 bg-gray-300 rounded-lg shadow-lg h-screen">
                <h2 id="details-title" class="text-xl font-bold mb-4">Details</h2>
                <div id="details-content" class="space-y-4">
                    <p>Select an invoice to view details.</p>
                </div>
            </div>

        </div>

        <div id="additional-info-column" class="w-1/4 p-6 bg-gray-300 rounded-lg shadow-lg hidden m-2">
            <h2 class="text-xl font-bold mb-4 customBlueColor">Additional Info</h2>
            <div id="additional-info" class="space-y-4"></div>
        </div>

        <!-- Add Invoice Modal -->
        <div id="add-invoice-modal" class="modal hidden ">
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
                `;
                break;
            case 'inventory':
                title.innerText = 'Inventory';
                detailsContent.innerHTML = `
                    <p>Details about Inventory, including travel packages and promotional materials on hand.</p>
                `;
                break;
            case 'current-liabilities':
                title.innerText = 'Current Liabilities';
                detailsContent.innerHTML = `
                    <p>Details about Current Liabilities, including obligations that need to be settled within a year.</p>
                `;
                break;
            case 'long-term-liabilities':
                title.innerText = 'Long-Term Liabilities';
                detailsContent.innerHTML = `
                    <p>Details about Long-Term Liabilities, which includes obligations due beyond one year.</p>
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
                `;
                break
            case 'owners-equity':
                title.innerText = "Owner's Equity";
                detailsContent.innerHTML = `
                    <p>Details about Owner's Equity, which represents the owner's investment in the business.</p>
                `;
                break;
            case 'client-payments':
                title.innerText = 'Client Payments';
                detailsContent.innerHTML = `
                    <p>List of payments received from clients.</p>
                    <ul class="list-disc pl-6">
                        ${payments.map(p => `<li>Payment for Invoice ID ${p.invoiceId} - $${p.amount}</li>`).join('')}
                    </ul>
                `;
                break;
            case 'commission-income':
                title.innerText = 'Commission Income';
                detailsContent.innerHTML = `
                    <p>Details about Commission Income, including earnings from supplier commissions.</p>
                `;
                break;
            case 'operating-expenses':
                title.innerText = 'Operating Expenses';
                detailsContent.innerHTML = `
                    <p>Details about Operating Expenses, encompassing all costs of running the agency.</p>
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

    /* li:hover {
        background-color: #e0e0e0;
        /* Light gray on hover */


    */ button {
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
    </style>

</x-app-layout>