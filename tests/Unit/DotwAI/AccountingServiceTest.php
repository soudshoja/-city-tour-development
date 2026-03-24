<?php

declare(strict_types=1);

namespace Tests\Unit\DotwAI;

use App\Enums\InvoiceStatus;
use App\Models\Account;
use App\Models\Agent;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyDotwCredential;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\JournalEntry;
use App\Modules\DotwAI\DTOs\DotwAIContext;
use App\Modules\DotwAI\Models\DotwAIBooking;
use App\Modules\DotwAI\Services\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * Unit tests for AccountingService cancellation entry creation.
 *
 * Verifies that Invoice + InvoiceDetail + two JournalEntry records are created
 * for cancellation penalties, and that all records carry explicit company_id
 * (not derived from auth) to work in queue/API contexts.
 *
 * @covers \App\Modules\DotwAI\Services\AccountingService
 * @see ACCT-01 Cancellation with penalty creates Invoice + JournalEntry
 * @see ACCT-03 All accounting records include company_id
 * @see ACCT-04 All JournalEntry/Account queries bypass global scopes
 */
class AccountingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $skipPermissionSeeder = true;

    private AccountingService $accountingService;
    private Company $company;
    private Agent $agent;
    private Client $client;
    private CompanyDotwCredential $credentials;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountingService = new AccountingService();

        $this->company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $this->company->id]);
        $this->agent = Agent::factory()->create(['branch_id' => $branch->id]);
        $this->client = Client::factory()->create(['agent_id' => $this->agent->id]);
        $this->agent->clients()->attach($this->client->id);

        $this->credentials = CompanyDotwCredential::create([
            'company_id'        => $this->company->id,
            'dotw_username'     => Crypt::encrypt('testuser'),
            'dotw_password'     => Crypt::encrypt('testpass'),
            'dotw_company_code' => 'TEST',
            'markup_percent'    => 0,
            'is_active'         => true,
            'b2b_enabled'       => true,
            'b2c_enabled'       => false,
        ]);

        // Seed Chart of Accounts for the test company
        Account::create([
            'company_id' => $this->company->id,
            'name'       => 'Client Receivable Account',
            'code'       => 'RECV-001',
        ]);
        Account::create([
            'company_id' => $this->company->id,
            'name'       => 'Revenue Account',
            'code'       => 'REV-001',
        ]);

        // Seed a credit topup so client ID can be resolved
        Credit::create([
            'company_id'  => $this->company->id,
            'client_id'   => $this->client->id,
            'type'        => Credit::TOPUP,
            'amount'      => 500,
            'description' => 'Test topup',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────

    private function makeBooking(array $overrides = []): DotwAIBooking
    {
        return DotwAIBooking::create(array_merge([
            'prebook_key'        => 'DOTWAI-ACCT-' . uniqid(),
            'booking_ref'        => 'DOTW-ACCT-REF-' . uniqid(),
            'company_id'         => $this->company->id,
            'agent_phone'        => '96599800027',
            'hotel_name'         => 'Accounting Test Hotel',
            'hotel_id'           => 'H001',
            'city_code'          => 'DXB',
            'check_in'           => now()->addDays(30),
            'check_out'          => now()->addDays(35),
            'display_total_fare' => 200.00,
            'display_currency'   => 'KWD',
            'track'              => DotwAIBooking::TRACK_B2B,
            'status'             => DotwAIBooking::STATUS_CANCELLED,
        ], $overrides));
    }

    private function makeContext(): DotwAIContext
    {
        return new DotwAIContext(
            agent:         $this->agent,
            companyId:     $this->company->id,
            credentials:   $this->credentials,
            track:         'b2b',
            markupPercent: 0,
            b2bEnabled:    true,
            b2cEnabled:    false,
        );
    }

    // ──────────────────────────────────────────────────────────────────────
    // Tests
    // ──────────────────────────────────────────────────────────────────────

    /**
     * createCancellationEntries with penalty=75 should create:
     * - 1 Invoice with amount=75 and currency from the booking
     * - 1 InvoiceDetail linked to the invoice
     * - 2 JournalEntry records (debit receivable + credit revenue)
     *
     * @see CANC-03
     * @see ACCT-04
     */
    public function test_creates_invoice_and_journal_for_penalty(): void
    {
        $booking = $this->makeBooking(['display_currency' => 'KWD']);
        $context = $this->makeContext();

        $this->accountingService->createCancellationEntries($booking, 75.00, $context);

        // Invoice check
        $invoice = Invoice::where('label', 'LIKE', '%' . $booking->prebook_key . '%')->first();
        $this->assertNotNull($invoice);
        $this->assertEquals(75.00, (float) $invoice->amount);
        $this->assertEquals('KWD', $invoice->currency);

        // InvoiceDetail check
        $detail = InvoiceDetail::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($detail);

        // Two JournalEntry records
        $entries = JournalEntry::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->where('type', 'cancellation')
            ->get();
        $this->assertCount(2, $entries);
    }

    /**
     * Every JournalEntry created by createCancellationEntries must have
     * explicit company_id matching the context's companyId.
     *
     * This verifies the entries are not relying on Auth::user()->company.
     *
     * @see ACCT-04
     */
    public function test_journal_entry_has_explicit_company_id(): void
    {
        $booking = $this->makeBooking();
        $context = $this->makeContext();

        $this->accountingService->createCancellationEntries($booking, 50.00, $context);

        $entries = JournalEntry::withoutGlobalScopes()
            ->where('type', 'cancellation')
            ->get();

        foreach ($entries as $entry) {
            $this->assertNotNull($entry->company_id);
            $this->assertEquals($context->companyId, $entry->company_id);
        }
    }

    /**
     * For B2B bookings, the invoice should be marked as 'paid' after
     * accounting entries are created, since credit deduction = payment.
     *
     * @see B2B accounting
     */
    public function test_b2b_penalty_invoice_marked_paid_after_credit_deduction(): void
    {
        $booking = $this->makeBooking(['track' => DotwAIBooking::TRACK_B2B]);
        $context = $this->makeContext();

        $this->accountingService->createCancellationEntries($booking, 60.00, $context);

        $invoice = Invoice::where('label', 'LIKE', '%' . $booking->prebook_key . '%')->first();
        $this->assertNotNull($invoice);
        $this->assertEquals(InvoiceStatus::PAID->value, $invoice->status);
    }
}
