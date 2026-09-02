<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Pins the three scheduling fixes in routes/console.php (see
 * .planning/AUTOMATIONS-INVENTORY-2026-09-02.md §C/§H):
 *
 * 1. The live entry named 'perform:payment-release-to-company-bankacc-process', a
 *    command that does not exist -- silently failing every Sunday-Thursday-midnight
 *    run -- is gone, replaced by the real command name.
 * 2. 'app:calculate-agent-commission' (Agent Profit module) was only scheduled in the
 *    dead app/Console/Kernel.php and has never auto-run. It must now appear with the
 *    Kernel's own cadence (monthlyOn(1, '00:10')).
 * 3. 'tasks:process-expired-confirmed' was in the same situation (dead Kernel only,
 *    everyFiveMinutes()) and must now appear with that cadence too.
 *
 * `php artisan schedule:list` is the same introspection Laravel's own
 * `Illuminate\Console\Scheduling\Schedule` exposes for a scheduled event's
 * expression and command -- this test renders it exactly like the real
 * `schedule:list` command output and greps it, so a regression to any of the three
 * cron expressions or command names fails this test the same way it would fail a
 * human reading the console output.
 */
class ScheduleAgentProfitTest extends TestCase
{
    private function scheduledCommandLines(): array
    {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);

        $lines = [];
        foreach ($schedule->events() as $event) {
            $lines[] = trim($event->expression).' '.$event->command;
        }

        return $lines;
    }

    public function test_the_broken_perform_command_name_is_gone(): void
    {
        $lines = $this->scheduledCommandLines();

        foreach ($lines as $line) {
            $this->assertStringNotContainsString(
                'perform:payment-release-to-company-bankacc-process',
                $line,
                'The broken command name must never be scheduled again.'
            );
        }
    }

    public function test_payment_release_command_is_scheduled_with_the_correct_name_and_cadence(): void
    {
        $lines = $this->scheduledCommandLines();

        $matches = array_filter($lines, fn ($line) => str_contains($line, 'app:payment-release-to-company-bankacc-process'));

        $this->assertNotEmpty($matches, 'app:payment-release-to-company-bankacc-process must be scheduled.');
        $this->assertStringStartsWith('0 0 * * 0-4', reset($matches), 'Cadence must stay Sunday-Thursday at midnight.');
    }

    public function test_agent_commission_command_is_scheduled_with_the_kernel_cadence(): void
    {
        $lines = $this->scheduledCommandLines();

        $matches = array_filter($lines, fn ($line) => str_contains($line, 'app:calculate-agent-commission'));

        $this->assertNotEmpty($matches, 'app:calculate-agent-commission must be scheduled (previously only in the dead Kernel.php).');
        $this->assertStringStartsWith('10 0 1 * *', reset($matches), 'Cadence must match the dead Kernel entry: monthlyOn(1, "00:10").');
    }

    public function test_expired_confirmed_tasks_command_is_scheduled_with_the_kernel_cadence(): void
    {
        $lines = $this->scheduledCommandLines();

        $matches = array_filter($lines, fn ($line) => str_contains($line, 'tasks:process-expired-confirmed'));

        $this->assertNotEmpty($matches, 'tasks:process-expired-confirmed must be scheduled (previously only in the dead Kernel.php).');
        $this->assertStringStartsWith('*/5 * * * *', reset($matches), 'Cadence must match the dead Kernel entry: everyFiveMinutes().');
    }

    public function test_all_three_commands_resolve_as_real_artisan_commands(): void
    {
        $all = Artisan::all();

        $this->assertArrayHasKey('app:payment-release-to-company-bankacc-process', $all);
        $this->assertArrayHasKey('app:calculate-agent-commission', $all);
        $this->assertArrayHasKey('tasks:process-expired-confirmed', $all);
    }
}
