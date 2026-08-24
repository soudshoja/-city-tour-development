<?php

namespace Tests\Feature\Console;

use App\Mail\UninvoicedPaymentLinkReminderMail;
use App\Models\Agent;
use App\Models\AgentNotificationSetting;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Fix 5 (2026-08-25 pre-pilot defect list): `reminder:uninvoiced-payment-links`
 * had no dedup guard. It is scheduled twice daily (12:00 + 19:00 Asia/Kuwait,
 * app/Console/Kernel.php) and that cadence is correct/wanted — the bug is
 * that nothing stops the SAME agent being reminded twice back-to-back if a
 * cron line is ever duplicated or a run overlaps.
 *
 * Verifies the cache-based dedup guard added to
 * SendAgentUninvoicedPaymentLinkReminders: two runs "in the same window"
 * send exactly one email, and a run after the window has elapsed sends
 * again (the twice-daily cadence itself must not be broken).
 */
class SendAgentUninvoicedPaymentLinkRemindersDedupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->skipPermissionSeeder = true;

        parent::setUp();
    }

    private function makeAgentWithUninvoicedPayment(): array
    {
        $owner = User::factory()->create();
        $company = Company::factory()->create(['user_id' => $owner->id]);
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $owner->id]);

        $agentType = \App\Models\AgentType::firstOrCreate(['id' => 1], ['name' => 'Default']);

        $agentUser = User::factory()->create();
        $agent = Agent::factory()->create([
            'type_id' => $agentType->id,
            'user_id' => $agentUser->id,
            'branch_id' => $branch->id,
            'email' => 'agent-'.uniqid().'@example.test',
        ]);

        // AgentObserver::created() already auto-seeds a default
        // AgentNotificationSetting row (channel=BOTH) for this type the
        // moment the agent above was created. Update it to EMAIL-only so
        // this test never touches the WhatsApp/Resayil send path (out of
        // scope here; Fix 5 is about the dedup guard, not the channels).
        AgentNotificationSetting::updateOrCreate(
            [
                'agent_id' => $agent->id,
                'company_id' => $company->id,
                'notification_type' => AgentNotificationSetting::TYPE_PAYMENT_LINK_UNINVOICED,
            ],
            [
                'channel' => AgentNotificationSetting::CHANNEL_EMAIL,
                'is_active' => true,
            ]
        );

        $client = Client::factory()->create();

        Payment::factory()->create([
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'company_id' => $company->id,
            'invoice_id' => null,
            'account_id' => null,
            'payment_method_id' => null,
            'created_by' => $owner->id,
            'completed' => true,
            'status' => 'completed',
            'is_disabled' => false,
            'payment_date' => now(),
        ]);

        return [$agent, $company];
    }

    public function test_two_runs_in_the_same_window_remind_an_agent_once(): void
    {
        Mail::fake();

        [$agent] = $this->makeAgentWithUninvoicedPayment();

        $this->artisan('reminder:uninvoiced-payment-links', ['--agent' => $agent->id])
            ->assertExitCode(0);

        $this->artisan('reminder:uninvoiced-payment-links', ['--agent' => $agent->id])
            ->assertExitCode(0);

        Mail::assertSent(UninvoicedPaymentLinkReminderMail::class, 1);
    }

    public function test_a_run_after_the_dedup_window_elapses_reminds_again(): void
    {
        // Proves the fix preserves the intended twice-daily cadence (12:00
        // + 19:00, a 7h gap) rather than collapsing it to once-ever like
        // NotifyStaleTaskActionRequests' escalated_at guard does.
        Mail::fake();

        [$agent] = $this->makeAgentWithUninvoicedPayment();

        $this->artisan('reminder:uninvoiced-payment-links', ['--agent' => $agent->id])
            ->assertExitCode(0);

        $this->travel(7)->hours();

        $this->artisan('reminder:uninvoiced-payment-links', ['--agent' => $agent->id])
            ->assertExitCode(0);

        Mail::assertSent(UninvoicedPaymentLinkReminderMail::class, 2);
    }

    public function test_dry_run_is_never_blocked_by_the_dedup_guard(): void
    {
        // A preview must always reflect current state, regardless of when
        // the agent was last actually reminded.
        Mail::fake();

        [$agent] = $this->makeAgentWithUninvoicedPayment();

        $this->artisan('reminder:uninvoiced-payment-links', ['--agent' => $agent->id])
            ->assertExitCode(0);

        Mail::assertSent(UninvoicedPaymentLinkReminderMail::class, 1);

        // Immediately preview again (well within the dedup window) -- must
        // not error and must not itself count as a send.
        $this->artisan('reminder:uninvoiced-payment-links', ['--agent' => $agent->id, '--dry-run' => true])
            ->assertExitCode(0);

        Mail::assertSent(UninvoicedPaymentLinkReminderMail::class, 1);
    }
}
