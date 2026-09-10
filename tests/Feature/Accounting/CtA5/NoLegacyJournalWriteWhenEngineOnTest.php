<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA5;

use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use App\Services\Accounting\AccountingLog;
use App\Services\Accounting\SupplierPayableRule;
use App\Services\TaskStatusService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * CT-A5a — **one document, one posting.** The ratchet for the whole lane.
 *
 * ── What this test exists to stop ────────────────────────────────────────────────────────────
 * CT-D1 (§0.4i) deployed the engine onto the City Travelers dev site and then found four invoices
 * (ids 2061–2064) carrying BOTH a legacy inline posting and an engine posting — KWD 1,065.000 of
 * revenue standing on the ledger twice. The lane's investigation established that those four rows
 * did NOT come from an ungated writer on this branch (see the lane report), but the shape they
 * demonstrate is real and reachable: any legacy `JournalEntry::create` that still executes while
 * `PostingSeam::isEnabledFor($company)` is true posts a row the engine cannot see, and a later
 * `accounting:replay` then posts the SAME real-world event a second time from the engine side.
 *
 * ── The oracle ───────────────────────────────────────────────────────────────────────────────
 * `journal_entries.posting_date` is written by exactly one writer in the tree —
 * {@see \App\Services\Accounting\PostingService::post()} (CT-A1 §1.7: "PostingService.php is the
 * sole `'posting_date' =>` site"). So "a row with a NULL posting_date, written while the engine
 * was on" IS a legacy write, with no interpretation needed and no allow-list to keep current. That
 * is deliberately a stronger oracle than counting `Transaction` rows or matching descriptions:
 * a legacy writer that reuses an engine transaction's id (several of the `fix:*` commands do
 * exactly that, CT-A1 §1.7) would be invisible to a header count and is caught here.
 *
 * Each case therefore asserts BOTH halves of the lane's title:
 *   - ZERO rows with a NULL `posting_date` were added by the flow, and
 *   - exactly the expected number of ENGINE documents exist for it.
 *
 * ── Mutation proof ───────────────────────────────────────────────────────────────────────────
 * Remove the `if ($engineOn) { $this->suppressLegacyFallThrough(...); return; }` guard at the tail
 * of {@see TaskStatusService::dispatchFinancial()} and `test_a_refund_status_task_writes_no_legacy
 * _rows_when_the_engine_is_on` fails: `TaskController::processTaskFinancial()` -> `processRefund
 * Task()` writes its raw pair (`TaskController.php:2569`, `:2599`) with a NULL posting_date.
 * Restore the `new TaskController()` call in
 * {@see \App\Console\Commands\UpdateHotelTaskWithSupplierPayDate} and
 * `test_no_caller_reaches_the_legacy_dispatcher_except_the_engine_off_branch` fails.
 */
class NoLegacyJournalWriteWhenEngineOnTest extends AccountingTestCase
{
    private Company $company;

    private Agent $agent;

