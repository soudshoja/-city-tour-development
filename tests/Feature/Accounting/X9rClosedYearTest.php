<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Exceptions\Accounting\ClosedYearProcedureException;
use App\Models\AccountingPeriod;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\ClosedYearAdjustmentService;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\YearEndCloseService;
use App\Services\ProfitLossService;
use App\Services\TrialBalanceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * XBRL-X9r (PLAN.md X9r, L25; H2 N-B2): the trap, the reopen-a-closed-year procedure, the YEC
 * re-run and isYearClosed(). Fixture and oracles: {@see X9rLedgerTestCase}.
 */
class X9rClosedYearTest extends X9rLedgerTestCase
{
    // ── N-B2: the trap, proved first and KEPT as its record ──────────────────────────────────

    /**
     * THE TRAP (PLAN.md §9 item 17). With FY2025 locked and YEC'd, a plain post of the 1,000.000
     * audit adjustment dated 15 Dec 2025 does NOT fail: PostingService silently moves it to the
     * earliest open period, 1 January 2026. FY2025 profit stays 5,000.000, FY2026 gains the
     * 1,000.000, and nothing errors. This test is kept as the record of that behaviour; the
     * procedure below is the only supported way to book into a closed year.
     */
    public function test_the_trap_a_plain_back_dated_post_silently_lands_in_january(): void
    {
        $this->closedFy2025();

        $posted = app(PostingService::class)->post($this->auditAdjustment(), $this->accountant->id)->transaction;
        $posted->refresh();

        $this->assertSame('2025-12-15', Carbon::parse($posted->transaction_date)->toDateString(), 'the document keeps the date it asked for');
        $this->assertSame('2026-01-01', Carbon::parse($posted->posting_date)->toDateString(), 'the trap: the post was silently shifted into January 2026');
        $this->assertSame(5_000_000, $this->oracleProfitFils('2025-01-01', '2025-12-31'), 'FY2025 profit did not move: the adjustment missed its year');
        $this->assertSame(1_000_000, $this->oracleProfitFils('2026-01-01', '2026-12-31'), 'the 1,000.000 landed in FY2026 instead');
    }

    /**
     * RED-BEFORE for X9r's run() change: reversing a YEC (the only engine path back into a closed
     * year) must let the year be closed again. On origin/main run() finds the reversed YEC and
     * answers already_closed, so a re-close can never sweep the corrected profit.
     */
    public function test_a_reversed_yec_does_not_count_as_closed_and_the_re_run_sweeps_the_corrected_profit(): void
    {
        $yec = $this->closedFy2025();

        $this->setPeriod(2025, 12, AccountingPeriod::STATUS_OPEN);
        app(PostingService::class)->reverse($yec, Carbon::parse('2025-12-31'), $this->accountant->id);
        app(PostingService::class)->post($this->auditAdjustment(), $this->accountant->id);
        $this->setPeriod(2025, 12, AccountingPeriod::STATUS_LOCKED);

        $rerun = app(YearEndCloseService::class)->run($this->company->id, 2025, $this->accountant->id);

        $this->assertTrue($rerun['success'], 'blocked: '.implode(' | ', $rerun['blocking']));
        // The corrected-profit assertion speaks first: if run() hands back the reversed YEC
        // (already_closed), its Retained Earnings line is the OLD 5,000.000, off by exactly the
        // 1,000.000 adjustment.
        $this->assertSame(
            6_000_000,
            $this->docLineFils($rerun['transaction']->id, $this->re()),
            'the YEC run() returns must credit Retained Earnings with the corrected 6,000.000'
        );
        $this->assertFalse($rerun['already_closed'], 'run() treated the REVERSED YEC #'.$yec->id.' as a live close');
        $this->assertNotSame($yec->id, $rerun['transaction']->id, 'run() handed back the reversed YEC');
        $this->assertSame("yec:{$this->company->id}:2025:replaces:{$yec->id}", $rerun['transaction']->idempotency_key, 'a fresh idempotency key, never yec:{c}:{y} again');
    }

    public function test_run_on_a_year_whose_yec_is_live_still_returns_already_closed(): void
    {
        $yec = $this->closedFy2025();

        $again = app(YearEndCloseService::class)->run($this->company->id, 2025, $this->accountant->id);

        $this->assertTrue($again['already_closed'], 'a live YEC must still short-circuit the close');
        $this->assertSame($yec->id, $again['transaction']->id, 'the live YEC itself is handed back');
        $this->assertCount(1, $this->yecRows(2025), 'no second YEC was posted');
    }

