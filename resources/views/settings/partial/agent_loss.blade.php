<div x-data="agentLossTab()" x-init="init()">
    <div x-show="loading" class="main-set-loading-container">
        <svg class="main-set-spinner" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span class="main-set-loading-text">Loading agent loss settings...</span>
    </div>

    <div x-show="!loading" x-cloak>
        <div class="main-set-header">
            <div class="main-set-header-content">
                <h3>Invoice Debit Loss Settings</h3>
                <p>Configure who bears the loss when supplier price exceeds invoice amount</p>
            </div>
            <button @click="showBulkModal = true" class="main-set-btn main-set-btn-primary">
                <svg class="main-set-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                Bulk Update
            </button>
        </div>

        <div class="main-set-info-box">
            <div class="main-set-info-box-content">
                <svg class="main-set-info-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div class="main-set-info-text">
                    <p>How debit loss calculation works:</p>
                    <ul>
                        <li><strong>Loss occurs when:</strong> Supplier Price > Invoice Amount</li>
                        <li><strong>Company Bears All:</strong> Full loss recorded in company's account</li>
                        <li><strong>Agent Bears All:</strong> Full loss deducted and recorded in agent's loss account</li>
                        <li><strong>Split:</strong> Loss shared based on percentage setting</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="main-set-search-container">
            <input type="text" x-model="searchQuery" placeholder="Search agents..." class="main-set-search-input">
        </div>

        <div class="main-set-table-container">
            <table class="main-set-table">
                <thead>
                    <tr>
                        <th>
                            <input type="checkbox" @change="toggleSelectAll" :checked="allSelected" class="main-set-checkbox">
                        </th>
                        <th>Agent</th>
                        <th>Branch</th>
                        <th>Type</th>
                        <th>Loss Bearer</th>
                        <th>Agent %</th>
                        <th>Loss Account</th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="agent in filteredAgents" :key="agent.id">
                        <tr>
                            <td>
                                <input type="checkbox" :value="agent.id" x-model="selectedAgents" class="main-set-checkbox">
                            </td>
                            <td>
                                <div class="main-set-agent-name" x-text="agent.name"></div>
                                <div class="main-set-agent-email" x-text="agent.email"></div>
                            </td>
                            <td>
                                <span class="main-set-text-sm main-set-text-gray-600" x-text="agent.branch?.name || '-'"></span>
                            </td>
                            <td>
                                <span class="main-set-badge"
                                    :class="getAgentTypeBadgeClass(agent.type_id)"
                                    x-text="getAgentTypeName(agent.type_id)">
                                </span>
                            </td>
                            <td>
                                <span class="main-set-badge"
                                    :class="getBearerBadgeClass(getAgentSetting(agent.id)?.loss_bearer || 'company')"
                                    x-text="getBearerLabel(getAgentSetting(agent.id)?.loss_bearer || 'company')">
                                </span>
                            </td>
                            <td>
                                <span class="main-set-text-sm main-set-text-gray-600" x-text="getAgentPercentageDisplay(agent.id)"></span>
                            </td>
                            <td>
                                <template x-if="agent.loss_account">
                                    <div class="al-account-wrapper">
                                        <span class="al-account-name" x-text="agent.loss_account.name"></span>
                                        <span class="al-account-code" x-text="'#' + agent.loss_account.code"></span>
                                    </div>
                                </template>
                                <template x-if="!agent.loss_account">
                                    <span class="al-account-missing">
                                        <svg class="al-account-missing-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                        </svg>
                                        Not set
                                    </span>
                                </template>
                            </td>
                            <td>
                                <span x-show="getAgentSetting(agent.id)?.id" class="main-set-status-configured">Configured</span>
                                <span x-show="!getAgentSetting(agent.id)?.id" class="main-set-status-default">Default</span>
                            </td>
                            <td style="text-align: right;">
                                <button @click="openEditModal(agent)" class="main-set-edit-link">
                                    Edit
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
                <p class="main-set-empty-text">No agents found</p>
            </div>
        </template>
    </div>

    <div x-show="showEditModal" x-cloak class="main-set-modal-overlay">
        <div class="main-set-modal-wrapper">
            <div class="main-set-modal-backdrop" @click="showEditModal = false"></div>

            <div class="main-set-modal-content">
                <form @submit.prevent="saveAgentSetting">
                    <div class="main-set-modal-header">
                        <h3 class="main-set-modal-title">
                            Edit Loss Settings for <span x-text="editingAgent?.name"></span>
                        </h3>

                        <div class="main-set-mb-4">
                            <label class="main-set-form-label">Who Bears the Loss?</label>
                            <div class="main-set-radio-group">
                                <label class="main-set-radio-option" :class="{'main-set-radio-option-active': editingSetting.loss_bearer === 'company'}">
                                    <input type="radio" name="loss_bearer" value="company" x-model="editingSetting.loss_bearer" class="main-set-radio-input">
                                    <div class="main-set-radio-label-wrapper">
                                        <span class="main-set-radio-label-title">Company Bears All</span>
                                        <p class="main-set-radio-label-desc">Full loss recorded in company's account</p>
                                    </div>
                                </label>
                                <label class="main-set-radio-option"
                                       :class="{'main-set-radio-option-active': editingSetting.loss_bearer === 'agent'}">
                                    <input type="radio" name="loss_bearer" value="agent" x-model="editingSetting.loss_bearer" class="main-set-radio-input">
                                    <div class="main-set-radio-label-wrapper">
                                        <span class="main-set-radio-label-title">Agent Bears All</span>
                                        <p class="main-set-radio-label-desc">Full loss deducted from agent</p>
                                    </div>
                                </label>
                                <label class="main-set-radio-option" :class="{'main-set-radio-option-active': editingSetting.loss_bearer === 'split'}">
                                    <input type="radio" name="loss_bearer" value="split" x-model="editingSetting.loss_bearer" class="main-set-radio-input">
                                    <div class="main-set-radio-label-wrapper">
                                        <span class="main-set-radio-label-title">Split</span>
                                        <p class="main-set-radio-label-desc">Share loss by percentage</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div x-show="editingSetting.loss_bearer === 'split'" class="main-set-percentage-section">
                            <label class="main-set-form-label main-set-mb-2">Agent Percentage</label>
                            <div class="main-set-percentage-wrapper">
                                <input type="number" x-model="editingSetting.agent_percentage" min="0" max="100" step="0.01"
                                    @input="editingSetting.company_percentage = 100 - editingSetting.agent_percentage" class="main-set-number-input">
                                <span class="main-set-percentage-symbol">%</span>
                                <span class="main-set-percentage-divider">|</span>
                                <span class="main-set-percentage-info">Company: <span x-text="editingSetting.company_percentage"></span>%</span>
                            </div>
                            <p class="main-set-percentage-note">Agent and company percentages must sum to 100%</p>
                        </div>

                        <div class="main-set-mb-4">
                            <label class="main-set-form-label">Notes (optional)</label>
                            <textarea x-model="editingSetting.notes" rows="2" class="main-set-textarea" placeholder="Any notes about this setting..."></textarea>
                        </div>
                    </div>

                    <div class="main-set-modal-footer">
                        <button type="submit" :disabled="saving" class="main-set-btn main-set-btn-primary">
                            <span x-show="!saving">Save</span>
                            <span x-show="saving">Saving...</span>
                        </button>
                        <button type="button" @click="showEditModal = false; resetEditModal()" class="main-set-btn main-set-btn-secondary">
                            Cancel
                        </button>
                        <button type="button" x-show="editingSetting.id" @click="deleteSetting" class="main-set-btn main-set-btn-danger">
                            Reset to Default
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div x-show="showBulkModal" x-cloak class="main-set-modal-overlay">
        <div class="main-set-modal-wrapper">
            <div class="main-set-modal-backdrop" @click="showBulkModal = false"></div>

            <div class="main-set-modal-content">
                <form @submit.prevent="bulkUpdate">
                    <div class="main-set-modal-header">
                        <h3 class="main-set-modal-title">Bulk Update Loss Settings</h3>

                        <div x-show="selectedAgents.length === 0" class="main-set-alert-warning">
                            <p class="main-set-alert-warning-text">Please select agents from the table first</p>
                        </div>

                        <div x-show="selectedAgents.length > 0">
                            <p class="main-set-text-sm main-set-text-gray-600 main-set-mb-4">
                                Updating settings for <strong x-text="selectedAgents.length"></strong> selected agent(s)
                            </p>

                            <div class="main-set-mb-4">
                                <label class="main-set-form-label">Who Bears the Loss?</label>
                                <select x-model="bulkSetting.loss_bearer" class="main-set-select">
                                    <option value="company">Company Bears All</option>
                                    <option value="agent">Agent Bears All</option>
                                    <option value="split">Split</option>
                                </select>
                            </div>

                            <div x-show="bulkSetting.loss_bearer === 'split'" class="main-set-percentage-section">
                                <label class="main-set-form-label main-set-mb-2">Agent Percentage</label>
                                <div class="main-set-percentage-wrapper">
                                    <input type="number" x-model="bulkSetting.agent_percentage" min="0" max="100" step="0.01"
                                        @input="bulkSetting.company_percentage = 100 - bulkSetting.agent_percentage" class="main-set-number-input">
                                    <span class="main-set-percentage-symbol">%</span>
                                    <span class="main-set-percentage-divider">|</span>
                                    <span class="main-set-percentage-info">Company: <span x-text="bulkSetting.company_percentage"></span>%</span>
                                </div>
                                <p class="main-set-percentage-note">Agent and company percentages must sum to 100%</p>
                            </div>

                            <div class="main-set-mb-4">
                                <label class="main-set-form-label">Notes (optional)</label>
                                <textarea x-model="bulkSetting.notes" rows="2" class="main-set-textarea" placeholder="Any notes about this setting..."></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="main-set-modal-footer">
                        <button type="submit" :disabled="saving || selectedAgents.length === 0" class="main-set-btn main-set-btn-primary">
                            <span x-show="!saving">Update All</span>
                            <span x-show="saving">Updating...</span>
                        </button>
                        <button type="button" 
                                @click="showBulkModal = false; resetBulkModal()"
                                class="main-set-btn main-set-btn-secondary">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function agentLossTab() {
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
                loss_bearer: 'company',
                agent_percentage: 0,
                company_percentage: 100,
                notes: ''
            },
            bulkSetting: {
                loss_bearer: 'company',
                agent_percentage: 0,
                company_percentage: 100,
                notes: ''
            },
            companyId: "{{ $companyId }}",

            init() {
                window.addEventListener('agent-loss-tab-loaded', () => {
                    this.loadAgentLoss();
                });
            },

            async loadAgentLoss() {
                if (this.agents.length > 0) return;

                this.loading = true;

                let url = '{{ route("settings.agent-loss") }}';
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
                    console.error('Error loading agent loss:', error);
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
                if (setting.loss_bearer === 'company') return '0%';
                if (setting.loss_bearer === 'agent') return '100%';
                return (parseFloat(setting.agent_percentage) || 0) + '%';
            },

            getAgentTypeName(typeId) {
                const types = {
                    1: 'Salary',
                    2: 'Commission',
                    3: 'Both-A',
                    4: 'Both-B'
                };
                return types[typeId] || 'Unknown';
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
                    'company': 'Company',
                    'agent': 'Agent',
                    'split': 'Split'
                };
                return labels[bearer] || 'Company';
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
                    this.editingSetting = {
                        id: existing.id,
                        loss_bearer: existing.loss_bearer || 'company',
                        agent_percentage: existing.agent_percentage ?? 0,
                        company_percentage: existing.company_percentage ?? 100,
                        notes: existing.notes || ''
                    };
                } else {
                    this.editingSetting = {
                        id: null,
                        loss_bearer: 'company',
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
                    const response = await fetch('{{ route("settings.agent-loss.store") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            ...this.editingSetting,
                            agent_id: this.editingAgent.id,
                            company_id: this.companyId,
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        this.settings[this.editingAgent.id] = {
                            ...this.editingSetting,
                            ...data.setting,
                            agent_id: this.editingAgent.id,
                        };
                        this.showEditModal = false;
                        this.resetEditModal();
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
                if (!confirm('Reset this agent to default settings (Company Bears All)?')) return;

                this.saving = true;

                try {
                    const response = await fetch('{{ route("settings.agent-loss.delete", ["id" => "SETTING_ID"]) }}'.replace('SETTING_ID', this.editingSetting.id), {
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
                        this.resetEditModal();
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
                    const response = await fetch('{{ route("settings.agent-loss.bulk-update") }}', {
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
                        this.agents = [];
                        this.settings = {};
                        await this.loadAgentLoss();
                        this.selectedAgents = [];
                        this.showBulkModal = false;
                        this.resetBulkModal();
                    } else {
                        alert(data.message || 'Failed to update');
                    }
                } catch (error) {
                    console.error('Error bulk updating:', error);
                    alert('Failed to update');
                } finally {
                    this.saving = false;
                }
            },

            resetEditModal() {
                this.editingAgent = null;
                this.editingSetting = {
                    id: null,
                    loss_bearer: 'company',
                    agent_percentage: 0,
                    company_percentage: 100,
                    notes: ''
                };
            },

            resetBulkModal() {
                this.bulkSetting = {
                    loss_bearer: 'company',
                    agent_percentage: 0,
                    company_percentage: 100,
                    notes: ''
                };
            }
        }
    }
</script>