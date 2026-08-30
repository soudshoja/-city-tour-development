<x-app-layout>
    @php
        $r = $invoiceReceipt;
        $company = $companies instanceof \App\Models\Company ? $companies : ($companies?->first());
        $statusStyles = [
            'pending' => 'bg-amber-50 text-amber-700 border-amber-200',
            'approved' => 'bg-green-50 text-green-700 border-green-200',
            'reversed' => 'bg-gray-100 text-gray-600 border-gray-300',
            'rejected' => 'bg-gray-100 text-gray-600 border-gray-300',
            'bounced' => 'bg-red-50 text-red-700 border-red-200',
        ];
        $statusStyle = $statusStyles[$r->status] ?? 'bg-gray-100 text-gray-600 border-gray-300';
        $chequeOutstanding = $r->cheque_no && ! $r->cheque_clearance_date && $r->status === 'approved';
        $chequeCleared = $r->cheque_no && $r->cheque_clearance_date && $r->status === 'approved';
        $fieldsDisabled = ! $canEditFields;
    @endphp

    <div class="mx-auto max-w-4xl">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-semibold text-gray-900">Receipt voucher {{ $r->voucher_number }}</h1>
                    <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $statusStyle }}">{{ ucfirst($r->status) }}</span>
                    @if ($isLocked)
                        <span class="inline-flex items-center rounded-full border border-gray-500 bg-gray-700 px-2.5 py-0.5 text-xs font-medium text-white" title="Locked by an accountant; edit/reverse are disabled">Locked</span>
                    @endif
                    @if ($isReconciled)
                        <span class="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700" title="A line on this voucher is reconciled; edit/reverse are disabled">Reconciled</span>
                    @endif
                </div>
                <p class="mt-1 text-sm text-gray-500">KWD {{ number_format((float) $r->amount, 3) }} &middot; {{ optional($r->doc_date)->format('d M Y') }}</p>
            </div>
            <a href="{{ route('receipt-voucher.index') }}" class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Back to receipt vouchers
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
            @if ($r->isPending() && $canApprove)
                <form method="POST" action="{{ route('receipt-voucher.approve', $r->id) }}">
                    @csrf
                    <button type="submit" class="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">Approve &amp; post</button>
                </form>
            @endif

            @if ($chequeOutstanding && $canReconcile)
                <form method="POST" action="{{ route('receipt-voucher.clear', $r->id) }}" class="flex items-center gap-2">
                    @csrf
                    <select name="bank_account_id" required class="rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Clear into bank…</option>
                        @foreach ($bankAccounts as $bank)
                            <option value="{{ $bank->id }}">[{{ $bank->code }}] {{ $bank->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-md border border-blue-300 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100">Clear cheque</button>
                </form>
            @endif

            @if ($chequeCleared && $canReconcile)
                <button type="button" onclick="document.getElementById('bounce-panel').classList.toggle('hidden')"
                        class="rounded-md border border-red-300 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-100">
                    Bounce cheque
                </button>
            @endif

            @if ($r->status === 'approved' && $canReverse)
                <form method="POST" action="{{ route('receipt-voucher.destroy', $r->id) }}" onsubmit="return confirm('Reverse this posted receipt voucher? This cannot be undone and creates a reversing entry.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Reverse</button>
                </form>
            @elseif ($r->isPending())
                <form method="POST" action="{{ route('receipt-voucher.destroy', $r->id) }}" onsubmit="return confirm('Delete this draft receipt voucher?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Delete draft</button>
                </form>
            @endif
        </div>

        @if ($chequeCleared && $canReconcile)
            <div id="bounce-panel" class="mb-6 hidden rounded-lg border border-red-200 bg-red-50 p-4">
                <form method="POST" action="{{ route('receipt-voucher.bounce', $r->id) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div>
                        <label class="mb-1 block text-xs font-medium text-red-700">Bounce fee to recharge the client (optional)</label>
                        <input type="number" step="0.001" min="0" name="bounce_fee_amount" class="w-40 rounded-md border-red-300 text-sm focus:border-red-500 focus:ring-red-500">
                    </div>
                    <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Confirm bounce</button>
                </form>
            </div>
        @endif

        @if ($r->cheque_image_path)
            <div class="mb-6 rounded-lg border border-gray-200 bg-white p-4">
                <p class="mb-2 text-sm font-medium text-gray-700">Cheque image on file</p>
                <a href="{{ route('receipt-voucher.cheque-image', $r->id) }}" target="_blank" rel="noopener" class="text-sm text-blue-600 hover:underline">View uploaded cheque image</a>
            </div>
        @endif

        <!-- Editable fields --------------------------------------------------------------------------- -->
        <form method="POST" action="{{ route('receipt-voucher.update', $r->id) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <input type="hidden" name="company_id" value="{{ $r->company_id }}">

            <div class="space-y-6">
                <section class="rounded-lg border border-gray-200 bg-white p-6">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Document details</h2>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="branch_id" class="mb-1 block text-sm font-medium text-gray-700">Branch</label>
                            <select id="branch_id" name="branch_id" required {{ $fieldsDisabled ? 'disabled' : '' }}
                                    class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-400">
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" {{ (int) $r->branch_id === $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="docdate" class="mb-1 block text-sm font-medium text-gray-700">Document date</label>
                            <input id="docdate" type="date" name="docdate" required value="{{ optional($r->doc_date)->toDateString() }}" {{ $fieldsDisabled ? 'disabled' : '' }}
                                   class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-400">
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-6">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Receipt</h2>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="type" class="mb-1 block text-sm font-medium text-gray-700">Receipt type</label>
                            <select id="type" name="type" required {{ $fieldsDisabled ? 'disabled' : '' }}
                                    class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-400">
                                <option value="account" {{ $r->type === 'account' ? 'selected' : '' }}>Account payment</option>
                                <option value="invoice" {{ $r->type === 'invoice' ? 'selected' : '' }}>Invoice payment</option>
                                <option value="credit" {{ $r->type === 'credit' ? 'selected' : '' }}>Client credit top-up</option>
                                <option value="import" {{ $r->type === 'import' ? 'selected' : '' }}>Imported historical receipt</option>
                            </select>
                        </div>
                        <div>
                            <label for="amount" class="mb-1 block text-sm font-medium text-gray-700">Amount (KWD)</label>
                            <input id="amount" type="number" step="0.001" min="0.001" name="amount" required value="{{ $r->amount }}" {{ $fieldsDisabled ? 'disabled' : '' }}
                                   class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-400">
                        </div>
                        @if ($r->type === 'account')
                            <div>
                                <label for="account_id" class="mb-1 block text-sm font-medium text-gray-700">Account</label>
                                <select id="account_id" name="account_id" {{ $fieldsDisabled ? 'disabled' : '' }}
                                        class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-400">
                                    @foreach ($accpayreceives as $acc)
                                        <option value="{{ $acc->id }}" {{ (int) $r->account_id === $acc->id ? 'selected' : '' }}>[{{ $acc->code }}] {{ $acc->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @else
                            <div>
                                <label for="client_id" class="mb-1 block text-sm font-medium text-gray-700">Client</label>
                                <select id="client_id" name="client_id" {{ $fieldsDisabled ? 'disabled' : '' }}
                                        class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-400">
                                    <option value="">None</option>
                                    @foreach ($clients as $client)
                                        <option value="{{ $client->id }}" {{ (int) $r->client_id === $client->id ? 'selected' : '' }}>{{ $client->full_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>
                </section>

                @if ($r->type === 'invoice')
                    @php $existingAllocations = is_array($r->allocations) && $r->allocations !== [] ? $r->allocations : ($r->invoice_id ? [['invoice_id' => $r->invoice_id, 'amount' => $r->amount]] : []); @endphp
                    <section class="rounded-lg border border-gray-200 bg-white p-6">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Applied to invoices</h2>
                        <div class="mt-4 space-y-3">
                            @foreach ($existingAllocations as $i => $alloc)
                                <div class="flex flex-wrap items-end gap-3 rounded-md border border-gray-100 bg-gray-50 p-3">
                                    <div class="min-w-[16rem] flex-1">
                                        <label class="mb-1 block text-xs font-medium text-gray-600">Invoice</label>
                                        <select name="allocations[{{ $i }}][invoice_id]" required {{ $fieldsDisabled ? 'disabled' : '' }}
                                                class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-400">
                                            @foreach ($unpaidInvoices as $inv)
                                                <option value="{{ $inv->id }}" {{ (int) $alloc['invoice_id'] === $inv->id ? 'selected' : '' }}>
                                                    {{ $inv->invoice_number ?? ('#'.$inv->id) }} — {{ $inv->client->full_name ?? 'No client' }} — KWD {{ number_format((float) $inv->amount, 3) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="w-40">
                                        <label class="mb-1 block text-xs font-medium text-gray-600">Amount (KWD)</label>
                                        <input type="number" step="0.001" min="0.001" name="allocations[{{ $i }}][amount]" value="{{ $alloc['amount'] }}" required {{ $fieldsDisabled ? 'disabled' : '' }}
                                               class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-400">
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <p class="mt-3 text-xs text-gray-500">Remainder disposition: <span class="font-medium">{{ ucfirst($overpayPolicy) }}</span>. Current remainder: KWD {{ number_format((float) $r->remainder_amount, 3) }}.</p>
                    </section>
                @endif

                <section class="rounded-lg border border-gray-200 bg-white p-6">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Instrument</h2>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="bank_account_id" class="mb-1 block text-sm font-medium text-gray-700">Bank account</label>
                            <select id="bank_account_id" name="bank_account_id" {{ $fieldsDisabled ? 'disabled' : '' }}
                                    class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-400">
                                <option value="">Cash in hand</option>
                                @foreach ($bankAccounts as $bank)
                                    <option value="{{ $bank->id }}" {{ (int) $r->bank_account_id === $bank->id ? 'selected' : '' }}>[{{ $bank->code }}] {{ $bank->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="cheque_no" class="mb-1 block text-sm font-medium text-gray-700">Cheque no.</label>
                            <input id="cheque_no" type="text" name="cheque_no" value="{{ $r->cheque_no }}" maxlength="100" {{ $fieldsDisabled ? 'disabled' : '' }}
                                   class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-400">
                        </div>
                        <div>
                            <label for="cheque_date" class="mb-1 block text-sm font-medium text-gray-700">Cheque date</label>
                            <input id="cheque_date" type="date" name="cheque_date" value="{{ optional($r->cheque_date)->toDateString() }}" {{ $fieldsDisabled ? 'disabled' : '' }}
                                   class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-400">
                        </div>
                        <div>
                            <label for="bank_info" class="mb-1 block text-sm font-medium text-gray-700">Bank reference</label>
                            <input id="bank_info" type="text" name="bank_info" value="{{ $r->bank_info }}" maxlength="200" {{ $fieldsDisabled ? 'disabled' : '' }}
                                   class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-400">
                        </div>
                        <div>
                            <label for="auth_no" class="mb-1 block text-sm font-medium text-gray-700">Authorization no.</label>
                            <input id="auth_no" type="text" name="auth_no" value="{{ $r->auth_no }}" maxlength="100" {{ $fieldsDisabled ? 'disabled' : '' }}
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
                            <input id="remarks_create" type="text" name="remarks_create" value="{{ $r->remarks }}" {{ $fieldsDisabled ? 'disabled' : '' }}
                                   class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-400">
                        </div>
                        <div>
                            <label for="internal_remarks" class="mb-1 block text-sm font-medium text-gray-700">Internal remarks</label>
                            <input id="internal_remarks" type="text" name="internal_remarks" value="{{ $r->remarks_internal }}" {{ $fieldsDisabled ? 'disabled' : '' }}
                                   class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-50 disabled:text-gray-400">
                        </div>
                    </div>
                </section>

                @unless ($fieldsDisabled)
                    <div class="flex items-center justify-end gap-3">
                        <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                            {{ $r->isPending() ? 'Save changes' : 'Save (reverse and repost)' }}
                        </button>
                    </div>
                @endunless
            </div>
        </form>

        @if ($journalEntries->isNotEmpty())
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
                            @foreach ($journalEntries as $line)
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
