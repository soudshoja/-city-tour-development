<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CT-A3 R3 — **the one resolver for "where does task T's supplier payable currently sit?"**, and
 * the implementation of owner ruling **R-CT8**, recorded verbatim in
 * `.planning/phases/citytravelers-accounting-audit/PLAN.md` §0.2:
 *
 * > "A payee nomination (who-to-pay reassignment) persists until explicitly changed. Every later
 * >  posting on that task — refund reversal, cancellation/void reversal, cancellation fee, and the
 * >  invoice-time reclassification 1430→COGS — follows the supplier payable to wherever it
 * >  CURRENTLY sits (party + leaf), never back to the purpose-resolved control. Same principle as
 * >  R2-1 (CRN against current posted position)."
 *
 * ── The defect this closes (VERIFY-CT-A3-STACK-R2 §2, finding V2, BLOCKING) ──────────────────────
 * `TaskController::updateJournalPaymentMethod()` posts a balanced `JV`/`PAYEE_REASSIGN` document
 * that moves a task's supplier payable OFF the purpose-resolved `SERVICE_PAYABLE`/{type} control
 * leaf and ONTO the nominated payee account. Every settlement path then posted its payable leg by
 * PURPOSE — i.e. back onto the control — or, in the case of a reversal, onto whatever leaf the
 * ORIGINAL document had credited, which is the same control. Measured:
 *
 *   - uninvoiced, reassigned, then refunded: control ended **+100 DEBIT**, payee **−100**. The AP
 *     GROUP nets to zero, so the trial balance foots, the AP-control reconciliation reports 0.000
 *     and no aggregate in any wave report could see it — while a payable control account sits in
 *     debit and a nominated payee is still owed for a booking that no longer exists.
 *   - invoiced, reassigned, then fully refunded: the ledger kept **KWD 100 of AP to the nominated
 *     payee with no cost, no revenue and no asset behind it, permanently**. An AP payment run
 *     reading the supplier sub-ledger pays for a refunded booking.
 *
 * On the replayed City Travelers population the reassign class posted 1,642 documents carrying
 * KWD 234,153.262; every one of those tasks is a refund away from this shape.
 *
 * ── How the position is decided ─────────────────────────────────────────────────────────────────
 * From the posted DOCUMENT FAMILY, never from a balance and never from `tasks.
 * payment_method_account_id` (which every caller sets BEFORE invoking the flow, so it says what an
 * operator *asked for*, not what the ledger *did*):
 *
 *   1. the newest LIVE `task:{id}:supplier-reassign:%` document for the company — the standing
 *      nomination. Its single CREDIT leg names the leaf the payable was moved to. A reversed or
 *      soft-deleted reassignment is not a standing nomination.
 *   2. no such document ⇒ no nomination, and every caller keeps its existing purpose-resolved
 *      behaviour byte for byte. This is what makes the change inert for the ~99% of tasks that
 *      were never reassigned.
 *
 * The nomination is a STANDING INSTRUCTION, not a balance: it survives the payable going to zero
 * and coming back (void → un-void), which is precisely "persists until explicitly changed". Reading
 * it from the balance instead would make the answer depend on the ORDER in which a caller relieves
 * and re-posts within one flow — the invoice path does both — and that is how two correct feeders
 * produce a wrong ledger.
 *
 * ── Who uses it ─────────────────────────────────────────────────────────────────────────────────
 *   - {@see PostingService::targetAccountId()} — every `SERVICE_PAYABLE` line that names a task,
 *     wherever it is built: the sale document's payable leg (the invoice-time reclassification),
 *     the retained-penalty document, the supplier-credit document, the void cancellation fee. ONE
 *     seam rather than one edit per feeder, so a feeder added later cannot forget the ruling.
 *   - {@see PostingService::reverse()} via {@see self::redirectFor()} — reversals rebuild from
 *     explicit account ids and cannot go through the purpose seam.
 *   - {@see RefundPostingService::taskNetOnPurpose()} — the READ side. R2-1's own principle
 *     ("against the CURRENT posted position") applied to the payable: a refund that measures the
 *     control leaf on a reassigned task reads 0.000 and correctly concludes there is nothing to
 *     relieve, which is the second half of V2's invoiced case.
 *   - {@see SupplierReassignDraftBuilder} — for the AP subtree walk, which was this class's own
 *     ancestor.
 *
 * ── What it deliberately does NOT do ────────────────────────────────────────────────────────────
 * It does not decide whether a payable should EXIST (that is `SupplierPayableRule` and owner ruling
 * R-CT3), it does not move money, and it never resolves a purpose for a line that does not name a
 * task — a company-level or invoice-level payable has no "current position" to follow.
 *
 * R-CT7 (what a payee reassignment BEFORE issuance should mean) is untouched and still open: a
 * reassignment can only be posted when there is already a position to move, so a pre-issuance
 * nomination leaves no document and therefore no nomination here.
 */
