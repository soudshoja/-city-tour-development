<x-app-layout>
    @php
        $company = $companies instanceof \App\Models\Company ? $companies : ($companies?->first());
    @endphp

    <div class="mx-auto max-w-4xl">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">New receipt voucher</h1>
                <p class="mt-1 text-sm text-gray-500">Record money coming in and apply it to an invoice, an account, or a client credit top-up.</p>
            </div>
            <a href="{{ route('receipt-voucher.index') }}" class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Back to receipt vouchers
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

        <form method="POST" action="{{ route('receipt-voucher.store') }}" enctype="multipart/form-data"
              x-data="receiptVoucherForm({
                  amount: {{ (float) old('amount', 0) }},
                  overpayPolicy: @js($overpayPolicy),
                  approvalThreshold: @js($approvalThreshold),
                  invoices: @js($unpaidInvoices->map(fn ($inv) => [
                      'id' => $inv->id,
                      'label' => ($inv->invoice_number ?? ('#'.$inv->id)).' — '.($inv->client->full_name ?? 'No client').' — KWD '.number_format((float) $inv->amount, 3),
                      'client_id' => $inv->client_id,
                  ])),
                  initialAllocations: @js(old('allocations', [])),
              })"
              @submit="onSubmit">
            @csrf

            <input type="hidden" name="company_id" value="{{ $company?->id }}">

            <div class="space-y-6">
                <!-- Document details ------------------------------------------------------------ -->
                <section class="rounded-lg border border-gray-200 bg-white p-6">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Document details</h2>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                </section>

                <!-- Receipt type + amount --------------------------------------------------------- -->
                <section class="rounded-lg border border-gray-200 bg-white p-6">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Receipt</h2>

                    <div class="mt-4">
                        <label for="type" class="mb-1 block text-sm font-medium text-gray-700">Receipt type <span class="text-red-500">*</span></label>
                        <select id="type" name="type" x-model="type" required class="w-full max-w-sm rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="account" {{ old('type', 'account') === 'account' ? 'selected' : '' }}>Account payment</option>
                            <option value="invoice" {{ old('type') === 'invoice' ? 'selected' : '' }}>Invoice payment</option>
                            <option value="credit" {{ old('type') === 'credit' ? 'selected' : '' }}>Client credit top-up</option>
                            <option value="import" {{ old('type') === 'import' ? 'selected' : '' }}>Imported historical receipt</option>
                        </select>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div x-show="type === 'account'" x-cloak>
                            <label for="account_id" class="mb-1 block text-sm font-medium text-gray-700">Account <span class="text-red-500">*</span></label>
                            <select id="account_id" name="account_id" class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500" :required="type === 'account'">
                                <option value="">Select account</option>
                                @foreach ($lastLevelAccounts as $acc)
                                    <option value="{{ $acc->id }}" {{ (string) old('account_id') === (string) $acc->id ? 'selected' : '' }}>
                                        [{{ $acc->code }}] {{ $acc->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div x-show="type === 'credit' || type === 'import' || type === 'invoice'" x-cloak>
                            <label for="client_id" class="mb-1 block text-sm font-medium text-gray-700">
                                Client <span x-show="type === 'credit' || type === 'import'" class="text-red-500">*</span>
                            </label>
                            <select id="client_id" name="client_id" x-model="clientId" class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500" :required="type === 'credit' || type === 'import'">
                                <option value="">Select client</option>
                                @foreach ($clients as $client)
                                    <option value="{{ $client->id }}" {{ (string) old('client_id') === (string) $client->id ? 'selected' : '' }}>{{ $client->full_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="amount" class="mb-1 block text-sm font-medium text-gray-700">Amount (KWD) <span class="text-red-500">*</span></label>
                            <input id="amount" type="number" step="0.001" min="0.001" name="amount" required
                                   x-model.number="amount" @input="recalc"
                                   value="{{ old('amount') }}"
                                   class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                            <p class="mt-1 text-xs text-gray-500" x-show="approvalThreshold !== null" x-cloak>
                                Auto-approves at or under KWD <span x-text="Number(approvalThreshold).toFixed(3)"></span>.
                            </p>
                            <p class="mt-1 text-xs text-gray-500" x-show="approvalThreshold === null" x-cloak>
                                This company always requires a manual approval step.
                            </p>
                        </div>
                    </div>
                </section>

                <!-- Allocation lines (invoice type only) ------------------------------------------ -->
                <section class="rounded-lg border border-gray-200 bg-white p-6" x-show="type === 'invoice'" x-cloak>
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Apply to invoices</h2>
                        <button type="button" @click="addAllocation" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            + Add invoice line
                        </button>
                    </div>

                    <div class="mt-4 space-y-3">
                        <template x-for="(row, index) in allocations" :key="index">
                            <div class="flex flex-wrap items-end gap-3 rounded-md border border-gray-100 bg-gray-50 p-3">
                                <div class="min-w-[16rem] flex-1">
                                    <label class="mb-1 block text-xs font-medium text-gray-600">Invoice</label>
                                    <select :name="`allocations[${index}][invoice_id]`" x-model="row.invoice_id" @change="recalc" required
                                            class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                        <option value="">Select invoice</option>
                                        <template x-for="inv in invoices" :key="inv.id">
                                            <option :value="inv.id" x-text="inv.label"></option>
                                        </template>
                                    </select>
                                </div>
                                <div class="w-40">
                                    <label class="mb-1 block text-xs font-medium text-gray-600">Amount (KWD)</label>
                                    <input type="number" step="0.001" min="0.001" :name="`allocations[${index}][amount]`"
                                           x-model.number="row.amount" @input="recalc" required
                                           class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>
                                <button type="button" @click="removeAllocation(index)" x-show="allocations.length > 1"
                                        class="rounded-md border border-red-200 bg-white px-2.5 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50">
                                    Remove
                                </button>
                            </div>
                        </template>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-sm">
                        <div>
                            Allocated: <span class="font-semibold" x-text="allocatedTotal.toFixed(3)"></span> KWD
                            &middot; Remainder: <span class="font-semibold" :class="remainder > 0.0005 ? 'text-amber-700' : 'text-gray-700'" x-text="remainder.toFixed(3)"></span> KWD
                        </div>
                        <div x-show="remainder > 0.0005" x-cloak class="text-xs">
                            <template x-if="overpayPolicy === 'block'">
                                <span class="font-medium text-red-700">This company blocks overpayment — reduce the amount or add another allocation before saving.</span>
                            </template>
                            <template x-if="overpayPolicy === 'credit'">
                                <span class="text-gray-600">The remainder posts as spendable client credit.</span>
                            </template>
                            <template x-if="overpayPolicy === 'hold'">
                                <span class="text-gray-600">The remainder is held as client credit pending release by an accountant.</span>
                            </template>
                        </div>
                    </div>
                </section>

                <!-- Instrument ---------------------------------------------------------------------- -->
                <section class="rounded-lg border border-gray-200 bg-white p-6">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Instrument</h2>
                    <p class="mt-1 text-xs text-gray-500">Leave the bank account blank to receive into cash in hand. A cheque dated after the document date floats as a post-dated cheque until cleared.</p>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="bank_account_id" class="mb-1 block text-sm font-medium text-gray-700">Bank account</label>
                            <select id="bank_account_id" name="bank_account_id" class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Cash in hand</option>
                                @foreach ($bankAccounts as $bank)
                                    <option value="{{ $bank->id }}" {{ (string) old('bank_account_id') === (string) $bank->id ? 'selected' : '' }}>[{{ $bank->code }}] {{ $bank->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="cheque_no" class="mb-1 block text-sm font-medium text-gray-700">Cheque no.</label>
                            <input id="cheque_no" type="text" name="cheque_no" value="{{ old('cheque_no') }}" maxlength="100"
                                   class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="cheque_date" class="mb-1 block text-sm font-medium text-gray-700">Cheque date</label>
                            <input id="cheque_date" type="date" name="cheque_date" value="{{ old('cheque_date') }}"
                                   class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="bank_info" class="mb-1 block text-sm font-medium text-gray-700">Bank reference</label>
                            <input id="bank_info" type="text" name="bank_info" value="{{ old('bank_info') }}" maxlength="200"
                                   placeholder="Drawee bank / branch" class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="auth_no" class="mb-1 block text-sm font-medium text-gray-700">Authorization no.</label>
                            <input id="auth_no" type="text" name="auth_no" value="{{ old('auth_no') }}" maxlength="100"
                                   class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="cheque_image" class="mb-1 block text-sm font-medium text-gray-700">Cheque image</label>
                            <input id="cheque_image" type="file" name="cheque_image" accept="image/png,image/jpeg,application/pdf"
                                   class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200">
                            <p class="mt-1 text-xs text-gray-500">JPG, PNG or PDF, up to 5 MB.</p>
                        </div>
                    </div>
                </section>

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
                    <a href="{{ route('receipt-voucher.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                    <button type="submit" :disabled="submitting" @click="submitting = true"
                            class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                        <span x-show="!submitting">Save receipt voucher</span>
                        <span x-show="submitting" x-cloak>Saving…</span>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script>
        function receiptVoucherForm(config) {
            return {
                type: {{ Illuminate\Support\Js::from(old('type', 'account')) }},
                clientId: {{ Illuminate\Support\Js::from(old('client_id', '')) }},
                amount: config.amount || 0,
                overpayPolicy: config.overpayPolicy,
                approvalThreshold: config.approvalThreshold,
                invoices: config.invoices || [],
                allocations: (config.initialAllocations && config.initialAllocations.length)
                    ? config.initialAllocations.map(a => ({ invoice_id: a.invoice_id || '', amount: a.amount || 0 }))
                    : [{ invoice_id: '', amount: 0 }],
                allocatedTotal: 0,
                remainder: 0,
                submitting: false,

                addAllocation() {
                    this.allocations.push({ invoice_id: '', amount: 0 });
                },
                removeAllocation(index) {
                    this.allocations.splice(index, 1);
                    this.recalc();
                },
                recalc() {
                    this.allocatedTotal = this.allocations.reduce((sum, row) => sum + (parseFloat(row.amount) || 0), 0);
                    this.remainder = Math.max(0, (parseFloat(this.amount) || 0) - this.allocatedTotal);
                },
                onSubmit(event) {
                    if (this.type === 'invoice' && this.overpayPolicy === 'block' && this.remainder > 0.0005) {
                        event.preventDefault();
                        this.submitting = false;
                        alert('This receipt would leave an unapplied remainder and this company blocks overpayment. Reduce the amount or add another allocation.');
                    }
                },
            };
        }
    </script>
</x-app-layout>
