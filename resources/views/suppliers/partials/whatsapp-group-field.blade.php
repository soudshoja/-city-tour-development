{{-- WhatsApp Group (PDF ingestion) field with a Verify button that resolves the
     typed group name to its stable group WID via Resayil. --}}
<div class="mt-2 wa-group-wrap">
    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">WhatsApp Group (PDF ingestion)</label>
    <div class="flex gap-2">
        <input type="text" name="whatsapp_group" value="{{ old('whatsapp_group', $supplier->whatsapp_group ?? '') }}" placeholder="Group name or group ID (…@g.us)"
            class="h-10 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-md px-3 w-full focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
        <button type="button" onclick="resolveWaGroup(this)"
            class="h-10 shrink-0 px-4 rounded-md bg-emerald-600 text-white text-sm font-medium shadow-sm hover:bg-emerald-700 transition">
            Verify
        </button>
    </div>
    <p class="wa-group-result text-xs mt-1"></p>
    <p class="text-xs text-gray-500 italic mt-1">PDF documents posted in this WhatsApp group are loaded as tasks automatically. Leave empty to disable. Type the group name and click Verify to confirm the group and fill in its permanent ID.</p>
</div>

@once
<script>
    async function resolveWaGroup(btn) {
        const wrap = btn.closest('.wa-group-wrap');
        const input = wrap.querySelector('input[name="whatsapp_group"]');
        const result = wrap.querySelector('.wa-group-result');
        const q = (input.value || '').trim();
        const setMsg = (msg, cls) => { result.textContent = msg; result.className = 'wa-group-result text-xs mt-1 ' + cls; };

        if (!q) { setMsg('Type the group name first.', 'text-red-600'); return; }

        btn.disabled = true;
        const orig = btn.textContent;
        btn.textContent = '…';
        try {
            const r = await fetch('{{ route('suppliers.resolve-wa-group') }}?q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            });
            const d = await r.json();
            if (!r.ok || !d.success) { setMsg(d.message || 'Lookup failed.', 'text-red-600'); return; }

            if (d.matches.length === 0) {
                setMsg('No WhatsApp group found matching "' + q + '". Check the name (the office WhatsApp must be a member of the group).', 'text-red-600');
            } else if (d.matches.length === 1) {
                input.value = d.matches[0].wid;
                setMsg('✓ ' + d.matches[0].name + ' — group ID filled in. Save the supplier to confirm.', 'text-emerald-600 font-semibold');
            } else {
                result.className = 'wa-group-result text-xs mt-1 text-gray-700';
                result.textContent = 'Several groups match — pick one: ';
                d.matches.forEach(m => {
                    const opt = document.createElement('button');
                    opt.type = 'button';
                    opt.textContent = m.name;
                    opt.className = 'underline text-blue-600 hover:text-blue-800 mr-2';
                    opt.onclick = () => {
                        input.value = m.wid;
                        setMsg('✓ ' + m.name + ' — group ID filled in. Save the supplier to confirm.', 'text-emerald-600 font-semibold');
                    };
                    result.appendChild(opt);
                });
            }
        } catch (e) {
            setMsg('Lookup failed: ' + e.message, 'text-red-600');
        } finally {
            btn.disabled = false;
            btn.textContent = orig;
        }
    }
</script>
@endonce
