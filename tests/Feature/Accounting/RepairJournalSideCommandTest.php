<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Services\Accounting\BeforeImageOwnership;
use Database\Seeders\CoaSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * CT-TALLY (2026-09-18) — `accounting:repair-journal-side`.
 *
 * The fixtures are the three real documents, rebuilt line for line from the read-only measurement
 * of `citycomm_city-tour-test` company 1 taken that day: #36575 (-235.320), #36588 (-165.100) and
 * #36590 (-320.850), which are the whole of that ledger's KWD -721.270.
 */
class RepairJournalSideCommandTest extends AccountingTestCase
{
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        config(['accounting.engine.enabled' => false]);
        $this->company = Company::factory()->create();
        CoaSeeder::run($this->company->id);
    }

    private function accountId(int $nth = 0): int
    {
        return Account::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->orderBy('id')
            ->skip($nth)
            ->take(1)
            ->value('id');
    }

    /** A LEGACY document: `doc_type` and `posting_date` both absent, so the conjunction says legacy. */
    private function legacyDocument(float $amount): Transaction
    {
        return Transaction::create([
            'company_id' => $this->company->id,
            'entity_id' => $this->company->id,
            'entity_type' => 'company',
            'transaction_type' => 'credit',
            'amount' => $amount,
            'description' => 'ct-tally fixture',
            'reference_type' => 'Payment',
            'transaction_date' => now(),
        ]);
    }

    private function line(Transaction $document, int $accountId, string $type, float $debit, float $credit): JournalEntry
    {
        return JournalEntry::create([
            'transaction_id' => $document->id,
            'company_id' => $this->company->id,
            'account_id' => $accountId,
            'transaction_date' => now(),
            'description' => 'ct-tally fixture line',
            'name' => 'CT Tally',
            'debit' => $debit,
            'credit' => $credit,
            'balance' => $debit - $credit,
            'type' => $type,
        ]);
    }

    private function imbalanceOf(Transaction $document): float
    {
        return round((float) JournalEntry::withoutGlobalScopes()
            ->where('transaction_id', $document->id)
            ->whereNull('deleted_at')
            ->selectRaw('SUM(debit) - SUM(credit) AS d')
            ->value('d'), 3);
    }

    /** #36575: `unbilled_cost` 117.660 stranded on the CREDIT side alongside its payable. */
    private function sideFlipDocument(float $amount): array
    {
        $document = $this->legacyDocument($amount);
        $cost = $this->line($document, $this->accountId(0), 'unbilled_cost', 0.0, $amount);
        $this->line($document, $this->accountId(1), 'payable', 0.0, $amount);

        return [$document, $cost];
    }

    /** #36590: the receipt's gateway-asset debit leg overwritten with 0.000. */
    private function zeroedLegDocument(): array
    {
        $document = $this->legacyDocument(321.0);
        $this->line($document, $this->accountId(0), 'receivable', 0.0, 321.0);
        $bank = $this->line($document, $this->accountId(1), 'bank', 0.0, 0.0);
        $this->line($document, $this->accountId(2), 'charges', 0.150, 0.0);

        return [$document, $bank];
    }

    public function test_dry_run_is_the_default_and_writes_nothing(): void
    {
        [$document, $cost] = $this->sideFlipDocument(117.660);

        $this->assertSame(-235.320, $this->imbalanceOf($document));

        $this->artisan('accounting:repair-journal-side', ['--company' => $this->company->id])
            ->expectsOutputToContain('DRY RUN')
            ->expectsOutputToContain('SIDE_FLIP')
            ->assertExitCode(0);

        $cost->refresh();
        $this->assertSame('0.000', (string) $cost->debit, 'A dry run writes nothing.');
        $this->assertSame('117.660', (string) $cost->credit, 'A dry run writes nothing.');
        $this->assertSame(-235.320, $this->imbalanceOf($document), 'A dry run writes nothing.');
        $this->assertSame(0, DB::table('coa_linkage_changes')->count(), 'A dry run records no before-image.');
    }

    public function test_apply_closes_both_real_shapes_exactly(): void
    {
        [$flipDoc, $cost] = $this->sideFlipDocument(117.660);
        [$zeroDoc, $bank] = $this->zeroedLegDocument();

        $this->assertSame(-235.320, $this->imbalanceOf($flipDoc));
        $this->assertSame(-320.850, $this->imbalanceOf($zeroDoc));

        $this->artisan('accounting:repair-journal-side', [
            '--company' => $this->company->id,
            '--apply' => true,
        ])->assertExitCode(0);

        $cost->refresh();
        $bank->refresh();

        $this->assertSame('117.660', (string) $cost->debit, 'SIDE_FLIP moves the amount to the debit column.');
        $this->assertSame('0.000', (string) $cost->credit);
        $this->assertSame('320.850', (string) $bank->debit, 'ZEROED_LEG takes back the overwritten 320.850.');
        $this->assertSame('0.000', (string) $bank->credit);

        $this->assertSame(0.0, $this->imbalanceOf($flipDoc), 'Document #36575 closes exactly.');
        $this->assertSame(0.0, $this->imbalanceOf($zeroDoc), 'Document #36590 closes exactly.');
    }

    public function test_it_refuses_a_document_whose_arithmetic_does_not_close(): void
    {
        // The same side-flip shape, plus a second, unrelated defect: a stray credit nobody can
        // explain. Flipping the cost line would leave the document off by -40.000, so the repair
        // is refused whole rather than applied half.
        $document = $this->legacyDocument(117.660);
        $cost = $this->line($document, $this->accountId(0), 'unbilled_cost', 0.0, 117.660);
        $this->line($document, $this->accountId(1), 'payable', 0.0, 117.660);
        $this->line($document, $this->accountId(2), 'charges', 0.0, 40.0);

        $before = $this->imbalanceOf($document);

        $this->artisan('accounting:repair-journal-side', [
            '--company' => $this->company->id,
            '--apply' => true,
        ])->expectsOutputToContain('REFUSED')->assertExitCode(0);

        $cost->refresh();
        $this->assertSame('117.660', (string) $cost->credit, 'Nothing written; nothing guessed.');
        $this->assertSame($before, $this->imbalanceOf($document));
        $this->assertSame(0, DB::table('coa_linkage_changes')->count());
    }

    public function test_it_refuses_a_document_with_more_than_one_candidate(): void
    {
        $document = $this->legacyDocument(100.0);
        $this->line($document, $this->accountId(0), 'receivable', 0.0, 100.0);
        $firstInert = $this->line($document, $this->accountId(1), 'bank', 0.0, 0.0);
        $this->line($document, $this->accountId(2), 'charges', 0.0, 0.0);

        $this->artisan('accounting:repair-journal-side', [
            '--company' => $this->company->id,
            '--apply' => true,
        ])->expectsOutputToContain('more than one line could be the repair')->assertExitCode(0);

        $firstInert->refresh();
        $this->assertSame('0.000', (string) $firstInert->debit, 'Two candidates means no repair, not a coin toss.');
    }

    /**
     * The command must never propose a write against a document the engine owns. The discriminator
     * is the CONJUNCTION `doc_type IS NOT NULL AND posting_date IS NOT NULL` — the one-column form
     * misclassifies engine-OFF vouchers.
     */
    public function test_it_never_touches_an_engine_owned_document(): void
    {
        $document = $this->legacyDocument(117.660);
        $document->doc_type = 'JV';
        $document->posting_date = now()->toDateString();
        $document->save();

        $cost = $this->line($document, $this->accountId(0), 'unbilled_cost', 0.0, 117.660);
        $this->line($document, $this->accountId(1), 'payable', 0.0, 117.660);

        $this->artisan('accounting:repair-journal-side', [
            '--company' => $this->company->id,
            '--apply' => true,
        ])->expectsOutputToContain('no unbalanced legacy document')->assertExitCode(0);

        $cost->refresh();
        $this->assertSame('117.660', (string) $cost->credit, 'An engine document is out of scope entirely.');
    }

    public function test_rollback_restores_the_recorded_before_images(): void
    {
        [$document, $cost] = $this->sideFlipDocument(117.660);

        $this->artisan('accounting:repair-journal-side', [
            '--company' => $this->company->id,
            '--apply' => true,
        ])->assertExitCode(0);

        $this->assertSame(0.0, $this->imbalanceOf($document));

        $runId = DB::table('coa_linkage_changes')
            ->where('subject_table', 'journal_entries')
            ->orderByDesc('id')
            ->value('run_id');
        $this->assertNotNull($runId);

        $this->artisan('accounting:repair-journal-side', ['--rollback' => $runId])->assertExitCode(0);

        $cost->refresh();
        $this->assertSame('0.000', (string) $cost->debit, 'Rollback restores the recorded before-image.');
        $this->assertSame('117.660', (string) $cost->credit);
        $this->assertSame(-235.320, $this->imbalanceOf($document), 'Back to the measured production imbalance.');
    }

    public function test_rollback_refuses_a_run_it_does_not_own(): void
    {
        $runId = 'CTTALLYFOREIGNRUN000000001';
        DB::table('coa_linkage_changes')->insert([
            'run_id' => $runId,
            'company_id' => $this->company->id,
            'subject_table' => 'journal_entries',
            'subject_id' => 1,
            'column_name' => 'type_reference_id',
            'before_value' => '1',
            'after_value' => '2',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('accounting:repair-journal-side', ['--rollback' => $runId])
            ->expectsOutputToContain('does not own')
            ->assertExitCode(1);

        $this->assertNull(
            DB::table('coa_linkage_changes')->where('run_id', $runId)->value('rolled_back_at'),
            'A foreign run must be left entirely alone.'
        );
    }

    public function test_the_ownership_map_routes_this_commands_columns_to_this_command(): void
    {
        $this->assertSame(
            'accounting:repair-journal-side',
            BeforeImageOwnership::commandFor(['journal_entries.debit'])
        );
        $this->assertSame(
            'accounting:repair-journal-side',
            BeforeImageOwnership::commandFor(['journal_entries.credit'])
        );
        $this->assertSame(
            ['debit', 'credit'],
            BeforeImageOwnership::columnsOwnedBy('accounting:repair-journal-side', 'journal_entries')
        );
    }

    public function test_limit_bounds_the_run(): void
    {
        [$firstDoc] = $this->sideFlipDocument(117.660);
        [$secondDoc] = $this->sideFlipDocument(82.550);

        $this->artisan('accounting:repair-journal-side', [
            '--company' => $this->company->id,
            '--apply' => true,
            '--limit' => 1,
        ])->assertExitCode(0);

        $this->assertSame(0.0, $this->imbalanceOf($firstDoc), 'The first document in id order is repaired.');
        $this->assertSame(-165.100, $this->imbalanceOf($secondDoc), '--limit stops the run before the second.');
    }
}