    // ── the procedure (L25 rule 2), through the two commands ─────────────────────────────────

    /**
     * Acceptance: through the procedure the adjustment lands dated 15 Dec 2025; FY2025 profit
     * moves by exactly 1,000.000; the new YEC sweeps the corrected profit; FY2026's opening
     * retained earnings move by exactly 1,000.000; December is locked again; the audit log holds
     * one reopen row per period and the reversal.
     */
    public function test_the_procedure_books_the_adjustment_into_its_own_year(): void
    {
        $yec = $this->closedFy2025();
        $reBefore = $this->oracleCreditBalanceFils($this->re(), '2025-12-31');
        $this->assertSame(5_000_000, $reBefore, 'fixture: FY2026 opening Retained Earnings before the adjustment');

        [$out, $exit] = $this->runCommand('accounting:reopen-year', [
            'company' => $this->company->id, 'year' => 2025,
            '--reason' => 'FY2025 audit adjustment', '--user' => $this->accountant->id,
        ]);
        $this->assertStringContainsString('Reopened 2025-12 (was locked).', $out);
        $this->assertStringContainsString("Reversed YEC #{$yec->id}", $out);
        $this->assertStringContainsString('accounting:reclose-year', $out, 'the command stops and prints the next steps');
        $this->assertSame(0, $exit, $out);

        $adjustment = app(PostingService::class)->post($this->auditAdjustment(), $this->accountant->id)->transaction->refresh();

        [$out, $exit] = $this->runCommand('accounting:reclose-year', [
            'company' => $this->company->id, 'year' => 2025, '--user' => $this->accountant->id,
        ]);
        $this->assertStringContainsString('Locked 2025-12.', $out);
        $this->assertStringContainsString('Year-end close posted: YEC #', $out);
        $this->assertStringContainsString('net profit/(loss) 6,000.000', $out);
        $this->assertStringContainsString('Fiscal year 2025 is closed again.', $out);
        $this->assertSame(0, $exit, $out);

        $this->assertProcedureOutcome($yec, (int) $adjustment->id, $reBefore);
    }

    /**
     * The same acceptance through ClosedYearAdjustmentService::postIntoClosedYear(), the atomic
     * form. MUTANT 2 lives here: posting the adjustment before the reopen.
     */
    public function test_post_into_closed_year_books_the_adjustment_atomically(): void
    {
        $yec = $this->closedFy2025();
        $reBefore = $this->oracleCreditBalanceFils($this->re(), '2025-12-31');

        $result = app(ClosedYearAdjustmentService::class)
            ->postIntoClosedYear($this->auditAdjustment(), $this->accountant->id, 'FY2025 audit adjustment');

        $this->assertProcedureOutcome($yec, $result['adjustment_transaction_id'], $reBefore);
    }

