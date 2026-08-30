<?php

namespace Tests\Feature\Accounting;

use App\Exceptions\Accounting\ProformaAmountLockedException;
use App\Models\Agent;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * KEY: invoice-proforma-lock. W3a PROFORMA LOCK (owner decision 2026-08-27 — binding business
 * fact: a proforma invoice in this system is shown to the client as a binding quote, not an
 * internal-only draft). Covers the two halves of the lock that migration 130004 + Invoice::boot()
 * + InvoiceController::markProformaSent() implement:
 *
 *  - Model layer (Invoice::boot()'s `saving` guard): once `proforma_sent_at` was set by a PRIOR
 *    save, any dirty `amount`/`sub_amount`/`currency` column throws
 *    {@see ProformaAmountLockedException}; the very save that first sets the timestamp (and the
 *    amounts it carries at that moment) must pass; non-amount columns stay editable; converting
 *    a sent proforma to an issued invoice (a status flip with the amounts untouched) must carry
 *    the exact same amounts verbatim.
 *  - Controller layer (InvoiceController::markProformaSent(), route invoice.proforma.markSent):
 *    staff-only (auth + InvoicePolicy 'update'), sets the timestamp exactly once — a second call
 *    is a silent idempotent no-op, never a second timestamp.
 *
 * Plain Tests\TestCase (not AccountingTestCase): nothing here posts a journal document, so the
 * per-company trial-balance invariant hook would have nothing to check.
 */
class InvoiceProformaLockTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Company::forgetModuleCache();
        parent::tearDown();
    }

    /**
     * A minimal company → branch → agent → client → invoice chain. markProformaSent() resolves
     * the invoice via whereHas('agent.branch.company'), so the full chain is load-bearing.
     *
     * @return array{0: Company, 1: User, 2: Invoice}
     */
    private function makeInvoiceFixture(float $amount = 500.00, string $currency = 'KWD'): array
    {
        $owner = User::factory()->create(['role_id' => Role::COMPANY]);
        $company = Company::factory()->create(['user_id' => $owner->id]);

        // AgentFactory defaults type_id = 1 and agents.type_id is an FK into agent_type.
        // AgentType::$fillable is ['name'] — an 'id' attribute is silently dropped on create,
        // so never force an id; use the row's real id.
        $agentType = \App\Models\AgentType::firstOrCreate(['name' => 'type-1']);

        $branch = Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => User::factory()->create()->id,
        ]);

        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => User::factory()->create()->id,
            'type_id' => $agentType->id,
        ]);

        $client = Client::factory()->create(['agent_id' => $agent->id]);

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'amount' => $amount,
            'sub_amount' => $amount,
            'currency' => $currency,
            'status' => 'unpaid',
        ]);

        return [$company, $owner, $invoice];
    }

    public function test_first_send_save_sets_timestamp_and_may_carry_amounts(): void
    {
        [, , $invoice] = $this->makeInvoiceFixture();

        // The very save that marks the proforma as sent is the definition of what the client
        // saw — its amounts at that moment must be allowed through (the guard only engages once
        // getOriginal('proforma_sent_at') is ALREADY non-null, i.e. from a later save).
        $invoice->amount = 750.00;
        $invoice->sub_amount = 750.00;
        $invoice->proforma_sent_at = now();
        $invoice->save();

        $invoice->refresh();
        $this->assertNotNull($invoice->proforma_sent_at, 'The send marking itself must persist.');
        $this->assertEquals(750.00, (float) $invoice->amount);
        $this->assertEquals(750.00, (float) $invoice->sub_amount);
    }

    public function test_amount_change_after_send_throws_and_persists_original(): void
    {
        [, , $invoice] = $this->makeInvoiceFixture();
        $invoice->proforma_sent_at = now();
        $invoice->save();
        $invoice->refresh();

        try {
            $invoice->amount = 999.00;
            $invoice->save();
            $this->fail('Expected ProformaAmountLockedException was not thrown.');
        } catch (ProformaAmountLockedException $e) {
            $this->assertSame($invoice->id, $e->invoiceId);
            $this->assertSame('amount', $e->column);
            $this->assertEquals(500.00, (float) $e->originalValue);
            $this->assertEquals(999.00, (float) $e->attemptedValue);
        }

        $this->assertEquals(
            500.00,
            (float) $invoice->fresh()->amount,
            'The rejected write must never reach the database.'
        );
    }

    public function test_sub_amount_change_after_send_throws(): void
    {
        [, , $invoice] = $this->makeInvoiceFixture();
        $invoice->proforma_sent_at = now();
        $invoice->save();
        $invoice->refresh();

        try {
            $invoice->sub_amount = 999.00;
            $invoice->save();
            $this->fail('Expected ProformaAmountLockedException was not thrown.');
        } catch (ProformaAmountLockedException $e) {
            $this->assertSame('sub_amount', $e->column);
        }
    }

    public function test_currency_change_after_send_throws(): void
    {
        [, , $invoice] = $this->makeInvoiceFixture();
        $invoice->proforma_sent_at = now();
        $invoice->save();
        $invoice->refresh();

        try {
            $invoice->currency = 'USD';
            $invoice->save();
            $this->fail('Expected ProformaAmountLockedException was not thrown.');
        } catch (ProformaAmountLockedException $e) {
            $this->assertSame('currency', $e->column);
        }
    }

    public function test_non_amount_columns_remain_editable_after_send(): void
    {
        [, , $invoice] = $this->makeInvoiceFixture();
        $invoice->proforma_sent_at = now();
        $invoice->save();
        $invoice->refresh();

        // The lock is scoped to amount-bearing columns ONLY (PROFORMA_LOCKED_COLUMNS): dates,
        // labels and status — none of which change what the client was quoted — must stay
        // editable, otherwise every post-send bookkeeping touch (due-date nudge, label fix)
        // would need a reverse + re-send, which is not the decision.
        $newDueDate = now()->addDays(60);
        $invoice->due_date = $newDueDate;
        $invoice->label = 'corrected-label-after-send';
        $invoice->save();

        $invoice->refresh();
        $this->assertSame('corrected-label-after-send', $invoice->label);
        $this->assertEquals($newDueDate->toDateString(), \Carbon\Carbon::parse($invoice->due_date)->toDateString());
        $this->assertEquals(500.00, (float) $invoice->amount);
    }

    public function test_issued_conversion_preserves_amounts_verbatim(): void
    {
        [, , $invoice] = $this->makeInvoiceFixture();
        $invoice->proforma_sent_at = now();
        $invoice->save();
        $invoice->refresh();

        // Converting the sent proforma into an issued invoice is a STATUS change carrying the
        // exact same amounts — the locked decision requires this path to succeed with the
        // numbers the client already saw, byte for byte.
        $invoice->status = 'paid';
        $invoice->paid_date = now();
        $invoice->save();

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertEquals(500.00, (float) $invoice->amount, 'Conversion must carry the amount verbatim.');
        $this->assertEquals(500.00, (float) $invoice->sub_amount, 'Conversion must carry the sub-amount verbatim.');
        $this->assertSame('KWD', $invoice->currency, 'Conversion must carry the currency verbatim.');
    }

    public function test_mark_proforma_sent_endpoint_sets_timestamp_and_is_idempotent(): void
    {
        [$company, $owner, $invoice] = $this->makeInvoiceFixture();

        $this->assertNull($invoice->proforma_sent_at);

        $firstResponse = $this->actingAs($owner)->postJson(
            route('invoice.proforma.markSent', [
                'companyId' => $company->id,
                'invoiceNumber' => $invoice->invoice_number,
            ])
        );

        $firstResponse->assertOk()->assertJson(['success' => true]);

        $invoice->refresh();
        $this->assertNotNull($invoice->proforma_sent_at, 'The endpoint must set proforma_sent_at.');
        $firstTimestamp = $invoice->proforma_sent_at->toIso8601String();

        // A second call must be a silent no-op: same timestamp, success payload — never a
        // second "sent" moment, and never an error for the operator who double-clicked.
        $secondResponse = $this->actingAs($owner)->postJson(
            route('invoice.proforma.markSent', [
                'companyId' => $company->id,
                'invoiceNumber' => $invoice->invoice_number,
            ])
        );

        $secondResponse->assertOk()->assertJson(['success' => true]);

        $invoice->refresh();
        $this->assertSame(
            $firstTimestamp,
            $invoice->proforma_sent_at->toIso8601String(),
            'Idempotent: the second call must not move the send timestamp.'
        );
    }

    public function test_mark_proforma_sent_then_amount_edit_is_rejected(): void
    {
        [$company, $owner, $invoice] = $this->makeInvoiceFixture();

        $this->actingAs($owner)->postJson(
            route('invoice.proforma.markSent', [
                'companyId' => $company->id,
                'invoiceNumber' => $invoice->invoice_number,
            ])
        )->assertOk();

        // The HTTP set-path and the model-layer lock must compose: an amount edit attempted
        // after the endpoint marks the proforma as sent hits the same ProformaAmountLockedException.
        $invoice->refresh();
        $invoice->amount = 1.00;

        $this->expectException(ProformaAmountLockedException::class);
        $invoice->save();
    }

    public function test_mark_proforma_sent_requires_authentication(): void
    {
        [$company, , $invoice] = $this->makeInvoiceFixture();

        $this->postJson(
            route('invoice.proforma.markSent', [
                'companyId' => $company->id,
                'invoiceNumber' => $invoice->invoice_number,
            ])
        )->assertUnauthorized();

        $this->assertNull(
            $invoice->fresh()->proforma_sent_at,
            'An unauthenticated request must not set the lock timestamp.'
        );
    }
}
