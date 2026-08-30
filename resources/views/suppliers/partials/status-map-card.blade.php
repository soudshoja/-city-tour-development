{{--
    W6.U "Supplier status map" (owner addition, 2026-08-28). Table of channel/raw_status/
    canonical_status/deadline_source/active for the current supplier+company, add/edit/deactivate
    row actions, a "test a raw status" preview, and an "Unmapped statuses seen" list with a
    one-click create-mapping action. Gated by SupplierPolicy::update -- $canManageSupplier is
    computed once in SupplierController::show() via Gate::allows('update', Supplier::class).
--}}
<div x-data="{ editingId: null, adding: false }" class="bg-white rounded-lg shadow-md p-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-signs-post text-blue-500"></i>
            Supplier status map
        </h2>
        <a href="{{ route('suppliers.status-map.defaults') }}" class="text-xs text-blue-600 hover:text-blue-800 font-medium">Company defaults &rarr;</a>
    </div>

    @if ($statusMapRows->isEmpty())
        <div class="text-sm text-gray-500 italic mb-3">No supplier-specific mappings yet. This supplier still resolves through the company/global defaults.</div>
    @else
        <div class="overflow-x-auto border border-gray-200 rounded-lg mb-3">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Channel</th>
                        <th class="text-left px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Raw status</th>
                        <th class="text-left px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Canonical</th>
                        <th class="text-left px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Deadline source</th>
                        <th class="text-center px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Active</th>
                        @if ($canManageSupplier)
                            <th class="text-right px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($statusMapRows as $row)
                        <tr class="hover:bg-blue-50/50">
                            <td class="px-3 py-2 font-mono">{{ $row->channel }}</td>
                            <td class="px-3 py-2 font-mono">{{ $row->raw_status }}</td>
                            <td class="px-3 py-2"><span class="px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 text-xs font-semibold">{{ $row->canonical_status }}</span></td>
                            <td class="px-3 py-2 text-gray-500">{{ $row->deadline_source ?? '—' }}</td>
                            <td class="px-3 py-2 text-center">
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $row->active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-500' }}">{{ $row->active ? 'Active' : 'Inactive' }}</span>
                            </td>
                            @if ($canManageSupplier)
                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                    <button type="button" @click="editingId = editingId === {{ $row->id }} ? null : {{ $row->id }}" class="text-blue-600 hover:text-blue-800 text-xs font-semibold mr-2">Edit</button>
                                    <form action="{{ route('suppliers.status-map.deactivate', $row->id) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold {{ $row->active ? 'text-red-600 hover:text-red-800' : 'text-emerald-600 hover:text-emerald-800' }}">{{ $row->active ? 'Deactivate' : 'Reactivate' }}</button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                        @if ($canManageSupplier)
                            <tr x-show="editingId === {{ $row->id }}" x-cloak>
                                <td colspan="6" class="px-3 py-3 bg-gray-50">
                                    <form action="{{ route('suppliers.status-map.update', $row->id) }}" method="POST" class="grid grid-cols-2 md:grid-cols-5 gap-2 items-end">
                                        @csrf @method('PUT')
                                        <div>
                                            <label class="text-xs text-gray-500 block">Channel</label>
                                            <select name="channel" class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
                                                @foreach (['air','magic','webhook','ai_pdf','manual'] as $c)
                                                    <option value="{{ $c }}" @selected($row->channel === $c)>{{ $c }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-500 block">Raw status</label>
                                            <input type="text" name="raw_status" value="{{ $row->raw_status }}" class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-500 block">Canonical status</label>
                                            <select name="canonical_status" class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
                                                @foreach (['on_hold','confirmed','issued','reissued','void','refund','emd','cancelled','needs_review'] as $c)
                                                    <option value="{{ $c }}" @selected($row->canonical_status === $c)>{{ $c }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-500 block">Deadline source</label>
                                            <input type="text" name="deadline_source" value="{{ $row->deadline_source }}" class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
                                        </div>
                                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-3 py-1.5 rounded">Save</button>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($canManageSupplier)
        <div class="mb-4">
            <button type="button" @click="adding = !adding" class="bg-blue-100 text-blue-700 hover:bg-blue-200 font-medium text-xs px-3 py-1.5 rounded-lg transition">+ Add mapping</button>
            <form x-show="adding" x-cloak action="{{ route('suppliers.status-map.store', $supplier->id) }}" method="POST" class="grid grid-cols-2 md:grid-cols-5 gap-2 items-end mt-2 bg-gray-50 rounded-lg p-3">
                @csrf
                <div>
                    <label class="text-xs text-gray-500 block">Channel</label>
                    <select name="channel" class="w-full border border-gray-300 rounded px-2 py-1 text-sm" required>
                        @foreach (['air','magic','webhook','ai_pdf','manual'] as $c)
                            <option value="{{ $c }}">{{ $c }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-gray-500 block">Raw status</label>
                    <input type="text" name="raw_status" required class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
                </div>
                <div>
                    <label class="text-xs text-gray-500 block">Canonical status</label>
                    <select name="canonical_status" class="w-full border border-gray-300 rounded px-2 py-1 text-sm" required>
                        @foreach (['on_hold','confirmed','issued','reissued','void','refund','emd','cancelled','needs_review'] as $c)
                            <option value="{{ $c }}">{{ $c }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-gray-500 block">Deadline source</label>
                    <input type="text" name="deadline_source" class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
                </div>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-3 py-1.5 rounded">Add</button>
            </form>
        </div>
    @else
        <div class="text-sm text-amber-700 flex items-center gap-2 bg-amber-50 border border-amber-200 rounded-md px-3 py-2 mb-4">
            <i class="fa-solid fa-circle-info"></i>
            <span>If you need to change a status mapping, please contact your system administrator.</span>
        </div>
    @endif

    {{-- "Test a raw status" preview -- never writes a task. --}}
    <div x-data="{
            channel: 'air', rawStatus: '', result: null, resolvedBy: null, error: null,
            async test() {
                this.error = null; this.result = null;
                try {
                    const res = await fetch('{{ route('suppliers.status-map.test') }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: JSON.stringify({ supplier_id: {{ $supplier->id }}, channel: this.channel, raw_status: this.rawStatus })
                    });
                    const data = await res.json();
                    if (data.success) { this.result = data.canonical_status; this.resolvedBy = data.resolved_by; }
                    else { this.error = data.message ?? 'Test failed.'; }
                } catch (e) { this.error = 'Network error.'; }
            }
        }" class="border-t border-gray-100 pt-4 mb-4">
        <h3 class="text-sm font-semibold text-gray-700 mb-2">Test a raw status</h3>
        <div class="flex flex-wrap gap-2 items-end">
            <select x-model="channel" class="border border-gray-300 rounded px-2 py-1 text-sm">
                @foreach (['air','magic','webhook','ai_pdf','manual'] as $c)
                    <option value="{{ $c }}">{{ $c }}</option>
                @endforeach
            </select>
            <input type="text" x-model="rawStatus" placeholder="e.g. OK, confirm, RQ" class="border border-gray-300 rounded px-2 py-1 text-sm">
            <button type="button" @click="test()" class="bg-gray-700 hover:bg-gray-800 text-white text-xs font-semibold px-3 py-1.5 rounded">Test</button>
        </div>
        <div x-show="result" x-cloak class="mt-2 text-sm">
            Resolves to <span class="px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 font-semibold" x-text="result"></span>
            <span class="text-gray-500">via <span x-text="resolvedBy"></span></span>
        </div>
        <div x-show="error" x-cloak class="mt-2 text-sm text-red-700" x-text="error"></div>
    </div>

    {{-- "Unmapped statuses seen" -- sourced from task_status_events (event=status_unmapped),
         the audit row that carries the channel+raw_status a needs_review Task's own row does not
         (see SupplierController::show()'s own docblock note). --}}
    @if ($unmappedStatuses->isNotEmpty())
        <div class="border-t border-gray-100 pt-4">
            <h3 class="text-sm font-semibold text-gray-700 mb-2">Unmapped statuses seen</h3>
            <div class="space-y-2">
                @foreach ($unmappedStatuses as $event)
                    <div class="flex items-center justify-between bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-sm">
                        <span><span class="font-mono">{{ $event->channel }}</span> / <span class="font-mono font-semibold">{{ $event->raw_status }}</span></span>
                        @if ($canManageSupplier)
                            <form action="{{ route('suppliers.status-map.create-from-unmapped') }}" method="POST" class="flex items-center gap-1">
                                @csrf
                                <input type="hidden" name="supplier_id" value="{{ $supplier->id }}">
                                <input type="hidden" name="channel" value="{{ $event->channel }}">
                                <input type="hidden" name="raw_status" value="{{ $event->raw_status }}">
                                <select name="canonical_status" class="border border-gray-300 rounded px-2 py-1 text-xs">
                                    @foreach (['on_hold','confirmed','issued','reissued','void','refund','emd','cancelled'] as $c)
                                        <option value="{{ $c }}">{{ $c }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="text-xs font-semibold text-blue-600 hover:text-blue-800">Create mapping</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
