<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA3;

use App\Exceptions\Accounting\NothingOutstandingToCreditException;
use App\Exceptions\Accounting\RefundExceedsOutstandingException;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\JournalEntry;
use App\Models\Refund;
use App\Models\RefundDetail;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\RefundPostingService;
use App\Services\Accounting\SaleDraftBuilder;
use App\Services\Accounting\SaleDraftInput;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * CT-A3 **R3-3** — VERIFY-CT-A3-STACK-R2 §3.2 finding **V3**: the refund amounts were unbounded,
 * and R2-1 measured the discrepancy without acting on it in either direction.
 *
 * `RefundController::store()` validated `tasks.*.original_invoice_price` and
 * `tasks.*.total_refund_to_client` as bare `['required','numeric']`. On a live sale of 100 with a
 * credit of 500 requested, the credit note correctly reversed exactly the live 100 while the
 * disposition credited the whole 500: `RECEIVABLE_CONTROL` **+500.000**, `CLIENT_ADVANCE`
 * **−500.000**. The client's NET position stayed zero, so the trial balance was right and no
 * aggregate check in any wave report could see it — while AR ageing, the client statement and the
 * credit-limit check each read one leaf without the other.
 *
 * The default is now REFUSE, at BOTH boundaries, tagged to owner ruling **R-CT6** (whether an
 * over-credit should instead be clamped).
 */
class R33RefundExceedsOutstandingTest extends AccountingTestCase
{
    use GrantsAccountingModule;

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    /** @return array{0: Company, 1: Agent, 2: Client, 3: Supplier, 4: Task, 5: Invoice, 6: InvoiceDetail, 7: Branch, 8: User} */
    private function makeFixture(): array
    {
        $company = Company::factory()->create();
        $this->grantAccountingModule($company);
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);

