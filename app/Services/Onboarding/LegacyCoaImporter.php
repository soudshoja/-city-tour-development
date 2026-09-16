<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\Account;
use App\Models\SystemAccount;
use App\Services\Accounting\AccountResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * legacy-ledger-pilot LP1.1 — imports the staged tblAccount tree
 * (legacy_pilot.stg_account) into the app's `accounts` table for one
 * company, recorded 1:1 in legacy_pilot.legacy_acc_map.
 *
 * ── Classification is POSITIONAL ────────────────────────────────────────
 * A row's A/L/I/E class is the AccType of the ROOT its parent chain ends
 * at — never the row's own declared AccType, and (a fortiori) never
 * AccTransType, which is documented dirty (ref 14 Q9). The real export
 * carries two rows whose declared type contradicts their position
 * ("TRANSFER TO RESERVES", declared E, sitting under the L-rooted
 * APPROPRIATIONS tree, and one A-declared party leaf under an L root):
 * classifying those by their declared type would put a P&L report_type on
 * a balance-sheet subtree. They are classified by position and FLAGGED
 * (legacy_acc_map.type_dirt) rather than silently absorbed.
 * legacy_acc_map.account_type_code still records the DECLARED type so the
 * plan's 429/454/332/136 split reconciles exactly.
 *
 * ── Roots are renamed to the canonical five ─────────────────────────────
 * App\Services\TrialBalanceService decides debit-normal vs credit-normal
 * from the NAME of an account's root, matched case-sensitively against
 * ['Assets','Expenses','Liabilities','Equity','Income']. The legacy roots
 * (ASSETS/LIABILITIES/INCOMES/EXPENSES/APPROPRIATIONS/EQUITY) match none
 * of them, so importing them verbatim silently inverts the sign of every
 * asset and expense balance. config('legacy_pilot.root_canonical_names')
 * maps each legacy root AccCode to its canonical name; an unmapped root
 * REFUSES the import rather than guessing. Two legacy roots share the
 * canonical name 'Equity', so the second becomes a level-2 group under the
 * first — which is also how six legacy roots become the five the owner
 * walkthrough expects.
 *
 * ── Integrity: all-or-nothing ───────────────────────────────────────────
 * The whole import runs inside ONE transaction on the app connection and
 * REFUSES (rolling everything back, importing nothing) on: a
 * ParentAccID_FK that names no staged row; a duplicate AccCode; an
 * AccLevel inconsistent with the legacy parent chain; an unmappable root.
 * A half-imported chart is worse than no chart.
 *
 * ── Party leaves pool, but keep their identity ──────────────────────────
 * Per PLAN.md §4 LP1.1, party leaves (customer group prefixes, payable
 * group prefixes, or any leaf a tblPartner account FK points at) and the
 * eleven yearly "Profit & Loss Account <year>" leaves get NO Account row
 * of their own; they fold onto a pool target recorded in legacy_acc_map.
 * Pooling is only REVERSIBLE — i.e. per-party AR/AP parity (109 customer /
 * 75 supplier leaves at 2025-12-31) is only reproducible — if the
 * leaf → party identity survives the fold, so every pooled party leaf also
 * records party_id/party_role, resolved from stg_partner. See
 * LegacyPartyMapper and Tests\Feature\Legacy\LegacyPartyIdentityTest.
 *
 * The pool target is resolved, in order: (a) the company's already-mapped
 * purpose (RECEIVABLE_CONTROL / PAYABLE_CONTROL / RETAINED_EARNINGS) when
 * LP1.2 has already run AND it points at a structural leaf; (b) failing
 * that, the single common structural parent every leaf on that side shares
 * within the just-imported legacy tree. Never a guess — if neither
 * resolves, the leaves land as 'unclassified' and are reported, not
 * silently dropped.
 *
 * ── COORDINATOR RULING R2 (2026-09-07, LP1c): synthetic control leaves ──
 * Step (b) hands back a GROUP, and a group is not postable. That is not a
 * corner case on the real chart: PAYABLE_CONTROL has no legacy parameter at
 * all (PayableControlAcc is blank in the export, verified in staging run
 * #3) and the legacy AP anchor named by
 * config('legacy_pilot.payable_group_prefixes') is a group, so every pooled
 * supplier line had nowhere postable to land. R2's answer: for the two
 * CONTROL purposes only, mint ONE synthetic pooled control leaf under that
 * group — named by self::SYNTHETIC_CONTROL_NAMES, coded from
 * config('legacy_pilot.import.synthetic_code_range') (never a literal) —
 * map the purpose at it, and record it in legacy_acc_map with
 * resolution='synthetic' so LP4 parity can tell a minted node from an
 * imported one. RECEIVABLE_CONTROL takes the same path only when its own
 * parameter target is not a leaf (on the real export it IS a leaf,
 * 2520004, so nothing is minted for it). RETAINED_EARNINGS is deliberately
 * excluded: its rule is SystemPurposeMapper's single-leaf descent, because
 * the yearly P&L folds must land on the chart's own retained-earnings leaf
 * for LP6's year-end close to reconcile against it.
 *
 * ── Frozen accounts ─────────────────────────────────────────────────────
 * tblAccount.IsFreeze is PRESERVED (accounts.disabled, plus
 * legacy_acc_map.legacy_is_freeze), never dropped and never silently
 * cleared. 21 accounts are frozen in the real export. Note for LP3: the
 * posting engine refuses to post to a disabled account
 * (FrozenAccountException), so `legacy:audit-masters` reports any frozen
 * account carrying in-window activity as a fail — that set must be
 * resolved by owner decision before the replay, not by quietly unfreezing
 * accounts here.
 *
 * Never writes accounts.actual_balance to anything other than the literal
 * 0.00 the NOT NULL column requires at INSERT time — see
 * tests/Feature/Legacy/AccountingBalanceColumnRatchetTest.php.
 */
final class LegacyCoaImporter
{
    /**
     * COORDINATOR RULING R2 (2026-09-07, LP1c): the names of the synthetic
     * pooled control leaves this importer mints when a control purpose has no
     * legacy LEAF to pool onto. The payable name is fixed by the ruling
     * verbatim; the receivable one mirrors it. Only the CODE is configurable
     * (config('legacy_pilot.import.synthetic_code_range')) — a name is an
     * identity a human reads in the COA screen and must not drift per
     * installation, whereas a code can collide with a legacy chart's own
     * numbering and therefore must be movable.
     *
     * @var array<string, string>
     */
    private const SYNTHETIC_CONTROL_NAMES = [
        'PAYABLE_CONTROL' => 'Suppliers Control (legacy pooled)',
        'RECEIVABLE_CONTROL' => 'Customers Control (legacy pooled)',
    ];

    /**
     * @return array{created:int,pooled_receivable:int,pooled_payable:int,folded_retained_earnings:int,unclassified:int,type_dirt:int,frozen:int,synthetic_controls:int}
     */
    public function import(int $companyId, ?\Closure $afterStructuralPhase = null): array
    {
        LegacyPathGuard::assertQuarantinedConnection('legacy_pilot');

        $rows = DB::connection('legacy_pilot')->table('stg_account')
            ->orderBy('acclevel')
            ->orderBy('acc_id')
            ->get();

        if ($rows->isEmpty()) {
            throw new RuntimeException('stg_account is empty — run legacy:load first.');
        }

        $byId = [];

        foreach ($rows as $row) {
            $byId[(int) $row->acc_id] = $row;
        }

        $this->assertTreeIntegrity($rows, $byId);

        $partnerAccountIds = $this->partnerAccountIds();
        $partyByAccId = $this->partyByAccountId();
        $childCounts = $this->childCounts($rows);

        // Positional class per row, computed once from the parent chain.
        $positionType = [];

        foreach ($rows as $row) {
            $positionType[(int) $row->acc_id] = $this->rootOf($row, $byId);
        }

        // LP1d. The structural anchor each pooled side falls back on when no
        // purpose names a leaf: the SHALLOWEST staged account whose AccGroup
        // carries that side's configured group prefix. See
        // prefixAnchorAccIds() and resolvePoolTarget() for why the older
        // "single common parent" rule could never fire on a real chart.
        $prefixAnchors = [
            'RECEIVABLE_CONTROL' => $this->prefixAnchorAccIds($rows, (array) config('legacy_pilot.customer_group_prefixes', [])),
            'PAYABLE_CONTROL' => $this->prefixAnchorAccIds($rows, (array) config('legacy_pilot.payable_group_prefixes', [])),
        ];

        return DB::transaction(function () use ($rows, $childCounts, $partnerAccountIds, $partyByAccId, $positionType, $prefixAnchors, $companyId, $afterStructuralPhase) {
            $stats = [
                'created' => 0, 'pooled_receivable' => 0, 'pooled_payable' => 0,
                'folded_retained_earnings' => 0, 'unclassified' => 0, 'type_dirt' => 0, 'frozen' => 0,
                'synthetic_controls' => 0,
            ];

            /** @var array<int, Account> $created legacy Acc_ID => created Account */
            $created = [];
            /** @var array<string, Account> $canonicalRoots canonical root name => Account */
            $canonicalRoots = [];
            $customerLeafParents = [];
            $payableLeafParents = [];
            $retainedEarningsLeafParents = [];
            $partyRows = [];

            // Phase 1: every ordinary leaf/group.
            foreach ($rows as $row) {
                $accId = (int) $row->acc_id;
                $accGroup = (string) ($row->accgroup ?? '');
                $accName = (string) ($row->accname ?? '');

                // A group/header node can carry the SAME AccGroup prefix as
                // the leaves under it -- only a true LEAF (no rows point
                // ParentAccID_FK at it) is ever a candidate for party
                // pooling. The control group's own header row is an ordinary
                // structural account like any other.
                $isLeaf = ($childCounts[$accId] ?? 0) === 0;

                $isPartnerLeaf = $isLeaf && in_array($accId, $partnerAccountIds, true);

                // O8-refunds. A leaf under an EXCLUDED group never pools,
                // whatever else matches: 206010300 (REFUNDS PAYABLE, per
                // branch) starts with the '206' payable prefix but is a
                // structural liability leaf, not a party. Pooling it would
                // break the 75-leaf AP anchor and the two-step refund model.
                $isPoolExcluded = $this->matchesGroupPrefix($accGroup, (array) config('legacy_pilot.pool_excluded_group_prefixes', []));

                // LP1e / R-arleaves: the RECEIVABLE side now carries the same
                // partner-FK requirement O8 put on the payable side. Six
                // customer-prefix leaves in the real export have no tblPartner
                // FK at all; pooling them produced six control-account
                // positions that LP4 check 3 cannot decompose per party, which
                // is what made `legacy:import-coa` exit 1 in staging run #5
                // ("6 pooled party leaf/leaves carry no party_id"). They are
                // ordinary GL leaves and import `direct`.
                $isCustomerLeaf = $isLeaf && ! $isPoolExcluded
                    && $this->matchesGroupPrefix($accGroup, (array) config('legacy_pilot.customer_group_prefixes', []))
                    && (! config('legacy_pilot.receivable_pooling_requires_partner_fk', true) || $isPartnerLeaf);

                // O8-refunds, general form: a payable-side leaf pools only
                // if a tblPartner role FK actually points at it. 83 of the
                // 350 '206%' leaves in the real export have none -- named
                // airlines, cargo payables, advances, refunds payable --
                // and every one of them keeps its own account.
                $isPayableLeaf = $isLeaf && ! $isPoolExcluded
                    && $this->matchesGroupPrefix($accGroup, (array) config('legacy_pilot.payable_group_prefixes', []))
                    && (! config('legacy_pilot.payable_pooling_requires_partner_fk', true) || $isPartnerLeaf);

                $isPartnerLeaf = $isPartnerLeaf && ! $isPoolExcluded;

                if ($this->looksLikeYearlyRetainedEarnings($accName)) {
                    $retainedEarningsLeafParents[] = $row->parentaccid_fk !== null ? (int) $row->parentaccid_fk : null;
                    $partyRows[] = ['row' => $row, 'kind' => 're'];

                    continue;
                }

                if ($isCustomerLeaf || $isPayableLeaf || $isPartnerLeaf) {
                    $role = $partyByAccId[$accId]['role'] ?? null;

                    // Which control a leaf pools onto is decided by POSITION
                    // (its group prefix) and, for a partner-FK leaf outside
                    // both prefixes, by which tblPartner role FK points at
                    // it -- never by the declared AccType.
                    $isPayableSide = $isPayableLeaf || (! $isCustomerLeaf && $role === 'supplier');

                    if ($isPayableSide) {
                        $payableLeafParents[] = $row->parentaccid_fk !== null ? (int) $row->parentaccid_fk : null;
                    } else {
                        $customerLeafParents[] = $row->parentaccid_fk !== null ? (int) $row->parentaccid_fk : null;
                    }

                    $partyRows[] = ['row' => $row, 'kind' => $isPayableSide ? 'payable' : 'receivable'];

                    continue;
                }

                $this->createOrdinaryAccount($row, $companyId, $created, $canonicalRoots, $childCounts, $positionType, $stats);
            }

            // LP1.2 runs HERE, between the phases -- not after the whole
            // import. Every ordinary account now exists and every `direct`
            // legacy_acc_map row is written, which is all SystemPurposeMapper
            // needs; running it now lets AccountResolver actually resolve
            // RECEIVABLE_CONTROL / PAYABLE_CONTROL / RETAINED_EARNINGS for
            // phase 2's pool targets instead of silently falling through to
            // the structural fallback (MAPPING-RULES.md decision O12).
            if ($afterStructuralPhase !== null) {
                $afterStructuralPhase($companyId);
            }

            // Phase 2: resolve pool targets and fold party/RE leaves.
            // R2: the two CONTROL purposes may mint a synthetic pooled leaf
            // when nothing legacy resolves to a postable leaf.
            // RETAINED_EARNINGS may not — its rule is SystemPurposeMapper's
            // single-leaf descent (or an explicit Acc_ID), never a minted
            // account, because the yearly P&L folds must land on the chart's
            // OWN retained-earnings leaf for LP6's close to reconcile.
            $receivableTarget = $this->resolvePoolTarget($companyId, 'RECEIVABLE_CONTROL', $customerLeafParents, $created, $stats, true, $prefixAnchors['RECEIVABLE_CONTROL']);
            $payableTarget = $this->resolvePoolTarget($companyId, 'PAYABLE_CONTROL', $payableLeafParents, $created, $stats, true, $prefixAnchors['PAYABLE_CONTROL']);
            $retainedEarningsTarget = $this->resolvePoolTarget($companyId, 'RETAINED_EARNINGS', $retainedEarningsLeafParents, $created, $stats, false, []);

            foreach ($partyRows as $entry) {
                $row = $entry['row'];
                $accId = (int) $row->acc_id;

                [$target, $resolution] = match ($entry['kind']) {
                    're' => [$retainedEarningsTarget, 'folded_retained_earnings'],
                    'payable' => [$payableTarget, 'pooled_payable'],
                    default => [$receivableTarget, 'pooled_receivable'],
                };

                $party = $partyByAccId[$accId] ?? null;

                if ($target === null) {
                    $this->recordMap($companyId, $row, null, 'unclassified', $positionType, $stats, $party, 'no pool target resolved for this side');
                    $stats['unclassified']++;

                    continue;
                }

                $this->recordMap($companyId, $row, $target, $resolution, $positionType, $stats, $party, null);
                $stats[$resolution]++;
            }

            return $stats;
        });
    }

    /**
     * LP1.5 gate (MAPPING-RULES.md decision O12; membership fixed by LP1c
     * ruling R2): after the import and the purpose mapping, EXACTLY four
     * purposes must resolve through AccountResolver to a structural leaf —
     * RECEIVABLE_CONTROL, PAYABLE_CONTROL, RETAINED_EARNINGS and
     * FX_GAIN_LOSS. If they do not, every pooled party line in LP3 would
     * resolve to nothing, and LP1's own pool targets were chosen by the
     * structural fallback rather than by a real purpose mapping.
     *
     * Nothing else is gated. SUSPENSE in particular stays unmapped by design
     * (its legacy target Acc_ID 1305003 does not exist anywhere in the
     * export, and O6 forbids plugging a suspense account), so gating it would
     * turn a documented, correct gap into a permanent red.
     *
     * @return array<int, string> human-readable failures; empty means green
     */
    public function assertPoolPurposesResolve(int $companyId): array
    {
        $failures = [];

        foreach (['RECEIVABLE_CONTROL', 'PAYABLE_CONTROL', 'RETAINED_EARNINGS', 'FX_GAIN_LOSS'] as $purposeCode) {
            try {
                $account = app(AccountResolver::class)->resolve($purposeCode, $companyId);
            } catch (Throwable $e) {
                $failures[] = "{$purposeCode}: ".$e->getMessage();

                continue;
            }

            if ($account->children()->exists()) {
                $failures[] = "{$purposeCode}: resolves to account {$account->code} ({$account->name}), which is a group, not a structural leaf.";
            }
        }

        return $failures;
    }

    /**
     * LP1d. The unclassified list, for the operator, in one shape:
     * why-they-are-unclassified counts plus the rows themselves.
     *
     * Exists because `legacy:import-coa` used to report only a NUMBER and
     * exit before every other step, so finding out WHICH accounts (and why)
     * meant a second run plus a hand-written query. Staging run #4a's 274
     * turned out to share a single cause; that fact was invisible from the
     * command's own output.
     *
     * @return array{total:int,by_note:array<string,int>,rows:array<int,object>}
     */
    public function unclassifiedReport(int $companyId, int $limit = 40): array
    {
        $query = DB::connection('legacy_pilot')->table('legacy_acc_map')
            ->where('company_id', $companyId)
            ->where('resolution', 'unclassified');

        $byNote = (clone $query)
            ->selectRaw('coalesce(notes, \'(no note)\') as note, count(*) as c')
            ->groupBy('note')
            ->orderByDesc('c')
            ->pluck('c', 'note')
            ->map(fn ($c) => (int) $c)
            ->all();

        return [
            'total' => (clone $query)->count(),
            'by_note' => $byNote,
            'rows' => (clone $query)
                ->select(['acc_id', 'acc_code', 'position_type_code', 'party_role', 'notes'])
                ->orderBy('acc_id')
                ->limit($limit)
                ->get()
                ->all(),
        ];
    }

    /**
     * The DECLARED-type split (tblAccount.AccType), over EVERY mapped row —
     * pooled and folded leaves included. The plan's acceptance figure
     * (A 429 / L 454 / I 332 / E 136) counts all 1,351 staged rows, not
     * only the ones that became their own Account.
     *
     * @return array<string, int>
     */
    public function typeSplit(int $companyId): array
    {
        return DB::connection('legacy_pilot')->table('legacy_acc_map')
            ->where('company_id', $companyId)
            ->selectRaw('account_type_code, count(*) as c')
            ->groupBy('account_type_code')
            ->pluck('c', 'account_type_code')
            ->toArray();
    }

    /**
     * REFUSE (before a single row is written) on any structural defect that
     * would silently reshape the tree: a parent FK naming no staged row, a
     * duplicate AccCode, an AccLevel inconsistent with the legacy parent
     * chain, or a root whose canonical name is not configured.
     *
     * @param  array<int, object>  $byId
     */
    private function assertTreeIntegrity($rows, array $byId): void
    {
        $rootNames = (array) config('legacy_pilot.root_canonical_names', []);

        $missingParents = [];
        $badLevels = [];
        $unmappedRoots = [];
        $seenCodes = [];
        $duplicateCodes = [];

        foreach ($rows as $row) {
            $accId = (int) $row->acc_id;
            $accCode = (string) ($row->acccode ?? '');
            $level = (int) ($row->acclevel ?? 0);
            $parentId = $row->parentaccid_fk !== null && $row->parentaccid_fk !== '' ? (int) $row->parentaccid_fk : null;

            if ($accCode !== '') {
                if (isset($seenCodes[$accCode])) {
                    $duplicateCodes[] = $accCode;
                }

                $seenCodes[$accCode] = true;
            }

            if ($parentId === null) {
                if (! array_key_exists($accCode, $rootNames)) {
                    $unmappedRoots[] = "{$accCode} (".(string) ($row->accname ?? '').')';
                }

                continue;
            }

            if (! isset($byId[$parentId])) {
                $missingParents[] = "acc_id {$accId} -> parent {$parentId}";

                continue;
            }

            $parentLevel = (int) ($byId[$parentId]->acclevel ?? 0);

            if ($level !== $parentLevel + 1) {
                $badLevels[] = "acc_id {$accId} AccLevel {$level}, parent {$parentId} AccLevel {$parentLevel}";
            }
        }

        $problems = [];

        if ($missingParents !== []) {
            $problems[] = count($missingParents).' account(s) name a ParentAccID_FK that is not in stg_account: '.implode('; ', array_slice($missingParents, 0, 10));
        }

        if ($duplicateCodes !== []) {
            $problems[] = count($duplicateCodes).' duplicate AccCode(s): '.implode(', ', array_slice(array_unique($duplicateCodes), 0, 10));
        }

        if ($badLevels !== []) {
            $problems[] = count($badLevels).' account(s) whose AccLevel is inconsistent with their parent chain: '.implode('; ', array_slice($badLevels, 0, 10));
        }

        if ($unmappedRoots !== []) {
            $problems[] = count($unmappedRoots).' root account(s) with no config(legacy_pilot.root_canonical_names) entry: '.implode(', ', $unmappedRoots).
                '. Add the mapping — the importer never guesses a canonical root name, because the trial balance derives debit/credit-normal from it.';
        }

        if ($problems !== []) {
            throw new RuntimeException(
                'legacy:import-coa refuses the whole chart — nothing was imported: '.implode(' | ', $problems)
            );
        }
    }

    /**
     * @param  array<int, object>  $byId
     * @return string A/L/I/E of the root this row's parent chain ends at
     */
    private function rootOf(object $row, array $byId): string
    {
        $seen = [];

        while (true) {
            $accId = (int) $row->acc_id;

            if (isset($seen[$accId])) {
                throw new RuntimeException("legacy:import-coa refuses: parent-chain cycle at acc_id {$accId}.");
            }

            $seen[$accId] = true;
            $parentId = $row->parentaccid_fk !== null && $row->parentaccid_fk !== '' ? (int) $row->parentaccid_fk : null;

            if ($parentId === null || ! isset($byId[$parentId])) {
                return strtoupper((string) ($row->acctype ?? ''));
            }

            $row = $byId[$parentId];
        }
    }

    /**
     * @param  array<int, Account>  $created
     * @param  array<string, Account>  $canonicalRoots
     * @param  array<int, string>  $positionType
     */
    private function createOrdinaryAccount(object $row, int $companyId, array &$created, array &$canonicalRoots, array $childCounts, array $positionType, array &$stats): void
    {
        $accId = (int) $row->acc_id;
        $accCode = (string) ($row->acccode ?? '');
        $accName = (string) ($row->accname ?? $accCode);
        $parentAccId = $row->parentaccid_fk !== null && $row->parentaccid_fk !== '' ? (int) $row->parentaccid_fk : null;

        // POSITIONAL classification — the declared AccType is recorded but
        // never used to decide where the account lands.
        $rootType = config('legacy_pilot.account_type_map.'.($positionType[$accId] ?? ''));

        if ($rootType === null) {
            $this->recordMap($companyId, $row, null, 'unclassified', $positionType, $stats, null, 'positional root AccType is not one of A/L/I/E');
            $stats['unclassified']++;

            return;
        }

        $parentAccount = $parentAccId !== null ? ($created[$parentAccId] ?? null) : null;

        if ($parentAccId !== null && ! $parentAccount instanceof Account) {
            // assertTreeIntegrity() has already proven every parent FK
            // resolves to a staged row and that levels are consistent, so
            // the only way to get here is a parent that was itself pooled
            // (a party leaf can never be a parent) or a bug. Refuse rather
            // than silently re-rooting a whole subtree.
            throw new RuntimeException(
                "legacy:import-coa refuses: acc_id {$accId} ({$accCode}) has parent acc_id {$parentAccId}, which produced no account."
            );
        }

        if ($parentAccount === null) {
            // A legacy ROOT. Rename to the canonical name the trial balance
            // recognises; if another legacy root already claimed that name,
            // hang this one under it as an ordinary group instead.
            $canonicalName = (string) config('legacy_pilot.root_canonical_names.'.$accCode);
            $existingRoot = $canonicalRoots[$canonicalName] ?? null;

            if ($existingRoot instanceof Account) {
                $parentAccount = $existingRoot;
                $note = "legacy root {$accCode} re-parented under canonical root '{$canonicalName}'";
            } else {
                $accName = $canonicalName;
                $note = "legacy root {$accCode} renamed to canonical root '{$canonicalName}'";
            }
        } else {
            $note = null;
        }

        $parentId = $parentAccount instanceof Account ? $parentAccount->id : null;
        $rootId = $parentAccount instanceof Account
            ? ($parentAccount->root_id ?? $parentAccount->id)
            : null;
        $level = $parentAccount instanceof Account ? ((int) $parentAccount->level + 1) : 1;

        $isGroup = ($childCounts[$accId] ?? 0) > 0;
        $isFreeze = $this->isTruthy($row->isfreeze ?? null);

        $account = new Account([
            'name' => $accName !== '' ? $accName : "Account {$accCode}",
            'code' => $accCode,
            'level' => $level,
            'parent_id' => $parentId,
            'root_id' => $rootId,
            'company_id' => $companyId,
            'account_type' => $this->humanAccountType($rootType),
            'report_type' => in_array($rootType, ['ASSET', 'LIABILITY'], true) ? Account::REPORT_TYPES['BALANCE_SHEET'] : Account::REPORT_TYPES['PROFIT_LOSS'],
            'is_group' => $isGroup,
            // tblAccount.IsFreeze is PRESERVED, never dropped.
            'disabled' => $isFreeze ? 1 : 0,
            'actual_balance' => 0.00,
            'budget_balance' => 0.00,
            'variance' => 0.00,
        ]);
        $account->save();

        $created[$accId] = $account;

        if ($parentId === null) {
            $canonicalRoots[$account->name] = $account;
        }

        $this->recordMap($companyId, $row, $account->id, 'direct', $positionType, $stats, null, $note);
        $stats['created']++;
    }

    /**
     * Resolve the account a pooled/folded family lands on, in order:
     *
     *   (a) the purpose LP1.2 already mapped, when it is a structural leaf;
     *   (b) the group that purpose maps to, or — failing any mapping —
     *       (b1, LP1d) the SHALLOWEST staged account whose AccGroup carries
     *       this side's configured group prefix, when exactly one such
     *       account exists, else (b2) the single common structural parent
     *       every leaf on that side shares within the just-imported tree;
     *   (c) R2, control purposes only: a synthetic pooled control LEAF minted
     *       under that group.
     *
     * ── LP1d: why (b2) alone was never enough ───────────────────────────
     * Staging run #4a left 274 of 1,351 accounts `unclassified`, ALL of them
     * payable-side party leaves and ALL with the same note ("no pool target
     * resolved for this side"): PAYABLE_CONTROL had no parameter to map, so
     * (a) produced nothing, and the real chart's payable leaves hang off 31
     * distinct parents, so (b2)'s `count($distinctParents) === 1` produced
     * nothing either. (b2) can only ever fire for a family seeded under one
     * node — the eleven yearly retained-earnings leaves — never for a real
     * party population, which is spread across the chart by construction.
     * (b1) is the rule R2 already stated in prose and the code never had.
     *
     * A deliberately NOT-chosen alternative: the lowest common ancestor of
     * the pooled leaves' parents. On the real chart the payable side spans
     * two roots (267 leaves under LIABILITIES, 7 partner-FK leaves under
     * asset groups — see prefixAnchorAccIds()), so their LCA is the whole
     * tree, i.e. nothing usable. The configured prefix is both narrower and
     * config-named, so it is never a guess.
     *
     * Step (c) is the LP1c ruling. Before it, a control purpose with no legacy
     * leaf (PAYABLE_CONTROL has NO legacy parameter at all — PayableControlAcc
     * is blank in the export — and its only structural anchor, the legacy AP
     * group named by payable_group_prefixes, is a GROUP) pooled every supplier
     * line onto an unpostable group node, or onto nothing. Minting one clearly
     * named leaf under the correct legacy group is the only option that is
     * neither a guess nor an unpostable target; it is recorded in
     * legacy_acc_map with resolution='synthetic' so LP4 parity can tell a
     * minted node from an imported one.
     *
     * @param  array<int, int|null>  $leafParents
     * @param  array<int, Account>  $created
     * @param  array<string, int>  $stats
     * @param  array<int, int>  $anchorAccIds  LP1d — legacy Acc_IDs of the shallowest
     *                                         staged accounts carrying this side's configured group prefix
     */
    private function resolvePoolTarget(int $companyId, string $purposeCode, array $leafParents, array &$created, array &$stats, bool $allowSynthetic, array $anchorAccIds): ?int
    {
        $mapped = $this->purposeMappedAccount($companyId, $purposeCode);

        if ($mapped instanceof Account && ! $mapped->children()->exists()) {
            return $mapped->id;
        }

        $group = $mapped;

        // LP1d step (b1): the configured group prefix's own shallowest node.
        // This is the rule R2 already describes in words ("the legacy AP group
        // named by config('legacy_pilot.payable_group_prefixes')"); before
        // LP1d the code did not implement it, and step (b2) below could not
        // stand in for it.
        if ($group === null && count($anchorAccIds) === 1 && isset($created[$anchorAccIds[0]])) {
            $group = $created[$anchorAccIds[0]];
        }

        if ($group === null) {
            $distinctParents = array_values(array_unique(array_filter($leafParents, fn ($p) => $p !== null)));

            if (count($distinctParents) === 1 && isset($created[$distinctParents[0]])) {
                $group = $created[$distinctParents[0]];
            }
        }

        if ($group === null) {
            return null;
        }

        if (! $allowSynthetic) {
            // Unchanged pre-LP1c behaviour: the RETAINED_EARNINGS fold target
            // is whatever the legacy chart itself offers.
            return $group->id;
        }

        return $this->createSyntheticControlLeaf($companyId, $purposeCode, $group, $stats)->id;
    }

    /**
     * The account a purpose is mapped to for this company, read straight from
     * `system_accounts` — deliberately NOT through AccountResolver, which
     * throws NonLeafAccountException on a group and so cannot tell "mapped to
     * a group" apart from "not mapped at all". That distinction is exactly
     * what R2's synthetic-leaf rule turns on.
     */
    private function purposeMappedAccount(int $companyId, string $purposeCode): ?Account
    {
        $accountId = DB::table('system_accounts')
            ->where('company_id', $companyId)
            ->where('purpose_code', $purposeCode)
            ->whereNull('service_type')
            ->value('account_id');

        if ($accountId === null) {
            return null;
        }

        return Account::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->find($accountId);
    }

    /**
     * R2. Mint the pooled control leaf under $group, map the purpose at it so
     * AccountResolver (and therefore the LP1.5 gate and every LP3 pooled line)
     * resolves it, and record it in legacy_acc_map as synthetic.
     *
     * @param  array<string, int>  $stats
     */
    private function createSyntheticControlLeaf(int $companyId, string $purposeCode, Account $group, array &$stats): Account
    {
        $name = self::SYNTHETIC_CONTROL_NAMES[$purposeCode] ?? null;

        if ($name === null) {
            throw new RuntimeException(
                "legacy:import-coa refuses: no synthetic pooled control name is defined for purpose {$purposeCode}. ".
                'A control leaf is never minted under an invented name.'
            );
        }

        [$code, $syntheticAccId] = $this->allocateSyntheticCode($companyId);

        $account = new Account([
            'name' => $name,
            'code' => $code,
            'level' => ((int) $group->level) + 1,
            'parent_id' => $group->id,
            'root_id' => $group->root_id ?? $group->id,
            'company_id' => $companyId,
            'account_type' => $group->account_type,
            'report_type' => $group->report_type,
            'is_group' => false,
            'disabled' => 0,
            'actual_balance' => 0.00,
            'budget_balance' => 0.00,
            'variance' => 0.00,
        ]);
        $account->save();

        // The group really is a group now, whatever it looked like a moment
        // ago (every one of its legacy children may have pooled away).
        if (! $group->is_group) {
            $group->is_group = true;
            $group->save();
        }

        SystemAccount::updateOrCreate(
            ['company_id' => $companyId, 'purpose_code' => $purposeCode, 'service_type' => null],
            ['account_id' => $account->id]
        );

        $note = "synthetic pooled control leaf minted under legacy group {$group->code} ({$group->name}) for {$purposeCode} — R2";

        DB::connection('legacy_pilot')->table('legacy_acc_map')->updateOrInsert(
            ['company_id' => $companyId, 'acc_id' => $syntheticAccId],
            [
                'acc_code' => $code,
                'account_id' => $account->id,
                'resolution' => 'synthetic',
                'account_type_code' => null,
                'position_type_code' => null,
                'type_dirt' => false,
                'legacy_is_freeze' => false,
                'party_id' => null,
                'party_role' => null,
                'notes' => $note,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        DB::connection('legacy_pilot')->table('map_purpose')->updateOrInsert(
            ['company_id' => $companyId, 'purpose_code' => $purposeCode],
            [
                'legacy_parameter_name' => null,
                'account_id' => $account->id,
                'status' => 'mapped',
                'reason' => $note,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $stats['synthetic_controls']++;

        return $account;
    }

    /**
     * First free code in config('legacy_pilot.import.synthetic_code_range')
     * — free meaning used by no account of this company AND claimed by no
     * legacy_acc_map row. Never a literal in this file; an exhausted range
     * refuses rather than reusing a code.
     *
     * @return array{0:string,1:int} the code, and the legacy_acc_map acc_id to file it under
     */
    private function allocateSyntheticCode(int $companyId): array
    {
        $start = (int) config('legacy_pilot.import.synthetic_code_range.start');
        $end = (int) config('legacy_pilot.import.synthetic_code_range.end');

        if ($start <= 0 || $end < $start) {
            throw new RuntimeException(
                'legacy:import-coa refuses: config(legacy_pilot.import.synthetic_code_range) is not a usable '.
                "range (start={$start}, end={$end}). The synthetic control leaf's code is never a literal."
            );
        }

        $takenCodes = Account::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->pluck('code')
            ->filter()
            ->map(fn ($c) => (string) $c)
            ->all();

        $takenAccIds = DB::connection('legacy_pilot')->table('legacy_acc_map')
            ->where('company_id', $companyId)
            ->pluck('acc_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        for ($candidate = $start; $candidate <= $end; $candidate++) {
            if (in_array((string) $candidate, $takenCodes, true)) {
                continue;
            }

            if (in_array($candidate, $takenAccIds, true)) {
                continue;
            }

            return [(string) $candidate, $candidate];
        }

        throw new RuntimeException(
            "legacy:import-coa refuses: every code in the synthetic range {$start}-{$end} is already taken for ".
            'company '.$companyId.'. Widen config(legacy_pilot.import.synthetic_code_range) — a synthetic '.
            'control leaf never reuses an existing code.'
        );
    }

    /**
     * @return array<int, int>
     */
    private function partnerAccountIds(): array
    {
        if (! Schema::connection('legacy_pilot')->hasTable('stg_partner')) {
            return [];
        }

        $custIds = DB::connection('legacy_pilot')->table('stg_partner')->whereNotNull('custaccid_fk')->pluck('custaccid_fk');
        $suppIds = DB::connection('legacy_pilot')->table('stg_partner')->whereNotNull('suppaccid_fk')->pluck('suppaccid_fk');

        return array_values(array_unique(array_map('intval', $custIds->merge($suppIds)->all())));
    }

    /**
     * legacy Acc_ID => ['party_id' => int, 'role' => 'customer'|'supplier'].
     *
     * LP1d — DUAL-ROLE LEAVES, stated rather than incidental. 50 leaves in the
     * real export are named by BOTH a tblPartner CustAccID_FK and a
     * SuppAccID_FK (MAPPING-RULES §1.2 (b)'s dual-role partners: legacy sets
     * SuppAccID_FK = CustAccID_FK). The loop below writes customer first and
     * supplier second, so for such a leaf the SUPPLIER role wins and the leaf
     * pools to the payable side. That is the rule for this pilot, and it is
     * parity-neutral: MAPPING-RULES §1.2 (b) already fixes one account to one
     * pool and decides a line's role by which pool the account resolves to.
     * It is recorded here because a behaviour that falls out of array order is
     * not a rule until someone writes it down. Owner item O-b in
     * .planning/phases/legacy-ledger-pilot/UNCLASSIFIED-274-2026-09-07.md.
     *
     * @return array<int, array{party_id:int,role:string}>
     */
    private function partyByAccountId(): array
    {
        if (! Schema::connection('legacy_pilot')->hasTable('stg_partner')) {
            return [];
        }

        $map = [];

        foreach (DB::connection('legacy_pilot')->table('stg_partner')->get() as $partner) {
            $partyId = (int) $partner->partner_id;

            foreach ([['custaccid_fk', 'customer'], ['suppaccid_fk', 'supplier']] as [$column, $role]) {
                $accId = $partner->$column ?? null;

                if ($accId === null || $accId === '' || (int) $accId === 0) {
                    continue;
                }

                $map[(int) $accId] = ['party_id' => $partyId, 'role' => $role];
            }
        }

        return $map;
    }

    /**
     * LP1d. The legacy Acc_ID(s) of the SHALLOWEST staged accounts whose
     * AccGroup carries one of $prefixes — i.e. the node the prefix itself
     * names, rather than one of the many sub-groups under it.
     *
     * Returns EVERY account tied at that minimum AccLevel, so the caller can
     * refuse when the prefix names more than one node: picking one of several
     * equally-shallow anchors would be a guess, and this importer never
     * guesses a posting target. On the real export each side has exactly one
     * (the payable prefix's own group at AccLevel 2, the customer prefix's at
     * AccLevel 3).
     *
     * Deliberately ignores pool_excluded_group_prefixes: an excluded subtree
     * (O8-refunds' REFUNDS PAYABLE group) is always DEEPER than the prefix's
     * own root node, so it can never be the shallowest match, and filtering
     * it here would only add a way to get the anchor wrong.
     *
     * @param  array<int, string>  $prefixes
     * @return array<int, int>
     */
    private function prefixAnchorAccIds($rows, array $prefixes): array
    {
        if ($prefixes === []) {
            return [];
        }

        $bestLevel = null;
        $anchors = [];

        foreach ($rows as $row) {
            if (! $this->matchesGroupPrefix((string) ($row->accgroup ?? ''), $prefixes)) {
                continue;
            }

            $level = (int) ($row->acclevel ?? 0);

            if ($bestLevel === null || $level < $bestLevel) {
                $bestLevel = $level;
                $anchors = [(int) $row->acc_id];

                continue;
            }

            if ($level === $bestLevel) {
                $anchors[] = (int) $row->acc_id;
            }
        }

        return array_values(array_unique($anchors));
    }

    private function matchesGroupPrefix(string $accGroup, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if ($prefix !== '' && str_starts_with($accGroup, (string) $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeYearlyRetainedEarnings(string $accName): bool
    {
        return (bool) preg_match('/Profit\s*&?\s*Loss\s*Account\s*\d{4}/i', $accName);
    }

    private function childCounts($rows): array
    {
        $counts = [];

        foreach ($rows as $row) {
            if ($row->parentaccid_fk !== null && $row->parentaccid_fk !== '') {
                $pid = (int) $row->parentaccid_fk;
                $counts[$pid] = ($counts[$pid] ?? 0) + 1;
            }
        }

        return $counts;
    }

    private function isTruthy($value): bool
    {
        if ($value === null) {
            return false;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'y'], true);
    }

    private function humanAccountType(string $rootType): string
    {
        return match ($rootType) {
            'ASSET' => 'Assets',
            'LIABILITY' => 'Liabilities',
            'INCOME' => 'Income',
            'EXPENSE' => 'Expenses',
            default => 'Unknown',
        };
    }

    /**
     * @param  array<int, string>  $positionType
     * @param  array{party_id:int,role:string}|null  $party
     */
    private function recordMap(int $companyId, object $row, ?int $accountId, string $resolution, array $positionType, array &$stats, ?array $party, ?string $note): void
    {
        $accId = (int) $row->acc_id;
        $declared = strtoupper((string) ($row->acctype ?? ''));
        $position = $positionType[$accId] ?? '';
        $isDirt = $declared !== '' && $position !== '' && $declared !== $position;
        $isFreeze = $this->isTruthy($row->isfreeze ?? null);

        if ($isDirt) {
            $stats['type_dirt']++;
        }

        if ($isFreeze) {
            $stats['frozen']++;
        }

        DB::connection('legacy_pilot')->table('legacy_acc_map')->updateOrInsert(
            ['company_id' => $companyId, 'acc_id' => $accId],
            [
                'acc_code' => (string) ($row->acccode ?? ''),
                'account_id' => $accountId,
                'resolution' => $resolution,
                'account_type_code' => $declared !== '' ? $declared : null,
                'position_type_code' => $position !== '' ? $position : null,
                'type_dirt' => $isDirt,
                'legacy_is_freeze' => $isFreeze,
                'party_id' => $party['party_id'] ?? null,
                'party_role' => $party['role'] ?? null,
                'notes' => $note,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
