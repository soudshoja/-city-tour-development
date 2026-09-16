<?php

declare(strict_types=1);

namespace Tests\Feature\Legacy;

use App\Services\Onboarding\Parity\LedgerFigures;
use App\Services\Onboarding\Parity\ParityHarness;
use App\Services\Onboarding\Parity\VerifyScoper;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsParityFixtures;
use Tests\Concerns\PreparesLegacyPilotFence;
use Tests\TestCase;

/**
 * legacy-ledger-pilot LP4 -- the properties that only break at SCALE or on
 * the failure path, and that a five-line fixture can never show:
 *
 *   - the per-account figures are aggregated IN SQL over the DECIMAL
 *     columns, in a query count that does not grow with the number of
 *     journal lines (532 accounts x 179,421 lines is the real shape);
 *   - a party carrying BOTH an AR and an AP role decomposes to its own
 *     leaf on each side, never to the other side's;
 *   - the O7-verify config overrides are restored when the checker THROWS,
 *     not only when it returns.
 */
class LegacyParityRobustnessTest extends TestCase
{
    use BuildsParityFixtures, PreparesLegacyPilotFence, RefreshDatabase;

    private int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLegacyPilotFence();
        $this->companyId = $this->makeParityCompany();
    }

    // ------------------------------------------------------------ scale

    /**
     * THE N+1 THAT WOULD NOT SHOW UP IN A FIXTURE. The real pilot is 532
     * accounts against 179,421 journal lines. A `foreach (accounts as $a)
     * { sum($a) }` shape passes every correctness test in this suite and
     * then issues 532 aggregate queries -- or, worse, pulls every line into
     * PHP and sums it there in floats.
     *
     * Three things are pinned at once, because a bound alone is not enough
     * at fixture scale: the count must be BOUNDED, it must not grow with
     * the number of LINES, and it must not grow with the number of
     * ACCOUNTS. A four-leaf fixture hides an N+1 over accounts entirely --
     * 4 extra queries still sit under any sane ceiling, and 532 do not.
     *
     * MUTATION PROOF: add a single `DB::table('journal_entries')
     * ->where('account_id', $id)->sum('debit')` inside perAccount()'s leaf
     * loop and this test fails on the accounts limb (the bound and the
     * lines limb both stay green -- which is exactly why the accounts limb
     * has to be here).
     */
    public function test_per_account_figures_are_aggregated_in_sql_in_a_bounded_query_count(): void
    {
        $accounts = $this->seedParityChart($this->companyId);

        $small = $this->countQueriesForFigures();

        $this->seedBulkLines($accounts, 2500);

        $moreLines = $this->countQueriesForFigures();

        $this->assertLessThanOrEqual(
            20,
            $moreLines,
            'perAccount() must aggregate in SQL: 532 accounts x 179k lines cannot afford a query per account.',
        );
        $this->assertSame(
            $small,
            $moreLines,
            'The query count grew with the number of journal lines — that is an N+1 over lines, invisible at fixture scale.',
        );

        $assetsRootId = (int) DB::table('accounts')
            ->where('company_id', $this->companyId)
            ->where('name', 'Assets')
            ->value('id');

        for ($i = 0; $i < 60; $i++) {
            $this->insertAccount($this->companyId, $assetsRootId, $assetsRootId, '19'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'FILLER LEAF '.$i, 2, false);
        }

        $moreAccounts = $this->countQueriesForFigures();

        $this->assertSame(
            $moreLines,
            $moreAccounts,
            'The query count grew with the number of LEAF ACCOUNTS — a per-account query is 532 round trips on the real chart.',
        );
        $this->assertLessThanOrEqual(20, $moreAccounts);
    }

    /**
     * The summation happens over DECIMAL columns in the database, so a
     * five-thousand-line population at real KWD magnitudes must come back
     * EXACT at 3 dp -- not "within a thousandth". A PHP-side float
     * accumulation over 179k lines is the shape that silently drifts, and
     * O5's pass line has no slack to absorb it.
     */
    public function test_a_five_thousand_line_population_sums_exactly_at_three_decimals(): void
    {
        $accounts = $this->seedParityChart($this->companyId);

        // 2,500 documents x (Dr 14,343.767 / Cr 14,343.767). The per-side
        // total, 35,859,417.500, is deliberately the scale of the real
        // closing anchor (Dr = Cr = 35,859,419.537).
        $this->seedBulkLines($accounts, 2500);

        $figures = $this->figures();

        $this->assertSame(35_859_417.500, $figures[$accounts['BANK']]['period_dr']);
        $this->assertSame(35_859_417.500, $figures[$accounts['SALES']]['period_cr']);
        $this->assertSame(35_859_417.500, $figures[$accounts['BANK']]['closing_net']);
        $this->assertSame(-35_859_417.500, $figures[$accounts['SALES']]['closing_net']);

        $closingSum = 0.0;

        foreach ($figures as $figure) {
            $closingSum += $figure['closing_net'];
        }

        $this->assertSame(0.0, round($closingSum, 3), 'The books must close to 0.000 exactly at this magnitude.');
    }

    // ------------------------------------------------------- dual role

    /**
     * MAPPING-RULES §9.3: the fold is reversed PER ROLE, through
     * map_party's `cust_acc_id_fk` / `supp_acc_id_fk`. A partner that is
     * both a customer AND a supplier -- ordinary in a travel agency, where
     * the same consolidator both buys and sells -- holds two positions on
     * two different control accounts and two different legacy leaves.
     *
     * MUTATION PROOF: make LegacyMapReader::partyRoleLeafByParty() ignore
     * $role and read `cust_acc_id_fk` always, and the AP check fails with
     * PARTY-31's payable balance landing on its RECEIVABLE leaf.
     */
    public function test_a_party_holding_both_an_ar_and_an_ap_role_derives_to_its_own_leaf_on_each_side(): void
    {
        $accounts = $this->seedParityChart($this->companyId);

        // PARTY-31 is both: 300.000 receivable and 700.000 payable.
        $this->postSyntheticDocument($this->companyId, 'OJV', 'LEGACY_OJV', '2025-01-01', [
            [$accounts['AR_CONTROL'], 300.000, 0.0, 31],
            [$accounts['AP_CONTROL'], 0.0, 700.000, 31],
            [$accounts['BANK'], 400.000, 0.0, null],
        ]);

        $this->seedLegacyAccMap($this->companyId, [
            ['acc_id' => 1, 'acc_code' => '1010', 'account_id' => $accounts['BANK'], 'resolution' => 'direct'],
            ['acc_id' => 2, 'acc_code' => '10904031', 'account_id' => $accounts['AR_CONTROL'], 'resolution' => 'pooled_receivable', 'party_id' => 31, 'party_role' => 'customer'],
            ['acc_id' => 4, 'acc_code' => '20600131', 'account_id' => $accounts['AP_CONTROL'], 'resolution' => 'pooled_payable', 'party_id' => 31, 'party_role' => 'supplier'],
        ]);

        $this->seedMapParty($this->companyId, [
            ['partner_id' => 31, 'cust_acc_id' => 2, 'supp_acc_id' => 4],
        ]);

        $this->seedTrialBalanceAnchor('stg_tb_20241231_consolidated', [
            ['acc_code' => '1010', 'acc_id' => 1, 'opening' => 400.000, 'dr' => 0.0, 'cr' => 0.0],
            ['acc_code' => '10904031', 'acc_id' => 2, 'opening' => 300.000, 'dr' => 0.0, 'cr' => 0.0],
            ['acc_code' => '20600131', 'acc_id' => 4, 'opening' => -700.000, 'dr' => 0.0, 'cr' => 0.0],
        ]);
        $this->seedTrialBalanceAnchor('stg_tb_20251231_consolidated', [
            ['acc_code' => '1010', 'acc_id' => 1, 'opening' => 400.000, 'dr' => 0.0, 'cr' => 0.0],
            ['acc_code' => '10904031', 'acc_id' => 2, 'opening' => 300.000, 'dr' => 0.0, 'cr' => 0.0],
            ['acc_code' => '20600131', 'acc_id' => 4, 'opening' => -700.000, 'dr' => 0.0, 'cr' => 0.0],
        ]);
        $this->seedProfitLossAnchor('stg_pl_2025', []);
        $this->seedPartyAnchor('stg_ar_20251231', [['acc_code' => '10904031', 'acc_id' => 2, 'closing' => 300.000]]);
        $this->seedPartyAnchor('stg_ap_20251231', [['acc_code' => '20600131', 'acc_id' => 4, 'closing' => -700.000]]);
        $this->useSyntheticAnchorConfig();

        $result = app(ParityHarness::class)->run($this->companyId, CarbonImmutable::parse('2025-12-31'), ParityHarness::ANCHOR_ALL);

        $ar = $this->check($result, 'ar');
        $ap = $this->check($result, 'ap');

        $this->assertSame('pass', $ar['status'], json_encode($ar['diffs']));
        $this->assertSame('pass', $ap['status'], json_encode($ap['diffs']));
        $this->assertSame(1, $ar['summary']['parties_derived']);
        $this->assertSame(1, $ap['summary']['parties_derived']);
    }

    /**
     * And the same party's two positions must not be interchangeable:
     * moving 1 fil from its receivable leaf to its payable one is a real
     * error that the CONSOLIDATED trial balance cannot see (both control
     * accounts still total correctly per account) and only the per-leaf
     * decomposition can.
     */
    public function test_a_dual_role_party_with_its_receivable_off_by_a_thousandth_fails_the_ar_check(): void
    {
        $accounts = $this->seedParityChart($this->companyId);

        $this->postSyntheticDocument($this->companyId, 'OJV', 'LEGACY_OJV', '2025-01-01', [
            [$accounts['AR_CONTROL'], 300.001, 0.0, 31],
            [$accounts['AP_CONTROL'], 0.0, 700.000, 31],
            [$accounts['BANK'], 399.999, 0.0, null],
        ]);

        $this->seedLegacyAccMap($this->companyId, [
            ['acc_id' => 2, 'acc_code' => '10904031', 'account_id' => $accounts['AR_CONTROL'], 'resolution' => 'pooled_receivable', 'party_id' => 31, 'party_role' => 'customer'],
            ['acc_id' => 4, 'acc_code' => '20600131', 'account_id' => $accounts['AP_CONTROL'], 'resolution' => 'pooled_payable', 'party_id' => 31, 'party_role' => 'supplier'],
        ]);
        $this->seedMapParty($this->companyId, [['partner_id' => 31, 'cust_acc_id' => 2, 'supp_acc_id' => 4]]);

        $this->seedTrialBalanceAnchor('stg_tb_20241231_consolidated', []);
        $this->seedTrialBalanceAnchor('stg_tb_20251231_consolidated', []);
        $this->seedProfitLossAnchor('stg_pl_2025', []);
        $this->seedPartyAnchor('stg_ar_20251231', [['acc_code' => '10904031', 'acc_id' => 2, 'closing' => 300.000]]);
        $this->seedPartyAnchor('stg_ap_20251231', [['acc_code' => '20600131', 'acc_id' => 4, 'closing' => -700.000]]);
        $this->useSyntheticAnchorConfig();

        $result = app(ParityHarness::class)->run($this->companyId, CarbonImmutable::parse('2025-12-31'), ParityHarness::ANCHOR_ALL);

        $ar = $this->check($result, 'ar');
        $ap = $this->check($result, 'ap');

        $this->assertSame('fail', $ar['status']);
        $this->assertSame('pass', $ap['status'], json_encode($ap['diffs']), 'The payable side of the same party is untouched and must stay green.');
        $this->assertSame('10904031', $ar['diffs'][0]['acc_code']);
        $this->assertSame(31, $ar['diffs'][0]['party_id'], 'A diff on a pooled leaf is useless without the party it belongs to.');
        $this->assertSame(0.001, $ar['diffs'][0]['delta']);
    }

    // ------------------------------------------------------ failure path

    /**
     * The O7-verify overrides are set on the LIVE container and also steer
     * AccountResolver on the POSTING path. A `finally` is the only thing
     * that keeps a thrown checker from leaving the whole process pointed at
     * the legacy chart's group names.
     *
     * MUTATION PROOF: move the restore loop out of `finally` and into the
     * line after `$this->checker->check()`, and this test fails with
     * bank_group_name still set to 'BANK ACCOUNTS'.
     */
    public function test_the_overrides_are_restored_when_the_checker_throws(): void
    {
        $before = config('accounting.engine.bank_group_name');
        $this->assertNotSame('BANK ACCOUNTS', $before, 'The fixture override must actually differ from the seeded value.');

        // RvPvInvariantChecker is final, so the exception is provoked for
        // real rather than mocked: its very first statement queries
        // `transactions`, and a table that is not there throws out of the
        // middle of run() exactly as a malformed legacy row would.
        Schema::rename('transactions', 'transactions_renamed_for_test');

        try {
            app(VerifyScoper::class)->run(
                $this->companyId,
                ['accounting.engine.bank_group_name' => 'BANK ACCOUNTS'],
                true,
                'LEGACY_',
            );
            $this->fail('The scoper must not swallow the checker exception.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('transactions', $e->getMessage());
        } finally {
            Schema::rename('transactions_renamed_for_test', 'transactions');
        }

        $this->assertSame($before, config('accounting.engine.bank_group_name'));
    }

    // ------------------------------------------------------------ utils

    private function figures(): array
    {
        return app(LedgerFigures::class)->perAccount(
            $this->companyId,
            CarbonImmutable::parse('2025-01-01'),
            CarbonImmutable::parse('2025-12-31'),
            'LEGACY_OJV',
        );
    }

    private function countQueriesForFigures(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->figures();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /**
     * @param  array<string, int>  $accounts
     */
    private function seedBulkLines(array $accounts, int $documents): void
    {
        $transactions = [];
        $lines = [];

        for ($i = 1; $i <= $documents; $i++) {
            $transactions[] = [
                'company_id' => $this->companyId,
                'entity_id' => $this->companyId,
                'entity_type' => 'company',
                'transaction_type' => 'journal',
                'amount' => 14343.767,
                'total_debit' => 14343.767,
                'total_credit' => 14343.767,
                'description' => 'bulk parity fixture',
                'reference_type' => 'Invoice',
                'doc_type' => 'INV',
                'sub_type' => 'LEGACY_INV',
                'reference_number' => 'BULK-'.$i,
                'idempotency_key' => 'legacy:bulk:'.$i,
                'posting_status' => 'posted',
                'transaction_date' => '2025-06-15 00:00:00',
                'posting_date' => '2025-06-15',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($transactions, 250) as $chunk) {
            DB::table('transactions')->insert($chunk);
        }

        $ids = DB::table('transactions')
            ->where('company_id', $this->companyId)
            ->where('reference_number', 'like', 'BULK-%')
            ->pluck('id');

        foreach ($ids as $transactionId) {
            foreach ([[$accounts['BANK'], 14343.767, 0.0], [$accounts['SALES'], 0.0, 14343.767]] as $line) {
                $lines[] = [
                    'company_id' => $this->companyId,
                    'transaction_id' => $transactionId,
                    'account_id' => $line[0],
                    'type_reference_id' => null,
                    'debit' => $line[1],
                    'credit' => $line[2],
                    'amount' => 14343.767,
                    'exchange_rate' => 1,
                    'currency' => 'KWD',
                    'name' => 'bulk',
                    'description' => 'bulk parity fixture line',
                    'transaction_date' => '2025-06-15 00:00:00',
                    'posting_date' => '2025-06-15',
                    'voucher_number' => 'V-BULK',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($lines, 500) as $chunk) {
            DB::table('journal_entries')->insert($chunk);
        }
    }

    private function check(array $result, string $key): array
    {
        foreach ($result['checks'] as $check) {
            if ($check['key'] === $key) {
                return $check;
            }
        }

        $this->fail("No check named '{$key}'.");
    }
}