    private function assertProcedureOutcome(Transaction $oldYec, int $adjustmentId, int $reBefore): void
    {
        $this->assertSame(
            6_000_000,
            $this->oracleProfitFils('2025-01-01', '2025-12-31'),
            'FY2025 profit must be the corrected 6,000.000 (5,000.000 + exactly 1,000.000)'
        );

        $adjustment = DB::table('transactions')->where('id', $adjustmentId)->first();
        $this->assertSame('2025-12-15', Carbon::parse($adjustment->posting_date)->toDateString(), 'the adjustment must land in FY2025, on its own date');
        $this->assertSame(0, $this->oracleProfitFils('2026-01-01', '2026-12-31'), 'nothing leaked into FY2026');

        $yecs = $this->yecRows(2025);
        $this->assertCount(2, $yecs, 'the reversed YEC and exactly one new YEC');
        $new = $yecs->last();
        $this->assertNotSame($oldYec->id, (int) $new->id);
        $this->assertSame("yec:{$this->company->id}:2025:replaces:{$oldYec->id}", $new->idempotency_key);
        $this->assertNull($this->reversalOf((int) $new->id), 'the new YEC is live');
        $this->assertSame(6_000_000, $this->docLineFils((int) $new->id, $this->re()), 'the new YEC sweeps the corrected 6,000.000 to Retained Earnings');

        $reversal = $this->reversalOf($oldYec->id);
        $this->assertNotNull($reversal, 'the old YEC was reversed');
        $this->assertSame(['REV', 'YEC'], [$reversal->doc_type, $reversal->sub_type]);
        $this->assertSame('2025-12-31', Carbon::parse($reversal->posting_date)->toDateString(), 'reversal dated 31 December, inside the year');
        $this->assertSame('reversed', DB::table('transactions')->where('id', $oldYec->id)->value('posting_status'));

        $this->assertSame(0, $this->oracleUnsweptFils('2025-12-31'), 'every FY2025 P&L leaf is swept to zero again');
        $reAfter = $this->oracleCreditBalanceFils($this->re(), '2025-12-31');
        $this->assertSame(1_000_000, $reAfter - $reBefore, 'FY2026 opening Retained Earnings must move by exactly 1,000.000');

        for ($m = 1; $m <= 12; $m++) {
            $this->assertSame(AccountingPeriod::STATUS_LOCKED, $this->periodStatus(2025, $m), sprintf('2025-%02d must be locked again', $m));
        }

        $reopens = $this->auditRows('reopen');
        $this->assertCount(1, $reopens, 'one reopen row per reopened period (December only here)');
        $this->assertSame('FY2025 audit adjustment', $reopens->first()->reason);
        $this->assertSame('2025-12', $reopens->first()->posting_period);
        $reverses = $this->auditRows('reverse')->where('subject_id', $oldYec->id);
        $this->assertCount(1, $reverses, 'one reversal row for the old YEC');
        $this->assertCount(1, $this->auditRows(ClosedYearAdjustmentService::REOPEN_ACTION));
        $this->assertCount(1, $this->auditRows(ClosedYearAdjustmentService::RECLOSE_ACTION));

        // The P&L screen (ProfitLossService) must agree: the YEC, its reversal and the new YEC are
        // all excluded whole-document, so FY2025 shows 6,000.000, not 11,000.000.
        $pl = app(ProfitLossService::class)->generate($this->company->id, Carbon::parse('2025-01-01'), Carbon::parse('2025-12-31'));
        $this->assertEqualsWithDelta(6000.0, (float) $pl['totals']['net_income'], 0.0005, 'P&L FY2025 net income');

        // The trial balance's period movement agrees on the income leaf: 7,000 + 1,000 credit.
        $tb = app(TrialBalanceService::class)->generate($this->company->id, Carbon::parse('2025-01-01'), Carbon::parse('2025-12-31'));
        $income = collect($tb['accounts'])->first(fn ($row) => (int) $row->id === $this->acc('4133')->id);
        $this->assertNotNull($income, 'income leaf present in the trial balance');
        $this->assertEqualsWithDelta(8000.0, (float) $income->total_credit - (float) $income->total_debit, 0.0005, 'TB FY2025 period movement on 4133 (7,000 + the 1,000 adjustment)');
    }

    /**
     * FY2026's January and February are locked and March is soft-closed when the adjustment
     * arrives. The reopen must open them latest-first (March, February, January, then December
     * 2025), and the re-close must restore each one. MUTANT 3 lives here.
     */
    public function test_reclose_restores_every_later_period_to_its_status(): void
    {
        $this->closedFy2025();
        $this->postJv('2026-02-10', $this->acc('1201'), $this->acc('4133'), 300.0, 'fy26-sale');
        $this->setPeriod(2026, 1, AccountingPeriod::STATUS_LOCKED);
        $this->setPeriod(2026, 2, AccountingPeriod::STATUS_LOCKED);
        $this->setPeriod(2026, 3, AccountingPeriod::STATUS_SOFT_CLOSED);

        app(ClosedYearAdjustmentService::class)
            ->postIntoClosedYear($this->auditAdjustment(), $this->accountant->id, 'FY2025 audit adjustment');

        $this->assertSame(AccountingPeriod::STATUS_LOCKED, $this->periodStatus(2025, 12), '2025-12 must be locked again');
        $this->assertSame(AccountingPeriod::STATUS_LOCKED, $this->periodStatus(2026, 1), '2026-01 must be locked again');
        $this->assertSame(AccountingPeriod::STATUS_LOCKED, $this->periodStatus(2026, 2), '2026-02 must be locked again');
        $this->assertSame(AccountingPeriod::STATUS_SOFT_CLOSED, $this->periodStatus(2026, 3), '2026-03 must be soft-closed again');
        $this->assertSame('open (no row)', $this->periodStatus(2026, 4), '2026-04 was never closed and must not be touched');

        $this->assertSame(
            ['2026-03', '2026-02', '2026-01', '2025-12'],
            $this->auditRows('reopen')->pluck('posting_period')->all(),
            'one reopen row per period, latest first'
        );
        $this->assertSame(6_000_000, $this->oracleProfitFils('2025-01-01', '2025-12-31'), 'FY2025 corrected profit');
        $this->assertSame(300_000, $this->oracleProfitFils('2026-01-01', '2026-12-31'), 'FY2026 profit untouched');
    }

