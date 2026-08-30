<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class JournalEntryController extends Controller
{
    public function index($transactionId)
    {
        // {transactionId} carries no whereNumber() route constraint, so a
        // non-numeric segment (e.g. "/journal-entries/abc") arrives here as
        // a raw string. sumRunningBalanceBeforeOffset() below type-hints
        // `int $transactionId` and PHP rejects a non-numeric string at the
        // call boundary with a TypeError before that method's own body ever
        // runs — so cast up front. The point of the cast is that a bad id
        // matches no rows and renders an empty-ledger 200 instead of a 500.
        //
        // NOT byte-for-byte HEAD parity, measured rather than assumed. HEAD
        // handed the raw string to the WHERE clause and let MySQL coerce it,
        // which agrees with this cast only for fully non-numeric ids. On
        // this MySQL: `SELECT 2 = '2abc', 2 = '2.9', 0 = 'abc'` returns
        // `1, 0, 1`. So "/journal-entries/2abc" matched transaction 2 at
        // HEAD and matches nothing now (is_numeric('2abc') is false -> 0),
        // and "/journal-entries/2.9" matched nothing at HEAD and matches
        // transaction 2 now ((int) '2.9' === 2). Both directions are
        // harmless — JournalEntry carries BelongsToCompany so neither can
        // cross a tenant boundary, and no in-app link generates such a URL —
        // but do not cite this line as HEAD-identical.
        //
        // The `: 0` fallback means a legacy row with transaction_id = 0
        // would surface for every non-numeric id. That is NOT new: HEAD's
        // implicit coercion produced the same 0 (`0 = 'abc'` is 1 above).
        // The column is `bigint unsigned NULL DEFAULT NULL`, so orphan lines
        // are NULL rather than 0 and are unaffected either way.
        $transactionId = is_numeric($transactionId) ? (int) $transactionId : 0;

        // Explicit order so pagination is deterministic AND so
        // sumRunningBalanceBeforeOffset() below can reproduce "every row
        // before this page" using the exact same ordering the paginator
        // used to decide what belongs on this page.
        $journalEntries = JournalEntry::with(['agent', 'account', 'task', 'transaction'])
            ->where('transaction_id', $transactionId)
            ->orderBy('id')
            ->paginate(15);
        if (!$journalEntries) {
            return response()->json(['message' => 'Journal entry not found'], 404);
        }

        // Without this, getJournalEntries() started every page's running
        // balance at 0, so page 2 read as if the ledger had just begun
        // rather than continuing from where page 1 left off.
        $offset = ($journalEntries->currentPage() - 1) * $journalEntries->perPage();
        $offsetResult = $this->sumRunningBalanceBeforeOffset($transactionId, $offset);

        $journalEntries = $this->getJournalEntries($journalEntries, $offsetResult['balance'], $offsetResult['unclassified']);

        return view('journal_entries.index', compact('journalEntries', 'transactionId'));
    }

    /**
     * Sum the running-balance contribution of every journal entry for this
     * transaction that sits strictly BEFORE $offset, using the identical
     * where()+orderBy('id') as index()'s paginated query and the identical
     * per-root classification rules as getJournalEntries(), so a page's
     * opening balance is exactly "everything that came before it" rather
     * than restarting at zero.
     *
     * Returns a no-op starting balance (0.0, 0 unclassified) when the COA
     * isn't fully seeded — getJournalEntries() fails safe to an empty
     * collection in that case anyway, so the starting balance is moot.
     *
     * @return array{balance: float, unclassified: int}
     */
    private function sumRunningBalanceBeforeOffset(int $transactionId, int $offset): array
    {
        if ($offset <= 0) {
            return ['balance' => 0.0, 'unclassified' => 0];
        }

        $roots = $this->resolveRootAccounts();
        if ($roots === null) {
            return ['balance' => 0.0, 'unclassified' => 0];
        }

        $priorEntries = JournalEntry::with('account')
            ->where('transaction_id', $transactionId)
            ->orderBy('id')
            ->limit($offset)
            ->get();

        $runningBalance = 0.0;
        $unclassifiedCount = 0;
        foreach ($priorEntries as $journalEntry) {
            // A journal entry whose account was hard-deleted, or belongs to
            // another company (BelongsToCompany's global scope excludes it
            // once Auth::check() is true), resolves to a null relation.
            // classifyEntryDelta() already returns null for this, which
            // previously made it "contribute nothing" with no trace at all
            // — log + count it exactly like an unrecognized root_id below,
            // so it feeds into getJournalEntries()'s banner instead of
            // silently vanishing from the running balance.
            if (!$journalEntry->account) {
                $unclassifiedCount++;
                Log::warning('Journal entry excluded from ledger: account is missing (deleted or out of tenant scope).', [
                    'company_id' => $journalEntry->company_id ?? null,
                    'transaction_id' => $journalEntry->transaction_id ?? null,
                    'journal_entry_id' => $journalEntry->id ?? null,
                    'account_id' => $journalEntry->account_id ?? null,
                ]);
                continue;
            }

            $delta = $this->classifyEntryDelta($journalEntry, $roots);
            if ($delta === null) {
                $unclassifiedCount++;
                Log::warning('Journal entry excluded from ledger: account root_id did not match any of the five known root accounts.', [
                    'company_id' => $journalEntry->company_id ?? null,
                    'transaction_id' => $journalEntry->transaction_id ?? null,
                    'account_id' => $journalEntry->account_id ?? null,
                ]);
                continue;
            }

            $runningBalance += $delta;
        }

        return ['balance' => $runningBalance, 'unclassified' => $unclassifiedCount];
    }

    /**
     * Fetch the five COA root accounts (Assets/Liabilities/Equity/Income/
     * Expenses). Returns null if any is missing — a partially-seeded COA
     * that neither getJournalEntries() nor sumRunningBalanceBeforeOffset()
     * can reliably classify against.
     *
     * @return array{assets: Account, liabilities: Account, equity: Account, income: Account, expenses: Account}|null
     */
    private function resolveRootAccounts(): ?array
    {
        $assets = Account::where('name', 'Assets')->first();
        $liabilities = Account::where('name', 'Liabilities')->first();
        $equity = Account::where('name', 'Equity')->first();
        $income = Account::where('name', 'Income')->first();
        $expenses = Account::where('name', 'Expenses')->first();

        if (!$assets || !$liabilities || !$equity || !$income || !$expenses) {
            return null;
        }

        return compact('assets', 'liabilities', 'equity', 'income', 'expenses');
    }

    /**
     * The running-balance delta a single journal entry contributes, based
     * on which of the five root accounts its account rolls up to. Returns
     * null when the entry's account.root_id matches none of the five
     * (renamed/orphaned root) — the caller decides whether that means
     * "skip it" (getJournalEntries()) or "contributes nothing" (offset sum).
     *
     * @param array{assets: Account, liabilities: Account, equity: Account, income: Account, expenses: Account} $roots
     */
    private function classifyEntryDelta(JournalEntry $journalEntry, array $roots): ?float
    {
        if (!$journalEntry->account) {
            return null;
        }

        return match ($journalEntry->account->root_id) {
            $roots['assets']->id => $journalEntry->debit - $journalEntry->credit,
            $roots['liabilities']->id, $roots['equity']->id, $roots['income']->id => $journalEntry->credit - $journalEntry->debit,
            $roots['expenses']->id => $journalEntry->debit - $journalEntry->credit,
            default => null,
        };
    }

    public function show(Request $request, $accountId)
    {
        $dateFrom = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', now()->endOfMonth()->toDateString());

        $account = Account::findOrFail($accountId);
        $openingBalance = (float) ($account->opening_balance ?? 0);

        $journalEntries = JournalEntry::with(['account', 'transaction', 'task', 'task.flightDetails', 'task.hotelDetails'])
            ->where('account_id', $accountId)
            ->whereDate('transaction_date', '>=', $dateFrom)
            ->whereDate('transaction_date', '<=', $dateTo)
            ->orderBy('transaction_date', 'asc')
            ->get();

        $journalEntries = $this->getJournalEntries($journalEntries, $openingBalance);

        return view('journal_entries.show', compact('journalEntries', 'dateFrom', 'dateTo', 'accountId', 'account', 'openingBalance'));
    }


    /**
     * Classify journal entries by root account type and stamp a running
     * balance onto each entry.
     *
     * IMPORTANT: this method must NEVER return a Response/RedirectResponse.
     * All three callers (index(), show(), and
     * InvoiceController::showDetails()) hand the return value straight to a
     * Blade view as a variable the view calls ->isEmpty()/->isNotEmpty()/
     * ->contains()/foreach on. A RedirectResponse leaking into a view blows
     * up with "Undefined property: ResponseHeaderBag::$transaction_id" (or
     * similar) instead of a clean redirect. Every path below therefore
     * returns a Collection or a LengthAwarePaginator.
     *
     * $journalEntries may be a Collection, a plain array, or a
     * LengthAwarePaginator (index() passes ->paginate(15) straight through).
     * A paginator's underlying page of items is classified and the SAME
     * paginator instance is returned (via ->setCollection()) so the 15-row
     * cap/links stay intact — collect()'ing a paginator directly would
     * instead yield its Arrayable metadata (current_page, data, ...), which
     * is not journal entries at all.
     */
    public function getJournalEntries($journalEntries, float $startingBalance = 0, int $startingUnclassifiedCount = 0): \Illuminate\Support\Collection|LengthAwarePaginator
    {
        // Operate on the underlying items regardless of what shape we were
        // handed, but remember the paginator (if any) so we can hand back
        // the exact same shape at the end.
        $paginator = $journalEntries instanceof LengthAwarePaginator ? $journalEntries : null;

        $items = $paginator !== null
            ? $paginator->getCollection()
            : ($journalEntries instanceof \Illuminate\Support\Collection ? $journalEntries : collect($journalEntries));

        // Nothing to classify — most commonly a company with no journal
        // entries yet for this filter (e.g. no Chart of Accounts seeded).
        // Return as-is rather than requiring the five root accounts to
        // exist just to render an empty list.
        if ($items->isEmpty()) {
            return $paginator !== null ? $paginator->setCollection($items) : $items;
        }

        $assets = Account::where('name', 'Assets')->first();
        $liabilities = Account::where('name', 'Liabilities')->first();
        $equity = Account::where('name', 'Equity')->first();
        $income = Account::where('name', 'Income')->first();
        $expenses = Account::where('name', 'Expenses')->first();

        // Partially-seeded COA (missing one or more root accounts): we can't
        // reliably classify entries against roots that don't exist. Rather
        // than redirecting (which breaks every view caller), fail safe by
        // returning an empty collection so the view renders its normal
        // "no entries" empty state instead of crashing.
        //
        // This silently dropped every row (and its debit/credit from the
        // running balance) with no trace. Inside a double-entry ledger,
        // wrong-but-plausible is worse than an error, so log it and flash a
        // warning banner rather than failing completely silently.
        if (!$assets || !$liabilities || !$equity || !$income || !$expenses) {
            $missingRoots = collect([
                'Assets' => $assets,
                'Liabilities' => $liabilities,
                'Equity' => $equity,
                'Income' => $income,
                'Expenses' => $expenses,
            ])->filter(fn ($account) => !$account)->keys()->implode(', ');

            $sample = $items->first();

            Log::warning('Journal entries could not be classified: chart of accounts is missing one or more root accounts.', [
                'company_id' => $sample->company_id ?? null,
                'transaction_id' => $sample->transaction_id ?? null,
                'account_id' => $sample->account_id ?? null,
                'entry_count' => $items->count(),
                'missing_roots' => $missingRoots,
            ]);

            // ->now() (not ->flash()) — this banner describes what just
            // happened on THIS request's data. flash() survives into the
            // next unrelated request (it lives until the request AFTER
            // next reads it), which showed the warning on whatever page the
            // user navigated to next even when nothing was wrong there.
            session()->now('warning', $items->count() . ' ' . \Illuminate\Support\Str::plural('entry', $items->count()) . ' could not be classified — chart of accounts incomplete.');

            // An honest total (0), not the original paginate() total — a
            // missing root account can never be classified on ANY page, so
            // ->setCollection($empty) alone left ->total()/->lastPage() at
            // their original (non-zero) values: a paginator reporting
            // "20 results, 2 pages" while holding, and being able to hold,
            // nothing.
            //
            // W0.4 MEASURED — this has NO user-visible effect on this
            // controller's own Blade view today, and the fix is not
            // justified by one: journal_entries/index.blade.php guards the
            // whole table+links block with @if($journalEntries->isEmpty())
            // ... @else, and ->hasPages()/->links() sit inside that @else,
            // so an empty page never reaches the pagination markup either
            // way (probed on both the fixed and the unfixed code: no
            // "page=2" in the response body in either). The fix is for the
            // paginator's OWN contract — ->total() is public API, this
            // method is public, and its other caller
            // (InvoiceController::showDetails()) and any future JSON/API
            // caller read it directly.
            return $paginator !== null ? $this->emptyPaginator($paginator) : collect();
        }

        $runningBalance = $startingBalance;
        $classified = collect();
        $unclassifiedCount = $startingUnclassifiedCount;
        foreach ($items as $journalEntry) {
            // A hard-deleted account, or one belonging to another company
            // (BelongsToCompany's global scope excludes it once
            // Auth::check() is true), resolves to a null relation here.
            // classifyEntryDelta() (used by sumRunningBalanceBeforeOffset())
            // already guards this; backport the same treatment so it can't
            // crash the render loop below on ->root_id.
            if (!$journalEntry->account) {
                $unclassifiedCount++;
                Log::warning('Journal entry excluded from ledger: account is missing (deleted or out of tenant scope).', [
                    'company_id' => $journalEntry->company_id ?? null,
                    'transaction_id' => $journalEntry->transaction_id ?? null,
                    'journal_entry_id' => $journalEntry->id ?? null,
                    'account_id' => $journalEntry->account_id ?? null,
                ]);
                continue;
            }

            if ($journalEntry->account->root_id == $assets->id) {
                $runningBalance += $journalEntry->debit - $journalEntry->credit;
            } elseif ($journalEntry->account->root_id == $liabilities->id) {
                $runningBalance += $journalEntry->credit - $journalEntry->debit;
            } elseif ($journalEntry->account->root_id == $equity->id) {
                $runningBalance += $journalEntry->credit - $journalEntry->debit;
            } elseif ($journalEntry->account->root_id == $income->id) {
                $runningBalance += $journalEntry->credit - $journalEntry->debit;
            } elseif ($journalEntry->account->root_id == $expenses->id) {
                $runningBalance += $journalEntry->debit - $journalEntry->credit;
            } else {
                // Renamed/unrecognized root account for this entry: skip it
                // (don't let one bad row abort the whole ledger view) rather
                // than redirecting out of a view context. Still log + flash
                // so the drop is visible instead of silently changing the
                // rendered total.
                $unclassifiedCount++;
                Log::warning('Journal entry excluded from ledger: account root_id did not match any of the five known root accounts.', [
                    'company_id' => $journalEntry->company_id ?? null,
                    'transaction_id' => $journalEntry->transaction_id ?? null,
                    'account_id' => $journalEntry->account_id ?? null,
                ]);
                continue;
            }
            $journalEntry->running_balance = $runningBalance;
            $classified->push($journalEntry);
        }

        if ($unclassifiedCount > 0) {
            // Distinct wording from the missing-root-accounts banner above:
            // here the five root accounts all exist (we got this far), the
            // problem is one or more ENTRIES point at a root_id that isn't
            // any of them — an orphaned or renamed root, not an incomplete
            // COA. Saying "incomplete" here misdiagnoses the cause.
            //
            // ->now(), not ->flash(), for the same reason as above: this
            // describes this request's data only.
            session()->now('warning', $unclassifiedCount . ' ' . \Illuminate\Support\Str::plural('entry', $unclassifiedCount) . ' could not be classified — one or more entries reference an orphaned or renamed root account.');
        }

        // Same honest-total fix as the missing-root-accounts branch above,
        // for the case where every single entry handed to this call failed
        // classification (e.g. all of them reference the same orphaned
        // root): ->setCollection($classified) with an empty collection
        // would otherwise keep the original paginate() total/lastPage.
        // Same caveat as above — this is a paginator-contract fix, not a
        // rendering fix; the view's isEmpty() short-circuit already hides
        // the links block on an empty page.
        if ($paginator !== null) {
            return $classified->isEmpty() ? $this->emptyPaginator($paginator) : $paginator->setCollection($classified);
        }

        return $classified;
    }

    /**
     * Build an honest empty LengthAwarePaginator (total 0, 1 page) that
     * preserves the original paginator's per-page size and URL path/page
     * parameter name, for the fail-safe paths in getJournalEntries() where
     * every entry handed to it failed classification. ->setCollection() on
     * its own leaves ->total()/->lastPage() at their pre-classification
     * values — a paginator that reports more results and more pages than it
     * can ever hold.
     *
     * NOT a rendering fix: journal_entries/index.blade.php never reaches its
     * ->hasPages()/->links() block on an empty page (it sits inside the
     * @else of an @if($journalEntries->isEmpty())), and W0.4 probed both the
     * fixed and the unfixed code — neither emits a "page=2" link. Do not
     * "verify" this helper by looking for stray links in the HTML; verify it
     * with ->total()/->lastPage() on the returned paginator, which is what
     * JournalEntriesViewSafetyTest's two direct-call tests assert.
     *
     * KNOWN LIMITATION: this rebuilds the paginator from perPage/path/
     * pageName only, so a caller that had called ->appends()/
     * ->withQueryString() loses those params, and currentPage resets to 1.
     * Unreachable today (total 0 => hasPages() false => no URL is emitted at
     * all), and left as-is rather than reached for via reflection.
     */
    private function emptyPaginator(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            collect(),
            0,
            $paginator->perPage(),
            1,
            [
                'path' => $paginator->path(),
                'pageName' => $paginator->getPageName(),
            ]
        );
    }


    public function exportPdf(Request $request, $accountId)
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        // Fetch filtered entries
        $journalEntries = JournalEntry::where('account_id', $accountId)
            ->whereBetween('transaction_date', [$dateFrom, $dateTo])
            ->get();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('journal_entries.pdf', [
            'journalEntries' => $journalEntries,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
        return $pdf->download('journal-entries-ledger.pdf');
    }
}
