<?php

declare(strict_types=1);

namespace App\Services\Onboarding\Scope;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CD-PORT — **the one gate that replaces five fixes.**
 *
 * ── Read this before changing anything in this namespace ────────────────────────────────────────
 * **This pipeline's row attribution is NOT safe on an application database shared with another
 * live company.** That is not a caveat, it is the finding of two independent adversarial
 * verification rounds, and this class is what enforces it:
 *
 *   * **Round 1** decided ownership by an id band. Four declared tables have no `company_id`
 *     column, so for them the band WAS the scope — and `legacy:scope --apply` raises exactly those
 *     counters to the floor, so every row the dev application mints afterwards lands inside it.
 *     `legacy:unload` deleted City Travelers rows and reported success, for a company id that had
 *     never existed.
 *   * **Round 2** replaced the band with attribution rules — `supplier_companies.company_id`,
 *     `companies/branches/agents .user_id`. Those are REACHABILITY rules, and reachability is not
 *     ownership. Round 1 could only reach the band; **round 2 deleted `suppliers` id 5**, a
 *     supplier linked to both City Travelers and the legacy company, and took company 1's own
 *     `supplier_companies` row with it by `ON DELETE CASCADE` — a row that was never in the ledger.
 *     `suppliers` has eleven inbound foreign keys on this schema.
 *
 * Two rounds, two different routes into another company's data. The owner's decision
 * (2026-09-16, superseding O-1) is therefore that **Como runs in its own copy of the development
 * database** — the sibling-schema option this phase's own §1.1 originally recommended. On a
 * dedicated sandbox the entire class of defect disappears: nothing is shared, so nothing can be
 * mis-attributed, and the undo of record is `DROP DATABASE`.
 *
 * ── What this gate is, and why it is a marker and not a name check ──────────────────────────────
 * A gate that merely refused a hard-coded list of database names would be defeated by the next
 * schema somebody creates. This one requires TWO deliberate, independent acts, neither of which a
 * shared database can perform by accident:
 *
 *   1. **`LEGACY_SANDBOX_DATABASE` must be set AND must equal the live application database name.**
 *     The working development site's `.env` does not carry this key at all. Copying the sandbox's
 *     `.env` onto the dev site does not help: the recorded name then disagrees with the live one.
 *   2. **A marker row must exist in `ct_legacy_sandbox`, in the application database itself, and
 *     must name the schema it was stamped for.** So a sandbox that is later dumped and restored
 *     under a different name refuses until somebody re-stamps it deliberately — the marker travels
 *     with the data and stops being true the moment the data moves.
 *
 * The marker is created only by `legacy:sandbox --mark`, which passes check 1 before it writes
 * anything. Marking a shared database therefore requires an operator to set an env var naming that
 * shared database and then run a command whose entire output says what it is doing. That is the
 * bar: not "unlikely", but "impossible by accident".
 *
 * Every `legacy:*` write command and `legacy:unload` call {@see self::assertSandbox()} before their
 * first statement.
 */
final class LegacySandboxGuard
{
    public const MARKER_TABLE = 'ct_legacy_sandbox';

    /**
     * @throws LegacyScopeRefused
     */
    public function assertSandbox(): void
    {
        $live = (string) DB::connection()->getDatabaseName();
        $declared = config('legacy_pilot.ct_scope.sandbox_database');

        // ── R4-1, FIRST, before anything else ────────────────────────────────────────────────
        // This loop used to live ONLY in mark(), and assertSandbox() — the method every
        // destructive command actually calls — never consulted it. Two routes walked straight
        // through the belt-and-braces as a result:
        //
        //   A6: the live name is on never_sandbox_databases, but env and marker agree -> OPEN.
        //   A8: the marker row is inserted BY HAND rather than via mark() -> OPEN. And the marker
        //       migration now puts `ct_legacy_sandbox` on EVERY database including production,
        //       while `token` is verified against nothing, so mark() need never be called at all.
        //
        // Which left exactly one `.env` line and one `INSERT` between this pipeline and
        // `citycomm_city-tour-test`. The check belongs on the method that guards the writes, and
        // it belongs first: a forbidden name is refused before the question of what any env var or
        // marker row claims is even asked.
        $this->assertNotForbidden($live);

        if (! is_string($declared) || trim($declared) === '') {
            throw new LegacyScopeRefused(
                'Refused: LEGACY_SANDBOX_DATABASE is not set, so this database has not been declared '.
                'a legacy sandbox. This pipeline\'s row attribution is NOT safe on a database shared '.
                'with another live company — two adversarial verification rounds found two different '.
                'routes into City Travelers data — so it will only run against a dedicated copy. '.
                'See App\\Services\\Onboarding\\Scope\\LegacySandboxGuard.'
            );
        }

        if ($declared !== $live) {
            throw new LegacyScopeRefused(
                "Refused: LEGACY_SANDBOX_DATABASE names '{$declared}' but this connection resolves to ".
                "'{$live}'. The two must agree. A sandbox .env copied onto another site does not make ".
                'that site a sandbox.'
            );
        }

        if (! Schema::hasTable(self::MARKER_TABLE)) {
            throw new LegacyScopeRefused(
                "Refused: database '{$live}' carries no `".self::MARKER_TABLE.'` marker. Run '.
                '`php artisan legacy:sandbox --mark` on the sandbox, and nowhere else. The marker is '.
                'deliberately a second, independent act: an env var alone could be copied.'
            );
        }

        $marker = DB::table(self::MARKER_TABLE)->orderByDesc('id')->first();

        if ($marker === null) {
            throw new LegacyScopeRefused(
                "Refused: `".self::MARKER_TABLE."` exists in '{$live}' but is empty. A table with no ".
                'marker row is not a declaration; re-run `php artisan legacy:sandbox --mark`.'
            );
        }

        if ((string) $marker->database_name !== $live) {
            throw new LegacyScopeRefused(
                "Refused: the sandbox marker in '{$live}' was stamped for '{$marker->database_name}'. ".
                'This schema is a COPY of a sandbox, not a sandbox — which is exactly the case the '.
                'marker exists to catch, because a restored dump carries the marker with it. Re-stamp '.
                'it deliberately if that is really what you want.'
            );
        }
    }

