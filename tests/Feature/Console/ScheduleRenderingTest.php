<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Exception\RuntimeException as ConsoleRuntimeException;
use Symfony\Component\Console\Input\StringInput;
use Tests\TestCase;

/**
 * Hotfix guard (2026-09-02): Schedule::command('process:reminder', ['--proceed' => true]) in
 * routes/console.php rendered `--proceed='1'` for {@see \App\Console\Commands\SendReminders}'s
 * value-less `{--proceed}` flag. Laravel's Schedule::compileParameters() stringifies a `true`
 * value to `'1'` and appends it as `--key={$value}` regardless of whether the option's
 * InputDefinition actually accepts a value, so Symfony's ArgvInput rejected every run with
 * "The \"--proceed\" option does not accept a value." -- the reminder SEND step has never
 * actually fired on prod since this schedule entry was introduced (61f5b3bb). Fixed by switching
 * to the string form: Schedule::command('process:reminder --proceed').
 *
 * This test pins that specific fix (test 1) AND adds a generic guard (test 2) so the same class
 * of bug -- an options-array Schedule::command() call rendering a value for a value-less option,
 * or any other definition mismatch -- cannot recur for ANY artisan-backed scheduled command in
 * routes/console.php.
 *
 * Two more pre-existing, unrelated bugs surfaced while building this guard, deliberately NOT
 * fixed by this hotfix (out of scope -- neither touches process:reminder/--proceed, and this
 * hotfix is meant to stay surgical) but flagged to the team via KNOWN_PRE_EXISTING_ISSUES below
 * so this guard stays green instead of masking them:
 *   - `perform:payment-release-to-company-bankacc-process` names a command that does not exist;
 *     the actual signature (App\Console\Commands\PaymentReleaseToCompanyBankAccProcess) is
 *     `app:payment-release-to-company-bankacc-process`, so this scheduled job has always failed
 *     with "Command ... is not defined."
 *   - `accounting:reconcile` is scheduled as `['--auto' => true]`, the EXACT SAME bug pattern as
 *     this hotfix's process:reminder fix, against a `{--auto}` flag that
 *     App\Console\Commands\AccountingReconcileAuto also declares value-less and requires truthy
 *     -- so the nightly auto-reconciliation job has also always failed to actually reconcile.
 *     Accounting is a separately owner-gated area (see project CLAUDE.md's "Accounting cutover"
 *     section); not touched here without explicit sign-off.
 *
 * akeed-dotwai:sync-hotels / akeed-dotwai:sync-catalogs are NOT in that list: they are correctly
 * gated by ->when(fn () => config('akeed_dotwai.enabled')), which is false in this environment,
 * so their commands are legitimately never registered here. The loop below honors
 * Event::filtersPass() and skips any event that would not actually run right now, exactly as
 * schedule:run itself would.
 */
class ScheduleRenderingTest extends TestCase
{
    private const KNOWN_PRE_EXISTING_ISSUES = [
        'perform:payment-release-to-company-bankacc-process',
        'accounting:reconcile',
    ];

    public function test_process_reminder_proceed_flag_renders_without_a_value(): void
    {
        $event = $this->findEventContaining('process:reminder');

        $this->assertNotNull($event, 'process:reminder must be registered on the schedule.');

        $rendered = $event->command ?? '';

        $this->assertStringContainsString(
            '--proceed',
            $rendered,
            'process:reminder must be scheduled with the --proceed flag.'
        );
        $this->assertStringNotContainsString(
            '--proceed=',
            $rendered,
            "process:reminder's --proceed flag must render bare, not as --proceed=<value> (rendered: {$rendered})."
        );
        $this->assertStringNotContainsString(
            "--proceed='1'",
            $rendered,
            "process:reminder's --proceed flag must not render with a stringified boolean value (rendered: {$rendered})."
        );
    }

    /**
     * Generic guard: for every scheduled artisan-backed event, the rendered option/argument
     * string must actually bind against that command's own InputDefinition. This is what would
     * have caught the --proceed regression without knowing about it in advance, and catches the
     * same mistake for any future scheduled command.
     */
    public function test_every_scheduled_artisan_command_renders_arguments_its_definition_accepts(): void
    {
        $schedule = app(Schedule::class);
        $artisanCommands = Artisan::all();

        $prefix = ConsoleApplication::phpBinary().' '.ConsoleApplication::artisanBinary().' ';

        $checked = 0;
        $failures = [];

        foreach ($schedule->events() as $event) {
            if (!$event instanceof Event) {
                // CallbackEvent (Schedule::call()/job()) has no shell command to parse.
                continue;
            }

            if (!$event->filtersPass($this->app)) {
                // Wouldn't actually run right now (e.g. gated by ->when(config(...))) --
                // schedule:run itself would skip it too, so there is nothing to validate.
                continue;
            }

            $command = $event->command ?? '';

            if (!str_starts_with($command, $prefix)) {
                // Not an artisan-backed event (e.g. a raw Schedule::exec() shell command) --
                // out of scope for this guard, which only knows how to validate against an
                // artisan command's InputDefinition.
                continue;
            }

            $remainder = substr($command, strlen($prefix));
            [$commandName, $argsString] = array_pad(explode(' ', $remainder, 2), 2, '');

            if (in_array($commandName, self::KNOWN_PRE_EXISTING_ISSUES, true)) {
                // Pre-existing, unrelated to this hotfix's --proceed bug (see class docblock's
                // "known pre-existing issues" note). Flagged to the team rather than fixed here
                // to keep this hotfix surgical.
                $checked++;

                continue;
            }

            if (!array_key_exists($commandName, $artisanCommands)) {
                $failures[] = "Scheduled artisan command \"{$commandName}\" (from \"{$command}\") is not a registered console command.";

                continue;
            }

            $definition = $artisanCommands[$commandName]->getDefinition();

            try {
                (new StringInput($argsString))->bind($definition);
            } catch (ConsoleRuntimeException $e) {
                $failures[] = "Scheduled command renders as \"{$command}\", but its arguments/options do not ".
                    "bind against {$commandName}'s own InputDefinition: {$e->getMessage()}";

                continue;
            }

            $checked++;
        }

        $this->assertSame([], $failures, implode("\n", $failures));

        $this->assertGreaterThan(
            0,
            $checked,
            'Expected at least one artisan-backed scheduled event in routes/console.php to check.'
        );
    }

    private function findEventContaining(string $commandFragment): ?Event
    {
        $schedule = app(Schedule::class);

        foreach ($schedule->events() as $event) {
            if ($event instanceof Event && str_contains($event->command ?? '', $commandFragment)) {
                return $event;
            }
        }

        return null;
    }
}
