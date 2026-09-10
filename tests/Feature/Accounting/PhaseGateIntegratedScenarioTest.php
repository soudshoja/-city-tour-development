<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\BankStatementImport;
use App\Models\BankStatementImportLine;
use App\Models\Branch;
use App\Models\Company;
use App\Models\FixedAsset;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\ApplyFxInput;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\FixedAssets\DepreciationRunService;
use App\Services\Accounting\FixedAssets\FixedAssetService;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostedDocument;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\RealisedFxService;
use App\Services\Accounting\Reconciliation\BankStatementMatcher;
use App\Services\Accounting\Reports\EquityChangesReportService;
use App\Services\Accounting\YearEndCloseService;
use App\Services\TrialBalanceService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * PHASE GATE — one integrated, cross-lane fiscal year for ONE company with the engine ON.
 *
 * Every lane the accounting-builds phase delivered meets in the SAME ledger, in the SAME year,
 * and then that year is closed:
 *   - Lane A (T1)  realised FX on apply — an FC supplier invoice paid later at a different rate.
 *   - Lane B (T2/T3/T4) fixed assets — capitalise, three months of depreciation, disposal at a gain.
 *   - T5 (L9)      the dividend sweep — a real Dr 3200 / Cr bank dividend payment during the year.
 *   - P2.5.C       year-end close with all twelve months locked.
 *   - T6 (L10)     the statement of changes in equity, read against the closed year.
 *   - P2.5.B       TrialBalanceService's YEC whole-document movement exclusion and its
 *                  getOpeningBalances() carry-forward, on the very same documents.
 *
 * Deliberately ONE test method: the point of this gate is that these lanes compose in one ledger,
 * so every assertion below reads the SAME posted history rather than a lane-local fixture. Every
 * expectation is computed independently of the service under test — either from a literal derived
 * by hand in the docblock of the step that posts it, or from a raw `journal_entries` query that
 * shares no code path with YearEndCloseService / EquityChangesReportService.
 *
 * ── The year, by hand (KWD, 3dp) ──────────────────────────────────────────────────────────────
 *   2026-01-05  capital injection            Dr 1201 bank        5000.000 / Cr 3100 capital 5000.000
 *   2026-01-01  fixed-asset capitalisation   Dr 1810 FA cost     1200.000 / Cr 1201 bank    1200.000
 *   2026-02-01  FC supplier invoice          Dr 5222 expense      300.000 / Cr 2110 payable  300.000  (USD 1000 @ 0.300)
 *   2026-03-01  FC supplier payment          Dr 2110 payable      310.000 / Cr 1201 bank     310.000  (USD 1000 @ 0.310)
 *   2026-03-01  realised FX on apply         Cr 2110 payable       10.000 / Dr 5219 FX loss   10.000  (paid 310 against a 300 invoice)
 *   2026-01/02/03 depreciation x3            Dr 5203 dep expense  100.000 / Cr 1881 accum    100.000  each
 *   2026-04-15  disposal, proceeds 1000      Dr cash 1000 + Dr 1881 300 / Cr 1810 1200 + Cr 4141 gain 100
 *   2026-05-10  service income               Dr 1201 bank        2000.000 / Cr 4133 income 2000.000
 *   2026-09-01  dividend payment             Dr 3200 dividends    200.000 / Cr 1201 bank     200.000
 *
 * Income  for 2026 = 4133 2000.000 + 4141 100.000                    = 2100.000
 * Expense for 2026 = 5222 300.000 + 5219 10.000 + 5203 300.000       =  610.000
 * Net profit                                                          = 1490.000
 * Dividends paid                                                      =  200.000
 * Retained Earnings after close = 0 + 1490.000 − 200.000              = 1290.000
 * Equity section after close    = 3100 5000.000 + 3400 1290.000 + 3200 0 = 6290.000
 */
class PhaseGateIntegratedScenarioTest extends AccountingTestCase
{
    private const YEAR = 2026;

    /** USD 1000 invoiced at 0.300 and paid at 0.310 — a realised LOSS of exactly 10.000 KWD. */
    private const FC_AMOUNT = 1000.0;

    private const INVOICE_RATE = 0.300;

    private const PAYMENT_RATE = 0.310;

    private const FX_APPLY_ID = 770001;

    private Company $company;

    private Branch $branch;

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    // ── Fixture helpers ─────────────────────────────────────────────────────────────────────────

    private function resolver(): AccountResolver
    {
        return app(AccountResolver::class);
    }

    private function accountByCode(string $code): Account
    {
        return Account::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->where('code', $code)
            ->firstOrFail();
    }

    private function makeEngineOnCompany(): void
    {
        $this->company = Company::factory()->create();
        CoaSeeder::run($this->company->id);
        (new SystemAccountsSeeder)->run();

        $owner = User::factory()->create();
        $this->branch = Branch::factory()->create(['company_id' => $this->company->id, 'user_id' => $owner->id]);

        config(['accounting.engine.enabled' => true]);
        Artisan::call('accounting:engine', ['company' => $this->company->id, '--enable' => true]);

        $this->trackCompanyForInvariants($this->company->id);
    }

    /** @param LineDraft[] $lines */
    private function postDocument(array $lines, Carbon $date, string $narration, string $key, string $docType = 'JV'): PostedDocument
    {
        return app(PostingService::class)->post(new DocumentDraft(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            docType: $docType,
            subType: null,
            docDate: $date,
            narration: $narration,
            lines: $lines,
            idempotencyKey: $key,
        ));
    }

    private function explicitLine(int $accountId, string $side, float $amount, string $label): LineDraft
    {
        return new LineDraft(
            purposeCode: '',
            accountId: $accountId,
            side: $side,
            amount: $amount,
            currency: 'KWD',
            originalAmount: $amount,
            exchangeRate: 1.0,
            transactionType: 'PHASE_GATE',
            description: $label,
        );
    }