final class TaskPayablePositionResolver
{
    /**
     * CT-A7 ROUND 3 (R3-1). The name this chart gives its accounts-payable structure, kept as a
     * constant so the one string this class still cares about is stated once and is greppable.
     * Resolving it is {@see NamedAccountGroupResolver}'s job, not this class's.
     */
    private const AP_GROUP_NAME = 'Accounts Payable';

    public function __construct(
        private readonly NamedAccountGroupResolver $namedGroups = new NamedAccountGroupResolver,
    ) {}

    /**
     * The leaf a who-to-pay nomination has moved this task's supplier payable onto, or null when
     * the task was never reassigned (the overwhelmingly common case, and the one in which every
     * caller must behave exactly as it did before R3).
     *
     * ── Deliberately NOT memoised ───────────────────────────────────────────────────────────────
     * The obvious per-instance cache is a live defect here, not an optimisation. This class is
     * injected into {@see PostingService}, which is constructed once per resolution and then reused
     * for the whole of a caller's flow — and the very flows R-CT8 exists for CHANGE the answer
     * mid-flow: `postIfDue()` posts an accrual (asking, and caching, "no nomination"), the operator
     * reassigns, and `reverseForTask()` on the same service instance would then settle against the
     * stale answer, reproducing V2 exactly. Two indexed reads per payable line is the price of a
     * question whose answer is allowed to change.
     */
    public function nominatedAccountId(int $taskId, int $companyId): ?int
    {
        if ($taskId <= 0 || $companyId <= 0) {
            return null;
        }

        return $this->readNomination($taskId, $companyId);
    }

    /**
     * The account a `SERVICE_PAYABLE` line for this task must target: the standing nomination when
     * there is one, else null so the caller keeps its own purpose resolution. Returning null rather
     * than resolving the purpose here is deliberate — the caller's fallback carries its own
     * service-type scoping and its own `UnmappedPurposeException` contract, and this class must not
     * become a second, subtly different account resolver.
     */
    public function payableAccountId(int $taskId, int $companyId): ?int
    {
        return $this->nominatedAccountId($taskId, $companyId);
    }

    /**
     * The full current position — leaf, party and the amount standing on it — for reporting, for
     * logs, and for a caller that needs the AMOUNT as well as the destination.
     *
     * `$fallbackAccountId` is what the caller's own purpose resolution produced; it is used when
     * there is no nomination. Passing null when there is also no nomination yields a position on
     * account 0 with amount 0.0, which every caller must read as "this task has no payable
     * position at all".
     */
    public function position(Task|int $task, int $companyId, ?int $fallbackAccountId = null): TaskPayablePosition
    {
        $taskId = $task instanceof Task ? (int) $task->getKey() : $task;

        $nominated = $this->nominatedAccountId($taskId, $companyId);
        $accountId = $nominated ?? (int) ($fallbackAccountId ?? 0);

        if ($accountId <= 0) {
            return new TaskPayablePosition($taskId, $companyId, 0, 0.0, false);
        }

        $row = DB::table('journal_entries')
            ->selectRaw('COALESCE(SUM(credit) - SUM(debit), 0) as net_credit')
            ->selectRaw('MAX(type_reference_id) as party_ref')
            ->selectRaw('MAX(name) as party_name')
            ->where('company_id', $companyId)
            ->where('task_id', $taskId)
            ->where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->first();

        return new TaskPayablePosition(
            taskId: $taskId,
            companyId: $companyId,
            accountId: $accountId,
            amount: round((float) ($row->net_credit ?? 0.0), 3),
            isNominated: $nominated !== null,
            partyRef: isset($row->party_ref) && $row->party_ref !== null ? (int) $row->party_ref : null,
            partyName: isset($row->party_name) && $row->party_name !== null ? (string) $row->party_name : null,
        );
    }

