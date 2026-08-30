<?php

namespace Tests\Feature\Accounting;

use App\Http\Controllers\InvoiceController;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Services\Accounting\CreditApplicationInput;
use App\Services\Accounting\PaymentIdempotencyKey;
use App\Services\PaymentApplicationService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use ReflectionMethod;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\Support\AccountingTestCase;

/**
 * W2c (orchestrator ruling B-2). Cross-feeder proof: {@see InvoiceController::createCreditPaymentCOA()}
 * ('partial'-sourced ids) and {@see PaymentApplicationService::createCreditPaymentCOA()}
 * ('pa'-sourced ids) posting against the SAME invoice with the SAME numeric id must produce TWO
 * documents, never a silent collision.
 *
 * This is the exact defect W2b shipped (lead report §5, B-2): both feeders fed
 * {@see PaymentIdempotencyKey::forCreditApplication()} a bare int from two INDEPENDENT
 * AUTO_INCREMENT sequences (`invoice_partials.id` vs `payment_applications.id`), so
 * `invoice_partials.id = 5` and `payment_applications.id = 5` collapsed onto ONE key and the
 * second feeder's post silently vanished — no exception, no CRITICAL log, and the caller
 * (`applyPaymentsToInvoice()`/`savePartial()`) still committed its own balance mutations as if
 * nothing had gone wrong. The source discriminator on {@see CreditApplicationInput::$idSource}
 * fixes this by namespacing the key on the TABLE, not just the number.
 */
class CreditApplicationCrossFeederIdempotencyTest extends AccountingTestCase
{
    use CreatesTenantFixtures;

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    private function invokePrivate(object $object, string $method, array $args): mixed
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $args);
    }

    public function test_pa_sourced_and_partial_sourced_equal_numeric_ids_post_two_separate_documents(): void
    {
        config(['accounting.engine.enabled' => true]);
        $tenant = $this->createTenant();
        $company = $tenant['company'];
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder())->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        $this->trackCompanyForInvariants($company->id);

        $invoice = Invoice::factory()->create([
            'client_id' => $tenant['client']->id,
            'agent_id' => $tenant['agent']->id,
            'amount' => 70.0,
            'sub_amount' => 70.0,
            'currency' => 'KWD',
        ]);

        $this->actingAs($tenant['user']);

        // Feeder 1: PaymentApplicationService — SOURCE_PAYMENT_APPLICATION, id=5.
        $pasAppliedPayments = [[
            'payment_application_id' => 5,
            'payment_id' => null,
            'refund_id' => null,
            'voucher_number' => 'TOPUP',
            'amount_applied' => 30.0,
        ]];
        $pasResult = $this->invokePrivate(
            new PaymentApplicationService(),
            'createCreditPaymentCOA',
            [$invoice, $pasAppliedPayments, 30.0]
        );
        $this->assertInstanceOf(Transaction::class, $pasResult, 'PaymentApplicationService feeder must post successfully.');

        // Feeder 2: InvoiceController — SOURCE_PARTIAL, id=5 (numerically equal, different table).
        $icAppliedPayments = [[
            'credit_id' => 999,
            'voucher_number' => 'Client Credit',
            'amount_applied' => 40.0,
            'invoice_partial_id' => null,
        ]];
        $icResult = $this->invokePrivate(
            app(InvoiceController::class),
            'createCreditPaymentCOA',
            [$invoice, $icAppliedPayments, 40.0, 5]
        );
        $this->assertInstanceOf(Transaction::class, $icResult, 'InvoiceController feeder must ALSO post successfully — this is the collision B-2 fixed.');

        $this->assertNotSame($pasResult->id, $icResult->id, 'two genuinely different business events must post as two documents, not collapse onto one.');
        $this->assertNotSame($pasResult->idempotency_key, $icResult->idempotency_key);

        $expectedPaKey = PaymentIdempotencyKey::forCreditApplication($invoice->id, [[CreditApplicationInput::SOURCE_PAYMENT_APPLICATION, 5]]);
        $expectedPartialKey = PaymentIdempotencyKey::forCreditApplication($invoice->id, [[CreditApplicationInput::SOURCE_PARTIAL, 5]]);
        $this->assertSame($expectedPaKey, $pasResult->idempotency_key);
        $this->assertSame($expectedPartialKey, $icResult->idempotency_key);
        $this->assertStringContainsString(':pa:5', $pasResult->idempotency_key);
        $this->assertStringContainsString(':partial:5', $icResult->idempotency_key);

        $this->assertSame(
            2,
            Transaction::where('invoice_id', $invoice->id)->where('reference_type', 'Payment')->count(),
            'both documents must exist — no silent drop.'
        );
        $this->assertEqualsWithDelta(
            30.0,
            (float) JournalEntry::where('transaction_id', $pasResult->id)->where('credit', '>', 0)->value('credit'),
            0.001
        );
        $this->assertEqualsWithDelta(
            40.0,
            (float) JournalEntry::where('transaction_id', $icResult->id)->where('credit', '>', 0)->value('credit'),
            0.001
        );
    }
}
