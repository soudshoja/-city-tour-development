<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\Reconciliation;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Supplier;
use App\Models\SupplierStatementImport;
use App\Models\SupplierStatementImportLine;
use App\Models\Transaction;
use App\Modules\DotwAI\Models\DotwAIBooking;
use App\Services\Accounting\Reconciliation\SupplierStatementMatcher;
use Database\Seeders\CoaSeeder;
use Illuminate\Support\Carbon;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * accounting-builds T8 (Lane E): SupplierStatementExceptionsTest — open ledger lines missing
 * from the statement (PLAN.md §5 Lane E test list), plus the exceptions report's shape combining
 * all four states (matched / disputed / unmatched-statement / unmatched-ledger), and the
 * `Accounting\ReconciliationController::supplierStatementExceptions` HTTP endpoint the spec
 * explicitly allows exercising the UI through.
 */
class SupplierStatementExceptionsTest extends AccountingTestCase
{
    use GrantsAccountingModule;

    private function matcher(): SupplierStatementMatcher
    {
        return app(SupplierStatementMatcher::class);
    }

    /** @return array{0: Company, 1: Branch, 2: \App\Models\User} */
    private function makeCompany(): array
    {
        $company = Company::factory()->create();
        $this->grantAccountingModule($company);
        CoaSeeder::run($company->id);
        $owner = \App\Models\User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $owner->id]);
        $this->trackCompanyForInvariants($company->id);

        return [$company, $branch, $owner];
    }

    private function anyLeaf(int $companyId, int $skip = 0): Account
    {
        return Account::withoutGlobalScopes()->where('company_id', $companyId)->orderBy('id')->skip($skip)->take(1)->firstOrFail();
    }

    private ?Agent $sharedAgent = null;

    private function makeInvoice(Branch $branch): Invoice
    {
        if ($this->sharedAgent === null) {
            $type = AgentType::factory()->create();
            $this->sharedAgent = Agent::factory()->create([
                'user_id' => (int) $branch->user_id,
                'branch_id' => $branch->id,
                'type_id' => $type->id,
            ]);
        }

        return Invoice::factory()->create([
            'client_id' => Client::factory()->create()->id,
            'agent_id' => $this->sharedAgent->id,
        ]);
    }

    private function postCharge(Company $company, Branch $branch, Account $debit, Account $credit, int $partyId, int $invoiceId, float $amount, Carbon $date): JournalEntry
    {
        $txn = Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id,
            'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'INV', 'amount' => $amount, 'description' => 'Test DOTW charge',
            'reference_type' => 'Invoice', 'reference_number' => 'SSE-'.substr(uniqid(), -8),
            'name' => 'Test', 'transaction_date' => $date, 'posting_date' => $date,
            'doc_type' => 'INV', 'doc_year' => (int) $date->format('Y'), 'posting_status' => 'posted',
            'total_debit' => $amount, 'total_credit' => $amount, 'idempotency_key' => uniqid('key:'),
        ]);

        JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $debit->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'cost', 'debit' => $amount, 'credit' => 0, 'name' => $debit->name,
            'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => $amount,
            'voucher_number' => 'SSE', 'invoice_id' => $invoiceId,
        ]);

        return JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $credit->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'payable', 'debit' => 0, 'credit' => $amount, 'name' => $credit->name,
            'type' => 'test', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => $amount,
            'voucher_number' => 'SSE', 'type_reference_id' => $partyId, 'invoice_id' => $invoiceId,
        ]);
    }

    public function test_exceptions_report_lists_all_four_states(): void
    {
        [$company, $branch] = $this->makeCompany();
        $supplier = Supplier::factory()->create(['name' => 'DOTW', 'has_hotel' => true]);
        $debit = $this->anyLeaf($company->id, 0);
        $credit = $this->anyLeaf($company->id, 1);

        // matched
        $invA = $this->makeInvoice($branch);
        $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invA->id, 100.000, Carbon::create(2026, 8, 5));
        DotwAIBooking::create([
            'prebook_key' => 'DOTWAI-'.uniqid(), 'company_id' => $company->id, 'agent_phone' => 'x',
            'hotel_id' => 'H', 'hotel_name' => 'H', 'check_in' => '2026-08-01', 'check_out' => '2026-08-03',
            'original_total_fare' => 100, 'original_currency' => 'USD', 'display_total_fare' => 100, 'display_currency' => 'USD',
            'booking_ref' => 'DTW-A', 'invoice_id' => $invA->id,
        ]);

        // disputed
        $invB = $this->makeInvoice($branch);
        $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invB->id, 50.000, Carbon::create(2026, 8, 6));
        DotwAIBooking::create([
            'prebook_key' => 'DOTWAI-'.uniqid(), 'company_id' => $company->id, 'agent_phone' => 'x',
            'hotel_id' => 'H', 'hotel_name' => 'H', 'check_in' => '2026-08-01', 'check_out' => '2026-08-03',
            'original_total_fare' => 50, 'original_currency' => 'USD', 'display_total_fare' => 50, 'display_currency' => 'USD',
            'booking_ref' => 'DTW-B', 'invoice_id' => $invB->id,
        ]);

        // unmatched-ledger: a posted payable line with NO statement line at all.
        $invC = $this->makeInvoice($branch);
        $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invC->id, 30.000, Carbon::create(2026, 8, 7));

        $import = SupplierStatementImport::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'file_name' => 'mixed.csv',
            'statement_currency' => 'KWD', 'period_from' => '2026-08-01', 'period_to' => '2026-08-31',
            'content_hash' => hash('sha256', uniqid('', true)), 'column_map' => [], 'status' => SupplierStatementImport::STATUS_STAGED,
        ]);
        SupplierStatementImportLine::create(['import_id' => $import->id, 'row_no' => 1, 'booking_ref' => 'DTW-A', 'amount' => 100.000, 'currency' => 'KWD', 'state' => 'unmatched']);
        SupplierStatementImportLine::create(['import_id' => $import->id, 'row_no' => 2, 'booking_ref' => 'DTW-B', 'amount' => 45.000, 'currency' => 'KWD', 'state' => 'unmatched']);
        // unmatched-statement: no booking resolves for this reference at all.
        SupplierStatementImportLine::create(['import_id' => $import->id, 'row_no' => 3, 'booking_ref' => 'DTW-NOPE', 'amount' => 10.000, 'currency' => 'KWD', 'state' => 'unmatched']);

        $this->matcher()->match($import->fresh());
        $exceptions = $this->matcher()->exceptionsFor($import->fresh());

        $this->assertCount(1, $exceptions['disputed']);
        $this->assertSame('DTW-B', $exceptions['disputed']->first()->booking_ref);

        $this->assertCount(1, $exceptions['unmatched_statement']);
        $this->assertSame('DTW-NOPE', $exceptions['unmatched_statement']->first()->booking_ref);

        $this->assertTrue(
            $exceptions['unmatched_ledger']->pluck('invoice_id')->contains($invC->id),
            'The unbilled invC payable line must appear in unmatched-ledger.'
        );
        $this->assertFalse(
            $exceptions['unmatched_ledger']->pluck('invoice_id')->contains($invA->id),
            'The matched invA payable line must NOT appear in unmatched-ledger.'
        );
    }

    public function test_http_import_match_and_exceptions_round_trip(): void
    {
        [$company, $branch] = $this->makeCompany();
        $admin = \App\Models\User::factory()->create(['role_id' => \App\Models\Role::ADMIN]);
        session(['company_id' => $company->id]);

        $supplier = Supplier::factory()->create(['name' => 'DOTW', 'has_hotel' => true]);
        $debit = $this->anyLeaf($company->id, 0);
        $credit = $this->anyLeaf($company->id, 1);
        $invoice = $this->makeInvoice($branch);
        $this->postCharge($company, $branch, $debit, $credit, $supplier->id, $invoice->id, 25.000, Carbon::create(2026, 8, 9));
        DotwAIBooking::create([
            'prebook_key' => 'DOTWAI-'.uniqid(), 'company_id' => $company->id, 'agent_phone' => 'x',
            'hotel_id' => 'H', 'hotel_name' => 'H', 'check_in' => '2026-08-01', 'check_out' => '2026-08-03',
            'original_total_fare' => 25, 'original_currency' => 'USD', 'display_total_fare' => 25, 'display_currency' => 'USD',
            'booking_ref' => 'DTW-HTTP', 'invoice_id' => $invoice->id,
        ]);

        $csv = "Booking Reference,Amount,Currency\nDTW-HTTP,25.000,KWD\n";
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('http-test.csv', $csv);

        $this->actingAs($admin);

        $importResponse = $this->post(route('accounting.reconciliation.supplier-statements.import'), [
            'file' => $file,
            'supplier_id' => $supplier->id,
            'statement_currency' => 'KWD',
            'column_map' => ['booking_ref' => 'Booking Reference', 'amount' => 'Amount', 'currency' => 'Currency'],
        ]);

        $importResponse->assertStatus(201);
        $importId = $importResponse->json('import.id');
        $this->assertNotNull($importId);

        $matchResponse = $this->post(route('accounting.reconciliation.supplier-statements.match', ['supplierStatementImport' => $importId]));
        $matchResponse->assertOk();
        $matchResponse->assertJsonPath('result.matched', 1);

        $exceptionsResponse = $this->get(route('accounting.reconciliation.supplier-statements.exceptions', ['supplierStatementImport' => $importId]));
        $exceptionsResponse->assertOk();
        $exceptionsResponse->assertJsonCount(1, 'matched');
    }
}