    /**
     * Net DEBIT − CREDIT this task carries on its supplier payable RIGHT NOW: the nominated leaf
     * when there is one, plus the caller's purpose-resolved control when the two differ.
     *
     * Both leaves are summed, not just the nominated one, because a reassignment moves the position
     * that EXISTED when it ran — anything posted to the control afterwards by a path this ruling
     * has not reached is still genuinely owed and must not vanish from a refund's arithmetic. On a
     * task that was never reassigned the two collapse to one account and the figure is identical to
     * the pre-R3 one, which is what keeps every existing refund test green.
     */
    public function netPayableForTask(int $taskId, int $companyId, ?int $controlAccountId): float
    {
        $ids = [];

        $nominated = $this->nominatedAccountId($taskId, $companyId);

        if ($nominated !== null) {
            $ids[$nominated] = true;
        }

        if ($controlAccountId !== null && $controlAccountId > 0) {
            $ids[$controlAccountId] = true;
        }

        if ($ids === []) {
            return 0.0;
        }

        return round((float) (DB::table('journal_entries')
            ->whereIn('account_id', array_keys($ids))
            ->where('company_id', $companyId)
            ->where('task_id', $taskId)
            ->whereNull('deleted_at')
            ->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0) as net')
            ->value('net') ?? 0.0), 3);
    }

    /**
     * The redirect {@see PostingService::reverse()} needs so a reversal's payable leg follows the
     * nomination instead of landing back on whichever AP leaf the original document credited.
     *
     * Null when the task carries no nomination — and a null redirect makes `reverse()` byte-for-byte
     * what it was before R3, which is the property every one of the 25 pre-existing test files
     * depends on.
     */
    public function redirectFor(int $taskId, int $companyId): ?TaskPayableRedirect
    {
        $nominated = $this->nominatedAccountId($taskId, $companyId);

        if ($nominated === null) {
            return null;
        }

        $subtree = $this->apSubtreeIds($companyId);

        if ($subtree === []) {
            return null;
        }

        return new TaskPayableRedirect($nominated, $subtree);
    }

    /**
     * Net CREDIT per AP leaf for this task, computed from posted journal rows.
     *
     * Lifted verbatim out of {@see SupplierReassignDraftBuilder::openPayablePositions()} when R3
     * made it the second caller — one implementation of "which accounts still carry this task's
     * payable", per the owner's stale-code rule, rather than two that can drift.
     *
     * @return array<int, array{account_id: int, net_credit: float, party_ref: ?int, party_name: ?string}>
     */
    public function openPositions(int $taskId, int $companyId, ?int $excludeAccountId, float $tolerance): array
    {
        $subtreeIds = $this->apSubtreeIds($companyId);

        if ($subtreeIds === []) {
            return [];
        }

        $rows = DB::table('journal_entries as je')
            ->selectRaw('je.account_id, SUM(je.credit) - SUM(je.debit) as net_credit')
            ->selectRaw('MAX(je.type_reference_id) as party_ref')
            ->selectRaw('MAX(je.name) as party_name')
            ->where('je.company_id', $companyId)
            ->where('je.task_id', $taskId)
            ->whereNull('je.deleted_at')
            ->whereIn('je.account_id', $subtreeIds)
            ->when($excludeAccountId !== null, fn ($q) => $q->where('je.account_id', '!=', $excludeAccountId))
            ->groupBy('je.account_id')
            ->havingRaw('SUM(je.credit) - SUM(je.debit) > ?', [$tolerance])
            ->orderBy('je.account_id')
            ->get();

        // ── CT-A7 ROUND 4, finding R4-1(b) — a position on a GROUP is not a movable position ─────
        // {@see \App\Services\Accounting\SupplierReassignDraftBuilder} turns every row this returns
        // into `new LineDraft(accountId: $position['account_id'], side: 'debit')`, and
        // {@see PostingService} refuses any line whose account has children. Before R3-1 this could
        // not happen — `apSubtreeIds()` returned descendants only — but R3-1 added the GROUPS, for
        // reading, and this method reads the same array to decide what to DEBIT. Company 2's
        // money-bearing `463` is itself a group with 210 descendants, so "Update For Whom to Pay"
        // would have built a debit against it and died on NonLeafAccountException.
        //
        // Skipped, and LOGGED rather than silently dropped: money sitting on a control group is a
        // real chart problem an operator has to hear about, and the reassignment that quietly moved
        // less than the task's whole payable would otherwise look like it worked.
        $positions = [];

        foreach ($rows as $row) {
            $accountId = (int) $row->account_id;

            $isGroup = Account::query()
                ->withoutGlobalScopes()
                ->where('parent_id', $accountId)
                ->whereNull('deleted_at')
                ->exists();

            if ($isGroup) {
                Log::warning('Skipping an open payable position that sits on a non-leaf account.', [
                    'event' => 'accounting.payable_position.non_leaf_skipped',
                    'task_id' => $taskId,
                    'company_id' => $companyId,
                    'account_id' => $accountId,
                    'net_credit' => (float) $row->net_credit,
                    'note' => 'a payable posted directly onto a control GROUP cannot be moved by a '
                        .'reassignment, because the engine refuses a line against a non-leaf account',
                ]);

                continue;
            }

            $positions[] = [
                'account_id' => $accountId,
                'net_credit' => (float) $row->net_credit,
                'party_ref' => $row->party_ref !== null ? (int) $row->party_ref : null,
                'party_name' => $row->party_name !== null ? (string) $row->party_name : null,
            ];
        }

        return $positions;
    }

    /**
     * CT-A7-1 (owner ruling **R-CT9**) — the COMPANY-WIDE shape of {@see self::readNomination()}'s
     * per-task question: every DISTINCT account an R-CT8 payee nomination has actually posted a
     * supplier payable onto (or off) for this company, derived from the POSTED DOCUMENT FAMILY.
     *
     * R-CT9, verbatim (PLAN.md §0.2):
     *
     * > "Reports must show a payable at its CURRENT position. Where a payee nomination has moved a
     * >  supplier payable to a payee leaf (R-CT8), the AR/AP, creditors and statement screens must
     * >  include that leaf alongside control+party, so a reassigned payable is never invisible."
     *
     * ── Why the document family, and not an AP subtree walk ─────────────────────────────────────
     * {@see self::apSubtreeIds()} would answer a superset of this question, and CT-A7 deliberately
     * does NOT use it for the report layer: it re-admits exactly the structural tree-walking the
     * CT-A6-1 no-name-lookup ratchet exists to prevent (it anchors on an account literally NAMED
     * 'Accounts Payable'), and it is unbounded by what the engine did — a chart with 132 AP
     * accounts would put all 132 on every payables screen whether or not one KWD ever moved there.
     * This method is bounded by the posted reassignment documents themselves: the set is exactly
     * "leaves the engine's own R-CT8 feeder touched", which is the population R3-9 measured
     * (1,642 documents / KWD 234,153.262 on the replayed City Travelers ledger).
     *
     * ── Why BOTH legs, not only the credit (destination) leg ────────────────────────────────────
     * A reassignment posts `Dr <every leaf still carrying the payable> / Cr <the nominated payee>`
     * ({@see SupplierReassignDraftBuilder}). The credit leg alone answers "where did it go"; the
     * debit legs answer "where has it been", and a superseded payee leaf (an A → B → A → B
     * sequence, which the feeder's own idempotency key explicitly supports) can still carry
     * residue from a path this ruling has not reached. Including both makes the set a strict
     * superset of "current position" and can never hide money; a leaf the payable has fully left
     * simply contributes 0.000 and costs one extra id in a `whereIn`.
     *
     * Soft-deleted and non-`posted` documents are excluded on the same reasoning
     * {@see self::readNomination()} gives: a reversed reassignment is no longer a nomination.
     *
     * @return list<int>
     */
    public function nominatedPayeeAccountIdsForCompany(int $companyId): array
    {
        if ($companyId <= 0) {
            return [];
        }

        return DB::table('journal_entries as je')
            ->join('transactions as t', 't.id', '=', 'je.transaction_id')
            ->where('t.company_id', $companyId)
            ->whereNull('t.deleted_at')
            ->where('t.posting_status', 'posted')
            ->where('t.idempotency_key', 'like', 'task:%:supplier-reassign:%')
            ->whereNull('je.deleted_at')
            ->distinct()
            ->orderBy('je.account_id')
            ->pluck('je.account_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * CT-A7 ROUND 2, finding **F1** — every account in this company's AP subtree that CARRIES
     * POSTED MOVEMENT. The completeness half of {@see \App\Services\Accounting\LedgerSource::payableAccountIds()}.
     *
     * ── Why the round-1 answer was not an answer ────────────────────────────────────────────────
     * Round 1 built the R-CT9 union from the purpose-resolved leaves plus
     * {@see self::nominatedPayeeAccountIdsForCompany()}, and argued that was enough because the
     * shortfall measured 0.000 on the live chart. It is not enough, and the verifier disproved it
     * with a measured counterexample: {@see \App\Services\Accounting\PostingService::targetAccountId()}
     * is the single R-CT8 seam ONLY for lines that resolve by `purposeCode`; its first branch
     * returns `$line->accountId` verbatim, before any nomination lookup. Four production writers
     * build AP-side lines with an explicit `accountId` and so enter NEITHER half —
     * `AccountingController::storePayableDetail()` / `storeReceivableDetail()` /
     * `storeBankPayment()`, and `BankPaymentController::buildVoucherDraft()`, which is the
     * highest-volume AP writer in the codebase. Through real HTTP routes: raw AP subtree 250.000,
     * screen 0.000 — R3-9 reproduced AFTER the round-1 fix. The live 0.000 shortfall was an
     * accident of the City Travelers chart, where the AP leaves carrying money happen to also be
     * the 1,642 reassignment targets.
     *
     * ── The completeness argument, stated as an argument ────────────────────────────────────────
     *   1. Any line that is a supplier payable lands on an account inside the company's
     *      `Accounts Payable` subtree. Purpose-resolved lines do, because PAYABLE_CONTROL and
     *      SERVICE_PAYABLE/{type} map there and `accounting:coa-linkage` asserts the root of every
     *      purpose it resolves. Operator-picked lines do, because every screen that offers an AP
     *      account offers only accounts that are IN it (see the clause-2 note below). R-CT8
     *      nomination destinations do, because finding F4 now constrains them to it
     *      ({@see \App\Http\Controllers\TaskController::updateJournalPaymentMethod()}).
     *
     * CLAUSE 2, STATED ACCURATELY (CT-A7 R3-1). "Every screen that offers an AP account builds its
     * dropdown from that subtree" was an overstatement and is corrected here rather than left to
     * mislead: `AccountingController::createPayableDetail()` and `::createBankPayment()` do build
     * theirs from the AP structure, but `::createReceivableDetail()`'s account list is every level
     * 3/4/5 account in the company. The conclusion survives, for a different reason: what that
     * screen can credit OUTSIDE the AP structure is client or tax liability, which is not a
     * SUPPLIER payable and does not belong on these screens. What it credits INSIDE the structure
     * is reached by clause 1 like everything else — which is exactly what the F1 attack test proves
     * by crediting an AP leaf through that very route.
     *   2. An account with no posted journal line contributes exactly 0.000 to any total, so
     *      excluding it cannot lose money.
     *   ∴ (subtree ∩ moved) reaches every KWD of accounts payable. Complete by CONSTRUCTION.
     *
     * ── Why the movement filter, and why it answers round 1's own objection ─────────────────────
     * Round 1 rejected the subtree because it "admits all 132 AP accounts whether or not a KWD
     * moved there". Intersecting with posted movement is exactly the filter the document-family
     * half already applied to itself, and it makes the objection moot: a zero-movement leaf is
     * never admitted (asserted by
     * {@see \Tests\Feature\Accounting\CtA7\UnionCompletenessR2Test::test_an_ap_leaf_with_no_movement_is_not_admitted()}),
     * so the set is bounded by what the ledger did, not by the shape of the chart. The round-1
     * "name-anchor purity" objection is WITHDRAWN as inconsistent: {@see self::apSubtreeIds()} is
     * already production code in the money path —
     * {@see \App\Services\Accounting\SupplierReassignDraftBuilder} uses it to decide what a
     * reassignment debits — and the name anchor itself now lives in
     * {@see NamedAccountGroupResolver}, plural and ratcheted (CT-A7 R3-1).
     *
     * ── Source-AGNOSTIC on purpose ─────────────────────────────────────────────────────────────
     * "Movement" here is any non-deleted `journal_entries` row, engine or legacy. This method
     * answers "which accounts are CANDIDATES", and {@see \App\Services\Accounting\LedgerSource::restrict()}
     * then decides which ROWS on them count for the company's current mode. Filtering by the engine
     * discriminator here would drop every AP leaf of an engine-OFF company (companies 2 and 3 on
     * the dev site) out of their own payables screens.
     *
     * @return int[]
     */
    public function apSubtreeIdsWithPostedMovement(int $companyId): array
    {
        $subtree = $this->apSubtreeIds($companyId);

        if ($subtree === []) {
            return [];
        }

        return DB::table('journal_entries')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereIn('account_id', $subtree)
            ->distinct()
            ->orderBy('account_id')
            ->pluck('account_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Every account in the company's `Accounts Payable` STRUCTURE — the groups so named and
     * everything below them.
     *
     * ── CT-A7 ROUND 3, finding R3-1 — the anchor moved out of this class ────────────────────────
     * This used to read the group with
     * `Account::where('name', 'Accounts Payable')->where('company_id', …)->value('id')` and walk
     * from that one id. `accounts.name` has NO uniqueness constraint — `CoaController` validates it
     * as `required|string|max:255` — so a second `Accounts Payable` is two clicks away, and
     * `->value()` silently took one of them (with no ordering, not even deterministically) and hid
     * the other's whole subtree. Measured through real HTTP routes: a supplier leaf under a second
     * such group carrying 700.000 was in neither `apSubtreeIds()` nor `payableAccountIds()`, and
     * the screen read 0.000 against a ledger holding 700.000.
     *
     * The lookup now lives in {@see NamedAccountGroupResolver}, which is plural by contract and is
     * the only file under `app/Services/Accounting` permitted to anchor on one of these names (a
     * two-sided ratchet in ArchitectureTest enforces that). The groups themselves are included as
     * well as their descendants: on a chart that never split its control, a payable can be posted
     * directly onto the group.
     *
     * Walked structurally.
     *
     * `accounts.is_group` is deliberately not consulted — CT-A1 §1.4 measured it wrong on 613
     * accounts (566 flagged as groups with no children, 47 flagged as leaves that have children),
     * the same reason {@see AccountResolver::isLeaf()} derives leaf-ness from the children rather
     * than the flag. The subtree is the right boundary for both chart shapes this codebase serves:
     * the legacy per-supplier leaves under `2110 Creditors` AND the engine's per-service control
     * leaves under `2120 Suppliers (Flights)` / `2130 Suppliers (Hotels)` / …, all of which
     * CoaSeeder parents on 2100. It deliberately does NOT sweep the whole Liabilities root — client
     * advances (2620) and accrued expenses (2200) are not supplier payables.
     *
     * @return int[]
     */
    public function apSubtreeIds(int $companyId): array
    {
        return $this->namedGroups->subtreeIds($companyId, self::AP_GROUP_NAME);
    }

    /**
     * The newest LIVE reassignment document's destination leaf.
     *
     * The destination is read from the DOCUMENT'S OWN CREDIT LEG rather than parsed out of the
     * idempotency key. The key does end `…:{sequence}:{accountId}`
     * ({@see \App\Http\Controllers\TaskController::postSupplierReassignDocument()}), but a key is a
     * label and the journal row is the fact; reading the fact means a key-format change can never
     * silently point a settlement at the wrong leaf.
     *
     * `posting_status = 'posted'` matters here in a way it deliberately does not in
     * `TaskIssuancePayableService::hasPostedSaleDocument()`: a REVERSED reassignment has had its
     * money moved back, so it is no longer a standing nomination — that is what "until explicitly
     * changed" means.
     */
    private function readNomination(int $taskId, int $companyId): ?int
    {
        $prefix = addcslashes('task:'.$taskId.':supplier-reassign:', '%_\\');

        $document = DB::table('transactions')
            ->select('id')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->where('posting_status', 'posted')
            ->where('idempotency_key', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->first();

        if ($document === null) {
            return null;
        }

        $leg = DB::table('journal_entries')
            ->select('account_id')
            ->where('transaction_id', $document->id)
            ->whereNull('deleted_at')
            ->where('credit', '>', 0)
            ->orderByDesc('credit')
            ->first();

        if ($leg === null) {
            Log::warning('accounting.payable_position.reassignment_without_credit_leg', [
                'task_id' => $taskId,
                'company_id' => $companyId,
                'transaction_id' => $document->id,
                'note' => 'the standing nomination cannot be read; settlements fall back to the purpose-resolved control',
            ]);

            return null;
        }

        return (int) $leg->account_id;
    }
}
