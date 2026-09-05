<x-app-layout>
    <div x-data="bankStatementRecon({
            companyId: {{ (int) $companyId }},
            canManage: {{ $canManage ? 'true' : 'false' }},
            csrfToken: '{{ csrf_token() }}',
            imports: {{ $imports->map(fn ($i) => [
                'id' => $i->id,
                'file_name' => $i->file_name,
                'bank_account_id' => $i->bank_account_id,
                'statement_currency' => $i->statement_currency,
                'statement_reference' => $i->statement_reference,
                'status' => $i->status,
                'counts' => $i->counts,
                'created_at' => optional($i->created_at)->format('Y-m-d H:i'),
            ])->values()->toJson() }},
            bankAccounts: {{ $bankAccounts->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'code' => $a->code, 'currency' => $a->currency])->values()->toJson() }},
            urls: {
                index: '{{ route('accounting.reconciliation.bank-statements.index') }}',
                import: '{{ route('accounting.reconciliation.bank-statements.import') }}',
                matchTemplate: '{{ url('accounting/reconciliation/bank-statements') }}',
            },
        })" x-init="init()" class="my-3">

        <!-- Page heading -->
        <div class="flex justify-between items-center gap-5 mb-6 flex-wrap">
            <div class="flex items-center space-x-4">
                <div class="p-3 DarkBGcolor rounded-full shadow-md flex items-center justify-center">
                    <a href="{{ route('accounting.reconciliation.index') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 42 42">
                            <path fill="#FFC107" fill-rule="evenodd" d="M27.066 1L7 21.068l19.568 19.569l4.934-4.933l-14.637-14.636L32 5.933z" />
                        </svg>
                    </a>
                </div>
                <div>
                    <h2 class="text-2xl md:text-3xl font-bold dark:text-white">Bank Statements</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Import a bank statement, auto-match it against one bank leaf's ledger lines, review the exceptions.</p>
                </div>
            </div>
        </div>

        <!-- Import form -->
        <div class="bg-white dark:bg-gray-800 rounded-lg BoxShadow p-5 mb-6" x-show="canManage">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-4">Import a statement</h3>

            <form @submit.prevent="submitImport()" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Statement file (CSV or Excel)</label>
                    <input type="file" accept=".csv,.xlsx,.xls,.txt" @change="form.file = $event.target.files[0]"
                           class="block w-full text-sm text-gray-700 dark:text-gray-200 file:mr-3 file:py-2 file:px-3 file:rounded-md file:border-0 file:bg-slate-800 file:text-white file:text-sm hover:file:bg-slate-700">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Bank account</label>
                    <select x-model="form.bank_account_id" @change="onBankAccountChange()" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                        <option value="">Select bank account…</option>
                        @foreach ($bankAccounts as $account)
                            <option value="{{ $account->id }}" data-currency="{{ $account->currency ?? 'KWD' }}">{{ $account->code }} — {{ $account->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Statement currency</label>
                    <input type="text" x-model="form.statement_currency" maxlength="3" placeholder="KWD"
                           class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm uppercase">
                    <p class="text-xs text-gray-400 mt-1">Must match the bank account's own currency.</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Statement reference (optional)</label>
                    <input type="text" x-model="form.statement_reference" placeholder="e.g. NBK-2026-08"
                           class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                    <p class="text-xs text-gray-400 mt-1">Re-importing the same file is a no-op. Reusing this reference with a DIFFERENT file is refused as a conflict.</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Statement from</label>
                    <input type="date" x-model="form.statement_from" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Statement to</label>
                    <input type="date" x-model="form.statement_to" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Closing balance (optional)</label>
                    <input type="number" step="0.001" x-model="form.closing_balance" placeholder="from statement footer"
                           class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                </div>

                <div class="md:col-span-4">
                    <button type="button" @click="showColumnMap = !showColumnMap" class="text-xs font-medium text-slate-600 dark:text-slate-300 underline">
                        <span x-text="showColumnMap ? 'Hide column mapping' : 'Override column mapping'"></span>
                    </button>
                    <div x-show="showColumnMap" x-cloak class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-3">
                        <template x-for="key in Object.keys(defaultColumns)" :key="key">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1" x-text="key"></label>
                                <input type="text" x-model="form.column_map[key]" :placeholder="defaultColumns[key]"
                                       class="w-full px-2 py-1.5 rounded-md border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-xs">
                            </div>
                        </template>
                    </div>
                </div>

                <div class="md:col-span-4 flex items-center gap-3">
                    <button type="submit" :disabled="importing || !form.file || !form.bank_account_id || !form.statement_currency"
                            class="px-4 py-2 rounded-lg text-sm font-medium bg-slate-800 text-white hover:bg-slate-700 disabled:opacity-40 disabled:cursor-not-allowed">
                        <span x-show="!importing">Import statement</span>
                        <span x-show="importing">Importing…</span>
                    </button>
                    <span x-show="importError" x-cloak class="text-sm text-red-700" x-text="importError"></span>
                    <span x-show="importSuccess" x-cloak class="text-sm text-emerald-700" x-text="importSuccess"></span>
                </div>
            </form>
        </div>

        <!-- Imports list -->
        <div class="bg-white dark:bg-gray-800 rounded-lg BoxShadow overflow-x-auto mb-6">
            <table class="min-w-full table-auto border-collapse text-sm">
                <thead class="bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-200 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 text-left">File</th>
                        <th class="px-4 py-3 text-left">Reference</th>
                        <th class="px-4 py-3 text-left">Currency</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-right">Matched</th>
                        <th class="px-4 py-3 text-right">Disputed</th>
                        <th class="px-4 py-3 text-right">Unmatched (statement)</th>
                        <th class="px-4 py-3 text-right">Unmatched (ledger)</th>
                        <th class="px-4 py-3 text-left">Imported</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    <template x-if="imports.length === 0">
                        <tr><td colspan="10" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No statements imported yet.</td></tr>
                    </template>
                    <template x-for="imp in imports" :key="imp.id">
                        <tr class="text-gray-800 dark:text-gray-100 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/40" @click="openImport(imp)">
                            <td class="px-4 py-3 font-medium" x-text="imp.file_name"></td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400" x-text="imp.statement_reference || '—'"></td>
                            <td class="px-4 py-3" x-text="imp.statement_currency"></td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold"
                                      :class="imp.status === 'matched' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'">
                                    <span x-text="imp.status"></span>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums" x-text="imp.counts?.matched ?? '—'"></td>
                            <td class="px-4 py-3 text-right tabular-nums" x-text="imp.counts?.disputed ?? '—'"></td>
                            <td class="px-4 py-3 text-right tabular-nums" x-text="imp.counts?.unmatched_statement ?? '—'"></td>
                            <td class="px-4 py-3 text-right tabular-nums" x-text="imp.counts?.unmatched_ledger ?? '—'"></td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400" x-text="imp.created_at"></td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" @click.stop="runMatch(imp)" :disabled="!canManage || matchingId === imp.id"
                                        class="px-3 py-1.5 rounded-md text-xs font-medium bg-slate-800 text-white hover:bg-slate-700 disabled:opacity-40">
                                    <span x-show="matchingId !== imp.id">Run match</span>
                                    <span x-show="matchingId === imp.id">Matching…</span>
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Exceptions + reconciliation report drawer -->
        <div x-show="selected" x-cloak class="bg-white dark:bg-gray-800 rounded-lg BoxShadow p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Reconciliation — <span x-text="selected?.file_name"></span>
                </h3>
                <button type="button" @click="selected = null" class="text-sm text-gray-400 hover:text-gray-600">Close</button>
            </div>

            <!-- Running-balance summary -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                    <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Statement closing balance</p>
                    <p class="text-lg font-semibold tabular-nums dark:text-white" x-text="report ? fmt(report.statement_closing_balance) : '—'"></p>
                </div>
                <div class="p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                    <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Ledger balance (derived)</p>
                    <p class="text-lg font-semibold tabular-nums dark:text-white" x-text="report ? fmt(report.ledger_balance) : '—'"></p>
                </div>
                <div class="p-4 rounded-lg border" :class="reportDifferenceIsZero ? 'border-emerald-200 dark:border-emerald-800' : 'border-amber-200 dark:border-amber-800'">
                    <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Difference</p>
                    <p class="text-lg font-semibold tabular-nums" :class="reportDifferenceIsZero ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300'"
                       x-text="report && report.difference !== null ? fmt(report.difference) : '—'"></p>
                </div>
            </div>

            <div class="flex items-center gap-2 mb-4 text-sm">
                <template x-for="tab in tabs" :key="tab.key">
                    <button type="button" @click="activeTab = tab.key"
                            :class="activeTab === tab.key ? 'bg-slate-800 text-white' : 'bg-white dark:bg-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600'"
                            class="px-3 py-1.5 rounded-md font-medium">
                        <span x-text="tab.label"></span>
                        <span class="opacity-70" x-text="'(' + (exceptions[tab.key]?.length ?? 0) + ')'"></span>
                    </button>
                </template>
            </div>

            <div x-show="loadingExceptions" class="text-sm text-gray-500 dark:text-gray-400 py-6 text-center">Loading…</div>

            <div x-show="!loadingExceptions" class="overflow-x-auto">
                <table class="min-w-full table-auto border-collapse text-sm">
                    <thead class="bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-200 text-xs uppercase tracking-wide">
                        <tr>
                            <th class="px-3 py-2 text-left">Date</th>
                            <th class="px-3 py-2 text-left">Reference / Auth</th>
                            <th class="px-3 py-2 text-right">Amount</th>
                            <th class="px-3 py-2 text-right" x-show="activeTab === 'disputed'">Difference</th>
                            <th class="px-3 py-2 text-left">Note</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <template x-for="row in exceptions[activeTab] ?? []" :key="row.id ?? (row.row_no + '-' + row.id)">
                            <tr class="text-gray-800 dark:text-gray-100">
                                <td class="px-3 py-2" x-text="row.value_date ?? row.posting_date ?? '—'"></td>
                                <td class="px-3 py-2" x-text="row.reference || row.auth_no || ('JE #' + row.id)"></td>
                                <td class="px-3 py-2 text-right tabular-nums" x-text="fmt((row.credit ?? 0) - (row.debit ?? 0) || row.debit - row.credit)"></td>
                                <td class="px-3 py-2 text-right tabular-nums" x-show="activeTab === 'disputed'" x-text="fmt(row.difference)"></td>
                                <td class="px-3 py-2 text-gray-500 dark:text-gray-400" x-text="row.note ?? row.description ?? '—'"></td>
                            </tr>
                        </template>
                        <template x-if="(exceptions[activeTab] ?? []).length === 0">
                            <tr><td colspan="5" class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400">Nothing here.</td></tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function bankStatementRecon(config) {
            return {
                companyId: config.companyId,
                canManage: config.canManage,
                csrfToken: config.csrfToken,
                urls: config.urls,
                imports: config.imports,
                bankAccounts: config.bankAccounts,
                defaultColumns: @json($defaultColumns),
                form: { file: null, bank_account_id: '', statement_currency: 'KWD', statement_reference: '', statement_from: '', statement_to: '', closing_balance: '', column_map: {} },
                showColumnMap: false,
                importing: false,
                importError: null,
                importSuccess: null,
                matchingId: null,
                selected: null,
                report: null,
                loadingExceptions: false,
                activeTab: 'unmatched_statement',
                exceptions: { matched: [], unmatched_statement: [], disputed: [], unmatched_ledger: [] },
                tabs: [
                    { key: 'unmatched_statement', label: 'Unmatched (statement)' },
                    { key: 'disputed', label: 'Disputed' },
                    { key: 'unmatched_ledger', label: 'Unmatched (ledger)' },
                    { key: 'matched', label: 'Matched' },
                ],

                init() {},

                get reportDifferenceIsZero() {
                    return this.report && Math.abs(Number(this.report.difference ?? 0)) < 0.0015;
                },

                fmt(n) {
                    const v = Number(n ?? 0);
                    return v.toLocaleString(undefined, { minimumFractionDigits: 3, maximumFractionDigits: 3 });
                },

                onBankAccountChange() {
                    const opt = event.target.selectedOptions[0];
                    if (opt && opt.dataset.currency) this.form.statement_currency = opt.dataset.currency;
                },

                async submitImport() {
                    this.importing = true;
                    this.importError = null;
                    this.importSuccess = null;

                    const body = new FormData();
                    body.append('file', this.form.file);
                    body.append('bank_account_id', this.form.bank_account_id);
                    body.append('statement_currency', this.form.statement_currency);
                    if (this.form.statement_reference) body.append('statement_reference', this.form.statement_reference);
                    if (this.form.statement_from) body.append('statement_from', this.form.statement_from);
                    if (this.form.statement_to) body.append('statement_to', this.form.statement_to);
                    if (this.form.closing_balance) body.append('closing_balance', this.form.closing_balance);
                    Object.entries(this.form.column_map).forEach(([k, v]) => { if (v) body.append(`column_map[${k}]`, v); });

                    try {
                        const res = await fetch(this.urls.import, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
                            body,
                        });
                        const data = await res.json();
                        if (!res.ok || !data.success) {
                            this.importError = data.message || 'Import failed.';
                            return;
                        }
                        this.importSuccess = 'Statement imported.';
                        this.imports.unshift({
                            id: data.import.id,
                            file_name: data.import.file_name,
                            bank_account_id: data.import.bank_account_id,
                            statement_currency: data.import.statement_currency,
                            statement_reference: data.import.statement_reference,
                            status: data.import.status,
                            counts: data.import.counts,
                            created_at: data.import.created_at,
                        });
                    } catch (e) {
                        this.importError = 'Import failed: ' + e;
                    } finally {
                        this.importing = false;
                    }
                },

                async runMatch(imp) {
                    this.matchingId = imp.id;
                    try {
                        const res = await fetch(`${this.urls.matchTemplate}/${imp.id}/match`, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
                        });
                        const data = await res.json();
                        if (data.success) {
                            imp.status = data.import.status;
                            imp.counts = data.import.counts;
                            if (this.selected && this.selected.id === imp.id) this.openImport(imp);
                        }
                    } finally {
                        this.matchingId = null;
                    }
                },

                async openImport(imp) {
                    this.selected = imp;
                    this.loadingExceptions = true;
                    try {
                        const res = await fetch(`${this.urls.matchTemplate}/${imp.id}/exceptions`, {
                            headers: { 'Accept': 'application/json' },
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.exceptions = {
                                matched: data.matched,
                                unmatched_statement: data.unmatched_statement,
                                disputed: data.disputed,
                                unmatched_ledger: data.unmatched_ledger,
                            };
                            this.report = data.report;
                        }
                    } finally {
                        this.loadingExceptions = false;
                    }
                },
            };
        }
    </script>
</x-app-layout>
