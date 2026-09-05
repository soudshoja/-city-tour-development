{{--
    T14 "Supplier bank details per currency" (accounting-builds PLAN.md §5 T14; L18). One row per
    (supplier, currency) remittance detail; at most one DEFAULT+active row per currency is
    enforced at the DB layer (supplier_bank_details_default_group_unique). Mirrors
    status-map-card.blade.php's own add/edit/deactivate Alpine pattern and
    SupplierPolicy::update-gated $canManageSupplier flag, computed once in
    SupplierController::show().
--}}
<div x-data="{ editingId: null, adding: false }" class="bg-white rounded-lg shadow-md p-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-building-columns text-blue-500"></i>
            Bank details for remittance
        </h2>
        <span class="text-xs text-gray-500">One default per currency &mdash; used to auto-fill payment vouchers</span>
    </div>

    @if ($bankDetailRows->isEmpty())
        <div class="text-sm text-gray-500 italic mb-3">No bank details on file yet. A payment voucher to this supplier will show a missing-currency warning until one is added.</div>
    @else
        <div class="overflow-x-auto border border-gray-200 rounded-lg mb-3">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Currency</th>
                        <th class="text-left px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Bank</th>
                        <th class="text-left px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Beneficiary</th>
                        <th class="text-left px-3 py-2 text-xs font-semibold text-gray-500 uppercase">IBAN / account no.</th>
                        <th class="text-left px-3 py-2 text-xs font-semibold text-gray-500 uppercase">SWIFT/BIC</th>
                        <th class="text-center px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Default</th>
                        <th class="text-center px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Status</th>
                        @if ($canManageSupplier)
                            <th class="text-right px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($bankDetailRows as $row)
                        <tr class="hover:bg-blue-50/50">
                            <td class="px-3 py-2"><span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 text-xs font-mono font-semibold">{{ $row->currency }}</span></td>
                            <td class="px-3 py-2">{{ $row->bank_name }}<div class="text-xs text-gray-400">{{ $row->bank_country }}</div></td>
                            <td class="px-3 py-2">{{ $row->beneficiary_name }}</td>
                            <td class="px-3 py-2 font-mono text-xs">{{ $row->iban ?? $row->account_number ?? '—' }}</td>
                            <td class="px-3 py-2 font-mono text-xs">{{ $row->swift_bic }}</td>
                            <td class="px-3 py-2 text-center">
                                @if ($row->is_default)
                                    <span class="px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 text-xs font-semibold">Default</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-center">
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $row->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-500' }}">{{ $row->is_active ? 'Active' : 'Inactive' }}</span>
                            </td>
                            @if ($canManageSupplier)
                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                    <button type="button" @click="editingId = editingId === {{ $row->id }} ? null : {{ $row->id }}" class="text-blue-600 hover:text-blue-800 text-xs font-semibold mr-2">Edit</button>
                                    <form action="{{ route('suppliers.bank-details.deactivate', $row->id) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold {{ $row->is_active ? 'text-red-600 hover:text-red-800' : 'text-emerald-600 hover:text-emerald-800' }}">{{ $row->is_active ? 'Deactivate' : 'Reactivate' }}</button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                        @if ($canManageSupplier)
                            <tr x-show="editingId === {{ $row->id }}" x-cloak>
                                <td colspan="8" class="px-3 py-3 bg-gray-50">
                                    <form action="{{ route('suppliers.bank-details.update', $row->id) }}" method="POST" class="grid grid-cols-2 md:grid-cols-4 gap-2 items-end">
                                        @csrf @method('PUT')
                                        <div>
                                            <label class="text-xs text-gray-500 block">Currency</label>
                                            <input type="text" name="currency" value="{{ $row->currency }}" maxlength="3" required class="w-full border border-gray-300 rounded px-2 py-1 text-sm uppercase">
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-500 block">Bank name</label>
                                            <input type="text" name="bank_name" value="{{ $row->bank_name }}" required class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-500 block">Bank country</label>
                                            <input type="text" name="bank_country" value="{{ $row->bank_country }}" required class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-500 block">Beneficiary name</label>
                                            <input type="text" name="beneficiary_name" value="{{ $row->beneficiary_name }}" required class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-500 block">IBAN</label>
                                            <input type="text" name="iban" value="{{ $row->iban }}" class="w-full border border-gray-300 rounded px-2 py-1 text-sm font-mono">
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-500 block">Account number</label>
                                            <input type="text" name="account_number" value="{{ $row->account_number }}" class="w-full border border-gray-300 rounded px-2 py-1 text-sm font-mono">
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-500 block">SWIFT/BIC</label>
                                            <input type="text" name="swift_bic" value="{{ $row->swift_bic }}" required class="w-full border border-gray-300 rounded px-2 py-1 text-sm font-mono uppercase">
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-500 block">Intermediary bank</label>
                                            <input type="text" name="intermediary_bank_name" value="{{ $row->intermediary_bank_name }}" class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-500 block">Intermediary SWIFT/BIC</label>
                                            <input type="text" name="intermediary_swift_bic" value="{{ $row->intermediary_swift_bic }}" class="w-full border border-gray-300 rounded px-2 py-1 text-sm font-mono uppercase">
                                        </div>
                                        <div class="md:col-span-2">
                                            <label class="text-xs text-gray-500 block">Notes</label>
                                            <input type="text" name="notes" value="{{ $row->notes }}" class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <label class="text-xs text-gray-500 flex items-center gap-1">
                                                <input type="checkbox" name="is_default" value="1" @checked($row->is_default)>
                                                Default for this currency
                                            </label>
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
        <div>
            <button type="button" @click="adding = !adding" class="bg-blue-100 text-blue-700 hover:bg-blue-200 font-medium text-xs px-3 py-1.5 rounded-lg transition">+ Add bank details</button>
            <form x-show="adding" x-cloak action="{{ route('suppliers.bank-details.store', $supplier->id) }}" method="POST" class="grid grid-cols-2 md:grid-cols-4 gap-2 items-end mt-2 bg-gray-50 rounded-lg p-3">
                @csrf
                <div>
                    <label class="text-xs text-gray-500 block">Currency</label>
                    <input type="text" name="currency" placeholder="EUR" maxlength="3" required class="w-full border border-gray-300 rounded px-2 py-1 text-sm uppercase">
                </div>
                <div>
                    <label class="text-xs text-gray-500 block">Bank name</label>
                    <input type="text" name="bank_name" required class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
                </div>
                <div>
                    <label class="text-xs text-gray-500 block">Bank country</label>
                    <input type="text" name="bank_country" required class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
                </div>
                <div>
                    <label class="text-xs text-gray-500 block">Beneficiary name</label>
                    <input type="text" name="beneficiary_name" required class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
                </div>
                <div>
                    <label class="text-xs text-gray-500 block">IBAN</label>
                    <input type="text" name="iban" class="w-full border border-gray-300 rounded px-2 py-1 text-sm font-mono">
                </div>
                <div>
                    <label class="text-xs text-gray-500 block">Account number</label>
                    <input type="text" name="account_number" class="w-full border border-gray-300 rounded px-2 py-1 text-sm font-mono">
                </div>
                <div>
                    <label class="text-xs text-gray-500 block">SWIFT/BIC</label>
                    <input type="text" name="swift_bic" required class="w-full border border-gray-300 rounded px-2 py-1 text-sm font-mono uppercase">
                </div>
                <div>
                    <label class="text-xs text-gray-500 block">Intermediary bank</label>
                    <input type="text" name="intermediary_bank_name" class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
                </div>
                <div>
                    <label class="text-xs text-gray-500 block">Intermediary SWIFT/BIC</label>
                    <input type="text" name="intermediary_swift_bic" class="w-full border border-gray-300 rounded px-2 py-1 text-sm font-mono uppercase">
                </div>
                <div class="md:col-span-2">
                    <label class="text-xs text-gray-500 block">Notes</label>
                    <input type="text" name="notes" class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-xs text-gray-500 flex items-center gap-1">
                        <input type="checkbox" name="is_default" value="1">
                        Default for this currency
                    </label>
                </div>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-3 py-1.5 rounded">Add</button>
            </form>
        </div>
    @else
        <div class="text-sm text-amber-700 flex items-center gap-2 bg-amber-50 border border-amber-200 rounded-md px-3 py-2">
            <i class="fa-solid fa-circle-info"></i>
            <span>If you need to change remittance bank details, please contact your system administrator.</span>
        </div>
    @endif
</div>
