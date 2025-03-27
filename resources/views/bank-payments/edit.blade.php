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
        <form method="POST" action="{{ route('bank-payments.update', $bankPayment->id) }}">
            @csrf
            @method('PUT')

            <div class="flex flex-col gap-2.5 xl:flex-row">
                <div class="panel flex-1 px-0 py-6 ltr:lg:mr-6 rtl:lg:ml-6">
                    <div class="flex flex-wrap justify-between px-4">

                        <div class="mb-6 w-full lg:w-1/2">
                            <div class="mt-6 space-y-1 text-gray-800 dark:text-gray-400">
                                <x-application-logo class="custom-logo-size" />
                                <h3>CITY TRAVELERS</h3>
                                <p>AL AHMADI - KUWAIT</p>
                                <p>ALSALEM ALSEBAH ST.</p>
                                <p>ABU HALI - BLOCK 3</p>
                                <p>citytravelers@agency.co</p>
                                <p>+965 22210017</p>
                            </div>
                        </div>

                        <div class="w-full lg:w-1/2 lg:max-w-fit mt-20">
                            <div class="flex items-center gap-x-4">
                                <label for="bankpaymentref" class="mb-0 flex-1">Payment Ref <span
                                        class="text-red-500">*</span></label>
                                <input required readonly id="bankpaymentref"
                                    value="{{ old('bankpaymentref', $bankPayment->reference_number) }}" type="text"
                                    name="bankpaymentref" class="form-input w-2/3 bg-gray-200 text-gray-700" />
                            </div>

                            <div class="flex items-center gap-x-4 mt-4">
                                <label for="branch_id" class="mb-0 flex-1">Branch <span
                                        class="text-red-500">*</span></label>
                                <select required id="branch_id" name="branch_id" class="form-input w-2/3">
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}"
                                            {{ old('branch_id', $bankPayment->branch_id) == $branch->id ? 'selected' : '' }}>
                                            {{ $branch->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mt-4 flex items-center gap-x-4">
                                <label for="docdate" class="mb-0 flex-1">Doc Date <span
                                        class="text-red-500">*</span></label>

                                <input required id="docdate" type="date" name="docdate" class="form-input w-2/3"
                                    value="{{ old('docdate', isset($bankPayment->date) ? \Carbon\Carbon::parse($bankPayment->date)->format('Y-m-d') : '') }}" />

                            </div>
                        </div>
                    </div>

                    <hr class="my-6 border-[#e0e6ed] dark:border-[#1b2e4b]" />

                    <div class="mt-8 px-4">
                        <div class="flex flex-col justify-between lg:flex-row gap-x-4">
                            <div class="mb-6 w-full lg:w-1/2 ltr:lg:mr-6 rtl:lg:ml-6">
                                <div class="text-lg font-semibold">Bank Payment To</div>
                                <div class="mt-4 flex items-center gap-x-4">
                                    <label for="pay_to" class="mb-0 w-1/3">Pay To <span
                                            class="text-red-500">*</span></label>
                                    <input required id="pay_to" type="text" name="pay_to" list="supplierList"
                                        placeholder="Enter Payee Name" value="{{ old('pay_to', $bankPayment->name) }}"
                                        class="form-input flex-1" />
                                    <datalist id="supplierList">
                                        @foreach ($suppliers as $supplier)
                                            <option value="{{ $supplier->name }}">[{{ $supplier->id }}]
                                                {{ $supplier->name }}
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
                                        class="form-input flex-1" placeholder="Enter Remarks"
                                        value="{{ old('remarks_create', $bankPayment->description) }}" />
                                </div>

                                <div class="mt-4 flex items-center gap-x-4">
                                    <label for="internal_remarks" class="mb-0 w-1/3 ltr:mr-2 rtl:ml-2">Internal
                                        Remarks</label>
                                    <input id="internal_remarks" type="text" name="internal_remarks"
                                        class="form-input flex-1" placeholder="Enter Internal Remarks"
                                        value="{{ old('internal_remarks', $bankPayment->remarks_internal) }}" />
                                </div>

                                <div class="mt-4 flex items-center gap-x-4">
                                    <label for="remarks_fl" class="mb-0 w-1/3 ltr:mr-2 rtl:ml-2">Remarks
                                        FL</label>
                                    <input id="remarks_fl" type="text" name="remarks_fl" class="form-input flex-1"
                                        placeholder="Enter Remarks FL"
                                        value="{{ old('remarks_fl', $bankPayment->remarks_fl) }}" />
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
                                </tr>
                            </thead>
                            <tbody id="paymentTable">
                                @foreach ($generalLedgers as $index => $transaction)
                                    <tr>
                                        <td>
                                            {{ $transaction->account ? '[' . $transaction->account->id . '] ' . $transaction->account->name : 'N/A' }}
                                            <input type="hidden" name="items[{{ $index }}][account_id]"
                                                value="{{ old("items.$index.account_id", $transaction->account_id) }}" />
                                        </td>
                                        <td><input type="text" name="items[{{ $index }}][description]"
                                                value="{{ old("items.$index.description", $transaction->description) }}" />
                                        </td>
                                        <td><input type="text" name="items[{{ $index }}][currency]"
                                                value="KWD" /></td>
                                        <td><input type="text" name="items[{{ $index }}][exchange_rate]"
                                                value="1.00" /></td>
                                        <td><input type="text" name="items[{{ $index }}][amount]"
                                                value="{{ old("items.$index.amount", $transaction->amount) }}" />
                                        </td>
                                        <td><input type="text" name="items[{{ $index }}][debit]"
                                                value="{{ old("items.$index.debit", $transaction->debit) }}" />
                                        </td>
                                        <td><input type="text" name="items[{{ $index }}][credit]"
                                                value="{{ old("items.$index.credit", $transaction->credit) }}" />
                                        </td>
                                        <td><input type="text" name="items[{{ $index }}][cheque_no]"
                                                value="{{ old("items.$index.cheque_no", $transaction->cheque_no) }}" />
                                        </td>
                                        <td><input type="text" name="items[{{ $index }}][cheque_date]"
                                                value="{{ old("items.$index.cheque_date", $transaction->cheque_date) }}" />
                                        </td>
                                        <td><input type="text" name="items[{{ $index }}][bank_name]"
                                                value="{{ old("items.$index.bank_name", $transaction->bank_name) }}" />
                                        </td>
                                        <td><input type="text" name="items[{{ $index }}][auth_no]"
                                                value="{{ old("items.$index.auth_no", $transaction->auth_no) }}" />
                                        </td>
                                        <td><input type="text" name="items[{{ $index }}][branch_name]"
                                                value="{{ old("items.$index.branch_name", $transaction->branch_name) }}" />
                                        </td>
                                        <td><input type="text" name="items[{{ $index }}][balance]"
                                                value="{{ old("items.$index.balance", $transaction->balance) }}" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>


                    <div class="panel overflow-hidden mt-10">
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
                        <button type="submit" @click="validateForm()"
                            class="btn btn-success px-6 py-2 w-40 rounded-lg">Update</button>
                        <a href="{{ route('bank-payments.index') }}"
                            class="btn btn-danger px-6 py-2 w-40 rounded-lg">Cancel</a>
                    </div>
                </div>
            </div>
        </form>


    </div>
    <script>
        const suppliers = @json($suppliers);
        const accpayreceives = @json($accpayreceives);

        document.addEventListener("DOMContentLoaded", function() {
            const paymentTable = document.getElementById("paymentTable");
            const totalDebitEl = document.getElementById("total_debit");
            const totalCreditEl = document.getElementById("total_credit");
            const totalDifferenceEl = document.getElementById("total_difference");
            const addItemButton = document.getElementById("addItem");

            let items = [];

            function addItem() {
                let id = crypto.randomUUID();
                let item = {
                    id,
                    account_id: "",
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
                    items[index][field] = parseFloat(value) || 0;
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
                if (!input || index === undefined) return;

                const selectedId = input.value;
                const dataList = document.getElementById(`accountList_${index}`);
                const selectedAccLabel = document.getElementById(`selectedAccName_${index}`);

                if (!selectedAccLabel || !dataList) return;

                const selectedOption = Array.from(dataList.options).find(option => option.value == selectedId);
                selectedAccLabel.textContent = selectedOption ? selectedOption.textContent : "Account not found";
            }

            window.selectedAccName = selectedAccName; // Make function globally available

            function updateRemarks() {
                let payTo = document.getElementById("pay_to").value;
                let remarksField = document.getElementById("remarks_create");

                if (payTo.trim() !== "") {
                    remarksField.value = "Payment to " + payTo;
                } else {
                    remarksField.value = "";
                }
            }

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

            function setDisplayValue(input) {
                const displayName = input.getAttribute('data-display');
                if (displayName) {
                    input.value = displayName;
                }
            }

            function setStoredValue(input) {
                const datalist = document.getElementById(input.getAttribute('list'));
                const selectedOption = Array.from(datalist.options).find(option => option.text === input.value);

                if (selectedOption) {
                    input.value = selectedOption.value;
                    input.setAttribute('data-display', selectedOption.text);
                } else {
                    input.value = "";
                }
            }

            function renderTable() {
                paymentTable.innerHTML = "";
                items.forEach((item, index) => {
                    const row = document.createElement("tr");

                    row.innerHTML = `
                    <td>
                        <input required list="accountList_${index}" 
                            class="form-control form-control-sm" 
                            name="items[${index}][account_id]" 
                            value="${item.account_id}" 
                            oninput="updateField(${index}, 'account_id', this.value); selectedAccName(this, ${index});">
                        
                        <datalist id="accountList_${index}">
                            ${accpayreceives.map(accpayreceive => 
                                `<option value="${accpayreceive.id}" ${item.account_id == accpayreceive.id ? 'selected' : ''}>
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        [${accpayreceive.id}] ${accpayreceive.name}
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    </option>`
                            ).join('')}
                        </datalist>

                    <small id="selectedAccName_${index}" class="text-muted">
                        ${(() => {
                            let acc = accpayreceives.find(acc => acc.id == item.account_id);
                            return acc ? `[${acc.id}] ${acc.name}` : '';
                        })()}
                    </small>


                    </td>

                    <td style="vertical-align: top;"><input required type="text" class="form-control form-control-sm" name="items[${index}][remarks]" value="${item.remarks}" oninput="updateField(${index}, 'remarks', this.value)"></td>
                    <td style="vertical-align: top;">
                        <select required class="form-control form-control-sm text-left" name="items[${index}][currency]" onchange="updateField(${index}, 'currency', this.value)">
                            <option ${item.currency === "KWD" ? "selected" : ""}>KWD</option>
                            <option ${item.currency === "USD" ? "selected" : ""}>USD</option>
                            <option ${item.currency === "GBP" ? "selected" : ""}>GBP</option>
                        </select>
                    </td>
                    <td style="vertical-align: top;"><input required type="number" class="form-control form-control-sm" name="items[${index}][exchange_rate]" value="${item.exchange_rate}" oninput="updateField(${index}, 'exchange_rate', this.value)"></td>
                    <td style="vertical-align: top;"><input required type="number" class="form-control form-control-sm" name="items[${index}][amount]" value="${item.amount}" oninput="updateField(${index}, 'amount', this.value)"></td>
                    <td style="vertical-align: top;"><input required type="number" class="form-control form-control-sm debit-input" name="items[${index}][debit]" value="${item.debit}" oninput="updateField(${index}, 'debit', this.value)"></td>
                    <td style="vertical-align: top;"><input required type="number" class="form-control form-control-sm credit-input" name="items[${index}][credit]" value="${item.credit}" oninput="updateField(${index}, 'credit', this.value)"></td>
                    <td style="vertical-align: top;"><input type="text" class="form-control form-control-sm" name="items[${index}][cheque_no]" value="${item.cheque_no}" oninput="updateField(${index}, 'cheque_no', this.value)"></td>
                    <td style="vertical-align: top;"><input type="date" class="form-control form-control-sm" name="items[${index}][cheque_date]" value="${item.cheque_date}" oninput="updateField(${index}, 'cheque_date', this.value)"></td>
                    <td style="vertical-align: top;"><input type="text" class="form-control form-control-sm" name="items[${index}][bank_name]" value="${item.bank_name}" oninput="updateField(${index}, 'bank_name', this.value)"></td>
                    <td style="vertical-align: top;"><input type="number" class="form-control form-control-sm" name="items[${index}][auth_no]" value="${item.auth_no}" oninput="updateField(${index}, 'auth_no', this.value)"></td>
                    <td style="vertical-align: top;"><input type="text" class="form-control form-control-sm" name="items[${index}][branch]" value="${item.branch}" oninput="updateField(${index}, 'branch', this.value)"></td>
                    <td style="vertical-align: top;"><input type="number" step="0.01" class="form-control form-control-sm" name="items[${index}][balance]" value="${item.balance}" oninput="updateField(${index}, 'balance', this.value)"></td>
                    <td style="vertical-align: top;"><button type="button" class="btn btn-danger btn-sm" onclick="removeItem(${index})">X</button></td>
                `;

                    paymentTable.appendChild(row);
                });

                updateTotals();
            }

            // Initial row
            addItemButton.addEventListener("click", addItem);
            addItem();
        });
    </script>

</x-app-layout>
