<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Account;

/**
 * CT-A7 ROUND 3, finding **R3-1** — the ONE place in `app/Services/Accounting` allowed to anchor on
 * a control account's NAME, and the only one that does it correctly.
 *
 * ── Why this class exists ──────────────────────────────────────────────────────────────────────
 * `accounts.name` carries **no uniqueness constraint anywhere** — not in the schema, and not in
 * validation: `CoaController` validates it as `required|string|max:255`. An operator can create a
 * second `Accounts Payable`, or rename an existing account to it, in two clicks.
 *
 * `TaskPayablePositionResolver::apSubtreeIds()` anchored with
 * `Account::where('name', 'Accounts Payable')->where('company_id', …)->value('id')` — SINGULAR, and
 * with no `orderBy`, so which of two same-named groups it picked was not even deterministic. An
 * adversarial verifier did it through real HTTP routes: a second `Accounts Payable` under
 * Liabilities, a supplier leaf under that, 700.000 credited — **not in `apSubtreeIds()`, not in
 * `payableAccountIds()`, screen 0.000, ledger 700.000.** So CT-A7-R2-F1's "complete by
 * construction" was really "complete by construction, GIVEN exactly one account of that name per
 * company", which is an invariant nothing enforces.
 *
 * ── Why a shared resolver rather than a local `pluck()` ────────────────────────────────────────
 * The PR was itself carrying THREE handlings of the same lookup, which is the evidence that the
 * codebase does not actually believe the invariant:
 *
 *   - `TaskPayablePositionResolver::apSubtreeIds()`        `->value('id')`   (singular)
 *   - `AccountingController::createBankPayment()`          `->pluck('id')`   (plural — round 2's own F6 fix)
 *   - `AccountingController::createPayableDetail()`        `->first()`       (singular)
 *
 * Making one of them plural would have left the other two and invited a fourth. All of them now go
 * through this class, and a two-sided ratchet
 * ({@see \Tests\Feature\Accounting\ArchitectureTest::test_no_accounting_service_anchors_on_a_control_account_name()})
 * fails the build if any other file under `app/Services/Accounting` anchors on one of these names
 * again.
 *
 * ── This is a LIVE condition, not a hypothetical ───────────────────────────────────────────────
 * A read-only audit of the real charts (2026-09-16) found company **2** carrying two accounts named
 * `Accounts Payable` and two named `Accounts Receivable`, identically on dev and live, with the
 * money on the LEGACY tree (`20002`/`10005`, level 3, `root_id` NULL) and our own canonical codes
 * (`2100`/`1350`, level 2, real `root_id`) sitting empty beside it. The money-bearing side is
 * therefore at a different LEVEL and has a NULL `root_id` — so nothing in this class filters on
 * either: the walk keys on `parent_id` alone, exactly so that the tree holding the balance cannot
 * be missed by an assumption about chart shape. See {@see self::primaryGroupId()} for the numbers.
 *
 * ── What it is NOT ─────────────────────────────────────────────────────────────────────────────
 * It is **not** an endorsement of name anchoring. Every real POSTING target is resolved through
 * {@see AccountResolver}'s purpose codes, and the CT-A6-1 ratchet keeps the report layer off names.
 * This class exists for the one question a purpose code cannot answer — *"which accounts make up
 * this company's accounts-payable STRUCTURE?"* — which is a chart-shape question, not a posting
 * question. It is also not a repo-wide fix: dozens of name-anchored lookups remain in
 * `InvoiceController`, `RefundController`, `AgentController` and elsewhere, which the CT-A6-1
 * ratchet's own docblock already records as a separate, much larger remediation lane.
 */
class NamedAccountGroupResolver
{
    /**
     * The depth cap on the subtree walk. Bounded by the chart's real depth (5 levels on this COA);
     * the guard exists only so a cyclic `parent_id` — which no constraint prevents — cannot spin
     * forever.
     */
    private const MAX_DEPTH = 12;

