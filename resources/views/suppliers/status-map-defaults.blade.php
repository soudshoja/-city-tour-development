<x-app-layout>
    <div class="flex items-center gap-3 my-3">
        <a href="{{ route('suppliers.index') }}" class="text-gray-400 hover:text-gray-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        </a>
        <h2 class="text-2xl font-bold">Status map — company defaults</h2>
    </div>
    <p class="text-sm text-gray-500 mb-4">Channel-wide rows (no specific supplier): "every {{ '' }} import from any supplier maps X to Y" for this company, without touching each supplier individually.</p>

    <div x-data="{ editingId: null, adding: false }" class="bg-white rounded-lg shadow-md p-6">
        @if ($rows->isEmpty())
            <div class="text-sm text-gray-500 italic mb-3">No company-wide defaults yet.</div>
        @else
            <div class="overflow-x-auto border border-gray-200 rounded-lg mb-3">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Channel</th>
                            <th class="text-left px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Raw status</th>
                            <th class="text-left px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Canonical</th>
                            <th class="text-center px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Active</th>
                            <th class="text-right px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($rows as $row)
                            <tr class="hover:bg-blue-50/50">
                                <td class="px-3 py-2 font-mono">{{ $row->channel }}</td>
                                <td class="px-3 py-2 font-mono">{{ $row->raw_status }}</td>
                                <td class="px-3 py-2"><span class="px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 text-xs font-semibold">{{ $row->canonical_status }}</span></td>
                                <td class="px-3 py-2 text-center">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $row->active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-500' }}">{{ $row->active ? 'Active' : 'Inactive' }}</span>
                                </td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                    <button type="button" @click="editingId = editingId === {{ $row->id }} ? null : {{ $row->id }}" class="text-blue-600 hover:text-blue-800 text-xs font-semibold mr-2">Edit</button>
                                    <form action="{{ route('suppliers.status-map.deactivate', $row->id) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold {{ $row->active ? 'text-red-600 hover:text-red-800' : 'text-emerald-600 hover:text-emerald-800' }}">{{ $row->active ? 'Deactivate' : 'Reactivate' }}</button>
                                    </form>
                                </td>
                            </tr>
                            <tr x-show="editingId === {{ $row->id }}" x-cloak>
                                <td colspan="5" class="px-3 py-3 bg-gray-50">
                                    <form action="{{ route('suppliers.status-map.update', $row->id) }}" method="POST" class="grid grid-cols-2 md:grid-cols-4 gap-2 items-end">
                                        @csrf @method('PUT')
                                        <select name="channel" class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
                                            @foreach (['air','magic','webhook','ai_pdf','manual'] as $c)
                                                <option value="{{ $c }}" @selected($row->channel === $c)>{{ $c }}</option>
                                            @endforeach
                                        </select>
                                        <input type="text" name="raw_status" value="{{ $row->raw_status }}" class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
                                        <select name="canonical_status" class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
                                            @foreach (['on_hold','confirmed','issued','reissued','void','refund','emd','cancelled','needs_review'] as $c)
                                                <option value="{{ $c }}" @selected($row->canonical_status === $c)>{{ $c }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-3 py-1.5 rounded">Save</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <button type="button" @click="adding = !adding" class="bg-blue-100 text-blue-700 hover:bg-blue-200 font-medium text-xs px-3 py-1.5 rounded-lg transition">+ Add company-wide default</button>
        <form x-show="adding" x-cloak action="{{ route('suppliers.status-map.create-from-unmapped') }}" method="POST" class="grid grid-cols-2 md:grid-cols-4 gap-2 items-end mt-2 bg-gray-50 rounded-lg p-3">
            @csrf
            <select name="channel" class="w-full border border-gray-300 rounded px-2 py-1 text-sm" required>
                @foreach (['air','magic','webhook','ai_pdf','manual'] as $c)
                    <option value="{{ $c }}">{{ $c }}</option>
                @endforeach
            </select>
            <input type="text" name="raw_status" required placeholder="Raw status" class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
            <select name="canonical_status" class="w-full border border-gray-300 rounded px-2 py-1 text-sm" required>
                @foreach (['on_hold','confirmed','issued','reissued','void','refund','emd','cancelled'] as $c)
                    <option value="{{ $c }}">{{ $c }}</option>
                @endforeach
            </select>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-3 py-1.5 rounded">Add</button>
        </form>
    </div>

    @if ($unmapped->isNotEmpty())
        <div class="bg-white rounded-lg shadow-md p-6 mt-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-2">Unmapped statuses seen (any supplier)</h3>
            <div class="space-y-2">
                @foreach ($unmapped as $event)
                    <div class="flex items-center justify-between bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-sm">
                        <span><span class="font-mono">{{ $event->channel }}</span> / <span class="font-mono font-semibold">{{ $event->raw_status }}</span>
                            @if ($event->meta['supplier_id'] ?? null) &mdash; supplier #{{ $event->meta['supplier_id'] }} @endif
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</x-app-layout>
