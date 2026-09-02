<?php

declare(strict_types=1);

namespace App\Services\Reminders;

use App\Models\Reminder;
use App\Services\Reminders\Generators\OverdueInvoiceReminderGenerator;
use App\Services\Reminders\Generators\PaymentLinkUninvoicedReminderGenerator;
use App\Services\Reminders\Generators\ReminderGeneratorInterface;
use App\Services\Reminders\Generators\StatementBalanceReminderGenerator;
use App\Services\Reminders\Generators\TicketingDeadlineReminderGenerator;
use InvalidArgumentException;

/**
 * P2.5.I (p2_5-brief.md §P2.5.I): "One command reminder:generate --kind=all|<kind>". Maps a kind
 * string to its {@see ReminderGeneratorInterface} implementation. `commission_unearned` is
 * deliberately absent from {@see self::SWEPT_KINDS} -- it is event-driven only (see
 * App\Events\Accounting\CommissionUnearned), never part of the scheduled sweep; `--kind=
 * commission_unearned` is still accepted (for interface completeness / a clear error path) but
 * returns a zero-count no-op rather than an "unknown kind" failure. `task_unassigned`/
 * `task_uninvoiced`/`custom` are reserved reminder_kind labels only -- the first two are already
 * served by their own standalone, already-scheduled commands (reminder:unassigned-tasks /
 * reminder:uninvoiced-tasks, pre-dating this wave); `custom` is manual-only (ReminderController::
 * store()). None of the three has a generator here.
 */
final class ReminderGeneratorDispatcher
{
    private const SWEPT_KINDS = [
        Reminder::KIND_OVERDUE_INVOICE,
        Reminder::KIND_STATEMENT_BALANCE,
        Reminder::KIND_TICKETING_DEADLINE,
        Reminder::KIND_PAYMENT_LINK_UNINVOICED,
    ];

    public function __construct(
        private readonly OverdueInvoiceReminderGenerator $overdueInvoice,
        private readonly StatementBalanceReminderGenerator $statementBalance,
        private readonly TicketingDeadlineReminderGenerator $ticketingDeadline,
        private readonly PaymentLinkUninvoicedReminderGenerator $paymentLinkUninvoiced,
    ) {}

    /** @return array<string, array{created: int, skipped: int}> keyed by kind actually run. */
    public function run(string $kind, ?int $companyId): array
    {
        if ($kind === 'all') {
            $results = [];
            foreach (self::SWEPT_KINDS as $sweptKind) {
                $results[$sweptKind] = $this->generatorFor($sweptKind)->generate($companyId);
            }

            return $results;
        }

        if ($kind === Reminder::KIND_COMMISSION_UNEARNED) {
            return [$kind => ['created' => 0, 'skipped' => 0]];
        }

        if (! in_array($kind, self::SWEPT_KINDS, true)) {
            throw new InvalidArgumentException("reminder:generate: unknown or non-generatable kind '{$kind}'. Valid: all, ".implode(', ', self::SWEPT_KINDS).', '.Reminder::KIND_COMMISSION_UNEARNED.' (no-op, event-driven).');
        }

        return [$kind => $this->generatorFor($kind)->generate($companyId)];
    }

    private function generatorFor(string $kind): ReminderGeneratorInterface
    {
        return match ($kind) {
            Reminder::KIND_OVERDUE_INVOICE => $this->overdueInvoice,
            Reminder::KIND_STATEMENT_BALANCE => $this->statementBalance,
            Reminder::KIND_TICKETING_DEADLINE => $this->ticketingDeadline,
            Reminder::KIND_PAYMENT_LINK_UNINVOICED => $this->paymentLinkUninvoiced,
            default => throw new InvalidArgumentException("No generator registered for kind '{$kind}'."),
        };
    }
}
