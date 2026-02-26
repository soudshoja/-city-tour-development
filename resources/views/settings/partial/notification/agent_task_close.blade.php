<div>
    <div class="main-set-info-box">
        <div class="main-set-info-box-content">
            <svg class="main-set-info-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div class="main-set-info-text">
                <p>Agent Notifications</p>
                <ul>
                    <li>Configure per-agent notification preferences (channel and status)</li>
                    <li>Currently used for: Task close reminders (every 7 days, remind agents to close tasks and create invoices)</li>
                    <li>Uses the agent's email and phone from their profile</li>
                </ul>
            </div>
        </div>
    </div>

    <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; margin-bottom: 1rem;">
        <div class="main-set-search-container" style="margin-bottom: 0; flex: 1; min-width: 12rem;">
            <input type="text" x-model="searchQuery" placeholder="Search agents..." class="main-set-search-input">
        </div>
        <button @click="showBulkModal = true" class="main-set-btn main-set-btn-primary">
            <svg class="main-set-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            Bulk Update
        </button>
    </div>

    <div class="main-set-table-container" style="overflow-x: auto;">
        <table class="main-set-table" style="min-width: 600px;">
            <thead>
                <tr>
                    <th style="width: 2.5rem;">
                        <input type="checkbox" @change="toggleSelectAll" :checked="allSelected" class="main-set-checkbox">
                    </th>
                    <th>Agent</th>
                    <th class="main-set-hide-mobile">Email</th>
                    <th class="main-set-hide-mobile">Phone</th>
                    <th>Type</th>
                    <th>Channel</th>
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
                        </td>
                        <td class="main-set-hide-mobile">
                            <span class="main-set-text-sm main-set-text-gray-600" x-text="agent.email || '-'"></span>
                        </td>
                        <td class="main-set-hide-mobile">
                            <span class="main-set-text-sm main-set-text-gray-600" x-text="agent.phone_number || '-'"></span>
                        </td>
                        <td>
                            <span x-show="getAgentSetting(agent.id)" class="main-set-text-sm main-set-text-gray-600" x-text="getTypeLabel(getAgentSetting(agent.id)?.notification_type)">
                            </span>
                            <span x-show="!getAgentSetting(agent.id)" class="main-set-text-sm main-set-text-gray-600">-</span>
                        </td>
                        <td>
                            <span x-show="getAgentSetting(agent.id)" class="main-set-badge" :class="getChannelBadgeClass(getAgentSetting(agent.id)?.channel || 'email')"
                                x-text="getChannelLabel(getAgentSetting(agent.id)?.channel || 'email')">
                            </span>
                            <span x-show="!getAgentSetting(agent.id)" class="main-set-text-sm main-set-text-gray-600">-</span>
                        </td>
                        <td>
                            <span x-show="getAgentSetting(agent.id)?.is_active" class="main-set-status-configured">Active</span>
                            <span x-show="getAgentSetting(agent.id) && !getAgentSetting(agent.id)?.is_active" class="main-set-badge main-set-badge-yellow">Inactive</span>
                            <span x-show="!getAgentSetting(agent.id)" class="main-set-status-default">Not Set</span>
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

    <div x-show="showEditModal" x-cloak class="main-set-modal-overlay">
        <div class="main-set-modal-wrapper">
            <div class="main-set-modal-backdrop" @click="showEditModal = false"></div>
            <div class="main-set-modal-content" style="max-width: 28rem;">
                <form @submit.prevent="saveAgentSetting">
                    <div class="main-set-modal-header">
                        <h3 class="main-set-modal-title">
                            Notification: <span x-text="editingAgent?.name"></span>
                        </h3>

                        <div class="noti-panel" style="padding: 0.75rem; margin-bottom: 1rem;">
                            <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; font-size: 0.8125rem;">
                                <div>
                                    <span class="noti-panel-title" style="margin-bottom: 0.25rem; display: block;">Email</span>
                                    <span class="main-set-text-sm" x-text="editingAgent?.email || 'Not set'"
                                        :style="!editingAgent?.email && 'font-style: italic; opacity: 0.6'"></span>
                                </div>
                                <div>
                                    <span class="noti-panel-title" style="margin-bottom: 0.25rem; display: block;">Phone</span>
                                    <span class="main-set-text-sm" x-text="editingAgent?.phone_number || 'Not set'"
                                        :style="!editingAgent?.phone_number && 'font-style: italic; opacity: 0.6'"></span>
                                </div>
                            </div>
                        </div>

                        <div style="margin-bottom: 1rem;">
                            <label class="main-set-form-label main-set-mb-2">Notification Channel</label>
                            <div class="noti-channel-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 0;">
                                <label class="noti-channel-card" :class="{'noti-channel-card-active': editingSetting.channel === 'email'}" style="padding: 0.75rem 0.5rem;">
                                    <input type="radio" name="agent_channel" value="email" x-model="editingSetting.channel" class="noti-sr-only">
                                    <svg class="noti-channel-icon" style="width: 1.25rem; height: 1.25rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                    <span class="noti-channel-title">Email</span>
                                </label>

                                <label class="noti-channel-card" :class="{'noti-channel-card-active': editingSetting.channel === 'whatsapp'}" style="padding: 0.75rem 0.5rem;">
                                    <input type="radio" name="agent_channel" value="whatsapp" x-model="editingSetting.channel" class="noti-sr-only">
                                    <svg class="noti-channel-icon" style="width: 1.25rem; height: 1.25rem;" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                    </svg>
                                    <span class="noti-channel-title">WhatsApp</span>
                                </label>

                                <label class="noti-channel-card" :class="{'noti-channel-card-active': editingSetting.channel === 'both'}" style="padding: 0.75rem 0.5rem;">
                                    <input type="radio" name="agent_channel" value="both" x-model="editingSetting.channel" class="noti-sr-only">
                                    <svg class="noti-channel-icon" style="width: 1.25rem; height: 1.25rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                    </svg>
                                    <span class="noti-channel-title">Both</span>
                                </label>
                            </div>
                        </div>

                        <div class="main-set-mb-4">
                            <label class="main-set-form-label main-set-mb-2">Status</label>
                            <div style="display: flex; gap: 1rem;">
                                <label class="noti-channel-card" :class="{'noti-channel-card-active': editingSetting.is_active}" @click="editingSetting.is_active = true" style="padding: 0.75rem 1.25rem; flex: 1; cursor: pointer;">
                                    <span class="noti-channel-title">Active</span>
                                </label>
                                <label class="noti-channel-card" :class="{'noti-channel-card-active': !editingSetting.is_active}" @click="editingSetting.is_active = false" style="padding: 0.75rem 1.25rem; flex: 1; cursor: pointer;">
                                    <span class="noti-channel-title">Inactive</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="main-set-modal-footer">
                        <button type="submit" :disabled="saving" class="main-set-btn main-set-btn-primary">
                            <span x-show="!saving">Save</span>
                            <span x-show="saving">Saving...</span>
                        </button>
                        <button type="button" @click="showEditModal = false" class="main-set-btn main-set-btn-secondary">
                            Cancel
                        </button>
                        <button type="button" x-show="editingSetting.id" @click="deleteSetting" class="main-set-btn main-set-btn-danger">
                            Remove
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div x-show="showBulkModal" x-cloak class="main-set-modal-overlay">
        <div class="main-set-modal-wrapper">
            <div class="main-set-modal-backdrop" @click="showBulkModal = false"></div>
            <div class="main-set-modal-content" style="max-width: 28rem;">
                <form @submit.prevent="bulkUpdate">
                    <div class="main-set-modal-header">
                        <h3 class="main-set-modal-title">Bulk Update Notifications</h3>

                        <div x-show="selectedAgents.length === 0" class="main-set-alert-warning">
                            <p class="main-set-alert-warning-text">Please select agents from the table first</p>
                        </div>

                        <div x-show="selectedAgents.length > 0">
                            <p class="main-set-text-sm main-set-text-gray-600 main-set-mb-4">
                                Updating <strong x-text="selectedAgents.length"></strong> agent(s)
                            </p>

                            <div style="margin-bottom: 1rem;">
                                <label class="main-set-form-label main-set-mb-2">Channel</label>
                                <div class="noti-channel-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 0;">
                                    <label class="noti-channel-card" :class="{'noti-channel-card-active': bulkSetting.channel === 'email'}" style="padding: 0.75rem 0.5rem;">
                                        <input type="radio" name="bulk_channel" value="email" x-model="bulkSetting.channel" class="noti-sr-only">
                                        <svg class="noti-channel-icon" style="width: 1.25rem; height: 1.25rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                        </svg>
                                        <span class="noti-channel-title">Email</span>
                                    </label>

                                    <label class="noti-channel-card" :class="{'noti-channel-card-active': bulkSetting.channel === 'whatsapp'}" style="padding: 0.75rem 0.5rem;">
                                        <input type="radio" name="bulk_channel" value="whatsapp" x-model="bulkSetting.channel" class="noti-sr-only">
                                        <svg class="noti-channel-icon" style="width: 1.25rem; height: 1.25rem;" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                        </svg>
                                        <span class="noti-channel-title">WhatsApp</span>
                                    </label>

                                    <label class="noti-channel-card" :class="{'noti-channel-card-active': bulkSetting.channel === 'both'}" style="padding: 0.75rem 0.5rem;">
                                        <input type="radio" name="bulk_channel" value="both" x-model="bulkSetting.channel" class="noti-sr-only">
                                        <svg class="noti-channel-icon" style="width: 1.25rem; height: 1.25rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                        </svg>
                                        <span class="noti-channel-title">Both</span>
                                    </label>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="main-set-modal-footer">
                        <button type="submit" :disabled="saving || selectedAgents.length === 0" class="main-set-btn main-set-btn-primary">
                            <span x-show="!saving">Update All</span>
                            <span x-show="saving">Updating...</span>
                        </button>
                        <button type="button" @click="showBulkModal = false" class="main-set-btn main-set-btn-secondary">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
