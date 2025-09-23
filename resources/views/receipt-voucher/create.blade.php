<x-app-layout>
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
    </style>

    <!-- start main content section -->
    <div class="panel h-full overflow-hidden border-0 p-0">
        <div class="min-h-[80px] bg-gradient-to-r from-[#160f6b] to-[#4361ee] p-6 flex items-center text-white">
            <div class="flex items-center justify-between text-white">
                <p class="text-2xl">Receipt Voucher</p>
                <h5 class="text-2xl ltr:mr-auto rtl:mr-auto"></h5>
            </div>
        </div>
        <form method="POST" action="{{ route('receipt-voucher.store') }}">
            @csrf
            <div class="flex flex-col gap-2.5 xl:flex-row">
                <div class="panel flex-1 px-0 py-6 ltr:lg:mr-6 rtl:lg:ml-6">
                    <div class="flex flex-wrap justify-between px-4">

                        <div class="mb-6 w-full lg:w-1/2">
                            <div class="mt-6 space-y-1 text-gray-800 dark:text-gray-400">
                                <x-application-logo class="custom-logo-size" />
                                @if ($companies)
                                <div class="pl-2">
                                    <h3>{{ $companies->name }}</h3>
                                    <p>{!! nl2br(e($companies->address)) !!}</p>
                                    <p>{{ $companies->email }}</p>
                                    <p>{{ $companies->phone }}</p>
                                </div>
                                @else
                                <div class="custom-select w-full border rounded-lg mt-4">
                                    <div class="select-trigger px-4 py-2 cursor-pointer dark:text-white">Select
                                        Company
                                    </div>
                                    <div
                                        class="select-options hidden absolute left-0 top-full w-full rounded-md shadow-lg grid {{ count($branches) === 1 ? 'grid-cols-1' : 'grid-cols-2' }} gap-2 py-3">
                                        @foreach ($companies as $company)
                                        <div class="select-option px-4 py-3 text-center bg-white dark:bg-gray-700 BoxShadow rounded-lg dark:hover:bg-gray-800 border border-gray-300 cursor-pointer"
                                            data-value="{{ $company->id }}">
                                            {{ $company->name }}
                                        </div>
                                        @endforeach
                                    </div>

                                </div>

                                @endif
                                <input type="hidden" id="company_id" name="company_id" value="{{ $companies->id }}">
                            </div>
                        </div>
                        <div class="w-full lg:w-1/2 lg:max-w-fit mt-5">
                            <div class="flex items-center gap-x-4">
                                <label for="receiptvoucherref" class="mb-0 flex-1 ltr:mr-2 rtl:ml-2">Receipt
                                    Ref <span class="text-red-500">*</span></label>
                                <input type="text" readonly value="RV-{{ now()->timestamp }}"
                                    class="form-input w-2/3 lg:w-[250px]  bg-gray-200" />
                                <input type="hidden" name="receiptvoucherref" value="RV-{{ now()->timestamp }}" required>

                            </div>
                            <!-- <div class="flex items-center gap-x-4 mt-4">
                                <label for="receiptvouchertype" class="mb-0 flex-1 ltr:mr-2 rtl:ml-2">
                                    Receipt Type <span class="text-red-500">*</span>
                                </label>
                                <select required id="receiptvouchertype" name="receiptvouchertype"
                                    class="form-select w-2/3 lg:w-[250px] bg-white text-gray-700 border-gray-300"
                                    onchange="toggleRefundDatalist()">
                                    <option value="">Choose One</option>
                                    <option value="Payment">Receipt</option>
                                    <option value="PaymentByDate">Receipt by Date</option>
                                    <option value="Refund">Refund</option>
                                </select>
                            </div> -->
                            <div class="flex items-center gap-x-4 mt-4">
                                <label for="receiptvouchertype" class="mb-0 flex-1 ltr:mr-2 rtl:ml-2">
                                    Receipt Type <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="receiptvouchertype" name="receiptvouchertype"
                                    class="form-input w-2/3 lg:w-[250px] bg-gray-200 text-gray-700 border-gray-300"
                                    value="Receipt" readonly required>
                            </div>
                            <div id="lastSearchInfo" class="text-muted ml-30" style="display: none;"></div>
                            <div id="refundNumberField" class="flex items-center gap-x-4 mt-4 hidden">
                                <label for="refund_number" class="mb-0 flex-1 ltr:mr-2 rtl:ml-2">
                                    Refund Number <span class="text-red-500">*</span>
                                </label>
                                <input required list="refundList" name="refund_number" id="refund_number"
                                    class="form-input w-2/3 lg:w-[250px] bg-white text-gray-700 border border-gray-300"
                                    placeholder="Search refund number..." />
                                <datalist id="refundList">
                                    @foreach ($refundNumbers as $refund)
                                    <option value="{{ $refund->refund_number }}"></option>
                                    @endforeach
                                </datalist>
                            </div>
                            <div class="flex items-center gap-x-4 mt-4">
                                <label for="branch_id" class="mb-0 flex-1 ltr:mr-2 rtl:ml-2">Branch <span
                                        class="text-red-500">*</span></label>
                                <select required id="branch_id" name="branch_id" class="form-input w-2/3 lg:w-[250px]">
                                    <option value="">Select Branch</option>
                                    @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" {{ old('branch_id') == $branch->id ? 'selected' : '' }}>
                                        {{ $branch->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mt-4 flex items-center gap-x-4">
                                <label for="docdate" class="mb-0 flex-1 ltr:mr-2 rtl:ml-2">Doc Date <span
                                        class="text-red-500">*</span></label>
                                <input required id="docdate" type="date" name="docdate"
                                    class="form-input w-2/3 lg:w-[250px]" value="{{ old('docdate') }}" />
                            </div>
                            <input type="hidden" id="total_payment" name="total_payment" value="" readonly>
                        </div>
                    </div>

                    <hr class="my-6 border-[#e0e6ed] dark:border-[#1b2e4b]" />

                    <div class="mt-8 px-4">
                        <div class="flex flex-col justify-between lg:flex-row gap-x-4">
                            <div class="mb-6 w-full lg:w-1/2 ltr:lg:mr-6 rtl:lg:ml-6">
                                <div class="text-lg font-semibold">Receipt Voucher</div>
                                <div class="mt-4 flex items-center gap-x-4">
                                    <label for="pay_to_payee" class="mb-0 w-1/3 ltr:mr-2 rtl:ml-2">Receive from <span
                                            class="text-red-500">*</span></label>
                                    <input required id="pay_to" type="text" name="pay_to"
                                        class="form-input flex-1" list="clientList"
                                        placeholder="Search client name..." value="{{ old('pay_to') }}" />
                                    <datalist id="clientList">
                                        @foreach ($clients as $client)
                                        <option value="{{ $client->full_name }}">
                                            {{ $client->email }}
                                        </option>
                                        @endforeach
                                    </datalist>
                                </div>
                            </div>
                            <div class="w-full lg:w-1/2">
                                <div class="text-lg font-semibold">Remarks</div>

                                <div class="mt-4 flex items-center gap-x-4">
                                    <label for="remarks_create_label" class="mb-0 w-1/3 ltr:mr-2 rtl:ml-2">Remarks
                                        <span class="text-red-500">*</span></label>
                                    <input required id="remarks_create" type="text" name="remarks_create"
                                        class="form-input flex-1" placeholder="Enter Remarks" value="{{ old('remarks_create') }}" />
                                </div>

                                <div class="mt-4 flex items-center gap-x-4">
                                    <label for="internal_remarks" class="mb-0 w-1/3 ltr:mr-2 rtl:ml-2">Internal
                                        Remarks</label>
                                    <input id="internal_remarks" type="text" name="internal_remarks"
                                        class="form-input flex-1" placeholder="Enter Internal Remarks" value="{{ old('internal_remarks') }}" />
                                </div>

                                <div class="mt-4 flex items-center gap-x-4">
                                    <label for="remarks_fl" class="mb-0 w-1/3 ltr:mr-2 rtl:ml-2">Remarks
                                        FL</label>
                                    <input id="remarks_fl" type="text" name="remarks_fl"
                                        class="form-input flex-1" placeholder="Enter Remarks FL" value="{{ old('remarks_fl') }}" />
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="table table-bordered bank-payment-table mt-10 w-full">
                            <thead class="table-light">
                                <tr>
                                    <th colspan="2">
                                        A/C / Invoice Number / Client Credit
                                    </th>
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
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="paymentTable">
                            </tbody>
                        </table>
                        <!-- Add New Record Button (below table with spacing) -->
                        <div class="add-record-container mb-10 ml-4">
                            <button type="button" class="btn btn-primary" id="addItem">+ Add New Record</button>
                        </div>

                    </div>

                    <div class="panel overflow-hidden">
                        <div class="relative">
                            <div class="grid grid-cols-2 gap-6 md:grid-cols-3">
                                <div class="mt-2">
                                    <div class="text-primary">Total Debit</div>
                                    <div class="mt-2 text-2xl font-semibold">
                                        <span id="total_debit">0.00</span>
                                    </div>
                                </div>

                                <div class="mt-2">
                                    <div class="text-primary">Total Credit</div>
                                    <div class="mt-2 text-2xl font-semibold">
                                        <span id="total_credit">0.00</span>
                                    </div>
                                </div>

                                <div class="mt-2">
                                    <div class="text-primary">Difference</div>
                                    <div class="mt-2 text-2xl font-semibold">
                                        <span id="total_difference">0.00</span>
                                    </div>
                                </div>

                            </div>
                            <div class="absolute -bottom-12 right-12 h-36 w-36"> <!-- Increased parent div size -->
                                <svg id="correct" width="36" height="36" viewBox="0 0 24 24"
                                    fill="none" xmlns="http://www.w3.org/2000/svg"
                                    class="h-full w-full text-success opacity-20">
                                    <circle opacity="0.5" cx="12" cy="12" r="10"
                                        stroke="currentColor" stroke-width="1.5" />
                                    <path d="M8.5 12.5L10.5 14.5L15.5 9.5" stroke="currentColor" stroke-width="1.5"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>

                                <svg id="false" width="36" height="36" viewBox="0 0 24 24"
                                    fill="none" xmlns="http://www.w3.org/2000/svg"
                                    class="h-full w-full text-danger opacity-20">
                                    <circle opacity="0.5" cx="12" cy="12" r="10"
                                        stroke="currentColor" stroke-width="1.5" />
                                    <path d="M12 7V13" stroke="currentColor" stroke-width="1.5"
                                        stroke-linecap="round" />
                                    <circle cx="12" cy="16" r="1" fill="currentColor" />
                                </svg>
                            </div>


                        </div>
                    </div>


                    <div class="mt-6 flex justify-between px-4">
                        <!-- Left side: Cancel button -->
                        <a href="{{ route('receipt-voucher.index') }}"
                            class="btn btn-secondary px-6 py-2 w-40 rounded-lg text-center bg-gray-200 hover:bg-gray-300 text-gray-700">
                            Cancel
                        </a>

                        <!-- Right side: Save and Reset -->
                        <div class="flex gap-4 ml-5">
                            <button type="reset" class="btn btn-warning px-6 py-2 w-40 rounded-lg">Reset</button>
                            <button id="save-paymentvoucher-btn" type="submit"
                                class="btn btn-success px-6 py-2 w-40 rounded-lg flex items-center justify-center gap-2">
                                <span id="iconSavePaymentVoucher" class="mr-2 inline-block">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1" />
                                    </svg>
                                </span>
                                <span id="textSavePaymentVoucher">Save</span>
                            </button>

                        </div>
                    </div>
                </div>
            </div>
        </form>
        <!-- end main content section -->
    </div>

    <div id="paymentByDateModal"
        class="fixed inset-0 bg-gray-800 bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
        <div class="bg-white rounded-lg p-4 sm:p-6 w-full max-w-3xl shadow-xl">
            <h2 class="text-lg font-bold mb-4">Select Payments by Date</h2>

            <div class="flex flex-col sm:flex-row sm:gap-4 gap-2 mb-4">
                <div class="w-full sm:w-1/3">
                    <label class="block text-sm font-medium">Date From:</label>
                    <input type="date" id="dateFrom"
                        class="border border-gray-300 rounded w-full px-2 py-1 h-10" />
                </div>
                <div class="w-full sm:w-1/3">
                    <label class="block text-sm font-medium">Date To:</label>
                    <input type="date" id="dateTo"
                        class="border border-gray-300 rounded w-full px-2 py-1 h-10" />
                </div>
                <div class="w-full sm:w-1/3">
                    <label class="block text-sm font-medium">Supplier:</label>
                    <input required id="supplierName" type="text" name="supplierName"
                        class="border border-gray-300 rounded w-full px-2 py-1 h-10" list="clientList"
                        placeholder="Search supplier name..." />
                    <datalist id="clientList">
                        @foreach ($clients as $client)
                        <option value="{{ $client->name }}">
                            [{{ $client->root->name ?? 'N/A' }}] [{{ $client->code }}] {{ $client->name }}
                        </option>
                        @endforeach
                    </datalist>
                </div>
            </div>

            <div class="mb-4">
                <button onclick="loadJournalEntries()"
                    class="bg-blue-600 text-white px-4 py-2 rounded w-full sm:w-auto">Search</button>
            </div>
            <div id="totalOutstandingBalance" class="text-right text-sm font-medium text-blue-700 mt-2 hidden">
                Total Outstanding Balance: KWD <span id="outstandingAmount">0.00</span>
            </div>
            <div id="recordsContainer" class="text-sm overflow-x-auto">
                <p class="text-gray-500">Select a date range and click Search to load entries.</p>
            </div>

            <div class="mt-6 flex flex-col sm:flex-row justify-end gap-2">
                <button onclick="closeModal()" class="bg-gray-300 px-4 py-2 rounded w-full sm:w-auto">Close</button>
                <button onclick="submitSelectedPayments()"
                    class="bg-blue-600 text-white px-4 py-2 rounded w-full sm:w-auto">Add Selected</button>
            </div>
        </div>
    </div>


    <script>
        const suppliers = @json($suppliers);
        const lastLevelAccounts = @json($lastLevelAccounts);

        let items = [];

        document.addEventListener("DOMContentLoaded", function() {
            const paymentTable = document.getElementById("paymentTable");
            const totalDebitEl = document.getElementById("total_debit");
            const totalCreditEl = document.getElementById("total_credit");
            const totalDifferenceEl = document.getElementById("total_difference");
            const addItemButton = document.getElementById("addItem");
            const totalPaymentInput = document.getElementById('total_payment');

            function addItem() {
                let id = crypto.randomUUID();
                let item = {
                    id,
                    invoice_number: "",
                    client_name: "",
                    ac_code: "",
                    account_id: "",
                    transaction_id: "",
                    remarks: "",
                    currency: "KWD",
                    exchange_rate: 1.0,
                    amount: 0,
                    debit: 0,
                    credit: 0,
                    cheque_no: "",
                    cheque_date: "",
                    bank_name: "",
                    branch: "",
                    auth_no: "",
                    balance: 0,
                    type_reference_id: "",
                    type_selector: "none",
                    client_id: "",
                };

                items.push(item);
                renderTable();
            }

            window.removeItem = function(index) {
                // Get the Receipt type from the dropdown
                const type = document.getElementById('receiptvouchertype').value;

                // If the Receipt type is 'PaymentByDate', reset the table and arrays when one item is removed
                if (type === 'PaymentByDate') {
                    // Reset the selectedJournalIds array
                    selectedJournalIds = [];

                    items = [];

                    // Re-render the table with no records
                    renderTable();

                    alert("All records have been reset and do re-select the record if you want to continue.");
                } else {
                    // For other Receipt types, continue normal removal
                    if (items.length > 1) {
                        // Remove the item at the given index
                        items.splice(index, 1);
                        renderTable();
                    } else {
                        alert("At least one record is required.");
                    }
                }
            };
            window.toggleAccountClientInput = function(select, index) {
                items[index].type_selector = select.value;
                renderTable();
            };

            window.updateField = function(index, field, value) {
                if (["debit", "credit", "amount", "exchange_rate", "balance"].includes(field)) {
                    items[index][field] = parseFloat(value).toFixed(2) || "0.00";
                    items[index][field] = parseFloat(items[index][field]); // Convert back to number
                } else {
                    items[index][field] = value;
                }
                updateTotals();
            };

            function updateTotals() {
                let totalDebit = items.reduce((sum, item) => sum + (parseFloat(item.debit) || 0), 0);
                let totalCredit = items.reduce((sum, item) => sum + (parseFloat(item.credit) || 0), 0);
                let totalDifference = totalDebit - totalCredit;

                totalDebitEl.textContent = totalDebit.toFixed(2);
                totalCreditEl.textContent = totalCredit.toFixed(2);
                totalDifferenceEl.textContent = totalDifference.toFixed(2);

                const totalPaymentInput = document.getElementById('total_payment');
                if (totalPaymentInput) {
                    totalPaymentInput.value = totalDifference.toFixed(2);
                }

                updateDifference(totalDifference);
            }


            function selectedAccName(input, index) {
                const selectedText = input.value.trim();

                // Match format: [CODE] NAME
                const match = selectedText.match(/^\[(.+?)\]\s+(.+)$/);
                if (!match) {
                    items[index].ac_code = null;
                    document.getElementById(`selectedAccName_${index}`).innerText = '';
                    document.getElementById(`account_id_${index}`).value = '';
                    return;
                }

                const selectedCode = match[1];
                const selectedName = match[2];

                const acc = lastLevelAccounts.find(a => a.code === selectedCode && a.name === selectedName);

                if (acc) {
                    items[index].ac_code = acc.id;
                    document.getElementById(`selectedAccName_${index}`).innerText =
                        `[${acc.root ? acc.root.name : 'No Root'}] [${acc.code}] ${acc.name}`;
                    document.getElementById(`account_id_${index}`).value = acc.id;
                } else {
                    items[index].ac_code = null;
                    document.getElementById(`selectedAccName_${index}`).innerText = '';
                    document.getElementById(`account_id_${index}`).value = '';
                }
            }


            window.selectedAccName = selectedAccName; // Make function globally available

            function updateDifference(value) {
                let differenceElement = document.getElementById("total_difference");
                let correctIcon = document.getElementById("correct");
                let falseIcon = document.getElementById("false");

                // Update the displayed total difference
                differenceElement.textContent = value.toFixed(2);

                // Show/hide icons based on the value
                if (value < 0) {
                    falseIcon.style.display = "block";
                    correctIcon.style.display = "none";
                } else {
                    falseIcon.style.display = "none";
                    correctIcon.style.display = "block";
                }
            }

            function renderTable() {
                paymentTable.innerHTML = "";
                items.forEach((item, index) => {
                    const row = document.createElement("tr");

                    const accountOptions = lastLevelAccounts.map(acc =>
                        `<option value="[${acc.code}] ${acc.name}">[${acc.root ? acc.root.name : 'No Root'}] [${acc.code}] ${acc.name}</option>`
                    ).join('');

                    const selectedAcc = lastLevelAccounts.find(acc => acc.id == item.ac_code);
                    const selectedAccDisplay = selectedAcc ?
                        `[${selectedAcc.root ? selectedAcc.root.name : 'No Root'}] [${selectedAcc.code}] ${selectedAcc.name}` :
                        '';
                    row.innerHTML = `
                    <td colspan="2" style="min-width:300px;">
                        <div style="display: flex; gap: 8px;">
                        
                            <!-- Account/Invoice/Client Dropdown -->
                                <select required class="form-control form-control-sm" name="items[${index}][type_selector]" onchange="toggleAccountClientInput(this, ${index})" style="flex: 0 0 120px;">
                                    <option value="none" ${(item.type_selector === 'none') ? 'selected' : ''}>-- Select Type --</option>
                                    <option value="invoice" ${item.type_selector === 'invoice' ? 'selected' : ''}>Invoice Number</option>
                                    <option value="account" ${item.type_selector === 'account' ? 'selected' : ''}>Account Name</option>
                                    <option value="client" ${item.type_selector === 'client' ? 'selected' : ''}>Client Credit</option>
                                </select>
                                        <!-- Account/Client Input -->
                                <div id="accountClientInput_${index}" style="flex: 1 1 8%;">
                                    ${
                                        item.type_selector === 'none'
                                            ? `<input required type="text" class="form-control form-control-sm"
                                                name="items[${index}][manual_entry]"
                                                value="${item.manual_entry || ''}"
                                                placeholder="Select a Type First..."
                                                readonly>`
                                        : item.type_selector === 'invoice'
                                        ? `<input required list="invoiceList_${index}" class="form-control form-control-sm"
                                                name="items[${index}][invoice_number_display]"
                                                value="${item.invoice_number_display || ''}"
                                                placeholder="Select invoice..."
                                                oninput="handleInvoiceInput(this, ${index})">
                                                <input type="hidden" name="items[${index}][invoice_id]" id="invoice_id_${index}" value="${item.invoice_id || ''}">
                                            <datalist id="invoiceList_${index}">
                                                @foreach ($unpaidInvoices as $inv)
                                                    <option value="[{{ $inv->invoice_number }}] {{ $inv->client->full_name ?? '' }}" data-id="{{ $inv->id }}"></option>
                                                @endforeach
                                            </datalist>`
                                        
                                        : item.type_selector === 'client'
                                        ? `<input required list="clientList_${index}" class="form-control form-control-sm"
                                                name="items[${index}][client_name]"
                                                value="${item.client_name || ''}"
                                                placeholder="Enter client name..."
                                                oninput="updateField(${index}, 'client_name', this.value); setClientId(${index}, this.value)">
                                                <input type="hidden" name="items[${index}][client_id]" id="client_id_${index}" value="${item.client_id || ''}">
                                            
                                            <datalist id="clientList_${index}">
                                                @foreach ($clients as $client)
                                                    <option value="{{ $client->full_name }}" data-id="{{ $client->id }}">
                                                        {{ $client->email }}
                                                    </option>
                                                @endforeach
                                            </datalist>`
                                        
                                        : `<input required list="accountList_${index}" class="form-control form-control-sm"
                                                    name="items[${index}][ac_code]"
                                                    value="${item.ac_code || ''}"
                                                    placeholder="Search account..."
                                                    oninput="selectedAccName(this, ${index})">
                                                
                                                <datalist id="accountList_${index}">
                                                    ${accountOptions}
                                                </datalist>
                                        
                                        <small id="selectedAccName_${index}" class="text-muted">
                                            ${selectedAccDisplay}
                                        </small>
                                        
                                        <input type="hidden" name="items[${index}][account_id]" id="account_id_${index}" value="${selectedAcc ? selectedAcc.id : ''}">
                                        <input type="hidden" name="items[${index}][transaction_id]" value="${item.transaction_id}">`
                                    }
                                </div>
                        </div>
                    </td>
                    <td style="vertical-align: top;"><input required type="text" class="form-control form-control-sm" name="items[${index}][remarks]" value="${item.remarks}" oninput="updateField(${index}, 'remarks', this.value)"></td>
                    <td style="vertical-align: top;">
                        <select required class="form-control form-control-sm text-left" name="items[${index}][currency]" onchange="updateField(${index}, 'currency', this.value)">
                            <option ${item.currency === "KWD" ? "selected" : ""}>KWD</option>
                            <option ${item.currency === "USD" ? "selected" : ""}>USD</option>
                            <option ${item.currency === "GBP" ? "selected" : ""}>GBP</option>
                        </select>
                    </td>
                    <td style="vertical-align: top;"><input required type="number" step="0.01" class="form-control form-control-sm" name="items[${index}][exchange_rate]" value="${item.exchange_rate}" oninput="updateField(${index}, 'exchange_rate', this.value)"></td>
                    <td style="vertical-align: top;"><input required type="number" step="0.01" class="form-control form-control-sm" name="items[${index}][amount]" value="${item.amount}" oninput="updateField(${index}, 'amount', this.value)"></td>
                    <td style="vertical-align: top;">
                        <input required type="number" step="0.01" class="form-control form-control-sm debit-input"
                            name="items[${index}][debit]" value="${item.debit}"
                            oninput="updateField(${index}, 'debit', this.value)"
                            ${item.type_selector === 'client' ? 'disabled' : ''}>
                    </td>
                    <td style="vertical-align: top;">
                        <input required type="number" step="0.01" class="form-control form-control-sm credit-input"
                            name="items[${index}][credit]" value="${item.credit}"
                            oninput="updateField(${index}, 'credit', this.value)"
                            ${item.type_selector === 'client' ? 'disabled' : ''}>
                    </td>
                    <td style="vertical-align: top;"><input type="text" class="form-control form-control-sm" name="items[${index}][cheque_no]" value="${item.cheque_no}" oninput="updateField(${index}, 'cheque_no', this.value)"></td>
                    <td style="vertical-align: top;"><input type="date" class="form-control form-control-sm" name="items[${index}][cheque_date]" value="${item.cheque_date}" oninput="updateField(${index}, 'cheque_date', this.value)"></td>
                    <td style="vertical-align: top;"><input type="text" class="form-control form-control-sm" name="items[${index}][bank_name]" value="${item.bank_name}" oninput="updateField(${index}, 'bank_name', this.value)"></td>
                    <td style="vertical-align: top;"><input type="number" class="form-control form-control-sm" name="items[${index}][auth_no]" value="${item.auth_no}" oninput="updateField(${index}, 'auth_no', this.value)"></td>
                    <td style="vertical-align: top;"><input list="branchList${index}" name="items[${index}][branch]" class="form-input w-full" onchange="updateField(${index}, 'branch', this.value)" value="${item.branch}" />
                        <datalist id="branchList${index}">
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}">[{{ $branch->id }}] {{ $branch->name }}</option>
                            @endforeach
                        </datalist>
                    </td>
                    <td style="vertical-align: top;"><input type="number" class="form-control form-control-sm" name="items[${index}][balance]" value="${item.balance}" oninput="updateField(${index}, 'balance', this.value)"></td>
                    <td style="vertical-align: top;"><button type="button" class="btn btn-danger btn-sm" onclick="removeItem(${index})">X</button></td>
                `;

                    paymentTable.appendChild(row);
                });

                updateTotals();
            }

            // Initial row
            addItemButton.addEventListener("click", addItem);
            addItem();

            window.renderTable = renderTable;

            const saveBtn = document.getElementById('save-paymentvoucher-btn');

            if (saveBtn) {
                saveBtn.addEventListener('click', handleSaveClick);
            }

            function handleSaveClick(event) {
                event.preventDefault(); // Prevent form from submitting immediately

                const button = document.getElementById('save-paymentvoucher-btn');
                const icon = document.getElementById('iconSavePaymentVoucher');
                const text = document.getElementById('textSavePaymentVoucher');

                // Disable the button
                button.disabled = true;

                icon.innerHTML = `
                        <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                        </svg>
                    `;
                text.textContent = 'Saving...';

                setTimeout(() => {
                    button.closest('form').submit();
                }, 500);
            }


        });

        document.getElementById('supplierName')?.addEventListener('input', function() {
            const payToInput = document.getElementById('pay_to');
            if (payToInput) {
                payToInput.value = this.value;
            }
        });

        document.getElementById('pay_to')?.addEventListener('input', function() {
            const payToValue = this.value.trim();
            const supplierSelect = document.getElementById('supplierName');

            if (supplierSelect && payToValue) {
                const options = supplierSelect.options ? Array.from(supplierSelect.options) : [];
                const optionExists = options.some(opt => opt.value === payToValue);
                if (optionExists) {
                    supplierSelect.value = payToValue;
                } else {
                    // Optionally add the supplier to the list
                    const newOption = document.createElement('option');
                    newOption.value = payToValue;
                    newOption.textContent = payToValue;
                    supplierSelect.appendChild(newOption);
                    supplierSelect.value = payToValue;
                }
            }
        });

        function updateHiddenAccountId(input, index) {
            const name = input.value;
            const acc = lastLevelAccounts.find(acc => acc.name === name);
            if (acc) {
                document.getElementById(`account_id_${index}`).value = acc.id;
                document.getElementById(`selectedAccName_${index}`).innerText =
                    `[${acc.root ? acc.root.name : 'No Root'}] [${acc.code}] ${acc.name}`;
            } else {
                document.getElementById(`account_id_${index}`).value = '';
                document.getElementById(`selectedAccName_${index}`).innerText = '';
            }
        }


        function toggleRefundDatalist() {
            const type = document.getElementById('receiptvouchertype').value;
            const refundField = document.getElementById('refundNumberField');

            // Show/hide refund field
            if (type === 'Refund') {
                refundField.classList.remove('hidden');
            } else {
                refundField.classList.add('hidden');
            }

            // Reset the form state
            items = [];
            selectedJournalIds = [];
            renderTable();

            lastSearchInfo.innerHTML = '';
            lastSearchInfo.style.display = 'none';

            if (type === 'PaymentByDate') {
                openPaymentByDateModal();
            }
        }

        function openPaymentByDateModal() {
            const today = new Date();
            const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
            const lastDayOfMonth = new Date(today.getFullYear(), today.getMonth() + 1,
                0); // This sets the last day of the current month

            const toDateString = date => {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };

            document.getElementById('dateFrom').value = toDateString(firstDayOfMonth);
            document.getElementById('dateTo').value = toDateString(lastDayOfMonth);

            document.getElementById('paymentByDateModal').classList.remove('hidden');
            document.getElementById('recordsContainer').innerHTML =
                '<p class="text-gray-500">Select a date range and click Search to load entries.</p>';

            const modal = document.getElementById('paymentByDateModal');
            if (modal) {
                modal.classList.remove('hidden');
                loadJournalEntries();
            } else {
                console.warn('Modal element not found');
            }
        }



        function closeModalAndShowLastSearch() {
            const infoContainer = document.getElementById('lastSearchInfo');

            if (lastSearchFrom && lastSearchTo) {
                infoContainer.innerHTML = `
            Last search: <strong>${lastSearchFrom} to ${lastSearchTo}</strong>${supplier ? ` for <strong>${supplier}</strong>` : ''}.
            <a href="javascript:void(0);" onclick="reopenModal()" class="text-blue-600 underline ml-2">View again</a>
        `;
            } else {
                infoContainer.innerHTML = '';
            }
        }


        function reopenModal() {
            document.getElementById('openPaymentByDateModal').classList.remove('hidden'); // Example

            if (lastSearchFrom && lastSearchTo) {
                document.getElementById('dateFrom').value = lastSearchFrom;
                document.getElementById('dateTo').value = lastSearchTo;
                openPaymentByDateModal();
            }
        }

        let lastSearchFrom = '';
        let lastSearchTo = '';
        let selectedJournalIds = [];

        function loadJournalEntries() {
            const from = document.getElementById('dateFrom').value;
            const to = document.getElementById('dateTo').value;
            const supplier = document.getElementById('supplierName').value;

            if (!from || !to) {
                alert('Please select both Date From and Date To.');
                return;
            }

            lastSearchFrom = from;
            lastSearchTo = to;

            const container = document.getElementById('recordsContainer');
            container.innerHTML = '<p class="text-gray-500">Loading records...</p>';

            fetch(`/bank-payments/fetch-journals-by-date?from=${from}&to=${to}&supplier=${encodeURIComponent(supplier)}`)
                .then(response => response.json())
                .then(data => {
                    container.innerHTML = '';

                    if (data.length === 0) {
                        container.innerHTML = '<p class="text-gray-500">No records found in the selected range.</p>';
                        return;
                    }

                    // Table header
                    let tableHTML = `
                    <div class="overflow-x-auto">
                        <div class="max-h-[300px] overflow-y-auto">
                            <table class="w-full border border-gray-300 text-sm">
                                <thead class="bg-gray-100 sticky top-0">
                                    <tr>
                                        <th class="p-2 border text-center">Reconciled<br><small>Select all</small>
                                            <input type="checkbox" id="selectAllCheckbox" onclick="toggleAllJournals(this)">
                                        </th>
                                        <th class="p-2 border">Date</th>
                                        <th class="p-2 border">A/C</th>
                                        <th class="p-2 border">Name</th>
                                        <th class="p-2 border">Description</th>
                                        <th class="p-2 border">Outstanding Balance (KWD)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    
                `;
                    lastSearchFrom = from;
                    lastSearchTo = to;
                    data.forEach(record => {
                        if (selectedJournalIds.includes(record.id)) return; // Skip if already selected

                        const formattedDate = new Date(record.transaction_date).toLocaleDateString('en-GB');
                        tableHTML += `
                        <tr class="border-t">
                            <td class="p-2 border text-center">
                                <input type="checkbox" 
                                    class="payment-checkbox" 
                                    value="${record.id}" 
                                    data-id="${record.id}"
                                    data-account-id="${record.account_id}"
                                    data-account-name="${record.account_name}"  
                                    data-debit="${record.debit}" 
                                    data-credit="${record.credit}" 
                                    data-transaction-id="${record.transaction_id}" 
                                    data-description="${record.description}" 
                                    onclick="selectJournalEntry(event)" />
                            </td>
                            <td class="p-2 border text-center">${formattedDate}</td>
                            <td class="p-2 border text-center">[${record.root_name}]<br>${record.account_code}</td>
                            <td class="p-2 border">${record.name}</td>
                            <td class="p-2 border">${record.description}</td>
                            <td class="p-2 border text-right">KWD ${Math.abs(parseFloat(record.credit) - parseFloat(record.debit)).toFixed(2)}</td>

                        </tr>
                    `;
                    });


                    // Append footer with search range
                    tableHTML += `
                            </tbody>
                            <tfoot class="bg-gray-50">
                                <tr>
                                    <td colspan="7" class="p-2 border text-right text-sm italic text-gray-600">
                                        Searched: ${from} to ${to} ${supplier ? 'by ' + supplier : ''}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            `;

                    container.innerHTML = tableHTML;
                });



            const info = document.getElementById('lastSearchInfo');
            info.innerHTML = `
                <small>Last search by date from <strong>${from}</strong> to <strong>${to}</strong>${supplier ? ` for <strong>${supplier}</strong>` : ''}
                    <a href="#" onclick="openModalWithLastSearch()" class="text-blue-400 ml-1">[View]</a>
                </small>
            `;
            info.style.display = 'block';
        }


        function selectJournalEntry(event) {
            const checkbox = event.target;
            const record = {
                id: parseInt(checkbox.value),
                debit: parseFloat(checkbox.dataset.debit || 0).toFixed(2),
                credit: parseFloat(checkbox.dataset.credit || 0).toFixed(2),
                account_id: checkbox.dataset.accountId || null,
                account_name: checkbox.dataset.accountName || null,
                transaction_id: checkbox.dataset.transactionId || null,
                description: checkbox.dataset.description || null
            };

            updateOutstandingTotal();
        }


        let supplier = "";

        function openModalWithLastSearch() {
            if (!lastSearchFrom || !lastSearchTo) {
                console.warn("Search dates are missing.");
                return;
            }

            // Sync dates in modal
            const dateFromInput = document.getElementById('dateFrom');
            const dateToInput = document.getElementById('dateTo');
            if (dateFromInput) dateFromInput.value = lastSearchFrom;
            if (dateToInput) dateToInput.value = lastSearchTo;

            // Sync pay_to from main to supplierName in modal
            const payToValue = document.getElementById('pay_to')?.value?.trim() || "";
            const supplierSelect = document.getElementById('supplierName');

            if (supplierSelect) {
                // Check if payToValue already exists as option
                const options = Array.from(supplierSelect.options || []);
                const optionExists = options.some(opt => opt.value === payToValue);

                if (payToValue) {
                    if (!optionExists) {
                        const newOption = document.createElement('option');
                        newOption.value = payToValue;
                        newOption.textContent = payToValue;
                        supplierSelect.appendChild(newOption);
                    }
                    supplierSelect.value = payToValue;
                } else {
                    supplierSelect.value = ''; // Clear if empty
                }
            } else {
                console.warn("Supplier select element not found.");
            }

            // Show modal and trigger journal entry load
            const modal = document.getElementById('paymentByDateModal');
            if (modal) {
                modal.classList.remove('hidden');
                loadJournalEntries();
            } else {
                console.warn("Receipt by Date Modal not found.");
            }

            updateOutstandingTotal();
        }


        function toggleAllJournals(masterCheckbox) {
            const checkboxes = document.querySelectorAll('.payment-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = masterCheckbox.checked;
            });

            updateOutstandingTotal();
        }

        function closeModal() {
            const modal = document.getElementById('paymentByDateModal');
            const supplierSelect = document.getElementById('supplierName');
            const payToInput = document.getElementById('pay_to');

            // Sync back supplier name from modal to main pay_to input
            if (supplierSelect && payToInput) {
                const supplierValue = supplierSelect.value.trim();
                if (supplierValue) {
                    payToInput.value = supplierValue;
                }
            }

            // Hide the modal
            if (modal) {
                modal.classList.add('hidden');
            }
        }

        function submitSelectedPayments() {
            const selectedCheckboxes = [...document.querySelectorAll('.payment-checkbox:checked')];

            if (selectedCheckboxes.length === 0) {
                alert("Please select at least one record.");
                return;
            }

            // Extract selected records
            const selectedRecords = selectedCheckboxes.map(cb => ({
                journal_entry_id: parseInt(cb.value),
                debit: parseFloat(cb.dataset.debit || 0),
                credit: parseFloat(cb.dataset.credit || 0),
                account_id: cb.dataset.accountId,
                account_name: cb.dataset.accountName,
                transaction_id: cb.dataset.transactionId,
                description: cb.dataset.description
            }));

            // Group by account_id and aggregate amounts
            const groupedByAccount = {};

            selectedRecords.forEach(record => {
                if (!groupedByAccount[record.account_id]) {
                    groupedByAccount[record.account_id] = {
                        account_id: record.account_id,
                        account_name: record.account_name,
                        total_debit: 0,
                        total_credit: 0,
                        journal_entry_ids: [],
                        description: record.description
                    };
                }
                groupedByAccount[record.account_id].total_debit += record.debit;
                groupedByAccount[record.account_id].total_credit += record.credit;
                groupedByAccount[record.account_id].journal_entry_ids.push(record.journal_entry_id);

                selectedJournalIds.push(record.journal_entry_id);
            });

            // Loop through each group and merge or add into items
            for (const acc_id in groupedByAccount) {
                const group = groupedByAccount[acc_id];
                const netDebit = group.total_credit - group.total_debit;

                // Check if this account already exists in items
                let existingItem = items.find(i => i.ac_code === acc_id);

                if (existingItem) {
                    // Merge amounts
                    existingItem.debit = parseFloat(existingItem.debit) + netDebit;
                    // Merge unique transaction ids
                    const existingIds = Array.isArray(existingItem.reconciled_entry_ids) ? existingItem
                        .reconciled_entry_ids : [];
                    const newUniqueIds = [...new Set([...existingIds, ...group.journal_entry_ids])];
                    existingItem.reconciled_entry_ids = newUniqueIds;
                    existingItem.transaction_id = newUniqueIds;
                } else {
                    // Add new entry
                    items.push({
                        id: acc_id,
                        ac_code: acc_id,
                        transaction_id: group.journal_entry_ids,
                        reconciled_entry_ids: group.journal_entry_ids,
                        remarks: `Reconciliation from ${lastSearchFrom} to ${lastSearchTo}`,
                        currency: "KWD",
                        exchange_rate: 1.0,
                        amount: 0,
                        debit: netDebit,
                        credit: 0,
                        cheque_no: "",
                        cheque_date: "",
                        bank_name: "",
                        branch: "",
                        auth_no: "",
                        balance: 0,
                    });
                }
            }

            console.log("Updated Items (Merged if exists):", items);

            renderTable();
            closeModal();
        }

        function appendLastSearchedDateOption(searchedDate) {
            const select = document.getElementById('receiptvouchertype');
            const existingOption = document.querySelector(`#receiptvouchertype option[value="LastSearch:${searchedDate}"]`);

            if (!existingOption) {
                const option = document.createElement('option');
                option.value = `LastSearch:${searchedDate}`;
                option.textContent = `Last Search: ${searchedDate}`;
                option.disabled = true;
                option.selected = true;

                select.appendChild(option);
            }
        }

        function updateOutstandingTotal() {
            const selectedCheckboxes = document.querySelectorAll('.payment-checkbox:checked');
            let totalOutstanding = 0;

            selectedCheckboxes.forEach(cb => {
                const debit = parseFloat(cb.dataset.debit || 0);
                const credit = parseFloat(cb.dataset.credit || 0);
                totalOutstanding += Math.abs(credit - debit);
            });

            const container = document.getElementById('totalOutstandingBalance');
            const amountEl = document.getElementById('outstandingAmount');

            if (selectedCheckboxes.length > 0) {
                container.classList.remove('hidden');
                amountEl.textContent = totalOutstanding.toFixed(2);
            } else {
                container.classList.add('hidden');
                amountEl.textContent = '0.00';
            }
        }

        window.handleInvoiceInput = function(input, index) {
            // Find the selected option in the datalist
            const val = input.value;
            const datalist = document.getElementById(`invoiceList_${index}`);
            let invoiceId = '';
            if (datalist) {
                for (const option of datalist.options) {
                    if (option.value === val) {
                        invoiceId = option.getAttribute('data-id');
                        break;
                    }
                }
            }
            items[index].invoice_number_display = val;
            items[index].invoice_id = invoiceId;
            document.getElementById(`invoice_id_${index}`).value = invoiceId;
        };

        function setClientId(index, value) {
            const datalist = document.getElementById(`clientList_${index}`);
            let clientId = '';
            if (datalist) {
                for (const option of datalist.options) {
                    if (option.value === value) {
                        clientId = option.getAttribute('data-id');
                        break;
                    }
                }
                // If not found, try to match by name (case-insensitive)
                if (!clientId) {
                    for (const option of datalist.options) {
                        if (option.value.toLowerCase() === value.toLowerCase()) {
                            clientId = option.getAttribute('data-id');
                            break;
                        }
                    }
                }
            }
            items[index].client_id = clientId;
            document.getElementById(`client_id_${index}`).value = clientId;
        }
    </script>

</x-app-layout>