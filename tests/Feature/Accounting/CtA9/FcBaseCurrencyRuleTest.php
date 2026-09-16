<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA9;

use App\Exceptions\Accounting\FcConsistencyException;
use App\Exceptions\Accounting\NonNegativeAmountException;
use App\Http\Controllers\ReceiptVoucherController;
use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoicePartial;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostingService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * CT-A9 **T1** — what `journal_entries.exchange_rate` is a fact ABOUT (ruling **R-CT10**).
 *
 * ── The defect ─────────────────────────────────────────────────────────────────────────────────
 * `PostingService::post()` step 3f used to refuse a base-currency line on
 * `originalAmount !== amount` **OR** `exchangeRate !== 1.0`. The second half was not a guard; it
 * was a live outage waiting for a config flag. `ReceiptVoucherController::invoiceJournalEntry()`
 * paired KWD amounts (`invoices.amount`, `invoice_partials.amount`) with the SOURCE TASK's
 * `exchange_rate`, so every receipt raised against a task sourced in a foreign currency arrived as
 * a fully self-consistent base-currency line carrying a stray 0.340000 — and the whole receipt
 * refused the moment `accounting.engine.enabled` went true (CT-FX-EXPOSURE-2026-09-16.md §5.4
 * item 1).
 *
 * ── The rule, settled ──────────────────────────────────────────────────────────────────────────
 * From the convention (`LineDraft.php:139-142`, `PostingService.php:855-866`): `debit`/`credit` are
 * LOCAL, `original_amount` is FOREIGN, the rate is base-per-foreign applied by MULTIPLICATION. So
 * on a line whose currency IS the base currency there is no conversion at all: the amounts must
 * agree, and the rate is 1.000000 by definition. Therefore
 *
 *   - amounts disagree on a base line          -> **REFUSE** (unchanged in force, new message);
 *   - rate ≠ 1 on an otherwise consistent base line -> **NORMALISE to 1.000000, WARN, POST**;
 *   - foreign line, rate <= 0 (== absent)      -> **REFUSE**, and say that absent and zero are the
 *     same value in this column;
 *   - foreign line, no foreign amount          -> **REFUSE**, by name;
 *   - foreign line, triple does not reconcile  -> **REFUSE** (untouched — this is the refusal that
 *     found task 15993).
 *
 * The last one is why the fix is NOT "relax the guard". A line tagged 'USD' with
 * `amount === originalAmount` and a rate ≠ 1 is indistinguishable from the genuine defect, and it
 * must keep refusing. The engine takes a currency label at its word; a KWD amount wearing a 'USD'
 * sticker is repaired in the LABEL, by `accounting:repair-currency-label`.
 */
class FcBaseCurrencyRuleTest extends AccountingTestCase
{
    use GrantsAccountingModule;

    private Company $company;

    private int $companyId;

    private int $branchId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->companyId = (int) $this->company->id;
        $this->grantAccountingModule($this->company);
        CoaSeeder::run($this->companyId);

        $branchOwner = User::factory()->create();
        $this->branchId = (int) Branch::factory()->create([
            'company_id' => $this->companyId, 'user_id' => $branchOwner->id,
        ])->id;

        session(['company_id' => $this->companyId]);
        $this->trackCompanyForInvariants($this->companyId);
        (new SystemAccountsSeeder)->run();