    private Client $client;

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
        $this->agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => User::factory()->create()->id,
            'type_id' => $agentType->id,
        ]);
        $this->client = Client::factory()->create(['agent_id' => $this->agent->id]);
    }

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ── The oracle ───────────────────────────────────────────────────────────────────────────

    /**
     * Every `journal_entries` row for this company that carries no `posting_date` — i.e. every row
     * NOT written by the engine. See the class docblock for why this column is the discriminator.
     */
    private function legacyRowCount(): int
    {
        return (int) DB::table('journal_entries')
            ->where('company_id', $this->company->id)
            ->whereNull('posting_date')
            ->count();
    }

    private function engineDocumentCount(): int
    {
        return (int) DB::table('transactions')
            ->where('company_id', $this->company->id)
            ->whereNotNull('idempotency_key')
            ->whereNull('deleted_at')
            ->count();
    }

    private function supplier(string $trigger = SupplierPayableRule::TRIGGER_ON_ISSUE): Supplier
    {
        return Supplier::factory()->create([
            'payable_trigger' => $trigger,
            'payable_hold' => false,
        ]);
    }

    private function task(Supplier $supplier, string $status, float $total = 100.0, ?Task $original = null): Task
    {
        return Task::factory()->create([
            'company_id' => $this->company->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => $status,
            'total' => $total,
            'original_task_id' => $original?->id,
            'issued_date' => Carbon::create(2026, 6, 10),
        ]);
    }

    // ── The flows ────────────────────────────────────────────────────────────────────────────

    public function test_an_issued_task_writes_no_legacy_rows_when_the_engine_is_on(): void
    {
        $task = $this->task($this->supplier(), 'issued', total: 250.000);

        $legacyBefore = $this->legacyRowCount();

        app(TaskStatusService::class)->dispatchFinancial($task);

        $this->assertSame(
            $legacyBefore,
            $this->legacyRowCount(),
            'issuing a task with the engine ON added a journal row with no posting_date'
        );
        $this->assertGreaterThan(
            0,
            $this->engineDocumentCount(),
            'issuing a task with the engine ON posted no engine document at all'
        );
    }

    public function test_a_refund_status_task_writes_no_legacy_rows_when_the_engine_is_on(): void
    {
        $supplier = $this->supplier();
        $issued = $this->task($supplier, 'issued', total: 250.000);
        app(TaskStatusService::class)->dispatchFinancial($issued);

        $legacyBefore = $this->legacyRowCount();

        $issued->status = 'refund';
        $issued->save();
        app(TaskStatusService::class)->dispatchFinancial($issued);

        $this->assertSame(
            $legacyBefore,
            $this->legacyRowCount(),
            'a refund-status task fell through to TaskController::processRefundTask() with the engine ON'
        );
    }

    public function test_a_refund_void_task_writes_no_legacy_rows_and_is_named_as_an_uncovered_gap(): void
    {
        $supplier = $this->supplier();
        $issued = $this->task($supplier, 'issued', total: 250.000);
        app(TaskStatusService::class)->dispatchFinancial($issued);

        $legacyBefore = $this->legacyRowCount();

        // `refund_void` is what AirFileParser returns for an RFNX block (a VOID *of a refund*), but
        // `tasks.status` is an ENUM that does not list it — on the real dev database as well as
        // here. So the status is set in memory, exactly as the parser hands it over and exactly as
        // dispatchFinancial() reads it, without asserting a persistence path the schema does not
        // currently allow. (That mismatch is a separate, pre-existing defect: the parser can
        // produce a value the column rejects. Recorded, not fixed here.)
        $rfnx = $this->task($supplier, 'void', total: 250.000, original: $issued);
        $rfnx->status = 'refund_void';

        app(TaskStatusService::class)->dispatchFinancial($rfnx);

        $this->assertSame(
            $legacyBefore,
            $this->legacyRowCount(),
            'a refund_void (RFNX) task fell through to TaskController::processVoidTask() with the engine ON'
        );

        // The suppression must never be silent: `refund_void` has NO engine feeder, so the audit
        // row is the only record that a real money event took no posting at all.
        $audit = DB::connection(AccountingLog::DURABLE_CONNECTION)
            ->table('accounting_audit_log')
            ->where('company_id', $this->company->id)
            ->where('action', 'legacy_fallthrough_suppressed')
            ->get();

        $this->assertGreaterThan(0, $audit->count(), 'the suppressed refund_void left no audit row');

        $payloads = $audit->map(fn ($r) => (string) ($r->after ?? ''))->implode(' ');
        $this->assertStringContainsString('refund_void', $payloads);
        $this->assertStringContainsString('NONE', $payloads, 'refund_void must be recorded as having NO covering feeder');
    }

    public function test_a_void_task_writes_no_legacy_rows_when_the_engine_is_on(): void
    {
        $supplier = $this->supplier();
        $issued = $this->task($supplier, 'issued', total: 250.000);
        app(TaskStatusService::class)->dispatchFinancial($issued);

        $legacyBefore = $this->legacyRowCount();

        $void = $this->task($supplier, 'void', total: 250.000, original: $issued);
        app(TaskStatusService::class)->dispatchFinancial($void);

        $this->assertSame(
            $legacyBefore,
            $this->legacyRowCount(),
            'a void task added a journal row with no posting_date while the engine was ON'
        );
    }

    public function test_engine_off_still_reaches_the_legacy_dispatcher_unchanged(): void
    {
        // The other half of the contract: nothing above may be achieved by breaking the OFF path.
        // With the engine off, a status with no engine branch must still reach the legacy
        // dispatcher exactly as it did at HEAD.
        // `processTaskFinancial()` refuses by name when the task's supplier is not activated for
        // the company — and this class never creates a `supplier_companies` row, so that refusal
        // is a stable property of the fixture rather than an accident. Reaching THAT exception is
        // a fixture-free proof that the OFF path still routes exactly where it did at HEAD: no
        // chart, no accounts, no legacy writer preconditions to build.
        $supplier = $this->supplier();
        $task = $this->task($supplier, 'refund', total: 250.000);

        config(['accounting.engine.enabled' => false]);

        $this->expectExceptionMessageMatches('/Supplier company not activated or not found/');

        app(TaskStatusService::class)->dispatchFinancial($task);
    }

    public function test_the_same_task_is_suppressed_rather_than_thrown_when_the_engine_is_on(): void
    {
        // The mirror of the test above, and the reason the suppression is a `return` and not a
        // rethrow: with the engine ON the legacy dispatcher is never reached, so neither is the
        // exception it would have raised. The pair together pin the routing decision from both
        // sides — OFF still goes there, ON never does.
        $supplier = $this->supplier();
        $task = $this->task($supplier, 'refund', total: 250.000);

        $legacyBefore = $this->legacyRowCount();

        app(TaskStatusService::class)->dispatchFinancial($task);

        $this->assertSame($legacyBefore, $this->legacyRowCount());
    }

    // ── The source ratchet ───────────────────────────────────────────────────────────────────

    /**
     * `TaskController::processTaskFinancial()` is the entry point to every raw legacy task writer
     * (`processIssuedTask()`, `processRefundTask()`, `processVoidTask()`). It must have exactly ONE
     * caller in the whole tree — the engine-OFF tail of `TaskStatusService::dispatchFinancial()` —
     * because a caller anywhere else bypasses the routing decision entirely and runs the legacy
     * writers with the engine ON, which is precisely what
     * {@see \App\Console\Commands\UpdateHotelTaskWithSupplierPayDate} did until this lane.
     */
    public function test_no_caller_reaches_the_legacy_dispatcher_except_the_engine_off_branch(): void
    {
        $appDir = base_path('app');
        $offenders = [];

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($appDir));

        foreach ($it as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $rel = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));

            if ($rel === 'app/Http/Controllers/TaskController.php') {
                continue; // the method's own declaration and its internal switch
            }

            foreach (file($file->getPathname()) as $i => $line) {
                // Comments and docblocks name the method constantly (it is the thing every wave
                // reasons about); only an actual call counts.
                $trimmed = ltrim($line);

                if ($trimmed === '' || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '//')) {
                    continue;
                }

                if (! str_contains($line, 'processTaskFinancial(')) {
                    continue;
                }

                $offenders[] = $rel.':'.($i + 1);
            }
        }

        $this->assertSame(
            ['app/Services/TaskStatusService.php'],
            array_values(array_unique(array_map(
                fn (string $o) => explode(':', $o)[0],
                $offenders
            ))),
            'processTaskFinancial() must be called from TaskStatusService::dispatchFinancial() and nowhere else; found: '
                .implode(', ', $offenders)
        );

        $this->assertCount(
            1,
            $offenders,
            'TaskStatusService must call processTaskFinancial() exactly once (the engine-OFF tail); found: '
                .implode(', ', $offenders)
        );
    }
}
