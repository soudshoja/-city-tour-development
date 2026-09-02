<?php

declare(strict_types=1);

namespace App\Services\Reminders\Generators;

/**
 * P2.5.I (p2_5-brief.md §P2.5.I). One implementation per reminder_kind that
 * {@see \App\Console\Commands\GenerateReminders} ("reminder:generate --kind=") sweeps. Every
 * implementation computes eligibility from ledger open items (never `accounts.actual_balance`),
 * writes `pending` rows with a `dedupe_key` so a second run in the same eligibility window is a
 * no-op, and returns a plain count so the command can report a total.
 */
interface ReminderGeneratorInterface
{
    /** The reminder_kind this generator writes, one of {@see \App\Models\Reminder}'s KIND_* constants. */
    public function kind(): string;

    /** @return array{created: int, skipped: int} */
    public function generate(?int $companyId): array;
}
