<div>
    <div class="main-set-info-box">
        <div class="main-set-info-box-content">
            <svg class="main-set-info-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div class="main-set-info-text">
                <p>Auto Billing Notifications</p>
                <ul>
                    <li>Configure where auto billing notifications are sent after invoice generation</li>
                    <li>Each company can set their own notification recipient</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="noti-form">
        <div class="noti-panel">
            <div class="noti-panel-title">Notification Channel</div>
            <div class="noti-channel-grid" style="grid-template-columns: repeat(2, 1fr);">
                <label class="noti-channel-card noti-channel-card-disabled" :class="{'noti-channel-card-active': settings['notification.autobill'].channel === 'none'}">
                    <input type="radio" value="none" x-model="settings['notification.autobill'].channel" class="noti-sr-only">
                    <svg class="noti-channel-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                    </svg>
                    <span class="noti-channel-title">Disabled</span>
                </label>

                <label class="noti-channel-card" :class="{'noti-channel-card-active': settings['notification.autobill'].channel === 'email'}">
                    <input type="radio" value="email" x-model="settings['notification.autobill'].channel" class="noti-sr-only">
                    <svg class="noti-channel-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    <span class="noti-channel-title">Email</span>
                </label>

                <label class="noti-channel-card" :class="{'noti-channel-card-active': settings['notification.autobill'].channel === 'whatsapp'}">
                    <input type="radio" value="whatsapp" x-model="settings['notification.autobill'].channel" class="noti-sr-only">
                    <svg class="noti-channel-icon" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                    </svg>
                    <span class="noti-channel-title">WhatsApp</span>
                </label>

                <label class="noti-channel-card" :class="{'noti-channel-card-active': settings['notification.autobill'].channel === 'both'}">
                    <input type="radio" value="both" x-model="settings['notification.autobill'].channel" class="noti-sr-only">
                    <svg class="noti-channel-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                    <span class="noti-channel-title">Both</span>
                </label>
            </div>
        </div>

        <div class="noti-panel">
            <div class="noti-panel-title">Recipient Details</div>
            <div x-show="settings['notification.autobill'].channel === 'none'" class="noti-disabled-msg">
                Notifications are disabled. Select a channel to configure recipients.
            </div>

            <div x-show="['email', 'both'].includes(settings['notification.autobill'].channel)" x-transition class="noti-field">
                <label>Recipient Email</label>
                <input type="email" x-model="settings['notification.autobill'].email" placeholder="e.g. billing@company.com" class="noti-input">
            </div>

            <div x-show="['whatsapp', 'both'].includes(settings['notification.autobill'].channel)" x-transition class="noti-field">
                <label>Recipient Phone (with country code)</label>
                <input type="text" x-model="settings['notification.autobill'].phone"
                    placeholder="e.g. +96512345678" class="noti-input">
            </div>

            <div x-show="settings['notification.autobill'].channel !== 'none'" class="noti-field" style="text-align: right;">
                <button @click="saveCompanySetting('notification.autobill')" :disabled="saving" class="main-set-btn main-set-btn-primary">
                    <span x-show="!saving">Save Settings</span>
                    <span x-show="saving">Saving...</span>
                </button>
            </div>
        </div>
    </div>
</div>
