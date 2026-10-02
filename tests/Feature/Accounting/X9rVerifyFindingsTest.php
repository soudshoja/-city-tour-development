<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Exceptions\Accounting\InvalidClosingReversalException;
use App\Models\AccountingPeriod;
use App\Models\Transaction;
use App\Services\Accounting\ClosedYearAdjustmentService;
use App\Services\Accounting\PeriodCloseService;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\Reports\EquityChangesReportService;
use App\Services\Accounting\YearEndCloseService;
use App\Services\Accounting\BalanceSheetService;
use App\Services\Accounting\GeneralLedgerService;
use App\Services\Onboarding\Parity\LedgerFigures;
use App\Services\ProfitLossService;
use App\Services\TrialBalanceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * XBRL-X9r, the verifier's findings (X9R-VERIFY.md, 2026-09-27): B-1 (concurrent re-close),
 * M-1 (isYearClosed with a stale YEC), M-2 (every reader agrees after a cycle) and the MINORs
 * m-1 to m-4. Each test was run RED on 365cf5cf before its fix. Fixture and oracles:
 * {@see X9rLedgerTestCase}.
 */
class X9rVerifyFindingsTest extends X9rLedgerTestCase
{
    /** The live (unreversed) YEC idempotency keys for a year, straight from the table. */
    private function liveYecKeys(int $year): array
    {
        return DB::table('transactions as t')
            ->where('t.company_id', $this->company->id)
            ->where('t.doc_type', 'YEC')
            ->whereNull('t.deleted_at')
            ->whereYear('t.transaction_date', $year)
            ->whereNotExists(fn ($s) => $s->selectRaw('1')->from('transactions as r')
                ->whereColumn('r.reversal_of_transaction_id', 't.id')->whereNull('r.deleted_at'))
            ->orderBy('t.id')
            ->pluck('idempotency_key')
            ->all();
    }

    // ── B-1: two re-closes interleaved ───────────────────────────────────────────────────────

    /**
     * Process A commits a whole re-close after process B has built its sweep lines and before B
     * posts (the hook fires on B's branch lookup, which run() makes after buildClosingLines() and
     * before post() on both the old and the new code). On 365cf5cf B then took `:rev2` and posted
     * a second live YEC: Retained Earnings 12,000.000 instead of 6,000.000.
     */
    public function test_b1_an_interleaved_second_reclose_cannot_post_a_second_yec(): void
    {
        $this->closedFy2025();
        app(ClosedYearAdjustmentService::class)->reopenYear($this->company->id, 2025, $this->accountant->id, 'audit');
        app(PostingService::class)->post($this->auditAdjustment(), $this->accountant->id);
        $this->setPeriod(2025, 12, AccountingPeriod::STATUS_LOCKED);

        $fired = false;
        $companyId = $this->company->id;
        $userId = $this->accountant->id;
        DB::listen(function ($q) use (&$fired, $companyId, $userId) {
            if (! $fired && preg_match('/from [`"]?branches[`"]?/i', $q->sql) === 1) {
                $fired = true;
                app(YearEndCloseService::class)->run($companyId, 2025, $userId); // process A
            }
        });

        app(YearEndCloseService::class)->run($companyId, 2025, $userId); // process B

        $this->assertTrue($fired, 'the interleaving hook never fired');
        $this->assertSame(6_000_000, $this->oracleCreditBalanceFils($this->re(), '2025-12-31'), 'Retained Earnings must carry the corrected profit ONCE (12,000,000 = two live YECs)');
        $this->assertCount(1, $this->liveYecKeys(2025), 'exactly one live YEC for FY2025, got '.json_encode($this->liveYecKeys(2025)));
        $this->assertSame(0, $this->oracleUnsweptFils('2025-12-31'));
    }

    // ── M-1: a stale live YEC ───────────────────────────────────────────────────────────────

