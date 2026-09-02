<?php

namespace Tests\Feature\Accounting\Reminders;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * P2.5.I (p2_5-brief.md §P2.5.I) required test: "scheduler registration test (Artisan::call
 * ('schedule:list') or equivalent shows process:reminder + reminder:generate with
 * withoutOverlapping)". Inspects the live Schedule container's registered events directly rather
 * than parsing schedule:list's console table, so the assertion survives a cosmetic formatting
 * change to that command's output.
 */
class ReminderSchedulerRegistrationTest extends TestCase
{
    public function test_process_reminder_is_scheduled_every_minute_without_overlapping(): void
    {
        $event = $this->findEvent('process:reminder');

        $this->assertNotNull($event, 'process:reminder must be registered on the schedule.');
        $this->assertTrue($event->withoutOverlapping, 'process:reminder must run withoutOverlapping().');
        $this->assertStringContainsString('* * * * *', $event->expression, 'process:reminder must run every minute.');
    }

    public function test_reminder_generate_kinds_are_scheduled_without_overlapping_on_one_server(): void
    {
        foreach (['overdue_invoice', 'statement_balance', 'ticketing_deadline', 'payment_link_uninvoiced'] as $kind) {
            $event = $this->findEvent('reminder:generate', "--kind=\"{$kind}\"");

            $this->assertNotNull($event, "reminder:generate --kind={$kind} must be registered on the schedule.");
            $this->assertTrue($event->withoutOverlapping, "reminder:generate --kind={$kind} must run withoutOverlapping().");
            $this->assertTrue($event->onOneServer, "reminder:generate --kind={$kind} must run onOneServer().");
        }
    }

    /**
     * P2.5.I prod-drift port: reminder:uninvoiced-payment-links and task-action-request:notify-stale
     * (App\Console\Commands\SendAgentUninvoicedPaymentLinkReminders / NotifyStaleTaskActionRequests)
     * must be driven by `php artisan schedule:run` now, not a direct crontab line — see
     * routes/console.php's P2.5.I prod-drift comment block for the deploy-time crontab-line removal
     * this enables.
     */
    public function test_uninvoiced_payment_links_reminder_is_scheduled_without_overlapping(): void
    {
        $event = $this->findEvent('reminder:uninvoiced-payment-links');

        $this->assertNotNull($event, 'reminder:uninvoiced-payment-links must be registered on the schedule.');
        $this->assertTrue($event->withoutOverlapping, 'reminder:uninvoiced-payment-links must run withoutOverlapping().');
        $this->assertStringContainsString('9,16', $event->expression, 'reminder:uninvoiced-payment-links must run at 09:00/16:00 (the audited prod cadence).');
    }

    public function test_task_action_request_notify_stale_is_scheduled_hourly_without_overlapping(): void
    {
        $event = $this->findEvent('task-action-request:notify-stale');

        $this->assertNotNull($event, 'task-action-request:notify-stale must be registered on the schedule.');
        $this->assertTrue($event->withoutOverlapping, 'task-action-request:notify-stale must run withoutOverlapping().');
        $this->assertStringContainsString('0 * * * *', $event->expression, 'task-action-request:notify-stale must run hourly (the audited prod cadence).');
    }

    private function findEvent(string $commandFragment, ?string $alsoContains = null): ?\Illuminate\Console\Scheduling\Event
    {
        $schedule = app(Schedule::class);

        foreach ($schedule->events() as $event) {
            if (str_contains($event->command ?? '', $commandFragment)
                && ($alsoContains === null || str_contains($event->command ?? '', $alsoContains))) {
                return $event;
            }
        }

        return null;
    }
}
