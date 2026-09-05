<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * accounting-builds Wave 3 lane I item A3 (T2–T4 §12 note E): pins `fixed-assets:depreciate`'s
 * schedule registration (routes/console.php) — cadence, overlap guard, and that the
 * `--all-companies` flag is actually present on the registered event.
 *
 * Follows {@see \Tests\Feature\Accounting\Reminders\ReminderSchedulerRegistrationTest}'s own house
 * pattern: inspect the live Schedule container's registered events directly (never parse
 * `schedule:list`'s console table), so the assertions survive a cosmetic formatting change.
 *
 * Scope note (coordinator update, 2026-09-02): the flag's RENDERED FORM — whether
 * `--all-companies` compiles bare or with a `="1"` value suffix, the specific bug this item was
 * originally scoped to catch (Schedule::compileParameters() compiles the associative
 * `['--all-companies' => true]` array form to `--all-companies="1"`, which
 * FixedAssetsDepreciate's value-less `{--all-companies}` option — no `=` in its own `$signature`
 * — then rejects) — is already fixed on the phase branch (commit 24a8e65f, string form
 * `Schedule::command('fixed-assets:depreciate --all-companies')`) and pinned by the generic
 * rendering guard plus its own explicit test in `tests/Feature/Console/ScheduleRenderingTest.php`
 * (`test_fixed_assets_depreciate_all_companies_flag_renders_without_a_value`). This file does not
 * duplicate that assertion (and deliberately does not touch routes/console.php — that fix lands
 * from the phase branch merge, not from this lane) — it only pins the cadence/overlap-guard shape
 * plus flag PRESENCE, which the rendering guard does not cover.
 */
class FixedAssetsDepreciateSchedulerRegistrationTest extends TestCase
{
    public function test_fixed_assets_depreciate_is_scheduled_monthly_without_overlapping_with_the_all_companies_flag(): void
    {
        $event = $this->findEvent('fixed-assets:depreciate');

        $this->assertNotNull($event, 'fixed-assets:depreciate must be registered on the schedule.');
        $this->assertTrue($event->withoutOverlapping, 'fixed-assets:depreciate must run withoutOverlapping().');
        // monthlyOn(1, '00:30') compiles to this cron expression.
        $this->assertStringContainsString('30 0 1 * *', $event->expression, 'fixed-assets:depreciate must run at 00:30 on the 1st of the month.');
        $this->assertStringContainsString('--all-companies', $event->command ?? '', 'fixed-assets:depreciate must be scheduled with the --all-companies flag (every company that has at least one fixed asset).');
    }

    private function findEvent(string $commandFragment): ?\Illuminate\Console\Scheduling\Event
    {
        $schedule = app(Schedule::class);

        foreach ($schedule->events() as $event) {
            if (str_contains($event->command ?? '', $commandFragment)) {
                return $event;
            }
        }

        return null;
    }
}
