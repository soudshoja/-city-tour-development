<div x-data="notificationsTab()" x-init="init()">
    <div x-show="loading" class="main-set-loading-container">
        <svg class="main-set-spinner" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="noti-spinner-circle" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="noti-spinner-path" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span class="main-set-loading-text">Loading notification settings...</span>
    </div>

    <div x-show="!loading" x-cloak>
        <div class="main-set-header">
            <div class="main-set-header-content">
                <h3>Notification Settings</h3>
                <p>Configure how and where notifications are sent for different events</p>
            </div>
        </div>

        <div class="noti-subtab-desktop">
            <button @click="activeSubTab = 'unassigned-task'" class="noti-subtab-btn" :class="{'noti-subtab-btn-active': activeSubTab === 'unassigned-task'}">
                Unassigned Tasks
            </button>
            <button @click="activeSubTab = 'agent-task-close'" class="noti-subtab-btn" :class="{'noti-subtab-btn-active': activeSubTab === 'agent-task-close'}">
                Agent Notifications
            </button>
            <button @click="activeSubTab = 'auto-billing'" class="noti-subtab-btn" :class="{'noti-subtab-btn-active': activeSubTab === 'auto-billing'}">
                Auto Billing
            </button>
        </div>

        <div x-show="activeSubTab === 'unassigned-task'">
            @include('settings.partial.notification.unassigned_task')
        </div>
        <div x-show="activeSubTab === 'agent-task-close'" x-cloak>
            @include('settings.partial.notification.agent_task_close')
        </div>
        <div x-show="activeSubTab === 'auto-billing'" x-cloak>
            @include('settings.partial.notification.auto_billing')
        </div>
    </div>
</div>