    /**
     * EVERY account of this company carrying this exact name. Plural by contract, even when there
     * is only one: a caller that takes `[0]` has reintroduced R3-1, and a caller that takes the
     * whole array cannot.
     *
     * Ordered by id so two runs on the same chart return the same array — the old `->value('id')`
     * had no ordering at all, so with two matches it was not even deterministic about WHICH subtree
     * it hid.
     *
     * @return int[]
     */
    public function groupIds(int $companyId, string $name): array
    {
        if ($companyId <= 0 || $name === '') {
            return [];
        }

        return Account::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->where('name', $name)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * The ONE account of this name a single-account caller should use — for the call sites whose
     * question is genuinely "which account do I hang this report off?" rather than "what is the
     * whole structure?".
     *
     * ── Why this is not just `->first()` with an `orderBy` bolted on ────────────────────────────
     * A read-only audit of the real charts (2026-09-16) found company **2** carrying TWO accounts
     * named `Accounts Payable` and TWO named `Accounts Receivable`, identically on dev and live:
     *
     *   | id   | code  | level | root_id | subtree | journal rows | net            |
     *   |------|-------|-------|---------|---------|--------------|----------------|
     *   | 463  | 20002 | 3     | NULL    | 210     | 115          | 14,205.62 Cr   |
     *   | 1305 | 2100  | 2     | 427     | 14      | 0            | empty          |
     *   | 437  | 10005 | 3     | NULL    | 155     | 8            | 579.00 Dr      |
     *   | 1291 | 1350  | 2     | 426     | 22      | 0            | empty          |
     *
     * The strays are not operator error: `2100` and `1350` are OUR canonical codes, seeded
     * alongside company 2's pre-existing legacy tree (`20002`/`10005`) where the money actually
     * lives. Our own chart work created the second tree. Consolidating them is a chart migration
     * and an OWNER decision for the COA design phase — deliberately not attempted here.
     *
     * Every one of the four call-site families the audit found used a bare `->first()` with **no
     * `ORDER BY` anywhere**. They return the money-bearing 463/437 today only because MariaDB's
     * index scan happens to hand back insertion order. An `ANALYZE TABLE`, an index rebuild, an
     * optimizer plan change or a server upgrade flips that with **zero code change**, and company
     * 2's payables screen silently drops 14,205.62 while the trial balance keeps carrying it. That
     * is a live fragility, not a hypothetical.
     *
     * So this method does not merely make the pick deterministic — it makes it CORRECT ON PURPOSE:
     * where several accounts share the name, the one whose subtree actually carries journal
     * movement wins, with the lowest id as a tie-break. On companies 1 and 3 (one group each) it is
     * byte-identical to `->first()`; on company 2 it pins the tree that holds the balance instead
     * of depending on the optimizer.
     *
     * A caller that wants the WHOLE structure must use {@see self::subtreeIds()} instead — picking
     * one group can only ever be right for a question that genuinely has one answer.
     */
    public function primaryGroupId(int $companyId, string $name): ?int
    {
        $groups = $this->groupIds($companyId, $name);

        if ($groups === []) {
            return null;
        }

        if (count($groups) === 1) {
            return $groups[0];
        }

        foreach ($groups as $groupId) {
            $subtree = array_merge([$groupId], $this->descendantsOf($companyId, [$groupId]));

            $hasMovement = \Illuminate\Support\Facades\DB::table('journal_entries')
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->whereIn('account_id', $subtree)
                ->exists();

            if ($hasMovement) {
                return $groupId;
            }
        }

        // Several groups, none with a single posted line: any of them is as right as any other, so
        // return the oldest rather than whatever the optimizer felt like.
        return $groups[0];
    }

    /**
     * Every account BELOW any account of this company named $name — the union of all their
     * subtrees, seeded from {@see self::groupIds()} rather than from a single id.
     *
     * The groups themselves are NOT included; a caller that wants them unions `groupIds()` in (as
     * {@see TaskPayablePositionResolver::apSubtreeIds()} does, because a payable can be posted
     * directly onto a control group on a chart that never split it).
     *
     * `accounts.is_group` is deliberately not consulted — CT-A1 §1.4 measured it wrong on 613
     * accounts (566 flagged as groups with no children, 47 flagged as leaves that have children),
     * the same reason {@see AccountResolver::isLeaf()} derives leaf-ness from the children rather
     * than the flag.
     *
     * @return int[]
     */
    public function descendantIds(int $companyId, string $name): array
    {
        return $this->descendantsOf($companyId, $this->groupIds($companyId, $name));
    }

    /**
     * {@see self::groupIds()} plus {@see self::descendantIds()} — the whole structure, groups
     * included.
     *
     * @return int[]
     */
    public function subtreeIds(int $companyId, string $name): array
    {
        $groups = $this->groupIds($companyId, $name);

        if ($groups === []) {
            return [];
        }

        return array_values(array_unique(array_merge($groups, $this->descendantsOf($companyId, $groups))));
    }

    /**
     * Breadth-first from every id in $frontier at once. One query per LEVEL, not per group, so two
     * same-named groups cost exactly what one did.
     *
     * @param  int[]  $frontier
     * @return int[]
     */
    private function descendantsOf(int $companyId, array $frontier): array
    {
        if ($frontier === []) {
            return [];
        }

        $all = [];
        $seen = $frontier;

        for ($depth = 0; $depth < self::MAX_DEPTH && $frontier !== []; $depth++) {
            $children = Account::query()
                ->withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            // Both diffs matter: `$all` stops a diamond re-adding a node, and `$seen` stops a cycle
            // back onto a seed id from restarting the walk.
            $children = array_values(array_diff($children, $all, $seen));

            if ($children === []) {
                break;
            }

            $all = array_merge($all, $children);
            $seen = array_merge($seen, $children);
            $frontier = $children;
        }

        return $all;
    }
}
