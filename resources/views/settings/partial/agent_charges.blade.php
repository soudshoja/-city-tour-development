<div x-data="agentChargesTab()" x-init="init()">
    <div x-show="loading" class="main-set-loading-container">
        <svg class="main-set-spinner" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span class="main-set-loading-text">{{ __('settings.loading_agent_charges') }}...</span>
    </div>

    <div x-show="!loading" x-cloak>
        <div class="main-set-header">
            <div class="main-set-header-content">
                <h3>{{ __('settings.agent_extra_charge_settings') }}</h3>
                <p>{{ __('settings.agent_extra_charge_description') }}</p> {{--  + supplier surcharges --}}
            </div>
            @can('bulkManageAgentCharges', 'App\Models\Setting')
            <button @click="showBulkModal = true" class="main-set-btn main-set-btn-primary">
                <svg class="main-set-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                {{ __('general.bulk_update') }}
            </button>
            @endcan
        </div>

        <div class="main-set-info-box">
            <div class="main-set-info-box-content">
                <svg class="main-set-info-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div class="main-set-info-text">
                    <p>{{ __('settings.profit_calculation_works') }}:</p>
                    <ul>
                        <li><strong>{{ __('general.profit') }} = {{ __('general.markup') }} - {{ __('settings.agent_charge_deduction') }}</strong></li>
                        <li><strong>{{ __('general.extra_charges') }}</strong> = {{ __('general.gateway_fees') }}</li> {{-- (service_charge) + Supplier Surcharges --}}
                        <li><strong>{{ __('settings.company_bears_all') }}:</strong> {{ __('settings.company_bears_all_description') }}</li>
                        <li><strong>{{ __('settings.agent_bears_all') }}:</strong> {{ __('settings.agent_bears_all_description') }}</li>
                        <li><strong>{{ __('general.split') }}:</strong> {{ __('settings.split_charges') }}</li>
                    </ul>
                </div>
            </div>
        </div>

        @if(auth()->user()->role_id != \App\Models\Role::AGENT)
        <div class="main-set-search-container">
            <input type="text" x-model="searchQuery" placeholder="{{ __('settings.search_agents') }}" class="main-set-search-input">
        </div>
        @endif

        <div class="main-set-table-container">
            <table class="main-set-table">
                <thead>
                    <tr>
                        @can('bulkManageAgentCharges', 'App\Models\Setting')
                        <th>
                            <input type="checkbox" @change="toggleSelectAll" :checked="allSelected" class="main-set-checkbox">
                        </th>
                        @endcan
                        <th>{{ __('general.agent') }}</th>
                        <!-- <th>Branch</th> -->
                        <th>{{ __('general.type') }}</th>
                        <th>{{ __('general.extra_charge_bearer') }}</th>
                        <th>{{ __('general.agent_percentage') }}</th>
                        <th>{{ __('general.status') }}</th>
                        <th style="text-align: right;">{{ __('general.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="agent in filteredAgents" :key="agent.id">
                        <tr>
                            @can('bulkManageAgentCharges', 'App\Models\Setting')
                            <td>
                                <input type="checkbox" :value="agent.id" x-model="selectedAgents" class="main-set-checkbox">
                            </td>
                            @endcan
                            <td>
                                <div class="main-set-agent-name" x-text="agent.name"></div>
                                <div class="main-set-agent-email" x-text="agent.email"></div>
                            </td>
                            <!-- <td>
                                <span class="main-set-text-sm main-set-text-gray-600" x-text="agent.branch?.name || '-'"></span>
                            </td> -->
                            <td>
                                <span class="main-set-badge"
                                      :class="getAgentTypeBadgeClass(agent.type_id)"
                                      x-text="getAgentTypeName(agent.type_id)">
                                </span>
                            </td>
                            <td>
                                <span class="main-set-badge"
                                      :class="getBearerBadgeClass(getAgentSetting(agent.id)?.charge_bearer || 'company')"
                                      x-text="getBearerLabel(getAgentSetting(agent.id)?.charge_bearer || 'company')">
                                </span>
                            </td>
                            <td>
                                <span class="main-set-text-sm main-set-text-gray-600" x-text="getAgentPercentageDisplay(agent.id)"></span>
                            </td>
                            <td>
                                <span x-show="getAgentSetting(agent.id)?.id" class="main-set-status-configured">{{ __('settings.configured') }}</span>
                                <span x-show="!getAgentSetting(agent.id)?.id" class="main-set-status-default">{{ __('settings.default') }}</span>
                            </td>
                            <td style="text-align: right;">
                                <button @click="openEditModal(agent)" class="main-set-edit-link">
                                    @can('manageAgentCharges', 'App\Models\Setting') {{ __('general.edit') }} @else {{ __('general.view') }} @endcan
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <template x-if="filteredAgents.length === 0">
            <div class="main-set-empty-container">
                <svg class="main-set-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <p class="main-set-empty-text">{{ __('settings.no_agents_found') }}</p>
            </div>
        </template>
    </div>

    <div x-show="showEditModal" x-cloak class="main-set-modal-overlay">
        <div class="main-set-modal-wrapper">
            <div class="main-set-modal-backdrop" @click="showEditModal = false"></div>

            <div class="main-set-modal-content">
                <form @submit.prevent="saveAgentSetting">
                    <div class="main-set-modal-header">
                        <div class="main-set-modal-header-top">
                            <div>
                                <h3 class="main-set-modal-title">
                                    @can('manageAgentCharges', 'App\Models\Setting') {{ __('general.edit') }} @else {{ __('general.view') }} @endcan {{ __('settings.charge_settings') }}
                                </h3>
                                <p class="main-set-modal-subtitle">{{ __('settings.configure_who_bears_for') }} <span x-text="editingAgent?.name"></span></p>
                            </div>
                            <button type="button" @click="showEditModal = false" class="main-set-modal-close">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>

                        <div class="main-set-mb-4">
                            <label class="main-set-form-label">{{ __('settings.who_bears_extra_charges') }}</label>
                            <div class="main-set-radio-group">
                                <label class="main-set-radio-option @cannot('manageAgentCharges', 'App\Models\Setting') pointer-events-none @endcannot"
                                       :class="{'main-set-radio-option-active': editingSetting.charge_bearer === 'company'}">
                                    <input type="radio"
                                           name="charge_bearer"
                                           value="company"
                                           x-model="editingSetting.charge_bearer"
                                           @cannot('manageAgentCharges', 'App\Models\Setting') disabled @endcannot
                                           class="main-set-radio-input">
                                    <div class="main-set-radio-label-wrapper">
                                        <span class="main-set-radio-label-title">{{ __('settings.company_bears_all') }}</span>
                                        <p class="main-set-radio-label-desc">{{ __('settings.company_bears_all_description') }}</p>
                                    </div>
                                </label>
                                <label class="main-set-radio-option @cannot('manageAgentCharges', 'App\Models\Setting') pointer-events-none @endcannot"
                                       :class="{'main-set-radio-option-active': editingSetting.charge_bearer === 'agent'}">
                                    <input type="radio"
                                           name="charge_bearer"
                                           value="agent"
                                           x-model="editingSetting.charge_bearer"
                                           @cannot('manageAgentCharges', 'App\Models\Setting') disabled @endcannot
                                           class="main-set-radio-input">
                                    <div class="main-set-radio-label-wrapper">
                                        <span class="main-set-radio-label-title">{{ __('settings.agent_bears_all') }}</span>
                                        <p class="main-set-radio-label-desc">{{ __('settings.agent_bears_all_description') }}</p>
                                    </div>
                                </label>
                                <label class="main-set-radio-option @cannot('manageAgentCharges', 'App\Models\Setting') pointer-events-none @endcannot"
                                       :class="{'main-set-radio-option-active': editingSetting.charge_bearer === 'split'}">
                                    <input type="radio"
                                           name="charge_bearer"
                                           value="split"
                                           x-model="editingSetting.charge_bearer"
                                           @cannot('manageAgentCharges', 'App\Models\Setting') disabled @endcannot
                                           class="main-set-radio-input">
                                    <div class="main-set-radio-label-wrapper">
                                        <span class="main-set-radio-label-title">{{ __('general.split') }}</span>
                                        <p class="main-set-radio-label-desc">{{ __('settings.split_description') }}</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div x-show="editingSetting.charge_bearer === 'split'" class="main-set-percentage-section">
                            <label class="main-set-form-label main-set-mb-2">{{ __('settings.agent_percentage') }}</label>
                            <div class="main-set-percentage-wrapper">
                                <input type="number"
                                       x-model="editingSetting.agent_percentage"
                                       min="0"
                                       max="100"
                                       step="0.01"
                                       @input="editingSetting.company_percentage = 100 - editingSetting.agent_percentage"
                                       @cannot('manageAgentCharges', 'App\Models\Setting') disabled @endcannot
                                       class="main-set-number-input">
                                <span class="main-set-percentage-symbol">%</span>
                                <span class="main-set-percentage-divider">|</span>
                                <span class="main-set-percentage-info">{{ __('general.company') }}: <span x-text="editingSetting.company_percentage"></span>%</span>
                            </div>
                            <p class="main-set-percentage-note">{{ __('settings.percentage_must_sum') }}</p>
                        </div>

                        <div class="main-set-mb-4">
                            <label class="main-set-form-label">{{ __('settings.notes_optional') }}</label>
                            <textarea x-model="editingSetting.notes"
                                      rows="2"
                                      @cannot('manageAgentCharges', 'App\Models\Setting') disabled @endcannot
                                      class="main-set-textarea"
                                      placeholder="{{ __('settings.notes_placeholder') }}"></textarea>
                        </div>
                    </div>

                    <div class="main-set-modal-footer">
                        <button type="button"
                                @click="showEditModal = false"
                                class="main-set-btn main-set-btn-secondary">
                            @can('manageAgentCharges', 'App\Models\Setting') {{ __('general.cancel') }} @else {{ __('general.close') }} @endcan
                        </button>
                        <div class="main-set-modal-footer-right">
                            @can('manageAgentCharges', 'App\Models\Setting')
                            <button type="button"
                                    x-show="editingSetting.id"
                                    @click="deleteSetting"
                                    class="main-set-btn main-set-btn-danger">
                                {{ __('settings.reset_to_default') }}
                            </button>
                            <button type="submit"
                                    :disabled="saving"
                                    class="main-set-btn main-set-btn-primary">
                                <span x-show="!saving">{{ __('general.save') }}</span>
                                <span x-show="saving">{{ __('settings.saving') }}</span>
                            </button>
                            @endcan
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @can('bulkManageAgentCharges', 'App\Models\Setting')
    <div x-show="showBulkModal" x-cloak class="main-set-modal-overlay">
        <div class="main-set-modal-wrapper">
            <div class="main-set-modal-backdrop" @click="showBulkModal = false"></div>

            <div class="main-set-modal-content">
                <form @submit.prevent="bulkUpdate">
                    <div class="main-set-modal-header">
                        <div class="main-set-modal-header-top">
                            <div>
                                <h3 class="main-set-modal-title">{{ __('settings.bulk_update_charge_settings') }}</h3>
                                <p class="main-set-modal-subtitle">{{ __('settings.bulk_update_charge_description') }}</p>
                            </div>
                            <button type="button" @click="showBulkModal = false" class="main-set-modal-close">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>

                        <div x-show="selectedAgents.length === 0" class="main-set-alert-warning">
                            <p class="main-set-alert-warning-text">{{ __('settings.select_agents_first') }}</p>
                        </div>

                        <div x-show="selectedAgents.length > 0">
                            <p class="main-set-text-sm main-set-text-gray-600 main-set-mb-4">
                                {{ __('settings.updating_settings_for') }} <strong x-text="selectedAgents.length"></strong> {{ __('settings.selected_agents') }}
                            </p>

                            <div class="main-set-mb-4">
                                <label class="main-set-form-label">{{ __('settings.who_bears_extra_charges') }}</label>
                                <select x-model="bulkSetting.charge_bearer" class="main-set-select">
                                    <option value="company">{{ __('settings.company_bears_all') }}</option>
                                    <option value="agent">{{ __('settings.agent_bears_all') }}</option>
                                    <option value="split">{{ __('general.split') }}</option>
                                </select>
                            </div>

                            <div x-show="bulkSetting.charge_bearer === 'split'" class="main-set-percentage-section">
                                <label class="main-set-form-label main-set-mb-2">{{ __('settings.agent_percentage') }}</label>
                                <div class="main-set-percentage-wrapper">
                                    <input type="number"  x-model="bulkSetting.agent_percentage"  min="0"  max="100"  step="0.01"
                                           @input="bulkSetting.company_percentage = 100 - bulkSetting.agent_percentage"
                                           class="main-set-number-input">
                                    <span class="main-set-percentage-symbol">%</span>
                                    <span class="main-set-percentage-divider">|</span>
                                    <span class="main-set-percentage-info">{{ __('general.company') }}: <span x-text="bulkSetting.company_percentage"></span>%</span>
                                </div>
                                <p class="main-set-percentage-note">{{ __('settings.percentage_must_sum') }}</p>
                            </div>

                            <div class="main-set-mb-4">
                                <label class="main-set-form-label">{{ __('settings.notes_optional') }}</label>
                                <textarea x-model="bulkSetting.notes" rows="2" class="main-set-textarea"
                                          placeholder="{{ __('settings.notes_placeholder') }}"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="main-set-modal-footer">
                        <button type="button" @click="showBulkModal = false" class="main-set-btn main-set-btn-secondary">
                            {{ __('general.cancel') }}
                        </button>
                        <button type="submit" :disabled="saving || selectedAgents.length === 0"
                                class="main-set-btn main-set-btn-primary">
                            <span x-show="!saving">{{ __('settings.update_all') }}</span>
                            <span x-show="saving">{{ __('settings.updating') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endcan
</div>

<script>
    function agentChargesTab() {
        return {
            agents: [],
            settings: {},
            loading: false,
            saving: false,
            searchQuery: '',
            selectedAgents: [],
            showEditModal: false,
            showBulkModal: false,
            editingAgent: null,
            editingSetting: {
                id: null,
                charge_bearer: 'company',
                agent_percentage: 0,
                company_percentage: 100,
                notes: ''
            },
            bulkSetting: {
                charge_bearer: 'company',
                agent_percentage: 0,
                company_percentage: 100,
                notes: ''
            },
            companyId: "{{ $companyId }}",

            init() {
                window.addEventListener('agent-charges-tab-loaded', () => {
                    this.loadAgentCharges();
                });
            },

            async loadAgentCharges() {
                if (this.agents.length > 0) return;

                this.loading = true;

                let url = '{{ route("settings.agent-charges") }}';
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
                        this.agents = data.agents;
                        this.settings = data.settings;
                    }
                } catch (error) {
                    console.error('Error loading agent charges:', error);
                } finally {
                    this.loading = false;
                }
            },

            get filteredAgents() {
                if (!this.searchQuery) return this.agents;
                const query = this.searchQuery.toLowerCase();
                return this.agents.filter(agent =>
                    agent.name?.toLowerCase().includes(query) ||
                    agent.email?.toLowerCase().includes(query) ||
                    agent.branch?.name?.toLowerCase().includes(query)
                );
            },

            get allSelected() {
                return this.filteredAgents.length > 0 &&
                    this.filteredAgents.every(a => this.selectedAgents.includes(a.id));
            },

            toggleSelectAll() {
                if (this.allSelected) {
                    this.selectedAgents = [];
                } else {
                    this.selectedAgents = this.filteredAgents.map(a => a.id);
                }
            },

            getAgentSetting(agentId) {
                return this.settings[agentId] || null;
            },

            getAgentPercentageDisplay(agentId) {
                const setting = this.getAgentSetting(agentId);
                if (!setting) return '0%';
                if (setting.charge_bearer === 'company') return '0%';
                if (setting.charge_bearer === 'agent') return '100%';
                return (parseFloat(setting.agent_percentage) || 0) + '%';
            },

            getAgentTypeName(typeId) {
                const types = {
                    1: '{{ __('general.salary') }}',
                    2: '{{ __('general.commission') }}',
                    3: '{{ __('general.both_a') }}',
                    4: '{{ __('general.both_b') }}'
                };
                return types[typeId] || '{{ __('general.unknown') }}';
            },

            getAgentTypeBadgeClass(typeId) {
                const classes = {
                    1: 'main-set-badge-yellow',
                    2: 'main-set-badge-green',
                    3: 'main-set-badge-purple',
                    4: 'main-set-badge-orange'
                };
                return classes[typeId] || 'main-set-badge-blue';
            },

            getBearerLabel(bearer) {
                const labels = {
                    'company': '{{ __('general.company') }}',
                    'agent': '{{ __('general.agent') }}',
                    'split': '{{ __('general.split') }}'
                };
                return labels[bearer] || '{{ __('general.company') }}';
            },

            getBearerBadgeClass(bearer) {
                const classes = {
                    'company': 'main-set-badge-blue',
                    'agent': 'main-set-badge-red',
                    'split': 'main-set-badge-yellow'
                };
                return classes[bearer] || 'main-set-badge-blue';
            },

            openEditModal(agent) {
                this.editingAgent = agent;
                const existing = this.getAgentSetting(agent.id);

                if (existing) {
                    // Only copy the fields we need, NOT agent_id/company_id
                    this.editingSetting = {
                        id: existing.id,
                        charge_bearer: existing.charge_bearer || 'company',
                        agent_percentage: existing.agent_percentage ?? 0,
                        company_percentage: existing.company_percentage ?? 100,
                        notes: existing.notes || ''
                    };
                } else {
                    this.editingSetting = {
                        id: null,
                        charge_bearer: 'company',
                        agent_percentage: 0,
                        company_percentage: 100,
                        notes: ''
                    };
                }

                this.showEditModal = true;
            },

            async saveAgentSetting() {
                this.saving = true;

                try {
                    const response = await fetch('{{ route("settings.agent-charges.store") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            ...this.editingSetting, // 👈 Spread FIRST
                            agent_id: this.editingAgent.id, // 👈 Then explicit values OVERRIDE
                            company_id: this.companyId,
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        // Also ensure we store full data
                        this.settings[this.editingAgent.id] = {
                            ...this.editingSetting,
                            ...data.setting,
                            agent_id: this.editingAgent.id, // Ensure correct agent_id
                        };
                        this.showEditModal = false;
                    } else {
                        alert(data.message || 'Failed to save setting');
                    }
                } catch (error) {
                    console.error('Error saving:', error);
                    alert('Failed to save setting');
                } finally {
                    this.saving = false;
                }
            },

            async deleteSetting() {
                if (!confirm('{{ __('settings.reset_confirm') }}')) return;

                this.saving = true;

                try {
                    const response = await fetch('{{ route("settings.agent-charges.delete", ["id" => "SETTING_ID"]) }}'.replace('SETTING_ID', this.editingSetting.id), {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });

                    const data = await response.json();

                    if (data.success) {
                        delete this.settings[this.editingAgent.id];
                        this.showEditModal = false;
                    } else {
                        alert(data.message || 'Failed to delete setting');
                    }
                } catch (error) {
                    console.error('Error deleting:', error);
                    alert('Failed to delete setting');
                } finally {
                    this.saving = false;
                }
            },

            async bulkUpdate() {
                if (this.selectedAgents.length === 0) return;

                this.saving = true;

                try {
                    const response = await fetch('{{ route("settings.agent-charges.bulk-update") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            agent_ids: this.selectedAgents,
                            company_id: this.companyId,
                            ...this.bulkSetting
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        // Reload to get updated settings
                        this.agents = [];
                        this.settings = {};
                        await this.loadAgentCharges();
                        this.selectedAgents = [];
                        this.showBulkModal = false;
                    } else {
                        alert(data.message || 'Failed to update');
                    }
                } catch (error) {
                    console.error('Error bulk updating:', error);
                    alert('Failed to update');
                } finally {
                    this.saving = false;
                }
            }
        }
    }
</script>