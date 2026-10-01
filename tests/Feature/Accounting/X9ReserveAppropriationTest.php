<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Events\Accounting\ReserveAppropriationRecorded;
use App\Exceptions\Accounting\ReserveAppropriationException;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Role;
use App\Models\SystemAccount;
use App\Models\User;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\ReserveAppropriationService;
use App\Services\Accounting\YearEndCloseService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;

/**
 * XBRL X9 (PLAN.md L13, X9, L25 rule 1; H-M3, H m4, H2 N-m7, N-m3): the year-end reserve
 * appropriation, on hand-computed ledgers posted through the real PostingService, with every
 * figure checked against the direct-SQL oracles of {@see X9rLedgerTestCase} (their own
 * LedgerSource conjunction, never the service under test) and pinned literals.
 *
 * Ledgers (KWD): a profit year (capital 100,000, profit 50,000: statutory 5,000); a loss year
 * (3,250) and a zero-profit year; a profit that turns into a loss after the appropriation; the
 * 50%-of-capital cap at 50% + 1 fils (nil) and exactly 50% (due); a first-year company; a
 * voluntary rate; a locked and a soft-closed December; a reopen/re-close cycle.
 *
 * CLI refusals are asserted per L21: Artisan::call(), the output first, then the side effects,
 * the exit code last.
 */
class X9ReserveAppropriationTest extends X9rLedgerTestCase
{
    /**
     * CT port (U1): what the XBRL module's AkeedReserveAppropriationListener would have written to
     * `xbrl_filing_events`, captured in memory with the identical mapping (the module lands with
     * U3). Same shape as Akeed's DB read, so every assertion below is unchanged.
     *
     * @var list<array{type: string, year: int, filing: null, reason: ?string, payload: array<string, int|null>}>
     */
    private array $recordedFilingEvents = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->plantReserveLeaves();

