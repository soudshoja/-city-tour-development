<?php

namespace Tests\Feature\Accounting;

use App\Exceptions\Accounting\UnbalancedDocumentException;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\TaskController;
use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CoaSeeder;
use Tests\Support\AccountingTestCase;

/**
 * CT-TALLY (2026-09-18). The three legacy (engine-OFF) writers that between them put
 * `citycomm_city-tour-test` company 1 off by KWD -721.270 — `SUM(debit) - SUM(credit)` over
 * 110,912 live journal lines, which must be 0.000.
 *
 * Every test here constructs the FAILURE, not the fix: each one reproduces the exact ledger
 * shape the production database was measured in on 2026-09-18, and fails against the
 * pre-fix bodies of the methods under test.
 *
 * The engine is never enabled in this file on purpose. All three defects live strictly on the
 * OFF path — `PostingService::post()` refuses an unbalanced document outright
 * (`UnbalancedDocumentException`, PostingService.php step 4), and the ENGINE slice of that same
 * measured ledger is 36,510 lines across 0 individually unbalanced documents.
 */
class LegacyRestatementBalanceTest extends AccountingTestCase
{
    private TaskController $taskController;

    protected function setUp(): void
    {
        parent::setUp();
        config(['accounting.engine.enabled' => false]);
        $this->taskController = app(TaskController::class);
    }

    private function invokePrivate(object $target, string $method, mixed ...$args): mixed
    {
        $ref = new \ReflectionMethod($target::class, $method);
        $ref->setAccessible(true);

        return $ref->invoke($target, ...$args);
    }

    /** @return array{0: Company, 1: Agent, 2: Client, 3: Supplier} */
    private function scaffold(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);

