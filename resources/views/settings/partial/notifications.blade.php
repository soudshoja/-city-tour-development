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

        @if(auth()->user()->role_id !== \App\Models\Role::AGENT)
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
            <button @click="activeSubTab = 'invoice-notification'" class="noti-subtab-btn" :class="{'noti-subtab-btn-active': activeSubTab === 'invoice-notification'}">
                Invoice Notification
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
        <div x-show="activeSubTab === 'invoice-notification'" x-cloak>
            @include('settings.partial.notification.invoice_notification')
        </div>
        @else
        <div>
            @include('settings.partial.notification.agent_task_close')
        </div>
        @endif
    </div>
</div>

<script>
    function notificationsTab() {
        return {
            loading: false,
            saving: false,
            activeSubTab: '{{ auth()->user()->role_id === \App\Models\Role::AGENT ? 'agent-task-close' : 'unassigned-task' }}',
            companyId: "{{ $companyId }}",

            settings: {
                'notification.unassigned_task': { channel: 'none', email: '', phone: '' },
                'notification.autobill': { channel: 'none', email: '', phone: '' },
                'notification.invoice_created': { channel: 'none', email: '', phone: '' },
            },

            agents: [],
            agentSettings: {},
            channelOptions: { email: 'Email', whatsapp: 'WhatsApp', both: 'Both (Email & WhatsApp)' },
            typeOptions: {
                task_close: 'Uninvoiced Task Reminder',
                payment_link_uninvoiced: 'Uninvoiced Payment Link Reminder',
                invoice_created: 'Invoice Notifications',
            },
            supportedTypes: ['task_close', 'payment_link_uninvoiced', 'invoice_created'],
            searchQuery: '',
            selectedAgents: [],
            showEditModal: false,
            showBulkModal: false,
            editingAgent: null,
            // Per-type editor state — one entry per supported type
            editingSettings: {
                task_close: { id: null, channel: 'both', is_active: true },
                payment_link_uninvoiced: { id: null, channel: 'both', is_active: true },
                invoice_created: { id: null, channel: 'both', is_active: true },
            },
            bulkSetting: {
                channel: 'both',
                is_active: true,
                notification_type: 'task_close',
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
                        this.agentSettings = data.settings || {};
                        if (data.channelOptions) this.channelOptions = data.channelOptions;
                        if (data.typeOptions) this.typeOptions = data.typeOptions;
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
                            'Accept': 'application/json',
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

                    if (!response.ok) {
                        alert(data.message || 'Failed to save setting');
                        return;
                    }

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

            getAgentSetting(agentId, type) {
                // Type-specific accessor (kept for backwards-compat with table refs)
                const settings = this.agentSettings[agentId];
                if (!settings) return null;
                if (type) return settings[type] || null;
                // No type given → return the first configured one (legacy fallback)
                for (const t of this.supportedTypes) {
                    if (settings[t]) return settings[t];
                }
                return null;
            },

            getAgentSettings(agentId) {
                return this.agentSettings[agentId] || {};
            },

            isAnyTypeActive(agentId) {
                const settings = this.agentSettings[agentId] || {};
                return this.supportedTypes.some(t => settings[t]?.is_active);
            },

            activeTypeCount(agentId) {
                const settings = this.agentSettings[agentId] || {};
                return this.supportedTypes.reduce((n, t) => n + (settings[t]?.is_active ? 1 : 0), 0);
            },

            getTypeLabel(type) {
                return this.typeOptions[type] || type || '-';
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
                const existing = this.getAgentSettings(agent.id);

                // Build one editor entry per supported type — pre-fill from existing row if any,
                // otherwise default to channel=both + active=true (matches AgentObserver default)
                const next = {};
                for (const t of this.supportedTypes) {
                    const row = existing[t];
                    next[t] = {
                        id: row?.id ?? null,
                        channel: row?.channel || 'both',
                        is_active: row ? !!row.is_active : true,
                    };
                }
                this.editingSettings = next;
                this.showEditModal = true;
            },

            async saveAgentSetting() {
                this.saving = true;
                const aggregated = {};
                try {
                    // Upsert each supported type — backend storeAgentNotification handles one type per call
                    for (const t of this.supportedTypes) {
                        const setting = this.editingSettings[t];
                        const response = await fetch('{{ route("settings.agent-notifications.store") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                ...setting,
                                agent_id: this.editingAgent.id,
                                company_id: this.companyId,
                                notification_type: t,
                            })
                        });

                        const data = await response.json();
                        if (!response.ok || !data.success) {
                            alert((data && data.message) || `Failed to save ${this.getTypeLabel(t)}`);
                            return;
                        }
                        aggregated[t] = data.setting || { ...setting, agent_id: this.editingAgent.id, notification_type: t };
                    }

                    this.agentSettings[this.editingAgent.id] = aggregated;
                    this.showEditModal = false;
                    const alert = document.getElementById('custom-success-ajax-alert');
                    if (alert) {
                        alert.classList.remove('hidden');
                        alert.querySelector('p').innerHTML = 'Agent notification settings saved';
                        setTimeout(() => alert.classList.add('hidden'), 3000);
                    }
                } catch (error) {
                    console.error('Error saving:', error);
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
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            agent_ids: this.selectedAgents,
                            company_id: this.companyId,
                            notification_type: this.bulkSetting.notification_type,
                            channel: this.bulkSetting.channel,
                            is_active: this.bulkSetting.is_active,
                        })
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        alert(data.message || 'Failed to update settings');
                        return;
                    }

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
