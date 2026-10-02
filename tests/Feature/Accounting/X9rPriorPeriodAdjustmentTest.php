<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Exceptions\Accounting\InvalidPriorPeriodAdjustmentException;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Services\Accounting\ClosedYearAdjustmentService;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\PostingService;
use App\Services\ProfitLossService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * XBRL-X9r (PLAN.md X9r, L15 route (ii), L25 rule 3): the `PPA` (prior-period adjustment, IAS 8)
 * document type. Fixture: {@see X9rLedgerTestCase} (FY2025 closed, profit 5,000.000) plus FY2026
 * trading: a sale of 3,000 and a cost of 1,000 in February, and a 70.000 sale dated 1 January 2026
 * (an ordinary current-year document dated the same day as the PPA, which must stay OUT of the
 * opening position). FY2026 profit therefore 2,070.000.
 */
class X9rPriorPeriodAdjustmentTest extends X9rLedgerTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->closedFy2025();
        $this->postJv('2026-01-01', $this->acc('1201'), $this->acc('4133'), 70.0, 'fy26-new-year-sale');
        $this->postJv('2026-02-10', $this->acc('1201'), $this->acc('4133'), 3000.0, 'fy26-sale');
        $this->postJv('2026-02-12', $this->acc('5222'), $this->acc('1201'), 1000.0, 'fy26-cost');
    }

    /** The IAS 8 restatement of the acceptance: Dr bank 500 / Cr opening Retained Earnings 500, dated 1 Jan 2026. */
    private function ppaDraft(string $date = '2026-01-01', ?string $creditCode = null): DocumentDraft
    {
        return $this->draft('PPA', $date, [
            $this->line($this->acc('1201'), 'debit', 500.0),
            $this->line($creditCode === null ? $this->re() : $this->acc($creditCode), 'credit', 500.0),
        ], 'ppa-'.$date.'-'.($creditCode ?? 're'));
    }

    private function plNetIncome(string $from, string $to): float
    {
        return (float) app(ProfitLossService::class)->generate($this->company->id, Carbon::parse($from), Carbon::parse($to))['totals']['net_income'];
    }

    /**
     * Acceptance: a PPA of 500.000 dated 1 Jan 2026 changes the FY2026 opening position by exactly
     * 500.000 and the FY2026 P&L by zero.
     *
     * Expected opening, worked by hand: bank 7,000 − 2,000 = 5,000 at 31 Dec 2025, plus the PPA's
     * 500 = 5,500.000 debit; Retained Earnings 5,000 swept by the FY2025 YEC plus the PPA's 500 =
     * 5,500.000 credit; every P&L leaf swept to zero; the 70.000 JV dated 1 Jan is CY movement and
     * is NOT in the opening.
     */
    public function test_a_ppa_moves_the_opening_position_and_not_the_current_year_profit(): void
    {
        $service = app(ClosedYearAdjustmentService::class);
        $cyStart = CarbonImmutable::create(2026, 1, 1);

        $this->assertSame(
            [$this->acc('1201')->id => 5_000_000, $this->re()->id => -5_000_000],
            $service->openingPositionIncludingPpa($this->company->id, $cyStart),
            'before the PPA: the plain opening (the 70.000 JV dated 1 Jan is not part of it)'
        );
        $this->assertEqualsWithDelta(2070.0, $this->plNetIncome('2026-01-01', '2026-12-31'), 0.0005, 'FY2026 P&L before the PPA');

        $ppa = app(PostingService::class)->post($this->ppaDraft(), $this->accountant->id)->transaction->refresh();
        $this->assertSame(['PPA', '2026-01-01'], [$ppa->doc_type, Carbon::parse($ppa->posting_date)->toDateString()]);

        $this->assertSame(
            [$this->acc('1201')->id => 5_500_000, $this->re()->id => -5_500_000],
            $service->openingPositionIncludingPpa($this->company->id, $cyStart),
            'after the PPA: bank and opening Retained Earnings each move by exactly 500.000'
        );
        $this->assertEqualsWithDelta(2070.0, $this->plNetIncome('2026-01-01', '2026-12-31'), 0.0005, 'FY2026 P&L unchanged by the PPA');
        $this->assertSame(2_070_000, $this->oracleProfitFils('2026-01-01', '2026-12-31'), 'oracle: FY2026 profit');
        $this->assertSame(5_000_000, $this->oracleProfitFils('2025-01-01', '2025-12-31'), 'oracle: FY2025 profit untouched (the year stays closed)');
    }

    /**
     * MUTANT 4's target. PostingService refuses a PPA with a P&L line (next test), so this row is
     * written DIRECTLY, and labelled so: it stands for a PPA-typed document that reached the ledger
     * some other way (an import, data written before X9r, a future writer that bypasses the
     * engine). Dr bank 500 / Cr 4133 income 500, dated 1 Jan 2026, engine-shaped header. The P&L
     * must exclude it whole-document: FY2026 stays 2,070.000, never 2,570.000.
     */
    public function test_the_pnl_excludes_ppa_lines_even_on_an_income_leaf(): void
    {
        $date = Carbon::parse('2026-01-01');
        $txn = Transaction::forceCreate([
            'company_id' => $this->company->id, 'branch_id' => $this->branch->id,
            'entity_id' => $this->company->id, 'entity_type' => 'company',
            'transaction_type' => 'PPA', 'amount' => 500, 'description' => 'x9r direct-insert PPA fixture',
            'reference_type' => 'Invoice', 'reference_number' => 'X9R-PPA-DIRECT', 'name' => 'x9r',
            'transaction_date' => $date, 'posting_date' => $date,
            'doc_type' => 'PPA', 'doc_year' => 2026, 'posting_status' => 'posted',
            'total_debit' => 500, 'total_credit' => 500, 'idempotency_key' => 'x9r:ppa-direct',
        ]);

        foreach ([[$this->acc('1201'), 500.0, 0.0], [$this->acc('4133'), 0.0, 500.0]] as [$account, $dr, $cr]) {
            JournalEntry::create([
                'transaction_id' => $txn->id, 'company_id' => $this->company->id, 'branch_id' => $this->branch->id,
                'account_id' => $account->id, 'transaction_date' => $date, 'posting_date' => $date,
                'description' => 'x9r direct-insert PPA fixture', 'debit' => $dr, 'credit' => $cr, 'name' => $account->name,
                'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => max($dr, $cr),
                'voucher_number' => 'X9R', 'type_reference_id' => $this->company->id,
            ]);
        }

        $this->assertEqualsWithDelta(
            2070.0,
            $this->plNetIncome('2026-01-01', '2026-12-31'),
            0.0005,
            'FY2026 P&L must exclude the PPA document (2,570.000 = the PPA income line was counted)'
        );
    }

    public function test_a_ppa_on_a_profit_and_loss_leaf_is_refused_and_writes_nothing(): void
    {
        $before = $this->ledgerFingerprint();
        $caught = null;

        try {
            app(PostingService::class)->post($this->ppaDraft('2026-01-01', '4133'), $this->accountant->id);
        } catch (InvalidPriorPeriodAdjustmentException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught, 'a PPA crediting an income leaf must be refused');
        $this->assertStringContainsString('A prior-period adjustment (PPA) may not post to a profit-and-loss account: line 1 targets 4133', $caught->getMessage());
        $this->assertSame($before, $this->ledgerFingerprint(), 'nothing written');
    }

    public function test_a_ppa_not_dated_the_first_of_january_is_refused(): void
    {
        $caught = null;

        try {
            app(PostingService::class)->post($this->ppaDraft('2026-01-02'), $this->accountant->id);
        } catch (InvalidPriorPeriodAdjustmentException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertStringContainsString('must be dated the first day of the fiscal year (1 January); got 2026-01-02', $caught->getMessage());
    }

    /** Into a LOCKED January the engine would silently move the PPA to February; it must refuse instead. */
    public function test_a_ppa_into_a_locked_january_is_refused_not_shifted(): void
    {
        $this->setPeriod(2026, 1, AccountingPeriod::STATUS_LOCKED);
        $before = $this->ledgerFingerprint();
        $caught = null;

        try {
            app(PostingService::class)->post($this->ppaDraft(), $this->accountant->id);
        } catch (InvalidPriorPeriodAdjustmentException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught, 'the PPA must not be shifted into February');
        $this->assertStringContainsString('dated 2026-01-01 would be moved to 2026-02-01', $caught->getMessage());
        $this->assertSame($before, $this->ledgerFingerprint(), 'nothing written');
        $this->assertSame(0, DB::table('transactions')->where('company_id', $this->company->id)->where('doc_type', 'PPA')->count());
    }

    public function test_the_opening_position_is_taken_on_the_first_of_january_only(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The opening position is taken at the first day of a fiscal year (1 January); got 2026-04-01.');

        app(ClosedYearAdjustmentService::class)->openingPositionIncludingPpa($this->company->id, CarbonImmutable::create(2026, 4, 1));
    }
}
