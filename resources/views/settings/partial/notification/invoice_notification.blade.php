<div>
    <div class="main-set-info-box">
        <div class="main-set-info-box-content">
            <svg class="main-set-info-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div class="main-set-info-text">
                <p>Invoice Notification</p>
                <ul>
                    <li>The recipient below (e.g. the accountant) receives the invoice email whenever ANY agent creates an invoice</li>
                    <li>They receive the detailed staff copy (PNR, issued date, net price, payment method, payment summary)</li>
                    <li>Independent of the per-agent "Invoice Notifications" in Agent Notifications — those send agents their own copies</li>
                    <li>Set the channel to Disabled to stop these emails</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="noti-form" @cannot('manageNotifications', 'App\Models\Setting') style="pointer-events: none; opacity: 0.7;" @endcannot>
        <div class="noti-panel">
            <div class="noti-panel-title">Notification Channel</div>
            <div class="noti-channel-grid" style="grid-template-columns: repeat(2, 1fr);">
                <label class="noti-channel-card noti-channel-card-disabled" :class="{'noti-channel-card-active': settings['notification.invoice_created'].channel === 'none'}">
                    <input type="radio" value="none" x-model="settings['notification.invoice_created'].channel" class="noti-sr-only" @cannot('manageNotifications', 'App\Models\Setting') disabled @endcannot>
                    <svg class="noti-channel-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                    </svg>
                    <span class="noti-channel-title">Disabled</span>
                </label>

                <label class="noti-channel-card" :class="{'noti-channel-card-active': settings['notification.invoice_created'].channel === 'email'}">
                    <input type="radio" value="email" x-model="settings['notification.invoice_created'].channel" class="noti-sr-only" @cannot('manageNotifications', 'App\Models\Setting') disabled @endcannot>
                    <svg class="noti-channel-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    <span class="noti-channel-title">Email</span>
                </label>
            </div>
        </div>

        <div class="noti-panel">
            <div class="noti-panel-title">Recipient Details</div>
            <div x-show="settings['notification.invoice_created'].channel === 'none'" class="noti-disabled-msg">
                Invoice notifications are disabled. Select Email to configure the recipient.
            </div>

            <div x-show="['email', 'both'].includes(settings['notification.invoice_created'].channel)" x-transition class="noti-field">
                <label>Recipient Email (e.g. accountant)</label>
                <input type="email" x-model="settings['notification.invoice_created'].email" placeholder="e.g. accountant@company.com" class="noti-input" @cannot('manageNotifications', 'App\Models\Setting') disabled @endcannot>
            </div>

            @can('manageNotifications', 'App\Models\Setting')
            <div x-show="settings['notification.invoice_created'].channel !== 'none'" class="noti-field" style="text-align: right;">
                <button @click="saveCompanySetting('notification.invoice_created')" :disabled="saving" class="main-set-btn main-set-btn-primary">
                    <span x-show="!saving">Save Settings</span>
                    <span x-show="saving">Saving...</span>
                </button>
            </div>
            @endcan
        </div>
    </div>
</div>
