<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtPortU1;

use App\Exceptions\Accounting\UnmappedPurposeException;
use App\Models\AccountingPeriod;
use App\Services\Accounting\ClosedYearAdjustmentService;
use App\Services\Accounting\ReserveAppropriationService;
use App\Services\Accounting\YearEndCloseService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Accounting\X9rLedgerTestCase;

/**
 * CT port, unit 1 (accounting-port-2026-09-30 PLAN §4.A A1): the engine follow-ups on City
 * Travelers' OWN chart, before the chart unit (U2) lands.
 *
 * City Travelers' CoaSeeder chart has no statutory or voluntary reserve leaf and no
 * STATUTORY_RESERVE / VOLUNTARY_RESERVE purpose (those arrive with Akeed's X8 chart additions,
 * which are U2 and held for PR #117). Akeed's own X9 tests plant those leaves; this file proves
 * what the ported code does WITHOUT them, on the chart CT dev actually has:
 *
 *   1. the reopen / post / re-close procedure (X9r) works end to end and says, by name, that the
 *      reserve appropriation was never run, rather than failing on the missing leaves;
 *   2. a profitable year's appropriation is REFUSED and writes nothing (no journal, no audit row);
 *   3. a loss year's nil appropriation records normally (no leaf is needed to record a nil);
 *   4. the database refuses a second live year-end close for one (company, year), and a reversed
 *      one leaves the constraint (the guard behind X9R-VERIFY B-1, built by hand, not through the
 *      service that normally prevents it).
 *
 * Every figure is read with X9rLedgerTestCase's own direct-SQL oracles, never the service under
 * test.
 */
class CtChartWithoutReserveLeavesTest extends X9rLedgerTestCase
{
    private function assertNoReservePurposes(): void
    {
        $this->assertSame(0, DB::table('system_accounts')
            ->where('company_id', $this->company->id)
            ->whereIn('purpose_code', ['STATUTORY_RESERVE', 'VOLUNTARY_RESERVE'])
            ->count(), 'fixture: CT chart without the U2 reserve purposes');
    }

    public function test_reopen_post_reclose_works_on_a_chart_without_reserve_leaves_and_names_the_skipped_appropriation(): void
    {
        $this->assertNoReservePurposes();
        $yec = $this->closedFy2025();
        $this->assertSame(5_000_000, $this->oracleCreditBalanceFils($this->re(), '2025-12-31'), 'fixture: FY2025 closed with 5,000.000');

        $service = app(ClosedYearAdjustmentService::class);
        $service->reopenYear($this->company->id, 2025, $this->accountant->id, 'audit');
        $this->assertNotNull($this->reversalOf($yec->id), 'the reopen reverses the live YEC');

        $this->postJv('2025-12-15', $this->acc('1201'), $this->acc('4133'), 1000.0, 'audit-adj');
        $result = $service->recloseYear($this->company->id, 2025, $this->accountant->id);

        $steps = implode(' | ', $result['steps']);
        $this->assertStringContainsString('Reserve appropriation: never run for 2025, so nothing was re-run.', $steps);
        $this->assertTrue(app(YearEndCloseService::class)->isClosed($this->company->id, 2025), 're-closed');
        $this->assertSame(6_000_000, $this->oracleCreditBalanceFils($this->re(), '2025-12-31'), 'Retained Earnings carries the corrected 6,000.000 once');
        $this->assertSame(0, $this->oracleUnsweptFils('2025-12-31'), 'nothing left unswept');
        $this->assertCount(1, $this->yecRows(2025)->filter(fn ($r) => $r->posting_status !== 'reversed' && $r->deleted_at === null), 'exactly one live YEC');
    }

    public function test_a_profitable_year_is_refused_without_reserve_leaves_and_nothing_is_written(): void
    {
        $this->assertNoReservePurposes();
        $this->postJv('2025-06-15', $this->acc('1201'), $this->acc('4133'), 7000.0, 'sale');
        $this->postJv('2025-06-20', $this->acc('5222'), $this->acc('1201'), 2000.0, 'cost');
        $this->assertSame(5_000_000, $this->oracleProfitFils('2025-01-01', '2025-12-31'), 'fixture profit');

        $figures = app(ReserveAppropriationService::class)->compute($this->company->id, 2025);
        $this->assertSame(500_000, $figures['statutory_due_fils'], 'what is due is still computed: 10% of 5,000.000');

        $before = $this->ledgerFingerprint() + ['audit' => $this->auditRows(ReserveAppropriationService::AUDIT_ACTION)->count()];
        $refused = null;
        try {
            app(ReserveAppropriationService::class)->appropriate($this->company->id, 2025, $this->accountant->id);
        } catch (UnmappedPurposeException $e) {
            $refused = $e->getMessage();
        }

        $this->assertNotNull($refused, 'a transfer that has no reserve leaf to go to must be refused');
        $this->assertStringContainsString('STATUTORY_RESERVE', $refused);
        $this->assertSame($before, $this->ledgerFingerprint() + ['audit' => $this->auditRows(ReserveAppropriationService::AUDIT_ACTION)->count()], 'refused whole: no APR journal, no audit row');
        $this->assertSame(0, DB::table('transactions')->where('company_id', $this->company->id)->where('doc_type', 'APR')->count());
    }

