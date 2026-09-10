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

        return $rows->map(fn ($r) => [
            'account_id' => (int) $r->account_id,
            'net_credit' => (float) $r->net_credit,
            'party_ref' => $r->party_ref !== null ? (int) $r->party_ref : null,
            'party_name' => $r->party_name !== null ? (string) $r->party_name : null,
        ])->all();
    }

    /**
     * Every account under the company's `Accounts Payable` (2100) group, walked structurally.
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
        $apGroupId = Account::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->where('name', 'Accounts Payable')
            ->value('id');

        if ($apGroupId === null) {
            return [];
        }

        $all = [];
        $frontier = [(int) $apGroupId];

        // Bounded by the chart's real depth (5 levels on this COA); the guard exists only so a
        // cyclic parent_id — which no constraint prevents — cannot spin forever.
        for ($depth = 0; $depth < 12 && $frontier !== []; $depth++) {
            $children = Account::query()
                ->withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $children = array_values(array_diff($children, $all));

            if ($children === []) {
                break;
            }

            $all = array_merge($all, $children);
            $frontier = $children;
        }

        return $all;
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