    private function purposeLine(string $purposeCode, string $side, float $amount, string $label): LineDraft
    {
        return new LineDraft(
            purposeCode: $purposeCode,
            accountId: null,
            side: $side,
            amount: $amount,
            currency: 'KWD',
            originalAmount: $amount,
            exchangeRate: 1.0,
            transactionType: 'PHASE_GATE',
            description: $label,
        );
    }

    private function lineOn(PostedDocument $document, int $accountId): JournalEntry
    {
        return JournalEntry::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('transaction_id', $document->transaction->id)
            ->where('account_id', $accountId)
            ->firstOrFail();
    }

    // ── Independent (non-service) ledger readers ────────────────────────────────────────────────

    /**
     * Raw signed movement of every leaf under $rootName between two dates, read straight from
     * journal_entries — no TrialBalanceService, no YearEndCloseService, no report service.
     * Returns debit − credit (so it is positive for a debit-normal root).
     *
     * @param  list<int>  $excludeTransactionIds
     */
    private function rawRootMovement(string $rootName, Carbon $from, Carbon $to, array $excludeTransactionIds = []): float
    {
        $query = DB::table('journal_entries as je')
            ->join('accounts as a', 'a.id', '=', 'je.account_id')
            ->join('accounts as root', 'root.id', '=', 'a.root_id')
            ->where('je.company_id', $this->company->id)
            ->whereNull('je.deleted_at')
            ->where('root.name', $rootName)
            ->whereBetween(DB::raw('COALESCE(je.posting_date, je.transaction_date)'), [$from, $to]);

        if ($excludeTransactionIds !== []) {
            $query->whereNotIn('je.transaction_id', $excludeTransactionIds);
        }

        $totals = $query->selectRaw('COALESCE(SUM(je.debit),0) as d, COALESCE(SUM(je.credit),0) as c')->first();

        return round((float) $totals->d - (float) $totals->c, 3);
    }

    /** Raw credit-normal balance of a single account across ALL history up to $asOf. */
    private function rawCreditBalance(int $accountId, ?Carbon $asOf = null): float
    {
        $query = DB::table('journal_entries')
            ->where('account_id', $accountId)
            ->whereNull('deleted_at');

        if ($asOf !== null) {
            $query->where(DB::raw('COALESCE(posting_date, transaction_date)'), '<=', $asOf);
        }

        $totals = $query->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')->first();

        return round((float) $totals->c - (float) $totals->d, 3);
    }

    /** Raw debit-normal balance of a single account across ALL history up to $asOf. */
    private function rawDebitBalance(int $accountId, ?Carbon $asOf = null): float
    {
        return round(-$this->rawCreditBalance($accountId, $asOf), 3);
    }

    /** The whole Equity section's credit-normal balance, raw, as of $asOf. */
    private function rawEquitySectionBalance(Carbon $asOf): float
    {
        $totals = DB::table('journal_entries as je')
            ->join('accounts as a', 'a.id', '=', 'je.account_id')
            ->join('accounts as root', 'root.id', '=', 'a.root_id')
            ->where('je.company_id', $this->company->id)
            ->whereNull('je.deleted_at')
            ->where('root.name', 'Equity')
            ->where(DB::raw('COALESCE(je.posting_date, je.transaction_date)'), '<=', $asOf)
            ->selectRaw('COALESCE(SUM(je.debit),0) as d, COALESCE(SUM(je.credit),0) as c')
            ->first();

        return round((float) $totals->c - (float) $totals->d, 3);
    }

    /** @return array<int, array{debit: float, credit: float}> per-leaf movement snapshot of the year's TB */
    private function trialBalanceSnapshot(): array
    {
        $report = app(TrialBalanceService::class)->generate(
            $this->company->id,
            Carbon::create(self::YEAR, 1, 1),
            Carbon::create(self::YEAR, 12, 31),
            ['show_zero' => true]
        );

        $snapshot = [];

        foreach ($report['accounts'] as $account) {
            $snapshot[(int) $account->id] = [
                'debit' => round((float) $account->total_debit, 3),
                'credit' => round((float) $account->total_credit, 3),
            ];
        }

        // CT-A3 R2: keyed by account id and SORTED by it, because `assertSame()` on an array
        // compares key ORDER as well as values, and this array's order comes from
        // TrialBalanceService's `ORDER BY a.code` — which is NOT a total order on this chart.
        // CoaSeeder mints code 2130 twice ('Suppliers (Hotels)' and 'Suppliers (Ferry)', the
        // duplicate W2SeededChartRatchetTest allow-lists and CT-A4 chose not to renumber), so the
        // two tied rows may come back in either order, and a row inserted between two snapshots can
        // flip them. Sorting removes a dependency on an order the query never promised; it does not
        // weaken the assertion, which still compares every account's debit and credit exactly.
        ksort($snapshot);

        return $snapshot;
    }

    private function lockAllMonths(): void
    {
        for ($month = 1; $month <= 12; $month++) {
            AccountingPeriod::create([
                'company_id' => $this->company->id,
                'year' => self::YEAR,
                'month' => $month,
                'status' => AccountingPeriod::STATUS_LOCKED,
            ]);
        }
    }

    // ── The scenario ────────────────────────────────────────────────────────────────────────────

