<?php

namespace App\Observers;

use App\Models\Agent;
use App\Models\AgentNotificationSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AgentObserver
{
    /**
     * Fire when a new agent is created. Seed default notification settings
     * (channel=both, is_active=true) for every notification type the system
     * supports, so new agents receive uninvoiced-task and uninvoiced-payment-link
     * reminders by default. Admin can disable per agent later in
     * Settings → Notifications → Agent Notifications.
     *
     * firstOrCreate guarantees idempotency (no-ops if an admin already
     * pre-seeded a row before save). Failures are logged and swallowed
     * so they can't block agent creation.
     */
    public function created(Agent $agent): void
    {
        $companyId = $agent->branch?->company_id;
        if (!$companyId) {
            Log::warning('[AgentObserver] Could not resolve company_id for new agent — skipping default notification settings', [
                'agent_id' => $agent->id,
                'branch_id' => $agent->branch_id,
            ]);
            return;
        }

        $actorId = Auth::id();

        $types = [
            AgentNotificationSetting::TYPE_TASK_CLOSE,
            AgentNotificationSetting::TYPE_PAYMENT_LINK_UNINVOICED,
        ];

        foreach ($types as $type) {
            try {
                AgentNotificationSetting::firstOrCreate(
                    [
                        'agent_id' => $agent->id,
                        'company_id' => $companyId,
                        'notification_type' => $type,
                    ],
                    [
                        'channel' => AgentNotificationSetting::CHANNEL_BOTH,
                        'is_active' => true,
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ]
                );
            } catch (\Throwable $e) {
                Log::error('[AgentObserver] Failed to seed default notification setting', [
                    'agent_id' => $agent->id,
                    'company_id' => $companyId,
                    'notification_type' => $type,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
