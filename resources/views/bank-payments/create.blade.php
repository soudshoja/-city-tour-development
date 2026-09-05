<x-app-layout>
    @php
        $company = $companies instanceof \App\Models\Company ? $companies : ($companies?->first());
    @endphp

    <div class="mx-auto max-w-5xl">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">New payment voucher</h1>
                <p class="mt-1 text-sm text-gray-500">Record money going out to a supplier, an account, or an agent bonus. Each line becomes its own posted document.</p>
            </div>
            <a href="{{ route('bank-payments.index') }}" class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Back to payment vouchers
            </a>
        </div>

        @if ($errors->any())
            <div class="mb-6 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <p class="font-medium">Please fix the following before saving:</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('bank-payments.store') }}" enctype="multipart/form-data"
              x-data="bankPaymentForm({
                  pvAllowOverdraft: @js($pvAllowOverdraft),
                  approvalThreshold: @js($approvalThreshold),
                  accounts: @js($lastLevelAccounts->map(fn ($a) => ['id' => $a->id, 'label' => '['.$a->code.'] '.$a->name])),
                  agents: @js($agents->map(fn ($a) => ['id' => $a->id, 'label' => $a->name])),
              })"
              @submit="submitting = true">
            @csrf
            <input type="hidden" name="company_id" value="{{ $company?->id }}">

            <div class="space-y-6">
                <!-- Document details ------------------------------------------------------------ -->
                <section class="rounded-lg border border-gray-200 bg-white p-6">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Document details</h2>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label for="bankpaymentref" class="mb-1 block text-sm font-medium text-gray-700">Reference</label>
                            <input id="bankpaymentref" type="text" name="bankpaymentref" maxlength="100" value="{{ old('bankpaymentref') }}"
                                   placeholder="Optional free-text reference" class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="bankpaymenttype" class="mb-1 block text-sm font-medium text-gray-700">Payment type <span class="text-red-500">*</span></label>
                            <select id="bankpaymenttype" name="bankpaymenttype" x-model="bankpaymenttype" @change="onTypeChange" required
                                    class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="Payment" {{ old('bankpaymenttype', 'Payment') === 'Payment' ? 'selected' : '' }}>Payment</option>
                                <option value="PaymentByDate" {{ old('bankpaymenttype') === 'PaymentByDate' ? 'selected' : '' }}>Payment by date (reconciliation)</option>
                                <option value="Refund" {{ old('bankpaymenttype') === 'Refund' ? 'selected' : '' }}>Refund</option>
                            </select>
                        </div>
                        <div>
                            <label for="branch_id" class="mb-1 block text-sm font-medium text-gray-700">Branch <span class="text-red-500">*</span></label>
                            <select id="branch_id" name="branch_id" required class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Select branch</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" {{ (string) old('branch_id') === (string) $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="docdate" class="mb-1 block text-sm font-medium text-gray-700">Document date <span class="text-red-500">*</span></label>
                            <input id="docdate" type="date" name="docdate" required value="{{ old('docdate', now()->toDateString()) }}"
                                   class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>

                    <div class="mt-4">
                        <label for="pay_from_account" class="mb-1 block text-sm font-medium text-gray-700">Pay from <span class="text-red-500">*</span></label>
                        <select id="pay_from_account" name="pay_from_account" x-model="payFromAccountId" @change="resolveAllBankDetails" required class="w-full max-w-sm rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Select bank account</option>
                            @foreach ($bankAccounts as $bank)
                                <option value="{{ $bank->id }}" {{ (string) old('pay_from_account') === (string) $bank->id ? 'selected' : '' }}>
                                    [{{ $bank->code }}] {{ $bank->name }} @if(isset($bank->current_balance)) (balance KWD {{ number_format((float) $bank->current_balance, 3) }}) @endif
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500">
                            <span x-show="pvAllowOverdraft">This company allows a payment to take this account negative.</span>
                            <span x-show="!pvAllowOverdraft">A payment that would take this account negative is refused.</span>
                            <span x-show="approvalThreshold !== null">Lines at or under KWD <span x-text="Number(approvalThreshold).toFixed(3)"></span> auto-approve.</span>
                        </p>
                    </div>
                </section>

                <!-- Payment lines ------------------------------------------------------------------ -->
                {{-- x-if (not x-show): when PaymentByDate is selected this section's `items[0]...`
                     inputs must be removed from the DOM entirely, not merely hidden -- a CSS-hidden
                     input still submits, which would collide with the reconciliation panel's own
                     `items[0]...` hidden inputs below (both would post under the identical field
                     names). --}}
                <template x-if="bankpaymenttype !== 'PaymentByDate'">
                <section class="rounded-lg border border-gray-200 bg-white p-6">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Payment lines</h2>
                        <button type="button" @click="addItem" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            + Add line
                        </button>
                    </div>

                    <div class="mt-4 space-y-4">
                        <template x-for="(item, index) in items" :key="item.key">
                            <div class="rounded-md border border-gray-100 bg-gray-50 p-4">
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-gray-600">Line type</label>
                                        <select :name="`items[${index}][type_selector]`" x-model="item.type_selector" @change="resolveBankDetail(index)"
                                                class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                            <option value="account">Account</option>
                                            <option value="supplier">Supplier</option>
                                            <option value="bonus">Agent bonus</option>
                                        </select>
                                    </div>
                                    <div class="sm:col-span-1 lg:col-span-2">
                                        <label class="mb-1 block text-xs font-medium text-gray-600">Account</label>
                                        <select :name="`items[${index}][account_id]`" x-model="item.account_id" @change="resolveBankDetail(index)" required
                                                class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                            <option value="">Select account</option>
                                            <template x-for="acc in accounts" :key="acc.id">
                                                <option :value="acc.id" x-text="acc.label"></option>
                                            </template>
                                        </select>
                                    </div>

                                    {{-- T14 -- auto-selected remittance details for a supplier line, resolved from
                                         BankPaymentController::resolveSupplierBankAjax() for the voucher's payment
                                         currency (the pay-from bank account's own currency). Never blocks saving --
                                         a missing-currency row surfaces a warning only (L18's own "gap-report
                                         convention, not a silent fallback to another currency's details"). --}}
                                    <div class="sm:col-span-2 lg:col-span-4" x-show="item.bankResolution && item.bankResolution.is_supplier_target" x-cloak>
                                        <template x-if="item.bankResolution && item.bankResolution.found">
                                            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
                                                <span class="font-semibold">Remittance details (<span x-text="item.bankResolution.currency"></span>, default on file):</span>
                                                <span x-text="item.bankResolution.bank_detail?.bank_name"></span> &middot;
                                                <span x-text="item.bankResolution.bank_detail?.beneficiary_name"></span> &middot;
                                                <span class="font-mono" x-text="item.bankResolution.bank_detail?.iban || item.bankResolution.bank_detail?.account_number"></span> &middot;
                                                <span class="font-mono" x-text="item.bankResolution.bank_detail?.swift_bic"></span>
                                            </div>
                                        </template>
                                        <template x-if="item.bankResolution && !item.bankResolution.found">
                                            <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                                No bank details on file for <span x-text="item.bankResolution.supplier_name"></span> in <span x-text="item.bankResolution.currency"></span>. The voucher can still be saved; add remittance details on the supplier's page first if this payment needs them.
                                            </div>
                                        </template>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-gray-600">Amount (KWD)</label>
                                        <input type="number" step="0.001" min="0.001" :name="`items[${index}][credit]`" x-model.number="item.credit" @input="recalc" required
                                               class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                    </div>

                                    <div x-show="item.type_selector === 'bonus'" x-cloak>
                                        <label class="mb-1 block text-xs font-medium text-gray-600">Agent</label>
                                        <select :name="`items[${index}][agent_id]`" x-model="item.agent_id"
                                                class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                            <option value="">Select agent</option>
                                            <template x-for="agent in agents" :key="agent.id">
                                                <option :value="agent.id" x-text="agent.label"></option>
                                            </template>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-gray-600">Cheque no.</label>
                                        <input type="text" :name="`items[${index}][cheque_no]`" x-model="item.cheque_no" maxlength="100"
                                               class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-gray-600">Cheque date</label>
                                        <input type="date" :name="`items[${index}][cheque_date]`" x-model="item.cheque_date"
                                               class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                        <p class="mt-1 text-xs text-gray-500" x-show="item.cheque_no">Issued, not yet cleared — posts to cheques issued not cleared until manually cleared.</p>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-gray-600">Bank reference</label>
                                        <input type="text" :name="`items[${index}][bank_name]`" x-model="item.bank_name" maxlength="200"
                                               class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-gray-600">Authorization no.</label>
                                        <input type="text" :name="`items[${index}][auth_no]`" x-model="item.auth_no" maxlength="100"
                                               class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-gray-600">Bank charge (optional)</label>
                                        <input type="number" step="0.001" min="0" :name="`items[${index}][bank_charge_amount]`" x-model.number="item.bank_charge_amount"
                                               class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-gray-600">Cheque image</label>
                                        <input type="file" :name="`items[${index}][cheque_image]`" accept="image/png,image/jpeg,application/pdf"
                                               class="block w-full text-xs text-gray-600 file:mr-2 file:rounded-md file:border-0 file:bg-gray-100 file:px-2 file:py-1.5 file:text-xs file:font-medium file:text-gray-700 hover:file:bg-gray-200">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-gray-600">Line remarks</label>
                                        <input type="text" :name="`items[${index}][remarks]`" x-model="item.remarks"
                                               class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                </div>
                                <div class="mt-3 flex justify-end">
                                    <button type="button" @click="removeItem(index)" x-show="items.length > 1"
                                            class="rounded-md border border-red-200 bg-white px-2.5 py-1 text-xs font-medium text-red-600 hover:bg-red-50">
                                        Remove line
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="mt-4 rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-sm">
                        Total: <span class="font-semibold" x-text="totalAmount.toFixed(3)"></span> KWD across <span x-text="items.length"></span> line(s).
                    </div>
                </section>
                </template>

                <!-- Payment by date reconciliation -------------------------------------------------- -->
                <template x-if="bankpaymenttype === 'PaymentByDate'">
                <section class="rounded-lg border border-gray-200 bg-white p-6">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Select outstanding entries to reconcile</h2>
                        <button type="button" @click="loadJournalEntries" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Search
                        </button>
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">From</label>
                            <input type="date" x-model="reconcileFrom" class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">To</label>
                            <input type="date" x-model="reconcileTo" class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Supplier</label>
                            <input type="text" x-model="reconcileSupplier" placeholder="Filter by supplier name"
                                   class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>

                    <div class="mt-4 max-h-80 overflow-y-auto rounded-md border border-gray-200" x-show="reconcileResults.length" x-cloak>
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="sticky top-0 bg-gray-50">
                                <tr class="text-left text-xs font-medium uppercase text-gray-500">
                                    <th class="px-3 py-2"></th>
                                    <th class="px-3 py-2">Date</th>
                                    <th class="px-3 py-2">Account</th>
                                    <th class="px-3 py-2">Description</th>
                                    <th class="px-3 py-2 text-right">Outstanding (KWD)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="row in reconcileResults" :key="row.id">
                                    <tr>
                                        <td class="px-3 py-2"><input type="checkbox" :value="row.id" @change="toggleReconcileRow(row, $event.target.checked)"></td>
                                        <td class="px-3 py-2" x-text="row.transaction_date ? row.transaction_date.substring(0, 10) : ''"></td>
                                        <td class="px-3 py-2" x-text="row.account_name"></td>
                                        <td class="px-3 py-2" x-text="row.description"></td>
                                        <td class="px-3 py-2 text-right tabular-nums" x-text="Math.abs((parseFloat(row.credit)||0) - (parseFloat(row.debit)||0)).toFixed(3)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-sm text-gray-500" x-show="reconcileSearched && !reconcileResults.length" x-cloak>No outstanding entries found for this range.</p>

                    <div class="mt-4 rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800" x-show="reconcileSelected.length" x-cloak>
                        <span x-text="reconcileSelected.length"></span> selected, total KWD <span x-text="reconcileTotal.toFixed(3)"></span>. Select entries for one account at a time; run this again for a second account.
                    </div>

                    <template x-if="reconcileSelected.length">
                        <div>
                            <input type="hidden" name="items[0][type_selector]" value="account">
                            <input type="hidden" name="items[0][account_id]" :value="reconcileAccountId">
                            <input type="hidden" name="items[0][credit]" :value="reconcileTotal.toFixed(3)">
                            <input type="hidden" name="items[0][remarks]" value="Reconciliation by date">
                            <input type="hidden" name="items[0][transaction_id]" :value="reconcileSelected.map(r => r.id).join(',')">
                        </div>
                    </template>
                </section>
                </template>

                <!-- Remarks -------------------------------------------------------------------------- -->
                <section class="rounded-lg border border-gray-200 bg-white p-6">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Remarks</h2>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="remarks_create" class="mb-1 block text-sm font-medium text-gray-700">Remarks (visible on the voucher)</label>
                            <input id="remarks_create" type="text" name="remarks_create" value="{{ old('remarks_create') }}"
                                   class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="internal_remarks" class="mb-1 block text-sm font-medium text-gray-700">Internal remarks</label>
                            <input id="internal_remarks" type="text" name="internal_remarks" value="{{ old('internal_remarks') }}"
                                   class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>
                </section>

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('bank-payments.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                    <button type="submit" :disabled="submitting"
                            class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                        <span x-show="!submitting">Save payment voucher</span>
                        <span x-show="submitting" x-cloak>Saving…</span>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script>
        function bankPaymentForm(config) {
            return {
                bankpaymenttype: {{ Illuminate\Support\Js::from(old('bankpaymenttype', 'Payment')) }},
                pvAllowOverdraft: config.pvAllowOverdraft,
                approvalThreshold: config.approvalThreshold,
                accounts: config.accounts || [],
                agents: config.agents || [],
                items: [{ key: 1, type_selector: 'account', account_id: '', agent_id: '', credit: 0, cheque_no: '', cheque_date: '', bank_name: '', auth_no: '', bank_charge_amount: '', remarks: '', bankResolution: null }],
                nextKey: 2,
                totalAmount: 0,
                submitting: false,
                payFromAccountId: {{ Illuminate\Support\Js::from(old('pay_from_account', '')) }},

                reconcileFrom: '',
                reconcileTo: '',
                reconcileSupplier: '',
                reconcileResults: [],
                reconcileSelected: [],
                reconcileSearched: false,
                reconcileTotal: 0,
                reconcileAccountId: '',

                addItem() {
                    this.items.push({ key: this.nextKey++, type_selector: 'account', account_id: '', agent_id: '', credit: 0, cheque_no: '', cheque_date: '', bank_name: '', auth_no: '', bank_charge_amount: '', remarks: '', bankResolution: null });
                },
                removeItem(index) {
                    this.items.splice(index, 1);
                    this.recalc();
                },
                recalc() {
                    this.totalAmount = this.items.reduce((sum, item) => sum + (parseFloat(item.credit) || 0), 0);
                },
                // T14 -- resolve one line's supplier default bank details for the current
                // pay-from currency. A non-'supplier' line or an empty account clears the panel
                // rather than calling the endpoint. Never blocks the form -- a fetch failure just
                // leaves the panel empty.
                resolveBankDetail(index) {
                    const item = this.items[index];
                    if (!item || item.type_selector !== 'supplier' || !item.account_id) {
                        if (item) item.bankResolution = null;
                        return;
                    }
                    const params = new URLSearchParams({ account_id: item.account_id });
                    if (this.payFromAccountId) params.set('pay_from_account_id', this.payFromAccountId);
                    fetch(`/bank-payments/resolve-supplier-bank?${params.toString()}`, { headers: { 'Accept': 'application/json' } })
                        .then(r => r.json())
                        .then(data => { item.bankResolution = data; })
                        .catch(() => { item.bankResolution = null; });
                },
                resolveAllBankDetails() {
                    this.items.forEach((item, index) => this.resolveBankDetail(index));
                },
                onTypeChange() {
                    if (this.bankpaymenttype === 'PaymentByDate') {
                        const today = new Date();
                        const first = new Date(today.getFullYear(), today.getMonth(), 1);
                        this.reconcileFrom = first.toISOString().substring(0, 10);
                        this.reconcileTo = today.toISOString().substring(0, 10);
                    }
                },
                loadJournalEntries() {
                    if (!this.reconcileFrom || !this.reconcileTo) {
                        alert('Select both a from and to date.');
                        return;
                    }
                    this.reconcileSearched = true;
                    const params = new URLSearchParams({ from: this.reconcileFrom, to: this.reconcileTo, supplier: this.reconcileSupplier || '' });
                    fetch(`/bank-payments/fetch-journals-by-date?${params.toString()}`)
                        .then(r => r.json())
                        .then(data => { this.reconcileResults = Array.isArray(data) ? data : []; })
                        .catch(() => { this.reconcileResults = []; });
                },
                toggleReconcileRow(row, checked) {
                    if (checked) {
                        this.reconcileSelected.push(row);
                        this.reconcileAccountId = row.account_id || this.reconcileAccountId;
                    } else {
                        this.reconcileSelected = this.reconcileSelected.filter(r => r.id !== row.id);
                    }
                    this.reconcileTotal = this.reconcileSelected.reduce((sum, r) => sum + Math.abs((parseFloat(r.credit) || 0) - (parseFloat(r.debit) || 0)), 0);
                },
            };
        }
    </script>
</x-app-layout>
