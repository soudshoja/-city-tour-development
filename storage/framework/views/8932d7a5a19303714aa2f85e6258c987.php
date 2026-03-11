<div x-data="agentChargesTab()" x-init="init()">
    <div x-show="loading" class="flex justify-center items-center py-12">
        <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span class="ml-2 text-gray-600">Loading agent charge settings...</span>
    </div>

    <div x-show="!loading" x-cloak>
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">Agent Extra Charge Settings</h3>
                <p class="text-sm text-gray-500 mt-1">Configure who bears extra charges (gateway fees) for profit calculation</p> 
            </div>
            <button @click="showBulkModal = true"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                Bulk Update
            </button>
        </div>

        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <div class="flex">
                <svg class="w-5 h-5 text-blue-600 mt-0.5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div class="text-sm text-blue-800">
                    <p class="font-medium mb-1">How profit calculation works:</p>
                    <ul class="list-disc list-inside space-y-1 text-blue-700">
                        <li><strong>Profit = Markup - Agent's Charge Deduction</strong></li>
                        <li><strong>Extra Charges</strong> = Gateway Fees</li> 
                        <li><strong>Company Bears All:</strong> Agent keeps full markup as profit</li>
                        <li><strong>Agent Bears All:</strong> Full extra charges deducted from agent's profit</li>
                        <li><strong>Split:</strong> Charges shared based on percentage setting</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="mb-4">
            <input type="text" x-model="searchQuery" placeholder="Search agents..."
                class="w-full md:w-64 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>

        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <input type="checkbox" @change="toggleSelectAll" :checked="allSelected"
                                class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Agent</th>
                        <!-- <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Branch</th> -->
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Extra Charge Bearer</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Agent %</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <template x-for="agent in filteredAgents" :key="agent.id">
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <input type="checkbox" :value="agent.id" x-model="selectedAgents"
                                    class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900" x-text="agent.name"></div>
                                <div class="text-sm text-gray-500" x-text="agent.email"></div>
                            </td>
                            <!-- <td class="px-4 py-3 text-sm text-gray-600" x-text="agent.branch?.name || '-'"></td> -->
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                    :class="getAgentTypeBadgeClass(agent.type_id)"
                                    x-text="getAgentTypeName(agent.type_id)">
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                    :class="getBearerBadgeClass(getAgentSetting(agent.id)?.charge_bearer || 'company')"
                                    x-text="getBearerLabel(getAgentSetting(agent.id)?.charge_bearer || 'company')">
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                <span x-text="getAgentPercentageDisplay(agent.id)"></span>
                            </td>
                            <td class="px-4 py-3">
                                <span x-show="getAgentSetting(agent.id)?.id" class="text-green-600 text-sm">Configured</span>
                                <span x-show="!getAgentSetting(agent.id)?.id" class="text-gray-400 text-sm">Default</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button @click="openEditModal(agent)"
                                    class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                    Edit
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <template x-if="filteredAgents.length === 0">
            <div class="bg-white border border-gray-200 rounded-lg p-8 text-center mt-4">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <p class="mt-2 text-gray-500">No agents found</p>
            </div>
        </template>
    </div>

    <div x-show="showEditModal" x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showEditModal = false"></div>

            <div class="relative bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
                <form @submit.prevent="saveAgentSetting">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">
                            Edit Charge Settings for <span x-text="editingAgent?.name"></span>
                        </h3>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Who Bears Extra Charges?</label>
                            <div class="space-y-2">
                                <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50"
                                    :class="{'border-blue-500 bg-blue-50': editingSetting.charge_bearer === 'company'}">
                                    <input type="radio" name="charge_bearer" value="company"
                                        x-model="editingSetting.charge_bearer"
                                        class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                                    <div class="ml-3">
                                        <span class="font-medium text-gray-700">Company Bears All</span>
                                        <p class="text-sm text-gray-500">Agent keeps full markup as profit</p>
                                    </div>
                                </label>
                                <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50"
                                    :class="{'border-blue-500 bg-blue-50': editingSetting.charge_bearer === 'agent'}">
                                    <input type="radio" name="charge_bearer" value="agent"
                                        x-model="editingSetting.charge_bearer"
                                        class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                                    <div class="ml-3">
                                        <span class="font-medium text-gray-700">Agent Bears All</span>
                                        <p class="text-sm text-gray-500">Full charges deducted from profit</p>
                                    </div>
                                </label>
                                <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50"
                                    :class="{'border-blue-500 bg-blue-50': editingSetting.charge_bearer === 'split'}">
                                    <input type="radio" name="charge_bearer" value="split"
                                        x-model="editingSetting.charge_bearer"
                                        class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                                    <div class="ml-3">
                                        <span class="font-medium text-gray-700">Split</span>
                                        <p class="text-sm text-gray-500">Share charges by percentage</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div x-show="editingSetting.charge_bearer === 'split'" class="mb-4 p-4 bg-gray-50 rounded-lg">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Agent Percentage</label>
                            <div class="flex items-center gap-4">
                                <input type="number" x-model="editingSetting.agent_percentage" min="0" max="100" step="0.01"
                                    @input="editingSetting.company_percentage = 100 - editingSetting.agent_percentage"
                                    class="w-24 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <span class="text-gray-500">%</span>
                                <span class="text-gray-400 mx-2">|</span>
                                <span class="text-sm text-gray-600">Company: <span x-text="editingSetting.company_percentage"></span>%</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">Agent and company percentages must sum to 100%</p>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Notes (optional)</label>
                            <textarea x-model="editingSetting.notes" rows="2"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Any notes about this setting..."></textarea>
                        </div>
                    </div>

                    <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse gap-2">
                        <button type="submit" :disabled="saving"
                            class="w-full sm:w-auto px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50">
                            <span x-show="!saving">Save</span>
                            <span x-show="saving">Saving...</span>
                        </button>
                        <button type="button" @click="showEditModal = false"
                            class="w-full sm:w-auto mt-2 sm:mt-0 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="button" x-show="editingSetting.id" @click="deleteSetting"
                            class="w-full sm:w-auto mt-2 sm:mt-0 px-4 py-2 text-sm font-medium text-red-600 bg-white border border-red-300 rounded-lg hover:bg-red-50">
                            Reset to Default
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div x-show="showBulkModal" x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showBulkModal = false"></div>

            <div class="relative bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
                <form @submit.prevent="bulkUpdate">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">
                            Bulk Update Charge Settings
                        </h3>

                        <div x-show="selectedAgents.length === 0" class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                            <p class="text-sm text-yellow-800">Please select agents from the table first</p>
                        </div>

                        <div x-show="selectedAgents.length > 0">
                            <p class="text-sm text-gray-600 mb-4">
                                Updating settings for <strong x-text="selectedAgents.length"></strong> selected agent(s)
                            </p>

                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Who Bears Extra Charges?</label>
                                <select x-model="bulkSetting.charge_bearer"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="company">Company Bears All</option>
                                    <option value="agent">Agent Bears All</option>
                                    <option value="split">Split</option>
                                </select>
                            </div>

                            <div x-show="bulkSetting.charge_bearer === 'split'" class="mb-4 p-4 bg-gray-50 rounded-lg">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Agent Percentage</label>
                                <div class="flex items-center gap-4">
                                    <input type="number" x-model="bulkSetting.agent_percentage" min="0" max="100" step="0.01"
                                        @input="bulkSetting.company_percentage = 100 - bulkSetting.agent_percentage"
                                        class="w-24 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <span class="text-gray-500">%</span>
                                    <span class="text-gray-400 mx-2">|</span>
                                    <span class="text-sm text-gray-600">Company: <span x-text="bulkSetting.company_percentage"></span>%</span>
                                </div>
                                <p class="text-xs text-gray-500 mt-2">Agent and company percentages must sum to 100%</p>
                            </div>

                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Notes (optional)</label>
                                <textarea x-model="bulkSetting.notes" rows="2"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="Any notes about this setting..."></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse gap-2">
                        <button type="submit" :disabled="saving || selectedAgents.length === 0"
                            class="w-full sm:w-auto px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50">
                            <span x-show="!saving">Update All</span>
                            <span x-show="saving">Updating...</span>
                        </button>
                        <button type="button" @click="showBulkModal = false"
                            class="w-full sm:w-auto mt-2 sm:mt-0 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
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
            companyId: "<?php echo e($companyId); ?>",

            init() {
                window.addEventListener('agent-charges-tab-loaded', () => {
                    this.loadAgentCharges();
                });
            },

            async loadAgentCharges() {
                if (this.agents.length > 0) return;

                this.loading = true;

                let url = '<?php echo e(route("settings.agent-charges")); ?>';
                if (this.companyId) {
                    url += '?company_id=' + this.companyId;
                }

                try {
                    const response = await fetch(url, {
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>'
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
                    1: 'Salary',
                    2: 'Commission',
                    3: 'Both-A',
                    4: 'Both-B'
                };
                return types[typeId] || 'Unknown';
            },

            getAgentTypeBadgeClass(typeId) {
                const classes = {
                    1: 'bg-yellow-100 text-yellow-800',
                    2: 'bg-green-100 text-green-800',
                    3: 'bg-purple-100 text-purple-800',
                    4: 'bg-orange-100 text-orange-800'
                };
                return classes[typeId] || 'bg-gray-100 text-gray-800';
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
                    'company': 'bg-blue-100 text-blue-800',
                    'agent': 'bg-red-100 text-red-800',
                    'split': 'bg-yellow-100 text-yellow-800'
                };
                return classes[bearer] || 'bg-gray-100 text-gray-800';
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
                    const response = await fetch('<?php echo e(route("settings.agent-charges.store")); ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>'
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
                if (!confirm('Reset this agent to default settings (Company Bears All)?')) return;

                this.saving = true;

                try {
                    const response = await fetch('<?php echo e(route("settings.agent-charges.delete", ["id" => "SETTING_ID"])); ?>'.replace('SETTING_ID', this.editingSetting.id), {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>'
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
                    const response = await fetch('<?php echo e(route("settings.agent-charges.bulk-update")); ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>'
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
</script><?php /**PATH /home/soudshoja/soud-laravel/resources/views/settings/partial/agent_charges.blade.php ENDPATH**/ ?>