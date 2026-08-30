@php
    /**
     * W4.U §b — staff-only refund console. Included only from refunds/show.blade.php's
     * non-public branch (see that file). Every action here is a plain POST to an EXISTING
     * RefundController route (approve/reject/complete-process) gated by the matching
     * RefundPolicy ability -- this partial never introduces a new write path of its own.
     */
    $statusOrder = ['draft', 'approved', 'posted', 'completed'];
    $currentStatus = $refund->status;
    // Legacy OFF-path statuses ('pending'/'processed'/'declined') map onto the nearest new-workflow
    // step for display only -- never written back, never used for gating (RefundPolicy owns that).
    $displayStatus = match ($currentStatus) {
        'pending' => 'draft',
        'processed' => 'completed',
        'declined' => 'rejected',
        default => $currentStatus,
    };
    $isRejected = $displayStatus === 'rejected';
    $currentStep = array_search($displayStatus, $statusOrder, true);
    $currentStep = $currentStep === false ? 0 : $currentStep;

    $methodDispositionMap = [
        'Credit' => 'Client store credit (2632)',
        'Cash' => 'Cash payout (PV, refund-out)',
        'Bank' => 'Bank payout (PV, refund-out)',
        'Online' => 'Gateway refund (async, on completion)',
    ];
@endphp