    public function test_reclose_is_idempotent(): void
    {
        $this->closedFy2025();
        $this->setPeriod(2026, 1, AccountingPeriod::STATUS_LOCKED);
        app(ClosedYearAdjustmentService::class)
            ->postIntoClosedYear($this->auditAdjustment(), $this->accountant->id, 'FY2025 audit adjustment');

        $before = $this->ledgerFingerprint();

        [$out, $exit] = $this->runCommand('accounting:reclose-year', [
            'company' => $this->company->id, 'year' => 2025, '--user' => $this->accountant->id,
        ]);

        $this->assertStringContainsString('Fiscal year 2025 was already closed again; nothing changed.', $out);
        $this->assertStringContainsString('was already re-closed after reopen #', $out);
        $this->assertSame(0, $exit, $out);
        $this->assertSame($before, $this->ledgerFingerprint(), 'a second reclose-year must change nothing');
    }

    /**
     * Two consecutive closed years: reopening FY2024 must open every FY2025 period and December
     * 2024, reverse ONLY FY2024's YEC, and leave FY2025's YEC live; the re-close re-locks all 13.
     */
    public function test_reopening_the_earlier_of_two_closed_years(): void
    {
        $this->postJv('2024-05-01', $this->acc('1201'), $this->acc('4133'), 400.0, 'fy24-sale');
        for ($m = 1; $m <= 12; $m++) {
            $this->setPeriod(2024, $m, AccountingPeriod::STATUS_LOCKED);
        }
        $fy24 = app(YearEndCloseService::class)->run($this->company->id, 2024, $this->accountant->id)['transaction'];
        $fy25 = $this->closedFy2025();

        app(ClosedYearAdjustmentService::class)->postIntoClosedYear(
            $this->draft('JV', '2024-12-20', [
                $this->line($this->acc('1201'), 'debit', 1000.0),
                $this->line($this->acc('4133'), 'credit', 1000.0),
            ], 'fy24-audit'),
            $this->accountant->id,
            'FY2024 audit adjustment',
        );

        $this->assertNotNull($this->reversalOf($fy24->id), 'FY2024 YEC reversed');
        $this->assertNull($this->reversalOf($fy25->id), 'FY2025 YEC untouched and live');
        $this->assertCount(13, $this->auditRows('reopen'), 'Dec 2024 plus the twelve FY2025 months');
        $this->assertSame(1_400_000, $this->oracleProfitFils('2024-01-01', '2024-12-31'), 'FY2024 corrected profit');
        $this->assertSame(5_000_000, $this->oracleProfitFils('2025-01-01', '2025-12-31'), 'FY2025 profit unchanged');
        $this->assertSame(1_400_000, $this->oracleCreditBalanceFils($this->re(), '2024-12-31'), 'RE at 31 Dec 2024');
        $this->assertSame(6_400_000, $this->oracleCreditBalanceFils($this->re(), '2025-12-31'), 'RE at 31 Dec 2025');
        for ($m = 1; $m <= 12; $m++) {
            $this->assertSame(AccountingPeriod::STATUS_LOCKED, $this->periodStatus(2025, $m), sprintf('2025-%02d must be locked again', $m));
        }
        $this->assertSame(AccountingPeriod::STATUS_LOCKED, $this->periodStatus(2024, 12), '2024-12 must be locked again');
    }

    // ── refusals: nothing half-done ──────────────────────────────────────────────────────────

    /** A reconciled line on the YEC: the reversal refuses, the procedure stops with that message and changes nothing. */
    public function test_reopen_refuses_a_reconciled_yec_line_and_changes_nothing(): void
    {
        $yec = $this->closedFy2025();
        $this->setPeriod(2026, 1, AccountingPeriod::STATUS_LOCKED);
        $lineId = (int) DB::table('journal_entries')->where('transaction_id', $yec->id)->orderBy('id')->value('id');
        DB::table('journal_entries')->where('id', $lineId)->update(['reconciled' => 1]);
        $before = $this->ledgerFingerprint();

        [$out, $exit] = $this->runCommand('accounting:reopen-year', [
            'company' => $this->company->id, 'year' => 2025,
            '--reason' => 'FY2025 audit adjustment', '--user' => $this->accountant->id,
        ]);

        $this->assertStringContainsString('Fiscal year 2025 was NOT reopened; nothing was changed.', $out);
        $this->assertStringContainsString("Transaction #{$yec->id} has reconciled and/or settled lines and cannot be reversed", $out);
        $this->assertStringContainsString('never forces a reversal', $out);
        $this->assertSame(1, $exit, $out);
        $this->assertSame($before, $this->ledgerFingerprint(), 'the refusal must leave periods, documents and the audit log exactly as they were');
        $this->assertNull($this->reversalOf($yec->id));
    }

