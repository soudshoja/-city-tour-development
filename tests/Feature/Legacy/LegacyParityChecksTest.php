<?php

declare(strict_types=1);

namespace Tests\Feature\Legacy;

use App\Services\Onboarding\Parity\ParityHarness;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsParityFixtures;
use Tests\Concerns\PreparesLegacyPilotFence;
use Tests\TestCase;

/**
 * legacy-ledger-pilot LP4 -- the three non-anchor checks:
 * posting-date integrity (check 11), the per-SubType-per-month document
 * census (check 4), and the O7-verify scoping of `accounting:verify`.
 */
class LegacyParityChecksTest extends TestCase
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

    private function check(array $result, string $key): array
    {
        foreach ($result['checks'] as $check) {
            if ($check['key'] === $key) {
                return $check;
            }
        }

        $this->fail("No check named '{$key}'.");
    }

    // ---------------------------------------------------------------- 11

    /**
     * MUTATION PROOF for the posting-date check: shift ONE line's
     * posting_date and the check must go red and name that journal entry.
     * Delete the `DATE(posting_date) <> DATE(transaction_date)` predicate,
     * or count NULL as a pass, and this test goes green while a document's
     * amounts sit in the wrong month -- and, at a year boundary, in the
     * wrong YEAR, which breaks the closing anchor while every line remains
     * individually correct.
     */
    public function test_a_shifted_posting_date_is_detected_and_the_line_is_named(): void
    {
        $entryId = DB::table('journal_entries')
            ->where('account_id', $this->parityAccounts['SALES'])
            ->value('id');

        DB::table('journal_entries')->where('id', $entryId)->update(['posting_date' => '2026-01-05']);

        $check = $this->check($this->parity(), 'posting_date');

        $this->assertSame('fail', $check['status']);
        $this->assertSame(1, $check['summary']['shifted']);
        $this->assertCount(1, $check['diffs']);
        $this->assertStringContainsString('journal_entries.id='.$entryId, $check['diffs'][0]['detail']);
        $this->assertSame('posting_date_shifted', $check['diffs'][0]['classification']);
    }

    /**
     * A NULL posting_date is a violation, not a pass: PostingService
     * populates it on every document it posts, so a legacy-fed line without
     * one did not come through the seam.
     */
    public function test_a_null_posting_date_on_a_legacy_line_is_a_violation(): void
    {
        DB::table('journal_entries')
            ->where('account_id', $this->parityAccounts['SALES'])
            ->update(['posting_date' => null]);

        $check = $this->check($this->parity(), 'posting_date');

        $this->assertSame('fail', $check['status']);
        $this->assertSame(1, $check['summary']['shifted']);
    }

    /**
     * The check is scoped to LEGACY-FED lines. A non-legacy document with a
     * deliberately different posting date (a genuine period-guard shift on
     * Akeed's own books) must not make a legacy parity run red.
     */
    public function test_a_non_legacy_document_with_a_shifted_posting_date_is_out_of_scope(): void
    {
        $this->postSyntheticDocument($this->companyId, 'JV', 'MANUAL_JV', '2025-02-10', [
            [$this->parityAccounts['BANK'], 10.0, 0.0, null],
            [$this->parityAccounts['SALES'], 0.0, 10.0, null],
        ], postingDate: '2025-03-01');

        $check = $this->check($this->parity(), 'posting_date');

        $this->assertSame('pass', $check['status']);
        $this->assertSame(5, $check['summary']['legacy_lines'], 'Only the five LEGACY_ lines are in scope.');
    }

    // ----------------------------------------------------------------- 4

    /**
     * PLAN.md §5.1 R9 (vacuous green): when LP3 has not run against this
     * database there is no Akeed-side census, and the check must be SKIPPED
     * with a reason rather than reported as passed.
     */
    public function test_the_document_census_is_skipped_with_a_reason_when_map_document_is_absent(): void
    {
        $check = $this->check($this->parity(), 'doc_counts');

        $this->assertSame('skipped', $check['status']);
        $this->assertStringContainsString('stg_acc_header is not staged', $check['summary']['reason']);
        $this->assertGreaterThanOrEqual(1, $this->parity()['checks_skipped']);
    }

    public function test_the_document_census_matches_when_every_legacy_document_is_accounted_for(): void
    {
        $this->seedCensusTables(
            legacy: [
                ['subtype' => 'INV', 'docdt' => '2025-06-15', 'posted' => '1', 'docid' => '900'],
                ['subtype' => 'INV', 'docdt' => '2025-06-20', 'posted' => '1', 'docid' => '901'],
                ['subtype' => 'INV', 'docdt' => '2025-07-02', 'posted' => '1', 'docid' => '902'],
                // Unposted: PLAN.md §5.2 O11 excludes it from the census.
                ['subtype' => 'INV', 'docdt' => '2025-07-03', 'posted' => '0', 'docid' => '903'],
            ],
            ours: [
                ['legacy_sub_type' => 'INV', 'legacy_doc_dt' => '2025-06-15', 'status' => 'posted'],
                ['legacy_sub_type' => 'INV', 'legacy_doc_dt' => '2025-06-20', 'status' => 'skipped_no_lines'],
                ['legacy_sub_type' => 'INV', 'legacy_doc_dt' => '2025-07-02', 'status' => 'posted'],
                // O11 is "exclude, COUNT, report", and LP3 records the
                // Posted=0 header as `skipped_unposted` rather than dropping
                // it. It is excluded from the POSTED side of the identity
                // and compared against the legacy unposted count instead.
                ['legacy_sub_type' => 'INV', 'legacy_doc_dt' => '2025-07-03', 'status' => 'skipped_unposted'],
            ],
        );

        $check = $this->check($this->parity(), 'doc_counts');

        $this->assertSame('pass', $check['status'], json_encode($check['diffs']));
        $this->assertSame(3, $check['summary']['legacy_total']);
        $this->assertSame(3, $check['summary']['akeed_total']);
        $this->assertSame(1, $check['summary']['unposted_total']);
    }

    /**
     * MUTATION PROOF for the census: drop one document from the replay and
     * the check must name the SubType and the MONTH, not merely the total.
     * A run that posts one document twice and drops another has the right
     * total and the wrong books -- comparing totals alone cannot see it.
     */
    public function test_a_missing_document_fails_the_census_naming_its_subtype_and_month(): void
    {
        $this->seedCensusTables(
            legacy: [
                ['subtype' => 'INV', 'docdt' => '2025-06-15', 'posted' => '1', 'docid' => '900'],
                ['subtype' => 'INV', 'docdt' => '2025-07-02', 'posted' => '1', 'docid' => '901'],
            ],
            ours: [
                // Two documents replayed, but BOTH in June: the total is
                // right, the months are not.
                ['legacy_sub_type' => 'INV', 'legacy_doc_dt' => '2025-06-15', 'status' => 'posted'],
                ['legacy_sub_type' => 'INV', 'legacy_doc_dt' => '2025-06-20', 'status' => 'posted'],
            ],
        );

        $check = $this->check($this->parity(), 'doc_counts');

        $this->assertSame('fail', $check['status']);
        $this->assertSame(2, $check['summary']['legacy_total']);
        $this->assertSame(2, $check['summary']['akeed_total'], 'The TOTALS agree — only the per-month split exposes this.');
        $this->assertCount(2, $check['diffs']);

        $details = implode(' ', array_column($check['diffs'], 'detail'));
        $this->assertStringContainsString('INV 2025-06', $details);
        $this->assertStringContainsString('INV 2025-07', $details);
    }

    /**
     * LP1b: the real export writes `Posted` as the literal strings
     * 'True'/'False', not '1'/'0'. Filtering that TEXT column with a
     * literal '1' comparison matches NOTHING and reports a census of zero
     * -- MastersAuditor shipped exactly that defect. The census must count
     * both shapes and must still exclude the unposted rows (O11).
     *
     * MUTATION PROOF: swap LegacyStagingCast::whereTruthy() back to
     * `->where('posted', '1')` and this test fails with legacy_total 0.
     */
    public function test_the_census_reads_true_false_text_posted_flags_not_only_one_and_zero(): void
    {
        $this->seedCensusTables(
            legacy: [
                ['subtype' => 'INV', 'docdt' => '2025-06-15 00:00:00', 'posted' => 'True', 'docid' => '900'],
                ['subtype' => 'INV', 'docdt' => '2025-06-20', 'posted' => 'true', 'docid' => '901'],
                ['subtype' => 'INV', 'docdt' => '2025-06-21', 'posted' => 'False', 'docid' => '902'],
                ['subtype' => 'INV', 'docdt' => '2025-06-22', 'posted' => null, 'docid' => '903'],
            ],
            ours: [
                ['legacy_sub_type' => 'INV', 'legacy_doc_dt' => '2025-06-15', 'status' => 'posted'],
                ['legacy_sub_type' => 'INV', 'legacy_doc_dt' => '2025-06-20', 'status' => 'posted'],
                ['legacy_sub_type' => 'INV', 'legacy_doc_dt' => '2025-06-21', 'status' => 'skipped_unposted'],
                ['legacy_sub_type' => 'INV', 'legacy_doc_dt' => '2025-06-22', 'status' => 'skipped_unposted'],
            ],
        );

        $check = $this->check($this->parity(), 'doc_counts');

        $this->assertSame('pass', $check['status'], json_encode($check['diffs']));
        $this->assertSame(2, $check['summary']['legacy_total'], "'True'/'true' count; 'False'/NULL do not (PLAN.md §5.2 O11).");
        $this->assertSame(2, $check['summary']['unposted_total'], "'False' and NULL are the unposted population — counted and reported, never silently dropped.");
    }

    /**
     * PLAN.md §5.2 O5: zero unclassified documents. A refused document is
     * a FAIL even when the arithmetic adds up.
     */
    public function test_a_refused_document_fails_the_census_even_when_the_counts_add_up(): void
    {
        $this->seedCensusTables(
            legacy: [['subtype' => 'JV', 'docdt' => '2025-04-01', 'posted' => '1', 'docid' => '910']],
            ours: [['legacy_sub_type' => 'JV', 'legacy_doc_dt' => '2025-04-01', 'status' => 'refused']],
        );

        $check = $this->check($this->parity(), 'doc_counts');

        $this->assertSame('fail', $check['status']);
        $this->assertSame(1, $check['summary']['refused_total']);
        $this->assertSame('refused_documents', $check['diffs'][0]['classification']);
    }

    // ---------------------------------------------------------------- O7

    /**
     * MAPPING-RULES §11 O7-verify. The cash/bank counter-leg rule is scoped
     * OUT for LEGACY_ documents; the BALANCE and VOUCHER-NUMBER rules stay
     * in force for every document. And scoping out is not suppressing --
     * the scoped-out finding is still counted and reported.
     */
    public function test_the_cash_bank_counter_leg_rule_is_scoped_out_for_legacy_documents_but_still_reported(): void
    {
        // An RV with no cash/bank leaf anywhere: exactly the ~8,730-document
        // shape O7-verify exists for.
        $this->postSyntheticDocument($this->companyId, 'RV', 'LEGACY_FRV', '2025-05-01', [
            [$this->parityAccounts['AR_CONTROL'], 50.0, 0.0, 11],
            [$this->parityAccounts['SALES'], 0.0, 50.0, null],
        ]);

        $check = $this->check($this->parity(), 'verify_scope');

        $this->assertSame('pass', $check['status'], 'The legacy no-cash-leg finding must not fail the run.');
        $this->assertSame(1, $check['summary']['scoped_out_total']);
        $this->assertArrayHasKey('LEGACY_FRV', $check['summary']['scoped_out_by_type']);
        $this->assertSame(1, $check['summary']['scoped_out_by_type']['LEGACY_FRV']['no_cash_or_bank_counter_leg']);
    }

    public function test_the_same_finding_on_a_non_legacy_document_is_still_enforced(): void
    {
        $this->postSyntheticDocument($this->companyId, 'RV', 'MANUAL_RV', '2025-05-01', [
            [$this->parityAccounts['AR_CONTROL'], 50.0, 0.0, 11],
            [$this->parityAccounts['SALES'], 0.0, 50.0, null],
        ]);

        $check = $this->check($this->parity(), 'verify_scope');

        $this->assertSame('fail', $check['status']);
        $this->assertSame(1, $check['summary']['residuals_by_type']['MANUAL_RV']['no_cash_or_bank_counter_leg']);
    }

    public function test_the_voucher_number_rule_stays_in_force_for_legacy_documents(): void
    {
        $this->postSyntheticDocument($this->companyId, 'RV', 'LEGACY_FRV', '2025-05-01', [
            [$this->parityAccounts['BANK'], 50.0, 0.0, null],
            [$this->parityAccounts['SALES'], 0.0, 50.0, null],
        ], voucherNumber: null);

        $check = $this->check($this->parity(), 'verify_scope');

        $this->assertSame('fail', $check['status'], 'O7-verify scopes out ONLY the cash/bank rule.');
        $this->assertSame(2, $check['summary']['residuals_by_type']['LEGACY_FRV']['missing_voucher_number']);
    }

    public function test_the_balance_rule_stays_in_force_for_legacy_documents(): void
    {
        $this->postSyntheticDocument($this->companyId, 'PV', 'LEGACY_BPV', '2025-05-01', [
            [$this->parityAccounts['BANK'], 0.0, 50.0, null],
            [$this->parityAccounts['SALES'], 49.0, 0.0, null],
        ]);

        $check = $this->check($this->parity(), 'verify_scope');

        $this->assertSame('fail', $check['status']);
        $this->assertSame(1, $check['summary']['residuals_by_type']['LEGACY_BPV']['unbalanced']);
    }

    public function test_the_pilot_group_name_overrides_are_restored_after_the_verify_run(): void
    {
        $before = config('accounting.engine.bank_group_name');

        $this->parity();

        $this->assertSame($before, config('accounting.engine.bank_group_name'), 'The overrides also steer AccountResolver on the POSTING path — leaving them changed would re-point a later posting in the same process.');
    }

    /**
     * @param  list<array<string, string>>  $legacy
     * @param  list<array<string, string>>  $ours
     */
    private function seedCensusTables(array $legacy, array $ours): void
    {
        Schema::connection('legacy_pilot')->dropIfExists('stg_acc_header');
        Schema::connection('legacy_pilot')->create('stg_acc_header', function ($table) {
            $table->id();
            $table->text('docid')->nullable();
            $table->text('subtype')->nullable();
            $table->text('docdt')->nullable();
            $table->text('posted')->nullable();
        });
        DB::connection('legacy_pilot')->table('stg_acc_header')->insert($legacy);

        Schema::connection('legacy_pilot')->dropIfExists('map_document');
        Schema::connection('legacy_pilot')->create('map_document', function ($table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('legacy_sub_type');
            $table->string('legacy_doc_dt');
            $table->string('status');
        });

        DB::connection('legacy_pilot')->table('map_document')->insert(array_map(
            fn ($row) => $row + ['company_id' => $this->companyId],
            $ours,
        ));
    }

    protected function tearDown(): void
    {
        foreach (['stg_acc_header', 'map_document'] as $table) {
            Schema::connection('legacy_pilot')->dropIfExists($table);
        }

        parent::tearDown();
    }
}
