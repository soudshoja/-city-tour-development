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
                <p class="text-2xl">Payment Voucher</p>
                <h5 class="text-2xl ltr:mr-auto rtl:mr-auto"></h5>
            </div>
        </div>
        <form method="POST" action="{{ route('bank-payments.store') }}">
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
                                <label for="bankpaymentref" class="mb-0 flex-1 ltr:mr-2 rtl:ml-2">Payment
                                    Ref <span class="text-red-500">*</span></label>
                                <input type="text" readonly value="PV-{{ now()->timestamp }}"
                                    class="form-input w-2/3 lg:w-[250px]  bg-gray-200" />
                                <input type="hidden" name="bankpaymentref" value="PV-{{ now()->timestamp }}" required>

                            </div>
                            <div class="flex items-center gap-x-4 mt-4">
                                <label for="bankpaymenttype" class="mb-0 flex-1 ltr:mr-2 rtl:ml-2">
                                    Payment Type <span class="text-red-500">*</span>
                                </label>
                                <select required id="bankpaymenttype" name="bankpaymenttype"
                                    class="form-select w-2/3 lg:w-[250px] bg-white text-gray-700 border-gray-300"
                                    onchange="toggleRefundDatalist()">
                                    <option value="">Choose One</option>
                                    <option value="Payment">Payment</option>
                                    <option value="PaymentByDate">Payment by Date</option>
                                    <option value="Refund">Refund</option>
                                </select>
                            </div>
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
                                        <option value="{{ $branch->id }}" data-name="{{ $branch->name }}">
                                            {{ $branch->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mt-4 flex items-center gap-x-4">
                                <label for="docdate" class="mb-0 flex-1 ltr:mr-2 rtl:ml-2">Doc Date <span
                                        class="text-red-500">*</span></label>
                                <input required id="docdate" type="date" name="docdate"
                                    class="form-input w-2/3 lg:w-[250px]" />
                            </div>
                        </div>
                    </div>

                    <hr class="my-6 border-[#e0e6ed] dark:border-[#1b2e4b]" />

                    <div class="mt-8 px-4">
                        <div class="flex flex-col justify-between lg:flex-row gap-x-4">
                            <div class="mb-6 w-full lg:w-1/2 ltr:lg:mr-6 rtl:lg:ml-6">
                                <div class="text-lg font-semibold">Bank Payment To</div>
                                <div class="mt-4 flex items-center gap-x-4">
                                    <label for="pay_to_payee" class="mb-0 w-1/3 ltr:mr-2 rtl:ml-2">Pay To <span
                                            class="text-red-500">*</span></label>
                                    <input required id="pay_to" type="text" name="pay_to"
                                        class="form-input flex-1" list="supplierList"
                                        placeholder="Search payee name..." />
                                    <datalist id="supplierList">
                                        @foreach ($suppliers as $supplier)
                                            <option value="{{ $supplier->name }}">
                                                [{{ $supplier->root->name ?? 'N/A' }}] [{{ $supplier->code }}]
                                                {{ $supplier->name }} </option>
                                        @endforeach
                                    </datalist>
                                </div>
                            </div>
                            <div class="w-full lg:w-1/2">
                                <div class="text-lg font-semibold">Remarks</div>

                                <div class="mt-4 flex items-center gap-x-4">
                                    <label for="remarks_create_label" class="mb-0 w-1/3 ltr:mr-2 rtl:ml-2">Remarks <span
                                            class="text-red-500">*</span></label>
                                    <input required id="remarks_create" type="text" name="remarks_create"
                                        class="form-input flex-1" placeholder="Enter Remarks" />
                                </div>

                                <div class="mt-4 flex items-center gap-x-4">
                                    <label for="internal_remarks" class="mb-0 w-1/3 ltr:mr-2 rtl:ml-2">Internal
                                        Remarks</label>
                                    <input id="internal_remarks" type="text" name="internal_remarks"
                                        class="form-input flex-1" placeholder="Enter Internal Remarks" />
                                </div>

                                <div class="mt-4 flex items-center gap-x-4">
                                    <label for="remarks_fl" class="mb-0 w-1/3 ltr:mr-2 rtl:ml-2">Remarks
                                        FL</label>
                                    <input id="remarks_fl" type="text" name="remarks_fl"
                                        class="form-input flex-1" placeholder="Enter Remarks FL" />
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="table table-bordered bank-payment-table mt-10 w-full">
                            <thead class="table-light">
                                <tr>
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
                        <a href="{{ route('bank-payments.index') }}"
                            class="btn btn-secondary px-6 py-2 w-40 rounded-lg text-center bg-gray-200 hover:bg-gray-300 text-gray-700">
                            Cancel
                        </a>

                        <!-- Right side: Save and Reset -->
                        <div class="flex gap-4">
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
        class="fixed inset-0 bg-gray-800 bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 w-[90%] max-w-3xl shadow-xl">
            <h2 class="text-lg font-bold mb-4">Select Payments by Date</h2>

            <div class="flex gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium">Date From:</label>
                    <input type="date" id="dateFrom" class="border-gray-300 rounded w-full" />
                </div>
                <div>
                    <label class="block text-sm font-medium">Date To:</label>
                    <input type="date" id="dateTo" class="border-gray-300 rounded w-full" />
                </div>
                <div class="flex items-end">
                    <button onclick="loadJournalEntries()" class="bg-blue-600 text-white px-4 py-2 rounded">
                        Search
                    </button>
                </div>
            </div>

            <div id="recordsContainer">
                <p class="text-gray-500">Select a date range to load entries.</p>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <button onclick="closeModal()" class="bg-gray-300 px-4 py-2 rounded">Cancel</button>
                <button onclick="submitSelectedPayments()" class="bg-blue-600 text-white px-4 py-2 rounded">Add
                    Selected</button>
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

            function addItem() {
                let id = crypto.randomUUID();
                let item = {
                    id,
                    ac_code: "",
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
                };

                items.push(item);
                renderTable();
            }

            window.removeItem = function(index) {
                if (items.length > 1) {
                    items.splice(index, 1);
                    renderTable();
                } else {
                    alert("At least one record is required.");
                }
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
                let totalDebit = items.reduce((sum, item) => sum + item.debit, 0);
                let totalCredit = items.reduce((sum, item) => sum + item.credit, 0);
                let totalDifference = totalDebit - totalCredit;

                totalDebitEl.textContent = totalDebit.toFixed(2);
                totalCreditEl.textContent = totalCredit.toFixed(2);
                totalDifferenceEl.textContent = totalDifference.toFixed(2);

                updateDifference(totalDifference);
            }

            function selectedAccName(input, index) {
                const selectedText = input.value.trim();
                const acc = lastLevelAccounts.find(a => a.name === selectedText);

                if (acc) {
                    items[index].ac_code = acc.id;
                    document.getElementById(`selectedAccName_${index}`).innerText =
                        `[${acc.root ? acc.root.name : 'No Root'}] [${acc.code}] ${acc.name}`;
                } else {
                    items[index].ac_code = null;
                    document.getElementById(`selectedAccName_${index}`).innerText = '';
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
                        `<option value="${acc.name}">[${acc.root ? acc.root.name : 'No Root'}] [${acc.code}] ${acc.name}</option>`
                    ).join('');

                    const selectedAcc = lastLevelAccounts.find(acc => acc.id == item.ac_code);
                    const selectedAccDisplay = selectedAcc ?
                        `[${selectedAcc.root ? selectedAcc.root.name : 'No Root'}] [${selectedAcc.code}] ${selectedAcc.name}` :
                        '';
                    row.innerHTML = `
                    <td>
                        <input required list="accountList_${index}" 
                            class="form-control form-control-sm" 
                            name="items[${index}][ac_code]" 
                            value="${selectedAcc ? selectedAcc.name : ''}" 
                            placeholder="Search..."
                            oninput="selectedAccName(this, ${index});">
                        
                        <datalist id="accountList_${index}">
                            ${accountOptions}
                            
                        </datalist>

                        <small id="selectedAccName_${index}" class="text-muted">
                            ${selectedAcc ? `[${selectedAcc.root ? selectedAcc.root.name : 'No Root'}] [${selectedAcc.code}] ${selectedAcc.name}` : ''}
                        </small>
                        <input type="hidden" name="items[${index}][transaction_id]" value="${item.transaction_id}">

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
                    <td style="vertical-align: top;"><input required type="number" step="0.01" class="form-control form-control-sm debit-input" name="items[${index}][debit]" value="${item.debit}" oninput="updateField(${index}, 'debit', this.value)"></td>
                    <td style="vertical-align: top;"><input required type="number" step="0.01" class="form-control form-control-sm credit-input" name="items[${index}][credit]" value="${item.credit}" oninput="updateField(${index}, 'credit', this.value)"></td>
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

                // Show loading spinner
                icon.innerHTML = `
                        <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                        </svg>
                    `;
                text.textContent = 'Saving...';

                // Submit the form after delay (simulate AJAX or save effect)
                setTimeout(() => {
                    button.closest('form').submit(); // Actually submit the form
                }, 500);
            }


        });

        function toggleRefundDatalist() {
            const type = document.getElementById('bankpaymenttype').value;
            const field = document.getElementById('refundNumberField');
            if (type === 'Refund') {
                field.classList.remove('hidden');
            } else {
                field.classList.add('hidden');
            }
            if (type === 'PaymentByDate') {
                openPaymentByDateModal();
            }
        }

        function openPaymentByDateModal() {
            document.getElementById('paymentByDateModal').classList.remove('hidden');
            document.getElementById('recordsContainer').innerHTML =
                '<p class="text-gray-500">Select a date range to load entries.</p>';
        }

        function loadJournalEntries() {
            const from = document.getElementById('dateFrom').value;
            const to = document.getElementById('dateTo').value;

            if (!from || !to) {
                alert('Please select both Date From and Date To.');
                return;
            }

            const container = document.getElementById('recordsContainer');
            container.innerHTML = '<p class="text-gray-500">Loading records...</p>';

            fetch(`/bank-payments/fetch-journals-by-date?from=${from}&to=${to}`)
                .then(response => response.json())
                .then(data => {
                    container.innerHTML = '';
                    if (data.length === 0) {
                        container.innerHTML = '<p class="text-gray-500">No records found in the selected range.</p>';
                        return;
                    }

                    data.forEach(record => {
                        const formattedDate = new Date(record.transaction_date).toLocaleDateString('en-GB');
                        container.innerHTML += `
                            <div class="flex items-center gap-2 mb-2">
                                <input type="checkbox" 
                                    class="payment-checkbox" 
                                    value="${record.id}" 
                                    data-account-id="${record.account_id}" 
                                    data-debit="${record.debit}" 
                                    data-transaction-id="${record.transaction_id}" />
                                <label>
                                    (${formattedDate}) (${record.account_id}) (${record.account_code}) ${record.name} - ${record.description} - KWD ${record.debit}
                                </label>
                            </div>
                        `;
                    });


                });
        }


        function closeModal() {
            document.getElementById('paymentByDateModal').classList.add('hidden');
        }

        function submitSelectedPayments() {
            const from = document.getElementById('dateFrom').value;
            const to = document.getElementById('dateTo').value;

            const selectedCheckboxes = [...document.querySelectorAll('.payment-checkbox:checked')];

            if (selectedCheckboxes.length === 0) {
                alert("Please select at least one record.");
                return;
            }

            // Extract data from selected checkboxes
            const selectedRecords = selectedCheckboxes.map(cb => ({
                id: cb.value,
                debit: parseFloat(cb.dataset.debit || 0),
                account_id: cb.dataset.accountId || null,
                transaction_id: cb.dataset.transactionId || null
            }));

            // Create debit entries for each selected record
            selectedRecords.forEach(record => {
                items.push({
                    id: crypto.randomUUID(),
                    ac_code: record.account_id,
                    transaction_id: record.transaction_id,
                    remarks: `Payment from ${from} to ${to}`,
                    currency: "KWD",
                    exchange_rate: 1.0,
                    amount: 0,
                    debit: 0,
                    credit: record.debit,
                    cheque_no: "",
                    cheque_date: "",
                    bank_name: "",
                    branch: "",
                    auth_no: "",
                    balance: 0,
                });
            });

            // Remove first row if it's an empty placeholder
            if (items.length > 1) {
                const first = items[0];
                const isEmpty =
                    (!first.ac_code || first.ac_code.trim() === "") &&
                    parseFloat(first.debit || 0) === 0 &&
                    parseFloat(first.credit || 0) === 0;

                if (isEmpty) {
                    items.splice(0, 1);
                }
            }

            renderTable();
            closeModal();
        }
    </script>

</x-app-layout>
