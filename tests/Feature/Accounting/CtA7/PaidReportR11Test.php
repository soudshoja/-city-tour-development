<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA7;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LedgerSource;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\SaleDraftBuilder;
use App\Services\Accounting\SaleDraftInput;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * CT-A7-4 — finding **R3-11** (VERIFY-CT-A56-R3 §3.4 / the §5 findings table), verbatim:
 *
 * > "`ReportController::paidaccountsPayableReceivableReport` — the PAID twin of the fixed screen —
 * >  still resolves by hardcoded name AND has no source restriction; not in CT-A6 §5's deferred
 * >  list."
 *
 * The PAID screen and the UNPAID screen are the same report split on `journal_entries.reconciled`.
 * CT-A6-1 fixed one of them and CT-A7-1/CT-A7-3 widened and un-crossed its filters; this file is the
 * other one catching up, and it asserts the three consequences that are measurable rather than the
 * four code changes that are not:
 *
 *   1. **the source restriction** — with no `LedgerSource::restrict()`, the screen summed ENGINE
 *      rows and their MIRRORED LEGACY TWINS together. CT-A5a measured 2,081 dual-posted
 *      transactions on the City Travelers dev ledger, so this screen read roughly DOUBLE the trial
 *      balance one click away. The fixture posts one engine document and one legacy twin of it and
 *      asserts the screen reads the engine figure, and states the doubled figure it must NOT read;
 *   2. **'all' means all** — the old default collapsed to `$allAccounts->first()`, one leaf, with
 *      no way to ask for the rest; and
 *   3. **the party filters** — supplier on the payable half only, keyed on `type_reference_id`
 *      rather than on `journal_entries.name` matched against `Supplier::name` (free text), and a
 *      `client_id` of its own for the receivable half (R3-10b on this screen).
 *
 * The fourth change — the hardcoded `Account::where('name', 'Accounts Payable')` resolution — is
 * proved by DELETION, not by a case here: `ReportController::paidaccountsPayableReceivableReport`
 * is removed from `ArchitectureTest::ALLOW_LISTED_ACCOUNT_NAME_LOOKUP_METHODS` in the same commit,
 * and that ratchet is two-sided — a listed method with no hit fails the build as STALE, and an
 * unlisted method with a hit fails it as a regression. Either direction of the fix coming undone
 * fails `test_no_report_controller_resolves_account_by_hardcoded_name()`.
 */
class PaidReportR11Test extends AccountingTestCase
{
    use GrantsAccountingModule;

    private const FLIGHT_SELL = 150.0;

    private const FLIGHT_COST = 90.0;

    private const HOTEL_SELL = 260.0;

    private const HOTEL_COST = 170.0;

    /** The legacy twin's size — deliberately NOT equal to either engine amount, so a screen that
     * reads it cannot be mistaken for one that merely double-counted. */
    private const LEGACY_TWIN_COST = 41.0;

    private int $companyId;

    private int $branchId;

    private int $flightSupplierId;

    private int $hotelSupplierId;

    private int $flightClientId;

    private int $hotelClientId;

    private int $flightPayableLeafId;