    /**
     * The verifier's exact path, with existing tools only: close FY2025; reopen December with
     * PeriodCloseService::reopen() (the period screen/command); post 1,000.000 dated 15 Dec;
     * re-lock December. The FY2025 YEC is still live but 1,000.000 is unswept. The year is NOT
     * closed, and the plain close must say so rather than answer "already closed".
     */
    public function test_m1_a_stale_live_yec_is_not_closed_and_the_close_says_why(): void
    {
        $this->closedFy2025();
        app(PeriodCloseService::class)->reopen($this->company->id, 2025, 12, $this->accountant->id, 'raw december reopen');
        $adj = app(PostingService::class)->post($this->auditAdjustment(), $this->accountant->id)->transaction->refresh();
        $this->assertSame('2025-12-15', Carbon::parse($adj->posting_date)->toDateString(), 'fixture: the post lands in December');
        $this->setPeriod(2025, 12, AccountingPeriod::STATUS_LOCKED);
        $this->assertSame(1_000_000, $this->oracleUnsweptFils('2025-12-31'), 'fixture: 1,000.000 of FY2025 profit unswept');

        $this->assertFalse(
            // CT port (U1): Akeed reads this through the XBRL module's isYearClosed() wrapper;
            // the module lands with U3, so the underlying service is asserted directly.
            app(YearEndCloseService::class)->isClosed($this->company->id, 2025),
            'isClosed(FY2025) must be false while 1,000.000 of FY2025 profit is unswept'
        );

        $run = app(YearEndCloseService::class)->run($this->company->id, 2025, $this->accountant->id);
        $this->assertFalse($run['success'], 'the close must not answer "already closed" over an unswept 1,000.000');
        $this->assertStringContainsString('accounting:reopen-year', implode(' | ', $run['blocking']));
        $this->assertStringContainsString('1,000.000', implode(' | ', $run['blocking']));
    }

    // ── M-2: every reader agrees after one cycle ────────────────────────────────────────────