        Event::listen(ReserveAppropriationRecorded::class, function (ReserveAppropriationRecorded $event): void {
            $payload = [
                'profit_fils' => $event->profitFils,
                'statutory_fils' => $event->statutoryFils,
                'voluntary_fils' => $event->voluntaryFils,
                'transaction_id' => $event->transactionId,
            ];
            if ($event->outcome === ReserveAppropriationRecorded::OUTCOME_NIL) {
                $this->recordedFilingEvents[] = ['type' => 'nil_appropriation', 'year' => $event->fiscalYear, 'filing' => null, 'reason' => $event->nilReason, 'payload' => $payload];

                return;
            }
            if ($event->totalFils() > 0) {
                $this->recordedFilingEvents[] = ['type' => 'appropriation_posted', 'year' => $event->fiscalYear, 'filing' => null, 'reason' => null, 'payload' => ['amount_fils' => $event->totalFils()] + $payload];
            }
        });
    }

    /**
     * CT port (U1): City Travelers' chart has no reserve leaves until the chart unit (U2) lands, so
     * this fixture plants the two rows Akeed's X8 canonical chart carries (3500 Statutory Reserve,
     * 3600 Voluntary Reserve, level 2 under the Equity root) and maps their purposes, exactly as
     * SystemAccountsSeeder does on an X8 chart. Fixture data only: no seeder or chart change.
     */
    private function plantReserveLeaves(): void
    {
        $equity = Account::withoutGlobalScopes()->where('company_id', $this->company->id)->where('code', '3000')->whereNull('parent_id')->firstOrFail();

        foreach ([['3500', 'Statutory Reserve', 'STATUTORY_RESERVE'], ['3600', 'Voluntary Reserve', 'VOLUNTARY_RESERVE']] as [$code, $name, $purpose]) {
            $leaf = Account::withoutGlobalScopes()->create([
                'company_id' => $this->company->id,
                'code' => $code,
                'name' => $name,
                'level' => 2,
                'parent_id' => $equity->id,
                'root_id' => $equity->id,
                'report_type' => $equity->report_type,
                'account_type' => null,
                'actual_balance' => 0,
                'budget_balance' => 0,
                'variance' => 0,
            ]);
            SystemAccount::query()->updateOrCreate(
                ['company_id' => $this->company->id, 'purpose_code' => $purpose, 'service_type' => null],
                ['account_id' => $leaf->id],
            );
        }
    }

    private function service(): ReserveAppropriationService
    {
        return app(ReserveAppropriationService::class);
    }

    private function trade(int $year, float $income, float $expense, string $key): void
    {
        if ($income > 0) {
            $this->postJv("{$year}-06-15", $this->acc('1201'), $this->acc('4133'), $income, "{$key}-sale");
        }
        if ($expense > 0) {
            $this->postJv("{$year}-06-20", $this->acc('5222'), $this->acc('1201'), $expense, "{$key}-cost");
        }
    }

    private function lockMonths(int $year, int $to, string $status = AccountingPeriod::STATUS_LOCKED): void
    {
        for ($m = 1; $m <= $to; $m++) {
            $this->setPeriod($year, $m, $status);
        }
    }

    /** The APR documents of the company, oldest first, straight from the table. */
    private function aprRows(): \Illuminate\Support\Collection
    {
        return DB::table('transactions')->where('company_id', $this->company->id)->where('doc_type', 'APR')->whereNull('deleted_at')->orderBy('id')->get();
    }

    /** The filing-trail events the module would have recorded (see setUp()), oldest first. */
    private function filingEvents(): \Illuminate\Support\Collection
    {
        return collect($this->recordedFilingEvents);
    }

    private function statutoryFils(string $asOf): int
    {
        return $this->oracleCreditBalanceFils($this->acc('3500'), $asOf);
    }

    /**
     * A profit year (L13 acceptance): capital 100,000.000, FY2025 profit 50,000.000; run before the
     * December lock. Statutory 10% = 5,000.000 posted dated 31 December 2025 (never January),
     * retained earnings 5,000.000 lower; one `appropriation_posted` event (filing_id NULL) and one
     * audit row. A re-run with nothing changed writes nothing.
     */
    public function test_a_profit_year_transfers_ten_percent_dated_the_year_end_and_is_idempotent(): void
    {
        $this->postJv('2025-01-01', $this->acc('1201'), $this->acc('3100'), 100000.0, 'capital');
        $this->trade(2025, 70000.0, 20000.0, 'fy25');
        $this->lockMonths(2025, 11);
        $this->assertSame(50_000_000, $this->oracleProfitFils('2025-01-01', '2025-12-31'), 'fixture profit');

        $run = $this->service()->appropriate($this->company->id, 2025, $this->accountant->id);

        $this->assertSame(5_000_000, $this->statutoryFils('2025-12-31'), 'statutory reserve for FY2025 = 10% of 50,000.000');
        $this->assertSame(-5_000_000, $this->oracleCreditBalanceFils($this->re(), '2025-12-31'), 'retained earnings 5,000.000 lower (before the YEC)');
        $this->assertSame(0, $this->statutoryFils('2025-12-30'), 'nothing before the year-end date');
        $apr = $this->aprRows();
        $this->assertCount(1, $apr);
        $this->assertSame('2025-12-31', Carbon::parse($apr[0]->posting_date)->toDateString(), 'the journal is dated the year-end, not January');
        $this->assertSame("appr:{$this->company->id}:2025", $apr[0]->idempotency_key);
        $this->assertSame([['type' => 'appropriation_posted', 'year' => 2025, 'filing' => null, 'reason' => null, 'payload' => [
            'amount_fils' => 5_000_000, 'profit_fils' => 50_000_000, 'statutory_fils' => 5_000_000, 'voluntary_fils' => 0, 'transaction_id' => (int) $apr[0]->id,
        ]]], $this->filingEvents()->all());
        $this->assertSame((int) $apr[0]->id, $run['transaction_id']);
        $this->assertFalse($this->service()->isStale($this->company->id, 2025));

        $before = $this->ledgerFingerprint() + ['events' => $this->filingEvents()->count()];
        $again = $this->service()->appropriate($this->company->id, 2025, $this->accountant->id);
        $this->assertSame($before, $this->ledgerFingerprint() + ['events' => $this->filingEvents()->count()], 'a re-run with nothing changed writes nothing');
        $this->assertNull($again['transaction_id']);

        // The YEC is unaffected (equity-to-equity): it sweeps the full 50,000.000.
        $this->setPeriod(2025, 12, AccountingPeriod::STATUS_LOCKED);
        $yec = app(YearEndCloseService::class)->run($this->company->id, 2025, $this->accountant->id);
        $this->assertTrue($yec['success'], implode(' | ', $yec['blocking']));
        $this->assertSame(45_000_000, $this->oracleCreditBalanceFils($this->re(), '2025-12-31'), 'retained earnings after the YEC: 50,000 - 5,000');
    }

    /**
     * Stale re-computation (L13; L25 rule 1): an audit adjustment of +1,000.000 income posted into
     * the still-open December after the appropriation makes it stale; the re-run posts ONLY the
     * difference, +100.000, as a second APR journal (`appr:{c}:2025:2`), both dated 31 December.
     */
    public function test_a_changed_profit_is_re_computed_by_posting_the_difference(): void
    {
        $this->postJv('2025-01-01', $this->acc('1201'), $this->acc('3100'), 100000.0, 'capital');
        $this->trade(2025, 70000.0, 20000.0, 'fy25');
        $this->service()->appropriate($this->company->id, 2025, $this->accountant->id);

        $this->postJv('2025-12-15', $this->acc('1201'), $this->acc('4133'), 1000.0, 'audit');
        $this->assertTrue($this->service()->isStale($this->company->id, 2025), 'the checklist sees the appropriation as stale');

        $this->service()->appropriate($this->company->id, 2025, $this->accountant->id);

        $this->assertSame(5_100_000, $this->statutoryFils('2025-12-31'), '10% of 51,000.000');
        $apr = $this->aprRows();
        $this->assertCount(2, $apr);
        $this->assertSame(100_000, $this->docLineFils((int) $apr[1]->id, $this->acc('3500')), 'the correction is the difference, +100.000');
        $this->assertSame("appr:{$this->company->id}:2025:2", $apr[1]->idempotency_key);
        $this->assertSame(['2025-12-31', '2025-12-31'], $apr->map(fn ($t) => Carbon::parse($t->posting_date)->toDateString())->all());
        $this->assertSame([5_000_000, 5_100_000], $this->filingEvents()->map(fn ($e) => $e['payload']['amount_fils'])->all());
        $this->assertFalse($this->service()->isStale($this->company->id, 2025));
    }

    /**
     * A LOSS year (L13; the Akeed FY2026 shape): sales 10,000.000, costs 13,250.000, a loss of
     * 3,250.000. No journal; a `nil_appropriation` event (reason `loss`, profit -3,250,000 fils,
     * filing_id NULL) that satisfies the checklist; the statutory reserve moves by 0. A re-run adds
     * nothing. (MUTANT 6: 10% of the negative profit posted, a debit to the reserve; MUTANT 7: a
     * loss treated as a refusal.)
     */
    public function test_a_loss_year_posts_no_journal_and_records_a_nil_appropriation(): void
    {
        $this->postJv('2026-01-01', $this->acc('1201'), $this->acc('3100'), 10000.0, 'capital');
        $this->trade(2026, 10000.0, 13250.0, 'fy26');
        $this->assertSame(-3_250_000, $this->oracleProfitFils('2026-01-01', '2026-12-31'), 'fixture loss');

        $run = $this->service()->appropriate($this->company->id, 2026, $this->accountant->id);

        $this->assertSame(0, $this->statutoryFils('2026-12-31'), 'the statutory reserve moves by 0 on a loss');
        $this->assertCount(0, $this->aprRows(), 'no journal on a loss');
        $this->assertSame([['type' => 'nil_appropriation', 'year' => 2026, 'filing' => null, 'reason' => 'loss', 'payload' => [
            'profit_fils' => -3_250_000, 'statutory_fils' => 0, 'voluntary_fils' => 0, 'transaction_id' => null,
        ]]], $this->filingEvents()->all(), 'the nil appropriation is recorded and satisfies the checklist');
        $this->assertNull($run['transaction_id']);
        $this->assertSame('nil', $this->auditRows(ReserveAppropriationService::AUDIT_ACTION)->map(fn ($r) => json_decode((string) $r->after, true)['outcome'])->sole());
        $this->assertFalse($this->service()->isStale($this->company->id, 2026));

        $this->service()->appropriate($this->company->id, 2026, $this->accountant->id);
        $this->assertCount(1, $this->filingEvents(), 'a re-run records nothing new');
    }

    /** A zero-profit year: sales equal costs; no journal, a nil appropriation with the reason `zero_profit`. */
    public function test_a_zero_profit_year_records_a_nil_appropriation(): void
    {
        $this->trade(2026, 5000.0, 5000.0, 'fy26');

        $this->service()->appropriate($this->company->id, 2026, $this->accountant->id);

        $event = $this->filingEvents()->sole();
        $this->assertSame(['nil_appropriation', 'zero_profit', 0], [$event['type'], $event['reason'], $event['payload']['profit_fils']]);
        $this->assertCount(0, $this->aprRows());
    }

    /**
     * A profit (1,000.000; statutory 100.000) that an audit adjustment in December turns into a loss
     * (-500.000): the re-run corrects the year back to a NIL transfer (Dr statutory 100 / Cr retained
     * earnings 100), never a negative transfer, and records the nil appropriation.
     */
    public function test_a_profit_that_becomes_a_loss_is_corrected_back_to_nil_never_below(): void
    {
        $this->postJv('2025-01-01', $this->acc('1201'), $this->acc('3100'), 10000.0, 'capital');
        $this->trade(2025, 3000.0, 2000.0, 'fy25');
        $this->service()->appropriate($this->company->id, 2025, $this->accountant->id);
        $this->assertSame(100_000, $this->statutoryFils('2025-12-31'));

        $this->postJv('2025-12-20', $this->acc('5222'), $this->acc('1201'), 1500.0, 'audit-loss');
        $this->service()->appropriate($this->company->id, 2025, $this->accountant->id);

        $this->assertSame(0, $this->statutoryFils('2025-12-31'), 'back to nil, not -50.000');
        $this->assertSame(0, $this->oracleCreditBalanceFils($this->re(), '2025-12-31'), 'retained earnings restored');
        $this->assertSame(['appropriation_posted', 'nil_appropriation'], $this->filingEvents()->pluck('type')->all());
        $this->assertSame(-500_000, $this->filingEvents()->last()['payload']['profit_fils']);
    }

    /**
     * The cap (L13, H m4, N-m7): capital 100,000.000 and the statutory reserve at 50% + 1 fils
     * (50,000.001) when FY2025 opens: NO statutory transfer on a profit of 50,000.000 (pinned 0), no
     * journal. (MUTANT 3: the cap lifted.)
     */
    public function test_the_cap_a_year_that_opens_above_half_of_capital_transfers_nothing(): void
    {
        $this->postJv('2024-01-01', $this->acc('1201'), $this->acc('3100'), 100000.0, 'capital');
        $this->postJv('2024-12-31', $this->re(), $this->acc('3500'), 50000.001, 'reserve-seed');
        $this->trade(2025, 70000.0, 20000.0, 'fy25');

        $run = $this->service()->appropriate($this->company->id, 2025, $this->accountant->id);

        $this->assertSame(50_000_001, $run['figures']['statutory_opening_fils']);
        $this->assertSame(0, $run['figures']['statutory_due_fils'], 'the reserve opened above 50% of capital: nothing is due');
        $this->assertSame(50_000_001, $this->statutoryFils('2025-12-31'), 'the reserve does not move');
        $this->assertCount(0, $this->aprRows());
        $this->assertSame('cap_reached', json_decode((string) $this->auditRows(ReserveAppropriationService::AUDIT_ACTION)->sole()->after, true)['outcome']);
    }

    /**
     * The cap boundary (H m4): at EXACTLY 50% (50,000.000 of 100,000.000) a transfer is still due,
     * the full 10%: 5,000.000 (pinned non-zero). (MUTANT 3b: transfers stopped at exactly 50%.)
     */
    public function test_the_cap_at_exactly_half_of_capital_the_full_ten_percent_is_still_due(): void
    {
        $this->postJv('2024-01-01', $this->acc('1201'), $this->acc('3100'), 100000.0, 'capital');
        $this->postJv('2024-12-31', $this->re(), $this->acc('3500'), 50000.0, 'reserve-seed');
        $this->trade(2025, 70000.0, 20000.0, 'fy25');

        $this->service()->appropriate($this->company->id, 2025, $this->accountant->id);

        $this->assertSame(55_000_000, $this->statutoryFils('2025-12-31'), '50,000 + the full 5,000 (the transfer may take it past 50%)');
    }

    /**
     * A first-year company (L22 "incorporated in 2026"): capital 10,000.000 paid in on 1 March
     * 2026, FY2026 profit 2,000.000; the reserve opens at 0, at or below half of the capital:
     * statutory 200.000.
     */
    public function test_a_first_year_company_transfers_ten_percent_of_its_first_profit(): void
    {
        $this->postJv('2026-03-01', $this->acc('1201'), $this->acc('3100'), 10000.0, 'capital');
        $this->trade(2026, 5000.0, 3000.0, 'fy26');

        $this->service()->appropriate($this->company->id, 2026, $this->accountant->id);

        $this->assertSame(200_000, $this->statutoryFils('2026-12-31'));
    }

    /**
     * The voluntary reserve (OD-3): `--voluntary-rate=5` on a profit of 50,000.000 transfers
     * 2,500.000 beside the statutory 5,000.000; a later run without the option KEEPS the year's
     * rate (and so changes nothing).
     */
    public function test_the_voluntary_rate_is_applied_and_kept_for_the_year(): void
    {
        $this->postJv('2025-01-01', $this->acc('1201'), $this->acc('3100'), 100000.0, 'capital');
        $this->trade(2025, 70000.0, 20000.0, 'fy25');

        [$out, $exit] = $this->runCommand('accounting:appropriate-reserves', ['company' => $this->company->id, 'year' => 2025, '--user' => $this->accountant->id, '--voluntary-rate' => '5']);
        $this->assertStringContainsString('statutory reserve +5,000.000, voluntary reserve +2,500.000', $out);
        $this->assertSame(2_500_000, $this->oracleCreditBalanceFils($this->acc('3600'), '2025-12-31'));
        $this->assertSame(-7_500_000, $this->oracleCreditBalanceFils($this->re(), '2025-12-31'));
        $this->assertSame(0, $exit, $out);

        [$out, $exit] = $this->runCommand('accounting:appropriate-reserves', ['company' => $this->company->id, 'year' => 2025, '--user' => $this->accountant->id]);
        $this->assertStringContainsString('Nothing to post', $out);
        $this->assertCount(1, $this->aprRows());
        $this->assertSame(0, $exit, $out);
    }

    /**
     * H-M3: December LOCKED -> refused, naming the fix; nothing posted. The locked-period bypass is
     * never used. (MUTANT 5: the refusal removed and the journal posted with allowLocked.)
     */
    public function test_a_locked_december_is_refused_naming_the_fix(): void
    {
        $this->postJv('2025-01-01', $this->acc('1201'), $this->acc('3100'), 100000.0, 'capital');
        $this->trade(2025, 70000.0, 20000.0, 'fy25');
        $this->lockMonths(2025, 12);

        [$out, $exit] = $this->runCommand('accounting:appropriate-reserves', ['company' => $this->company->id, 'year' => 2025, '--user' => $this->accountant->id]);

        $this->assertStringContainsString('The FY2025 reserve appropriation was NOT recorded; nothing was changed. December 2025 is locked', $out);
        $this->assertStringContainsString('Reopen December or run before locking', $out);
        $this->assertCount(0, $this->aprRows());
        $this->assertSame(0, $this->statutoryFils('2026-12-31'), 'nothing posted anywhere, January included');
        $this->assertCount(0, $this->filingEvents());
        $this->assertSame(1, $exit);
    }

    /**
     * L25 rule 1: December SOFT-CLOSED for the audit. Without a reason: refused, nothing posted.
     * With `--reason` (the acting accountant holds the soft-closed override): posted, dated 31
     * December 2025, never shifted into January. (MUTANT 9: the reason not passed to the post, so
     * PostingService shifts it to 1 January 2026.)
     */
    public function test_a_soft_closed_december_posts_only_with_a_reason_and_never_shifts(): void
    {
        $this->postJv('2025-01-01', $this->acc('1201'), $this->acc('3100'), 100000.0, 'capital');
        $this->trade(2025, 70000.0, 20000.0, 'fy25');
        $this->lockMonths(2025, 11);
        $this->setPeriod(2025, 12, AccountingPeriod::STATUS_SOFT_CLOSED);

        [$out, $exit] = $this->runCommand('accounting:appropriate-reserves', ['company' => $this->company->id, 'year' => 2025, '--user' => $this->accountant->id]);
        $this->assertStringContainsString('December 2025 is soft-closed for the audit', $out);
        $this->assertStringContainsString('--reason=', $out);
        $this->assertCount(0, $this->aprRows());
        $this->assertSame(1, $exit);

        [$out, $exit] = $this->runCommand('accounting:appropriate-reserves', ['company' => $this->company->id, 'year' => 2025, '--user' => $this->accountant->id, '--reason' => 'FY2025 appropriation during the audit']);
        $this->assertStringContainsString('Posted APR journal', $out);
        $apr = $this->aprRows()->sole();
        $this->assertSame('2025-12-31', Carbon::parse($apr->posting_date)->toDateString(), 'posted into the soft-closed December, never January');
        $this->assertSame(5_000_000, $this->statutoryFils('2025-12-31'));
        $this->assertSame('FY2025 appropriation during the audit', $this->auditRows(ReserveAppropriationService::AUDIT_ACTION)->sole()->reason);
        $this->assertSame(0, $exit, $out);
    }

    /**
     * X9b, X9-VERIFY m-2 (the verifier's V2, adopted): December soft-closed; the acting user holds
     * the CLOSE tier (an explicit `accounting.period.close` grant) but NOT the soft-closed override,
     * and passes a reason. PostingService silently shifts such a post to 1 January; the service must
     * detect it and roll everything back: no APR, no audit row, no filing event, nothing in January.
     * (Kills the verifier's mutant C, the landed-date refusal disabled, which the rest survives.)
     */
    public function test_a_soft_closed_post_that_would_shift_to_january_is_refused_and_leaves_nothing(): void
    {
        $this->postJv('2025-01-01', $this->acc('1201'), $this->acc('3100'), 100000.0, 'capital');
        $this->trade(2025, 70000.0, 20000.0, 'fy25');
        $this->lockMonths(2025, 11);
        $this->setPeriod(2025, 12, AccountingPeriod::STATUS_SOFT_CLOSED);

        Permission::firstOrCreate(['name' => 'accounting.period.close', 'guard_name' => 'web'], ['group' => 'accounting']);
        $closer = User::factory()->create(['role_id' => Role::AGENT]);
        $closer->givePermissionTo('accounting.period.close');

        $before = $this->ledgerFingerprint();
        try {
            $this->service()->appropriate($this->company->id, 2025, $closer->id, 'audit adjustment window');
            $this->fail('a post that PostingService shifts out of the soft-closed December was accepted');
        } catch (ReserveAppropriationException $e) {
            $this->assertStringContainsString('would land on 2026-01-01', $e->getMessage());
        }

        $this->assertSame($before, $this->ledgerFingerprint(), 'nothing written: no APR, no audit row');
        $this->assertCount(0, $this->aprRows());
        $this->assertCount(0, $this->filingEvents());
        $this->assertSame(0, $this->statutoryFils('2026-12-31'), 'nothing landed in January either');
    }

    /** L21 refusals: no --user; a malformed or out-of-range voluntary rate. Nothing is written by any. */
    public function test_cli_refusals_write_nothing(): void
    {
        $this->trade(2025, 7000.0, 2000.0, 'fy25');
        $fingerprint = $this->ledgerFingerprint();

        [$out, $exit] = $this->runCommand('accounting:appropriate-reserves', ['company' => $this->company->id, 'year' => 2025]);
        $this->assertStringContainsString('--user= is required', $out);
        $this->assertSame(1, $exit);

        [$out, $exit] = $this->runCommand('accounting:appropriate-reserves', ['company' => $this->company->id, 'year' => 2025, '--user' => $this->accountant->id, '--voluntary-rate' => 'five']);
        $this->assertStringContainsString("--voluntary-rate must be a percentage such as 5 or 2.5; got 'five'", $out);
        $this->assertSame(1, $exit);

        [$out, $exit] = $this->runCommand('accounting:appropriate-reserves', ['company' => $this->company->id, 'year' => 2025, '--user' => $this->accountant->id, '--voluntary-rate' => '12']);
        $this->assertStringContainsString('The voluntary reserve rate must be between 0% and 10% of net profit', $out);
        $this->assertSame(1, $exit);

        $this->assertSame($fingerprint, $this->ledgerFingerprint());
        $this->assertCount(0, $this->filingEvents());
    }

    /**
     * X9r hand-off (L25 rule 2): FY2025 closed the normal way (appropriation 500.000 on a profit of
     * 5,000.000 before the December lock, then the YEC); reopened; +1,000.000 audit adjustment in
     * December; `accounting:reclose-year` re-runs the appropriation BEFORE it locks December and
     * re-runs the YEC: it posts only the +100.000 difference (statutory 600.000 = 10% of 6,000.000),
     * dated 31 December, and retained earnings close at 6,000 - 600 = 5,400.000. A second re-close
     * changes nothing. (MUTANT: the re-run posts the full appropriation again, a DOUBLE
     * appropriation: statutory 1,100.000.)
     */
    public function test_reclose_re_runs_the_appropriation_once_and_never_doubles_it(): void
    {
        $this->trade(2025, 7000.0, 2000.0, 'fy25');
        $this->lockMonths(2025, 11);
        $this->service()->appropriate($this->company->id, 2025, $this->accountant->id);
        $this->setPeriod(2025, 12, AccountingPeriod::STATUS_LOCKED);
        $yec = app(YearEndCloseService::class)->run($this->company->id, 2025, $this->accountant->id);
        $this->assertTrue($yec['success'], implode(' | ', $yec['blocking']));
        $this->assertSame(500_000, $this->statutoryFils('2025-12-31'));
        $this->assertSame(4_500_000, $this->oracleCreditBalanceFils($this->re(), '2025-12-31'));

        [$out, $exit] = $this->runCommand('accounting:reopen-year', ['company' => $this->company->id, 'year' => 2025, '--reason' => 'FY2025 audit', '--user' => $this->accountant->id]);
        $this->assertSame(0, $exit, $out);
        app(PostingService::class)->post($this->auditAdjustment(), $this->accountant->id);

        [$out, $exit] = $this->runCommand('accounting:reclose-year', ['company' => $this->company->id, 'year' => 2025, '--user' => $this->accountant->id]);
        $this->assertStringContainsString('Reserve appropriation: Posted APR journal', $out);
        $this->assertStringContainsString('statutory reserve +100.000', $out);
        $this->assertStringContainsString('net profit/(loss) 6,000.000', $out);
        $this->assertSame(0, $exit, $out);

        $this->assertSame(600_000, $this->statutoryFils('2025-12-31'), '10% of the corrected 6,000.000, once');
        $this->assertSame(5_400_000, $this->oracleCreditBalanceFils($this->re(), '2025-12-31'), 'retained earnings: 6,000 swept less 600 appropriated');
        $apr = $this->aprRows();
        $this->assertCount(2, $apr);
        $this->assertSame(['2025-12-31', '2025-12-31'], $apr->map(fn ($t) => Carbon::parse($t->posting_date)->toDateString())->all());
        $newYec = $this->yecRows(2025)->last();
        $this->assertLessThan((int) $newYec->id, (int) $apr[1]->id, 'the appropriation is re-run before the YEC');
        for ($m = 1; $m <= 12; $m++) {
            $this->assertSame(AccountingPeriod::STATUS_LOCKED, $this->periodStatus(2025, $m));
        }

        $fingerprint = $this->ledgerFingerprint();
        [$out, $exit] = $this->runCommand('accounting:reclose-year', ['company' => $this->company->id, 'year' => 2025, '--user' => $this->accountant->id]);
        $this->assertStringContainsString('already re-closed', $out);
        $this->assertSame($fingerprint, $this->ledgerFingerprint(), 'a second re-close changes nothing');
        $this->assertSame(0, $exit, $out);
    }

    /** A re-close of a year whose appropriation was never run does not invent one; it says so. */
    public function test_reclose_does_not_invent_an_appropriation_that_was_never_run(): void
    {
        $this->closedFy2025();
        $this->runCommand('accounting:reopen-year', ['company' => $this->company->id, 'year' => 2025, '--reason' => 'FY2025 audit', '--user' => $this->accountant->id]);

        [$out, $exit] = $this->runCommand('accounting:reclose-year', ['company' => $this->company->id, 'year' => 2025, '--user' => $this->accountant->id]);

        $this->assertStringContainsString('Reserve appropriation: never run for 2025, so nothing was re-run. Run accounting:appropriate-reserves', $out);
        $this->assertCount(0, $this->aprRows());
        $this->assertSame(0, $exit, $out);
    }

    /** The service refuses directly with its own exception type (no command wrapper). */
    public function test_the_service_refuses_a_locked_december_with_its_own_exception(): void
    {
        $this->trade(2025, 7000.0, 2000.0, 'fy25');
        $this->lockMonths(2025, 12);

        $this->expectException(ReserveAppropriationException::class);
        $this->expectExceptionMessage('December 2025 is locked');

        $this->service()->appropriate($this->company->id, 2025, $this->accountant->id);
    }
}
