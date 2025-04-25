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

                    <div class="mb-6 w-full lg:w-1/2 lg:max-w-fit mt-5">
                        <div class="flex items-center gap-x-6">
                            <label for="bankpaymentref" class="mb-0 flex-1">Ref <span
                                    class="text-red-500">*</span></label>
                            <input required readonly id="bankpaymentref"
                                value="{{ old('bankpaymentref', $bankPayment->reference_number) }}" type="text"
                                name="bankpaymentref" class="form-input w-2/3 bg-gray-200 text-gray-700" />
                        </div>
                        <div class="flex items-center gap-x-4 mt-4">
                            <label class="mb-0 flex-1 ltr:mr-2 rtl:ml-2">
                                Payment Type
                            </label>
                            <input value="{{ old('reference_type', $bankPayment->reference_type) }}" type="text"
                                readonly class="form-input w-2/3 lg:w-[250px] bg-gray-100 text-gray-700 border-gray-300"
                                value="{{ $bankPayment->reference_type }}" />
                        </div>

                        @if ($bankPayment->reference_type === 'Refund')
                            <div class="flex items-center gap-x-4 mt-4">
                                <label class="mb-0 flex-1 ltr:mr-2 rtl:ml-2">
                                    Refund Number
                                </label>
                                <input type="text" readonly
                                    class="form-input w-2/3 lg:w-[250px] bg-gray-100 text-gray-700 border-gray-300"
                                    value="{{ trim(\Illuminate\Support\Str::after($bankPayment->description, '|')) }}" />
                            </div>
                        @endif

                        <div class="flex items-center gap-x-6 mt-4">
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

                        <div class="mt-4 flex items-center gap-x-6">
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
                                    value="{{ old('remarks_create', trim(\Illuminate\Support\Str::before($bankPayment->description, '|'))) }}" />

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
                            @foreach ($JournalEntrys as $index => $transaction)
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
                    <a href="{{ route('bank-payments.index') }}"
                        class="btn btn-info px-6 py-2 w-48 rounded-lg text-center">Payment Voucher List</a>
                </div>
            </div>
        </div>


    </div>
    <script>
        const suppliers = @json($suppliers);
        const accpayreceives = @json($accpayreceives);

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

                totalDebitEl.textContent = totalDebit.toFixed(2);
                totalCreditEl.textContent = totalCredit.toFixed(2);
                totalDifferenceEl.textContent = diff.toFixed(2);

                const correctIcon = document.getElementById('correct');
                const falseIcon = document.getElementById('false');

                if (correctIcon && falseIcon) {
                    correctIcon.style.display = (diff === 0) ? 'block' : 'none';
                    falseIcon.style.display = (diff !== 0) ? 'block' : 'none';
                }
            }

            // Optional: Set display/stored values for datalist
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

            // Event: Update totals on input change
            document.addEventListener('input', function(e) {
                if (e.target.matches(
                        'input[name^="items"][name$="[debit]"], input[name^="items"][name$="[credit]"]')) {
                    updateTotals();
                }
            });

            // Add row logic
            if (addItemButton) {
                addItemButton.addEventListener("click", function() {
                    if (typeof addItem === "function") {
                        addItem();
                    }
                });
            }

            // Ensure totals update when everything is loaded
            window.addEventListener('load', function() {
                updateTotals();
            });
        });
    </script>


</x-app-layout>