    public function test_one_company_one_year_every_lane_closes_and_reconciles(): void
    {
        $this->makeEngineOnCompany();

        $bank = $this->accountByCode('1201');
        $capital = $this->accountByCode('3100');
        $serviceIncome = $this->accountByCode('4133');
        $supplierExpense = $this->accountByCode('5222');
        $faCost = $this->accountByCode('1810');
        $faAccum = $this->accountByCode('1881');
        $disposalGain = $this->accountByCode('4141');
        $fxLoss = $this->accountByCode('5219');
        $payable = $this->resolver()->resolve('PAYABLE_CONTROL', $this->company->id);
        $dividends = $this->resolver()->resolve('DIVIDENDS_PAID', $this->company->id);
        $depExpense = $this->resolver()->resolve('DEPRECIATION_EXPENSE', $this->company->id);
        $retainedEarnings = $this->resolver()->resolve('RETAINED_EARNINGS', $this->company->id);

        // ── 1. Capital injection — a real Equity opening the statement of changes in equity reads.
        $this->postDocument(
            [
                $this->explicitLine($bank->id, 'debit', 5000.0, 'Capital injection'),
                $this->explicitLine($capital->id, 'credit', 5000.0, 'Capital injection'),
            ],
            Carbon::create(self::YEAR, 1, 5),
            'Capital injection',
            'gate:capital'
        );

        // ── 2. FOREIGN-CURRENCY supplier invoice, later payment at a DIFFERENT rate, then apply.
        $invoiceDoc = $this->postDocument(
            [
                $this->explicitLine($supplierExpense->id, 'debit', 300.0, 'FC supplier invoice cost'),
                new LineDraft(
                    purposeCode: 'PAYABLE_CONTROL',
                    accountId: null,
                    side: 'credit',
                    amount: round(self::FC_AMOUNT * self::INVOICE_RATE, 3),
                    currency: 'USD',
                    originalAmount: self::FC_AMOUNT,
                    exchangeRate: self::INVOICE_RATE,
                    transactionType: 'SUPPLIERCREDITED',
                    description: 'FC supplier invoice USD 1000 @ 0.300',
                ),
            ],
            Carbon::create(self::YEAR, 2, 1),
            'FC supplier invoice',
            'gate:ap-invoice'
        );

        $paymentDoc = $this->postDocument(
            [
                new LineDraft(
                    purposeCode: 'PAYABLE_CONTROL',
                    accountId: null,
                    side: 'debit',
                    amount: round(self::FC_AMOUNT * self::PAYMENT_RATE, 3),
                    currency: 'USD',
                    originalAmount: self::FC_AMOUNT,
                    exchangeRate: self::PAYMENT_RATE,
                    transactionType: 'SUPPLIERDEBITED',
                    description: 'FC supplier payment USD 1000 @ 0.310',
                ),
                $this->explicitLine($bank->id, 'credit', round(self::FC_AMOUNT * self::PAYMENT_RATE, 3), 'FC supplier payment'),
            ],
            Carbon::create(self::YEAR, 3, 1),
            'FC supplier payment',
            'gate:ap-payment'
        );

        $sourceLine = $this->lineOn($paymentDoc, $payable->id);   // paid at 0.310 (debit-sourced)
        $appliedLine = $this->lineOn($invoiceDoc, $payable->id);  // invoiced at 0.300

        $fxDocument = app(RealisedFxService::class)->postForApply(new ApplyFxInput(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            sourceLineId: (int) $sourceLine->id,
            appliedLineId: (int) $appliedLine->id,
            appliedFcAmount: self::FC_AMOUNT,
            idSource: 'pa',
            id: self::FX_APPLY_ID,
            docDate: Carbon::create(self::YEAR, 3, 1),
        ));

        // D = 1000·0.310 − 1000·0.300 = +10.000 on a DEBIT-sourced apply => realised LOSS: the
        // 300.000 KWD payable was settled with 310.000 KWD of real money.
        $this->assertNotNull($fxDocument, 'A same-currency apply at two different posted rates must produce an FXR document.');
        $this->assertSame('FXR', $fxDocument->transaction->doc_type);

        $fxLossLine = $this->lineOn($fxDocument, $fxLoss->id);
        $this->assertSame('5219', $fxLoss->code, 'FX_LOSS_REALISED must resolve to the 5219 leaf.');
        $this->assertEqualsWithDelta(10.0, (float) $fxLossLine->debit, 0.0005, 'Realised FX must be a 10.000 LOSS (paid 310.000 against a 300.000 invoice).');
        $this->assertEqualsWithDelta(0.0, (float) $fxLossLine->credit, 0.0005);

        $fxPartyLine = $this->lineOn($fxDocument, $payable->id);
        $this->assertEqualsWithDelta(10.0, (float) $fxPartyLine->credit, 0.0005, 'The payable must be CREDITED on a debit-sourced loss.');
        $this->assertEqualsWithDelta(
            0.0,
            $this->rawCreditBalance($payable->id) * -1,
            0.0005,
            'The invoice, its payment and the realised FX together must settle the payable to exactly zero.'
        );

        // ── 3. Fixed asset: capitalise, three months of depreciation, disposal at a gain.
        $assets = app(FixedAssetService::class);
        $asset = $assets->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'asset_class' => 'CAPITAL_EQUIPMENT',
            'name' => 'Phase-gate asset',
            'code' => 'FA-GATE-001',
            'cost' => 1200.000,
            'salvage' => 0.000,
            'acquisition_date' => Carbon::create(self::YEAR, 1, 1),
            'in_service_date' => Carbon::create(self::YEAR, 1, 1),
            'useful_life_months' => 12,
        ]);

        $capitalisation = $assets->capitalise($asset, null, $bank->id);
        $this->assertNotNull($capitalisation, 'Capitalisation must post with the engine on.');
        $asset->refresh();
        $this->assertSame(FixedAsset::STATUS_ACTIVE, $asset->status);
        $this->assertEqualsWithDelta(1200.0, $assets->nbv($asset), 0.0005, 'NBV of a freshly capitalised asset is its cost.');

        $depreciation = app(DepreciationRunService::class);
        foreach ([1, 2, 3] as $month) {
            $run = $depreciation->runForMonth($this->company->id, self::YEAR, $month);
            $this->assertSame(1, $run['posted'], "Month {$month} must post exactly one DEP document.");
            $this->assertSame([], $run['blocked']);
        }

        $depDocuments = Transaction::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('company_id', $this->company->id)->where('doc_type', 'DEP')->get();
        $this->assertCount(3, $depDocuments, 'Exactly three DEP documents — one per depreciated month.');

        $asset->refresh();
        // 1200 / 12 = 100.000 a month; three months posted => accumulated 300.000, NBV 900.000.
        $this->assertEqualsWithDelta(900.0, $assets->nbv($asset), 0.0005, 'NBV must be cost 1200 − 3 x 100 posted depreciation.');
        $this->assertEqualsWithDelta(300.0, $this->rawCreditBalance($faAccum->id), 0.0005, 'The 1881 contra must carry exactly 300.000 of accumulated depreciation.');

        $disposal = $assets->dispose($asset, Carbon::create(self::YEAR, 4, 15), 1000.000);
        $this->assertNotNull($disposal);
        $this->assertSame('DSP', $disposal->transaction->doc_type);
        $this->assertSame(
            1,
            Transaction::withoutGlobalScopes()->whereNull('deleted_at')
                ->where('company_id', $this->company->id)->where('doc_type', 'DSP')->count(),
            'Exactly one DSP document.'
        );

        // proceeds 1000.000 − NBV 900.000 = a 100.000 GAIN on 4141.
        $this->assertEqualsWithDelta(100.0, (float) $this->lineOn($disposal, $disposalGain->id)->credit, 0.0005, 'Gain = proceeds(1000) − NBV(900) = 100.');
        $this->assertEqualsWithDelta(300.0, (float) $this->lineOn($disposal, $faAccum->id)->debit, 0.0005, 'Accumulated depreciation cleared on disposal.');
        $this->assertEqualsWithDelta(1200.0, (float) $this->lineOn($disposal, $faCost->id)->credit, 0.0005, 'Full cost removed on disposal.');
        $this->assertEqualsWithDelta(0.0, $this->rawDebitBalance($faCost->id), 0.0005, 'FA cost leaf must be flat after disposal.');
        $this->assertEqualsWithDelta(0.0, $this->rawCreditBalance($faAccum->id), 0.0005, 'FA accumulated-depreciation contra must be flat after disposal.');

        // ── 4. Ordinary service income, so the year closes at a PROFIT (a credit-side RE sweep).
        $this->postDocument(
            [
                $this->explicitLine($bank->id, 'debit', 2000.0, 'Service income received'),
                $this->explicitLine($serviceIncome->id, 'credit', 2000.0, 'Service income received'),
            ],
            Carbon::create(self::YEAR, 5, 10),
            'Service income',
            'gate:income'
        );

        // ── 5. Dividend payment through the DIVIDENDS_PAID (3200) purpose.
        $this->postDocument(
            [
                $this->purposeLine('DIVIDENDS_PAID', 'debit', 200.0, 'Dividend paid'),
                $this->explicitLine($bank->id, 'credit', 200.0, 'Dividend paid'),
            ],
            Carbon::create(self::YEAR, 9, 1),
            'Dividend payment',
            'gate:dividend'
        );
        $this->assertSame('3200', $dividends->code, 'DIVIDENDS_PAID must resolve to the 3200 leaf.');

        // ── 5b. LANE H (T9) — a bank statement over this same year's real bank movements ───────
        //
        // PHASE GATE DELTA (lane H merge, tip 6cce9953). Lane H landed after the gate ran, so the
        // one thing no lane-local suite can see — a bank statement reconciled against the SAME
        // ledger every other lane posted into, inside the year that is about to be closed — is
        // proved here. Three oracles: (i) matched/unmatched states are sane against real
        // movements; (ii) the running-balance reconciliation identity ties the statement's own
        // closing balance to the ledger-derived bank balance; (iii) the close that follows is
        // completely unaffected by reconciliation state — every (a)–(f) assertion below runs
        // unchanged with four bank lines now carrying `reconciled = 1`.
        $bankLedgerLines = JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('company_id', $this->company->id)->where('account_id', $bank->id)
            ->orderBy('posting_date')->get();
        $this->assertCount(
            5,
            $bankLedgerLines,
            'Precondition: the year put exactly five movements through the bank leaf (injection, capitalisation, FC payment, income, dividend).'
        );

        $statementImport = BankStatementImport::create([
            'company_id' => $this->company->id,
            'bank_account_id' => $bank->id,
            'file_name' => 'gate-2026.csv',
            'statement_currency' => 'KWD',
            'statement_from' => Carbon::create(self::YEAR, 1, 1),
            'statement_to' => Carbon::create(self::YEAR, 12, 31),
            'opening_balance' => 0.000,
            // 5000 in − 310 out + 2000 in − 200 out − 45 bank charge = 6445.000. NOT the ledger's
            // own 5290.000: the bank never saw the 1200.000 asset purchase (paid from a different
            // instrument) and the ledger never saw the 45.000 charge. That gap is the whole point.
            'closing_balance' => 6445.000,
            'content_hash' => hash('sha256', 'gate-2026'),
            'column_map' => ['date' => 0, 'description' => 1, 'debit' => 2, 'credit' => 3],
            'status' => BankStatementImport::STATUS_STAGED,
        ]);

        // Statement convention (BankStatementMatcher::ledgerColumnFor): statement CREDIT is money
        // in (ledger debit), statement DEBIT is money out (ledger credit).
        $statementRows = [
            [1, Carbon::create(self::YEAR, 1, 5), 0.0, 5000.000, 'Capital injection', 5000.000],
            [2, Carbon::create(self::YEAR, 3, 1), 310.000, 0.0, 'FC supplier payment', 4690.000],
            [3, Carbon::create(self::YEAR, 5, 10), 0.0, 2000.000, 'Service income', 6690.000],
            [4, Carbon::create(self::YEAR, 9, 1), 200.000, 0.0, 'Dividend', 6490.000],
            // No ledger counterpart anywhere — the statement-side exception.
            [5, Carbon::create(self::YEAR, 11, 20), 45.000, 0.0, 'Bank charges', 6445.000],
        ];
        foreach ($statementRows as [$rowNo, $valueDate, $debit, $credit, $label, $running]) {
            BankStatementImportLine::create([
                'import_id' => $statementImport->id, 'row_no' => $rowNo, 'value_date' => $valueDate,
                'posting_date' => $valueDate, 'description' => $label, 'debit' => $debit, 'credit' => $credit,
                'running_balance' => $running, 'state' => BankStatementImportLine::STATE_UNMATCHED,
            ]);
        }

        $ledgerStateBeforeMatch = $this->trialBalanceSnapshot();
        $linesBeforeMatch = JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('company_id', $this->company->id)->count();
        $documentsBeforeMatch = Transaction::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('company_id', $this->company->id)->count();

        $matcher = app(BankStatementMatcher::class);
        $matchResult = $matcher->match($statementImport->refresh());

        // (i) States. Four rows have a real ledger counterpart; the bank charge has none.
        $this->assertSame(4, $matchResult->matched, 'Lane H must match the four statement rows that have real ledger movements.');
        $this->assertSame(0, $matchResult->disputed, 'No amount differs, so nothing may be disputed.');
        $this->assertSame(1, $matchResult->unmatchedStatement, 'The bank charge has no ledger counterpart and must stay unmatched.');

        $statementLines = $statementImport->lines()->orderBy('row_no')->get();
        $this->assertSame(
            [
                BankStatementImportLine::STATE_MATCHED,
                BankStatementImportLine::STATE_MATCHED,
                BankStatementImportLine::STATE_MATCHED,
                BankStatementImportLine::STATE_MATCHED,
                BankStatementImportLine::STATE_UNMATCHED,
            ],
            $statementLines->pluck('state')->all()
        );
        // Each matched row must name the ledger line whose amount and direction it mirrors — a
        // count alone would pass even if every row matched the wrong movement.
        $bankById = $bankLedgerLines->keyBy('id');
        foreach ($statementLines->take(4) as $matchedRow) {
            $counterpart = $bankById[(int) $matchedRow->matched_journal_entry_id] ?? null;
            $this->assertNotNull($counterpart, 'A matched statement row must name a line on THIS bank leaf.');
            $this->assertEqualsWithDelta(
                round((float) $matchedRow->credit - (float) $matchedRow->debit, 3),
                round((float) $counterpart->debit - (float) $counterpart->credit, 3),
                0.001,
                'A matched statement row must mirror its ledger line in both amount and direction.'
            );
            $this->assertEqualsWithDelta(0.0, (float) $matchedRow->difference, 0.001);
        }
        $this->assertSame(
            4,
            $statementLines->take(4)->pluck('matched_journal_entry_id')->unique()->count(),
            'Two statement rows must never consume the same ledger line.'
        );

        // The capitalisation is the ONE bank movement the statement never carried.
        $unmatchedLedger = $matcher->unmatchedLedgerLines($statementImport);
        $this->assertCount(1, $unmatchedLedger, 'Exactly one bank movement (the 1200.000 asset purchase) is missing from the statement.');
        $this->assertEqualsWithDelta(1200.0, (float) $unmatchedLedger->first()->credit, 0.001);

        // (ii) The running-balance reconciliation. Derived independently here, then required to
        // agree with the service's own report:
        //     ledger balance = statement closing + unmatched-statement net + unmatched-ledger net
        // = 6445.000 + 45.000 (a charge the ledger never saw) − 1200.000 (a payment the bank
        // statement never saw) = 5290.000, which is the bank leaf's real derived balance.
        $derivedBankBalance = round(
            (float) $bankLedgerLines->sum('debit') - (float) $bankLedgerLines->sum('credit'),
            3
        );
        $this->assertEqualsWithDelta(5290.0, $derivedBankBalance, 0.001, 'Derived bank balance = 5000 − 1200 − 310 + 2000 − 200.');

        $report = $matcher->reconciliationReport($statementImport->refresh());
        $this->assertEqualsWithDelta($derivedBankBalance, (float) $report['ledger_balance'], 0.001, 'The report must derive the bank balance from journal lines, never from accounts.actual_balance.');
        $this->assertEqualsWithDelta(6445.0, (float) $report['statement_closing_balance'], 0.001);
        $this->assertSame(1, (int) $report['unmatched_statement_count']);
        $this->assertEqualsWithDelta(45.0, (float) $report['unmatched_statement_net'], 0.001);
        $this->assertSame(1, (int) $report['unmatched_ledger_count']);
        $this->assertEqualsWithDelta(-1200.0, (float) $report['unmatched_ledger_net'], 0.001);
        $this->assertEqualsWithDelta(
            (float) $report['ledger_balance'],
            round((float) $report['statement_closing_balance'] + (float) $report['unmatched_statement_net'] + (float) $report['unmatched_ledger_net'], 3),
            0.001,
            'The reconciliation must close: statement closing + both exception nets = the derived ledger balance.'
        );
        $this->assertEqualsWithDelta(-1155.0, (float) $report['difference'], 0.001, 'The unexplained difference is exactly the two exception nets.');

        // (iii) Read-and-propose only: matching wrote no ledger.
        $this->assertSame($linesBeforeMatch, JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')->where('company_id', $this->company->id)->count(), 'Bank-statement matching must never write a journal line.');
        $this->assertSame($documentsBeforeMatch, Transaction::withoutGlobalScopes()->whereNull('deleted_at')->where('company_id', $this->company->id)->count(), 'Bank-statement matching must never post a document.');
        $this->assertSame($ledgerStateBeforeMatch, $this->trialBalanceSnapshot(), 'Bank-statement matching must not move the trial balance.');

        // Approving every proposal flips reconciliation state on four of the five bank lines. The
        // close that follows must not notice: reconciliation is metadata, never money.
        $reconciler = User::factory()->create(['role_id' => \App\Models\Role::ADMIN]);
        $bankStatementProposals = \App\Models\ReconciliationProposal::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->where('kind', \App\Models\ReconciliationProposal::KIND_BANK_STATEMENT)
            ->orderBy('id')->get();
        $this->assertCount(4, $bankStatementProposals, 'One proposal per matched statement row.');

        foreach ($bankStatementProposals as $bankStatementProposal) {
            app(\App\Services\Accounting\ReconciliationProposalService::class)->approve($bankStatementProposal, $reconciler);
        }

        $this->assertSame(
            4,
            JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')
                ->where('company_id', $this->company->id)->where('account_id', $bank->id)
                ->where('reconciled', 1)->count(),
            'All four matched bank lines must now be reconciled.'
        );
        $this->assertSame($linesBeforeMatch, JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')->where('company_id', $this->company->id)->count(), 'Approval must never write a journal line.');
        $this->assertSame($ledgerStateBeforeMatch, $this->trialBalanceSnapshot(), 'Approval must not move the trial balance — reconciliation state is not money.');
        $this->assertEqualsWithDelta(
            $derivedBankBalance,
            round((float) $matcher->reconciliationReport($statementImport->refresh())['ledger_balance'], 3),
            0.001,
            'The derived bank balance must be identical before and after approval.'
        );

        // ── 6. Independent expectations, computed from raw journal_entries only ────────────────
        $yearStart = Carbon::create(self::YEAR, 1, 1)->startOfDay();
        $yearEnd = Carbon::create(self::YEAR, 12, 31)->endOfDay();
        $nextYearStart = Carbon::create(self::YEAR + 1, 1, 1)->startOfDay();

        $expectedIncome = -$this->rawRootMovement('Income', $yearStart, $yearEnd);   // credit-normal
        $expectedExpense = $this->rawRootMovement('Expenses', $yearStart, $yearEnd); // debit-normal
        $expectedNetProfit = round($expectedIncome - $expectedExpense, 3);
        $expectedDividends = $this->rawDebitBalance($dividends->id, $yearEnd);
        $openingRetainedEarnings = $this->rawCreditBalance($retainedEarnings->id, $yearStart->copy()->subSecond());

        // Cross-checked against the hand-derived figures in this class's docblock: any drift
        // between the raw ledger and the arithmetic this gate was designed around fails HERE,
        // before any service under test is even called.
        $this->assertEqualsWithDelta(2100.0, $expectedIncome, 0.0005, 'Income for the year: 2000 service income + 100 disposal gain.');
        $this->assertEqualsWithDelta(610.0, $expectedExpense, 0.0005, 'Expenses for the year: 300 supplier cost + 10 realised FX loss + 300 depreciation.');
        $this->assertEqualsWithDelta(1490.0, $expectedNetProfit, 0.0005);
        $this->assertEqualsWithDelta(200.0, $expectedDividends, 0.0005);
        $this->assertEqualsWithDelta(0.0, $openingRetainedEarnings, 0.0005, 'A brand-new company opens the year with zero retained earnings.');

        // ── 7. Lock all twelve months, snapshot the year's trial balance, then close ───────────
        $this->lockAllMonths();

        $preCloseTrialBalance = $this->trialBalanceSnapshot();
        $preCloseReport = app(TrialBalanceService::class)->generate($this->company->id, $yearStart, $yearEnd);
        $this->assertTrue($preCloseReport['totals']['is_balanced'], 'Pre-close trial balance must already balance.');

        // The snapshot must actually CARRY this year's movement, otherwise the pre/post-close
        // identity asserted under (d) below would be a comparison of two empty maps.
        $this->assertArrayHasKey($serviceIncome->id, $preCloseTrialBalance);
        $this->assertEqualsWithDelta(2000.0, $preCloseTrialBalance[$serviceIncome->id]['credit'], 0.001);
        $this->assertArrayHasKey($retainedEarnings->id, $preCloseTrialBalance);
        $this->assertEqualsWithDelta(0.0, $preCloseTrialBalance[$retainedEarnings->id]['credit'], 0.001);

        $close = app(YearEndCloseService::class)->run($this->company->id, self::YEAR, null);

        $this->assertTrue($close['success'], 'Year-end close must succeed: '.json_encode($close['blocking']));
        $this->assertFalse($close['already_closed']);
        $this->assertNotNull($close['transaction']);
        $this->assertSame('YEC', $close['transaction']->doc_type);
        $this->assertEqualsWithDelta($expectedNetProfit, (float) $close['net_profit'], 0.001, 'net_profit must equal the independently-summed Income − Expenses for the year.');

        $yecId = (int) $close['transaction']->id;
        $yecLines = JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')->where('transaction_id', $yecId)->get();

        // ── (a) TRIAL BALANCE BALANCES AT YEAR END ────────────────────────────────────────────
        $postCloseReport = app(TrialBalanceService::class)->generate($this->company->id, $yearStart, $yearEnd);
        $this->assertTrue($postCloseReport['totals']['is_balanced'], '(a) Trial balance must balance at year end after the close.');
        $this->assertEqualsWithDelta(
            (float) $postCloseReport['totals']['debit'],
            (float) $postCloseReport['totals']['credit'],
            0.001,
            '(a) Debits must equal credits at year end.'
        );
        $this->assertEqualsWithDelta((float) $yecLines->sum('debit'), (float) $yecLines->sum('credit'), 0.001, '(a) The YEC document itself must balance.');
        // The base class re-runs the FULL C1 invariant suite for this company in tearDown();
        // asserting it here too pins the failure to this line rather than to teardown.
        $this->assertAccountingInvariants($this->company->id);

        // ── (b) RETAINED EARNINGS = OPENING RE + NET PROFIT − DIVIDENDS ───────────────────────
        $actualRetainedEarnings = $this->rawCreditBalance($retainedEarnings->id, $yearEnd);
        $this->assertEqualsWithDelta(
            round($openingRetainedEarnings + $expectedNetProfit - $expectedDividends, 3),
            $actualRetainedEarnings,
            0.001,
            '(b) Retained Earnings after close must equal opening RE + net profit − dividends.'
        );
        $this->assertEqualsWithDelta(1290.0, $actualRetainedEarnings, 0.001, '(b) 0 + 1490 − 200 = 1290.');
        $this->assertEqualsWithDelta(
            0.0,
            $this->rawDebitBalance($dividends->id, $yearEnd),
            0.001,
            '(b) The dividend sweep must leave 3200 at zero.'
        );

        // ── (c) STATEMENT OF CHANGES IN EQUITY RECONCILES ─────────────────────────────────────
        $statement = app(EquityChangesReportService::class)->generate($this->company->id, self::YEAR);

        $ledgerEquity = $this->rawEquitySectionBalance($yearEnd);
        $this->assertEqualsWithDelta(6290.0, $ledgerEquity, 0.001, '(c) Ledger equity section = capital 5000 + RE 1290.');
        $this->assertEqualsWithDelta(
            $ledgerEquity,
            (float) $statement['closing_equity_total'],
            0.001,
            "(c) The statement's closing equity must equal the ledger's actual Equity-section balance."
        );
        $this->assertTrue($statement['checks']['ties_to_next_year_opening'], '(c) Post-close the statement must tie to the real next-year opening.');
        $this->assertEqualsWithDelta(0.0, (float) $statement['checks']['difference'], 0.001);
        $this->assertTrue($statement['checks']['ties_to_ledger_derivation']);

        $this->assertEqualsWithDelta($expectedNetProfit, (float) $statement['net_profit'], 0.001);
        $this->assertEqualsWithDelta($expectedDividends, (float) $statement['dividends_paid_this_year'], 0.001, '(c) The Dividends Paid row must report the year\'s real 200.000 payment.');
        $this->assertEqualsWithDelta(-$expectedDividends, (float) $statement['components']['dividends_paid']['movement'], 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $statement['components']['dividends_paid']['closing'], 0.001, '(c) Dividends Paid closes at zero (swept).');

        $openingFooted = (float) $statement['components']['capital']['opening']
            + (float) $statement['components']['opening_balance_equity']['opening']
            + (float) $statement['components']['retained_earnings']['opening']
            + (float) $statement['components']['dividends_paid']['opening'];
        $closingFooted = (float) $statement['components']['capital']['closing']
            + (float) $statement['components']['opening_balance_equity']['closing']
            + (float) $statement['components']['retained_earnings']['closing']
            + (float) $statement['components']['dividends_paid']['closing'];

        $this->assertEqualsWithDelta((float) $statement['opening_equity_total'], $openingFooted, 0.001, '(c) Opening column must foot.');
        $this->assertEqualsWithDelta((float) $statement['closing_equity_total'], $closingFooted, 0.001, '(c) Closing column must foot — the Dividends Paid row included.');

        // ── (d) YEC EXCLUSION — every lane's document flowed through the close, none double-counted
        $sweptDebitOn = fn (int $accountId): float => (float) ($yecLines->firstWhere('account_id', $accountId)?->debit ?? 0.0);
        $sweptCreditOn = fn (int $accountId): float => (float) ($yecLines->firstWhere('account_id', $accountId)?->credit ?? 0.0);

        $this->assertEqualsWithDelta(10.0, $sweptCreditOn($fxLoss->id), 0.001, '(d) The realised-FX loss leaf must be swept by the close.');
        $this->assertEqualsWithDelta(300.0, $sweptCreditOn($depExpense->id), 0.001, '(d) Three months of depreciation expense must be swept by the close.');
        $this->assertEqualsWithDelta(100.0, $sweptDebitOn($disposalGain->id), 0.001, '(d) The disposal gain must be swept by the close.');
        $this->assertEqualsWithDelta(2000.0, $sweptDebitOn($serviceIncome->id), 0.001, '(d) Service income must be swept by the close.');
        $this->assertEqualsWithDelta(300.0, $sweptCreditOn($supplierExpense->id), 0.001, '(d) The supplier cost must be swept by the close.');
        $this->assertEqualsWithDelta(200.0, $sweptCreditOn($dividends->id), 0.001, '(d) The dividend must be swept by the close (T5 dividend sweep).');

        $reLines = $yecLines->where('account_id', $retainedEarnings->id);
        $this->assertCount(2, $reLines, '(d) Retained Earnings carries two distinct YEC lines: the net-profit sweep and the dividend sweep.');
        $this->assertEqualsWithDelta(
            1290.0,
            (float) $reLines->sum('credit') - (float) $reLines->sum('debit'),
            0.001,
            '(d) Net Retained-Earnings effect of the close = 1490 profit − 200 dividends.'
        );

        // Not double-counted: the YEC's own lines are excluded from the CLOSING year's own
        // movement, so the year's trial balance is byte-for-byte what it was before the close.
        $postCloseTrialBalance = $this->trialBalanceSnapshot();
        $this->assertSame($preCloseTrialBalance, $postCloseTrialBalance, '(d) The YEC document must not change the closing year\'s own trial-balance movement (whole-document YEC exclusion).');

        // Spelled out for the one leaf the exclusion is most load-bearing for. The key must be
        // PRESENT (the snapshot is taken with show_zero) so "zero movement" is a real reading of
        // Retained Earnings, never a missing row silently coalescing to zero.
        $this->assertArrayHasKey($retainedEarnings->id, $postCloseTrialBalance);
        $this->assertEqualsWithDelta(0.0, $postCloseTrialBalance[$retainedEarnings->id]['debit'], 0.001, '(d) Retained Earnings must show ZERO in-period debit movement for the closing year.');
        $this->assertEqualsWithDelta(0.0, $postCloseTrialBalance[$retainedEarnings->id]['credit'], 0.001, '(d) Retained Earnings must show ZERO in-period credit movement for the closing year — the swept effect belongs to next year\'s opening.');
        // ... and for the four lane documents, whose in-period movement must survive the close
        // unchanged rather than being netted to zero against the YEC's own sweep lines.
        $this->assertEqualsWithDelta(2000.0, $postCloseTrialBalance[$serviceIncome->id]['credit'], 0.001);
        $this->assertEqualsWithDelta(100.0, $postCloseTrialBalance[$disposalGain->id]['credit'], 0.001);
        $this->assertEqualsWithDelta(10.0, $postCloseTrialBalance[$fxLoss->id]['debit'], 0.001);
        $this->assertEqualsWithDelta(300.0, $postCloseTrialBalance[$depExpense->id]['debit'], 0.001);
        $this->assertEqualsWithDelta(200.0, $postCloseTrialBalance[$dividends->id]['debit'], 0.001);
        // The pre-close raw expectations are unchanged once the YEC is excluded — proving the
        // close swept the real figures rather than re-reading its own output.
        $this->assertEqualsWithDelta(2100.0, -$this->rawRootMovement('Income', $yearStart, $yearEnd, [$yecId]), 0.001);
        $this->assertEqualsWithDelta(610.0, $this->rawRootMovement('Expenses', $yearStart, $yearEnd, [$yecId]), 0.001);

        // ── (e) OPENINGS NEXT YEAR ────────────────────────────────────────────────────────────
        $nextYearOpening = app(TrialBalanceService::class)->getOpeningBalances($this->company->id, $nextYearStart);

        $plLeafIds = DB::table('accounts as a')
            ->join('accounts as root', 'root.id', '=', 'a.root_id')
            ->where('a.company_id', $this->company->id)
            ->whereIn('root.name', ['Income', 'Expenses'])
            ->whereRaw('NOT EXISTS (SELECT 1 FROM accounts child WHERE child.parent_id = a.id)')
            ->pluck('a.id');

        $this->assertGreaterThan(0, $plLeafIds->count(), '(e) precondition: the chart has P&L leaves.');

        foreach ($plLeafIds as $leafId) {
            $row = $nextYearOpening[(int) $leafId] ?? ['opening_debit' => 0.0, 'opening_credit' => 0.0];
            $this->assertEqualsWithDelta(
                0.0,
                round((float) $row['opening_debit'] - (float) $row['opening_credit'], 3),
                0.001,
                "(e) P&L leaf #{$leafId} must open the next year at exactly zero."
            );
        }

        $openingOf = function (int $accountId) use ($nextYearOpening): float {
            $row = $nextYearOpening[$accountId] ?? ['opening_debit' => 0.0, 'opening_credit' => 0.0];

            return round((float) $row['opening_debit'] - (float) $row['opening_credit'], 3);
        };

        // Balance-sheet accounts carry forward: bank = 5000 injected + 2000 income − 1200 asset
        // − 310 FC payment − 200 dividend = 5290.000.
        $this->assertEqualsWithDelta(5290.0, $openingOf($bank->id), 0.001, '(e) Bank must carry its full closing balance into the next year.');
        $this->assertEqualsWithDelta(-5000.0, $openingOf($capital->id), 0.001, '(e) Capital (credit-normal) carries forward at 5000.');
        $this->assertEqualsWithDelta(-1290.0, $openingOf($retainedEarnings->id), 0.001, '(e) Retained Earnings carries the swept 1290 into the next year.');
        $this->assertEqualsWithDelta(0.0, $openingOf($dividends->id), 0.001, '(e) Dividends Paid opens the next year at zero.');
        $this->assertEqualsWithDelta(0.0, $openingOf($faCost->id), 0.001, '(e) The disposed asset leaves no cost behind.');
        $this->assertEqualsWithDelta(0.0, $openingOf($payable->id), 0.001, '(e) The settled FC payable opens the next year at zero.');

        // ── (f) A SECOND CLOSE IS A NO-OP ─────────────────────────────────────────────────────
        $trialBalanceBeforeSecondClose = $this->trialBalanceSnapshot();
        $lineCountBefore = JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('company_id', $this->company->id)->count();

        $secondClose = app(YearEndCloseService::class)->run($this->company->id, self::YEAR, null);

        $this->assertTrue($secondClose['success']);
        $this->assertTrue($secondClose['already_closed'], '(f) A second close of the same year must report already_closed.');
        $this->assertSame($yecId, (int) $secondClose['transaction']->id, '(f) It must return the SAME YEC document.');
        $this->assertSame(
            1,
            Transaction::withoutGlobalScopes()->whereNull('deleted_at')
                ->where('company_id', $this->company->id)->where('doc_type', 'YEC')->count(),
            '(f) No second YEC document may be posted.'
        );
        $this->assertSame(
            $lineCountBefore,
            JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')->where('company_id', $this->company->id)->count(),
            '(f) A second close must write no journal lines at all.'
        );
        $this->assertSame($trialBalanceBeforeSecondClose, $this->trialBalanceSnapshot(), '(f) The trial balance must be unchanged by a second close.');
        $this->assertEqualsWithDelta(1290.0, $this->rawCreditBalance($retainedEarnings->id, $yearEnd), 0.001, '(f) Retained Earnings must not be swept twice.');
    }
}
