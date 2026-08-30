<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Accounting;

use App\Models\Agent;
use App\Models\AgentSettlement;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\StatementOptions;
use App\Services\Accounting\StatementService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Tests\Support\AccountingTestCase;

/**
 * P2.5.H (p2_5-brief.md §P2.5.H): "a settled invoice disappears from open-items mode and remains
 * in full_activity; ageing buckets correct; PDF renders both modes" -- the PDF-rendering claim is
 * exercised at the HTTP layer, see {@see \Tests\Feature\Accounting\StatementControllerTest}. This
 * suite exercises {@see StatementService} + its three {@see \App\Services\Accounting\Statements\
 * PartyStatementSourceInterface} implementations directly.
 */
class StatementServiceTest extends AccountingTestCase
{
    private function service(): StatementService
    {
        return app(StatementService::class);
    }

    /** @return array{0: Company, 1: Branch} */
    private function makeCompany(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();

        $owner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $owner->id]);

        $this->trackCompanyForInvariants($company->id);

        return [$company, $branch];
    }

    private function makeAgent(Company $company, Branch $branch): Agent
    {
        $agentUser = User::factory()->create();
        $agentType = AgentType::firstOrCreate(['name' => 'Salary']);

        return Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentType->id]);
    }

    // ── Client (Invoice + PaymentApplication) ──────────────────────────────────────────────────

    public function test_client_open_items_hides_a_fully_settled_invoice_but_full_activity_keeps_it(): void
    {
        [$company, $branch] = $this->makeCompany();
        $agent = $this->makeAgent($company, $branch);
        $client = Client::factory()->create(['company_id' => $company->id, 'agent_id' => $agent->id]);

        $settledInvoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'invoice_number' => 'INV-SETTLED',
            'amount' => 100,
            'invoice_date' => Carbon::create(2026, 1, 5),
        ]);
        $openInvoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'invoice_number' => 'INV-OPEN',
            'amount' => 250,
            'invoice_date' => Carbon::create(2026, 1, 10),
        ]);

        $payment = Payment::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'company_id' => $company->id,
            'amount' => 100,
            'invoice_id' => null,
            'account_id' => null,
            'created_by' => User::factory()->create()->id,
        ]);
        PaymentApplication::create([
            'payment_id' => $payment->id,
            'invoice_id' => $settledInvoice->id,
            'amount' => 100,
            'applied_at' => Carbon::create(2026, 1, 6),
        ]);

        $asOf = Carbon::create(2026, 2, 1);

        $openItems = $this->service()->generate($company->id, 'client', $client->id, $asOf, StatementOptions::MODE_OPEN_ITEMS);
        $numbers = array_column($openItems['items'], 'document_number');
        $this->assertNotContains('INV-SETTLED', $numbers, 'a fully settled invoice must disappear from open_items mode');
        $this->assertContains('INV-OPEN', $numbers);

        $fullActivity = $this->service()->generate($company->id, 'client', $client->id, $asOf, StatementOptions::MODE_FULL_ACTIVITY);
        $fullNumbers = array_column($fullActivity['items'], 'document_number');
        $this->assertContains('INV-SETTLED', $fullNumbers, 'full_activity must still list a settled invoice');
        $this->assertContains('INV-OPEN', $fullNumbers);

        // The settled invoice's own row still reports itself fully settled in full_activity.
        $settledRow = collect($fullActivity['items'])->firstWhere('document_number', 'INV-SETTLED');
        $this->assertEquals(0.0, $settledRow['outstanding']);
    }

    public function test_client_open_items_ageing_buckets_bucket_correctly(): void
    {
        [$company, $branch] = $this->makeCompany();
        $agent = $this->makeAgent($company, $branch);
        $client = Client::factory()->create(['company_id' => $company->id, 'agent_id' => $agent->id]);

        $asOf = Carbon::create(2026, 6, 1);

        // 10 days old -> bucket 0 (0-30). 45 days old -> bucket 1 (31-60). 200 days old -> bucket 4 (120+).
        Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_number' => 'INV-10D', 'amount' => 10, 'invoice_date' => $asOf->copy()->subDays(10)]);
        Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_number' => 'INV-45D', 'amount' => 45, 'invoice_date' => $asOf->copy()->subDays(45)]);
        Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_number' => 'INV-200D', 'amount' => 200, 'invoice_date' => $asOf->copy()->subDays(200)]);

        $result = $this->service()->generate($company->id, 'client', $client->id, $asOf, StatementOptions::MODE_OPEN_ITEMS);

        $ageing = $result['ageing'];
        $this->assertSame('0-30', $ageing[0]['label']);
        $this->assertEquals(10.0, $ageing[0]['total']);
        $this->assertSame('31-60', $ageing[1]['label']);
        $this->assertEquals(45.0, $ageing[1]['total']);
        $this->assertSame('120+', $ageing[4]['label']);
        $this->assertEquals(200.0, $ageing[4]['total']);

        $this->assertEquals(255.0, $result['totals']['open_total']);
    }

    public function test_client_unapplied_payment_reduces_net_outstanding_and_is_listed(): void
    {
        [$company, $branch] = $this->makeCompany();
        $agent = $this->makeAgent($company, $branch);
        $client = Client::factory()->create(['company_id' => $company->id, 'agent_id' => $agent->id]);

        Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_number' => 'INV-A', 'amount' => 100, 'invoice_date' => now()]);
        $creator = User::factory()->create();
        Payment::factory()->create([
            'client_id' => $client->id, 'agent_id' => $agent->id, 'company_id' => $company->id, 'amount' => 40,
            'voucher_number' => 'RV-1', 'payment_date' => now(), 'invoice_id' => null, 'account_id' => null,
            'created_by' => $creator->id,
        ]);

        $result = $this->service()->generate($company->id, 'client', $client->id, now()->addDay(), StatementOptions::MODE_OPEN_ITEMS);

        $this->assertEquals(100.0, $result['totals']['open_total']);
        $this->assertEquals(40.0, $result['totals']['unapplied_total']);
        $this->assertEquals(60.0, $result['totals']['net_outstanding']);
        $this->assertCount(1, $result['unapplied']);
        $this->assertSame('RV-1', $result['unapplied'][0]['document_number']);
    }

    // ── Agent (AgentSettlement) ─────────────────────────────────────────────────────────────────

    public function test_agent_settlement_open_items_hides_a_fully_paid_settlement(): void
    {
        [$company, $branch] = $this->makeCompany();
        $agent = $this->makeAgent($company, $branch);
        $creator = User::factory()->create();

        AgentSettlement::create([
            'settlement_number' => 'AS-PAID', 'agent_id' => $agent->id, 'branch_id' => $branch->id, 'company_id' => $company->id,
            'total_amount' => 50, 'paid_amount' => 50, 'remaining_amount' => 0, 'status' => 'paid',
            'settlement_date' => Carbon::create(2026, 1, 1), 'created_by' => $creator->id,
        ]);
        AgentSettlement::create([
            'settlement_number' => 'AS-OPEN', 'agent_id' => $agent->id, 'branch_id' => $branch->id, 'company_id' => $company->id,
            'total_amount' => 70, 'paid_amount' => 20, 'remaining_amount' => 50, 'status' => 'partial',
            'settlement_date' => Carbon::create(2026, 1, 2), 'created_by' => $creator->id,
        ]);

        $asOf = Carbon::create(2026, 2, 1);
        $result = $this->service()->generate($company->id, 'agent', $agent->id, $asOf, StatementOptions::MODE_OPEN_ITEMS);

        $numbers = array_column($result['items'], 'document_number');
        $this->assertNotContains('AS-PAID', $numbers);
        $this->assertContains('AS-OPEN', $numbers);
        $this->assertEquals(50.0, $result['totals']['open_total']);
    }

    // ── Supplier (ledger FIFO projection) ───────────────────────────────────────────────────────

    public function test_supplier_ledger_source_fifo_matches_settlements_against_charges(): void
    {
        [$company, $branch] = $this->makeCompany();
        $supplier = Supplier::factory()->create();
        $payableAccount = app(AccountResolver::class)->resolve('PAYABLE_CONTROL', $company->id);

        $txn1 = $this->makeTransaction($company, $branch, Carbon::create(2026, 1, 1));
        $this->makeLine($txn1, $company, $branch, $payableAccount, debit: 0, credit: 100, date: Carbon::create(2026, 1, 1), typeReferenceId: $supplier->id, voucher: 'CHG-1');

        $txn2 = $this->makeTransaction($company, $branch, Carbon::create(2026, 1, 15));
        $this->makeLine($txn2, $company, $branch, $payableAccount, debit: 100, credit: 0, date: Carbon::create(2026, 1, 15), typeReferenceId: $supplier->id, voucher: 'PV-1');

        $txn3 = $this->makeTransaction($company, $branch, Carbon::create(2026, 2, 1));
        $this->makeLine($txn3, $company, $branch, $payableAccount, debit: 0, credit: 60, date: Carbon::create(2026, 2, 1), typeReferenceId: $supplier->id, voucher: 'CHG-2');

        $asOf = Carbon::create(2026, 3, 1);
        $result = $this->service()->generate($company->id, 'supplier', $supplier->id, $asOf, StatementOptions::MODE_OPEN_ITEMS);

        $numbers = array_column($result['items'], 'document_number');
        $this->assertNotContains('CHG-1', $numbers, 'a fully-paid supplier charge must disappear from open_items mode');
        $this->assertContains('CHG-2', $numbers);
        $this->assertEquals(60.0, $result['totals']['open_total']);

        // Prevent the invariant tearDown from tripping over an intentionally unbalanced fixture
        // pair: each makeLine() call above posts one leg only (deliberately, to exercise the
        // charge/settlement direction split) -- balance the books with an offsetting line on a
        // neutral suspense-style leaf so the whole-company trial balance still ties out.
        $this->balanceFixtureLines($company, $branch, $payableAccount, $txn1, $txn2, $txn3);
    }

    public function test_supplier_prepayment_carries_forward_to_the_next_charge(): void
    {
        [$company, $branch] = $this->makeCompany();
        $supplier = Supplier::factory()->create();
        $payableAccount = app(AccountResolver::class)->resolve('PAYABLE_CONTROL', $company->id);

        // Paid 30 in advance, before any charge exists.
        $txn1 = $this->makeTransaction($company, $branch, Carbon::create(2026, 1, 1));
        $this->makeLine($txn1, $company, $branch, $payableAccount, debit: 30, credit: 0, date: Carbon::create(2026, 1, 1), typeReferenceId: $supplier->id, voucher: 'PV-PRE');

        $asOf1 = Carbon::create(2026, 1, 10);
        $before = $this->service()->generate($company->id, 'supplier', $supplier->id, $asOf1, StatementOptions::MODE_OPEN_ITEMS);
        $this->assertCount(1, $before['unapplied'], 'the prepayment sits unapplied until a charge arrives');
        $this->assertEquals(30.0, $before['unapplied'][0]['amount']);

        $txn2 = $this->makeTransaction($company, $branch, Carbon::create(2026, 1, 20));
        $this->makeLine($txn2, $company, $branch, $payableAccount, debit: 0, credit: 50, date: Carbon::create(2026, 1, 20), typeReferenceId: $supplier->id, voucher: 'CHG-AFTER-PRE');

        $asOf2 = Carbon::create(2026, 2, 1);
        $after = $this->service()->generate($company->id, 'supplier', $supplier->id, $asOf2, StatementOptions::MODE_OPEN_ITEMS);
        $this->assertCount(0, $after['unapplied'], 'the prepayment is consumed once the charge arrives');
        $this->assertEquals(20.0, $after['totals']['open_total'], '50 charge minus the 30 prepayment already on file');

        $this->balanceFixtureLines($company, $branch, $payableAccount, $txn1, $txn2);
    }

    /**
     * P2.5.H re-verify fix (prior verdict FAIL/MAJOR): the brief's own acceptance wording asks for
     * values that "reconcile against the ledger", and the supplier source is the one genuinely new
     * read-time projection this wave builds (see config('accounting.statements')'s own
     * INTERIM-DESIGN RATIFICATION note). P5.3's `journal_entries.settled_amount` writer does not
     * exist to reconcile against directly, so this test reconciles the FIFO projection's OWN
     * output against an INDEPENDENTLY computed net of the SAME posted `journal_entries` rows
     * (`SUM(credit) - SUM(debit)`, no FIFO matching at all) -- an invariant that must hold no
     * matter how many charge/settlement lines are matched in what order, because
     * `net_outstanding = open_total - unapplied_total` is algebraically just
     * `SUM(charge amounts) - SUM(settlement amounts)` redistributed between "still owed on an open
     * charge" and "sitting as a prepayment pool". A FIFO bug that mis-matched lines could still
     * make individual line placement wrong while accidentally preserving this total -- this test
     * does not replace the line-level FIFO tests above, it adds the ledger-reconciliation
     * assertion those two do not make.
     */
    public function test_supplier_projection_net_outstanding_reconciles_against_the_ledgers_own_credit_minus_debit(): void
    {
        [$company, $branch] = $this->makeCompany();
        $supplier = Supplier::factory()->create();
        $payableAccount = app(AccountResolver::class)->resolve('PAYABLE_CONTROL', $company->id);

        $txns = [];
        $txns[] = $t1 = $this->makeTransaction($company, $branch, Carbon::create(2026, 1, 1));
        $this->makeLine($t1, $company, $branch, $payableAccount, debit: 0, credit: 130, date: Carbon::create(2026, 1, 1), typeReferenceId: $supplier->id, voucher: 'CHG-R1');
        $txns[] = $t2 = $this->makeTransaction($company, $branch, Carbon::create(2026, 1, 10));
        $this->makeLine($t2, $company, $branch, $payableAccount, debit: 40, credit: 0, date: Carbon::create(2026, 1, 10), typeReferenceId: $supplier->id, voucher: 'PV-R1');
        $txns[] = $t3 = $this->makeTransaction($company, $branch, Carbon::create(2026, 1, 20));
        $this->makeLine($t3, $company, $branch, $payableAccount, debit: 0, credit: 25, date: Carbon::create(2026, 1, 20), typeReferenceId: $supplier->id, voucher: 'CHG-R2');
        $txns[] = $t4 = $this->makeTransaction($company, $branch, Carbon::create(2026, 1, 25));
        $this->makeLine($t4, $company, $branch, $payableAccount, debit: 200, credit: 0, date: Carbon::create(2026, 1, 25), typeReferenceId: $supplier->id, voucher: 'PV-R2');

        $asOf = Carbon::create(2026, 2, 1);

        $independentNet = (float) JournalEntry::query()
            ->where('account_id', $payableAccount->id)
            ->where('type_reference_id', $supplier->id)
            ->where('posting_date', '<=', $asOf->copy()->endOfDay())
            ->selectRaw('SUM(credit) - SUM(debit) as net')
            ->value('net');

        $result = $this->service()->generate($company->id, 'supplier', $supplier->id, $asOf, StatementOptions::MODE_OPEN_ITEMS);

        // 130 + 25 charges - 40 - 200 settlements = -85: net owed is negative, i.e. the supplier
        // has been overpaid by 85 -- that 85 must show up entirely as the unapplied prepayment
        // pool, with zero still-open charges, and net_outstanding must equal the independent net
        // exactly (never a hand-rolled approximation that happens to differ).
        $this->assertEqualsWithDelta(-85.0, $independentNet, 0.001);
        $this->assertEqualsWithDelta($independentNet, $result['totals']['net_outstanding'], 0.001);
        $this->assertEqualsWithDelta(0.0, $result['totals']['open_total'], 0.001, 'fully consumed by the overpayment, no charge left open');
        $this->assertEqualsWithDelta(85.0, $result['totals']['unapplied_total'], 0.001);

        $this->balanceFixtureLines($company, $branch, $payableAccount, ...$txns);
    }

    private function makeTransaction(Company $company, Branch $branch, Carbon $date): Transaction
    {
        return Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id,
            'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'JV', 'amount' => 1, 'description' => 'Statement fixture',
            'reference_type' => 'Payment', 'reference_number' => 'STMT-'.substr(uniqid(), -8),
            'name' => 'Statement fixture', 'transaction_date' => $date, 'posting_date' => $date,
            'doc_type' => 'JV', 'doc_year' => (int) $date->format('Y'), 'posting_status' => 'posted',
            'total_debit' => 1, 'total_credit' => 1, 'idempotency_key' => uniqid('key:'),
        ]);
    }

    private function makeLine(
        Transaction $txn,
        Company $company,
        Branch $branch,
        \App\Models\Account $account,
        float $debit,
        float $credit,
        Carbon $date,
        int $typeReferenceId,
        string $voucher,
    ): JournalEntry {
        return JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $account->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'Statement fixture line', 'debit' => $debit, 'credit' => $credit,
            'name' => $account->name, 'type' => 'supplier', 'currency' => 'KWD', 'exchange_rate' => 1,
            'amount' => max($debit, $credit), 'voucher_number' => $voucher,
            'type_reference_id' => $typeReferenceId,
        ]);
    }

    /**
     * Every fixture line above deliberately posts only ONE leg of a real double-entry pair (to
     * isolate the charge/settlement direction split under test) -- offsetting each one onto a
     * neutral suspense leaf here keeps the whole-company trial balance tying out for this test
     * class's own tearDown() invariant, without polluting the assertions above with a second
     * account to filter out.
     */
    private function balanceFixtureLines(Company $company, Branch $branch, \App\Models\Account $payableAccount, Transaction ...$txns): void
    {
        $suspense = \App\Models\Account::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'name' => 'Statement Test Suspense']);

        foreach ($txns as $txn) {
            $lines = JournalEntry::where('transaction_id', $txn->id)->get();
            foreach ($lines as $line) {
                JournalEntry::create([
                    'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
                    'account_id' => $suspense->id, 'transaction_date' => $line->transaction_date, 'posting_date' => $line->posting_date,
                    'description' => 'Offsetting leg', 'debit' => $line->credit, 'credit' => $line->debit,
                    'name' => $suspense->name, 'type' => 'suspense', 'currency' => 'KWD', 'exchange_rate' => 1,
                    'amount' => max($line->debit, $line->credit),
                ]);
            }
        }
    }
}
