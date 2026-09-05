<?php

namespace Tests\Feature\Accounting\Reminders;

use App\Mail\UninvoicedPaymentLinkReminderMail;
use App\Models\Agent;
use App\Models\AgentNotificationSetting;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskActionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * P2.5.I prod-drift port (p2_5-brief.md §P2.5.I "port ... as-is"; routes/console.php's own
 * P2.5.I prod-drift comment block). Proves the two ported commands --
 * App\Console\Commands\SendAgentUninvoicedPaymentLinkReminders and
 * App\Console\Commands\NotifyStaleTaskActionRequests -- actually run against this repo's schema
 * (additive migration 2026_08_31_130000_create_task_action_requests_table, the new
 * AgentNotificationSetting::TYPE_PAYMENT_LINK_UNINVOICED constant) and behave as ported, not just
 * that the class file exists.
 */
class ProdDriftRemindersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * NOTE: intentionally does NOT hardcode agent_type.id => 1 (unlike some other tests in this
     * suite) -- RefreshDatabase rolls back each test's transaction, but MySQL/InnoDB does not
     * roll back the table's AUTO_INCREMENT counter, so a fixed id only survives the first test in
     * the class. Look the row up/create it by name instead and pass its real id through.
     */
    private function agentTypeId(): int
    {
        return AgentType::firstOrCreate(['name' => 'type-1'])->id;
    }

    private function makeAgent(): array
    {
        $company = Company::factory()->create();
        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);
        $agentUser = User::factory()->create();
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => $agentUser->id,
            'type_id' => $this->agentTypeId(),
        ]);

        return [$company, $branch, $agent];
    }

    // -------------------- SendAgentUninvoicedPaymentLinkReminders --------------------

    public function test_uninvoiced_payment_links_dry_run_lists_eligible_payments_without_sending(): void
    {
        [$company, , $agent] = $this->makeAgent();
        $client = Client::factory()->create();
        $payment = Payment::factory()->completed()->create([
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'company_id' => $company->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => null,
            'is_disabled' => false,
            'payment_date' => now()->subDays(3),
        ]);

        Mail::fake();
        Http::fake();

        $this->artisan('reminder:uninvoiced-payment-links', ['--dry-run' => true])
            ->assertExitCode(0);

        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_uninvoiced_payment_links_sends_email_when_channel_is_email(): void
    {
        [$company, , $agent] = $this->makeAgent();
        $client = Client::factory()->create();
        Payment::factory()->completed()->create([
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'company_id' => $company->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => null,
            'is_disabled' => false,
            'payment_date' => now()->subDays(3),
        ]);

        // AgentObserver::created() (prod-drift commit 254bb45a8) now auto-seeds a default
        // TYPE_PAYMENT_LINK_UNINVOICED row (channel=both, is_active=true) for every new agent --
        // updateOrCreate so this test's explicit channel/is_active override wins instead of
        // colliding with that seeded row on the agent_notif_unique key.
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

        Mail::fake();

        $this->artisan('reminder:uninvoiced-payment-links')->assertExitCode(0);

        Mail::assertSent(UninvoicedPaymentLinkReminderMail::class, function ($mail) use ($agent) {
            return $mail->agent->id === $agent->id && count($mail->payments) === 1;
        });
    }

    public function test_uninvoiced_payment_links_skips_agent_when_setting_is_inactive(): void
    {
        [$company, , $agent] = $this->makeAgent();
        $client = Client::factory()->create();
        Payment::factory()->completed()->create([
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'company_id' => $company->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => null,
            'is_disabled' => false,
            'payment_date' => now()->subDays(3),
        ]);

        // See the updateOrCreate note above -- AgentObserver::created() already seeded a row on
        // this same unique key when $agent was created.
        AgentNotificationSetting::updateOrCreate(
            [
                'agent_id' => $agent->id,
                'company_id' => $company->id,
                'notification_type' => AgentNotificationSetting::TYPE_PAYMENT_LINK_UNINVOICED,
            ],
            [
                'channel' => AgentNotificationSetting::CHANNEL_EMAIL,
                'is_active' => false,
            ]
        );

        Mail::fake();

        $this->artisan('reminder:uninvoiced-payment-links')->assertExitCode(0);

        Mail::assertNothingSent();
    }

    public function test_uninvoiced_payment_links_ignores_already_invoiced_and_disabled_payments(): void
    {
        [$company, , $agent] = $this->makeAgent();
        $client = Client::factory()->create();
        // Already invoiced -- must not count.
        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id]);
        Payment::factory()->completed()->create([
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'company_id' => $company->id,
            'invoice_id' => $invoice->id,
            'account_id' => null,
            'created_by' => null,
            'is_disabled' => false,
            'payment_date' => now()->subDays(3),
        ]);
        // Disabled -- must not count.
        Payment::factory()->completed()->create([
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'company_id' => $company->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => null,
            'is_disabled' => true,
            'payment_date' => now()->subDays(3),
        ]);
        // Outside the --days window -- must not count.
        Payment::factory()->completed()->create([
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'company_id' => $company->id,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => null,
            'is_disabled' => false,
            'payment_date' => now()->subDays(90),
        ]);

        Mail::fake();

        $this->artisan('reminder:uninvoiced-payment-links', ['--days' => 30])->assertExitCode(0);

        Mail::assertNothingSent();
    }

    // -------------------- NotifyStaleTaskActionRequests --------------------

    private function makeTaskActionRequest(array $overrides = []): TaskActionRequest
    {
        $typeId = $this->agentTypeId();
        $company = Company::factory()->create();
        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);
        $ownerAgent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => User::factory()->create()->id, 'type_id' => $typeId]);
        $actorAgent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => User::factory()->create()->id, 'type_id' => $typeId]);
        $client = Client::factory()->create();
        $originalTask = Task::factory()->create(['company_id' => $company->id]);
        $newTask = Task::factory()->create(['company_id' => $company->id]);

        return TaskActionRequest::create(array_merge([
            'request_token' => TaskActionRequest::generateToken(),
            'task_id' => $newTask->id,
            'original_task_id' => $originalTask->id,
            'client_id' => $client->id,
            'owner_agent_id' => $ownerAgent->id,
            'actor_agent_id' => $actorAgent->id,
            'action_type' => TaskActionRequest::ACTION_REFUND,
            'status' => TaskActionRequest::STATUS_PENDING,
        ], $overrides));
    }

    public function test_notify_stale_dry_run_lists_but_does_not_escalate(): void
    {
        $req = $this->makeTaskActionRequest();
        $req->created_at = now()->subHours(60);
        $req->saveQuietly();

        $this->artisan('task-action-request:notify-stale', ['--dry-run' => true])
            ->assertExitCode(0);

        $this->assertNull($req->fresh()->escalated_at);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_notify_stale_escalates_pending_requests_older_than_48_hours(): void
    {
        $admin = User::factory()->create(['role_id' => Role::ADMIN, 'email' => 'admin@akeed.test']);

        $req = $this->makeTaskActionRequest();
        $req->created_at = now()->subHours(60);
        $req->saveQuietly();

        Mail::fake();

        $this->artisan('task-action-request:notify-stale')->assertExitCode(0);

        $req->refresh();
        $this->assertNotNull($req->escalated_at);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'task_action_request',
        ]);
        // Mail::raw() (used by TaskActionRequestNotifier::fireEmail) is a documented no-op under
        // Mail::fake() (Illuminate\Support\Testing\Fakes\MailFake::raw() has an empty body -- it
        // is not trackable via assertSent), so the in-app Notification row above is this test's
        // real proof of "notifies admin + accountant"; Mail::fake() here only prevents a real
        // SMTP attempt from a *different* code path (none exists in this flow) from erroring out.
    }

    public function test_notify_stale_skips_rows_younger_than_48_hours(): void
    {
        $req = $this->makeTaskActionRequest();
        $req->created_at = now()->subHours(10);
        $req->saveQuietly();

        $this->artisan('task-action-request:notify-stale')->assertExitCode(0);

        $this->assertNull($req->fresh()->escalated_at);
    }

    public function test_notify_stale_never_re_escalates_an_already_escalated_row(): void
    {
        $req = $this->makeTaskActionRequest(['escalated_at' => now()->subDay()]);
        $req->created_at = now()->subHours(60);
        $req->saveQuietly();

        Mail::fake();

        $this->artisan('task-action-request:notify-stale')->assertExitCode(0);

        Mail::assertNothingSent();
        $this->assertDatabaseCount('notifications', 0);
    }
}