    public function test_reopen_refuses_without_a_reason_or_the_permission(): void
    {
        $this->closedFy2025();
        $before = $this->ledgerFingerprint();

        [$out, $exit] = $this->runCommand('accounting:reopen-year', [
            'company' => $this->company->id, 'year' => 2025, '--user' => $this->accountant->id,
        ]);
        $this->assertStringContainsString('--reason= is required to reopen a closed year.', $out);
        $this->assertSame(1, $exit, $out);

        $agent = User::factory()->create(['role_id' => Role::AGENT]);
        [$out, $exit] = $this->runCommand('accounting:reopen-year', [
            'company' => $this->company->id, 'year' => 2025, '--reason' => 'x', '--user' => $agent->id,
        ]);
        $this->assertStringContainsString('This action requires the accounting.period.reopen permission.', $out);
        $this->assertSame(1, $exit, $out);

        $this->assertSame($before, $this->ledgerFingerprint());
    }

    public function test_an_adjustment_dated_in_a_still_locked_month_is_refused_whole(): void
    {
        $yec = $this->closedFy2025();
        $before = $this->ledgerFingerprint();

        try {
            app(ClosedYearAdjustmentService::class)->postIntoClosedYear(
                $this->draft('JV', '2025-06-30', [
                    $this->line($this->acc('1201'), 'debit', 1000.0),
                    $this->line($this->acc('4133'), 'credit', 1000.0),
                ], 'june-adjustment'),
                $this->accountant->id,
                'FY2025 audit adjustment',
            );
            $refused = null;
        } catch (ClosedYearProcedureException $e) {
            $refused = $e->getMessage();
        }

        $this->assertNotNull($refused, 'a June-dated adjustment must be refused (it would shift to December)');
        $this->assertStringContainsString('The adjustment dated 2025-06-30 would land on 2025-12-01', $refused);
        $this->assertSame($before, $this->ledgerFingerprint(), 'rolled back whole: no reopen, no reversal, no adjustment');
        $this->assertNull($this->reversalOf($yec->id));
    }

    // ── isClosed(), pinned booleans ──────────────────────────────────────────────────────────
    //
    // CT port (U1): Akeed pins these through the XBRL module's FinancialStatementsSource::
    // isYearClosed(), a thin calendar-year wrapper over YearEndCloseService::isClosed(). The module
    // is not part of U1 (it lands with U3), so the same booleans are pinned on isClosed() itself.
    // The wrapper's own date-range refusal moves with the module.

    public function test_is_year_closed_follows_the_reversed_yec_rule(): void
    {
        $yec = app(YearEndCloseService::class);
        $closed = fn (int $y) => $yec->isClosed($this->company->id, $y);

        $this->closedFy2025();
        $this->assertTrue($closed(2025), 'locked + live YEC: closed');

        app(ClosedYearAdjustmentService::class)->reopenYear($this->company->id, 2025, $this->accountant->id, 'audit');
        $this->assertFalse($closed(2025), 'YEC reversed by the reopen: NOT closed');

        $this->setPeriod(2025, 12, AccountingPeriod::STATUS_LOCKED);
        $this->assertFalse($closed(2025), 'December re-locked but the YEC not re-run: still NOT closed');

        app(ClosedYearAdjustmentService::class)->recloseYear($this->company->id, 2025, $this->accountant->id);
        $this->assertTrue($closed(2025), 're-closed: closed');

        for ($m = 1; $m <= 12; $m++) {
            $this->setPeriod(2027, $m, AccountingPeriod::STATUS_LOCKED);
        }
        $this->assertTrue($closed(2027), 'locked with no P&L activity: closed without a YEC');

        $this->postJv('2026-03-01', $this->acc('1201'), $this->acc('4133'), 50.0, 'fy26-sale');
        for ($m = 1; $m <= 12; $m++) {
            $this->setPeriod(2026, $m, AccountingPeriod::STATUS_LOCKED);
        }
        $this->assertFalse($closed(2026), 'locked with P&L activity and no YEC: NOT closed');

        $this->setPeriod(2025, 12, AccountingPeriod::STATUS_OPEN);
        $this->assertFalse($closed(2025), 'live YEC but December open: NOT closed');
    }
}