    private int $hotelPayableLeafId;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        $this->companyId = (int) $company->id;
        $this->grantAccountingModule($company);
        CoaSeeder::run($this->companyId);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $this->companyId, 'user_id' => $branchOwner->id]);
        $this->branchId = (int) $branch->id;

        $agentUser = User::factory()->create();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => $agentUser->id,
            'type_id' => $agentType->id,
        ]);

        session(['company_id' => $this->companyId]);
        $this->trackCompanyForInvariants($this->companyId);

        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $this->companyId, '--enable' => true]);
        Artisan::call('accounting:periods:init', ['--company' => $this->companyId]);

        $flightSupplier = Supplier::factory()->create();
        $hotelSupplier = Supplier::factory()->create();
        $this->flightSupplierId = (int) $flightSupplier->id;
        $this->hotelSupplierId = (int) $hotelSupplier->id;

        $flightClient = Client::factory()->create(['agent_id' => $agent->id, 'company_id' => $this->companyId]);
        $hotelClient = Client::factory()->create(['agent_id' => $agent->id, 'company_id' => $this->companyId]);
        $this->flightClientId = (int) $flightClient->id;
        $this->hotelClientId = (int) $hotelClient->id;

        // Two engine sales on two DIFFERENT per-service payable leaves. One leaf would make "'all'
        // means all" unfalsifiable: the old `$allAccounts->first()` default would have shown it.
        $this->postSale($agent, $flightClient, $flightSupplier, 'flight', self::FLIGHT_SELL, self::FLIGHT_COST);
        $this->postSale($agent, $hotelClient, $hotelSupplier, 'hotel', self::HOTEL_SELL, self::HOTEL_COST);

        $resolver = app(AccountResolver::class);
        $this->flightPayableLeafId = (int) $resolver->resolve('SERVICE_PAYABLE', $this->companyId, 'flight')->id;
        $this->hotelPayableLeafId = (int) $resolver->resolve('SERVICE_PAYABLE', $this->companyId, 'hotel')->id;

        $this->assertNotSame(
            $this->flightPayableLeafId,
            $this->hotelPayableLeafId,
            'the two sales must land on two different payable leaves for the "all" case to mean anything'
        );

        // This is the PAID screen: it reads `reconciled != 0`. Settlement is stamped directly
        // rather than driven through a payment voucher because what is under test here is which
        // ROWS the screen reads and how it filters them, not how a row comes to be reconciled —
        // and a voucher would add its own bank/payable legs to every figure below.
        DB::table('journal_entries')->where('company_id', $this->companyId)->update(['reconciled' => 1]);

        $this->postLegacyTwin();
    }

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    private function postSale(Agent $agent, Client $client, Supplier $supplier, string $serviceType, float $sell, float $cost): void
    {
        $task = Task::factory()->create([
            'company_id' => $this->companyId,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => $serviceType,
            'status' => 'issued',
            'total' => $sell,
            'issued_date' => now()->subDays(5),
        ]);

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'invoice_date' => now()->subDays(4),
        ]);
        $detail = InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id]);

        $lines = (new SaleDraftBuilder)->buildLines(new SaleDraftInput(
            serviceType: $serviceType,
            sellAmount: $sell,
            costAmount: $cost,
            postingBasis: SaleDraftInput::BASIS_AGENT,
            clientId: (int) $client->id, clientName: $client->full_name,
            supplierId: (int) $supplier->id, supplierName: $supplier->name,
            agentId: (int) $agent->id, agentName: $agent->name,
            invoiceId: (int) $invoice->id, invoiceDetailId: (int) $detail->id, taskId: (int) $task->id,
        ));

        app(PostingService::class)->post(new DocumentDraft(
            companyId: $this->companyId, branchId: (int) $agent->branch_id, docType: 'INV', subType: 'SALE',
            docDate: now()->subDays(4), narration: 'CT-A7-4 fixture sale ('.$serviceType.')', lines: $lines,
            idempotencyKey: 'ct-a7-4:sale:'.$serviceType.':'.$detail->id, invoiceId: (int) $invoice->id,
        ));
    }

    /**
     * A MIRRORED LEGACY TWIN of the flight payable: a header with `doc_type` NULL and
     * `posting_date` NULL — the exact shape {@see LedgerSource}'s own docblock classifies as
     * legacy — carrying a reconciled credit on the same payable leaf, with the same party.
     * This is the shape CT-A5a counted 2,081 of on the dev ledger.
     */
    private function postLegacyTwin(): void
    {
        $payableLeaf = Account::withoutGlobalScopes()->findOrFail($this->flightPayableLeafId);
        $receivableLeaf = Account::withoutGlobalScopes()->findOrFail(
            app(AccountResolver::class)->resolve('RECEIVABLE_CONTROL', $this->companyId)->id
        );

        $txn = Transaction::forceCreate([
            'company_id' => $this->companyId,
            'branch_id' => $this->branchId,
            'entity_id' => $this->companyId,
            'entity_type' => 'company',
            'transaction_type' => 'INV',
            'amount' => self::LEGACY_TWIN_COST,
            'description' => 'CT-A7-4 legacy twin',
            'reference_type' => 'Invoice',
            'reference_number' => 'CTA74-LEG-'.substr(uniqid(), -8),
            'name' => 'CT-A7-4 legacy twin',
            'transaction_date' => now()->subDays(4),
            'total_debit' => self::LEGACY_TWIN_COST,
            'total_credit' => self::LEGACY_TWIN_COST,
        ]);

        $this->assertNull($txn->fresh()->doc_type, 'the twin must be LEGACY by the LedgerSource discriminator');

        foreach ([
            [$payableLeaf, 0.0, self::LEGACY_TWIN_COST, 'payable', $this->flightSupplierId],
            [$receivableLeaf, self::LEGACY_TWIN_COST, 0.0, 'receivable', $this->flightClientId],
        ] as [$account, $debit, $credit, $type, $party]) {
            DB::table('journal_entries')->insert([
                'transaction_id' => $txn->id,
                'company_id' => $this->companyId,
                'branch_id' => $this->branchId,
                'account_id' => $account->id,
                'transaction_date' => now()->subDays(4),
                'description' => 'CT-A7-4 legacy twin',
                'debit' => $debit,
                'credit' => $credit,
                'name' => $account->name,
                'type' => $type,
                'type_reference_id' => $party,
                'currency' => 'KWD',
                'exchange_rate' => 1,
                'amount' => self::LEGACY_TWIN_COST,
                'voucher_number' => 'CTA74-LEG',
                'reconciled' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function companyUser(): User
    {
        $user = User::factory()->create(['role_id' => Role::COMPANY]);
        Company::where('id', $this->companyId)->update(['user_id' => $user->id]);

        return $user;
    }

    /** @param array<string, mixed> $query */
    private function screen(array $query = []): \Illuminate\Testing\TestResponse
    {
        $response = $this->actingAs($this->companyUser())->get(route('reports.paid-report', $query));

        $response->assertOk();

        return $response;
    }

    private function partyRefsOn(\Illuminate\Testing\TestResponse $response, string $key): array
    {
        return collect($response->viewData($key))
            ->pluck('type_reference_id')->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════

    /**
     * The fixture reproduces the dual-posted shape before anything under test is consulted: both
     * kinds of row sit on the same leaf, and only one of them is the engine's.
     */
    public function test_the_fixture_holds_an_engine_row_and_a_legacy_twin_on_one_leaf(): void
    {
        $ledgerSource = app(LedgerSource::class);
        $this->assertTrue($ledgerSource->engineOn($this->companyId), 'the company must be engine-ON for restrict() to bite');

        $all = (float) DB::table('journal_entries')
            ->where('company_id', $this->companyId)
            ->where('account_id', $this->flightPayableLeafId)
            ->whereNull('deleted_at')
            ->sum('credit');

        $this->assertEqualsWithDelta(
            self::FLIGHT_COST + self::LEGACY_TWIN_COST,
            $all,
            0.0005,
            'unrestricted, the flight payable leaf holds BOTH rows — that sum is what the pre-fix screen showed'
        );
    }

    /**
     * R3-11's headline: the screen reads ENGINE rows only.
     */
    public function test_the_paid_screen_reads_engine_rows_only_and_not_the_legacy_twin(): void
    {
        $response = $this->screen(['account_id' => $this->flightPayableLeafId]);

        $response->assertViewHas(
            'payableBalance',
            fn ($balance) => abs((float) $balance - self::FLIGHT_COST) < 0.0005
        );

        $shown = (float) $response->viewData('payableBalance');

        $this->assertNotEqualsWithDelta(
            self::FLIGHT_COST + self::LEGACY_TWIN_COST,
            $shown,
            0.0005,
            'the pre-fix figure — engine plus its mirrored legacy twin — must not be what the screen shows; '
            .'that is the shape CT-A5a counted 2,081 of on the dev ledger and the reason this screen read '
            .'roughly double the trial balance one click away'
        );
    }

    /**
     * 'all' means all, and it is the default — the same correction CT-A7-1 made on the unpaid twin.
     */
    public function test_the_paid_screen_defaults_to_every_payable_leaf(): void
    {
        $response = $this->screen();

        $response->assertViewHas('accountId', null);
        $response->assertViewHas(
            'payableBalance',
            fn ($balance) => abs((float) $balance - (self::FLIGHT_COST + self::HOTEL_COST)) < 0.0005
        );
        $response->assertViewHas(
            'receivableBalance',
            fn ($balance) => abs((float) $balance - (self::FLIGHT_SELL + self::HOTEL_SELL)) < 0.0005
        );

        $this->assertSame(
            collect([$this->flightPayableLeafId, $this->hotelPayableLeafId])->sort()->values()->all(),
            collect($response->viewData('payableTransactions'))
                ->pluck('account_id')->map(fn ($id) => (int) $id)->unique()->sort()->values()->all(),
            'the default view must span every payable leaf, not collapse to $allAccounts->first()'
        );
    }

    /**
     * The party filters, both directions — `supplier_id` on the payable half, `client_id` on the
     * receivable half, each keyed on `type_reference_id` (R3-10b on this screen).
     */
    public function test_the_supplier_filter_is_payable_only_and_the_client_filter_is_receivable_only(): void
    {
        $bySupplier = $this->screen(['supplier_id' => $this->hotelSupplierId]);

        $bySupplier->assertViewHas(
            'payableBalance',
            fn ($balance) => abs((float) $balance - self::HOTEL_COST) < 0.0005
        );
        $this->assertSame([$this->hotelSupplierId], $this->partyRefsOn($bySupplier, 'payableTransactions'));
        $bySupplier->assertViewHas(
            'receivableBalance',
            fn ($balance) => abs((float) $balance - (self::FLIGHT_SELL + self::HOTEL_SELL)) < 0.0005
        );

        $byClient = $this->screen(['client_id' => $this->flightClientId]);

        $byClient->assertViewHas(
            'receivableBalance',
            fn ($balance) => abs((float) $balance - self::FLIGHT_SELL) < 0.0005
        );
        $this->assertSame([$this->flightClientId], $this->partyRefsOn($byClient, 'receivableTransactions'));
        $byClient->assertViewHas(
            'payableBalance',
            fn ($balance) => abs((float) $balance - (self::FLIGHT_COST + self::HOTEL_COST)) < 0.0005
        );
    }

    /**
     * The party filter keys on the PARTY COLUMN, not on `journal_entries.name`. Proved by renaming
     * the supplier after posting: the old `->where('name', $supplier->name)` lookup would return
     * nothing at all, because the rows still carry the name the supplier had when they were
     * written. This is the same free-text defect CT-A6-1 removed from the unpaid twin.
     */
    public function test_a_supplier_renamed_after_posting_is_still_found_by_the_filter(): void
    {
        Supplier::withoutGlobalScopes()->whereKey($this->hotelSupplierId)->update(['name' => 'Renamed After Posting Ltd']);

        $response = $this->screen(['supplier_id' => $this->hotelSupplierId]);

        $response->assertViewHas(
            'payableBalance',
            fn ($balance) => abs((float) $balance - self::HOTEL_COST) < 0.0005
        );
        $this->assertSame([$this->hotelSupplierId], $this->partyRefsOn($response, 'payableTransactions'));
    }
}
