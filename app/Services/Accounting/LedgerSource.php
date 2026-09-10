<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Company;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * CT-A6-2 — the ONE place every report (trial balance, P&L, general ledger, AR/AP, deferred
 * revenue, creditors, settlements) asks "which journal_entries rows count right now", replacing
 * the ad hoc, per-report reasoning CT-A2 §7 found (some reports join `transactions`, most don't,
 * and none of them agreed on what "engine" meant).
 *
 * ── The discriminator, and the two alternatives this class deliberately does NOT use ───────────
 * The rule is **`transactions.doc_type IS NOT NULL`** — verbatim from CT-A3-R3 §6.6, "a
 * discriminator note, because it cost an hour and would mislead anyone repeating this", and reused
 * by every CT-D1/CT-D1b probe since. Two more-obvious-looking alternatives were tried and measured
 * WRONG on the real dataset, not merely reasoned about in the abstract:
 *   - **`journal_entries.transaction_id IS NULL`** — wrong because `transaction_id` is NOT NULL on
 *     every legacy row on this database (`transactions` rows were purged at some point, per CT-A1
 *     — the FK survived the purge, the header did not); this split "reports nonsense" (§6.6,
 *     verbatim).
 *   - **`transactions.idempotency_key IS NOT NULL`** — also wrong, and not merely unreliable in
 *     principle: measured on this exact discriminator, CT-A3-R3 §6.6 reports it manufactured a
 *     PHANTOM finding ("1 unbalanced document with a residual of −179.180") that vanished (0
 *     unbalanced, residual 0.000) the moment the query switched to `doc_type`. The phantom was
 *     transaction #36152 — a pre-existing legacy row with NULL `doc_type`, NULL `sub_type` AND
 *     NULL `idempotency_key`, created the day before that lane touched anything. `idempotency_key`
 *     is a real engine invariant (PostingSeam refuses to post a feeder draft without one — see
 *     {@see \App\Exceptions\Accounting\MissingIdempotencyKeyException}) but it answers a DIFFERENT
 *     question ("did a feeder set a dedupe key") than the one this class answers ("did the engine
 *     write this row at all"): a legacy row can go NULL on both, which an idempotency-key split
 *     cannot distinguish from "engine row whose feeder forgot to set a key" the way `doc_type`
 *     can — only {@see \App\Services\Accounting\PostingService::post()} ever sets `doc_type`,
 *     unconditionally, for every document it writes, feeder-supplied idempotency key or not.
 *
 * ── CT-A56 R3-1: and the ONE row shape `doc_type` alone gets WRONG ─────────────────────────────
 * The paragraph above used to end "only {@see PostingService::post()} ever sets `doc_type`". That
 * is false, and independent verification R3 measured the consequence rather than arguing it. The
 * SEAM's own **engine-OFF** writers — `BankPaymentController::writeLegacyTransaction()` and
 * `ReceiptVoucherController::writeLegacyTransaction()`, built by CT-A3 waves 1/2 for OFF/ON parity
 * — `Transaction::forceCreate()` a header carrying `doc_type`, `sub_type`, `doc_year`,
 * `posting_status='posted'`, `posted_at` and `idempotency_key`. Everything the engine header
 * carries EXCEPT `posting_date`. Under a `doc_type`-only split, every payment voucher and receipt
 * voucher an engine-OFF company posts is classified ENGINE, and therefore
 * `whereNotExists`-ed out of that company's OWN trial balance, general ledger, balance sheet and
 * both AR/AP screens: money on the ledger, absent from every report. Companies 2 and 3 on the
 * City Travelers dev site are in exactly that state today, and so is EVERY company between a code
 * deploy and its gate flip — the sequence CT-D1 §0.4i itself used.
 *
 * The discriminator is therefore the CONJUNCTION `doc_type IS NOT NULL AND posting_date IS NOT
 * NULL`, which is right for all four row shapes this schema actually holds:
 *
 *   | row                                   | doc_type | transactions.posting_date | verdict |
 *   |---------------------------------------|----------|---------------------------|---------|
 *   | pre-engine legacy (before the P2.5.B migration) | NULL | backfilled, NOT NULL | legacy |
 *   | engine ({@see PostingService::post()}) | set      | always set (never null)   | engine |
 *   | engine-OFF seam writer                | set      | NULL                      | legacy |
 *   | any other post-migration raw writer    | NULL     | NULL                      | legacy |
 *
 * `posting_date` alone is NOT enough either, and that is why this is a conjunction rather than a
 * replacement: migration `2026_08_30_120000_p25b_add_posting_date_to_journal_entries_and_
 * transactions` BACKFILLS the column on every row that existed when it ran, so a pre-engine legacy
 * row carries a non-null `posting_date` too. CT-A5a's ratchet oracle (`journal_entries.posting_date
 * IS NULL` == a legacy write) is unaffected by that: it only ever looks at rows written DURING a
 * test, after the migration.
 *
 * `doc_type` is nullable and additive-only (migration `2026_08_24_120004_add_document_columns_
 * to_transactions_table`, "Existing rows = posted (legacy code always fully commits today)" —
 * i.e. every pre-existing row is legacy by construction, and every row `PostingService::post()`
 * writes going forward gets one of INV/RV/PV/JV/CRN/DBN/OJV/REV, unconditionally). There is no
 * separate "engine table": both kinds of row live in the same `journal_entries` table, which is
 * exactly why CT-D1b found leaves like `1430 Unbilled Supplier Cost` mixing both and reporting no
 * single coherent balance.
 *
 * The company-level switch is the SAME one {@see \App\Services\Accounting\PostingService::post()}
 * itself gates writes on (`config('accounting.engine.enabled')` AND `companies.
 * posting_engine_enabled`) — a report can never show a company "on" that the engine itself would
 * refuse to post for.
 *
 * ── Why the restriction is applied inside the JOIN, not as an outer WHERE ───────────────────────
 * {@see \App\Services\TrialBalanceService::getAccountBalances()} deliberately `leftJoin`s
 * `journal_entries` onto `accounts` "so accounts with zero activity still appear" (that method's
 * own comment). Adding the source filter as an outer WHERE would silently turn that LEFT JOIN into
 * an INNER JOIN for every account with no matching-source activity, making a zero-activity account
 * vanish from the trial balance instead of showing a zero row. {@see self::restrict()} therefore
 * works from inside a join's ON closure (a {@see JoinClause} supports whereExists/whereNotExists
 * exactly like a query Builder) as well as directly on a top-level query.
 */
final class LedgerSource
{
    public function engineOn(int $companyId): bool
    {
        if (! (bool) config('accounting.engine.enabled')) {
            return false;
        }

        return (bool) Company::query()->whereKey($companyId)->value('posting_engine_enabled');
    }

    /**
     * A whereExists/whereNotExists-compatible closure: true when the named `journal_entries.
     * transaction_id` column links to a `transactions` row carrying a `doc_type` — the engine
     * discriminator (see class docblock). Usable against a query Builder, an Eloquent Builder, or
     * a JoinClause (all three implement whereExists/whereNotExists identically), which is what
     * lets one definition serve TrialBalanceService's raw joins, GeneralLedgerService's
     * query-builder chain, and any future Eloquent-based report alike.
     */
    public function engineTransactionSubquery(string $journalEntriesTransactionIdColumn): \Closure
    {
        return function ($sub) use ($journalEntriesTransactionIdColumn) {
            $sub->selectRaw('1')
                ->from('transactions as ls_t')
                ->whereColumn('ls_t.id', $journalEntriesTransactionIdColumn)
                ->whereNotNull('ls_t.doc_type')
                // CT-A56 R3-1 — see the class docblock's "and the ONE row shape doc_type alone
                // gets wrong" section. `doc_type` alone is not sufficient.
                ->whereNotNull('ls_t.posting_date');
        };
    }

    /**
     * Applies the engine/legacy restriction for $companyId's CURRENT mode to any Builder-like
     * object exposing whereExists/whereNotExists (query Builder, Eloquent Builder, or JoinClause).
     * Never a mixed read — engine-on reads engine rows only, engine-off reads legacy rows only —
     * which is the fix for CT-D1b's GL 1430 finding: a leaf with both kinds of row now reports one
     * coherent balance per mode instead of silently summing both.
     *
     * @template TQuery of QueryBuilder|JoinClause|\Illuminate\Database\Eloquent\Builder
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public function restrict($query, int $companyId, string $journalEntriesTransactionIdColumn = 'transaction_id')
    {
        return $this->engineOn($companyId)
            ? $query->whereExists($this->engineTransactionSubquery($journalEntriesTransactionIdColumn))
            : $query->whereNotExists($this->engineTransactionSubquery($journalEntriesTransactionIdColumn));
    }

    /**
     * Same restriction as restrict(), expressed directly against an already-joined `transactions`
     * alias's own `doc_type` column — for a caller (GeneralLedgerService, findUnbalancedTransactions())
     * whose query already INNER JOINs `transactions` for its own reference/doc_type columns, where a
     * second correlated EXISTS subquery would be redundant.
     *
     * @template TQuery of QueryBuilder|JoinClause|\Illuminate\Database\Eloquent\Builder
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public function restrictJoinedTransactions($query, int $companyId, string $docTypeColumn)
    {
        $postingDateColumn = $this->siblingColumn($docTypeColumn, 'posting_date');

        // CT-A56 R3-1: engine == BOTH columns set; legacy == either one missing. Expressed as the
        // exact complement of the engine test so no row can fall through both branches.
        return $this->engineOn($companyId)
            ? $query->whereNotNull($docTypeColumn)->whereNotNull($postingDateColumn)
            : $query->where(function ($q) use ($docTypeColumn, $postingDateColumn) {
                $q->whereNull($docTypeColumn)->orWhereNull($postingDateColumn);
            });
    }

    /**
     * `'t.doc_type'` -> `'t.posting_date'`; `'doc_type'` -> `'posting_date'`. Keeps
     * {@see self::restrictJoinedTransactions()}'s existing one-column signature (and therefore
     * every existing call site) while letting it police two columns.
     */
    private function siblingColumn(string $qualifiedColumn, string $column): string
    {
        $dot = strrpos($qualifiedColumn, '.');

        return $dot === false ? $column : substr($qualifiedColumn, 0, $dot + 1).$column;
    }

    /**
     * A6-2 transition banner: engine on but legacy rows still exist on this company's ledger —
     * the CT-D1b "engine ledger is internally perfect, legacy ledger drifted" state. Returns null
     * when there is nothing to show a banner about (engine off, or engine on with zero legacy
     * rows left), so a view can `@if($banner)` with no separate existence check.
     *
     * Deliberately always counts LEGACY rows regardless of $companyId's own current mode (unlike
     * restrict(), which reads according to that mode) — the banner's entire purpose is to surface
     * the OTHER side while the engine is on.
     *
     * @return array{legacy_lines: int, legacy_debit: float, legacy_credit: float, legacy_diff: float}|null
     */
    public function transitionBanner(int $companyId): ?array
    {
        if (! $this->engineOn($companyId)) {
            return null;
        }

        $legacy = DB::table('journal_entries as je')
            ->where('je.company_id', $companyId)
            ->whereNull('je.deleted_at')
            ->whereNotExists($this->engineTransactionSubquery('je.transaction_id'))
            ->selectRaw('COUNT(*) as legacy_lines, COALESCE(SUM(je.debit),0) as legacy_debit, COALESCE(SUM(je.credit),0) as legacy_credit')
            ->first();

        $lines = (int) $legacy->legacy_lines;

        if ($lines === 0) {
            return null;
        }

        return [
            'legacy_lines' => $lines,
            'legacy_debit' => (float) $legacy->legacy_debit,
            'legacy_credit' => (float) $legacy->legacy_credit,
            'legacy_diff' => round((float) $legacy->legacy_debit - (float) $legacy->legacy_credit, 3),
        ];
    }

    /**
     * Every leaf a party's PAYABLE could live on, per {@see AccountResolver}: the PAYABLE_CONTROL
     * control leaf itself, plus every per-service `SERVICE_PAYABLE/{type}` leaf
     * (`config('accounting.purpose_codes.service_types')`) that resolves to a DIFFERENT leaf than
     * PAYABLE_CONTROL — a service type with no distinct mapping falls back to PAYABLE_CONTROL
     * itself (`config('accounting.purpose_codes.purpose_fallbacks')`), so de-duplicating here is
     * what keeps a company with no per-service split from reporting the control leaf's balance
     * multiple times over.
     *
     * @return list<int>
     */
    public function payableAccountIds(int $companyId, AccountResolver $resolver): array
    {
        $ids = [$resolver->resolve('PAYABLE_CONTROL', $companyId)->id];

        foreach (config('accounting.purpose_codes.service_types', []) as $serviceType) {
            $ids[] = $resolver->resolve('SERVICE_PAYABLE', $companyId, $serviceType)->id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * The RECEIVABLE_CONTROL control leaf. Kept as its own method (rather than inlining the
     * resolve() call at every AR call site) for the same "one place" reason payableAccountIds()
     * is one.
     *
     * @return list<int>
     */
    public function receivableAccountIds(int $companyId, AccountResolver $resolver): array
    {
        return [$resolver->resolve('RECEIVABLE_CONTROL', $companyId)->id];
    }
}
