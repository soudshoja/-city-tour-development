{{--
    W6.U "Bulk void" (w6-brief.md "W6.U -- UI"):
    "multi-select checkboxes + a bulk-void action, mode selector atomic|per_task_report (default
    from company option bulk_void_mode), submits to the POST /tasks/bulk-void route; on
    per_task_report mode, render a per-task result table (task id, success/fail, reason) from
    the response." Self-contained Alpine component -- reads the selected task ids from the
    'open-bulk-void' event {@see tasks/index.blade.php's own Bulk void button} so it does not need
    to touch the page's own large, pre-existing x-data block.
--}}
<div x-data="{
        open: false,
        taskIds: [],
        mode: 'atomic',
        busy: false,
        error: null,
        results: null,
        voidedCount: 0,
        failedCount: 0,

        async init() {
            // Company default, per w6-brief.md ('default from company option bulk_void_mode') --
            // read through the same JSON endpoint the Accounting settings tab itself uses, so
            // this never drifts from what that tab actually saved.
            try {
                const res = await fetch('{{ route('settings.accounting-settings') }}', { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.success && data.settings?.bulk_void_mode) {
                    this.mode = data.settings.bulk_void_mode;
                }
            } catch (e) { /* keep the 'atomic' fallback */ }
        },

        openModal(ids) {
            this.taskIds = ids;
            this.error = null;
            this.results = null;
            this.open = true;
        },

        async submit() {
            if (this.taskIds.length === 0) {
                this.error = 'Select at least one task first.';
                return;
            }
            this.busy = true;
            this.error = null;
            this.results = null;
            try {
                const res = await fetch('{{ route('tasks.bulk-void') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ task_ids: this.taskIds, bulk_void_mode: this.mode })
                });
                const data = await res.json();

                if (!data.success) {
                    this.error = data.message || 'Bulk void failed.';
                    this.results = data.results ?? null;
                    this.voidedCount = data.voided_count ?? 0;
                    this.failedCount = data.failed_count ?? this.taskIds.length;
                    return;
                }

                this.voidedCount = data.voided_count;
                this.failedCount = data.failed_count;
                this.results = data.results;

                if (this.mode === 'atomic' && data.failed_count === 0) {
                    setTimeout(() => location.reload(), 1200);
                }
            } catch (e) {
                this.error = 'Network error while submitting bulk void.';
            } finally {
                this.busy = false;
            }
        }
    }"
    @open-bulk-void.window="openModal($event.detail.ids); init()">

    <div x-show="open" x-cloak
        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        @click.self="open = false" @keydown.escape.window="open = false"
        class="fixed inset-0 z-[10003] flex items-center justify-center bg-gray-900/60 backdrop-blur-sm">

        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl max-w-lg w-full mx-4 border border-gray-200 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Bulk void tasks</h3>
                <button @click="open = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="px-6 py-5 space-y-4">
                <p class="text-sm text-gray-600 dark:text-gray-400"><span x-text="taskIds.length"></span> task(s) selected.</p>

                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300 block mb-1">Mode</label>
                    <select x-model="mode" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-800 rounded-lg px-3 py-2 text-sm">
                        <option value="atomic">Atomic (any failure rolls back the whole batch)</option>
                        <option value="per_task_report">Per-task report (partial success allowed)</option>
                    </select>
                </div>

                <div x-show="error" x-cloak class="text-sm font-medium text-red-700 bg-red-50 dark:bg-red-900/30 dark:text-red-300 rounded-lg px-3 py-2" x-text="error"></div>

                <div x-show="results" x-cloak>
                    <div class="flex gap-4 text-sm font-semibold mb-2">
                        <span class="text-emerald-700 dark:text-emerald-400">Voided: <span x-text="voidedCount"></span></span>
                        <span class="text-red-700 dark:text-red-400">Failed: <span x-text="failedCount"></span></span>
                    </div>
                    <div class="max-h-56 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="text-left px-3 py-2 font-semibold text-gray-600 dark:text-gray-300">Task</th>
                                    <th class="text-left px-3 py-2 font-semibold text-gray-600 dark:text-gray-300">Result</th>
                                    <th class="text-left px-3 py-2 font-semibold text-gray-600 dark:text-gray-300">Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="row in results" :key="row.task_id">
                                    <tr class="border-t border-gray-100 dark:border-gray-700">
                                        <td class="px-3 py-2" x-text="'#' + row.task_id"></td>
                                        <td class="px-3 py-2">
                                            <span :class="row.success ? 'text-emerald-700 bg-emerald-100 dark:bg-emerald-900/40 dark:text-emerald-300' : 'text-red-700 bg-red-100 dark:bg-red-900/40 dark:text-red-300'" class="px-2 py-0.5 rounded-full text-xs font-semibold" x-text="row.success ? 'Voided' : 'Failed'"></span>
                                        </td>
                                        <td class="px-3 py-2 text-gray-500 dark:text-gray-400" x-text="row.error ?? '—'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-700 flex justify-end gap-2">
                <button type="button" @click="open = false" class="px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700">Close</button>
                <button type="button" :disabled="busy" @click="submit()" class="px-4 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 disabled:opacity-50 rounded-lg">
                    <span x-show="!busy">Void selected tasks</span>
                    <span x-show="busy">Voiding...</span>
                </button>
            </div>
        </div>
    </div>
</div>