<script>
    function notificationsTab() {
        return {
            loading: false,
            saving: false,
            activeSubTab: 'unassigned-task',
            companyId: "{{ $companyId }}",

            settings: {
                'notification.unassigned_task': { channel: 'none', email: '', phone: '' },
                'notification.autobill': { channel: 'none', email: '', phone: '' },
            },

            agents: [],
            agentSettings: {},
            searchQuery: '',
            selectedAgents: [],
            showEditModal: false,
            showBulkModal: false,
            editingAgent: null,
            editingSetting: {
                id: null,
                channel: 'email',
                is_active: true,
            },
            bulkSetting: {
                channel: 'email',
                is_active: true,
            },

            init() {
                window.addEventListener('notifications-tab-loaded', () => {
                    this.loadSettings();
                });
            },

            async loadSettings() {
                if (this.agents.length > 0) return;

                this.loading = true;

                try {
                    let url = '{{ route("settings.notifications") }}';
                    if (this.companyId) {
                        url += '?company_id=' + this.companyId;
                    }

                    const response = await fetch(url, {
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                    const data = await response.json();
                    if (data.success) {
                        this.settings = data.settings;
                    }

                    await this.loadAgentNotifications();
                } catch (error) {
                    console.error('Error loading notification settings:', error);
                } finally {
                    this.loading = false;
                }
            },

            async loadAgentNotifications() {
                let url = '{{ route("settings.agent-notifications") }}';
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
                        this.agentSettings = data.settings;
                    }
                } catch (error) {
                    console.error('Error loading agent notifications:', error);
                }
            },

            async saveCompanySetting(prefix) {
                this.saving = true;

                try {
                    const settingData = this.settings[prefix];
                    const response = await fetch('{{ route("settings.notifications.update") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            prefix: prefix,
                            channel: settingData.channel,
                            email: settingData.email,
                            phone: settingData.phone,
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        const alert = document.getElementById('custom-success-ajax-alert');
                        if (alert) {
                            alert.classList.remove('hidden');
                            alert.querySelector('p').innerHTML = 'Setting saved successfully';
                            setTimeout(() => alert.classList.add('hidden'), 3000);
                        }
                    }
                } catch (error) {
                    console.error('Error saving:', error);
                } finally {
                    this.saving = false;
                }
            },

            get filteredAgents() {
                if (!this.searchQuery) return this.agents;
                const query = this.searchQuery.toLowerCase();
                return this.agents.filter(agent =>
                    agent.name?.toLowerCase().includes(query) ||
                    agent.email?.toLowerCase().includes(query)
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
                return this.agentSettings[agentId] || null;
            },

            getTypeLabel(type) {
                const labels = {
                    'task_close': 'Task Close',
                };
                return labels[type] || type || '-';
            },

            getChannelLabel(channel) {
                const labels = {
                    'email': 'Email',
                    'whatsapp': 'WhatsApp',
                    'both': 'Both'
                };
                return labels[channel] || 'Email';
            },

            getChannelBadgeClass(channel) {
                const classes = {
                    'email': 'main-set-badge-blue',
                    'whatsapp': 'main-set-badge-green',
                    'both': 'main-set-badge-purple'
                };
                return classes[channel] || 'main-set-badge-blue';
            },

            openEditModal(agent) {
                this.editingAgent = agent;
                const existing = this.getAgentSetting(agent.id);

                if (existing) {
                    this.editingSetting = {
                        id: existing.id,
                        channel: existing.channel || 'email',
                        is_active: existing.is_active ?? true,
                    };
                } else {
                    this.editingSetting = {
                        id: null,
                        channel: 'email',
                        is_active: true,
                    };
                }

                this.showEditModal = true;
            },

            async saveAgentSetting() {
                this.saving = true;

                try {
                    const response = await fetch('{{ route("settings.agent-notifications.store") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            ...this.editingSetting,
                            agent_id: this.editingAgent.id,
                            company_id: this.companyId,
                            notification_type: 'task_close',
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        this.agentSettings[this.editingAgent.id] = {
                            ...this.editingSetting,
                            ...data.setting,
                            agent_id: this.editingAgent.id,
                        };
                        this.showEditModal = false;
                        const alert = document.getElementById('custom-success-ajax-alert');
                        if (alert) {
                            alert.classList.remove('hidden');
                            alert.querySelector('p').innerHTML = 'Agent notification saved successfully';
                            setTimeout(() => alert.classList.add('hidden'), 3000);
                        }
                    }
                } catch (error) {
                    console.error('Error saving:', error);
                } finally {
                    this.saving = false;
                }
            },

            async deleteSetting() {
                if (!confirm('Remove notification setting for this agent?')) return;

                this.saving = true;

                try {
                    const response = await fetch('{{ route("settings.agent-notifications.delete", ["id" => "SETTING_ID"]) }}'.replace('SETTING_ID', this.editingSetting.id), {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });

                    const data = await response.json();

                    if (data.success) {
                        delete this.agentSettings[this.editingAgent.id];
                        this.showEditModal = false;
                        const alert = document.getElementById('custom-success-ajax-alert');
                        if (alert) {
                            alert.classList.remove('hidden');
                            alert.querySelector('p').innerHTML = 'Notification setting removed';
                            setTimeout(() => alert.classList.add('hidden'), 3000);
                        }
                    }
                } catch (error) {
                    console.error('Error deleting:', error);
                } finally {
                    this.saving = false;
                }
            },

            async bulkUpdate() {
                if (this.selectedAgents.length === 0) return;

                this.saving = true;

                try {
                    const response = await fetch('{{ route("settings.agent-notifications.bulk-update") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            agent_ids: this.selectedAgents,
                            company_id: this.companyId,
                            notification_type: 'task_close',
                            ...this.bulkSetting
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        this.agents = [];
                        this.agentSettings = {};
                        await this.loadAgentNotifications();
                        this.selectedAgents = [];
                        this.showBulkModal = false;
                        const alert = document.getElementById('custom-success-ajax-alert');
                        if (alert) {
                            alert.classList.remove('hidden');
                            alert.querySelector('p').innerHTML = 'Bulk update completed successfully';
                            setTimeout(() => alert.classList.add('hidden'), 3000);
                        }
                    }
                } catch (error) {
                    console.error('Error bulk updating:', error);
                } finally {
                    this.saving = false;
                }
            }
        }
    }
</script>
