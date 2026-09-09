<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\Task;
use Illuminate\Support\Facades\DB;

/**
 * CT-A3 E3 — CT-F39: the "Update For Whom to Pay" flow had **no engine feeder at all**.
 *
 * ── The finding ─────────────────────────────────────────────────────────────────────────────────
 * CT-A1 §1.7 and CT-A2 §1.4 row 17. `TaskController::updateJournalPaymentMethod()`
 * (`TaskController.php:5751`) moves a task's supplier payable from whichever account currently
 * carries it onto `tasks.payment_method_account_id`. Its legacy implementation writes a
 * **one-sided** credit to the new account and only "reverses" the old side when a replicate-based
 * heuristic happens to fire — so on the City Travelers data it produced **1,511 documents carrying
 * KWD 220,908.987 of credits against KWD 477.800 of debits** (CT-A1 §0 item 2). A repair script
 * neutralised 1,435 of them in July 2026 by parking the difference in Equity `3900 Suspense`; the
 * flow was never fixed and wrote 685 more documents in 2026.
 *
 * CT-A2 §5 row 14: the engine has no counterpart, so a cutover would silently STOP a live business
 * operation rather than fix it.
 *
 * ── The document this builds ────────────────────────────────────────────────────────────────────
 * A genuine two-sided reclassification, balanced by construction:
 *
 *   Dr <each account still carrying this task's payable>   = that account's net credit for the task
 *       Cr <the new payable account>                       = the total moved
 *
 * Party attribution is preserved on every leg (`partyAccountRef`), so the supplier sub-ledger stays
 * answerable after the move — the thing CT-A2 §5 row 13 flags as the cost of collapsing per-supplier
 * leaves into a control account.
 *
 * ── Idempotency, by construction rather than by a key alone ─────────────────────────────────────
 * {@see self::buildLines()} derives the amounts from the ledger's CURRENT net position per account.
 * Once the money has moved, a second call finds nothing left to move and returns an empty array —
 * the caller then posts nothing. That is what makes a retry safe even though the callers
 * (`TaskController::handlePaymentMethodChange()`, `TaskWebhook`, the import paths) all set
 * `tasks.payment_method_account_id` BEFORE invoking the flow, so "has the task changed?" is not a
 * question this code can ask. The document also carries its own idempotency key, minted by the
 * caller from the count of reassignment documents already posted for the task, so a genuine
 * A -> B -> A -> B sequence produces four distinct documents rather than colliding on one key.
 *
 * ── Which accounts count as "still carrying the payable" ────────────────────────────────────────
 * Every LEAF under the company's `Accounts Payable` (2100) group that has a net CREDIT for this
 * task, excluding the destination account itself. That subtree is the right boundary for both
 * chart shapes this codebase has to serve: the legacy per-supplier leaves under `2110 Creditors`
 * AND the engine's per-service control leaves under `2120 Suppliers (Flights)` / `2130 Suppliers
 * (Hotels)` / …, all of which CoaSeeder parents on 2100. It deliberately does NOT sweep the whole
 * Liabilities root — client advances (2620) and accrued expenses (2200) are not supplier payables
 * and must never be moved by this flow.
 *
 * This class builds lines only. It does not call {@see PostingSeam} or {@see PostingService} and
 * does not build a {@see DocumentDraft} — same division of labour as {@see SaleDraftBuilder}.
 */
final class SupplierReassignDraftBuilder
{
    /**
     * @return LineDraft[] one debit per account still carrying the task's payable, plus one credit
     *                     to the destination. EMPTY when there is nothing to move (already
     *                     reassigned, or the task never carried a payable) — the caller must treat
     *                     an empty array as "no document", never as an error.
     */
    public function buildLines(
        Task $task,
        int $companyId,
        Account $destination,
        ?int $destinationPartyRef = null,
        ?string $destinationPartyName = null,
    ): array {
        $tolerance = (float) config('accounting.engine.balance_tolerance', 0.0005);
        $currency = (string) config('accounting.engine.base_currency');

        $positions = $this->openPayablePositions($task, $companyId, (int) $destination->id, $tolerance);

        if ($positions === []) {
            return [];
        }

        $lines = [];
        $total = 0.0;

        foreach ($positions as $position) {
            $amount = round($position['net_credit'], 3);
            $total += $amount;

            $lines[] = new LineDraft(
                purposeCode: '',
                accountId: $position['account_id'],
                side: 'debit',
                amount: $amount,
                currency: $currency,
                originalAmount: $amount,
                exchangeRate: 1.0,
                transactionType: 'SUPPLIERDEBITED',
                partyAccountRef: $position['party_ref'],
                description: 'Update For Whom to Pay — released from previous payable account: '.$task->reference,
                taskId: $task->id,
                ledgerType: 'payable',
                partyName: $position['party_name'],
            );
        }

        $total = round($total, 3);

        $lines[] = new LineDraft(
            purposeCode: '',
            accountId: (int) $destination->id,
            side: 'credit',
            amount: $total,
            currency: $currency,
            originalAmount: $total,
            exchangeRate: 1.0,
            transactionType: 'SUPPLIERCREDITED',
            partyAccountRef: $destinationPartyRef,
            description: 'Update For Whom to Pay: '.$task->reference,
            taskId: $task->id,
            ledgerType: 'payable',
            partyName: $destinationPartyName ?? $destination->name,
        );

        return $lines;
    }

    /**
     * Net credit per AP leaf for this task, computed from posted journal rows — never from
     * `accounts.actual_balance` or `journal_entries.balance`, both of which CT-A1 §4.1 proved
     * unusable (Σ|drift| KWD 6,277,563.301 across 200 of 207 posted accounts).
     *
     * @return array<int, array{account_id: int, net_credit: float, party_ref: ?int, party_name: ?string}>
     */
    private function openPayablePositions(Task $task, int $companyId, int $destinationAccountId, float $tolerance): array
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

        $subtreeIds = $this->descendantIds((int) $apGroupId, $companyId);

        if ($subtreeIds === []) {
            return [];
        }

        $rows = DB::table('journal_entries as je')
            ->selectRaw('je.account_id, SUM(je.credit) - SUM(je.debit) as net_credit')
            ->selectRaw('MAX(je.type_reference_id) as party_ref')
            ->selectRaw('MAX(je.name) as party_name')
            ->where('je.company_id', $companyId)
            ->where('je.task_id', $task->id)
            ->whereNull('je.deleted_at')
            ->whereIn('je.account_id', $subtreeIds)
            ->where('je.account_id', '!=', $destinationAccountId)
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
     * Every descendant of a group, walked structurally. `accounts.is_group` is deliberately not
     * consulted — CT-A1 §1.4 measured it wrong on 613 accounts (566 flagged as groups with no
     * children, 47 flagged as leaves that have children), the same reason
     * {@see AccountResolver::isLeaf()} derives leaf-ness from the children rather than the flag.
     *
     * @return int[]
     */
    private function descendantIds(int $groupId, int $companyId): array
    {
        $all = [];
        $frontier = [$groupId];

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
}