        $agentUser = User::factory()->create();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentType->id]);

        $client = Client::factory()->create(['agent_id' => $agent->id, 'company_id' => $company->id]);
        $supplier = Supplier::factory()->create();

        $task = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => 'issued',
            'total' => 60.0,
            'issued_date' => now()->subDays(5),
        ]);

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'amount' => 100.000,
            'status' => 'paid',
            'invoice_date' => now()->subDays(4),
        ]);

        $detail = InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id]);

        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $company->id]);

        $this->trackCompanyForInvariants($company->id);

        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        Artisan::call('accounting:periods:init', ['--company' => $company->id]);

        return [$company, $agent, $client, $supplier, $task->fresh(), $invoice, $detail, $branch, $admin];
    }

    private function postSale(Company $c, Agent $a, Client $cl, Supplier $s, Task $t, Invoice $i, InvoiceDetail $d, float $sell = 100.0, float $cost = 60.0): void
    {
        $lines = (new SaleDraftBuilder)->buildLines(new SaleDraftInput(
            serviceType: $t->type, sellAmount: $sell, costAmount: $cost,
            postingBasis: SaleDraftInput::BASIS_AGENT,
            clientId: $cl->id, clientName: $cl->full_name,
            supplierId: $s->id, supplierName: $s->name,
            agentId: $a->id, agentName: $a->name,
            invoiceId: $i->id, invoiceDetailId: $d->id, taskId: $t->id,
        ));

        app(PostingService::class)->post(new DocumentDraft(
            companyId: $c->id, branchId: (int) $a->branch_id, docType: 'INV', subType: 'SALE',
            docDate: now()->subDays(4), narration: 'Sale', lines: $lines,
            idempotencyKey: 'invoice-detail:'.$d->id.':sale', invoiceId: $i->id,
        ));
    }

    private function makeRefund(Company $c, Agent $a, Invoice $i, Task $t, Client $cl, float $credit, string $suffix): Refund
    {
        $refund = Refund::create([
            'refund_number' => 'REF-R33-'.$suffix.'-'.uniqid(),
            'company_id' => $c->id, 'branch_id' => $a->branch_id, 'agent_id' => $a->id,
            'invoice_id' => $i->id, 'method' => 'Credit', 'status' => Refund::STATUS_APPROVED,
            'refund_date' => now(), 'total_refund_amount' => 0, 'total_refund_charge' => 0, 'total_nett_refund' => 0,
        ]);

        RefundDetail::create([
            'refund_id' => $refund->id, 'task_id' => $t->id, 'client_id' => $cl->id,
            'original_invoice_price' => $credit, 'original_task_cost' => 60.000,
            'original_task_profit' => $credit - 60.0,
            'refund_fee_to_client' => 0, 'supplier_charge' => 0, 'supplier_refund_amount' => null,
            'new_task_profit' => 0, 'total_refund_to_client' => $credit,
        ]);

        return $refund->fresh();
    }

    private function netForPurpose(int $companyId, string $purpose, ?string $serviceType = null): float
    {
        $account = app(AccountResolver::class)->resolve($purpose, $companyId, $serviceType);
        $rows = JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')->where('account_id', $account->id);

        return round((float) (clone $rows)->sum('debit') - (float) (clone $rows)->sum('credit'), 3);
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // 100 sale, credit 500 → REFUSED, and the ledger is untouched.
    // ════════════════════════════════════════════════════════════════════════════════════════════

    public function test_a_credit_of_500_against_a_sale_of_100_is_refused_and_the_ledger_is_untouched(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $detail] = $this->makeFixture();

        $this->postSale($company, $agent, $client, $supplier, $task, $invoice, $detail);

        $linesBefore = JournalEntry::withoutGlobalScopes()->where('company_id', $company->id)->count();
        $revBefore = $this->netForPurpose((int) $company->id, 'SERVICE_REVENUE', 'flight');
        $arBefore = $this->netForPurpose((int) $company->id, 'RECEIVABLE_CONTROL');

        $refund = $this->makeRefund($company, $agent, $invoice, $task, $client, 500.0, 'over');

        try {
            app(RefundPostingService::class)->post($refund, null);
            $this->fail('a credit of 500 against an outstanding sell of 100 must be refused');
        } catch (RefundExceedsOutstandingException $e) {
            $this->assertSame(500.0, $e->requestedCredit);
            $this->assertSame(100.0, $e->outstandingSell);
            $this->assertSame((int) $task->id, $e->taskId);
            $this->assertStringContainsString('R-CT6', $e->getMessage(), 'the refusal names the owner ruling it defaults under');
        }

        $this->assertSame(
            $linesBefore,
            JournalEntry::withoutGlobalScopes()->where('company_id', $company->id)->count(),
            'not one journal line was written'
        );
        $this->assertSame($revBefore, $this->netForPurpose((int) $company->id, 'SERVICE_REVENUE', 'flight'));
        $this->assertSame($arBefore, $this->netForPurpose((int) $company->id, 'RECEIVABLE_CONTROL'));
        $this->assertSame(0.0, $this->netForPurpose((int) $company->id, 'CLIENT_ADVANCE'), 'and nothing reached the client advance');

        // ── The audit trail ─────────────────────────────────────────────────────────────────────
        // INVERTED BY CT-A3 R4. This assertion used to pin `count() === 0` on the default
        // connection, with a note recording that the DB row did NOT survive — R3 §4.1's finding,
        // and a direct contradiction of R2-1's `refuseNothingOutstanding()` docblock, which had
        // claimed durability since R2. The claim is now true instead of merely documented: the
        // refusal writes through `AccountingLog::eventDurable()`, i.e. the independent
        // `accounting_audit` connection `IdempotencyKeyRejection` already uses for exactly this,
        // so the INSERT commits on its own session and the rollback cannot reach it.
        //
        // Read on the durable connection, not the default one — from inside a RefreshDatabase test
        // the default connection's own open transaction predates the durable INSERT and cannot see
        // it, which is precisely what proves a different session committed the row. The mechanism
        // has its own file: {@see \Tests\Feature\Accounting\CtA3\R42DurableRefusalAuditTest}.
        $this->assertSame(
            1,
            DB::connection(\App\Services\Accounting\AccountingLog::DURABLE_CONNECTION)
                ->table('accounting_audit_log')
                ->where('company_id', $company->id)
                ->where('action', 'refund_crn_refused')
                ->count(),
            'the refusal is findable afterwards, not only by whoever was watching the screen'
        );
    }

    /**
     * The half of the audit trail that DOES survive a refusal: the structured file-log event,
     * carrying both figures and the ruling it defaults under, so an operator can answer "why was
     * this refund refused?" from the log alone.
     */
    public function test_the_refusal_writes_a_structured_log_event_naming_both_figures(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $detail] = $this->makeFixture();

        $this->postSale($company, $agent, $client, $supplier, $task, $invoice, $detail);

        $refund = $this->makeRefund($company, $agent, $invoice, $task, $client, 500.0, 'log');

        \Illuminate\Support\Facades\Log::spy();

        try {
            app(RefundPostingService::class)->post($refund, null);
        } catch (RefundExceedsOutstandingException) {
            // expected
        }

        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context = []): bool {
                return $message === 'accounting.refund_crn.credit_exceeds_outstanding'
                    && ($context['requested_credit'] ?? null) === 500.0
                    && ($context['outstanding_sell'] ?? null) === 100.0
                    && ($context['over_credit'] ?? null) === 400.0
                    && str_contains((string) ($context['ruling'] ?? ''), 'R-CT6');
            })
            ->once();
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // Credit exactly the outstanding sell → accepted.
    // ════════════════════════════════════════════════════════════════════════════════════════════

    public function test_a_credit_equal_to_the_outstanding_sell_is_accepted(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $detail] = $this->makeFixture();

        $this->postSale($company, $agent, $client, $supplier, $task, $invoice, $detail);

        app(RefundPostingService::class)->post(
            $this->makeRefund($company, $agent, $invoice, $task, $client, 100.0, 'exact'),
            null
        );

        $this->assertSame(0.0, $this->netForPurpose((int) $company->id, 'SERVICE_REVENUE', 'flight'), 'the sale is credited in full');
        $this->assertSame(-100.0, $this->netForPurpose((int) $company->id, 'CLIENT_ADVANCE'), 'and the client is credited exactly the sale');
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // Two credits, 60 then 40. Recorded EXACTLY as it behaves, which is not what a naive reading
    // of "60 + 40 = 100, both fine" would predict — and the difference is a DIFFERENT open ruling,
    // not this fix.
    // ════════════════════════════════════════════════════════════════════════════════════════════

    public function test_a_second_credit_after_a_first_one_is_refused_because_the_crn_is_a_full_reversal(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $detail] = $this->makeFixture();

        $this->postSale($company, $agent, $client, $supplier, $task, $invoice, $detail);

        // 60 is WITHIN the outstanding 100, so R3-3's bound lets it through.
        app(RefundPostingService::class)->post(
            $this->makeRefund($company, $agent, $invoice, $task, $client, 60.0, 'first'),
            null
        );

        // …and the credit note is a FULL reversal of the live sale (R2-1's design, unchanged by
        // R3-3), so the outstanding sell is now 0.000, not 40.000. A partial credit note is NOT
        // expressible today: "what a partial or second refund on an already-refunded task means"
        // is the deferred partial-credit ruling (verify-R1 report §7 item 2), and R3-3 deliberately
        // does not decide it — it bounds the OTHER direction.
        $this->assertSame(0.0, $this->netForPurpose((int) $company->id, 'SERVICE_REVENUE', 'flight'));
        $this->assertSame(-60.0, $this->netForPurpose((int) $company->id, 'CLIENT_ADVANCE'));

        $second = $this->makeRefund($company, $agent, $invoice, $task, $client, 40.0, 'second');

        // So the SECOND credit is refused — by NothingOutstandingToCredit, which is the more
        // informative of the two refusals here (there is no live sale at all, not merely too
        // little of one), and by the pre-existing R2-1 guard rather than by R3-3.
        try {
            app(RefundPostingService::class)->post($second, null);
            $this->fail('the second credit must be refused: the first already reversed the whole sale');
        } catch (NothingOutstandingToCreditException $e) {
            $this->assertSame((int) $task->id, $e->taskId);
        }

        $this->assertSame(-60.0, $this->netForPurpose((int) $company->id, 'CLIENT_ADVANCE'), 'and the ledger is unchanged by the refusal');
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // The controller boundary — numeric AND ≤ outstanding.
    // ════════════════════════════════════════════════════════════════════════════════════════════

    public function test_the_controller_refuses_an_over_credit_before_anything_is_written(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $detail, , $admin] = $this->makeFixture();

        $this->postSale($company, $agent, $client, $supplier, $task, $invoice, $detail);

        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'create refund', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'refund-creator', 'company_id' => $company->id], ['guard_name' => 'web']);
        $admin->assignRole($role);
        $role->givePermissionTo(['create refund']);

        $linesBefore = JournalEntry::withoutGlobalScopes()->where('company_id', $company->id)->count();

        $this->actingAs($admin)->post(route('refunds.store'), [
            'date' => now()->toDateString(),
            'method' => 'Credit',
            'tasks' => [[
                'task_id' => $task->id,
                'original_invoice_price' => 500,
                'original_task_cost' => 60,
                'original_task_profit' => 440,
                'refund_fee_to_client' => 0,
                'supplier_charge' => 0,
                'new_task_profit' => 0,
                'total_refund_to_client' => 500,
            ]],
        ])->assertSessionHasErrors('error');

        $this->assertSame(0, Refund::where('company_id', $company->id)->count(), 'no refund row was created');
        $this->assertSame(
            $linesBefore,
            JournalEntry::withoutGlobalScopes()->where('company_id', $company->id)->count(),
            'and not one journal line was written'
        );
    }

    public function test_the_controller_lets_a_credit_within_the_outstanding_sell_through(): void
    {
        [$company, $agent, $client, $supplier, $task, $invoice, $detail, , $admin] = $this->makeFixture();

        $this->postSale($company, $agent, $client, $supplier, $task, $invoice, $detail);

        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'create refund', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'refund-creator', 'company_id' => $company->id], ['guard_name' => 'web']);
        $admin->assignRole($role);
        $role->givePermissionTo(['create refund']);

        $this->actingAs($admin)->post(route('refunds.store'), [
            'date' => now()->toDateString(),
            'method' => 'Credit',
            'tasks' => [[
                'task_id' => $task->id,
                'original_invoice_price' => 100,
                'original_task_cost' => 60,
                'original_task_profit' => 40,
                'refund_fee_to_client' => 0,
                'supplier_charge' => 0,
                'new_task_profit' => 0,
                'total_refund_to_client' => 100,
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Refund::where('company_id', $company->id)->count());
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // The boundary must DEFER, never silently pass, when there is nothing to measure.
    // ════════════════════════════════════════════════════════════════════════════════════════════

    public function test_the_outstanding_reader_returns_null_when_no_sale_was_ever_posted(): void
    {
        [$company, , , , $task] = $this->makeFixture();

        $this->assertNull(
            app(RefundPostingService::class)->outstandingSellForTask($task->fresh(), (int) $company->id),
            'no posted sale means nothing to bound — the engine still applies its own rule'
        );
    }
}