<div class="bg-white shadow-lg rounded-lg p-6 space-y-6">

    {{-- Status timeline --------------------------------------------------------------------- --}}
    <div>
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Refund status</h2>

        @if ($isRejected)
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Rejected
                </span>
                <span class="text-sm text-gray-500">
                    @if($refund->rejected_at) on {{ $refund->rejected_at->format('d M Y, H:i') }} @endif
                </span>
            </div>
        @else
            <ol class="flex items-center w-full">
                @foreach ($statusOrder as $index => $step)
                    <li class="flex items-center {{ $index < count($statusOrder) - 1 ? 'flex-1' : '' }}">
                        <div class="flex flex-col items-center">
                            <span class="flex items-center justify-center w-7 h-7 rounded-full text-xs font-semibold shrink-0
                                {{ $index < $currentStep ? 'bg-emerald-600 text-white' : ($index === $currentStep ? 'bg-emerald-600 text-white ring-4 ring-emerald-100' : 'bg-gray-200 text-gray-500') }}">
                                @if ($index < $currentStep)
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                @else
                                    {{ $index + 1 }}
                                @endif
                            </span>
                            <span class="mt-1 text-xs font-medium {{ $index <= $currentStep ? 'text-gray-800' : 'text-gray-400' }}">
                                {{ ucfirst($step) }}
                            </span>
                        </div>
                        @if ($index < count($statusOrder) - 1)
                            <div class="flex-1 h-0.5 mx-2 {{ $index < $currentStep ? 'bg-emerald-600' : 'bg-gray-200' }}"></div>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif
    </div>

    {{-- Gated actions -------------------------------------------------------------------- --}}
    <div class="flex flex-wrap items-center gap-2 border-t border-gray-100 pt-4">
        @can('update', $refund)
            <a href="{{ route('refunds.edit', $refund->id) }}" class="px-3 py-1.5 text-sm font-medium rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50 transition-colors">
                Edit draft
            </a>
        @endcan

        @can('approve', $refund)
            <form method="POST" action="{{ route('refunds.approve', $refund->id) }}">
                @csrf
                <button type="submit" class="px-3 py-1.5 text-sm font-medium rounded-md bg-emerald-600 text-white hover:bg-emerald-700 transition-colors">
                    Approve
                </button>
            </form>
        @endcan

        @can('reject', $refund)
            <form method="POST" action="{{ route('refunds.reject', $refund->id) }}" onsubmit="return confirm('Reject this refund? The draft will be voided, never deleted.');">
                @csrf
                <button type="submit" class="px-3 py-1.5 text-sm font-medium rounded-md border border-red-300 text-red-700 hover:bg-red-50 transition-colors">
                    Reject
                </button>
            </form>
        @endcan

        @can('complete', $refund)
            <form method="POST" action="{{ route('refunds.complete_process', $refund->id) }}">
                @csrf
                <button type="submit" class="px-3 py-1.5 text-sm font-medium rounded-md bg-blue-600 text-white hover:bg-blue-700 transition-colors">
                    Post refund
                </button>
            </form>
        @endcan

        @cannot('update', $refund)
            @cannot('approve', $refund)
                @cannot('reject', $refund)
                    @cannot('complete', $refund)
                        <span class="text-sm text-gray-400">No further action available for your role at this status.</span>
                    @endcannot
                @endcannot
            @endcannot
        @endcannot
    </div>

    {{-- Method / disposition ---------------------------------------------------------------- --}}
    <div class="border-t border-gray-100 pt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
        <div>
            <div class="text-gray-500">Refund method</div>
            <div class="font-medium text-gray-800">{{ $refund->method ?? '—' }}</div>
            <div class="text-xs text-gray-400 mt-0.5">Drives: {{ $methodDispositionMap[$refund->method] ?? 'company default disposition' }}</div>
        </div>
        <div>
            <div class="text-gray-500">Disposition</div>
            <div class="font-medium text-gray-800">
                {{ $refund->disposition ? ucfirst(str_replace('_', ' ', $refund->disposition)) : 'Not yet posted' }}
            </div>
            <div class="text-xs text-gray-400 mt-0.5">
                Company default: {{ ucfirst(str_replace('_', ' ', $accountingSettings['invoice_overpay_cancel_policy'] ?? 'credit')) }}
            </div>
        </div>
    </div>

    {{-- Notification toggles (read-only here; edited in Settings → Accounting) --------------- --}}
    <div class="border-t border-gray-100 pt-4 flex flex-wrap gap-4 text-xs text-gray-500">
        <span class="inline-flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full {{ ($accountingSettings['refund_send_on_post'] ?? true) ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
            Client CRN + statement on post: {{ ($accountingSettings['refund_send_on_post'] ?? true) ? 'on' : 'off' }}
        </span>
        <span class="inline-flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full {{ ($accountingSettings['agent_unearn_notice'] ?? true) ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
            Agent commission-unearned notice: {{ ($accountingSettings['agent_unearn_notice'] ?? true) ? 'on' : 'off' }}
        </span>
    </div>

    {{-- Batch siblings (multi-invoice picker grouping) --------------------------------------- --}}
    @if ($batchSiblings->isNotEmpty())
        <div class="border-t border-gray-100 pt-4">
            <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">Same batch (other invoices)</h3>
            <ul class="text-sm divide-y divide-gray-100 border border-gray-100 rounded-md">
                @foreach ($batchSiblings as $sibling)
                    <li class="px-3 py-2 flex items-center justify-between">
                        <a href="{{ route('refunds.show', ['companyId' => $sibling->company_id, 'refundNumber' => $sibling->refund_number]) }}" class="text-blue-600 hover:underline font-medium">
                            {{ $sibling->refund_number }}
                        </a>
                        <span class="text-gray-500">{{ $sibling->originalInvoice?->invoice_number ?? '—' }} · {{ ucfirst($sibling->status) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Linked documents ----------------------------------------------------------------------- --}}
    <div class="border-t border-gray-100 pt-4">
        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">Linked ledger documents</h3>

        @php
            $rows = collect();
            foreach (($linkedDocuments['crn'] ?? []) as $t) { $rows->push(['label' => 'Credit note (CRN)', $t]); }
            if (($linkedDocuments['recharge'] ?? null)) { $rows->push(['label' => 'Recharge (penalty + fee)', $linkedDocuments['recharge']]); }
            foreach (($linkedDocuments['supplier_credit'] ?? []) as $t) { $rows->push(['label' => 'Supplier credit item', $t]); }
            foreach (($linkedDocuments['commission_unearn'] ?? []) as $t) { $rows->push(['label' => 'Commission un-earn', $t]); }
            if (($linkedDocuments['clawback'] ?? null)) { $rows->push(['label' => 'Airline clawback', $linkedDocuments['clawback']]); }
            if (($linkedDocuments['disposition'] ?? null)) { $rows->push(['label' => 'Client-net disposition', $linkedDocuments['disposition']]); }
        @endphp

        @if ($rows->isEmpty())
            <p class="text-sm text-gray-400">No ledger documents have posted for this refund yet — post it once approved.</p>
        @else
            <div class="overflow-x-auto border border-gray-100 rounded-md">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500">
                        <tr>
                            <th class="text-left px-3 py-2 font-medium">Document</th>
                            <th class="text-left px-3 py-2 font-medium">Type</th>
                            <th class="text-right px-3 py-2 font-medium">Amount</th>
                            <th class="text-left px-3 py-2 font-medium">Status</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($rows as $row)
                            @php [$label, $t] = $row; @endphp
                            <tr>
                                <td class="px-3 py-2 text-gray-800">{{ $label }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $t->doc_type }}{{ $t->sub_type ? ' / '.$t->sub_type : '' }}</td>
                                <td class="px-3 py-2 text-right font-medium text-gray-800">{{ number_format((float) $t->total_debit, 3) }}</td>
                                <td class="px-3 py-2">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                        {{ $t->posting_status === 'reversed' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">
                                        {{ ucfirst($t->posting_status) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <a href="{{ route('journal-entries.index', $t->id) }}" class="text-blue-600 hover:underline text-xs">View entries</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