        // Every test in this class is about what the ENGINE does with a currency triple, so the
        // engine is on for all of them. tearDown() puts the global flag back.
        config(['accounting.engine.enabled' => true]);
        Artisan::call('accounting:engine', ['company' => $this->companyId, '--enable' => true]);
    }

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    // ── fixture helpers ─────────────────────────────────────────────────────────────────────────

    private function accountByCode(string $code): Account
    {
        return Account::withoutGlobalScopes()
            ->where('company_id', $this->companyId)->where('code', $code)
            ->whereNull('deleted_at')->firstOrFail();
    }

    /**
     * A balanced two-line JV built from explicit account ids, so nothing but step 3f can decide
     * whether it posts. Both legs carry the SAME currency triple — the thing under test.
     */
    private function draft(string $currency, float $amount, float $originalAmount, float $rate, string $key): DocumentDraft
    {
        $debitAccount = $this->accountByCode('1120');   // Cash in hand
        $creditAccount = $this->accountByCode('1201');  // a bank leaf

        return new DocumentDraft(
            companyId: $this->companyId,
            branchId: $this->branchId,
            docType: 'JV',
            subType: 'GENERAL',
            docDate: now(),
            narration: 'CT-A9 T1 probe',
            lines: [
                new LineDraft(
                    purposeCode: '', accountId: (int) $debitAccount->id, side: 'debit', amount: $amount,
                    currency: $currency, originalAmount: $originalAmount, exchangeRate: $rate,
                    transactionType: 'JOURNAL', description: 'probe debit', ledgerType: 'journal',
                ),
                new LineDraft(
                    purposeCode: '', accountId: (int) $creditAccount->id, side: 'credit', amount: $amount,
                    currency: $currency, originalAmount: $originalAmount, exchangeRate: $rate,
                    transactionType: 'JOURNAL', description: 'probe credit', ledgerType: 'journal',
                ),
            ],
            idempotencyKey: $key,
            sourceType: 'Test',
            sourceId: null,
        );
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // THE RULE
    // ════════════════════════════════════════════════════════════════════════════════════════════

    /**
     * ── MUTATION PROOF M-A9-T1-BASE ─────────────────────────────────────────────────────────────
     * This is the exact shape that refused before CT-A9: a base-currency line, amounts agreeing,
     * carrying the source task's rate. Under the old step 3f this test throws
     * `FcConsistencyException` at `post()` and never reaches an assertion.
     *
     * The oracle is deliberately TWO-SIDED, so that merely deleting the refusal would not pass it:
     * the document must post AND the persisted rate must be exactly 1.000000. A build that simply
     * dropped the check would write 0.340000 to `journal_entries.exchange_rate` and fail the second
     * assertion — which is the whole point, because writing the caller's rate through is how 74.3 %
     * of the live ledger came to carry a rate that describes nothing.
     *
     * 1.000000 is asserted as a literal, not as `$line->exchangeRate` or as any constant the
     * production code also reads: the expectation is the convention, not the implementation.
     */
    public function test_a_base_currency_line_with_a_stray_rate_posts_and_persists_rate_one(): void
    {
        $posted = app(PostingService::class)->post(
            $this->draft('KWD', 100.000, 100.000, 0.340000, 'ct-a9:t1:stray-rate')
        );

        $lines = JournalEntry::where('transaction_id', $posted->transaction->id)->get();
        $this->assertCount(2, $lines);

        foreach ($lines as $line) {
            $this->assertSame(
                '1.000000',
                (string) $line->exchange_rate,
                'a base-currency line carries exchange_rate 1.000000 on disk, whatever the feeder supplied'
            );
            $this->assertEqualsWithDelta(100.000, (float) $line->original_amount, 0.0005);
            $this->assertSame('KWD', (string) $line->currency);
        }
    }

    /**
     * Normalising is not the same as swallowing. R-CT10's whole defence is that the smell is still
     * REPORTED — "a feeder handed a base-currency line a rate" is worth seeing on the 'accounting'
     * channel, in the same shape as `TaskIssuancePayableService`'s own
     * `accounting.supplier_payable.fx_inconsistent`. Asserted, because a claim in a docblock that
     * nothing checks is a claim that stops being true on the next refactor.
     */
    public function test_normalising_a_stray_rate_is_warned_not_swallowed(): void
    {
        $captured = [];

        Log::listen(function ($message) use (&$captured) {
            if ($message->message === 'accounting.fx.base_line_rate_normalised') {
                $captured[] = $message->context;
            }
        });

        app(PostingService::class)->post(
            $this->draft('KWD', 100.000, 100.000, 0.340000, 'ct-a9:t1:warns')
        );

        $this->assertCount(2, $captured, 'one warning per line, not one per document');
        $this->assertSame(0.340000, $captured[0]['supplied_exchange_rate']);
        $this->assertSame(1.0, $captured[0]['persisted_exchange_rate']);
        $this->assertSame('R-CT10', $captured[0]['ruling']);
        $this->assertSame($this->companyId, $captured[0]['company_id']);
    }

    /** A base-currency line already at rate 1.0 is normal traffic and must NOT be warned about. */
    public function test_a_well_formed_base_line_is_not_warned_about(): void
    {
        $captured = 0;

        Log::listen(function ($message) use (&$captured) {
            if ($message->message === 'accounting.fx.base_line_rate_normalised') {
                $captured++;
            }
        });

        app(PostingService::class)->post(
            $this->draft('KWD', 100.000, 100.000, 1.0, 'ct-a9:t1:no-warn')
        );

        $this->assertSame(0, $captured, 'the warning must mark the smell, not every base-currency line');
    }

    /** The same normalisation for a rate of ZERO, which is the column's own default. */
    public function test_a_base_currency_line_with_a_zero_rate_posts_and_persists_rate_one(): void
    {
        $posted = app(PostingService::class)->post(
            $this->draft('KWD', 55.500, 55.500, 0.0, 'ct-a9:t1:zero-rate')
        );

        $line = JournalEntry::where('transaction_id', $posted->transaction->id)->firstOrFail();
        $this->assertSame('1.000000', (string) $line->exchange_rate);
    }

    /** Case is not a currency. A line tagged 'kwd' is a base line and gets the same treatment. */
    public function test_a_lowercase_base_currency_tag_is_a_base_line(): void
    {
        $posted = app(PostingService::class)->post(
            $this->draft('kwd', 10.000, 10.000, 7.5, 'ct-a9:t1:lowercase')
        );

        $line = JournalEntry::where('transaction_id', $posted->transaction->id)->firstOrFail();
        $this->assertSame('1.000000', (string) $line->exchange_rate);
    }

    /**
     * The half of the base-currency rule that is still a REFUSAL. A base-currency line whose two
     * amounts disagree is asserting a conversion that cannot exist, and it refuses — with the new
     * message, quoted.
     */
    public function test_a_base_currency_line_whose_amounts_disagree_still_refuses(): void
    {
        $this->expectException(FcConsistencyException::class);
        $this->expectExceptionMessage(
            'base-currency line must have originalAmount (90.000) === amount (100.000); '
            .'there is no conversion on a base-currency line.'
        );

        app(PostingService::class)->post(
            $this->draft('KWD', 100.000, 90.000, 1.0, 'ct-a9:t1:amounts-disagree')
        );
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // THE FOREIGN BRANCH — UNTOUCHED IN FORCE. This is the half an adversary will attack.
    // ════════════════════════════════════════════════════════════════════════════════════════════

    /**
     * ── MUTATION PROOF M-A9-T1-FOREIGN (the counter-proof) ──────────────────────────────────────
     * Task 15993's exact numbers: `amount 368.000, originalAmount 368.000, rate 0.340000, which
     * implies 125.120` (`TaskIssuancePayableService.php:197-202`). This is the shape the whole
     * KWD 1,628.919 defect is made of, and it is ALSO, structurally, "a KWD amount wearing a USD
     * sticker" — the two are indistinguishable from the triple alone.
     *
     * So it must keep refusing. A build that "fixed" T1 by making `originalAmount === amount` stop
     * refusing generally — the obvious wrong reading — passes every test above and fails this one.
     */
    public function test_the_task_15993_shape_on_a_foreign_line_still_refuses(): void
    {
        $this->expectException(FcConsistencyException::class);
        $this->expectExceptionMessage(
            'amount (368.000) is not consistent with originalAmount (368.000) × '
            .'exchangeRate (0.340000) = 125.120'
        );

        app(PostingService::class)->post(
            $this->draft('USD', 368.000, 368.000, 0.340000, 'ct-a9:t1:task-15993-shape')
        );
    }

    /**
     * ABSENT and ZERO are the same value in this column, and both refuse. The message says so by
     * name, because the schema cannot.
     */
    public function test_a_foreign_line_with_no_rate_refuses_and_names_the_absent_zero_ambiguity(): void
    {
        $this->expectException(FcConsistencyException::class);
        $this->expectExceptionMessage(
            'A rate of 0.000000 is the column\'s own default, so "no rate supplied" and "rate zero" '
            .'are the same value here and both refuse; supply the rate this amount was converted at.'
        );

        app(PostingService::class)->post(
            $this->draft('USD', 31.000, 100.000, 0.0, 'ct-a9:t1:foreign-no-rate')
        );
    }

    /**
     * A line that claims a currency but carries no foreign amount IS already refused — by step 3b,
     * which is earlier and better named than anything step 3f could add. CT-A9 considered adding a
     * dedicated FC branch here, found it would be unreachable, and did not add it; this test is what
     * makes that finding falsifiable rather than a comment. If step 3b ever stops covering the case,
     * this test fails and the branch is owed.
     */
    public function test_a_foreign_line_with_no_foreign_amount_is_already_refused_by_step_3b(): void
    {
        $this->expectException(NonNegativeAmountException::class);
        $this->expectExceptionMessage(
            'DocumentDraft::$lines[0] originalAmount must be > 0 (got 0.000000) — a zero-amount '
            .'line has no real double-entry meaning.'
        );

        app(PostingService::class)->post(
            $this->draft('USD', 31.000, 0.0, 0.310000, 'ct-a9:t1:foreign-no-amount')
        );
    }

    /** A genuinely foreign, genuinely consistent line is unaffected and keeps its real rate. */
    public function test_a_consistent_foreign_line_posts_and_keeps_its_own_rate(): void
    {
        // 1,239.000 EGP x 0.006301 = 7.807 — CT-FX-EXPOSURE §1.3's own engine-posted row, used
        // here because it is a real, independently-recorded instance of the identity rather than a
        // number invented for a test.
        $posted = app(PostingService::class)->post(
            $this->draft('EGP', 7.807, 1239.000, 0.006301, 'ct-a9:t1:real-egp')
        );

        $line = JournalEntry::where('transaction_id', $posted->transaction->id)->firstOrFail();
        $this->assertSame('0.006301', (string) $line->exchange_rate, 'a FOREIGN line keeps the rate it was given');
        $this->assertSame('EGP', (string) $line->original_currency);
        $this->assertEqualsWithDelta(1239.000, (float) $line->original_amount, 0.0005);
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // THE FEEDER — ReceiptVoucherController, end to end, engine ON
    // ════════════════════════════════════════════════════════════════════════════════════════════

    /**
     * ── MUTATION PROOF M-A9-T1-RV ───────────────────────────────────────────────────────────────
     * The live outage, reproduced through the controller that caused it. The task carries
     * `exchange_rate = 0.340000` and a foreign `original_currency`, exactly as the 2,350
     * foreign-sourced tasks on the real chart do; the invoice and the partial are KWD.
     *
     * Before CT-A9 this call returns `['status' => 'error']` (or throws) because
     * `invoiceJournalEntry()` handed step 3f a base-currency line carrying 0.340000, and the whole
     * import refused. After it, the receipt posts and its lines are honestly base-currency.
     *
     * Note what the assertions pin: NOT merely that it posted, but that the lines carry the base
     * currency and rate 1.000000 — so a "fix" that made the controller pass the task's rate through
     * to a foreign-tagged line (the other tempting wrong answer, which would make the document post
     * and quietly book a KWD receivable as USD) fails here.
     */
    public function test_the_receipt_import_posts_for_a_foreign_sourced_task_with_the_engine_on(): void
    {
        $agentType = AgentType::firstOrCreate(['id' => 1], ['name' => 'type-1']);
        $agentUser = User::factory()->create();
        $agent = Agent::factory()->create([
            'branch_id' => $this->branchId, 'user_id' => $agentUser->id, 'type_id' => $agentType->id,
        ]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);
        User::factory()->create(['role_id' => Role::ADMIN]);

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id, 'agent_id' => $agent->id,
            'amount' => 200.000, 'status' => 'unpaid', 'invoice_date' => now(),
        ]);

        // THE DEFECTIVE SHAPE: a task priced abroad, converted into KWD, carrying its own rate.
        $task = Task::factory()->create([
            'company_id' => $this->companyId,
            'supplier_id' => Supplier::factory()->create()->id,
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'type' => 'flight',
            'total' => 100.000,
            'exchange_currency' => 'KWD',
            'original_currency' => 'USD',
            'exchange_rate' => 0.340000,
        ]);

        $invoiceDetail = InvoiceDetail::factory()->create([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'task_id' => $task->id,
            'task_price' => 200.000,
        ]);

        InvoicePartial::create([
            'invoice_id' => $invoice->id, 'invoice_number' => $invoice->invoice_number,
            'client_id' => $client->id, 'service_charge' => 0, 'amount' => 200.000,
            'status' => 'paid', 'type' => 'cash', 'payment_gateway' => 'Cash',
        ]);

        $transaction = Transaction::forceCreate([
            'company_id' => $this->companyId, 'branch_id' => $this->branchId,
            'entity_id' => $this->companyId, 'entity_type' => 'company',
            'transaction_type' => 'RV', 'amount' => 200.000, 'description' => 'imported receipt',
            'reference_type' => 'Receipt', 'reference_number' => 'CTA9-IMP-1',
            'name' => 'imported receipt', 'transaction_date' => now(),
            'total_debit' => 200.000, 'total_credit' => 200.000,
        ]);

        $result = app(ReceiptVoucherController::class)->invoiceJournalEntry($transaction, $invoice);

        $this->assertSame(
            'success',
            $result['status'] ?? null,
            'the receipt import must not refuse a foreign-sourced task once the engine is on: '.json_encode($result)
        );

        $sale = Transaction::withoutGlobalScopes()
            ->where('idempotency_key', 'invoice-detail:'.$invoiceDetail->id.':sale')
            ->firstOrFail();

        $lines = JournalEntry::where('transaction_id', $sale->id)->get();
        $this->assertCount(2, $lines);

        foreach ($lines as $line) {
            $this->assertSame('KWD', (string) $line->currency, 'a KWD receivable is booked as KWD, not as the task\'s source currency');
            $this->assertSame('1.000000', (string) $line->exchange_rate);
        }
    }

    /**
     * A source ratchet on the feeder itself. The engine no longer refuses the bad shape, so a
     * behavioural test alone can no longer tell whether the controller stopped BUILDING it — and
     * `tasks` has no `currency` column at all, so a read of `$task->currency` is a phantom read
     * whichever way it falls back.
     *
     * Scoped to `invoiceJournalEntry()`'s own body, because the same file legitimately discusses
     * both names in prose elsewhere.
     */
    public function test_the_receipt_import_no_longer_reads_a_task_rate_or_a_phantom_task_currency(): void
    {
        $body = $this->importSaleBody();

        $this->assertStringNotContainsString('$task->currency', $body);
        $this->assertStringNotContainsString('$task->exchange_rate', $body);
        $this->assertStringContainsString("config('accounting.engine.base_currency'", $body);
    }

    /**
     * MUTATION PROOF for the ratchet above — it must actually bite. A scanner that silently stopped
     * matching (because the anchors moved, or the comment stripper ate the body) would read as a
     * clean codebase, which is the failure mode every source ratchet has.
     */
    public function test_the_feeder_ratchet_bites_a_synthetic_violation(): void
    {
        $body = $this->importSaleBody();

        $mutated = str_replace('$exchangeRate = 1.0;', '$exchangeRate = (float) ($task->exchange_rate ?? 1.0);', $body);

        $this->assertNotSame($body, $mutated, 'the anchor this ratchet depends on has moved — re-derive it');
        $this->assertStringContainsString('$task->exchange_rate', $mutated, 'the scan finds the forbidden read once it is present');
    }

    /**
     * The code (comments stripped) of `invoiceJournalEntry()`'s uninvoiced-sale block, from the
     * currency resolution down to the sale draft's own seam call.
     */
    private function importSaleBody(): string
    {
        $source = file_get_contents(app_path('Http/Controllers/ReceiptVoucherController.php'));
        $this->assertIsString($source);

        $start = strpos($source, '$currency = ');
        $end = strpos($source, "'receipt-voucher.import'");
        $this->assertNotFalse($start, 'CT-A9 anchor missing: the currency resolution in invoiceJournalEntry()');
        $this->assertNotFalse($end, 'CT-A9 anchor missing: the receipt-voucher.import seam call');
        $this->assertLessThan($end, $start);

        $slice = substr($source, $start, $end - $start);

        // Strip block and line comments so the block's own prose ABOUT the old reads (which quotes
        // them verbatim, deliberately) cannot fire the scan.
        $slice = preg_replace('#/\*.*?\*/#s', '', $slice);

        return (string) preg_replace('#//[^\n]*#', '', (string) $slice);
    }

    /** The trial balance is untouched by any of this — asserted, not assumed. */
    public function test_nothing_in_this_rule_unbalances_a_document(): void
    {
        app(PostingService::class)->post($this->draft('KWD', 12.345, 12.345, 0.5, 'ct-a9:t1:balance'));

        $sums = DB::table('journal_entries')
            ->where('company_id', $this->companyId)
            ->whereNull('deleted_at')
            ->selectRaw('SUM(debit) AS d, SUM(credit) AS c')
            ->first();

        $this->assertEqualsWithDelta((float) $sums->d, (float) $sums->c, 0.0005);
    }

    /** The AccountResolver is untouched by this lane; asserted so a broken fixture is visible. */
    public function test_fixture_precondition_purposes_resolve(): void
    {
        $this->assertNotNull(app(AccountResolver::class)->resolve('RECEIVABLE_CONTROL', $this->companyId));
    }
}
