<?php

declare(strict_types=1);

namespace Tests\Feature\Legacy;

use App\Services\Onboarding\Parity\ParityReportWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\Concerns\BuildsParityFixtures;
use Tests\Concerns\PreparesLegacyPilotFence;
use Tests\TestCase;

/**
 * legacy-ledger-pilot LP4 -- `legacy:parity` and `legacy:parity-diff`, and
 * the report writer's redaction gate.
 */
class LegacyParityCommandTest extends TestCase
{
    use BuildsParityFixtures, PreparesLegacyPilotFence, RefreshDatabase;

    private int $companyId;

    private string $reportDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLegacyPilotFence();
        $this->companyId = $this->makeParityCompany();
        $this->buildBalancedParityWorld($this->companyId);

        $this->reportDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'lp4_report_'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($this->reportDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->reportDir);

        parent::tearDown();
    }

    private function reportPath(string $name = 'parity.md'): string
    {
        return str_replace('\\', '/', $this->reportDir).'/'.$name;
    }

    public function test_a_matching_run_exits_zero_and_writes_both_reports(): void
    {
        $path = $this->reportPath();

        $this->artisan('legacy:parity', [
            '--as-of' => '2025-12-31',
            '--company' => $this->companyId,
            '--report' => $path,
        ])->assertExitCode(0);

        $this->assertFileExists($path);
        $this->assertFileExists(preg_replace('/\.md$/', '.json', $path));

        $markdown = File::get($path);
        $this->assertStringContainsString('Legacy-ledger parity report — PASS', $markdown);
        $this->assertStringContainsString('Closing trial balance', $markdown);
        $this->assertStringContainsString('legacy:parity-diff --account=', $markdown);

        $json = json_decode(File::get(preg_replace('/\.md$/', '.json', $path)), true);
        $this->assertSame('pass', $json['status']);
        $this->assertArrayHasKey('definitions', $json);
    }

    /**
     * Exit code is the CI gate; the plan's own R9 warning is that every
     * command in this codebase exits 0 while achieving nothing, so a
     * failing parity run MUST exit non-zero.
     */
    public function test_a_failing_run_exits_non_zero_and_names_the_account(): void
    {
        DB::table('journal_entries')
            ->where('account_id', $this->parityAccounts['BANK'])
            ->update(['debit' => 1000.501]);

        $this->artisan('legacy:parity', [
            '--company' => $this->companyId,
            '--report' => $this->reportPath('fail.md'),
        ])
            ->expectsOutputToContain('1010')
            ->assertExitCode(1);

        $this->assertStringContainsString('balance_mismatch', File::get($this->reportPath('fail.md')));
    }

    public function test_an_unknown_anchor_is_refused_rather_than_silently_running_everything(): void
    {
        $this->artisan('legacy:parity', [
            '--company' => $this->companyId,
            '--anchor' => 'nonsense',
        ])->assertExitCode(2);
    }

    public function test_narrowing_to_one_anchor_still_runs_the_replay_wide_checks(): void
    {
        $path = $this->reportPath('narrow.md');

        $this->artisan('legacy:parity', [
            '--company' => $this->companyId,
            '--anchor' => 'ar',
            '--report' => $path,
        ])->assertExitCode(0);

        $markdown = File::get($path);
        $this->assertStringContainsString('Accounts receivable per party leaf', $markdown);
        $this->assertStringNotContainsString('Closing trial balance', $markdown);
        $this->assertStringContainsString('Posting-date integrity', $markdown, 'Posting dates are a property of the replay, not of one anchor.');
    }

    public function test_the_run_is_persisted_unless_no_persist_is_passed(): void
    {
        $this->artisan('legacy:parity', [
            '--company' => $this->companyId,
            '--report' => $this->reportPath('p1.md'),
        ])->assertExitCode(0);

        $this->assertSame(1, DB::connection('legacy_pilot')->table('parity_run')->count());

        $this->artisan('legacy:parity', [
            '--company' => $this->companyId,
            '--report' => $this->reportPath('p2.md'),
            '--no-persist' => true,
        ])->assertExitCode(0);

        $this->assertSame(1, DB::connection('legacy_pilot')->table('parity_run')->count());
    }

    // ------------------------------------------------------------ redaction

    public function test_the_writer_drops_name_shaped_keys_at_every_depth(): void
    {
        $writer = app(ParityReportWriter::class);

        $redacted = $writer->redact([
            'status' => 'pass',
            'name' => 'Acme Travel LLC',
            'checks' => [
                ['key' => 'tb_closing', 'diffs' => [
                    ['acc_code' => '1010', 'partner_name' => 'Someone Real', 'narration' => 'paid by Mr X', 'delta' => 0.0],
                ]],
            ],
        ]);

        $encoded = json_encode($redacted);

        $this->assertStringNotContainsString('Acme Travel LLC', $encoded);
        $this->assertStringNotContainsString('Someone Real', $encoded);
        $this->assertStringNotContainsString('paid by Mr X', $encoded);
        $this->assertStringContainsString('1010', $encoded);
    }

    public function test_a_party_id_is_rendered_as_a_pseudonym_and_never_as_a_bare_id_field(): void
    {
        $redacted = app(ParityReportWriter::class)->redact([
            'status' => 'fail',
            'checks' => [['key' => 'ar', 'diffs' => [['party_id' => 12, 'acc_code' => '10904002']]]],
        ]);

        $this->assertSame('PARTY-12', $redacted['checks'][0]['diffs'][0]['party']);
        $this->assertArrayNotHasKey('party_id', $redacted['checks'][0]['diffs'][0]);
    }

    /**
     * MUTATION PROOF for the redaction gate: remove the email tripwire and
     * this test goes green while a report carrying an address is written to
     * disk. Refusing to WRITE is the correct failure mode -- a report that
     * has already been written cannot be un-shared (PLAN.md §6 gate 5).
     */
    public function test_an_email_shaped_value_that_survives_redaction_refuses_the_write(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PII TRIPWIRE');

        app(ParityReportWriter::class)->redact([
            'status' => 'pass',
            'checks' => [['key' => 'x', 'diffs' => [['detail' => 'contact someone@example.com about this']]]],
        ]);
    }

    public function test_a_real_report_carries_only_codes_amounts_and_pseudonyms(): void
    {
        DB::connection('legacy_pilot')->table('map_party')->where('partner_id_fk', 12)->delete();

        $path = $this->reportPath('redacted.md');

        $this->artisan('legacy:parity', [
            '--company' => $this->companyId,
            '--report' => $path,
        ])->assertExitCode(1);

        $markdown = File::get($path);

        $this->assertStringContainsString('PARTY-12', $markdown);
        $this->assertStringNotContainsString('RECEIVABLE CONTROL', $markdown, 'Account NAMES never reach a report — codes only.');
        $this->assertStringContainsString('No personal data appears in this report', $markdown);
    }

    // ------------------------------------------------------------ drill-down

    public function test_the_drill_down_lists_both_sides_for_one_account(): void
    {
        $this->seedLegacyDetail();

        $this->artisan('legacy:parity-diff', [
            '--account' => '1010',
            '--company' => $this->companyId,
        ])
            ->expectsOutputToContain('AccCode 1010')
            ->expectsOutputToContain('LEGACY lines (1)')
            ->expectsOutputToContain('AKEED lines (1)')
            ->assertExitCode(0);
    }

    /**
     * A pooled leaf folds onto a control account carrying every other
     * party's lines. Drilling into ONE party's code must narrow our side to
     * that party, or the answer is buried.
     */
    public function test_the_drill_down_narrows_a_pooled_leaf_to_its_own_party(): void
    {
        $this->artisan('legacy:parity-diff', [
            '--account' => '10904002',
            '--company' => $this->companyId,
        ])
            ->expectsOutputToContain('PARTY-12')
            ->expectsOutputToContain('AKEED lines (1)')
            ->assertExitCode(0);
    }

    public function test_the_drill_down_refuses_an_unknown_account_code(): void
    {
        $this->artisan('legacy:parity-diff', [
            '--account' => '99999999',
            '--company' => $this->companyId,
        ])->assertExitCode(1);
    }

    public function test_the_drill_down_requires_an_account(): void
    {
        $this->artisan('legacy:parity-diff', ['--company' => $this->companyId])->assertExitCode(2);
    }

    private function seedLegacyDetail(): void
    {
        \Illuminate\Support\Facades\Schema::connection('legacy_pilot')->dropIfExists('stg_acc_detail');
        \Illuminate\Support\Facades\Schema::connection('legacy_pilot')->create('stg_acc_detail', function ($table) {
            $table->id();
            $table->text('accdetailid')->nullable();
            $table->text('docid_fk')->nullable();
            $table->text('docno')->nullable();
            $table->text('subtype')->nullable();
            $table->text('docdt')->nullable();
            $table->text('accid_fk')->nullable();
            $table->text('debit')->nullable();
            $table->text('credit')->nullable();
            $table->text('dc')->nullable();
        });

        DB::connection('legacy_pilot')->table('stg_acc_detail')->insert([
            'accdetailid' => '5001', 'docid_fk' => '900', 'docno' => 'OJV-1',
            'subtype' => 'OJV', 'docdt' => '2025-01-01', 'accid_fk' => '1',
            'debit' => '1000.500', 'credit' => '0.000', 'dc' => 'D',
        ]);
    }
}
