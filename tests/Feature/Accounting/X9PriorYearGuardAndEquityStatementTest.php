<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\AccountingPeriod;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\Reports\EquityChangesReportService;
use App\Services\Accounting\YearEndCloseService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * XBRL X9 (PLAN.md X9, H-M2, §4.1 D-8, §10 items 2 and 6):
 *
 * 1. `YearEndCloseService` refuses year Y while Y-1 still has an unswept profit or loss, and does
 *    NOT refuse when Y-1 is before the company's earliest year on its ledger source (the floor).
 * 2. The host statement of changes in equity opens retained earnings at the component PLUS the
 *    profit not yet swept before the period (an unclosed prior year), over a calendar year or any
 *    date range.
 *
 * Fixture literals (KWD), real PostingService: FY2024 income 400.000 (never closed); FY2025
 * income 7,000.000 and expense 2,000.000 (profit 5,000.000).
 */
class X9PriorYearGuardAndEquityStatementTest extends X9rLedgerTestCase
{
    use \Tests\Feature\Accounting\Concerns\PostsXbrlLedger;
    use \Tests\Feature\Accounting\Concerns\GrantsAccountingModule;

    private function lockYearMonths(int $year): void
    {
        for ($m = 1; $m <= 12; $m++) {
            $this->setPeriod($year, $m, AccountingPeriod::STATUS_LOCKED);
        }
    }

    private function fy2025Trading(): void
    {
        $this->postJv('2025-06-15', $this->acc('1201'), $this->acc('4133'), 7000.0, 'fy25-sale');
        $this->postJv('2025-06-20', $this->acc('5222'), $this->acc('1201'), 2000.0, 'fy25-cost');
    }

