<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA7;

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
use App\Models\User;
use App\Services\Accounting\DocumentDraft;
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
 * CT-A7-3 — finding **R3-10b** (VERIFY-CT-A56-R3 §3.3), verbatim:
 *
 * > "**The same `supplier_id` filter is applied to the RECEIVABLE query too.**
 * >  `type_reference_id` on a receivable line is a **client** id. Supplier #5 and client #5 are
 * >  different parties sharing an integer, so filtering AR by a supplier id returns that client's
 * >  rows."
 *
 * ── Why an integer collision is the fixture, not a curiosity ────────────────────────────────────
 * `suppliers.id` and `clients.id` are two independent auto-increment sequences. Nothing keeps them
 * apart, and on any real chart they overlap almost completely — City Travelers' dev database has
 * 4-digit ids in both tables. So the pre-fix screen did not merely return "the wrong rows": for
 * every supplier id that is also a live client id it returned THAT CLIENT'S receivables, on a
 * screen headed "Filtered by Supplier", with no indication the two halves were talking about
 * different parties. This fixture therefore mints a client whose id is EXACTLY the supplier's, and
 * a second, unrelated client whose id is not — because "the right rows survive" and "the wrong
 * rows do not appear" are two different failures and only the pair of them pins the behaviour.
 *
 * ── Both directions, as the brief requires ─────────────────────────────────────────────────────
 *   - `supplier_id=S`: the PAYABLE half narrows to supplier S; the RECEIVABLE half is untouched and
 *     still carries every client. Before CT-A7-3 the receivable half collapsed to the colliding
 *     client alone — a silent, unsignalled under-report of AR on exactly this screen.
 *   - `client_id=C`: the RECEIVABLE half narrows to client C; the PAYABLE half is untouched. Before
 *     CT-A7-3 the screen had no `client_id` input at all, so there was no way to ask a receivable
 *     question of it and the AR half could only ever be filtered by the WRONG party's id.
 *
 * Both totals are read off the real controller over HTTP (`payableBalance` / `receivableBalance`,
 * the figures the blade prints), not recomputed here — a test that recomputes the sum it is
 * checking proves only that two copies of one expression agree.
 */
class SupplierFilterOffReceivableR10bTest extends AccountingTestCase
{
    use GrantsAccountingModule;

    /** The colliding pair's sale — client id == supplier id. */
    private const COLLIDING_SELL = 150.0;

    private const COLLIDING_COST = 90.0;

    /** The unrelated pair's sale — neither party shares an id with the pair above. */
    private const OTHER_SELL = 260.0;

    private const OTHER_COST = 170.0;

    private int $companyId;

    private int $collidingId;

    private int $otherClientId;