    public function test_a_loss_year_records_a_nil_appropriation_without_reserve_leaves(): void
    {
        $this->assertNoReservePurposes();
        $this->postJv('2025-06-20', $this->acc('5222'), $this->acc('1201'), 3250.0, 'loss');
        $this->assertSame(-3_250_000, $this->oracleProfitFils('2025-01-01', '2025-12-31'), 'fixture loss');

        $run = app(ReserveAppropriationService::class)->appropriate($this->company->id, 2025, $this->accountant->id);

        $this->assertNull($run['transaction_id'], 'a nil appropriation posts no journal');
        $this->assertSame('nil', $run['figures']['outcome']);
        $this->assertSame('loss', $run['figures']['nil_reason']);
        $this->assertCount(1, $this->auditRows(ReserveAppropriationService::AUDIT_ACTION), 'the nil is recorded once');
    }

    /**
     * The database guard (migration 2026_09_27_000001), constructed by hand: a second LIVE YEC
     * header for the same company and year is inserted directly, bypassing the service's row lock
     * and deterministic key. The unique index on (company_id, live_yec_year) must refuse it.
     * Then the first YEC is marked reversed, as PostingService::reverse() does, and the same
     * insert is accepted: a reversed YEC leaves the constraint.
     */
    public function test_the_database_refuses_a_second_live_year_end_close_and_a_reversed_one_frees_the_year(): void
    {
        $yec = $this->closedFy2025();
        $row = (array) DB::table('transactions')->where('id', $yec->id)->first();
        $this->assertSame(2025, (int) $row['live_yec_year'], 'fixture: the live YEC carries its year in the generated column');

        unset($row['id'], $row['live_yec_year']);
        $row['idempotency_key'] = 'ctport-u1:second-live-yec';
        $row['reference_number'] = 'YEC-DUP-2025';
        $row['payment_id'] = null;

        $refused = null;
        try {
            DB::transaction(fn () => DB::table('transactions')->insert($row));
        } catch (UniqueConstraintViolationException $e) {
            $refused = $e->getMessage();
        }
        $this->assertNotNull($refused, 'a second live YEC for FY2025 must be refused by the database');
        $this->assertStringContainsString('transactions_company_live_yec_year_unique', $refused);

        DB::table('transactions')->where('id', $yec->id)->update(['posting_status' => 'reversed']);
        $this->assertNull(DB::table('transactions')->where('id', $yec->id)->value('live_yec_year'), 'a reversed YEC leaves the index');

        DB::table('transactions')->insert($row);
        $this->assertSame(1, DB::table('transactions')->where('company_id', $this->company->id)->where('live_yec_year', 2025)->count(), 'the replacement is the one live YEC');

        // Leave the ledger as the invariants expect: the hand-built header has no lines.
        DB::table('transactions')->where('idempotency_key', 'ctport-u1:second-live-yec')->delete();
        DB::table('transactions')->where('id', $yec->id)->update(['posting_status' => 'posted']);
    }

    /**
     * The step-5b reversal guard in PostingService::post() (X9R-VERIFY m-2/m-5), which Akeed's
     * suite reaches only through the reopen procedure (which opens December first, so the guard
     * never fires there). Built by hand: reverse the live FY2025 YEC dated 31 December while every
     * month of 2025 is still LOCKED. Without the guard the reversal is silently moved to the next
     * open period (January 2026), leaving FY2025 "reopened" by a document that counts in FY2026.
     * It must be refused, and nothing written.
     */
    public function test_a_year_end_close_reversal_that_would_be_shifted_out_of_its_year_is_refused(): void
    {
        $yec = $this->closedFy2025();
        $this->assertSame(AccountingPeriod::STATUS_LOCKED, $this->periodStatus(2025, 12), 'fixture: December 2025 locked');
        $before = $this->ledgerFingerprint();

        $refused = null;
        try {
            app(\App\Services\Accounting\PostingService::class)->reverse($yec, \Illuminate\Support\Carbon::create(2025, 12, 31), $this->accountant->id);
        } catch (\App\Exceptions\Accounting\InvalidClosingReversalException $e) {
            $refused = $e->getMessage();
        }

        $this->assertNotNull($refused, 'a YEC reversal into a locked December must be refused, not shifted');
        $this->assertStringContainsString('The reversal of a YEC dated 2025-12-31 would be moved to 2026-01-01', $refused);
        $this->assertSame($before, $this->ledgerFingerprint(), 'nothing written');
        $this->assertNull($this->reversalOf($yec->id));
    }

    public function test_every_period_status_constant_the_procedure_uses_exists_on_ct(): void
    {
        // A cheap tripwire: the ported procedure writes these three statuses into CT's own
        // accounting_periods enum. If CT's enum ever lacked one, every test above would fail late
        // and far from the cause.
        foreach ([AccountingPeriod::STATUS_OPEN, AccountingPeriod::STATUS_SOFT_CLOSED, AccountingPeriod::STATUS_LOCKED] as $status) {
            $this->setPeriod(2031, 1, $status);
            $this->assertSame($status, $this->periodStatus(2031, 1));
        }
    }
}
