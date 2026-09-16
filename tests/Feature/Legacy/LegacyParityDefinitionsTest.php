<?php

declare(strict_types=1);

namespace Tests\Feature\Legacy;

use App\Services\Onboarding\LegacyStagingCast;
use App\Services\Onboarding\Parity\AnchorReader;
use App\Services\Onboarding\Parity\LedgerFigures;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Concerns\BuildsParityFixtures;
use Tests\Concerns\PreparesLegacyPilotFence;
use Tests\TestCase;

/**
 * legacy-ledger-pilot LP4 -- the DEFINITIONS the whole harness rests on.
 *
 * These tests pin the arithmetic itself, independently of any anchor: the
 * opening/period partition (the OJV date trap), the debit-positive sign
 * convention, the leaf-only rule, and the anchor reader's refusal to accept
 * a defective staged file.
 */
class LegacyParityDefinitionsTest extends TestCase
{
    use BuildsParityFixtures, PreparesLegacyPilotFence, RefreshDatabase;

    private int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLegacyPilotFence();
        $this->companyId = $this->makeParityCompany();
    }

    private function figures(): array
    {
        return app(LedgerFigures::class)->perAccount(
            $this->companyId,
            CarbonImmutable::parse('2025-01-01'),
            CarbonImmutable::parse('2025-12-31'),
            'LEGACY_OJV',
        );
    }

    /**
     * THE OJV DATE TRAP. MAPPING-RULES §9.1 says opening is
     * "COALESCE(...) < 2025-01-01" AND that the opening journal is dated
     * 2025-01-01. Taken literally, the opening journal falls into NEITHER
     * opening nor period and the whole opening position evaporates from
     * every closing figure while both queries still look correct.
     *
     * MUTATION PROOF: delete the `orWhereExists(... sub_type = OJV)` limb
     * from LedgerFigures::openingNet() and this test fails with opening
     * 0.000 and closing 0.000 for an account that plainly holds 500.000.
     */
    public function test_an_opening_journal_dated_inside_the_period_lands_in_opening_not_period_and_is_never_lost(): void
    {
        $accounts = $this->seedParityChart($this->companyId);

        $this->postSyntheticDocument($this->companyId, 'OJV', 'LEGACY_OJV', '2025-01-01', [
            [$accounts['BANK'], 500.0, 0.0, null],
            [$accounts['SALES'], 0.0, 500.0, null],
        ]);

        $figures = $this->figures();

        $this->assertSame(500.0, $figures[$accounts['BANK']]['opening_net'], 'The OJV is the OPENING position.');
        $this->assertSame(0.0, $figures[$accounts['BANK']]['period_dr'], 'The OJV is never period activity.');
        $this->assertSame(500.0, $figures[$accounts['BANK']]['closing_net'], 'And it must still appear in the closing figure.');
    }

    /**
     * The partition property: every line at or before the comparison date
     * belongs to exactly ONE of opening and period. That is what makes
     * closing = opening + Dr - Cr an identity rather than a coincidence.
     */
    public function test_opening_and_period_partition_every_line_with_none_counted_twice(): void
    {
        $accounts = $this->seedParityChart($this->companyId);

        $this->postSyntheticDocument($this->companyId, 'OJV', 'LEGACY_OJV', '2025-01-01', [
            [$accounts['BANK'], 100.0, 0.0, null],
            [$accounts['SALES'], 0.0, 100.0, null],
        ]);

        $this->postSyntheticDocument($this->companyId, 'INV', 'LEGACY_INV', '2025-06-01', [
            [$accounts['BANK'], 40.0, 0.0, null],
            [$accounts['SALES'], 0.0, 40.0, null],
        ]);

        $figures = $this->figures();
        $bank = $figures[$accounts['BANK']];

        $this->assertSame(100.0, $bank['opening_net']);
        $this->assertSame(40.0, $bank['period_dr']);
        $this->assertSame(140.0, $bank['closing_net']);

        $rawTotal = (float) DB::table('journal_entries')
            ->where('account_id', $accounts['BANK'])
            ->sum(DB::raw('debit - credit'));

        $this->assertSame($rawTotal, $bank['closing_net'], 'opening + Dr - Cr must equal the raw ledger total: nothing dropped, nothing double-counted.');
    }

    /**
     * A document dated genuinely before the period start belongs to opening
     * whatever its sub_type -- the `< periodStart` limb is preserved
     * unchanged from TrialBalanceService::getOpeningBalances().
     */
    public function test_a_pre_period_document_of_any_sub_type_lands_in_opening(): void
    {
        $accounts = $this->seedParityChart($this->companyId);

        $this->postSyntheticDocument($this->companyId, 'JV', 'LEGACY_JV', '2024-11-30', [
            [$accounts['BANK'], 70.0, 0.0, null],
            [$accounts['SALES'], 0.0, 70.0, null],
        ]);

        $figures = $this->figures();

        $this->assertSame(70.0, $figures[$accounts['BANK']]['opening_net']);
        $this->assertSame(0.0, $figures[$accounts['BANK']]['period_dr']);
    }

    /**
     * Figures are DEBIT-POSITIVE for every account class
     * (README-MANIFEST.md line 51), NOT normal-side signed the way
     * TrialBalanceService::generate() returns closing_balance. Applying the
     * normal-side flip would invert every liability, equity and income
     * account and produce a wall of "twice the balance" deltas that look
     * like a posting bug.
     *
     * MUTATION PROOF: sign the closing figure by root class and this test
     * fails on the income leaf with +400 instead of -400.
     */
    public function test_credit_normal_accounts_are_reported_debit_positive_not_normal_side_signed(): void
    {
        $accounts = $this->seedParityChart($this->companyId);

        $this->postSyntheticDocument($this->companyId, 'INV', 'LEGACY_INV', '2025-06-01', [
            [$accounts['BANK'], 400.0, 0.0, null],
            [$accounts['SALES'], 0.0, 400.0, null],
        ]);

        $figures = $this->figures();

        $this->assertSame('Income', $figures[$accounts['SALES']]['root_name']);
        $this->assertSame(-400.0, $figures[$accounts['SALES']]['closing_net'], 'An income account with 400 credit is -400.000 on the debit-positive convention.');
        $this->assertSame(400.0, $figures[$accounts['BANK']]['closing_net']);
    }

    /**
     * TrialBalanceService reports LEAF accounts only. A group account that
     * appeared in the comparison would double-count its whole subtree
     * against the anchor.
     */
    public function test_group_accounts_are_excluded_and_leaves_are_not(): void
    {
        $accounts = $this->seedParityChart($this->companyId);
        $figures = $this->figures();

        $assetsRootId = (int) DB::table('accounts')->where('company_id', $this->companyId)->where('name', 'Assets')->value('id');

        $this->assertArrayNotHasKey($assetsRootId, $figures, 'Assets has children — it is a group, never a compared row.');
        $this->assertArrayHasKey($accounts['BANK'], $figures);
    }

    /**
     * A deleted line is not a balance. TrialBalanceService puts
     * whereNull('deleted_at') on every journal-entry join; dropping it here
     * would resurrect reversed or soft-deleted lines into the anchor
     * comparison.
     */
    public function test_soft_deleted_journal_entries_are_excluded(): void
    {
        $accounts = $this->seedParityChart($this->companyId);

        $this->postSyntheticDocument($this->companyId, 'INV', 'LEGACY_INV', '2025-06-01', [
            [$accounts['BANK'], 400.0, 0.0, null],
            [$accounts['SALES'], 0.0, 400.0, null],
        ]);

        DB::table('journal_entries')->where('account_id', $accounts['BANK'])->update(['deleted_at' => now()]);

        $this->assertSame(0.0, $this->figures()[$accounts['BANK']]['closing_net']);
    }

    // ------------------------------------------------------- anchor reader

    /**
     * README-MANIFEST.md line 51 is an identity, not a description. A
     * staged anchor that fails its own identity is a defective load and
     * must stop the comparison rather than be quietly reconciled into
     * agreement with us.
     */
    public function test_an_anchor_row_that_fails_its_own_identity_is_refused(): void
    {
        $this->seedAnchorTable('stg_tb_broken', ['acccode', 'accid_fk', 'branchid', 'openingnet', 'perioddr', 'periodcr', 'closingnet'], [[
            'acccode' => '1010', 'accid_fk' => '1', 'branchid' => 'ALL',
            'openingnet' => '100.000', 'perioddr' => '10.000', 'periodcr' => '0.000',
            'closingnet' => '999.000',
        ]]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('fails its own identity');

        app(AnchorReader::class)->trialBalance('stg_tb_broken');
    }

    /**
     * `(float) 'n/a'` is 0.0 in PHP. Casting an unreadable anchor cell to a
     * plausible 0.000 would let a broken load pass parity.
     */
    public function test_a_non_numeric_anchor_amount_is_refused_not_cast_to_zero(): void
    {
        $this->seedAnchorTable('stg_tb_junk', ['acccode', 'accid_fk', 'branchid', 'openingnet', 'perioddr', 'periodcr', 'closingnet'], [[
            'acccode' => '1010', 'accid_fk' => '1', 'branchid' => 'ALL',
            'openingnet' => 'n/a', 'perioddr' => '0.000', 'periodcr' => '0.000', 'closingnet' => '0.000',
        ]]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is not a number');

        app(AnchorReader::class)->trialBalance('stg_tb_junk');
    }

    /**
     * A consolidated anchor is BranchID='ALL', one row per account. Staging
     * a by-branch file into a consolidated slot would silently produce a
     * plausible-looking but wrong anchor if the duplicates were summed.
     */
    public function test_a_duplicate_acc_code_is_refused_rather_than_summed(): void
    {
        $this->seedAnchorTable('stg_tb_dupe', ['acccode', 'accid_fk', 'branchid', 'openingnet', 'perioddr', 'periodcr', 'closingnet'], [
            ['acccode' => '1010', 'accid_fk' => '1', 'branchid' => 'CO', 'openingnet' => '10.000', 'perioddr' => '0.000', 'periodcr' => '0.000', 'closingnet' => '10.000'],
            ['acccode' => '1010', 'accid_fk' => '1', 'branchid' => 'SH', 'openingnet' => '20.000', 'perioddr' => '0.000', 'periodcr' => '0.000', 'closingnet' => '20.000'],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('duplicate AccCode');

        app(AnchorReader::class)->trialBalance('stg_tb_dupe');
    }

    public function test_an_anchor_whose_row_count_changed_since_load_is_refused(): void
    {
        $this->seedAnchorTable('stg_tb_short', ['acccode', 'accid_fk', 'branchid', 'openingnet', 'perioddr', 'periodcr', 'closingnet'], [[
            'acccode' => '1010', 'accid_fk' => '1', 'branchid' => 'ALL',
            'openingnet' => '1.000', 'perioddr' => '0.000', 'periodcr' => '0.000', 'closingnet' => '1.000',
        ]]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('expected exactly 205');

        app(AnchorReader::class)->trialBalance('stg_tb_short', 205);
    }

    public function test_an_unstaged_anchor_names_the_command_that_stages_it(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('legacy:load');

        app(AnchorReader::class)->trialBalance('stg_tb_never_loaded');
    }

    /**
     * LP1b: every stg_* column is nullable TEXT and the real export writes
     * boolean-shaped columns as 'True'/'False', numeric columns as strings,
     * and sometimes with thousands separators. The anchor reader must go
     * through LegacyStagingCast for all of it.
     *
     * MUTATION PROOF: replace the LegacyStagingCast::toDecimal() call in
     * AnchorReader::money() with a bare `(float)` and this test fails --
     * '1,234.500' casts to 1.0, which is a plausible-looking anchor figure
     * and a silently wrong one.
     */
    public function test_a_thousands_separated_staging_amount_is_coerced_not_truncated(): void
    {
        $this->seedAnchorTable('stg_tb_commas', ['acccode', 'accid_fk', 'branchid', 'openingnet', 'perioddr', 'periodcr', 'closingnet'], [[
            'acccode' => '1010', 'accid_fk' => '1', 'branchid' => 'ALL',
            'openingnet' => '1,234.500', 'perioddr' => '0.000', 'periodcr' => '0.000', 'closingnet' => '1,234.500',
        ]]);

        $rows = app(AnchorReader::class)->trialBalance('stg_tb_commas');

        $this->assertSame(1234.5, $rows['1010']['opening_net']);
    }

    public function test_a_blank_staging_amount_is_a_genuine_zero_but_garbage_is_not(): void
    {
        $this->assertSame(0.0, LegacyStagingCast::toDecimal('', 3, 'ctx'));
        $this->assertSame(0.0, LegacyStagingCast::toDecimal(null, 3, 'ctx'));
        $this->assertSame(0.0, LegacyStagingCast::toDecimal('NULL', 3, 'ctx'));

        $this->expectException(RuntimeException::class);
        LegacyStagingCast::toDecimal('#DIV/0!', 3, 'ctx');
    }

    /**
     * The P&L anchor is CREDIT-positive (NetIncome = Cr - Dr) — the
     * opposite of the trial balance's convention, in the same export.
     */
    public function test_the_profit_and_loss_anchor_identity_is_credit_positive(): void
    {
        $this->seedAnchorTable('stg_pl_bad', ['acccode', 'accid_fk', 'class', 'cr', 'dr', 'netincome'], [[
            'acccode' => '30010', 'accid_fk' => '5', 'class' => 'I',
            'cr' => '100.000', 'dr' => '10.000', 'netincome' => '-90.000',
        ]]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('fails its own identity');

        app(AnchorReader::class)->profitLoss('stg_pl_bad');
    }
}
