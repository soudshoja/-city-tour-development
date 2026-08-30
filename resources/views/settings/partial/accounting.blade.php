<div x-data="accountingSettingsTab()" x-init="init()">
    <div x-show="loading" class="main-set-loading-container">
        <svg class="main-set-spinner" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span class="main-set-loading-text">Loading accounting settings...</span>
    </div>

    <div x-show="!loading" x-cloak>
        <div class="main-set-header">
            <div class="main-set-header-content">
                <h3>Accounting</h3>
                <p>Refund disposition, fee schedule, posting basis, and bearer defaults for the posting engine.</p>
            </div>
        </div>

        <div x-show="saveError" x-cloak class="main-set-alert-warning" x-text="saveError"></div>
        <div x-show="savedAt" x-cloak class="main-set-info-box" style="border-color:#a7f3d0;background:#ecfdf5;">
            <p style="color:#047857;margin:0;font-size:0.875rem;">Saved.</p>
        </div>

        <form @submit.prevent="save">
            <!-- Refund disposition ---------------------------------------------------------- -->
            <div class="main-set-mb-4">
                <h4 style="font-size:1rem;font-weight:600;margin-bottom:0.5rem;">Refund disposition</h4>
                <label class="main-set-form-label">Default when a client is owed money (invoice_overpay_cancel_policy)</label>
                <select x-model="settings.invoice_overpay_cancel_policy" class="main-set-select" @cannot('manageAccountingSettings', 'App\Models\Setting') disabled @endcannot>
                    <option value="credit">Credit to client account</option>
                    <option value="refund_out">Refund out (cash / bank)</option>
                    <option value="manual">Manual (staff decides per case)</option>
                </select>
                <p class="main-set-percentage-note">Any refund's own `method` field or a per-case override still wins over this default.</p>
            </div>

            <div class="main-set-mb-4">
                <label class="main-set-form-label">Unclaimed client credit / agent payable write-back</label>
                <div style="display:flex;align-items:center;gap:0.5rem;">
                    <input type="number" min="1" max="120" x-model.number="settings.unclaimed_writeback_months" class="main-set-number-input" style="max-width:8rem;" @cannot('manageAccountingSettings', 'App\Models\Setting') disabled @endcannot>
                    <span class="main-set-text-sm main-set-text-gray-600">months (manual approval always required)</span>
                </div>
            </div>

            <div class="main-set-mb-4">
                <label class="main-set-form-label">Commissionable fee types</label>
                <p class="main-set-percentage-note main-set-mb-2">Refund/cancellation fees for these service types earn agent commission. Empty by default — paying commission on a cancellation fee means cancelling costs the agent nothing.</p>
                <div style="display:flex;flex-wrap:wrap;gap:0.75rem;">
                    <template x-for="type in serviceTypes" :key="'commissionable-'+type">
                        <label style="display:flex;align-items:center;gap:0.375rem;font-size:0.875rem;">
                            <input type="checkbox" :value="type" x-model="settings.commissionable_fee_types" @cannot('manageAccountingSettings', 'App\Models\Setting') disabled @endcannot>
                            <span x-text="type"></span>
                        </label>
                    </template>
                </div>
            </div>

            <!-- Statements (P2.5.H) ------------------------------------------------------------ -->
            <div class="main-set-mb-4">
                <h4 style="font-size:1rem;font-weight:600;margin-bottom:0.5rem;">Statements</h4>
                <label class="main-set-form-label">Client / supplier / agent statement default</label>
                <select x-model="settings.statement_mode" class="main-set-select" @cannot('manageAccountingSettings', 'App\Models\Setting') disabled @endcannot>
                    <option value="open_items">Open items (unsettled documents + ageing)</option>
                    <option value="full_activity">Full activity (every document in the period)</option>
                </select>
                <p class="main-set-percentage-note">Each statement screen can still preview the other mode without changing this default.</p>
            </div>

            <!-- Notifications ----------------------------------------------------------------- -->
            <div class="main-set-mb-4">
                <h4 style="font-size:1rem;font-weight:600;margin-bottom:0.5rem;">Notifications</h4>
                <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.875rem;" class="main-set-mb-2">
                    <input type="checkbox" x-model="settings.refund_send_on_post" @cannot('manageAccountingSettings', 'App\Models\Setting') disabled @endcannot>
                    Send client CRN + statement when a refund posts
                </label>
                <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.875rem;">
                    <input type="checkbox" x-model="settings.agent_unearn_notice" @cannot('manageAccountingSettings', 'App\Models\Setting') disabled @endcannot>
                    Notify the agent when their commission is un-earned on refund
                </label>
            </div>

            <!-- Fee schedule -------------------------------------------------------------------- -->
            <div class="main-set-mb-4">
                <h4 style="font-size:1rem;font-weight:600;margin-bottom:0.5rem;">Refund fee schedule</h4>
                <div class="main-set-table-container">
                    <table class="main-set-table">
                        <thead>
                            <tr>
                                <th>Service type</th>
                                <th>Fee amount</th>
                                <th>Fee percent</th>
                                <th>Override policy</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="type in serviceTypes" :key="'fee-'+type">
                                <tr>
                                    <td x-text="type"></td>
                                    <td>
                                        <input type="number" step="0.001" min="0" x-model.number="settings.fee_schedule[type].amount" class="main-set-number-input" @cannot('manageAccountingSettings', 'App\Models\Setting') disabled @endcannot>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="0" max="100" x-model.number="settings.fee_schedule[type].percent" class="main-set-number-input" @cannot('manageAccountingSettings', 'App\Models\Setting') disabled @endcannot>
                                    </td>
                                    <td>
                                        <select x-model="settings.fee_schedule[type].override" class="main-set-select" @cannot('manageAccountingSettings', 'App\Models\Setting') disabled @endcannot>
                                            <option value="free">Free (no approval)</option>
                                            <option value="needs_approval">Needs approval</option>
                                        </select>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Posting basis ------------------------------------------------------------------- -->
            <div class="main-set-mb-4">
                <h4 style="font-size:1rem;font-weight:600;margin-bottom:0.5rem;">Posting basis per service type</h4>
                <p class="main-set-percentage-note main-set-mb-2">Agent = net commission posting; Principal = gross cost-of-sales posting. Revenue recognition timing is a future option (P2.5.D).</p>
                <div class="main-set-table-container">
                    <table class="main-set-table">
                        <thead>
                            <tr>
                                <th>Service type</th>
                                <th>Posting basis</th>
                                <th>Revenue recognition</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="type in serviceTypes" :key="'basis-'+type">
                                <tr>
                                    <td x-text="type"></td>
                                    <td>
                                        <select x-model="settings.posting_basis[type]" class="main-set-select" @cannot('manageAccountingSettings', 'App\Models\Setting') disabled @endcannot>
                                            <option value="agent">Agent (net)</option>
                                            <option value="principal">Principal (gross)</option>
                                        </select>
                                    </td>
                                    <td>
                                        <span class="main-set-badge main-set-badge-blue">On invoice (fixed)</span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Voucher approval / overdraft ---------------------------------------------------- -->
            <div class="main-set-mb-4">
                <h4 style="font-size:1rem;font-weight:600;margin-bottom:0.5rem;">Receipt &amp; payment vouchers</h4>
                <label class="main-set-form-label">Auto-approve threshold</label>
                <div style="display:flex;align-items:center;gap:0.5rem;">
                    <input type="number" step="0.001" min="0" x-model.number="settings.voucher_approval_threshold"
                           placeholder="Always require manual approval"
                           class="main-set-number-input" style="max-width:12rem;"
                           @cannot('manageAccountingSettings', 'App\Models\Setting') disabled @endcannot>
                    <span class="main-set-text-sm main-set-text-gray-600">KWD</span>
                </div>
                <p class="main-set-percentage-note">A receipt or payment voucher at or under this amount posts immediately on save. Leave blank so every voucher waits for a manual Approve, regardless of amount.</p>

                <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.875rem;margin-top:0.75rem;">
                    <input type="checkbox" x-model="settings.pv_allow_overdraft" @cannot('manageAccountingSettings', 'App\Models\Setting') disabled @endcannot>
                    Allow a payment voucher to take a bank account negative
                </label>
                <p class="main-set-percentage-note">Off by default. When off, a payment voucher is refused if it would leave the paying bank account below zero.</p>
            </div>

            <!-- Task lifecycle (void / hold-confirmed / reminders) ------------------------------ -->
            {{--
                W6.U (w6-brief.md "W6.U -- UI" -- "Settings addition: bulk_void_mode ... in the
                same accounting settings tab from W4.U"). bulk_void_mode/hold_auto_expire/
                hold_expire_grace_hours were REGISTERED (readable/writable) by W6.S already, via
                this same endpoint -- only the UI row for each was missing until now.
                hold_reminder_offsets_hours/hold_client_nudge are new options this sub-wave adds.
            --}}
            <div class="main-set-mb-4">
                <h4 style="font-size:1rem;font-weight:600;margin-bottom:0.5rem;">Task lifecycle: void &amp; hold/confirmed</h4>

                <label class="main-set-form-label">Bulk-void mode</label>
                <select x-model="settings.bulk_void_mode" class="main-set-select" @cannot('manageAccountingSettings', 'App\Models\Setting') disabled @endcannot>
                    <option value="atomic">Atomic (any failure rolls back the whole batch)</option>
                    <option value="per_task_report">Per-task report (partial success, one row per task)</option>
                </select>
                <p class="main-set-percentage-note">The default mode a bulk-void submission uses when the screen doesn't override it.</p>

                <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.875rem;margin-top:0.75rem;">
                    <input type="checkbox" x-model="settings.hold_auto_expire" @cannot('manageAccountingSettings', 'App\Models\Setting') disabled @endcannot>
                    Automatically expire on-hold/confirmed tasks past their deadline
                </label>
                <p class="main-set-percentage-note">Only tasks never issued/invoiced are ever touched -- an issued ticket is never expired.</p>

                <label class="main-set-form-label" style="margin-top:0.75rem;">Expiry grace period</label>
                <div style="display:flex;align-items:center;gap:0.5rem;">
                    <input type="number" min="0" max="720" x-model.number="settings.hold_expire_grace_hours" class="main-set-number-input" style="max-width:8rem;" @cannot('manageAccountingSettings', 'App\Models\Setting') disabled @endcannot>
                    <span class="main-set-text-sm main-set-text-gray-600">hours added to deadline_at before expiry is eligible</span>
                </div>

                <label class="main-set-form-label" style="margin-top:0.75rem;">Ticketing-deadline reminder offsets</label>
                <input type="text" placeholder="24,2" x-model="settings.hold_reminder_offsets_hours" class="main-set-number-input" style="max-width:12rem;" @cannot('manageAccountingSettings', 'App\Models\Setting') disabled @endcannot>
                <p class="main-set-percentage-note">Comma-separated hours-before-deadline; one reminder is created per offset (e.g. "24,2" = one a day before, one two hours before).</p>

                <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.875rem;margin-top:0.75rem;">
                    <input type="checkbox" x-model="settings.hold_client_nudge" @cannot('manageAccountingSettings', 'App\Models\Setting') disabled @endcannot>
                    Also nudge the client (WhatsApp) when a ticketing-deadline reminder fires
                </label>
                <p class="main-set-percentage-note">Off by default. The owner agent always receives the reminder regardless of this setting.</p>
            </div>

            <!-- Bearer matrix ------------------------------------------------------------------- -->
            {{--
                W4.U verify-fix (MEDIUM): this matrix originally shipped three rows
                (commission_clawback, adm, gateway_fee). Only commission_clawback has a real,
                documented consumer (RefundPostingService::postClawbackBearerRecoveryHook(),
                gated behind the P5.13 flag `accounting.engine.agent_loss_recovery_enabled` — a
                deliberate, DOCUMENTED stub, not silent dead config). `adm` had no consumer or
                hook anywhere — no ADM document type exists in this codebase yet for a bearer
                decision to even apply to. `gateway_fee` duplicated a DIFFERENT, already-shipped
                mechanism (W4.D's `Charge::paid_by`), entirely disconnected from this Setting row
                — a staff member configuring it would see zero actual effect. Per the brief's own
                W4.U rationale ("every wave that ships a config option must also ship a UI,
                otherwise the config is dead"), shipping a UI row for an option nothing reads is
                the same failure inverted, so both were removed rather than left to mislead.
            --}}
            <div class="main-set-mb-4">
                <h4 style="font-size:1rem;font-weight:600;margin-bottom:0.5rem;">Bearer defaults</h4>
                <p class="main-set-percentage-note main-set-mb-2">Company-wide defaults. Per-agent overrides are managed separately.</p>
                <div class="main-set-table-container">
                    <table class="main-set-table">
                        <thead>
                            <tr>
                                <th>Kind</th>
                                <th>Bearer</th>
                                <th>Split % (agent share)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="kind in bearerKinds" :key="'bearer-'+kind">
                                <tr>
                                    <td x-text="bearerLabels[kind]"></td>
                                    <td>
                                        <select x-model="settings.bearer[kind].value" class="main-set-select" @cannot('manageAccountingSettings', 'App\Models\Setting') disabled @endcannot>
                                            <option value="company">Company</option>
                                            <option value="agent">Agent</option>
                                            <option value="split">Split</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" min="0" max="100" step="1"
                                               x-show="settings.bearer[kind].value === 'split'"
                                               x-model.number="settings.bearer[kind].split_percent"
                                               class="main-set-number-input" style="max-width:6rem;" @cannot('manageAccountingSettings', 'App\Models\Setting') disabled @endcannot>
                                        <span x-show="settings.bearer[kind].value !== 'split'" class="main-set-text-sm main-set-text-gray-600">—</span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            @can('manageAccountingSettings', 'App\Models\Setting')
            <button type="submit" :disabled="saving" class="main-set-btn main-set-btn-primary">
                <span x-show="!saving">Save accounting settings</span>
                <span x-show="saving">Saving...</span>
            </button>
            @endcan
        </form>
    </div>
</div>

<script>
    function accountingSettingsTab() {
        return {
            loading: false,
            saving: false,
            saveError: null,
            savedAt: null,
            companyId: "{{ $companyId }}",
            serviceTypes: [],
            // W4.U verify-fix (MEDIUM): 'adm' and 'gateway_fee' removed — see the bearer-matrix
            // comment in the markup above for why. 'commission_clawback' is the only bearer kind
            // with a real, documented consumer.
            bearerKinds: ['commission_clawback'],
            bearerLabels: {
                commission_clawback: 'Commission clawback',
            },
            settings: {
                invoice_overpay_cancel_policy: 'credit',
                unclaimed_writeback_months: 12,
                commissionable_fee_types: [],
                refund_send_on_post: true,
                agent_unearn_notice: true,
                fee_schedule: {},
                posting_basis: {},
                bearer: {},
                voucher_approval_threshold: null,
                pv_allow_overdraft: false,
                bulk_void_mode: 'atomic',
                hold_auto_expire: true,
                hold_expire_grace_hours: 0,
                hold_reminder_offsets_hours: '24,2',
                hold_client_nudge: false,
                statement_mode: 'open_items',
            },

            init() {
                window.addEventListener('accounting-tab-loaded', () => {
                    this.loadSettings();
                });
            },

            async loadSettings() {
                if (this.serviceTypes.length > 0) return;

                this.loading = true;

                let url = '{{ route("settings.accounting-settings") }}';
                if (this.companyId) {
                    url += '?company_id=' + this.companyId;
                }

                try {
                    const response = await fetch(url, {
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                    const data = await response.json();
                    if (data.success) {
                        this.serviceTypes = data.serviceTypes;
                        this.settings = data.settings;
                    } else {
                        this.saveError = data.message || 'Failed to load accounting settings';
                    }
                } catch (error) {
                    console.error('Error loading accounting settings:', error);
                    this.saveError = 'Failed to load accounting settings';
                } finally {
                    this.loading = false;
                }
            },

            async save() {
                this.saving = true;
                this.saveError = null;
                this.savedAt = null;

                try {
                    const response = await fetch('{{ route("settings.accounting-settings.store") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify(this.settings)
                    });

                    const data = await response.json();

                    if (data.success) {
                        this.savedAt = Date.now();
                        setTimeout(() => { this.savedAt = null; }, 3000);
                    } else {
                        this.saveError = data.message || 'Failed to save accounting settings';
                    }
                } catch (error) {
                    console.error('Error saving accounting settings:', error);
                    this.saveError = 'Failed to save accounting settings';
                } finally {
                    this.saving = false;
                }
            }
        }
    }
</script>