    private int $otherSupplierId;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        $this->companyId = (int) $company->id;
        $this->grantAccountingModule($company);
        CoaSeeder::run($this->companyId);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $this->companyId, 'user_id' => $branchOwner->id]);

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

        $supplier = Supplier::factory()->create();
        $this->collidingId = (int) $supplier->id;

        // The collision itself. Forced rather than hoped for: both sequences start at 1 in a
        // RefreshDatabase run, but a factory that happens to mint an extra row would silently
        // decouple them and the test would still pass while proving nothing.
        $collidingClient = $this->clientWithId($this->collidingId, (int) $agent->id);

        $otherSupplier = Supplier::factory()->create();
        $this->otherSupplierId = (int) $otherSupplier->id;

        $otherClient = Client::factory()->create([
            'agent_id' => $agent->id,
            'company_id' => $this->companyId,
        ]);
        $this->otherClientId = (int) $otherClient->id;

        $this->assertNotSame(
            $this->collidingId,
            $this->otherClientId,
            'the second client must NOT share the supplier id, or "the wrong rows did not appear" is untestable'
        );
        $this->assertNotSame(
            $this->collidingId,
            $this->otherSupplierId,
            'the second supplier must NOT share the first supplier id either'
        );

        $this->postSale($agent, $collidingClient, $supplier, self::COLLIDING_SELL, self::COLLIDING_COST, 'collide');
        $this->postSale($agent, $otherClient, $otherSupplier, self::OTHER_SELL, self::OTHER_COST, 'other');
    }

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    /**
     * A client whose primary key is $id. `clients.id` is only referenced by rows this fixture
     * creates AFTER this call, so re-keying the freshly-minted row is safe here and nowhere else.
     */
    private function clientWithId(int $id, int $agentId): Client
    {
        $client = Client::factory()->create(['agent_id' => $agentId, 'company_id' => $this->companyId]);

        if ((int) $client->id === $id) {
            return $client;
        }

        $this->assertNull(
            Client::withoutGlobalScopes()->find($id),
            "client #{$id} already exists — this fixture cannot re-key onto an occupied id"
        );

        DB::table('clients')->where('id', $client->id)->update(['id' => $id]);

        return Client::withoutGlobalScopes()->findOrFail($id);
    }

    private function postSale(Agent $agent, Client $client, Supplier $supplier, float $sell, float $cost, string $tag): void
    {
        $task = Task::factory()->create([
            'company_id' => $this->companyId,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
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
            serviceType: 'flight',
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
            docDate: now()->subDays(4), narration: 'CT-A7-3 fixture sale ('.$tag.')', lines: $lines,
            idempotencyKey: 'ct-a7-3:sale:'.$tag.':'.$detail->id, invoiceId: (int) $invoice->id,
        ));
    }

    private function companyUser(): User
    {
        $user = User::factory()->create(['role_id' => Role::COMPANY]);
        Company::where('id', $this->companyId)->update(['user_id' => $user->id]);

        return $user;
    }

    /** @param array<string, mixed> $query */
    private function screen(array $query): \Illuminate\Testing\TestResponse
    {
        $response = $this->actingAs($this->companyUser())
            ->get(route('reports.unpaid-report', $query + ['account_id' => 'all']));

        $response->assertOk();

        return $response;
    }

    private function partyRefsOn(\Illuminate\Testing\TestResponse $response, string $key): array
    {
        $rows = $response->viewData($key);

        return collect($rows)->pluck('type_reference_id')->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════

    /**
     * The fixture reproduces the collision before anything under test is consulted: the AR side
     * carries a line whose party integer is identical to the AP side's supplier, and the two are
     * different parties.
     */
    public function test_the_fixture_puts_a_client_and_a_supplier_on_the_same_integer(): void
    {
        $arPartyIds = DB::table('journal_entries')
            ->where('company_id', $this->companyId)
            ->where('type', 'receivable')
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('type_reference_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertContains($this->collidingId, $arPartyIds, 'the colliding CLIENT must own a receivable line');
        $this->assertContains($this->otherClientId, $arPartyIds, 'the unrelated client must own one too');

        $apPartyIds = DB::table('journal_entries')
            ->where('company_id', $this->companyId)
            ->where('type', 'payable')
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('type_reference_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertContains($this->collidingId, $apPartyIds, 'and the SUPPLIER of the same integer must own a payable line');

        $this->assertNotNull(Supplier::find($this->collidingId));
        $this->assertNotNull(Client::withoutGlobalScopes()->find($this->collidingId));
    }

    /**
     * Direction 1 — `supplier_id` is a PAYABLE-side filter and nothing else.
     *
     * This is the assertion that fails the moment `$receivableQuery->where('type_reference_id',
     * $supplierId)` comes back: the receivable half would collapse to the colliding client's 150.000
     * and the unrelated client's 260.000 would vanish from a screen that never said it was
     * filtering receivables at all.
     */
    public function test_the_supplier_filter_narrows_the_payable_half_and_leaves_the_receivable_half_whole(): void
    {
        $response = $this->screen(['supplier_id' => $this->collidingId]);

        $response->assertViewHas(
            'payableBalance',
            fn ($balance) => abs((float) $balance - self::COLLIDING_COST) < 0.0005
        );
        $this->assertSame(
            [$this->collidingId],
            $this->partyRefsOn($response, 'payableTransactions'),
            'the payable half must contain that supplier and only that supplier'
        );

        $response->assertViewHas(
            'receivableBalance',
            fn ($balance) => abs((float) $balance - (self::COLLIDING_SELL + self::OTHER_SELL)) < 0.0005
        );
        $this->assertSame(
            collect([$this->collidingId, $this->otherClientId])->sort()->values()->all(),
            $this->partyRefsOn($response, 'receivableTransactions'),
            'R3-10b: a SUPPLIER id must not filter the RECEIVABLE half — the unrelated client\'s rows '
            .'must still be there, and the colliding client\'s presence must not be mistaken for the '
            .'filter working'
        );
    }

    /**
     * Direction 2 — `client_id` is a RECEIVABLE-side filter and nothing else.
     *
     * Before CT-A7-3 this input did not exist; the receivable half was unfilterable by its own
     * party, so this whole case is new behaviour and the `receivableBalance` assertion is what
     * pins it.
     */
    public function test_the_client_filter_narrows_the_receivable_half_and_leaves_the_payable_half_whole(): void
    {
        $response = $this->screen(['client_id' => $this->otherClientId]);

        $response->assertViewHas(
            'receivableBalance',
            fn ($balance) => abs((float) $balance - self::OTHER_SELL) < 0.0005
        );
        $this->assertSame(
            [$this->otherClientId],
            $this->partyRefsOn($response, 'receivableTransactions'),
            'the receivable half must contain that client and only that client'
        );

        $response->assertViewHas(
            'payableBalance',
            fn ($balance) => abs((float) $balance - (self::COLLIDING_COST + self::OTHER_COST)) < 0.0005
        );
        $this->assertSame(
            collect([$this->collidingId, $this->otherSupplierId])->sort()->values()->all(),
            $this->partyRefsOn($response, 'payableTransactions'),
            'a CLIENT id must not filter the PAYABLE half — the mirror image of R3-10b, asserted so the '
            .'fix cannot be "corrected" into the same defect on the other side'
        );
    }

    /**
     * Both at once — the two filters are independent, and the screen echoes each back under its own
     * name so the operator can see which half each one narrowed.
     */
    public function test_the_two_filters_are_independent_and_both_are_echoed_back_to_the_view(): void
    {
        $response = $this->screen([
            'supplier_id' => $this->otherSupplierId,
            'client_id' => $this->collidingId,
        ]);

        $response->assertViewHas('supplierId', (string) $this->otherSupplierId);
        $response->assertViewHas('clientId', (string) $this->collidingId);

        $response->assertViewHas(
            'payableBalance',
            fn ($balance) => abs((float) $balance - self::OTHER_COST) < 0.0005
        );
        $response->assertViewHas(
            'receivableBalance',
            fn ($balance) => abs((float) $balance - self::COLLIDING_SELL) < 0.0005
        );
    }
}
