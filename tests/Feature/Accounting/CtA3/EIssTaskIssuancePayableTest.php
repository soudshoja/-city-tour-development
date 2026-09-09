<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA3;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\PostedDocument;
use App\Services\Accounting\SupplierPayableRule;
use App\Services\Accounting\TaskIssuancePayableService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * CT-A3 wave 1 — feeder **E-iss** and its gate, OWNER RULINGS 2026-09-09.
 *
 * Ruling 1 (the feeder): "anything comes into task where its been issued/vouchered and needs to be
 * paid to supplier we want to automatically add it to the right account so we know how much we need
 * to pay regardless of them being invoiced; invoicing them is another story where its accounts
 * receivable."
 *
 * Ruling 2 (R-CT3, the gate): "need to pay are the one guaranteed to be paid not hold or some
 * supplier confirmed so this needs to be done based on the status of supplier which we set on
 * supplier aspect … from there decide add or not add? need to be paid or not paid."
 *
 * The gap: CT-A2 §1.4 row 1 / §3.1 row 5 — the engine has NO issuance document, so the 5,495 of
 * 8,706 issued City Travelers tasks that were never invoiced (CT-A1 §0) carry no supplier payable
 * anywhere and the agency cannot see what it owes.
 */
class EIssTaskIssuancePayableTest extends AccountingTestCase
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

    private function supplier(string $trigger = SupplierPayableRule::TRIGGER_ON_ISSUE, bool $hold = false): Supplier
    {
        return Supplier::factory()->create([
            'payable_trigger' => $trigger,
            'payable_hold' => $hold,
        ]);
    }

    private function task(Supplier $supplier, string $status, float $total = 100.0, ?string $voucherStatus = null): Task
    {
        return Task::factory()->create([
            'company_id' => $this->company->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => $status,
            'total' => $total,
            'voucher_status' => $voucherStatus,
            'issued_date' => Carbon::create(2026, 6, 10),
        ]);
    }

    private function accrual(Task $task): ?Transaction
    {
        return Transaction::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->where('idempotency_key', TaskIssuancePayableService::idempotencyKeyFor((int) $task->id))
            ->first();
    }

    private function accountIdByCode(string $code): int
    {
        return (int) Account::query()->withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->where('code', $code)
            ->value('id');
    }

    // ── The document ─────────────────────────────────────────────────────────────────────────────

    public function test_an_issued_task_accrues_dr_unbilled_supplier_cost_cr_supplier_payable(): void
    {
        $supplier = $this->supplier();
        $task = $this->task($supplier, 'issued', total: 250.000);

        $posted = app(TaskIssuancePayableService::class)->postIfDue($task);

        $this->assertInstanceOf(PostedDocument::class, $posted);
        $this->assertSame('JV', $posted->transaction->doc_type);
        $this->assertSame('SUPPLIER_ACCRUAL', $posted->transaction->sub_type);
        $this->assertSame(
            'task:'.$task->id.':issuance-payable',
            $posted->transaction->idempotency_key
        );

        $lines = DB::table('journal_entries')
            ->where('transaction_id', $posted->transaction->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $lines);

        $debit = $lines->firstWhere('debit', '>', 0);
        $credit = $lines->firstWhere('credit', '>', 0);

        $this->assertSame(
            $this->accountIdByCode('1430'),
            (int) $debit->account_id,
            'The cost is deferred to 1430 Unbilled Supplier Cost — the agency bought the work but '
            .'has not billed it, so it is an asset, not yet COGS.'
        );
        $this->assertEqualsWithDelta(250.0, (float) $debit->debit, 0.0005);

        $payableAccount = Account::query()->withoutGlobalScopes()->find($credit->account_id);
        $this->assertSame(
            'Liabilities',
            Account::query()->withoutGlobalScopes()->find($payableAccount->root_id)->name,
            'The credit is the supplier payable — a liability.'
        );
        $this->assertEqualsWithDelta(250.0, (float) $credit->credit, 0.0005);
        $this->assertSame(
            $supplier->id,
            (int) $credit->type_reference_id,
            'The supplier must be recoverable from the AP line (party = supplier).'
        );
        $this->assertSame('payable', $credit->type);
        $this->assertNotNull($credit->posting_date);
    }

    public function test_the_accrual_is_idempotent_per_task(): void
    {
        $task = $this->task($this->supplier(), 'issued');

        $first = app(TaskIssuancePayableService::class)->postIfDue($task);
        $second = app(TaskIssuancePayableService::class)->postIfDue($task);

        $this->assertSame($first->transaction->id, $second->transaction->id);
        $this->assertSame(
            1,
            Transaction::withoutGlobalScopes()
                ->where('company_id', $this->company->id)
                ->where('sub_type', 'SUPPLIER_ACCRUAL')
                ->count(),
            'A second dispatch of the same task must never accrue a second payable.'
        );
    }

    public function test_the_exchange_rate_is_captured_on_a_foreign_currency_task(): void
    {
        // KWD 100.000 bought for USD 325.733 at 0.307 KWD per USD.
        $task = $this->task($this->supplier(), 'issued', total: 100.000);
        $task->original_currency = 'USD';
        $task->original_total = 325.733;
        $task->exchange_rate = 0.307000;
        $task->save();

        $posted = app(TaskIssuancePayableService::class)->postIfDue($task);

        $lines = DB::table('journal_entries')
            ->where('transaction_id', $posted->transaction->id)
            ->get();

        $this->assertCount(2, $lines);

        foreach ($lines as $line) {
            $this->assertSame('USD', $line->currency, 'The line carries the currency actually billed.');
            $this->assertEqualsWithDelta(
                0.307,
                (float) $line->exchange_rate,
                0.000001,
                'CT-F9: 4,553 legacy non-KWD rows carry exchange_rate = 1. The engine must carry '
                .'the real rate on the row.'
            );
            $this->assertEqualsWithDelta(325.733, (float) $line->original_amount, 0.0005);
            $this->assertEqualsWithDelta(
                100.0,
                (float) $line->debit + (float) $line->credit,
                0.0005,
                'The posted debit/credit is always the BASE amount; the FC figure lives in '
                .'original_amount.'
            );
        }
    }

    public function test_a_task_with_no_usable_fx_pair_posts_cleanly_in_base_currency(): void
    {
        $task = $this->task($this->supplier(), 'issued', total: 100.000);
        // A currency label with no FC amount and no rate — CT-F9's exact legacy corruption shape.
        $task->original_currency = 'USD';
        $task->original_total = null;
        $task->exchange_rate = null;
        $task->save();

        $posted = app(TaskIssuancePayableService::class)->postIfDue($task);

        $base = strtoupper((string) config('accounting.engine.base_currency'));

        foreach (DB::table('journal_entries')->where('transaction_id', $posted->transaction->id)->get() as $line) {
            $this->assertSame(
                $base,
                strtoupper((string) $line->currency),
                'With no FC amount and no rate there is nothing to convert — the line must stay '
                .'honestly base-currency rather than inventing a rate.'
            );
            $this->assertEqualsWithDelta(1.0, (float) $line->exchange_rate, 0.000001);
        }
    }

    public function test_a_zero_supplier_cost_task_accrues_nothing(): void
    {
        $task = $this->task($this->supplier(), 'issued', total: 0.0);

        $this->assertNull(app(TaskIssuancePayableService::class)->postIfDue($task));
        $this->assertNull($this->accrual($task));
    }

    // ── R-CT3: the (task status x supplier rule) matrix ──────────────────────────────────────────

    /**
     * @dataProvider gateMatrix
     */
    public function test_supplier_rule_and_task_status_decide_whether_the_payable_is_added(
        string $trigger,
        bool $hold,
        string $status,
        ?string $voucherStatus,
        bool $expectPosted,
        string $because
    ): void {
        $task = $this->task($this->supplier($trigger, $hold), $status, voucherStatus: $voucherStatus);

        app(TaskIssuancePayableService::class)->postIfDue($task);

        $expectPosted
            ? $this->assertNotNull($this->accrual($task), $because)
            : $this->assertNull($this->accrual($task), $because);
    }

    /** @return array<string, array{0:string,1:bool,2:string,3:?string,4:bool,5:string}> */
    public static function gateMatrix(): array
    {
        $onIssue = SupplierPayableRule::TRIGGER_ON_ISSUE;
        $onConfirm = SupplierPayableRule::TRIGGER_ON_SUPPLIER_CONFIRM;
        $onVoucher = SupplierPayableRule::TRIGGER_ON_VOUCHER;
        $manual = SupplierPayableRule::TRIGGER_MANUAL;

        return [
            // trigger, hold, task status, voucher_status, expect posted, why
            'on_issue + issued => posted' => [$onIssue, false, 'issued', null, true, 'The default rule: a ticketed task is a guaranteed liability.'],
            'on_issue + reissued => posted' => [$onIssue, false, 'reissued', null, true, 'A reissue is issued work.'],
            'on_issue + emd => posted' => [$onIssue, false, 'emd', null, true, 'An EMD is issued work.'],
            'on_issue + on hold => NOT posted' => [$onIssue, false, 'on hold', null, false, 'THE RULING: a held task is not guaranteed to be paid.'],
            'on_issue + confirmed => NOT posted' => [$onIssue, false, 'confirmed', null, false, 'THE RULING: "not hold or some supplier confirmed" — confirmed is below this supplier\'s trigger.'],
            'on_issue + needs_review => NOT posted' => [$onIssue, false, 'needs_review', null, false, 'An unmapped supplier status must never accrue money.'],
            'on_issue + expired => NOT posted' => [$onIssue, false, 'expired', null, false, 'An expired hold owes nobody anything.'],

            'on_supplier_confirm + confirmed => posted' => [$onConfirm, false, 'confirmed', null, true, 'This supplier holds inventory firm on confirmation, so the money is due then.'],
            'on_supplier_confirm + issued => posted' => [$onConfirm, false, 'issued', null, true, 'Past the trigger is still committed.'],
            'on_supplier_confirm + on hold => NOT posted' => [$onConfirm, false, 'on hold', null, false, 'Even the earliest trigger never accrues on a hold.'],

            'on_voucher + issued with a voucher => posted' => [$onVoucher, false, 'issued', 'VCH-1201', true, 'Issued AND vouchered.'],
            'on_voucher + issued with no voucher => NOT posted' => [$onVoucher, false, 'issued', null, false, 'This supplier only bills against a raised voucher.'],
            'on_voucher + issued with a cancelled voucher => NOT posted' => [$onVoucher, false, 'issued', 'cancelled', false, 'A cancelled voucher is not a raised voucher.'],

            'manual + issued => NOT posted' => [$manual, false, 'issued', null, false, 'This supplier is reconciled by hand; the engine must not invent the payable.'],

            'payable_hold overrides on_issue + issued' => [$onIssue, true, 'issued', null, false, 'The per-supplier kill switch wins at every status.'],
            'payable_hold overrides on_supplier_confirm + confirmed' => [$onConfirm, true, 'confirmed', null, false, 'Same, on the earliest trigger.'],
        ];
    }

    public function test_a_task_held_today_and_issued_tomorrow_accrues_on_the_later_transition(): void
    {
        $supplier = $this->supplier();
        $task = $this->task($supplier, 'on hold');

        app(TaskIssuancePayableService::class)->postIfDue($task);
        $this->assertNull($this->accrual($task), 'Nothing while it is held.');

        $task->status = 'issued';
        $task->save();

        app(TaskIssuancePayableService::class)->postIfDue($task->fresh());

        $this->assertNotNull(
            $this->accrual($task),
            'Status transitions drive posting: the payable appears when the task actually commits.'
        );
    }

    public function test_a_supplier_with_no_row_on_the_task_accrues_nothing(): void
    {
        $task = Task::factory()->create([
            'company_id' => $this->company->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'supplier_id' => null,
            'type' => 'flight',
            'status' => 'issued',
            'total' => 100.0,
        ]);

        $this->assertNull(app(TaskIssuancePayableService::class)->postIfDue($task));
    }

    public function test_an_unknown_or_null_payable_trigger_falls_back_to_the_configured_default(): void
    {
        $supplier = Supplier::factory()->create();
        DB::table('suppliers')->where('id', $supplier->id)->update(['payable_trigger' => 'on_issue']);

        $rule = app(SupplierPayableRule::class);

        $this->assertSame('on_issue', $rule->triggerFor($supplier->fresh()));
        $this->assertSame(
            (string) config('accounting.supplier_payable.default_trigger'),
            $rule->triggerFor(null),
            'A missing supplier must degrade to the documented default, never throw.'
        );
    }

    // ── Reversal ─────────────────────────────────────────────────────────────────────────────────

    public function test_a_cancelled_task_reverses_its_accrual_instead_of_deleting_it(): void
    {
        $task = $this->task($this->supplier(), 'issued', total: 180.0);
        app(TaskIssuancePayableService::class)->postIfDue($task);
        $accrual = $this->accrual($task);
        $this->assertNotNull($accrual);

        $task->status = 'cancelled';
        $task->save();

        app(TaskIssuancePayableService::class)->postIfDue($task->fresh());

        $this->assertNotNull(
            Transaction::withoutGlobalScopes()->find($accrual->id),
            'The original document must survive — reversal is a new REV document, never a delete.'
        );

        $reversal = Transaction::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->where('doc_type', 'REV')
            ->first();
        $this->assertNotNull($reversal, 'A REV document must exist.');

        $net = (float) DB::table('journal_entries')
            ->where('company_id', $this->company->id)
            ->where('task_id', $task->id)
            ->sum(DB::raw('debit - credit'));

        $this->assertEqualsWithDelta(0.0, $net, 0.0005, 'The accrual and its reversal must net to zero.');
    }

    public function test_reverse_for_task_is_a_no_op_when_nothing_was_accrued(): void
    {
        $task = $this->task($this->supplier(SupplierPayableRule::TRIGGER_MANUAL), 'issued');

        app(TaskIssuancePayableService::class)->reverseForTask($task);

        $this->assertSame(
            0,
            Transaction::withoutGlobalScopes()->where('company_id', $this->company->id)->count(),
            'Callers invoke this blindly on every void path; it must be safe on a task with no accrual.'
        );
    }

    public function test_the_engine_gate_is_respected(): void
    {
        config(['accounting.engine.enabled' => false]);

        $task = $this->task($this->supplier(), 'issued');

        $this->assertNull(
            app(TaskIssuancePayableService::class)->postIfDue($task),
            'This feeder has no legacy counterpart, so with the engine off it must post nothing at all.'
        );
        $this->assertNull($this->accrual($task));
    }
}