        $branch = Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => User::factory()->create()->id,
        ]);
        $agentType = AgentType::firstOrCreate(['name' => 'ct-tally-test-type']);
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'type_id' => $agentType->id,
            'user_id' => User::factory()->create()->id,
        ]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $supplier = Supplier::factory()->create(['name' => 'CT Tally Test Supplier']);

        return [$company, $agent, $client, $supplier];
    }

    private function accountId(Company $company, int $nth = 0): int
    {
        return Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->orderBy('id')
            ->skip($nth)
            ->take(1)
            ->value('id');
    }

    private function makeTask(Company $company, Agent $agent, Client $client, Supplier $supplier, float $total): Task
    {
        return Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => 'issued',
            'reference' => 'CTTALLY'.substr(uniqid(), -6),
            'price' => $total,
            'total' => $total,
        ]);
    }

    private function makeDocument(Company $company, Task $task, float $headerAmount): Transaction
    {
        return Transaction::create([
            'company_id' => $company->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'transaction_type' => 'credit',
            'amount' => $headerAmount,
            'description' => 'Task created: '.$task->reference,
            'reference_type' => 'Payment',
            'transaction_date' => now(),
        ]);
    }

    private function makeLine(
        Company $company,
        Transaction $transaction,
        int $accountId,
        string $type,
        float $debit,
        float $credit,
        ?Task $task = null,
        string $description = 'ct-tally line',
    ): JournalEntry {
        return JournalEntry::create([
            'transaction_id' => $transaction->id,
            'company_id' => $company->id,
            'account_id' => $accountId,
            'task_id' => $task?->id,
            'transaction_date' => now(),
            'description' => $description,
            'name' => 'CT Tally',
            'debit' => $debit,
            'credit' => $credit,
            'balance' => $debit - $credit,
            'type' => $type,
        ]);
    }

    private function imbalanceOf(Transaction $transaction): float
    {
        return round((float) JournalEntry::withoutGlobalScopes()
            ->where('transaction_id', $transaction->id)
            ->whereNull('deleted_at')
            ->selectRaw('SUM(debit) - SUM(credit) AS d')
            ->value('d'), 3);
    }

    /**
     * THE DEFECT, reproduced. `citycomm_city-tour-test` documents #36575 (task 20845 / RCJ75K,
     * KWD 117.660) and #36588 (task 20854 / U99GHW, KWD 82.550), both written 2026-09-16.
     *
     * A task created from a price-less supplier document posts its issuance pair INERT —
     * `unbilled_cost` 0.000 / `payable` 0.000. When the real fare later arrives and the task's
     * total is edited, `handleAmountChange()`'s OFF path restated both legs. It chose the column
     * with `$entry->debit > 0`, which is a test of the line's VALUE, not of its SIDE: the
     * `unbilled_cost` DEBIT leg, sitting at 0.000, failed that test and was rewritten as a
     * CREDIT. Document off by -2x the amount.
     */
    public function test_amount_change_restates_an_inert_debit_leg_as_a_debit_not_a_credit(): void
    {
        [$company, $agent, $client, $supplier] = $this->scaffold();
        $task = $this->makeTask($company, $agent, $client, $supplier, 0.0);
        $document = $this->makeDocument($company, $task, 0.0);

        $cost = $this->makeLine($company, $document, $this->accountId($company, 0), 'unbilled_cost', 0.0, 0.0, $task);
        $payable = $this->makeLine($company, $document, $this->accountId($company, 1), 'payable', 0.0, 0.0, $task);

        $this->assertSame(0.0, $this->imbalanceOf($document), 'Precondition: the inert pair balances.');

        $task->total = 117.660;
        $task->price = 117.660;
        $task->save();

        $this->invokePrivate($this->taskController, 'handleAmountChange', $task->fresh());

        $cost->refresh();
        $payable->refresh();

        $this->assertSame(
            0.0,
            $this->imbalanceOf($document),
            'Restating an inert issuance pair must leave the document balanced. '
            .'Pre-fix this landed at -235.320 — exactly what transaction #36575 carries in production.'
        );
        $this->assertSame('117.660', (string) $cost->debit, 'The unbilled_cost leg is a DEBIT leg.');
        $this->assertSame('0.000', (string) $cost->credit, 'The unbilled_cost leg must not acquire a credit.');
        $this->assertSame('117.660', (string) $payable->credit, 'The payable leg is a CREDIT leg.');
        $this->assertSame('0.000', (string) $payable->debit, 'The payable leg must not acquire a debit.');
    }

    /**
     * The same defect through the second, independently written copy of the loop — the
     * `$legacyLedgerCorrection` closure inside `updateAdminFinancial()`. Same `debit > 0`
     * side-picker, same hole.
     */
    public function test_admin_financial_correction_restates_an_inert_debit_leg_as_a_debit(): void
    {
        [$company, $agent, $client, $supplier] = $this->scaffold();
        $task = $this->makeTask($company, $agent, $client, $supplier, 0.0);
        $document = $this->makeDocument($company, $task, 0.0);

        $cost = $this->makeLine($company, $document, $this->accountId($company, 0), 'unbilled_cost', 0.0, 0.0, $task);
        $this->makeLine($company, $document, $this->accountId($company, 1), 'payable', 0.0, 0.0, $task);

        $side = $this->invokePrivate($this->taskController, 'legacyRestatementSide', $cost);

        $this->assertSame(
            'debit',
            $side,
            'An inert unbilled_cost line is a DEBIT line. This is the single decision both '
            .'legacy restatement loops get wrong when they read $entry->debit > 0 instead.'
        );
    }

    /**
     * The fallback must not over-reach. A line with no usable side signal at all — inert AND of a
     * type that carries no fixed side — is LEFT ALONE, because an understated line keeps the
     * ledger balanced and a guessed one does not.
     */
    public function test_an_inert_line_of_unknown_side_is_skipped_rather_than_guessed(): void
    {
        [$company, $agent, $client, $supplier] = $this->scaffold();
        $task = $this->makeTask($company, $agent, $client, $supplier, 50.0);
        $document = $this->makeDocument($company, $task, 50.0);

        $debitLeg = $this->makeLine($company, $document, $this->accountId($company, 0), 'receivable', 50.0, 0.0, $task);
        $creditLeg = $this->makeLine($company, $document, $this->accountId($company, 1), 'income', 0.0, 50.0, $task);
        $inert = $this->makeLine($company, $document, $this->accountId($company, 2), 'bank', 0.0, 0.0, $task);

        $task->total = 117.660;
        $task->price = 117.660;
        $task->save();

        $this->invokePrivate($this->taskController, 'handleAmountChange', $task->fresh());

        $debitLeg->refresh();
        $creditLeg->refresh();
        $inert->refresh();

        $this->assertSame(
            0.0,
            $this->imbalanceOf($document),
            'Pre-fix the inert third line was rewritten as a 117.660 credit and the document '
            .'landed at -117.660.'
        );
        $this->assertSame('117.660', (string) $debitLeg->debit);
        $this->assertSame('117.660', (string) $creditLeg->credit);
        $this->assertSame('0.000', (string) $inert->debit, 'The unclassifiable line is untouched.');
        $this->assertSame('0.000', (string) $inert->credit, 'The unclassifiable line is untouched.');
    }

    /**
     * The backstop. Even if some future side-resolution decision is wrong, a legacy restatement
     * must never unbalance a document that BALANCES.
     *
     * Here the loop's own query — `task_id` + a description LIKE on the task reference — reaches
     * only ONE leg of a balanced two-task document, so restating that leg alone would take the
     * document from 0.000 to 67.660. It is refused instead, and the caller's transaction rolls the
     * whole edit back.
     */
    public function test_a_restatement_that_would_unbalance_a_balanced_document_is_refused(): void
    {
        [$company, $agent, $client, $supplier] = $this->scaffold();
        $task = $this->makeTask($company, $agent, $client, $supplier, 50.0);
        $otherTask = $this->makeTask($company, $agent, $client, $supplier, 50.0);
        $document = $this->makeDocument($company, $task, 50.0);

        $ourLeg = $this->makeLine($company, $document, $this->accountId($company, 0), 'receivable', 50.0, 0.0, $task);
        $this->makeLine($company, $document, $this->accountId($company, 1), 'income', 0.0, 50.0, $otherTask);

        $this->assertSame(0.0, $this->imbalanceOf($document), 'Precondition: the document balances.');

        $task->total = 117.660;
        $task->price = 117.660;
        $task->save();

        // Both real call chains (applyTaskUpdate() via update()/updateMulti(), and
        // updateAdminFinancial()) run this inside their OWN DB::beginTransaction() and never
        // swallow the exception, so the throw is what rolls the edit back. Reproduce that frame
        // here -- asserting the throw alone would not prove the rows are unwritten.
        $threw = false;
        \Illuminate\Support\Facades\DB::beginTransaction();

        try {
            $this->invokePrivate($this->taskController, 'handleAmountChange', $task->fresh());
            \Illuminate\Support\Facades\DB::commit();
        } catch (UnbalancedDocumentException $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            $threw = true;
            $this->assertStringContainsString('Refusing to write', $e->getMessage());
        }

        $this->assertTrue($threw, 'Expected UnbalancedDocumentException; the restatement was allowed through.');

        $ourLeg->refresh();
        $this->assertSame(0.0, $this->imbalanceOf($document), 'The refusal must leave the document balanced.');
        $this->assertSame('50.000', (string) $ourLeg->debit, 'Nothing was written.');
    }

    /**
     * The guard's scope, stated as a test. A document that was ALREADY unbalanced before the edit
     * is out of scope: this codebase really did write one-sided legacy documents (CT-A1 finding 3,
     * the credit-only issuance entries), refusing an unrelated task edit cannot repair one, and
     * blocking that edit would be a regression rather than a fix.
     */
    public function test_an_already_unbalanced_document_is_still_restated(): void
    {
        [$company, $agent, $client, $supplier] = $this->scaffold();
        $task = $this->makeTask($company, $agent, $client, $supplier, 50.0);
        $document = $this->makeDocument($company, $task, 50.0);

        $onlyLeg = $this->makeLine($company, $document, $this->accountId($company, 0), 'receivable', 50.0, 0.0, $task);

        $this->assertSame(50.0, $this->imbalanceOf($document), 'Precondition: an already one-sided document.');

        $task->total = 117.660;
        $task->price = 117.660;
        $task->save();

        $this->invokePrivate($this->taskController, 'handleAmountChange', $task->fresh());

        $onlyLeg->refresh();
        $this->assertSame('117.660', (string) $onlyLeg->debit, 'Pre-existing history is restated exactly as before.');
    }

    /**
     * THE THIRD DEFECT. `citycomm_city-tour-test` journal entry #84522, document #36590,
     * invoice INV-2026-02072, 2026-09-16: a MyFatoorah payment receipt's gateway-asset DEBIT leg
     * of KWD 320.850 was claimed by `InvoiceController::updateOrCreateEntryByAccount()` — which
     * matched only on `(invoice_detail_id, account_id)` and then took `$entries->first()` because
     * exactly one row existed — and overwritten in place with the gateway-profit writer's own
     * 0.000, description and all. 100 receipts carry that overwrite; this one is the only one not
     * yet papered over into 1654 Suspense / Adjustments by `accounting:repair`, and it is the
     * largest single slice of the KWD -721.270.
     */
    public function test_update_or_create_by_account_never_claims_another_documents_line(): void
    {
        [$company, $agent, $client, $supplier] = $this->scaffold();
        $task = $this->makeTask($company, $agent, $client, $supplier, 321.0);

        $receiptDocument = $this->makeDocument($company, $task, 321.0);
        $recalcDocument = $this->makeDocument($company, $task, 321.0);

        $gatewayAccountId = $this->accountId($company, 0);
        $invoice = \App\Models\Invoice::factory()->create(['agent_id' => $agent->id]);
        $invoiceDetailId = \App\Models\InvoiceDetail::factory()->create([
            'invoice_id' => $invoice->id,
            'task_id' => $task->id,
            'task_price' => 321.0,
            'supplier_price' => 0.0,
        ])->id;

        $bankLeg = $this->makeLine(
            $company,
            $receiptDocument,
            $gatewayAccountId,
            'bank',
            320.850,
            0.0,
            $task,
            'Net payment received',
        );
        $bankLeg->invoice_detail_id = $invoiceDetailId;
        $bankLeg->save();

        $this->invokePrivate(
            app(InvoiceController::class),
            'updateOrCreateEntryByAccount',
            $invoiceDetailId,
            $gatewayAccountId,
            'Gateway profit on '.$task->reference,
            [
                'transaction_id' => $recalcDocument->id,
                'company_id' => $company->id,
                'account_id' => $gatewayAccountId,
                'invoice_detail_id' => $invoiceDetailId,
                'transaction_date' => now(),
                'name' => 'Gateway',
                'type' => 'asset',
                'debit' => 0.0,
                'credit' => 0,
                'amount' => 0.0,
            ],
        );

        $bankLeg->refresh();

        $this->assertSame(
            '320.850',
            (string) $bankLeg->debit,
            'The receipt document\'s own bank leg belongs to a different document and must not be '
            .'claimed. Pre-fix this read 0.000 and document #36590 went off by -320.850.'
        );
        $this->assertSame('Net payment received', $bankLeg->description, 'Its description must survive too.');
        $this->assertSame(
            0.0,
            $this->imbalanceOf($recalcDocument),
            'A zero-amount gateway-profit call must write nothing at all.'
        );
    }
}
