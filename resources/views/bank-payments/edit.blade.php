<x-app-layout>
    @php
        $bp = $bankPayment;
        $company = $companies instanceof \App\Models\Company ? $companies : ($companies?->first());
        $statusStyles = [
            'pending' => 'bg-amber-50 text-amber-700 border-amber-200',
            'approved' => 'bg-green-50 text-green-700 border-green-200',
            'reversed' => 'bg-gray-100 text-gray-600 border-gray-300',
        ];
        $statusStyle = $statusStyles[$bp->status] ?? 'bg-gray-100 text-gray-600 border-gray-300';
        $chequeOutstanding = $bp->cheque_no && ! $bp->cheque_clearance_date && $bp->status === 'approved';
        $fieldsDisabled = ! $canEditFields;
    @endphp

    <div class="mx-auto max-w-4xl">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-semibold text-gray-900">Payment voucher {{ $bp->voucher_number }}</h1>
                    <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $statusStyle }}">{{ ucfirst($bp->status) }}</span>
                    @if ($isLocked)
                        <span class="inline-flex items-center rounded-full border border-gray-500 bg-gray-700 px-2.5 py-0.5 text-xs font-medium text-white" title="Locked by an accountant; edit/reverse are disabled">Locked</span>
                    @endif
                    @if ($isReconciled)
                        <span class="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700" title="A line on this voucher is reconciled; edit/reverse are disabled">Reconciled</span>
                    @endif
                </div>
                <p class="mt-1 text-sm text-gray-500">KWD {{ number_format((float) $bp->amount, 3) }} to {{ $payeeName ?: 'N/A' }} &middot; {{ optional($bp->doc_date)->format('d M Y') }}</p>
            </div>
            <a href="{{ route('bank-payments.index') }}" class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Back to payment vouchers
            </a>
        </div>

        @if (session('success'))
            <div class="mb-6 rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-700">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-6 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ session('error') }}</div>
        @endif

        @if ($isLocked || $isReconciled)
            <div class="mb-6 rounded-md border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600">
                This voucher {{ $isLocked ? 'is locked' : '' }}{{ $isLocked && $isReconciled ? ' and has' : ($isReconciled ? 'has' : '') }}{{ $isReconciled ? ' a reconciled line' : '' }}. Editing and reversal are disabled
                @if ($isReconciled) until the line is un-reconciled @endif
                @if ($isLocked) — contact your accountant to unlock it @endif.
            </div>
        @endif

        <!-- Actions --------------------------------------------------------------------------------- -->
        <div class="mb-6 flex flex-wrap items-center gap-2">
            @if ($bp->isPending() && $canApprove)
                <form method="POST" action="{{ route('bank-payments.approve', $bp->id) }}">
                    @csrf
                    <button type="submit" class="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">Approve &amp; post</button>
                </form>
            @endif

            @if ($chequeOutstanding && $canReconcile)
                <form method="POST" action="{{ route('bank-payments.clear', $bp->id) }}" class="flex items-center gap-2">
                    @csrf
                    <select name="bank_account_id" required class="rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Clear from bank…</option>
                        @foreach ($bankAccounts as $bank)
                            <option value="{{ $bank->id }}">[{{ $bank->code }}] {{ $bank->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-md border border-blue-300 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100">Clear cheque</button>
                </form>
            @endif

            @if ($bp->status === 'approved' && $canReverse)
                <form method="POST" action="{{ route('bank-payments.destroy', $bp->id) }}" onsubmit="return confirm('Reverse this posted payment voucher? This cannot be undone and creates a reversing entry.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Reverse</button>
                </form>
            @elseif ($bp->isPending())
                <form method="POST" action="{{ route('bank-payments.destroy', $bp->id) }}" onsubmit="return confirm('Delete this draft payment voucher?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Delete draft</button>
                </form>
            @endif
        </div>

        @if ($bp->cheque_image_path)
            <div class="mb-6 rounded-lg border border-gray-200 bg-white p-4">
                <p class="mb-2 text-sm font-medium text-gray-700">Cheque image on file</p>
                <a href="{{ route('bank-payments.cheque-image', $bp->id) }}" target="_blank" rel="noopener" class="text-sm text-blue-600 hover:underline">View uploaded cheque image</a>
            </div>
        @endif

        <!-- Editable fields --------------------------------------------------------------------------- -->
        <form method="POST" action="{{ route('bank-payments.update', $bp->id) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="space-y-6">
                <section class="rounded-lg border border-gray-200 bg-white p-6">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Document details</h2>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="bankpaymenttype" class="mb-1 block text-sm font-medium text-gray-700">Payment type</label>
                            <select id="bankpaymenttype" name="bankpaymenttype" required {{ $fieldsDisabled ? 'disabled' : '' }}
                                    class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-400">
                                <option value="Payment" {{ $bp->sub_type !== 'BY_DATE' ? 'selected' : '' }}>Payment</option>
                                <option value="PaymentByDate" {{ $bp->sub_type === 'BY_DATE' ? 'selected' : '' }}>Payment by date</option>
                                <option value="Refund" {{ $bp->sub_type === 'REFUND_OUT' ? 'selected' : '' }}>Refund</option>
                            </select>
                        </div>
                        <div>
                            <label for="docdate" class="mb-1 block text-sm font-medium text-gray-700">Document date</label>
                            <input id="docdate" type="date" name="docdate" required value="{{ optional($bp->doc_date)->toDateString() }}" {{ $fieldsDisabled ? 'disabled' : '' }}
                                   class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-400">
                        </div>
                        <div>
                            <label for="pay_from_account" class="mb-1 block text-sm font-medium text-gray-700">Pay from</label>
                            <select id="pay_from_account" name="pay_from_account" required {{ $fieldsDisabled ? 'disabled' : '' }}
                                    class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-400">
                                @foreach ($bankAccounts as $bank)
                                    <option value="{{ $bank->id }}" {{ (int) $bp->pay_from_account_id === $bank->id ? 'selected' : '' }}>[{{ $bank->code }}] {{ $bank->name }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">
                                @if ($pvAllowOverdraft) This company allows this account to go negative. @else A payment that would take this account negative is refused. @endif
                            </p>
                        </div>
                        <div>
                            <label for="account_id" class="mb-1 block text-sm font-medium text-gray-700">Pay to</label>
                            <select id="account_id" name="account_id" required {{ $fieldsDisabled ? 'disabled' : '' }}
                                    class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-400">
                                @foreach ($accpayreceives as $acc)
                                    <option value="{{ $acc->id }}" {{ (int) $bp->target_account_id === $acc->id ? 'selected' : '' }}>[{{ $acc->code }}] {{ $acc->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-6">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Amount</h2>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="amount" class="mb-1 block text-sm font-medium text-gray-700">Amount (KWD)</label>
                            <input id="amount" type="number" step="0.001" min="0.001" name="amount" required value="{{ $bp->amount }}" {{ $fieldsDisabled ? 'disabled' : '' }}
                                   class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-400">
                        </div>
                        <div>
                            <label for="type_selector" class="mb-1 block text-sm font-medium text-gray-700">Line type</label>
                            <select id="type_selector" name="type_selector" {{ $fieldsDisabled ? 'disabled' : '' }}
                                    class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-400">
                                <option value="account" {{ $bp->sub_type === 'ACCOUNT' ? 'selected' : '' }}>Account</option>
                                <option value="supplier" {{ $bp->sub_type === 'SUPPLIER' ? 'selected' : '' }}>Supplier</option>
                                <option value="bonus" {{ $bp->sub_type === 'BONUS' ? 'selected' : '' }}>Agent bonus</option>
                            </select>
                        </div>
                        @if ($bp->sub_type === 'BONUS')
                            <div>
                                <label for="agent_id" class="mb-1 block text-sm font-medium text-gray-700">Agent</label>
                                <select id="agent_id" name="agent_id" {{ $fieldsDisabled ? 'disabled' : '' }}
                                        class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-400">
                                    <option value="">None</option>
                                    @foreach (\App\Models\Agent::whereHas('branch', fn ($q) => $q->where('company_id', $bp->company_id))->get() as $agent)
                                        <option value="{{ $agent->id }}" {{ (int) $bp->agent_id === $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div>
                            <label for="bank_charge_amount" class="mb-1 block text-sm font-medium text-gray-700">Bank charge (optional)</label>
                            <input id="bank_charge_amount" type="number" step="0.001" min="0" name="bank_charge_amount" value="{{ $bp->bank_charge_amount }}" {{ $fieldsDisabled ? 'disabled' : '' }}
                                   class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-400">
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-6">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Instrument</h2>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="cheque_no" class="mb-1 block text-sm font-medium text-gray-700">Cheque no.</label>
                            <input id="cheque_no" type="text" name="cheque_no" value="{{ $bp->cheque_no }}" maxlength="100" {{ $fieldsDisabled ? 'disabled' : '' }}
                                   class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-400">
                            <p class="mt-1 text-xs text-gray-500">Set means "issued, not yet cleared" — posts to cheques issued not cleared until manually cleared.</p>
                        </div>
                        <div>
                            <label for="cheque_date" class="mb-1 block text-sm font-medium text-gray-700">Cheque date</label>
                            <input id="cheque_date" type="date" name="cheque_date" value="{{ optional($bp->cheque_date)->toDateString() }}" {{ $fieldsDisabled ? 'disabled' : '' }}
                                   class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-400">
                        </div>
                        <div>
                            <label for="bank_name" class="mb-1 block text-sm font-medium text-gray-700">Bank reference</label>
                            <input id="bank_name" type="text" name="bank_name" value="{{ $bp->bank_info }}" maxlength="200" {{ $fieldsDisabled ? 'disabled' : '' }}
                                   class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-400">
                        </div>
                        <div>
                            <label for="auth_no" class="mb-1 block text-sm font-medium text-gray-700">Authorization no.</label>
                            <input id="auth_no" type="text" name="auth_no" value="{{ $bp->auth_no }}" maxlength="100" {{ $fieldsDisabled ? 'disabled' : '' }}
                                   class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-400">
                        </div>
                        @unless ($fieldsDisabled)
                            <div>
                                <label for="cheque_image" class="mb-1 block text-sm font-medium text-gray-700">Replace cheque image</label>
                                <input id="cheque_image" type="file" name="cheque_image" accept="image/png,image/jpeg,application/pdf"
                                       class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200">
                            </div>
                        @endunless
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-6">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Remarks</h2>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="remarks_create" class="mb-1 block text-sm font-medium text-gray-700">Remarks</label>
                            <input id="remarks_create" type="text" name="remarks_create" value="{{ $bp->remarks }}" {{ $fieldsDisabled ? 'disabled' : '' }}
                                   class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-400">
                        </div>
                        <div>
                            <label for="internal_remarks" class="mb-1 block text-sm font-medium text-gray-700">Internal remarks</label>
                            <input id="internal_remarks" type="text" name="internal_remarks" value="{{ $bp->remarks_internal }}" {{ $fieldsDisabled ? 'disabled' : '' }}
                                   class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-400">
                        </div>
                    </div>
                </section>

                @unless ($fieldsDisabled)
                    <div class="flex items-center justify-end gap-3">
                        <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                            {{ $bp->isPending() ? 'Save changes' : 'Save (reverse and repost)' }}
                        </button>
                    </div>
                @endunless
            </div>
        </form>

        @if ($JournalEntrys->isNotEmpty())
            <section class="mt-6 rounded-lg border border-gray-200 bg-white p-6">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Posted journal lines</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-xs font-medium uppercase text-gray-500">
                                <th class="py-2 pr-4">Account</th>
                                <th class="py-2 pr-4 text-right">Debit</th>
                                <th class="py-2 pr-4 text-right">Credit</th>
                                <th class="py-2 pr-4">Reconciled</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($JournalEntrys as $line)
                                <tr>
                                    <td class="py-2 pr-4">{{ $line->account?->name ?? $line->account_id }}</td>
                                    <td class="py-2 pr-4 text-right tabular-nums">{{ number_format((float) $line->debit, 3) }}</td>
                                    <td class="py-2 pr-4 text-right tabular-nums">{{ number_format((float) $line->credit, 3) }}</td>
                                    <td class="py-2 pr-4">
                                        @if ((int) $line->reconciled === 1)
                                            <span class="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">Reconciled</span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</x-app-layout>
