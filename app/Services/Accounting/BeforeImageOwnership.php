<?php

declare(strict_types=1);

namespace App\Services\Accounting;

/**
 * CT-A9 T3 — who owns a `coa_linkage_changes` before-image, keyed by (subject_table, column_name).
 *
 * ── Why this is now a class ────────────────────────────────────────────────────────────────────
 * `coa_linkage_changes` is a SHARED before-image table, and its own migration says so. CT-A7 added
 * a second writer, CT-A8 a third and made the discriminator the PAIR rather than the table alone,
 * and CT-A9 adds two more — `journal_entries.currency` / `.exchange_rate`
 * (`accounting:repair-currency-label`) and `tasks.total` / `.price`
 * (`accounting:repair-task-fx-conversion`).
 *
 * At three writers the "name the other command" logic was already duplicated in three places, each
 * with its own hand-rolled list, and each list was a little bit wrong about the others. At five it
 * would be wrong in more interesting ways — `BackfillSupplierLeaf::ownerOf()` mapped ANY
 * `journal_entries.*` pair to `accounting:backfill-payable-party`, which stopped being true the
 * moment a second command started writing a different `journal_entries` column. One table, one map.
 *
 * ── What an ownership answer is FOR ────────────────────────────────────────────────────────────
 * It is not access control. Nothing here prevents a write. It exists so an operator who typed the
 * wrong `--rollback` is told WHICH command to type instead, rather than being told only that this
 * was not it — and, more important, so that no command ever restores another's before-images as if
 * they were its own. A partial undo reported as complete is the failure mode every one of these
 * guards exists to prevent.
 */
final class BeforeImageOwnership
{
    /**
     * `subject_table.column_name` -> the artisan command that owns it.
     *
     * Wildcards are deliberately absent. A pair that is not listed is UNKNOWN, and an unknown pair
     * is reported as unknown — guessing an owner is how an operator ends up running an undo against
     * the wrong table.
     *
     * @var array<string, string>
     */
    private const OWNERS = [
        // CT-A7 F2 — party attribution on historical AP ledger lines.
        'journal_entries.type_reference_id' => 'accounting:backfill-payable-party',

        // CT-A9 T3 — the `?? 'USD'` phantom-column label defect.
        'journal_entries.currency' => 'accounting:repair-currency-label',
        'journal_entries.exchange_rate' => 'accounting:repair-currency-label',

        // CT-A9 T3 — the seven un-converted foreign tasks.
        'tasks.total' => 'accounting:repair-task-fx-conversion',
        'tasks.price' => 'accounting:repair-task-fx-conversion',
        'tasks.__operator_supplied_rate__' => 'accounting:repair-task-fx-conversion',

        // CT-A8 — supplier on the payable LEAF, derived from posted evidence.
        'accounts.supplier_id' => 'accounting:backfill-supplier-leaf',
    ];

    /**
     * The command that owns the first recognised pair, or `accounting:coa-linkage` — which owns
     * every classification column of `accounts`/`system_accounts` and is therefore the right
     * fallback for an unrecognised pair on those two tables and the honest default elsewhere.
     *
     * @param  string[]  $pairs  e.g. ['journal_entries.currency']
     */
    public static function commandFor(array $pairs): string
    {
        foreach ($pairs as $pair) {
            if (isset(self::OWNERS[(string) $pair])) {
                return self::OWNERS[(string) $pair];
            }
        }

        return 'accounting:coa-linkage';
    }

    /**
     * The same answer as a copy-pasteable undo line, which is what every caller actually prints.
     *
     * @param  string[]  $pairs
     */
    public static function rollbackHint(array $pairs, string $runId): string
    {
        return 'php artisan '.self::commandFor($pairs).' --rollback='.$runId;
    }
}
