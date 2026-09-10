<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA5;

use App\Console\Commands\AccountingDedupeCutover;
use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use App\Services\Accounting\AccountingLog;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostingService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * CT-A5a — `accounting:dedupe-cutover`, proven on a fenced fixture.
 *
 * The dev database is NOT written to by this lane (owner decision on freezing the feeders is
 * pending), so the command's behaviour is established here, against a fixture built to carry
 * exactly the shape CT-D1 §0.4i measured: a document with a legacy posting AND an engine posting.
 */
class AccountingDedupeCutoverTest extends AccountingTestCase
{
    private Company $company;

    private Task $task;

    private int $debitAccountId;

    private int $creditAccountId;

    private Carbon $windowStart;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 6, 15, 10));
        config(['accounting.engine.enabled' => true]);

        $this->company = Company::factory()->create();
        CoaSeeder::run($this->company->id);
        (new SystemAccountsSeeder)->run();
        $this->trackCompanyForInvariants($this->company->id);
        Artisan::call('accounting:engine', ['company' => $this->company->id, '--enable' => true]);
        Artisan::call('accounting:periods:init', ['--company' => $this->company->id]);

        $branch = Branch::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => User::factory()->create()->id,
        ]);
        $agentType = AgentType::firstOrCreate(['name' => 'Sales']);
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => User::factory()->create()->id,
            'type_id' => $agentType->id,
        ]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);

        $this->task = Task::factory()->create([
            'company_id' => $this->company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => Supplier::factory()->create()->id,
            'type' => 'flight',
            'status' => 'issued',
            'total' => 100.0,
        ]);

        $this->debitAccountId = $this->leaf('1351');   // Clients (AR control)
        $this->creditAccountId = $this->leaf('4133');  // Service Fee Income

        $this->windowStart = Carbon::create(2026, 6, 15, 0);
    }

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function leaf(string $code): int
    {
        $id = (int) Account::query()->withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->where('code', $code)
            ->value('id');

        $this->assertGreaterThan(0, $id, "the CoaSeeder chart has no account with code {$code}");

        return $id;
    }

    /** The ENGINE half — a real posted document naming this task. */
    private function postEngineDocument(): void
    {
        app(PostingService::class)->post(new DocumentDraft(
            companyId: $this->company->id,
            branchId: null,
            docType: 'JV',
            subType: 'FIXTURE',
            docDate: Carbon::create(2026, 6, 14),
            narration: 'fixture engine document',
            lines: [
                new LineDraft(
                    purposeCode: '', accountId: $this->debitAccountId, side: 'debit', amount: 100.0,
                    currency: 'KWD', originalAmount: 100.0, exchangeRate: 1.0,
                    transactionType: 'CUSTOMERDEBITED', taskId: (int) $this->task->id, ledgerType: 'receivable',
                ),
                new LineDraft(
                    purposeCode: '', accountId: $this->creditAccountId, side: 'credit', amount: 100.0,
                    currency: 'KWD', originalAmount: 100.0, exchangeRate: 1.0,
                    transactionType: 'INCOME', taskId: (int) $this->task->id, ledgerType: 'income',
                ),
            ],
            idempotencyKey: 'fixture:task:'.$this->task->id.':sale',
        ));
    }

    /**
     * The LEGACY half — raw rows exactly as an inline writer leaves them: no `posting_date`, no
     * idempotency key on the header, stamped with the same document key as the engine document.
     *
     * @return int the legacy transaction id
     */
    private function writeLegacySet(float $debit, float $credit): int
    {
        $txId = (int) DB::table('transactions')->insertGetId([
            'company_id' => $this->company->id,
            'branch_id' => null,
            'description' => 'Task created: fixture',
            'entity_id' => $this->company->id,
            'entity_type' => 'company',
            'transaction_type' => 'credit',
            'amount' => $debit,
            'transaction_date' => Carbon::create(2026, 6, 14),
            'created_at' => Carbon::create(2026, 6, 15, 9),
            'updated_at' => Carbon::create(2026, 6, 15, 9),
        ]);

        foreach ([[$this->debitAccountId, $debit, 0.0, 'receivable'], [$this->creditAccountId, 0.0, $credit, 'income']] as [$accountId, $dr, $cr, $type]) {
            DB::table('journal_entries')->insert([
                'transaction_id' => $txId,
                'company_id' => $this->company->id,
                'account_id' => $accountId,
                'task_id' => $this->task->id,
                'transaction_date' => Carbon::create(2026, 6, 14),
                'posting_date' => null,
                'description' => 'legacy inline write',
                'name' => 'fixture',
                'debit' => $dr,
                'credit' => $cr,
                'type' => $type,
                'currency' => 'KWD',
                'created_at' => Carbon::create(2026, 6, 15, 9),
                'updated_at' => Carbon::create(2026, 6, 15, 9),
            ]);
        }

        return $txId;
    }

    private function dedupe(bool $apply): int
    {
        return Artisan::call('accounting:dedupe-cutover', array_filter([
            '--company' => $this->company->id,
            '--from' => $this->windowStart->toDateTimeString(),
            '--apply' => $apply ?: null,
            '--dry-run' => $apply ? null : true,
        ]));
    }

    /** Same call as {@see self::dedupe()}, but as a PendingCommand so output can be asserted on. */
    private function artisanDedupe(bool $apply): \Illuminate\Testing\PendingCommand
    {
        return $this->artisan('accounting:dedupe-cutover', array_filter([
            '--company' => $this->company->id,
            '--from' => $this->windowStart->toDateTimeString(),
            '--apply' => $apply ?: null,
            '--dry-run' => $apply ? null : true,
        ]));
    }

    private function reversalOf(int $legacyTxId): ?object
    {
        return DB::table('transactions')
            ->where('company_id', $this->company->id)
            ->where('idempotency_key', AccountingDedupeCutover::KEY_PREFIX.$legacyTxId)
            ->first();
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────

    public function test_a_dry_run_reports_the_dual_posting_and_writes_nothing(): void
    {
        $this->postEngineDocument();
        $legacyTxId = $this->writeLegacySet(100.0, 100.0);

        $rowsBefore = (int) DB::table('journal_entries')->where('company_id', $this->company->id)->count();

        $this->artisanDedupe(apply: false)
            ->assertExitCode(0)
            ->expectsOutputToContain('would reverse legacy tx '.$legacyTxId)
            ->run();

        $this->assertNull($this->reversalOf($legacyTxId), 'a dry run posted a reversing document');
        $this->assertSame(
            $rowsBefore,
            (int) DB::table('journal_entries')->where('company_id', $this->company->id)->count(),
            'a dry run wrote journal rows'
        );
    }

    public function test_apply_reverses_the_legacy_set_without_deleting_it(): void
    {
        $this->postEngineDocument();
        $legacyTxId = $this->writeLegacySet(100.0, 100.0);

        $this->assertSame(0, $this->dedupe(apply: true));

        $reversal = $this->reversalOf($legacyTxId);
        $this->assertNotNull($reversal, 'no reversing document was posted');
        $this->assertSame(AccountingDedupeCutover::SUB_TYPE, $reversal->sub_type);
        $this->assertStringContainsString('Cutover dedupe', (string) $reversal->description);

        // NEVER DELETED: the two legacy rows are still exactly where they were.
        $this->assertSame(
            2,
            (int) DB::table('journal_entries')->where('transaction_id', $legacyTxId)->count(),
            'the legacy rows were deleted instead of reversed'
        );

        // MIRRORED: the account that carried the legacy debit now carries the reversal's credit.
        $lines = DB::table('journal_entries')->where('transaction_id', $reversal->id)->get();
        $this->assertCount(2, $lines);

        $mirroredCredit = $lines->firstWhere('account_id', $this->debitAccountId);
        $mirroredDebit = $lines->firstWhere('account_id', $this->creditAccountId);

        $this->assertNotNull($mirroredCredit);
        $this->assertNotNull($mirroredDebit);
        $this->assertEqualsWithDelta(100.0, (float) $mirroredCredit->credit, 0.0005);
        $this->assertEqualsWithDelta(100.0, (float) $mirroredDebit->debit, 0.0005);

        // The net effect on each account is zero — the double posting is gone from the balances
        // while every row that ever posted is still on the ledger.
        foreach ([$this->debitAccountId, $this->creditAccountId] as $accountId) {
            $net = (float) DB::table('journal_entries')
                ->where('company_id', $this->company->id)
                ->where('account_id', $accountId)
                ->whereIn('transaction_id', [$legacyTxId, $reversal->id])
                ->sum(DB::raw('debit - credit'));

            $this->assertEqualsWithDelta(0.0, $net, 0.0005, 'the legacy posting was not fully unwound on account '.$accountId);
        }
    }

    public function test_the_before_image_is_recorded_durably_before_the_reversal(): void
    {
        $this->postEngineDocument();
        $legacyTxId = $this->writeLegacySet(100.0, 100.0);

        $this->dedupe(apply: true);

        $row = DB::connection(AccountingLog::DURABLE_CONNECTION)
            ->table('accounting_audit_log')
            ->where('company_id', $this->company->id)
            ->where('action', 'cutover_dedupe_reversed')
            ->first();

        $this->assertNotNull($row, 'no before-image row was written');

        $after = json_decode((string) $row->after, true);

        $this->assertSame($legacyTxId, $after['legacy_transaction_id'] ?? null);
        $this->assertCount(2, $after['before'] ?? [], 'the before-image does not carry the legacy rows');
        $this->assertSame('legacy half of a cutover dual posting', $after['reason'] ?? null);
    }

    public function test_a_second_apply_reverses_nothing(): void
    {
        $this->postEngineDocument();
        $legacyTxId = $this->writeLegacySet(100.0, 100.0);

        $this->dedupe(apply: true);
        $reversalId = $this->reversalOf($legacyTxId)->id;

        $this->assertSame(0, $this->dedupe(apply: true));
        $this->assertSame($reversalId, $this->reversalOf($legacyTxId)->id, 'a second run posted a second reversal');
        $this->assertSame(1, (int) DB::table('transactions')->where('company_id', $this->company->id)->where('idempotency_key', 'like', \App\Console\Commands\AccountingDedupeCutover::KEY_PREFIX.'%')->count());
    }

    public function test_a_legacy_only_document_is_left_alone(): void
    {
        // No engine document at all: this is an ordinary pre-cutover legacy posting, not a dual
        // posting. Reversing it would DELETE money from the ledger.
        $legacyTxId = $this->writeLegacySet(100.0, 100.0);

        $this->artisanDedupe(apply: true)
            ->assertExitCode(0)
            ->expectsOutputToContain('summary reversed=0 already_reversed=0 refused=0 dual_posted=0')
            ->run();

        $this->assertNull($this->reversalOf($legacyTxId), 'a legacy-only document was reversed');
    }

    public function test_an_unbalanced_legacy_set_is_refused_by_name_and_exits_non_zero(): void
    {
        $this->postEngineDocument();
        $legacyTxId = $this->writeLegacySet(100.0, 60.0);

        $this->artisanDedupe(apply: true)
            ->assertExitCode(1)
            ->expectsOutputToContain('UNBALANCED_LEGACY_SET')
            ->run();

        $this->assertNull($this->reversalOf($legacyTxId), 'an unbalanced legacy set was reversed anyway');

        // The fixture is DELIBERATELY unbalanced — that is the whole point of the case — and
        // AccountingTestCase's tearDown asserts the C1 invariant ("every transaction balances")
        // over every tracked company. Remove the fixture now that the assertions are made, so the
        // suite-wide invariant stays a real ratchet instead of being weakened for this one test.
        DB::table('journal_entries')->where('transaction_id', $legacyTxId)->delete();
        DB::table('transactions')->where('id', $legacyTxId)->delete();
    }

    public function test_a_legacy_row_written_before_the_window_is_out_of_scope(): void
    {
        $this->postEngineDocument();
        $legacyTxId = $this->writeLegacySet(100.0, 100.0);

        $exit = Artisan::call('accounting:dedupe-cutover', [
            '--company' => $this->company->id,
            '--from' => Carbon::create(2026, 6, 16)->toDateTimeString(),
            '--apply' => true,
        ]);

        $this->assertSame(0, $exit);
        $this->assertNull($this->reversalOf($legacyTxId), 'a row outside the --from window was reversed');
    }

    public function test_the_command_refuses_without_a_window(): void
    {
        $this->artisan('accounting:dedupe-cutover', ['--company' => $this->company->id, '--apply' => true])
            ->assertExitCode(1)
            ->expectsOutputToContain('--from is required')
            ->run();
    }
}
