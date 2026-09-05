<x-app-layout>
    <div x-data="supplierStatementRecon({
            companyId: {{ (int) $companyId }},
            canManage: {{ $canManage ? 'true' : 'false' }},
            csrfToken: '{{ csrf_token() }}',
            imports: {{ $imports->map(fn ($i) => [
                'id' => $i->id,
                'file_name' => $i->file_name,
                'supplier_id' => $i->supplier_id,
                'statement_currency' => $i->statement_currency,
                'statement_reference' => $i->statement_reference,
                'status' => $i->status,
                'counts' => $i->counts,
                'created_at' => optional($i->created_at)->format('Y-m-d H:i'),
            ])->values()->toJson() }},
            urls: {
                index: '{{ route('accounting.reconciliation.supplier-statements.index') }}',
                import: '{{ route('accounting.reconciliation.supplier-statements.import') }}',
                matchTemplate: '{{ url('accounting/reconciliation/supplier-statements') }}',
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
                    <h2 class="text-2xl md:text-3xl font-bold dark:text-white">DOTW Supplier Statements</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Import a DOTW statement, match it against our payable ledger, review the exceptions.</p>
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
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Supplier</label>
                    <select x-model="form.supplier_id" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                        <option value="">Select supplier…</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected(strcasecmp($supplier->name, 'DOTW') === 0)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Statement currency</label>
                    <input type="text" x-model="form.statement_currency" maxlength="3" placeholder="KWD"
                           class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm uppercase">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Statement reference / period (optional)</label>
                    <input type="text" x-model="form.statement_reference" placeholder="e.g. DOTW-2026-08"
                           class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                    <p class="text-xs text-gray-400 mt-1">Re-importing the same file (or the same reference) is a no-op, not a duplicate.</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Period from</label>
                    <input type="date" x-model="form.period_from" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Period to</label>
                    <input type="date" x-model="form.period_to" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
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
                    <button type="submit" :disabled="importing || !form.file || !form.supplier_id || !form.statement_currency"
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

        <!-- Exceptions drawer -->
        <div x-show="selected" x-cloak class="bg-white dark:bg-gray-800 rounded-lg BoxShadow p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Exceptions — <span x-text="selected?.file_name"></span>
                </h3>
                <button type="button" @click="selected = null" class="text-sm text-gray-400 hover:text-gray-600">Close</button>
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
                            <th class="px-3 py-2 text-left">Booking ref</th>
                            <th class="px-3 py-2 text-left">Guest</th>
                            <th class="px-3 py-2 text-right">Amount</th>
                            <th class="px-3 py-2 text-right" x-show="activeTab === 'disputed'">Difference</th>
                            <th class="px-3 py-2 text-left">Note</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <template x-for="row in exceptions[activeTab] ?? []" :key="row.id ?? (row.row_no + '-' + row.id)">
                            <tr class="text-gray-800 dark:text-gray-100">
                                <td class="px-3 py-2" x-text="row.booking_ref ?? ('JE #' + row.id)"></td>
                                <td class="px-3 py-2" x-text="row.guest ?? '—'"></td>
                                <td class="px-3 py-2 text-right tabular-nums" x-text="fmt(row.amount ?? ((row.credit ?? 0) - (row.debit ?? 0)))"></td>
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
        function supplierStatementRecon(config) {
            return {
                companyId: config.companyId,
                canManage: config.canManage,
                csrfToken: config.csrfToken,
                urls: config.urls,
                imports: config.imports,
                defaultColumns: @json($defaultColumns),
                form: { file: null, supplier_id: '', statement_currency: 'KWD', statement_reference: '', period_from: '', period_to: '', column_map: {} },
                showColumnMap: false,
                importing: false,
                importError: null,
                importSuccess: null,
                matchingId: null,
                selected: null,
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

                fmt(n) {
                    const v = Number(n ?? 0);
                    return v.toLocaleString(undefined, { minimumFractionDigits: 3, maximumFractionDigits: 3 });
                },

                async submitImport() {
                    this.importing = true;
                    this.importError = null;
                    this.importSuccess = null;

                    const body = new FormData();
                    body.append('file', this.form.file);
                    body.append('supplier_id', this.form.supplier_id);
                    body.append('statement_currency', this.form.statement_currency);
                    if (this.form.statement_reference) body.append('statement_reference', this.form.statement_reference);
                    if (this.form.period_from) body.append('period_from', this.form.period_from);
                    if (this.form.period_to) body.append('period_to', this.form.period_to);
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
                            supplier_id: data.import.supplier_id,
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
                        }
                    } finally {
                        this.loadingExceptions = false;
                    }
                },
            };
        }
    </script>
</x-app-layout>
