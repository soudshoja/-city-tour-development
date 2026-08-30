<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * W5.X (w5-brief.md §W5.X item 3: "reconcile / declineReconcile / fetchPaymentsByDate actions moved
 * behind a ReconciliationService method and gated by permission accounting.reconcile ... P5.10 will
 * replace the service internals; keep the interface minimal").
 *
 * Before this fix, `ReceiptVoucherController` and `BankPaymentController` each carried their own,
 * near-identical copy of `fetchPaymentsByDate()`/`fetchJournalEntriesByIds()`/`declineReconcile()`
 * (w5-brief.md's own Traps section: "reconciled/reconciled_ref_id int flags manipulated directly in
 * fetchPaymentsByDate/declineReconcile -- move behind a service method; P5.10 replaces") -- and
 * NEITHER route carried any `Gate::authorize()` call at all, unlike every other action in either
 * controller. Both defects are fixed here in one move: the query/mutation logic is centralized (one
 * implementation, not two forks that can drift), and every entry point requires
 * {@see self::assertCanReconcile()} before touching `journal_entries.reconciled`.
 *
 * `fetchPaymentsByDate()`'s pre-existing supplier-name search used to resolve the target ACCOUNT via
 * `Account::where('name', $supplierName)->first() ?? Account::where('name', 'LIKE', ...)->first()` --
 * exactly the name/name-LIKE account-resolution anti-pattern w5-brief.md §W5.X item 2's
 * ArchitectureTest rule now forbids under ReceiptVoucherController/BankPaymentController. Fixed here
 * by resolving the SUPPLIER (a distinct model, legitimately searched by its own display name -- see
 * {@see \App\Models\Supplier}) and then following its `payableAccount()` FK
 * (`accounts.supplier_id`, {@see \App\Http\Controllers\ReportController::accountsReconciliationReport()}'s
 * own established convention for the same search box) rather than ever querying `accounts.name`
 * directly. No Account is ever resolved by name in this class.
 *
 * Same dual-check permission convention every policy in this codebase uses (ReceiptVoucherPolicy::
 * reconcile(), BankPaymentPolicy::reconcile()): Spatie `$user->can('accounting.reconcile')` OR the
 * legacy integer `role_id` tier, never only one.
 */
final class ReconciliationService
{
    /**
     * W5.X item 3's permission gate. Deliberately not a policy method (these three actions have no
     * single model instance to authorize against -- they operate over a date range / an arbitrary
     * journal_entries row by id) but the SAME combined check ReceiptVoucherPolicy::reconcile()/
     * BankPaymentPolicy::reconcile() already apply to clear()/bounce() one level up.
     *
     * @throws AuthorizationException
     */
    public function assertCanReconcile(?User $user): void
    {
        if ($user === null) {
            throw new AuthorizationException('This action requires the accounting.reconcile permission.');
        }

        $allowed = $user->hasRole('admin')
            || $user->hasRole('accountant')
            || in_array($user->role_id, [Role::ADMIN, Role::COMPANY, Role::ACCOUNTANT], true)
            || $user->can('accounting.reconcile');

        if (! $allowed) {
            throw new AuthorizationException('This action requires the accounting.reconcile permission.');
        }
    }

    /**
     * W5.X item 3's "reconcile" action -- the write-side counterpart to fetchPaymentsByDate()/
     * declineReconcile(). Moved from `BankPaymentController::applyByDateReconciliation()`'s own
     * raw `JournalEntry::where(...)->update([...])` call (identical filter/values, unchanged
     * behaviour) so all THREE actions the brief names ("reconcile / declineReconcile /
     * fetchPaymentsByDate") live in one place. Marks the given, already-posted liability lines
     * `reconciled = 1` against `$referenceLineId` (this voucher's own new instrument line) --
     * never touches a line already at `reconciled = 2` (BankPaymentController's own
     * PaymentByDate-fast-path "already reconciled" sentinel, set via the engine's `LineDraft::
     * $reconciled` line flag, not this method).
     *
     * @param  int[]  $journalEntryIds
     */
    public function reconcile(int $companyId, int $branchId, array $journalEntryIds, int $referenceLineId): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $journalEntryIds), fn ($id) => $id > 0)));

        if ($ids === []) {
            return;
        }

        $affected = JournalEntry::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->whereIn('id', $ids)
            ->where('reconciled', '!=', 2)
            ->update([
                'reconciled' => 1,
                'reconciled_ref_id' => $referenceLineId,
            ]);

        // P2.5.F writer (b): "reconcile/unreconcile" named explicitly as a writer path. One row per
        // call (not per line) — subject is the reference line the caller matched every journal_entry
        // id against; the matched ids themselves live in `after`.
        AccountingLog::write(
            action: 'reconcile',
            companyId: $companyId,
            subjectType: 'journal_entry',
            subjectId: $referenceLineId,
            after: ['journal_entry_ids' => $ids, 'matched_count' => $affected],
            actorId: \Illuminate\Support\Facades\Auth::id(),
        );
    }

    /**
     * Unreconciled liability-side journal lines in a date range, optionally narrowed to one
     * supplier. Moved verbatim (same SELECT shape, same filters, same response shape both
     * controllers' own callers already depend on) from
     * `ReceiptVoucherController`/`BankPaymentController::fetchPaymentsByDate()` -- the only
     * behavioural change is the supplier-search fix documented in this class's own docblock.
     *
     * @param  int[]  $branchIds
     * @return Collection<int, array<string, mixed>>
     */
    public function fetchPaymentsByDate(int $companyId, array $branchIds, string $from, string $to, ?string $supplierName = null): Collection
    {
        $accountIds = $this->resolveSupplierAccountIds($companyId, $supplierName);

        $totalsByAccountQuery = DB::table('journal_entries')
            ->join('accounts as a', 'journal_entries.account_id', '=', 'a.id')
            ->join('accounts as root_a', 'a.root_id', '=', 'root_a.id')
            ->select(
                'journal_entries.account_id',
                DB::raw('SUM(COALESCE(journal_entries.credit, 0)) - SUM(COALESCE(journal_entries.debit, 0)) AS total')
            )
            ->where('journal_entries.company_id', $companyId)
            ->whereIn('journal_entries.branch_id', $branchIds)
            ->whereBetween('journal_entries.transaction_date', [$from, $to])
            ->whereIn('root_a.name', ['Liabilities'])
            ->when($accountIds !== [], fn ($q) => $q->whereIn('journal_entries.account_id', $accountIds));

        $totalsByAccount = $totalsByAccountQuery
            ->groupBy('journal_entries.account_id')
            ->get()
            ->filter(fn ($e) => $e->total > 0)
            ->pluck('total', 'account_id');

        if ($totalsByAccount->isEmpty()) {
            return collect();
        }

        $entriesQuery = JournalEntry::whereIn('account_id', $totalsByAccount->keys())
            ->where('company_id', $companyId)
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('transaction_date', [$from, $to])
            ->where('credit', '!=', 0)
            ->where('reconciled', 0)
            ->whereNull('voucher_number')
            ->whereHas('account.root', fn ($q) => $q->whereIn('name', ['Liabilities']))
            ->when($accountIds !== [], fn ($q) => $q->whereIn('account_id', $accountIds))
            ->with(['account', 'account.root', 'task'])
            ->orderBy('transaction_date');

        return $entriesQuery->get()->map(function (JournalEntry $entry) use ($totalsByAccount) {
            $description = '';
            if ($entry->task) {
                $description = $entry->task->reference.' - ';
            }

            if (isset($entry->task->client_name)) {
                $description .= $entry->task->client_name;
            } elseif (isset($entry->task->passenger_name)) {
                $description .= $entry->task->passenger_name;
            } elseif (isset($entry->task->supplier_name)) {
                $description .= $entry->task->supplier_name;
            } else {
                $description .= 'No Client';
            }

            if ($entry->task) {
                if ($entry->task->type === 'flight') {
                    $ticketNumber = $entry->task->ticket_number;
                    $description .= $ticketNumber ? ' - '.$ticketNumber : '';
                } elseif ($entry->task->hotel === 'hotel') {
                    $hotelName = $entry->task->hotelDetails->hotel->name ?? '';
                    $description .= $hotelName ? ' - '.$hotelName : '';
                }
            }

            return [
                'id' => $entry->id,
                'transaction_id' => $entry->transaction_id,
                'transaction_date' => $entry->transaction_date,
                'account_id' => $entry->account_id,
                'account_code' => $entry->account->code ?? '',
                'account_name' => $entry->account->name ?? '',
                'root_name' => $entry->account->root->name ?? 'No Root',
                'name' => $entry->name,
                'description' => $description,
                'debit' => (float) $entry->debit,
                'credit' => (float) $entry->credit,
                'account_total' => (float) ($totalsByAccount[$entry->account_id] ?? 0),
            ];
        });
    }

    /**
     * Resolves a typed supplier search term to the account id(s) it should filter on --
     * NEVER by querying `accounts.name` (see class docblock). Returns `[]` (no filter applied)
     * when the term is blank or matches no supplier, or when the matched supplier has no
     * `payableAccount` yet.
     *
     * @return int[]
     */
    private function resolveSupplierAccountIds(int $companyId, ?string $supplierName): array
    {
        $term = trim((string) $supplierName);
        if ($term === '') {
            return [];
        }

        $supplier = Supplier::where('name', $term)->first()
            ?? Supplier::where('name', 'LIKE', "%{$term}%")->first();

        if ($supplier === null) {
            return [];
        }

        $accountId = $supplier->payableAccount?->id;

        return $accountId ? [$accountId] : [];
    }

    /**
     * Moved verbatim from both controllers' identical `fetchJournalEntriesByIds()`.
     */
    public function fetchJournalEntriesByIds(int $reconciledRefId): EloquentCollection
    {
        return JournalEntry::with(['account', 'transaction'])
            ->where('reconciled', 1)
            ->where('reconciled_ref_id', $reconciledRefId)
            ->get();
    }

    /**
     * Moved verbatim from both controllers' identical `declineReconcile()`. Still a raw
     * `reconciled`/`reconciled_ref_id` column write -- w5-brief.md's own Traps section names this
     * a P5.10 concern ("move behind a service method; P5.10 replaces"), i.e. THIS move, not a
     * redesign of the columns themselves.
     */
    public function declineReconcile(int $journalEntryId): void
    {
        $recJournalEntry = JournalEntry::where('id', $journalEntryId)->firstOrFail();

        // P2.5.F writer (b): captured before the cascading unreconcile below mutates/deletes rows.
        AccountingLog::write(
            action: 'unreconcile',
            companyId: (int) $recJournalEntry->company_id,
            subjectType: 'journal_entry',
            subjectId: (int) $recJournalEntry->id,
            before: ['reconciled' => $recJournalEntry->reconciled],
            after: ['reconciled' => 0],
            actorId: \Illuminate\Support\Facades\Auth::id(),
        );

        $recJournalEntry->reconciled = 0;
        $recJournalEntry->save();

        JournalEntry::where('id', $recJournalEntry->id)->update([
            'reconciled' => 0,
        ]);

        $recOriginalJournalEntry = JournalEntry::where('reconciled_ref_id', $recJournalEntry->id)->get();
        foreach ($recOriginalJournalEntry as $entry) {
            $entry->reconciled = 0;
            $entry->reconciled_ref_id = null;
            $entry->save();
        }

        JournalEntry::where('reconciled_ref_id', $recJournalEntry->id)->update([
            'reconciled' => 0,
            'reconciled_ref_id' => null,
        ]);

        JournalEntry::where('id', $recJournalEntry->id)->delete();
    }
}
