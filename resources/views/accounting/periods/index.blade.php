<x-app-layout>
    <div x-data="periodControl({
            companyId: {{ (int) $companyId }},
            year: {{ (int) $year }},
            isAnnual: {{ $isAnnual ? 'true' : 'false' }},
            canClose: {{ $canClose ? 'true' : 'false' }},
            canReopen: {{ $canReopen ? 'true' : 'false' }},
            csrfToken: '{{ csrf_token() }}',
            urls: {
                checklist: '{{ route('accounting.periods.checklist') }}',
                close: '{{ route('accounting.periods.close') }}',
                reopen: '{{ route('accounting.periods.reopen') }}',
                closeYear: '{{ route('accounting.periods.close-year') }}',
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
                <h2 class="text-2xl md:text-3xl font-bold dark:text-white">Period Control</h2>
            </div>

            <div class="flex items-center gap-3">
                <label class="text-sm text-gray-600 dark:text-gray-300" for="period-year">Fiscal year</label>
                <select id="period-year" x-model.number="year" @change="navigateYear()"
                        class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                    <template x-for="y in yearOptions()" :key="y">
                        <option :value="y" x-text="y"></option>
                    </template>
                </select>

                <button type="button" @click="openYearCloseModal()"
                        class="px-4 py-2 rounded-lg text-sm font-medium bg-slate-800 text-white hover:bg-slate-700 disabled:opacity-40 disabled:cursor-not-allowed"
                        :disabled="!canClose">
                    Close fiscal year
                </button>
            </div>
        </div>

        <!-- Period table -->
        <div class="bg-white dark:bg-gray-800 rounded-lg BoxShadow overflow-x-auto">
            <table class="min-w-full table-auto border-collapse text-sm">
                <thead class="bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-200 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 text-left">Period</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Closed</th>
                        <th class="px-4 py-3 text-left">Reopen history</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($months as $month)
                        @php($period = $periods->get($month))
                        <tr class="text-gray-800 dark:text-gray-100">
                            <td class="px-4 py-3 font-medium">
                                {{ $isAnnual ? $year : \Illuminate\Support\Carbon::create($year, $month, 1)->format('F Y') }}
                            </td>
                            <td class="px-4 py-3">
                                @php($status = $period->status ?? 'open')
                                <span @class([
                                    'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold',
                                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' => $status === 'open',
                                    'bg-amber-50 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' => $status === 'soft_closed',
                                    'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200' => $status === 'locked',
                                ])>
                                    <span class="w-1.5 h-1.5 rounded-full"
                                          @class([
                                              'bg-emerald-500' => $status === 'open',
                                              'bg-amber-500' => $status === 'soft_closed',
                                              'bg-slate-500' => $status === 'locked',
                                          ])></span>
                                    {{ ['open' => 'Open', 'soft_closed' => 'Soft closed', 'locked' => 'Locked'][$status] ?? $status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                                @if ($period?->closed_at)
                                    by #{{ $period->closed_by }} on {{ $period->closed_at->format('Y-m-d H:i') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                                @if ($period?->reopened_at)
                                    by #{{ $period->reopened_by }} on {{ $period->reopened_at->format('Y-m-d H:i') }}
                                    <div class="italic">"{{ $period->reopen_reason }}"</div>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2 flex-wrap">
                                    <button type="button" @click="runChecklist({{ $month }})"
                                            class="px-3 py-1.5 rounded-md text-xs font-medium border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">
                                        Run checklist
                                    </button>
                                    <template x-if="canClose && '{{ $status }}' !== 'locked'">
                                        <button type="button" @click="closePeriod({{ $month }}, 'soft_closed')"
                                                class="px-3 py-1.5 rounded-md text-xs font-medium bg-amber-500 text-white hover:bg-amber-600">
                                            Soft close
                                        </button>
                                    </template>
                                    <template x-if="canClose && '{{ $status }}' !== 'locked'">
                                        <button type="button" @click="closePeriod({{ $month }}, 'locked')"
                                                class="px-3 py-1.5 rounded-md text-xs font-medium bg-slate-800 text-white hover:bg-slate-700">
                                            Lock
                                        </button>
                                    </template>
                                    <template x-if="canReopen && '{{ $status }}' !== 'open'">
                                        <button type="button" @click="openReopenModal({{ $month }})"
                                                class="px-3 py-1.5 rounded-md text-xs font-medium border border-red-300 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">
                                            Reopen
                                        </button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Checklist results panel -->
        <div x-show="checklist.month !== null" x-cloak class="mt-6 bg-white dark:bg-gray-800 rounded-lg BoxShadow p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold dark:text-white">
                    Checklist — <span x-text="checklist.periodLabel"></span>
                </h3>
                <button type="button" @click="checklist.month = null" class="text-sm text-gray-400 hover:text-gray-600">Close</button>
            </div>

            <div x-show="checklist.loading" class="text-sm text-gray-500">Running checklist…</div>

            <template x-if="!checklist.loading && checklist.data">
                <div class="space-y-5">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-semibold"
                              :class="checklist.data.can_close ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'">
                            <span x-text="checklist.data.can_close ? 'Can close' : 'Blocked'"></span>
                        </span>
                        <span class="text-xs text-gray-500" x-text="checklist.data.period_start + ' → ' + checklist.data.period_end"></span>
                    </div>

                    <div x-show="checklist.data.blocking.length > 0">
                        <h4 class="text-sm font-semibold text-red-700 mb-2">Blocking</h4>
                        <ul class="space-y-1">
                            <template x-for="item in checklist.data.blocking" :key="item.code">
                                <li class="text-sm text-red-700 bg-red-50 dark:bg-red-900/20 rounded-md px-3 py-2" x-text="item.message"></li>
                            </template>
                        </ul>
                    </div>

                    <div x-show="checklist.data.warnings.length > 0">
                        <h4 class="text-sm font-semibold text-amber-700 mb-2">Warnings</h4>
                        <ul class="space-y-1">
                            <template x-for="item in checklist.data.warnings" :key="item.code + JSON.stringify(item.meta)">
                                <li class="text-sm text-amber-700 bg-amber-50 dark:bg-amber-900/20 rounded-md px-3 py-2" x-text="item.message"></li>
                            </template>
                        </ul>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Bank / cash / clearing</h4>
                            <div class="text-xs text-gray-500 space-y-1">
                                <template x-for="row in checklist.data.sections.bank_cash" :key="row.account_id">
                                    <div class="flex justify-between border-b border-gray-100 dark:border-gray-700 py-1">
                                        <span x-text="row.name + ' (' + row.code + ')'"></span>
                                        <span :class="row.status === 'ok' ? 'text-emerald-600' : 'text-amber-600'"
                                              x-text="row.status === 'ok' ? 'reconciled' : row.unreconciled_count + ' unmatched'"></span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div>
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Control accounts (AR/AP)</h4>
                            <div class="text-xs text-gray-500 space-y-1">
                                <template x-for="row in checklist.data.sections.control_accounts" :key="row.purpose_code">
                                    <div class="flex justify-between border-b border-gray-100 dark:border-gray-700 py-1">
                                        <span x-text="row.label"></span>
                                        <span :class="row.status === 'ok' ? 'text-emerald-600' : 'text-red-600'"
                                              x-text="row.status === 'ok' ? 'matches' : ('gap ' + row.unattributed_net)"></span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div>
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Clearing / roll-forward</h4>
                            <div class="text-xs text-gray-500 space-y-1">
                                <template x-for="row in checklist.data.sections.clearing_rollforward" :key="row.code">
                                    <div class="flex justify-between border-b border-gray-100 dark:border-gray-700 py-1">
                                        <span x-text="row.label"></span>
                                        <span x-text="row.closing_balance !== undefined ? row.closing_balance : 'n/a'"></span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div>
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Income / expense (review only)</h4>
                            <div class="text-xs text-gray-500 space-y-1">
                                <template x-for="row in checklist.data.sections.income_expense" :key="row.root">
                                    <div class="flex justify-between border-b border-gray-100 dark:border-gray-700 py-1">
                                        <span x-text="row.root"></span>
                                        <span x-text="row.this_period.toFixed(3) + ' (Δ ' + row.variance.toFixed(3) + ')'"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Reason modal (reopen) -->
        <div x-show="modal.open" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 px-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md p-6" @click.outside="modal.open = false">
                <h3 class="text-lg font-semibold dark:text-white mb-2" x-text="modal.title"></h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4" x-text="modal.description"></p>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Reason</label>
                <textarea x-model="modal.reason" rows="3"
                          class="w-full rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm p-2"
                          placeholder="Why is this period being reopened?"></textarea>
                <p x-show="modal.error" x-text="modal.error" class="text-xs text-red-600 mt-2"></p>
                <div class="flex justify-end gap-2 mt-5">
                    <button type="button" @click="modal.open = false" class="px-4 py-2 rounded-md text-sm border border-gray-300 dark:border-gray-600">Cancel</button>
                    <button type="button" @click="submitReopen()" :disabled="modal.saving"
                            class="px-4 py-2 rounded-md text-sm bg-red-600 text-white hover:bg-red-700 disabled:opacity-50">
                        <span x-show="!modal.saving">Reopen period</span>
                        <span x-show="modal.saving">Reopening…</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Year-close confirm modal -->
        <div x-show="yearCloseModal.open" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 px-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md p-6">
                <h3 class="text-lg font-semibold dark:text-white mb-2">Close fiscal year <span x-text="year"></span></h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                    All 12 periods must already be locked. This posts one irreversible closing entry sweeping net profit/loss to Retained Earnings.
                </p>
                <p x-show="yearCloseModal.error" x-text="yearCloseModal.error" class="text-xs text-red-600 mb-2"></p>
                <p x-show="yearCloseModal.success" x-text="yearCloseModal.success" class="text-xs text-emerald-600 mb-2"></p>
                <div class="flex justify-end gap-2 mt-3">
                    <button type="button" @click="yearCloseModal.open = false" class="px-4 py-2 rounded-md text-sm border border-gray-300 dark:border-gray-600">Cancel</button>
                    <button type="button" @click="submitYearClose()" :disabled="yearCloseModal.saving"
                            class="px-4 py-2 rounded-md text-sm bg-slate-800 text-white hover:bg-slate-700 disabled:opacity-50">
                        <span x-show="!yearCloseModal.saving">Confirm close</span>
                        <span x-show="yearCloseModal.saving">Closing…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function periodControl(config) {
            return {
                companyId: config.companyId,
                year: config.year,
                isAnnual: config.isAnnual,
                canClose: config.canClose,
                canReopen: config.canReopen,
                checklist: { month: null, periodLabel: '', loading: false, data: null },
                modal: { open: false, title: '', description: '', reason: '', error: null, saving: false, month: null },
                yearCloseModal: { open: false, error: null, success: null, saving: false },

                init() {},

                yearOptions() {
                    const current = new Date().getFullYear();
                    const options = [];
                    for (let y = current - 5; y <= current + 1; y++) options.push(y);
                    return options;
                },

                navigateYear() {
                    window.location.href = '{{ route('accounting.periods.index') }}?company_id=' + this.companyId + '&year=' + this.year;
                },

                periodLabel(month) {
                    if (this.isAnnual) return String(this.year);
                    const d = new Date(this.year, month - 1, 1);
                    return d.toLocaleString('default', { month: 'long', year: 'numeric' });
                },

                async runChecklist(month) {
                    this.checklist = { month, periodLabel: this.periodLabel(month), loading: true, data: null };
                    try {
                        const res = await this.post(config.urls.checklist, { company_id: this.companyId, year: this.year, month });
                        const json = await res.json();
                        this.checklist.data = json.checklist;
                    } finally {
                        this.checklist.loading = false;
                    }
                },

                async closePeriod(month, status) {
                    const res = await this.post(config.urls.close, { company_id: this.companyId, year: this.year, month, status });
                    const json = await res.json();
                    if (!json.success) {
                        this.checklist = { month, periodLabel: this.periodLabel(month), loading: false, data: json.checklist || null };
                        alert(json.message || 'Could not close period.');
                        return;
                    }
                    window.location.reload();
                },

                openReopenModal(month) {
                    this.modal = {
                        open: true,
                        title: 'Reopen ' + this.periodLabel(month),
                        description: 'A reason is required and is recorded on this period\'s audit trail.',
                        reason: '',
                        error: null,
                        saving: false,
                        month,
                    };
                },

                async submitReopen() {
                    if (!this.modal.reason || this.modal.reason.trim() === '') {
                        this.modal.error = 'A reason is required.';
                        return;
                    }
                    this.modal.saving = true;
                    this.modal.error = null;
                    try {
                        const res = await this.post(config.urls.reopen, {
                            company_id: this.companyId, year: this.year, month: this.modal.month, reason: this.modal.reason,
                        });
                        const json = await res.json();
                        if (!json.success) {
                            this.modal.error = json.message || 'Could not reopen period.';
                            return;
                        }
                        window.location.reload();
                    } finally {
                        this.modal.saving = false;
                    }
                },

                openYearCloseModal() {
                    this.yearCloseModal = { open: true, error: null, success: null, saving: false };
                },

                async submitYearClose() {
                    this.yearCloseModal.saving = true;
                    this.yearCloseModal.error = null;
                    try {
                        const res = await this.post(config.urls.closeYear, { company_id: this.companyId, year: this.year });
                        const json = await res.json();
                        if (!json.success) {
                            this.yearCloseModal.error = (json.message || 'Refused.') + (json.blocking ? ' ' + json.blocking.join(' ') : '');
                            return;
                        }
                        this.yearCloseModal.success = json.already_closed ? 'Already closed.' : ('Closed. Net profit/(loss): ' + json.net_profit);
                    } finally {
                        this.yearCloseModal.saving = false;
                    }
                },

                async post(url, body) {
                    return fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': config.csrfToken,
                        },
                        body: JSON.stringify(body),
                    });
                },
            };
        }
    </script>
</x-app-layout>