    /** A legacy document (doc_type and posting_date NULL), written straight to the tables. */
    private function legacyDoc(string $date, int $debitAccount, int $creditAccount, string $amount): void
    {
        $id = (int) DB::table('transactions')->insertGetId([
            'company_id' => $this->company->id, 'entity_id' => $this->company->id, 'entity_type' => 'company',
            'transaction_type' => 'journal', 'amount' => $amount, 'total_debit' => $amount, 'total_credit' => $amount,
            'description' => 'x9 legacy fixture', 'reference_type' => 'Invoice', 'doc_type' => null, 'sub_type' => null,
            'reference_number' => 'X9-LEGACY-'.$date, 'posting_status' => 'posted', 'transaction_date' => $date.' 00:00:00',
            'posting_date' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([[$debitAccount, $amount, '0.000'], [$creditAccount, '0.000', $amount]] as [$account, $dr, $cr]) {
            DB::table('journal_entries')->insert([
                'company_id' => $this->company->id, 'transaction_id' => $id, 'account_id' => $account,
                'debit' => $dr, 'credit' => $cr, 'amount' => $amount, 'exchange_rate' => 1, 'currency' => 'KWD',
                'name' => 'x9 legacy', 'description' => 'x9 legacy line', 'transaction_date' => $date.' 00:00:00',
                'posting_date' => null, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /**
     * The guard: FY2024 carries 400.000 of income no YEC swept; FY2025 is fully locked. Closing
     * FY2025 is REFUSED, naming FY2024 and the amount, and nothing is posted. Once FY2024 is closed,
     * FY2025 closes.
     */
    public function test_closing_a_year_over_an_unclosed_prior_year_is_refused(): void
    {
        $this->postJv('2024-05-01', $this->acc('1201'), $this->acc('4133'), 400.0, 'fy24-sale');
        $this->fy2025Trading();
        $this->lockYearMonths(2025);

        $refused = app(YearEndCloseService::class)->run($this->company->id, 2025, $this->accountant->id);

        $this->assertFalse($refused['success']);
        $this->assertStringContainsString('Fiscal year 2024 still has 400.000 of profit/(loss) that no year-end close has swept. Close 2024 first', implode(' | ', $refused['blocking']));
        $this->assertCount(0, $this->yecRows(2025), 'nothing posted');

        $this->lockYearMonths(2024);
        $fy24 = app(YearEndCloseService::class)->run($this->company->id, 2024, $this->accountant->id);
        $this->assertTrue($fy24['success'], implode(' | ', $fy24['blocking']));
        $fy25 = app(YearEndCloseService::class)->run($this->company->id, 2025, $this->accountant->id);
        $this->assertTrue($fy25['success'], implode(' | ', $fy25['blocking']));
        $this->assertSame(5_400_000, $this->oracleCreditBalanceFils($this->re(), '2025-12-31'), 'both years swept, in order');
    }

    /**
     * The floor (§4.1 D-8): FY2024 holds only LEGACY rows (doc_type NULL) on this engine-on company,
     * so its earliest engine year is 2025; closing FY2025 is not blocked by the pre-ledger year.
     */
    public function test_the_guard_never_looks_before_the_earliest_ledger_year(): void
    {
        $this->legacyDoc('2024-05-01', $this->acc('1201')->id, $this->acc('4133')->id, '400.000');
        $this->fy2025Trading();
        $this->lockYearMonths(2025);

        $result = app(YearEndCloseService::class)->run($this->company->id, 2025, $this->accountant->id);

        $this->assertTrue($result['success'], 'blocked by a pre-ledger year: '.implode(' | ', $result['blocking']));
        $this->assertSame(5_000_000, $this->oracleCreditBalanceFils($this->re(), '2025-12-31'));
    }

    /**
     * H-M2 on the host statement: with FY2024's 400.000 unswept, FY2025 opens retained earnings at
     * 400.000 (component 0 + unswept 400.000) and closes at 5,400.000, which is the balance sheet's
     * equity; its independent ledger derivation still ties. Over 1 July to 31 December 2025 (a date
     * range) the opening carries everything swept by no close before 1 July: 5,400.000, and the
     * period's profit is 0.
     */
    public function test_the_equity_statement_opens_with_the_unswept_profit_of_an_unclosed_prior_year(): void
    {
        $this->postJv('2024-05-01', $this->acc('1201'), $this->acc('4133'), 400.0, 'fy24-sale');
        $this->fy2025Trading();

        $soce = app(EquityChangesReportService::class)->generate($this->company->id, 2025);

        $this->assertEqualsWithDelta(400.0, $soce['unswept_profit_before_period'], 0.0005);
        $this->assertEqualsWithDelta(400.0, $soce['components']['retained_earnings']['opening'], 0.0005, 'opening RE = component + unswept (H-M2)');
        $this->assertEqualsWithDelta(5400.0, $soce['components']['retained_earnings']['closing'], 0.0005);
        $this->assertEqualsWithDelta(($this->oracleUnsweptFils('2025-12-31') + $this->oracleCreditBalanceFils($this->re(), '2025-12-31')) / 1000, $soce['closing_equity_total'], 0.0005, 'the closing equity is the balance sheet equity (component + unswept)');
        $this->assertTrue($soce['checks']['ties_to_ledger_derivation']);

        $range = app(EquityChangesReportService::class)->generateForRange($this->company->id, Carbon::parse('2025-07-01'), Carbon::parse('2025-12-31'));
        $this->assertSame(['from' => '2025-07-01', 'to' => '2025-12-31'], $range['period']);
        $this->assertEqualsWithDelta(5400.0, $range['components']['retained_earnings']['opening'], 0.0005);
        $this->assertEqualsWithDelta(0.0, $range['net_profit'], 0.0005);
        $this->assertEqualsWithDelta(5400.0, $range['closing_equity_total'], 0.0005);
    }

    /** The screen takes `?from=&to=`: the range reaches the service. */
    public function test_the_equity_statement_screen_takes_a_date_range(): void
    {
        $this->fy2025Trading();
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $this->company->id]);
        $this->grantAccountingModule($this->company); // CT port (U1): CT gates the screen on the company's accounting module // the `module:accounting` gate reads the session company, as EquityStatementControllerTest does

        $response = $this->actingAs($admin)->get(route('accounting.reports.equity-changes', ['company_id' => $this->company->id, 'from' => '2025-07-01', 'to' => '2025-12-31']));

        $response->assertOk();
        $this->assertSame(['from' => '2025-07-01', 'to' => '2025-12-31'], $response->viewData('statement')['period']);
        $response->assertSee('period 2025-07-01 to 2025-12-31');
    }

    /**
     * X9b (X9-VERIFY m-4): FY2024's 400.000 was never closed; FY2025 IS closed. The screen names
     * the real cause, the earlier open year and its unswept amount, never "pending year-end close"
     * for a year that is closed; the header and the CSV state the period.
     */
    public function test_the_screen_words_an_open_earlier_year_as_such_and_states_the_period(): void
    {
        $this->legacyFreeGapYear();
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $this->company->id]);
        $this->grantAccountingModule($this->company); // CT port (U1): CT gates the screen on the company's accounting module

        $response = $this->actingAs($admin)->get(route('accounting.reports.equity-changes', ['company_id' => $this->company->id, 'year' => 2025]));

        $response->assertOk();
        $response->assertSee('An earlier year is not closed — 400.000 of profit/(loss) before 2025-01-01 is unswept', false);
        $response->assertDontSee('pending year-end close');
        $response->assertSee('fiscal year 2025 (2025-01-01 to 2025-12-31)');

        $csv = $this->actingAs($admin)->get(route('accounting.reports.equity-changes.export', ['company_id' => $this->company->id, 'from' => '2025-07-01', 'to' => '2025-12-31']));
        $csv->assertOk();
        $this->assertStringContainsString("Period: 2025-07-01 to 2025-12-31\n", $csv->getContent());
        $this->assertStringNotContainsString('Fiscal Year:', $csv->getContent(), 'a range is not labelled as a fiscal year');
        $this->assertStringContainsString('Profit/(loss) of earlier years not yet closed (in the opening),,"5,400.000"', $csv->getContent());
    }

    /** FY2024 income 400.000 never closed; FY2025 traded and CLOSED (the pre-guard gap state, written with the guard bypassed by closing FY2025's sweep directly). */
    private function legacyFreeGapYear(): void
    {
        $this->postJv('2024-05-01', $this->acc('1201'), $this->acc('4133'), 400.0, 'fy24-sale');
        $this->fy2025Trading();
        // The gap state predates the X9 guard; build it with a YEC written straight to the ledger.
        $this->postDoc($this->company->id, 'YEC', '2025-12-31', [
            [$this->acc('4133')->id, '7000.000', '0.000'], [$this->acc('5222')->id, '0.000', '2000.000'], [$this->re()->id, '0.000', '5000.000'],
        ]);
    }
}
