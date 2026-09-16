<?php

declare(strict_types=1);

namespace Tests\Feature\Legacy;

use App\Services\Onboarding\Parity\ParityHarness;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsParityFixtures;
use Tests\Concerns\PreparesLegacyPilotFence;
use Tests\TestCase;

/**
 * legacy-ledger-pilot LP4 -- the parity harness's core comparisons.
 *
 * Every test starts from a synthetic world in which parity holds exactly
 * (BuildsParityFixtures::buildBalancedParityWorld()) and breaks ONE thing.
 * A harness that stays green under a deliberate corruption is worthless
 * (PLAN.md §LP4 mutation proof), so the "it passes" test is only half the
 * suite -- the other half is one failure mode per check.
 */
class LegacyParityHarnessTest extends TestCase
{
    use BuildsParityFixtures, PreparesLegacyPilotFence, RefreshDatabase;

    private int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLegacyPilotFence();
        $this->companyId = $this->makeParityCompany();
        $this->buildBalancedParityWorld($this->companyId);
    }

    private function parity(string $anchor = ParityHarness::ANCHOR_ALL): array
    {
        return app(ParityHarness::class)->run($this->companyId, CarbonImmutable::parse('2025-12-31'), $anchor);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function check(array $result, string $key): array
    {
        foreach ($result['checks'] as $check) {
            if ($check['key'] === $key) {
                return $check;
            }
        }

        $this->fail("The harness produced no check named '{$key}'.");
    }

    public function test_a_matching_replay_passes_every_anchor_at_three_decimal_places(): void
    {
        $result = $this->parity();

        $this->assertSame('pass', $result['status'], 'A world built to match must PASS: '.json_encode(array_map(
            fn ($c) => [$c['key'] => $c['diffs']],
            $result['checks'],
        )));

        foreach (['tb_opening', 'tb_closing', 'pl', 'ar', 'ap', 'posting_date', 'verify_scope'] as $key) {
            $this->assertSame('pass', $this->check($result, $key)['status'], "check {$key} should pass");
        }

        $this->assertSame(0, $result['accounts_out_of_tolerance']);
    }

    /**
     * PLAN.md §5.2 O5, RATIFIED: the pass line is EXACT at 3 dp. One
     * thousandth of a dinar on one account fails the run and the account is
     * named.
     *
     * MUTATION PROOF for the 3 dp comparison: widen
     * legacy_pilot.parity.tolerance past 0.0005 (or round to 2 dp instead
     * of 3) and this test goes green while the books are wrong.
     */
    public function test_one_account_off_by_one_thousandth_fails_and_is_named(): void
    {
        DB::table('journal_entries')
            ->where('account_id', $this->parityAccounts['BANK'])
            ->update(['debit' => 1000.501]);

        $result = $this->parity();

        $this->assertSame('fail', $result['status']);

        $closing = $this->check($result, 'tb_closing');
        $this->assertSame('fail', $closing['status']);

        $named = array_values(array_filter(
            $closing['diffs'],
            fn ($diff) => $diff['acc_code'] === '1010',
        ));

        $this->assertNotEmpty($named, 'The failing account must be NAMED by its legacy AccCode, not merely counted.');

        $deltas = array_map(fn ($d) => $d['delta'], $named);
        $this->assertContains(0.001, $deltas, 'The reported delta must be exactly the 0.001 that was injected.');

        // And the aggregate must notice too: our books no longer balance.
        $unbalanced = array_values(array_filter($closing['diffs'], fn ($d) => $d['classification'] === 'ledger_unbalanced'));
        $this->assertNotEmpty($unbalanced, 'A one-sided 0.001 change also breaks SUM(debit) = SUM(credit) and must be reported.');
    }

    /**
     * A rounding difference SMALLER than a thousandth is representation
     * noise, not a bookkeeping difference: both sides round to 3 dp first.
     * Without this test, tightening the epsilon to 0 would look harmless
     * and would then fail every real run on float noise.
     */
    public function test_a_sub_thousandth_difference_is_absorbed_by_rounding_to_three_decimals(): void
    {
        DB::table('journal_entries')
            ->where('account_id', $this->parityAccounts['BANK'])
            ->update(['debit' => 1000.5001]);

        $result = $this->parity('closing');

        $this->assertSame('pass', $this->check($result, 'tb_closing')['status']);
    }

    public function test_the_aggregate_debit_equals_credit_check_reproduces_the_anchor_totals(): void
    {
        $result = $this->parity('closing');
        $summary = $this->check($result, 'tb_closing')['summary'];

        $this->assertSame(400.125, $summary['legacy_period_dr']);
        $this->assertSame(400.125, $summary['legacy_period_cr']);
        $this->assertSame(400.125, $summary['akeed_period_dr']);
        $this->assertSame(400.125, $summary['akeed_period_cr']);
        $this->assertSame(0.0, $summary['legacy_closing_sum']);
        $this->assertSame(0.0, $summary['akeed_closing_sum']);
    }

    /**
     * The single easiest way to fail every account at once
     * (MAPPING-RULES §9.1): count the opening journal as period activity.
     * Here the harness is pointed at a DIFFERENT opening sub_type, which is
     * what a mis-configured or mis-tagged replay looks like -- the OJV then
     * lands in period movement AND leaves the opening bucket, and both the
     * opening and closing anchors must go red.
     */
    public function test_treating_the_opening_journal_as_period_activity_fails_the_run(): void
    {
        config(['legacy_pilot.parity.opening_sub_type' => 'LEGACY_NOT_THE_OJV']);

        $result = $this->parity();

        $this->assertSame('fail', $result['status']);
        $this->assertSame('fail', $this->check($result, 'tb_opening')['status'], 'The opening bucket loses the OJV entirely.');
        $this->assertSame('fail', $this->check($result, 'tb_closing')['status'], 'Period movement double-counts the opening position.');
    }

    /**
     * P&L net check (MAPPING-RULES §9.2). The anchor is CREDIT-positive
     * (NetIncome = Cr - Dr), the trial balance is DEBIT-positive; swapping
     * the two conventions inverts every income account.
     */
    public function test_a_profit_and_loss_movement_change_fails_the_pl_check_and_its_net(): void
    {
        DB::table('journal_entries')
            ->where('account_id', $this->parityAccounts['SALES'])
            ->update(['credit' => 500.125]);

        $result = $this->parity('pl');
        $pl = $this->check($result, 'pl');

        $this->assertSame('fail', $pl['status']);
        $this->assertSame(400.125, $pl['summary']['legacy_net_income']);
        $this->assertSame(500.125, $pl['summary']['akeed_net_income']);

        $net = array_values(array_filter($pl['diffs'], fn ($d) => $d['classification'] === 'aggregate_mismatch'));
        $this->assertNotEmpty($net, 'The P&L NET must be checked, not only the per-account rows.');
        $this->assertSame(100.0, $net[0]['delta']);
    }

    /**
     * An anchor row LP1 never imported cannot be compared, and the harness
     * must say so rather than treat "we hold 0.000 for it" as agreement.
     */
    public function test_an_anchor_row_with_no_mapping_is_reported_as_unmapped_not_as_a_match(): void
    {
        DB::connection('legacy_pilot')->table('legacy_acc_map')
            ->where('acc_code', '1010')
            ->delete();

        $result = $this->parity('closing');
        $closing = $this->check($result, 'tb_closing');

        $this->assertSame('fail', $closing['status']);

        $unmapped = array_values(array_filter($closing['diffs'], fn ($d) => $d['classification'] === 'unmapped_account'));
        $this->assertCount(1, $unmapped);
        $this->assertSame('1010', $unmapped[0]['acc_code']);
    }

    /**
     * Every check runs even after an earlier one fails (PLAN.md §5.2 O4).
     * A first-failure abort would hide the other checks and force one
     * re-run per defect.
     */
    public function test_a_failing_check_does_not_abort_the_remaining_checks(): void
    {
        DB::table('journal_entries')
            ->where('account_id', $this->parityAccounts['BANK'])
            ->update(['debit' => 9999.999]);

        $result = $this->parity();

        $this->assertSame('fail', $result['status']);
        $this->assertSame('pass', $this->check($result, 'ar')['status'], 'AR is untouched and must still be evaluated.');
        $this->assertSame('pass', $this->check($result, 'posting_date')['status']);
        $this->assertGreaterThanOrEqual(7, $result['checks_total']);
    }

    public function test_the_run_and_its_diffs_are_persisted_on_the_quarantined_connection(): void
    {
        DB::table('journal_entries')
            ->where('account_id', $this->parityAccounts['BANK'])
            ->update(['debit' => 1000.501]);

        $result = $this->parity('closing');
        $runId = app(ParityHarness::class)->persist($result, 'C:/tmp/report.md', 'C:/tmp/report.json');

        $run = DB::connection('legacy_pilot')->table('parity_run')->find($runId);
        $this->assertSame('fail', $run->status);
        $this->assertSame($result['run_key'], $run->run_key);

        $diffs = DB::connection('legacy_pilot')->table('parity_diff')->where('parity_run_id', $runId)->get();
        $this->assertNotEmpty($diffs);

        foreach ($diffs as $diff) {
            $this->assertNotNull($diff->classification, 'PLAN.md §5.2 O5: zero unclassified residuals.');
            $this->assertNotSame('', $diff->classification);
        }
    }
}
