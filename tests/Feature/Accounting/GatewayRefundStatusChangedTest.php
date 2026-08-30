<?php

namespace Tests\Feature\Accounting;

use App\Events\Accounting\GatewayRefundStatusChanged;
use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Refund;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\PaymentIdempotencyKey;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Support\AccountingTestCase;

/**
 * KEY: w4r-gateway-refund. W4.R verify-fix (finding #3, HIGH) — GatewayRefundStatusChanged was
 * never registered to its listener anywhere in the codebase (no EventServiceProvider, no explicit
 * Event::listen() call, zero tests). Fixed by wiring it in AccountingServiceProvider::boot(),
 * following the same explicit-registration convention AppServiceProvider already uses for
 * CheckConfirmedOrIssuedTask -> ProcessTaskFinancials. This test proves the wiring actually fires
 * end-to-end (dispatch the real event, assert the real ledger/status side effects), not merely
 * that the classes exist.
 */
class GatewayRefundStatusChangedTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    private function makeRefund(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);

        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => User::factory()->create()->id, 'type_id' => $agentType->id]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $invoice = Invoice::factory()->create(['client_id' => $client->id, 'agent_id' => $agent->id, 'invoice_date' => now()]);

        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $refund = Refund::create([
            'refund_number' => 'REF-GW-'.uniqid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'agent_id' => $agent->id,
            'invoice_id' => $invoice->id,
            'method' => 'Online',
            'status' => Refund::STATUS_POSTED,
            'refund_date' => now(),
            'total_refund_amount' => 0,
            'total_refund_charge' => 0,
            'total_nett_refund' => 50,
            'gateway_refund_id' => 'GWREF-123',
        ]);

        return [$company, $refund];
    }

    /**
     * Registration-level check: Laravel actually knows to call the listener for this event --
     * would have failed on the prior build (nothing registered it anywhere).
     */
    public function test_listener_is_registered_for_the_event(): void
    {
        $this->assertTrue(
            Event::hasListeners(GatewayRefundStatusChanged::class),
            'AccountingServiceProvider must register HandleGatewayRefundStatusChanged for this event.'
        );
    }

    public function test_dispatching_completed_posts_the_gateway_payout_and_completes_the_refund(): void
    {
        [$company, $refund] = $this->makeRefund();
        $this->trackCompanyForInvariants($company->id);

        GatewayRefundStatusChanged::dispatch('myfatoorah', 'GWREF-123', 50.000, $refund->id, GatewayRefundStatusChanged::STATUS_COMPLETED);

        $refund->refresh();
        $this->assertSame(Refund::STATUS_COMPLETED, $refund->status);
        $this->assertSame(Refund::DISPOSITION_REFUND_OUT, $refund->disposition);

        $key = PaymentIdempotencyKey::forGatewayRefund('myfatoorah', 'GWREF-123');
        $posted = Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('idempotency_key', $key)->first();

        $this->assertNotNull($posted, 'The listener must actually post a document -- proves the wiring ran, not just that the classes exist.');
        $this->assertEqualsWithDelta(0.0, (float) $posted->total_debit - (float) $posted->total_credit, 0.0005);

        // Fresh CoaSeeder has no per-gateway split under "Payment Gateway" (1300, Assets) yet, so
        // GATEWAY_CLEARING_MYFATOORAH resolves to that pooled leaf itself (SystemAccountsSeeder::
        // resolveGatewayClearing()'s documented fallback).
        $clearingAccount = Account::where('company_id', $company->id)->where('name', 'Payment Gateway')
            ->whereHas('parent', fn ($q) => $q->where('name', 'Assets'))
            ->first();
        $this->assertNotNull($clearingAccount, 'GATEWAY_CLEARING_MYFATOORAH pool leaf (1300) must exist.');
        $this->assertSame(1, DB::table('journal_entries')->where('transaction_id', $posted->id)->where('account_id', $clearingAccount->id)->count());
    }

    public function test_dispatching_rejected_voids_the_draft_and_posts_nothing(): void
    {
        [$company, $refund] = $this->makeRefund();
        $this->trackCompanyForInvariants($company->id);

        GatewayRefundStatusChanged::dispatch('myfatoorah', 'GWREF-123', 50.000, $refund->id, GatewayRefundStatusChanged::STATUS_REJECTED);

        $refund->refresh();
        $this->assertSame(Refund::STATUS_REJECTED, $refund->status);

        $key = PaymentIdempotencyKey::forGatewayRefund('myfatoorah', 'GWREF-123');
        $this->assertNull(Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('idempotency_key', $key)->first());
    }
}
