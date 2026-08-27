<x-app-layout>
    @include('layouts.alert')

    {{--
        Task-scoped "Vouchers" mini page (Step 4 item 1, plan section 10, section
        16). Deliberately its own small page rather than a change wedged
        into tasks/index.blade.php or tasks/detail.blade.php (2600-3700+
        line files) -- reached from one new action-menu link (this step's
        own addition there). Plain inline CSS per this project's own rule
        (arbitrary Tailwind values are not compiled; public/build is
        months stale) -- everything visual here is the <style> block below
        via the layout's styles stack, not Tailwind utility classes.
    --}}
    @push('styles')
        <style>
            .vch-wrap { max-width: 860px; margin: 24px auto; padding: 0 16px; }
            .vch-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; box-shadow: 0 1px 3px rgba(15,23,42,.06); padding: 20px 24px; margin-bottom: 20px; }
            .vch-title { font-size: 16px; font-weight: 700; color: #1f2933; margin: 0 0 4px; }
            .vch-sub { font-size: 12.5px; color: #64748b; margin: 0 0 16px; }
            .vch-row { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
            .vch-select, .vch-btn { font-size: 13px; padding: 8px 14px; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; }
            .vch-btn-primary { background: #1d3f91; color: #fff; border-color: #1d3f91; cursor: pointer; }
            .vch-btn-primary:disabled { opacity: .6; cursor: not-allowed; }
            .vch-btn-outline { background: #fff; color: #1d3f91; border-color: #1d3f91; cursor: pointer; text-decoration: none; display: inline-block; }
            .vch-btn-danger { background: #fff; color: #b91c1c; border-color: #fca5a5; cursor: pointer; }
            .vch-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
            .vch-table th, .vch-table td { text-align: left; padding: 9px 10px; font-size: 12.5px; border-bottom: 1px solid #eef2f6; vertical-align: middle; }
            .vch-table th { color: #64748b; font-weight: 600; text-transform: uppercase; font-size: 10.5px; letter-spacing: .04em; }
            .vch-badge { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 10.5px; font-weight: 600; text-transform: uppercase; }
            .vch-badge-issued { background: #dcfce7; color: #166534; }
            .vch-badge-reissued, .vch-badge-refunded { background: #fef3c7; color: #92400e; }
            .vch-badge-void_pending { background: #fee2e2; color: #991b1b; }
            .vch-badge-cancelled { background: #e2e8f0; color: #475569; }
            .vch-badge-superseded { background: #e2e8f0; color: #475569; }
            .vch-actions { display: flex; gap: 6px; flex-wrap: wrap; }
            .vch-empty { font-size: 13px; color: #94a3b8; padding: 14px 0; }
            .vch-warn { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; border-radius: 8px; padding: 10px 14px; font-size: 12.5px; margin-bottom: 16px; }
            .vch-link-input { width: 100%; font-size: 12px; padding: 6px 8px; border: 1px solid #e2e8f0; border-radius: 6px; background: #f8fafc; color: #475569; }
        </style>
    @endpush

    <div class="vch-wrap" x-data="voucherPanel()">
        <div class="vch-card">
            <p class="vch-title">Vouchers &mdash; {{ $task->reference ?? ('Task #'.$task->id) }}</p>
            <p class="vch-sub">
                Type: {{ ucfirst($task->type) }} &middot; Status: {{ ucfirst($task->status) }}
                @if($catalogEntry)
                    &middot; Design: {{ $catalogEntry['name'] }}
                @endif
            </p>

            @unless($hasClient)
                <div class="vch-warn">
                    No client is attached to this task yet. The voucher can still be issued, previewed and
                    downloaded &mdash; attaching a client only unlocks the WhatsApp send button.
                </div>
            @endunless

            @if(! $catalogEntry)
                <p class="vch-empty">No voucher design exists for task type "{{ $task->type }}" yet.</p>
            @else
                <div class="vch-row">
                    <select class="vch-select" x-model="language">
                        <option value="EN">English</option>
                        <option value="ARB">Arabic</option>
                    </select>
                    <button type="button" class="vch-btn vch-btn-primary" @click="issue()" :disabled="issuing">
                        <span x-show="!issuing">Issue New Voucher</span>
                        <span x-show="issuing">Issuing&hellip;</span>
                    </button>
                </div>
            @endif
        </div>

        <div class="vch-card">
            <p class="vch-title">Issued vouchers</p>
            <template x-if="rows.length === 0">
                <p class="vch-empty">No vouchers issued for this task yet.</p>
            </template>
            <table class="vch-table" x-show="rows.length > 0">
                <thead>
                    <tr>
                        <th>Number</th>
                        <th>Status</th>
                        <th>Lang</th>
                        <th>Sent to</th>
                        <th>Link</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="row in rows" :key="row.id">
                        <tr>
                            <td x-text="row.voucher_number"></td>
                            <td><span class="vch-badge" :class="'vch-badge-' + row.status" x-text="row.status"></span></td>
                            <td x-text="row.language"></td>
                            <td x-text="row.sent_to_phone || '&mdash;'"></td>
                            <td style="min-width:180px"><input class="vch-link-input" type="text" readonly :value="row.public_url" @click="$event.target.select()"></td>
                            <td>
                                <div class="vch-actions">
                                    <a class="vch-btn vch-btn-outline" :href="row.public_url" target="_blank">View</a>
                                    {{-- BLOCKER B2: dompdf cannot shape Arabic (vouchers/partials/styles.blade.php
                                         has the codepoint evidence) -- an ARB voucher never gets a PDF file at
                                         all (VoucherService::renderPdf() is a no-op for it), so this button is
                                         honest about that instead of linking to a 404. --}}
                                    <a class="vch-btn vch-btn-outline" :href="row.download_url" x-show="row.language !== 'ARB'">PDF</a>
                                    <span class="vch-empty" style="padding:0;font-size:11px;" x-show="row.language === 'ARB'" title="PDF is not offered for Arabic vouchers -- the web page is sent as a link instead.">PDF: N/A (ARB)</span>
                                    <button type="button" class="vch-btn vch-btn-primary" @click="send(row)" :disabled="row.sending">
                                        <span x-show="!row.sending">Send</span>
                                        <span x-show="row.sending">Sending&hellip;</span>
                                    </button>
                                    <button type="button" class="vch-btn vch-btn-danger" @click="cancelVoucher(row)" x-show="row.status === 'issued'">Cancel</button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    @php
        // Built ahead of the <script> block (rather than inline inside
        // @json(...)) because Blade's directive-argument parser does not
        // reliably match nested [ ] spanning multiple lines inside a
        // closure passed straight to @json() -- verified live 2026-08-27
        // ("Unclosed '[' " compile error). A plain array built first, then
        // @json()'d on one line below, sidesteps that entirely.
        $voucherRows = $vouchers->map(fn ($v) => [
            'id' => $v->id,
            'voucher_number' => $v->voucher_number,
            'status' => $v->status,
            'language' => $v->language,
            'sent_to_phone' => $v->sent_to_phone,
            'sending' => false,
            'public_url' => route('travel-voucher.show', ['companyId' => $task->company_id, 'token' => $v->token]),
            'download_url' => route('vouchers.download', $v->id),
        ])->all();
    @endphp

    <script>
        function voucherPanel() {
            return {
                language: 'EN',
                issuing: false,
                rows: @json($voucherRows),
                csrf() {
                    return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                },
                issue() {
                    this.issuing = true;
                    fetch("{{ route('vouchers.task.issue', $task->id) }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrf(),
                        },
                        body: JSON.stringify({ language: this.language }),
                    })
                        .then(r => r.json())
                        .then(data => {
                            this.issuing = false;
                            if (data.success && data.voucher) {
                                this.rows.unshift(Object.assign({ sending: false }, data.voucher));
                            } else {
                                alert(data.message || 'Failed to issue voucher.');
                            }
                        })
                        .catch(() => { this.issuing = false; alert('Failed to issue voucher.'); });
                },
                send(row) {
                    row.sending = true;
                    fetch(`/vouchers/${row.id}/send`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrf(),
                        },
                    })
                        .then(r => r.json())
                        .then(data => {
                            row.sending = false;
                            if (data.success) {
                                row.sent_to_phone = data.sent_to_phone || row.sent_to_phone;
                            }
                            alert(data.message || (data.success ? 'Sent.' : 'Failed to send.'));
                        })
                        .catch(() => { row.sending = false; alert('Failed to send voucher.'); });
                },
                cancelVoucher(row) {
                    if (! confirm(`Cancel voucher ${row.voucher_number}? Its public link will stop working.`)) {
                        return;
                    }
                    fetch(`/vouchers/${row.id}/cancel`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrf(),
                        },
                    })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                row.status = 'cancelled';
                            } else {
                                alert(data.message || 'Failed to cancel voucher.');
                            }
                        });
                },
            };
        }
    </script>
</x-app-layout>
