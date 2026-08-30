<x-app-layout>
    <div x-data="reconciliationCenter({
            companyId: {{ (int) $companyId }},
            canManage: {{ $canManage ? 'true' : 'false' }},
            csrfToken: '{{ csrf_token() }}',
            urls: {
                grid: '{{ route('accounting.reconciliation.grid') }}',
                rowDetail: '{{ url('accounting/reconciliation/rows') }}',
                approve: '{{ url('accounting/reconciliation/proposals') }}',
                match: '{{ route('accounting.reconciliation.match') }}',
                unmatch: '{{ route('accounting.reconciliation.unmatch') }}',
                fixDraftsCreate: '{{ route('accounting.reconciliation.fix-drafts.create') }}',
                fixDrafts: '{{ url('accounting/reconciliation/fix-drafts') }}',
                run: '{{ route('accounting.reconciliation.run') }}',
                runStatus: '{{ route('accounting.reconciliation.run-status') }}',
            },
        })" x-init="init()" class="my-3">

        <!-- Page heading -->
        <div class="flex justify-between items-center gap-5 mb-6 flex-wrap">
            <div class="flex items-center space-x-4">
                <div class="p-3 DarkBGcolor rounded-full shadow-md flex items-center justify-center">
                    <a href="javascript:history.back()">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 42 42">
                            <path fill="#FFC107" fill-rule="evenodd" d="M27.066 1L7 21.068l19.568 19.569l4.934-4.933l-14.637-14.636L32 5.933z" />
                        </svg>
                    </a>
                </div>
                <div>
                    <h2 class="text-2xl md:text-3xl font-bold dark:text-white">Reconciliation Center</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Every balance-sheet account, book vs. confirmed, one screen.</p>
                </div>
            </div>

            <div class="flex items-center gap-3 flex-wrap">
                <div class="flex items-center rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden text-sm">
                    <button type="button" @click="setMode('day')"
                            :class="mode === 'day' ? 'bg-slate-800 text-white' : 'bg-white dark:bg-gray-700 dark:text-gray-200'"
                            class="px-3 py-2 font-medium">Day</button>
                    <button type="button" @click="setMode('month')"
                            :class="mode === 'month' ? 'bg-slate-800 text-white' : 'bg-white dark:bg-gray-700 dark:text-gray-200'"
                            class="px-3 py-2 font-medium">Month</button>
                </div>
                <input type="date" x-model="date" @change="loadGrid()"
                       class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                <button type="button" @click="runNow()" :disabled="!canManage || runStatus.running"
                        class="px-4 py-2 rounded-lg text-sm font-medium bg-slate-800 text-white hover:bg-slate-700 disabled:opacity-40 disabled:cursor-not-allowed">
                    <span x-show="!runStatus.running">Run now</span>
                    <span x-show="runStatus.running">Queued…</span>
                </button>
            </div>
        </div>

        <!-- Run-status panel -->
        <div class="bg-white dark:bg-gray-800 rounded-lg BoxShadow p-4 mb-6">
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-400">Last nightly run</div>
                    <div class="font-semibold dark:text-white" x-text="runStatus.lastNightlyLabel"></div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-400">Proposals created</div>
                    <div class="font-semibold dark:text-white" x-text="runStatus.proposalsCreated"></div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-400">Auto-matched, pending approval</div>
                    <div class="font-semibold dark:text-white" x-text="runStatus.autoMatchedPending"></div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-400">Exceptions</div>
                    <div class="font-semibold dark:text-white" x-text="runStatus.exceptions"></div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-400">Duration</div>
                    <div class="font-semibold dark:text-white" x-text="runStatus.durationLabel"></div>
                </div>
            </div>
        </div>

        <!-- Loading / error / empty states -->
        <div x-show="grid.loading" class="text-sm text-gray-500 dark:text-gray-400 py-8 text-center">Loading grid…</div>
        <div x-show="grid.error" x-cloak class="text-sm text-red-700 bg-red-50 dark:bg-red-900/20 rounded-md px-4 py-3 mb-4" x-text="grid.error"></div>
        <div x-show="!grid.loading && !grid.error && grid.rows.length === 0" x-cloak class="text-sm text-gray-500 dark:text-gray-400 py-8 text-center">
            No balance-sheet accounts found for this company.
        </div>

        <!-- Grid -->
        <div x-show="!grid.loading && grid.rows.length > 0" x-cloak class="bg-white dark:bg-gray-800 rounded-lg BoxShadow overflow-x-auto">
            <table class="min-w-full table-auto border-collapse text-sm">
                <thead class="bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-200 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 text-left">Account</th>
                        <th class="px-4 py-3 text-right">Opening</th>
                        <th class="px-4 py-3 text-right">Period Dr</th>
                        <th class="px-4 py-3 text-right">Period Cr</th>
                        <th class="px-4 py-3 text-right">Book</th>
                        <th class="px-4 py-3 text-right">Confirmed</th>
                        <th class="px-4 py-3 text-right">Gap</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-right">Proposals</th>
                        <th class="px-4 py-3 text-right">Unmatched</th>
                        <th class="px-4 py-3 text-right">&gt;30d</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    <template x-for="group in groupOrder" :key="group.key">
                        <template x-if="rowsByGroup(group.key).length > 0">
                            <tbody>
                                <tr class="bg-gray-50 dark:bg-gray-900/40">
                                    <td colspan="11" class="px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" x-text="group.label"></td>
                                </tr>
                                <template x-for="row in rowsByGroup(group.key)" :key="row.key">
                                    <tr class="text-gray-800 dark:text-gray-100 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/40"
                                        @click="openRow(row)">
                                        <td class="px-4 py-3 font-medium">
                                            <span x-text="row.label"></span>
                                            <span class="text-xs text-gray-400" x-show="row.code"> (<span x-text="row.code"></span>)</span>
                                        </td>
                                        <td class="px-4 py-3 text-right tabular-nums" x-text="fmt(row.opening_balance)"></td>
                                        <td class="px-4 py-3 text-right tabular-nums" x-text="fmt(row.period_debit)"></td>
                                        <td class="px-4 py-3 text-right tabular-nums" x-text="fmt(row.period_credit)"></td>
                                        <td class="px-4 py-3 text-right tabular-nums font-medium" x-text="fmt(row.book_balance)"></td>
                                        <td class="px-4 py-3 text-right tabular-nums" x-text="row.confirmed_balance === null ? '—' : fmt(row.confirmed_balance)"></td>
                                        <td class="px-4 py-3 text-right tabular-nums">
                                            <span :class="gapClass(row)" class="inline-flex items-center gap-1">
                                                <span x-text="gapIcon(row)"></span>
                                                <span x-text="row.gap === null ? '—' : fmt(row.gap)"></span>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold"
                                                  :class="statusChipClass(row.status)">
                                                <span class="w-1.5 h-1.5 rounded-full" :class="statusDotClass(row.status)"></span>
                                                <span x-text="statusLabel(row.status)"></span>
                                            </span>
                                            <span x-show="row.blocks_close" class="ml-1 text-xs font-semibold text-red-600" title="Blocks month-end close">BLOCKS CLOSE</span>
                                        </td>
                                        <td class="px-4 py-3 text-right tabular-nums" x-text="row.counts.proposals"></td>
                                        <td class="px-4 py-3 text-right tabular-nums" x-text="row.counts.unmatched"></td>
                                        <td class="px-4 py-3 text-right tabular-nums" x-text="row.counts.ageing_over"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </template>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Drill-down drawer -->
        <div x-show="drawer.open" x-cloak class="fixed inset-0 z-40 flex justify-end bg-black/40" @click.self="drawer.open = false">
            <div class="bg-white dark:bg-gray-800 w-full max-w-2xl h-full overflow-y-auto shadow-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold dark:text-white" x-text="drawer.row?.label"></h3>
                    <button type="button" @click="drawer.open = false" class="text-sm text-gray-400 hover:text-gray-600">Close</button>
                </div>

                <div x-show="drawer.loading" class="text-sm text-gray-500 py-6 text-center">Loading…</div>
                <div x-show="drawer.error" x-cloak class="text-sm text-red-700 bg-red-50 dark:bg-red-900/20 rounded-md px-4 py-3 mb-4" x-text="drawer.error"></div>

                <template x-if="!drawer.loading && drawer.data">
                    <div class="space-y-6">
                        <!-- Gap explanation -->
                        <div x-show="drawer.data.gap_explanation" class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <h4 class="text-sm font-semibold dark:text-white mb-2">Gap explanation</h4>
                            <div class="text-xs text-gray-500 dark:text-gray-400 space-y-1 mb-3">
                                <div class="flex justify-between"><span>Book</span><span x-text="fmt(drawer.data.gap_explanation?.book)"></span></div>
                                <template x-for="c in (drawer.data.gap_explanation?.components || [])" :key="c.label">
                                    <div class="flex justify-between pl-3">
                                        <span>− <span x-text="c.label"></span></span>
                                        <span x-text="fmt(c.amount)"></span>
                                    </div>
                                </template>
                                <div class="flex justify-between font-semibold text-gray-700 dark:text-gray-200 border-t border-gray-100 dark:border-gray-700 pt-1">
                                    <span>= Confirmed</span><span x-text="fmt(drawer.data.gap_explanation?.confirmed)"></span>
                                </div>
                            </div>
                            <div x-show="drawer.data.gap_explanation?.exception" x-cloak
                                 class="rounded-md bg-amber-50 dark:bg-amber-900/20 text-amber-800 dark:text-amber-200 px-3 py-2 text-sm">
                                <div class="font-semibold">Exception: <span x-text="fmt(drawer.data.gap_explanation?.residual)"></span> unexplained</div>
                                <div class="mt-1" x-text="drawer.data.gap_explanation?.advice?.cause"></div>
                                <button type="button" x-show="drawer.data.gap_explanation?.advice?.fix_now_kind" x-cloak
                                        @click="openFixNow(drawer.data.gap_explanation.advice.fix_now_kind, drawer.data.gap_explanation.residual)"
                                        class="mt-2 px-3 py-1.5 rounded-md text-xs font-medium bg-slate-800 text-white hover:bg-slate-700">
                                    <span x-text="drawer.data.gap_explanation?.advice?.label || 'Draft a fix'"></span>
                                </button>
                            </div>
                        </div>

                        <!-- Tabs -->
                        <div class="flex gap-1 border-b border-gray-200 dark:border-gray-700 text-sm">
                            <template x-for="tab in ['proposals', 'unmatched', 'fix_now', 'history']" :key="tab">
                                <button type="button" @click="drawer.tab = tab"
                                        :class="drawer.tab === tab ? 'border-b-2 border-slate-800 text-slate-800 dark:text-white dark:border-white font-semibold' : 'text-gray-400'"
                                        class="px-3 py-2 -mb-px" x-text="tabLabel(tab)"></button>
                            </template>
                        </div>

                        <!-- Proposals tab -->
                        <div x-show="drawer.tab === 'proposals'">
                            <div x-show="drawer.data.proposals.length === 0" class="text-sm text-gray-400 py-4 text-center">No pending proposals.</div>
                            <div class="space-y-2">
                                <template x-for="p in drawer.data.proposals" :key="p.id">
                                    <div class="rounded-md border border-gray-200 dark:border-gray-700 p-3 text-sm">
                                        <div class="flex justify-between">
                                            <span class="font-medium" x-text="p.kind"></span>
                                            <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700" x-text="p.confidence"></span>
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1">Amount: <span x-text="fmt(p.amount)"></span> · Status: <span x-text="p.status"></span></div>
                                        <div class="flex gap-2 mt-2" x-show="p.status === 'pending' && canManage">
                                            <button type="button" @click="approveProposal(p)" class="px-3 py-1 rounded-md text-xs font-medium bg-emerald-600 text-white hover:bg-emerald-700">Approve</button>
                                            <button type="button" @click="openRejectModal(p)" class="px-3 py-1 rounded-md text-xs font-medium border border-red-300 text-red-600 hover:bg-red-50">Reject</button>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <template x-if="(drawer.data.recently_matched || []).length > 0">
                                <div class="mt-5">
                                    <h5 class="text-xs font-semibold uppercase text-gray-400 mb-2">Recently matched</h5>
                                    <div class="space-y-2">
                                        <template x-for="p in drawer.data.recently_matched" :key="'matched-'+p.id">
                                            <div class="rounded-md border border-gray-200 dark:border-gray-700 p-3 text-sm">
                                                <div class="flex justify-between">
                                                    <span class="font-medium" x-text="p.kind"></span>
                                                    <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">approved</span>
                                                </div>
                                                <div class="text-xs text-gray-500 mt-1">Amount: <span x-text="fmt(p.amount)"></span></div>
                                                <div class="mt-2" x-show="canManage">
                                                    <button type="button" @click="openUnmatchModal({ id: p.book_journal_entry_id })"
                                                            class="px-3 py-1 rounded-md text-xs font-medium border border-amber-300 text-amber-700 hover:bg-amber-50">Unmatch</button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Unmatched tab -->
                        <div x-show="drawer.tab === 'unmatched'">
                            <div class="grid grid-cols-4 gap-2 text-xs mb-3">
                                <template x-for="(amount, bucket) in (drawer.data.unmatched?.buckets || {})" :key="bucket">
                                    <div class="rounded-md bg-gray-50 dark:bg-gray-900/40 p-2 text-center">
                                        <div class="text-gray-400" x-text="bucket.replace('_', '-') + 'd'"></div>
                                        <div class="font-semibold" x-text="fmt(amount)"></div>
                                    </div>
                                </template>
                            </div>
                            <div x-show="(drawer.data.unmatched?.items || []).length === 0" class="text-sm text-gray-400 py-4 text-center">Nothing unmatched.</div>
                            <div class="space-y-1">
                                <template x-for="item in (drawer.data.unmatched?.items || [])" :key="item.id">
                                    <div class="flex justify-between text-sm border-b border-gray-100 dark:border-gray-700 py-2">
                                        <div>
                                            <div x-text="item.date + ' · ' + item.description"></div>
                                            <div class="text-xs text-gray-400" x-text="item.age_days + ' day(s) old'"></div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="tabular-nums" x-text="fmt(item.debit - item.credit)"></span>
                                            <a x-show="item.document_url" :href="item.document_url" target="_blank" class="text-xs text-slate-600 underline">doc</a>
                                            <button type="button" x-show="canManage" @click="openMatchModal(item)" class="text-xs px-2 py-1 rounded-md border border-gray-300">Match</button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Fix-now tab -->
                        <div x-show="drawer.tab === 'fix_now'">
                            <div class="grid grid-cols-1 gap-2 mb-4">
                                <template x-for="kind in fixNowKinds" :key="kind.value">
                                    <button type="button" @click="openFixNow(kind.value, 0)" :disabled="!canManage"
                                            class="text-left rounded-md border border-gray-200 dark:border-gray-700 p-3 text-sm hover:bg-gray-50 dark:hover:bg-gray-700/40 disabled:opacity-40">
                                        <div class="font-medium" x-text="kind.label"></div>
                                        <div class="text-xs text-gray-400" x-text="kind.hint"></div>
                                    </button>
                                </template>
                            </div>
                            <div x-show="(drawer.data.fix_drafts || []).length > 0">
                                <h5 class="text-xs font-semibold uppercase text-gray-400 mb-2">Drafts for this row</h5>
                                <template x-for="d in (drawer.data.fix_drafts || [])" :key="d.id">
                                    <div class="flex justify-between text-sm border-b border-gray-100 dark:border-gray-700 py-2">
                                        <span x-text="d.kind + ' · ' + fmt(d.amount)"></span>
                                        <span class="text-xs" x-text="d.status"></span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- History tab -->
                        <div x-show="drawer.tab === 'history'">
                            <div x-show="(drawer.data.history || []).length === 0" class="text-sm text-gray-400 py-4 text-center">No history yet.</div>
                            <div class="space-y-2">
                                <template x-for="h in (drawer.data.history || [])" :key="h.id">
                                    <div class="text-sm border-b border-gray-100 dark:border-gray-700 py-2">
                                        <div class="flex justify-between">
                                            <span class="font-medium" x-text="h.action"></span>
                                            <span class="text-xs text-gray-400" x-text="h.created_at"></span>
                                        </div>
                                        <div class="text-xs text-gray-500" x-show="h.reason">Reason: <span x-text="h.reason"></span></div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Reason modal (reject / match / unmatch) -->
        <div x-show="reasonModal.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md p-6" @click.outside="reasonModal.open = false">
                <h3 class="text-lg font-semibold dark:text-white mb-2" x-text="reasonModal.title"></h3>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Reason</label>
                <textarea x-model="reasonModal.reason" rows="3"
                          class="w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm p-2"></textarea>
                <p x-show="reasonModal.error" x-text="reasonModal.error" class="text-xs text-red-600 mt-2"></p>
                <div class="flex justify-end gap-2 mt-5">
                    <button type="button" @click="reasonModal.open = false" class="px-4 py-2 rounded-md text-sm border border-gray-300 dark:border-gray-600">Cancel</button>
                    <button type="button" @click="submitReasonModal()" :disabled="reasonModal.saving"
                            class="px-4 py-2 rounded-md text-sm bg-slate-800 text-white hover:bg-slate-700 disabled:opacity-50">
                        <span x-show="!reasonModal.saving">Confirm</span>
                        <span x-show="reasonModal.saving">Saving…</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Fix-now draft modal -->
        <div x-show="fixNowModal.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md p-6" @click.outside="fixNowModal.open = false">
                <h3 class="text-lg font-semibold dark:text-white mb-2">Draft a correcting document</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">This creates a DRAFT only — it never posts money. Post it from the normal approval path.</p>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Amount</label>
                <input type="number" step="0.001" x-model.number="fixNowModal.amount" class="w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm p-2 mb-3">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Narration</label>
                <textarea x-model="fixNowModal.narration" rows="2" class="w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm p-2"></textarea>
                <p x-show="fixNowModal.error" x-text="fixNowModal.error" class="text-xs text-red-600 mt-2"></p>
                <div class="flex justify-end gap-2 mt-5">
                    <button type="button" @click="fixNowModal.open = false" class="px-4 py-2 rounded-md text-sm border border-gray-300 dark:border-gray-600">Cancel</button>
                    <button type="button" @click="submitFixNow()" :disabled="fixNowModal.saving"
                            class="px-4 py-2 rounded-md text-sm bg-slate-800 text-white hover:bg-slate-700 disabled:opacity-50">
                        <span x-show="!fixNowModal.saving">Create draft</span>
                        <span x-show="fixNowModal.saving">Saving…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function reconciliationCenter(config) {
            return {
                companyId: config.companyId,
                canManage: config.canManage,
                mode: 'day',
                date: new Date().toISOString().slice(0, 10),
                grid: { loading: false, error: null, rows: [] },
                runStatus: { running: false, lastNightlyLabel: '—', proposalsCreated: '—', autoMatchedPending: '—', exceptions: '—', durationLabel: '—' },
                drawer: { open: false, loading: false, error: null, row: null, data: null, tab: 'proposals' },
                reasonModal: { open: false, title: '', reason: '', error: null, saving: false, action: null, payload: null },
                fixNowModal: { open: false, kind: null, amount: 0, narration: '', error: null, saving: false },

                groupOrder: [
                    { key: 'bank_cash', label: 'Bank, cash & gateway/cheque clearing' },
                    { key: 'control', label: 'Control accounts (AR / AP / agent)' },
                    { key: 'clearing', label: 'Advances, deferred & memo/clearing roll-forward' },
                    { key: 'review_only', label: 'Income & expense (review only)' },
                ],

                fixNowKinds: [
                    { value: 'bank_charge_pv', label: 'Bank-charge PV', hint: 'Draft a payment voucher for an unrecorded bank charge.' },
                    { value: 'gateway_timing_jv', label: 'Gateway-timing JV', hint: 'Draft a journal absorbing a settlement-timing difference to 5147.' },
                    { value: 'unapply_reapply_receipt', label: 'Un-apply / re-apply receipt', hint: 'Flag a possible duplicate receipt for the Receipt Voucher screen.' },
                    { value: 'writeoff_proposal', label: 'Write-off proposal', hint: 'Draft a write-off of a stale open item to 5218.' },
                ],

                init() {
                    this.loadGrid();
                    this.loadRunStatus();
                },

                setMode(mode) {
                    this.mode = mode;
                    this.loadGrid();
                },

                rowsByGroup(key) {
                    return this.grid.rows.filter(r => r.group === key);
                },

                fmt(n) {
                    if (n === null || n === undefined) return '—';
                    return Number(n).toLocaleString(undefined, { minimumFractionDigits: 3, maximumFractionDigits: 3 });
                },

                gapClass(row) {
                    if (row.gap === null) return 'text-gray-400';
                    const zero = Math.abs(row.gap) <= 0.0005;
                    if (zero) return 'text-emerald-600';
                    return row.blocks_close ? 'text-red-600 font-semibold' : 'text-amber-600';
                },

                gapIcon(row) {
                    if (row.gap === null) return '';
                    return Math.abs(row.gap) <= 0.0005 ? '✓' : (row.blocks_close ? '✕' : '!');
                },

                statusLabel(status) {
                    return { reconciled: 'Reconciled', proposals_pending: 'Proposals pending', exceptions: 'Exceptions', review_only: 'Review only' }[status] || status;
                },

                statusChipClass(status) {
                    return {
                        reconciled: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
                        proposals_pending: 'bg-amber-50 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
                        exceptions: 'bg-red-50 text-red-700 dark:bg-red-900/40 dark:text-red-300',
                        review_only: 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
                    }[status] || 'bg-gray-100 text-gray-700';
                },

                statusDotClass(status) {
                    return {
                        reconciled: 'bg-emerald-500', proposals_pending: 'bg-amber-500', exceptions: 'bg-red-500', review_only: 'bg-slate-500',
                    }[status] || 'bg-gray-400';
                },

                tabLabel(tab) {
                    return { proposals: 'Proposals', unmatched: 'Unmatched', fix_now: 'Fix-now', history: 'History' }[tab];
                },

                async loadGrid() {
                    this.grid.loading = true;
                    this.grid.error = null;
                    try {
                        const res = await this.get(config.urls.grid, { company_id: this.companyId, date: this.date, mode: this.mode });
                        const json = await res.json();
                        if (!json.success) throw new Error(json.message || 'Could not load the grid.');
                        this.grid.rows = json.grid.rows;
                    } catch (e) {
                        this.grid.error = e.message;
                    } finally {
                        this.grid.loading = false;
                    }
                },

                async loadRunStatus() {
                    try {
                        const res = await this.get(config.urls.runStatus, { company_id: this.companyId });
                        const json = await res.json();
                        const last = json.run_status?.last_nightly_run;
                        this.runStatus.lastNightlyLabel = last?.finished_at ? new Date(last.finished_at).toLocaleString() : 'Never run yet';
                        this.runStatus.proposalsCreated = last?.proposals_created ?? 0;
                        this.runStatus.autoMatchedPending = last?.auto_matched_pending ?? 0;
                        this.runStatus.exceptions = last?.exceptions_count ?? 0;
                        this.runStatus.durationLabel = last?.duration_ms ? (last.duration_ms / 1000).toFixed(1) + 's' : '—';
                    } catch (e) { /* run-status is best-effort, never blocks the grid */ }
                },

                async runNow() {
                    if (!this.canManage) return;
                    this.runStatus.running = true;
                    try {
                        await this.post(config.urls.run, { company_id: this.companyId });
                        setTimeout(() => { this.loadRunStatus(); this.loadGrid(); this.runStatus.running = false; }, 3000);
                    } catch (e) {
                        this.runStatus.running = false;
                    }
                },

                async openRow(row) {
                    this.drawer = { open: true, loading: true, error: null, row, data: null, tab: 'proposals' };
                    try {
                        const res = await this.get(config.urls.rowDetail + '/' + encodeURIComponent(row.key), { company_id: this.companyId, date: this.date, mode: this.mode });
                        const json = await res.json();
                        if (!json.success) throw new Error(json.message || 'Could not load this row.');
                        this.drawer.data = json;
                    } catch (e) {
                        this.drawer.error = e.message;
                    } finally {
                        this.drawer.loading = false;
                    }
                },

                async approveProposal(p) {
                    const res = await this.post(config.urls.approve + '/' + p.id + '/approve', {});
                    const json = await res.json();
                    if (json.success) this.openRow(this.drawer.row);
                },

                openRejectModal(p) {
                    this.reasonModal = { open: true, title: 'Reject proposal', reason: '', error: null, saving: false, action: 'reject', payload: { id: p.id } };
                },

                openMatchModal(item) {
                    this.reasonModal = { open: true, title: 'Manual match', reason: '', error: null, saving: false, action: 'match', payload: { account_id: this.drawer.row.account_ids[0], journal_entry_id: item.id } };
                },

                openUnmatchModal(item) {
                    this.reasonModal = { open: true, title: 'Unmatch', reason: '', error: null, saving: false, action: 'unmatch', payload: { journal_entry_id: item.id } };
                },

                async submitReasonModal() {
                    if (!this.reasonModal.reason || this.reasonModal.reason.trim() === '') {
                        this.reasonModal.error = 'A reason is required.';
                        return;
                    }
                    this.reasonModal.saving = true;
                    this.reasonModal.error = null;
                    try {
                        let res;
                        if (this.reasonModal.action === 'reject') {
                            res = await this.post(config.urls.approve + '/' + this.reasonModal.payload.id + '/reject', { reason: this.reasonModal.reason });
                        } else if (this.reasonModal.action === 'match') {
                            res = await this.post(config.urls.match, { ...this.reasonModal.payload, reason: this.reasonModal.reason });
                        } else {
                            res = await this.post(config.urls.unmatch, { ...this.reasonModal.payload, reason: this.reasonModal.reason });
                        }
                        const json = await res.json();
                        if (!json.success) {
                            this.reasonModal.error = json.message || 'Could not complete this action.';
                            return;
                        }
                        this.reasonModal.open = false;
                        if (this.drawer.row) this.openRow(this.drawer.row);
                        this.loadGrid();
                    } finally {
                        this.reasonModal.saving = false;
                    }
                },

                openFixNow(kind, suggestedAmount) {
                    this.fixNowModal = { open: true, kind, amount: Math.abs(suggestedAmount || 0), narration: '', error: null, saving: false };
                },

                async submitFixNow() {
                    if (!this.fixNowModal.narration || this.fixNowModal.narration.trim() === '') {
                        this.fixNowModal.error = 'A narration is required.';
                        return;
                    }
                    this.fixNowModal.saving = true;
                    this.fixNowModal.error = null;
                    try {
                        const res = await this.post(config.urls.fixDraftsCreate, {
                            account_id: this.drawer.row.account_ids[0],
                            kind: this.fixNowModal.kind,
                            amount: this.fixNowModal.amount,
                            narration: this.fixNowModal.narration,
                        });
                        const json = await res.json();
                        if (!json.success) {
                            this.fixNowModal.error = json.message || 'Could not create the draft.';
                            return;
                        }
                        this.fixNowModal.open = false;
                        if (this.drawer.row) this.openRow(this.drawer.row);
                    } finally {
                        this.fixNowModal.saving = false;
                    }
                },

                async get(url, params) {
                    const qs = new URLSearchParams(params).toString();
                    return fetch(url + '?' + qs, { headers: { 'Accept': 'application/json' } });
                },

                async post(url, body) {
                    return fetch(url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': config.csrfToken },
                        body: JSON.stringify(body),
                    });
                },
            };
        }
    </script>
</x-app-layout>