    /**
     * The forbidden-name check, in one place, called by BOTH {@see self::assertSandbox()} and
     * {@see self::mark()}.
     *
     * It is a belt-and-braces over the marker mechanism, not the mechanism itself — but it is the
     * belt that catches the single most damaging typo, and R4-1 showed what happens when it is
     * fastened to only one of the two methods that need it.
     *
     * @throws LegacyScopeRefused
     */
    private function assertNotForbidden(string $live): void
    {
        /** @var list<string> $forbidden */
        $forbidden = array_map('strval', (array) config('legacy_pilot.ct_scope.never_sandbox_databases', []));

        foreach ($forbidden as $name) {
            if ($name !== '' && $live === $name) {
                throw new LegacyScopeRefused(
                    "Refused: '{$live}' is on legacy_pilot.ct_scope.never_sandbox_databases. The ".
                    'working development site and the live site are never sandboxes, whatever any '.
                    'env var says and whatever any marker row claims — including a marker row '.
                    'inserted by hand rather than through `legacy:sandbox --mark`.'
                );
            }
        }
    }

    public function isSandbox(): bool
    {
        try {
            $this->assertSandbox();

            return true;
        } catch (LegacyScopeRefused) {
            return false;
        }
    }

    /**
     * Stamp the marker. Passes check 1 (the env declaration) before writing anything, so this
     * command cannot be the thing that accidentally declares a shared database.
     *
     * @return array{database:string, token:string}
     *
     * @throws LegacyScopeRefused
     */
    public function mark(string $note = ''): array
    {
        $live = (string) DB::connection()->getDatabaseName();
        $declared = config('legacy_pilot.ct_scope.sandbox_database');

        if (! is_string($declared) || $declared !== $live) {
            throw new LegacyScopeRefused(
                'Refused to stamp a sandbox marker: LEGACY_SANDBOX_DATABASE is '.
                (is_string($declared) && $declared !== '' ? "'{$declared}'" : 'unset').
                " but this connection resolves to '{$live}'. Set the env var on the sandbox's own ".
                '.env, to the sandbox\'s own schema name, and run this there.'
            );
        }

        // Kept here as well as in assertSandbox(): stamping is a separate act from running, and
        // both must refuse. See assertSandbox()'s own note for why having it ONLY here was the
        // R4-1 defect.
        $this->assertNotForbidden($live);

        if (! Schema::hasTable(self::MARKER_TABLE)) {
            throw new LegacyScopeRefused(
                "Refused: `".self::MARKER_TABLE."` does not exist in '{$live}'. Run `php artisan ".
                'migrate` first. This command deliberately does NOT create the table: DDL implicitly '.
                'commits in MySQL, so a guard that built its own table as a side effect of arming '.
                'itself would silently invalidate whatever transaction it was called inside.'
            );
        }

        $token = bin2hex(random_bytes(16));

        DB::table(self::MARKER_TABLE)->insert([
            'database_name' => $live,
            'token' => $token,
            'note' => $note !== '' ? $note : null,
            'marked_at' => now(),
        ]);

        return ['database' => $live, 'token' => $token];
    }
}