    /**
     * After one reopen, adjust and re-close cycle on FY2025 (corrected profit 6,000.000), the P&L,
     * the TB, LedgerFigures (the parity oracle), the GL and the balance sheet must all say the
     * same, and the GL on 4133 must equal the TB. On 365cf5cf LedgerFigures said 11,000 and the GL
     * 15,000 on 4133 and -5,000 on Retained Earnings.
     */
    public function test_m2_all_five_readers_agree_after_a_reopen_cycle(): void
    {
        $this->closedFy2025();
        app(ClosedYearAdjustmentService::class)->postIntoClosedYear($this->auditAdjustment(), $this->accountant->id, 'audit');
        $c = $this->company->id;
        [$from, $to] = [Carbon::parse('2025-01-01'), Carbon::parse('2025-12-31')];

        $pl = (float) app(ProfitLossService::class)->generate($c, $from->copy(), $to->copy())['totals']['net_income'];

        $tb = app(TrialBalanceService::class)->generate($c, $from->copy(), $to->copy(), ['show_zero' => true]);
        $inc = $tb['grouped']['Income'];
        $exp = $tb['grouped']['Expenses'];
        $tbProfit = ((float) $inc['subtotal_credit'] - (float) $inc['subtotal_debit']) - ((float) $exp['subtotal_debit'] - (float) $exp['subtotal_credit']);
        $tb4133 = collect($tb['accounts'])->firstWhere('id', $this->acc('4133')->id);
        $tb4133Move = (float) $tb4133->total_credit - (float) $tb4133->total_debit;

        $plLeafIds = DB::table('accounts as a')->join('accounts as root', 'root.id', '=', 'a.root_id')
            ->where('a.company_id', $c)->whereIn('root.name', ['Income', 'Expenses'])->pluck('a.id')->map(fn ($id) => (int) $id)->all();
        $lfProfit = 0.0;
        foreach ((new LedgerFigures)->profitAndLoss($c, CarbonImmutable::parse('2025-01-01'), CarbonImmutable::parse('2025-12-31'), 'LEGACY_OJV') as $accountId => $row) {
            if (in_array((int) $accountId, $plLeafIds, true)) {
                $lfProfit += (float) $row['net_income'];
            }
        }

        $gl4133 = app(GeneralLedgerService::class)->generate($c, $this->acc('4133')->id, $from->copy(), $to->copy());
        $gl4133Move = (float) $gl4133['totals']['credit'] - (float) $gl4133['totals']['debit'];
        $glRe2026 = app(GeneralLedgerService::class)->generate($c, $this->re()->id, Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'));
        // FY2025 as a range is a PRE-closing view in both the TB and the GL (the closing family is
        // excluded from movement): Retained Earnings closes at 0 in both, never -5,000.
        $glRe2025 = app(GeneralLedgerService::class)->generate($c, $this->re()->id, $from->copy(), $to->copy());
        $tbRe = collect($tb['accounts'])->firstWhere('id', $this->re()->id);

        $bs = app(BalanceSheetService::class)->generate($c, Carbon::parse('2025-12-31'));
        $bsRe = 0.0;
        foreach ($bs['sections']['Equity']['groups'] as $group) {
            foreach ($group['accounts'] as $account) {
                if ((int) $account->id === $this->re()->id) {
                    $bsRe = (float) $account->balance;
                }
            }
        }

        $got = [
            'oracle' => $this->oracleProfitFils('2025-01-01', '2025-12-31') / 1000.0,
            'pl' => round($pl, 3),
            'tb' => round($tbProfit, 3),
            'ledger_figures' => round($lfProfit, 3),
            'gl_4133_minus_tb_4133' => round($gl4133Move - $tb4133Move, 3),
            'gl_re_opening_2026' => round((float) $glRe2026['opening_balance'], 3),
            'gl_re_closing_2025_minus_tb' => round((float) $glRe2025['totals']['closing_balance'] - (float) ($tbRe->closing_balance ?? 0), 3),
            'bs_re' => round($bsRe, 3),
            'bs_unswept' => round((float) $bs['net_profit'], 3),
            'bs_balanced' => $bs['totals']['is_balanced'],
        ];

        $this->assertSame([
            'oracle' => 6000.0,
            'pl' => 6000.0,
            'tb' => 6000.0,
            'ledger_figures' => 6000.0,
            'gl_4133_minus_tb_4133' => 0.0,
            'gl_re_opening_2026' => 6000.0,
            'gl_re_closing_2025_minus_tb' => 0.0,
            'bs_re' => 6000.0,
            'bs_unswept' => 0.0,
            'bs_balanced' => true,
        ], $got, 'every reader must agree after one reopen cycle');
        $this->assertEqualsWithDelta(8000.0, $gl4133Move, 0.0005, 'GL FY2025 movement on 4133 (7,000 + 1,000)');
    }

    // ── m-1: the equity statement shows a PPA in the restated opening, not as CY movement ───

    public function test_m1_minor_the_equity_statement_does_not_show_a_ppa_as_current_year_movement(): void
    {
        $this->closedFy2025();
        app(PostingService::class)->post($this->draft('PPA', '2026-01-01', [
            $this->line($this->acc('1201'), 'debit', 500.0),
            $this->line($this->re(), 'credit', 500.0),
        ], 'ppa'), $this->accountant->id);

        $soce = app(EquityChangesReportService::class)->generate($this->company->id, 2026);
        $re = $soce['components']['retained_earnings'];

        $this->assertEqualsWithDelta(0.0, (float) $re['movement'], 0.0005, 'a PPA is never a current-year RE movement (500 = it was)');
        $this->assertEqualsWithDelta(5500.0, (float) $re['opening'], 0.0005, 'the PPA restates the opening RE');
        $this->assertTrue($soce['checks']['ties_to_next_year_opening']);
        $this->assertTrue($soce['checks']['ties_to_ledger_derivation']);
    }

    // ── m-2: closing-document reversals keep their own date and rules ───────────────────────

    public function test_m2_minor_a_ppa_is_reversed_only_on_its_own_date(): void
    {
        $this->closedFy2025();
        $ppa = app(PostingService::class)->post($this->draft('PPA', '2026-01-01', [
            $this->line($this->acc('1201'), 'debit', 500.0),
            $this->line($this->re(), 'credit', 500.0),
        ], 'ppa'), $this->accountant->id)->transaction;

        $caught = null;
        try {
            app(PostingService::class)->reverse($ppa, Carbon::parse('2026-06-30'), $this->accountant->id);
        } catch (InvalidClosingReversalException $e) {
            $caught = $e;
        }
        $this->assertNotNull($caught, 'a PPA reversed on 30 June would leave the opening restated');
        $this->assertStringContainsString('A prior-period adjustment (PPA) is reversed only on its own date, 2026-01-01; got 2026-06-30.', $caught->getMessage());

        app(PostingService::class)->reverse($ppa, Carbon::parse('2026-01-01'), $this->accountant->id);
        $this->assertSame(
            [$this->acc('1201')->id => 5_000_000, $this->re()->id => -5_000_000],
            app(ClosedYearAdjustmentService::class)->openingPositionIncludingPpa($this->company->id, CarbonImmutable::create(2026, 1, 1)),
            'a PPA reversed on its own date leaves the plain opening'
        );
    }

    public function test_m2_minor_a_yec_is_reversed_only_inside_its_year_and_a_closing_reversal_is_not_reversed(): void
    {
        $yec = $this->closedFy2025();
        $this->setPeriod(2025, 12, AccountingPeriod::STATUS_OPEN);

        $caught = null;
        try {
            app(PostingService::class)->reverse($yec, Carbon::parse('2026-01-15'), $this->accountant->id);
        } catch (InvalidClosingReversalException $e) {
            $caught = $e;
        }
        $this->assertNotNull($caught, 'a YEC reversed in the next year');
        $this->assertStringContainsString('A year-end close (YEC) is reversed only inside its own fiscal year (2025); got 2026-01-15.', $caught->getMessage());

        $reversal = app(PostingService::class)->reverse($yec, Carbon::parse('2025-12-31'), $this->accountant->id)->transaction;

        $caught = null;
        try {
            app(PostingService::class)->reverse(Transaction::withoutGlobalScopes()->findOrFail($reversal->id), Carbon::parse('2025-12-31'), $this->accountant->id);
        } catch (InvalidClosingReversalException $e) {
            $caught = $e;
        }
        $this->assertNotNull($caught, 'reversing the reversal of a YEC');
        $this->assertStringContainsString('is the reversal of a YEC and is not itself reversed', $caught->getMessage());
    }

    // ── m-3: a second reopen gets a clean refusal ───────────────────────────────────────────

    public function test_m3_minor_reopening_an_already_reopened_year_is_refused_cleanly(): void
    {
        $this->closedFy2025();
        app(ClosedYearAdjustmentService::class)->reopenYear($this->company->id, 2025, $this->accountant->id, 'audit');
        $before = $this->ledgerFingerprint();

        [$out, $exit] = $this->runCommand('accounting:reopen-year', [
            'company' => $this->company->id, 'year' => 2025, '--reason' => 'again', '--user' => $this->accountant->id,
        ]);

        $this->assertStringContainsString('Fiscal year 2025 was NOT reopened; nothing was changed.', $out);
        $this->assertStringContainsString('is already reopened', $out);
        $this->assertStringContainsString('accounting:reclose-year', $out);
        $this->assertSame(1, $exit, $out);
        $this->assertSame($before, $this->ledgerFingerprint());
    }

    // ── m-4: re-close never replays a finished reopen ───────────────────────────────────────

    public function test_m4_minor_reclose_does_not_replay_a_finished_reopen(): void
    {
        $this->closedFy2025();
        $this->setPeriod(2026, 1, AccountingPeriod::STATUS_LOCKED);
        app(ClosedYearAdjustmentService::class)->postIntoClosedYear($this->auditAdjustment(), $this->accountant->id, 'audit');
        $this->assertSame(AccountingPeriod::STATUS_LOCKED, $this->periodStatus(2026, 1), 'fixture: restored');

        app(PeriodCloseService::class)->reopen($this->company->id, 2026, 1, $this->accountant->id, 'January reopened on purpose');

        [$out, $exit] = $this->runCommand('accounting:reclose-year', [
            'company' => $this->company->id, 'year' => 2025, '--user' => $this->accountant->id,
        ]);

        $this->assertStringContainsString('already re-closed', $out);
        $this->assertSame(0, $exit, $out);
        $this->assertSame(AccountingPeriod::STATUS_OPEN, $this->periodStatus(2026, 1), 'a finished reopen log must not re-lock a period reopened on purpose since');
    }
}
