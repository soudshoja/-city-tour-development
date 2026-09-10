<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA56R3;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostingService;
use App\Services\TrialBalanceService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * CT-A56 R3 probe — `accounting:dedupe-cutover` (PR #13) measured against `LedgerSource` (PR #12).
 *
 * PR #13's own test suite proves the command in isolation: it reverses the legacy half, never
 * deletes, is idempotent, refuses an unbalanced set. Every one of those claims holds. What no test
 * in either PR asks is what the command does to the ledger the OTHER PR made the reports read —
 * and the two PRs are being deployed together.
 *
 * The reversing document is posted through {@see PostingService::post()}. It therefore carries
 * `doc_type='REV'` and a `posting_date`, which makes it, by {@see \App\Services\Accounting\
 * LedgerSource}'s own discriminator, an **ENGINE** row. The rows it reverses are LEGACY rows, which
 * an engine-mode report never reads. So the reversal lands entirely on the side of the ledger that
 * did not need correcting.
 */
class DedupeCutoverProbeTest extends AccountingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['accounting.engine.enabled' => true]);
    }

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);

        parent::tearDown();
    }

    private function makeCompany(): Company
    {
        $company = tap(Company::factory()->create(), fn (Company $c) => $c
            ->forceFill(['posting_engine_enabled' => true])->save());
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();
        $this->trackCompanyForInvariants($company->id);

        return $company;
    }

    private function makeBranch(Company $company): Branch
    {
        return Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => User::factory()->create()->id,
        ]);
    }

    /** A real `invoices` row — `journal_entries.invoice_id` carries a FK. */
    private function makeInvoice(Company $company, float $amount): int
    {
        return (int) Invoice::factory()->create([
            'amount' => $amount,
            'status' => 'unpaid',
            'invoice_date' => now(),
        ])->id;
    }

    private function receivableId(Company $c): int
    {
        return app(AccountResolver::class)->resolve('RECEIVABLE_CONTROL', $c->id)->id;
    }

    private function markupIncomeId(Company $c): int
    {
        return app(AccountResolver::class)->resolve('MARKUP_INCOME', $c->id)->id;
    }

    /** One engine document: Dr RECEIVABLE_CONTROL / Cr MARKUP_INCOME, stamped with $invoiceId. */
    private function postEngineDocument(Company $company, Branch $branch, int $invoiceId, float $amount): void
    {
        app(PostingService::class)->post(new DocumentDraft(
            companyId: $company->id,
            branchId: $branch->id,
            docType: 'JV',
            subType: null,
            docDate: now(),
            narration: 'R3 dedupe probe — engine half',
            lines: [
                new LineDraft(
                    purposeCode: 'RECEIVABLE_CONTROL', accountId: null, side: 'debit', amount: $amount,
                    currency: 'KWD', originalAmount: $amount, exchangeRate: 1.0,
                    transactionType: 'R3_ENGINE', invoiceId: $invoiceId,
                ),
                new LineDraft(
                    purposeCode: 'MARKUP_INCOME', accountId: null, side: 'credit', amount: $amount,
                    currency: 'KWD', originalAmount: $amount, exchangeRate: 1.0,
                    transactionType: 'R3_ENGINE', invoiceId: $invoiceId,
                ),
            ],
            idempotencyKey: 'r3-dedupe-probe:engine:'.$invoiceId,
        ));
    }

    /**
     * One legacy transaction (doc_type NULL, posting_date NULL on header and lines) carrying
     * $pairs, each `[invoiceId, amount]` — a balanced Dr receivable / Cr income pair per entry,
     * exactly the shape the pre-engine invoice writer produces for a multi-detail invoice.
     */
    private function insertLegacyDocument(Company $company, Branch $branch, array $pairs): int
    {
        $total = array_sum(array_column($pairs, 1));

        $txId = DB::table('transactions')->insertGetId([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'transaction_type' => 'JV',
            'amount' => $total,
            'description' => 'R3 dedupe probe — legacy half',
            'reference_type' => 'Invoice',
            'transaction_date' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rows = [];

        foreach ($pairs as [$invoiceId, $amount]) {
            $rows[] = [
                'name' => 'R3 legacy debit',
                'transaction_id' => $txId,
                'company_id' => $company->id,
                'account_id' => $this->receivableId($company),
                'branch_id' => $branch->id,
                'invoice_id' => $invoiceId,
                'transaction_date' => now(),
                'description' => 'R3 legacy debit',
                'debit' => $amount,
                'credit' => 0,
                'type_reference_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $rows[] = [
                'name' => 'R3 legacy credit',
                'transaction_id' => $txId,
                'company_id' => $company->id,
                'account_id' => $this->markupIncomeId($company),
                'branch_id' => $branch->id,
                'invoice_id' => $invoiceId,
                'transaction_date' => now(),
                'description' => 'R3 legacy credit',
                'debit' => 0,
                'credit' => $amount,
                'type_reference_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('journal_entries')->insert($rows);

        return $txId;
    }

    private function engineIncome(Company $company): float
    {
        $rows = app(TrialBalanceService::class)
            ->generate($company->id, Carbon::now()->subYear(), Carbon::now()->addDay(), ['show_zero' => true])['accounts']
            ->keyBy('id');

        $income = $this->markupIncomeId($company);

        return $rows->has($income)
            ? round((float) $rows[$income]->total_credit - (float) $rows[$income]->total_debit, 3)
            : 0.0;
    }

    /**
     * DEFECT R3-2 — the two PRs cancel each other out.
     *
     * The engine-mode trial balance already reads the engine document alone; the legacy twin is
     * invisible to it. `accounting:dedupe-cutover --apply` then posts an ENGINE reversal of the
     * legacy amount, which the engine-mode trial balance DOES read. Net: the document's revenue
     * disappears from every CT-A6 report.
     */
    public function test_dedupe_cutover_does_not_zero_the_engine_document_it_was_meant_to_protect(): void
    {
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company);

        $invoiceId = $this->makeInvoice($company, 100.000);

        $this->postEngineDocument($company, $branch, invoiceId: $invoiceId, amount: 100.000);
        $this->insertLegacyDocument($company, $branch, [[$invoiceId, 100.000]]);

        $before = $this->engineIncome($company);

        $this->assertEqualsWithDelta(
            100.000,
            $before,
            0.0005,
            'Pre-condition: the engine-mode trial balance already ignores the legacy twin — there is '.
            'nothing for the dedupe command to correct on this report in the first place.'
        );

        // R3-2 fix: --apply alone is REFUSED by name, and writes nothing.
        $this->artisan('accounting:dedupe-cutover', [
            '--company' => $company->id,
            '--from' => Carbon::now()->subDay()->toDateTimeString(),
            '--apply' => true,
        ])->expectsOutputToContain('LEDGER_SOURCE_ACTIVE')->assertExitCode(1)->run();

        $after = $this->engineIncome($company);

        $this->assertEqualsWithDelta(
            100.000,
            $after,
            0.0005,
            "accounting:dedupe-cutover --apply moved the engine-mode revenue from {$before} to {$after}. ".
            'The reversal is posted through PostingService, so it carries doc_type=REV and a '.
            'posting_date and is an ENGINE row; the legacy rows it reverses are never read in engine '.
            'mode. The command therefore subtracts real money from every LedgerSource-restricted '.
            'report instead of removing a double count.'
        );
    }

    /**
     * DEFECT R3-3 — detection is per document KEY, reversal is per legacy TRANSACTION.
     *
     * One legacy transaction covering two invoices, only one of which the engine replay actually
     * carried (CT-D1 measured 10 outright replay refusals and 5,835 issuance skips, so a partially
     * covered legacy header is the normal case, not a contrived one). The command reverses BOTH
     * halves, taking the un-replayed invoice's money off the ledger with nothing standing in its
     * place.
     */
    public function test_dedupe_cutover_does_not_reverse_a_legacy_half_the_engine_never_covered(): void
    {
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company);

        // $coveredId exists on both sides. $uncoveredId exists ONLY on the legacy side.
        $coveredId = $this->makeInvoice($company, 100.000);
        $uncoveredId = $this->makeInvoice($company, 70.000);

        $this->postEngineDocument($company, $branch, invoiceId: $coveredId, amount: 100.000);
        $this->insertLegacyDocument($company, $branch, [[$coveredId, 100.000], [$uncoveredId, 70.000]]);

        $this->artisan('accounting:dedupe-cutover', [
            '--company' => $company->id,
            '--from' => Carbon::now()->subDay()->toDateTimeString(),
            '--apply' => true,
            '--force-legacy-reversal' => true,
        ])->expectsOutputToContain('PARTIAL_ENGINE_COVERAGE')->assertExitCode(1)->run();

        $reversalLines = DB::table('journal_entries as je')
            ->join('transactions as t', 't.id', '=', 'je.transaction_id')
            ->where('t.company_id', $company->id)
            ->where('t.sub_type', 'CUTOVER_DEDUPE')
            ->get(['je.invoice_id', 'je.debit', 'je.credit']);

        $reversedForUncovered = $reversalLines
            ->where('invoice_id', $uncoveredId)
            ->sum(fn ($l) => (float) $l->debit + (float) $l->credit);

        $this->assertEqualsWithDelta(
            0.0,
            $reversedForUncovered,
            0.0005,
            'The dedupe reversal touched invoice '.$uncoveredId.', which has NO engine posting at '.
            'all — its legacy lines are the only record of that money. Dual posting was detected on '.
            'invoice '.$coveredId.' and the whole legacy transaction was reversed on the strength of it.'
        );
    }

    /**
     * The `--from` boundary, measured. The command bounds on `journal_entries.created_at`, which on
     * the City Travelers dev site is the LIVE row's own timestamp, preserved by the hourly mirror —
     * the command's own docblock says so. A row written on LIVE before the operator's cutover clock
     * and mirrored in afterwards is therefore INVISIBLE to any `--from` chosen from the deploy
     * clock, which is precisely the population (invoices 2061-2064, 03:34-03:58 UTC, gate flipped
     * 06:40 UTC) the command exists for.
     */
    public function test_the_from_boundary_misses_a_mirrored_row_stamped_before_the_cutover_clock(): void
    {
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company);

        $cutoverClock = Carbon::now()->subHours(3);

        $invoiceId = $this->makeInvoice($company, 100.000);

        $this->postEngineDocument($company, $branch, invoiceId: $invoiceId, amount: 100.000);

        $legacyTxId = $this->insertLegacyDocument($company, $branch, [[$invoiceId, 100.000]]);

        // The mirror preserves the SOURCE row's created_at: hours before the operator's own clock.
        DB::table('journal_entries')
            ->where('transaction_id', $legacyTxId)
            ->update(['created_at' => $cutoverClock->copy()->subHours(3)]);

        $this->artisan('accounting:dedupe-cutover', [
            '--company' => $company->id,
            '--from' => $cutoverClock->toDateTimeString(),
            '--dry-run' => true,
        ])->expectsOutputToContain('dual_posted=0')->assertExitCode(0)->run();

        // R3-4 fix: the id boundary. `journal_entries.id` is assigned by the SOURCE system in
        // arrival order and preserved by the mirror, so "greater than the highest id in my rollback
        // dump" is exactly "arrived after my rollback point" — the question --from was trying, and
        // failing, to ask.
        $highestIdInTheRollbackDump = (int) DB::table('journal_entries')
            ->where('transaction_id', '<>', $legacyTxId)
            ->max('id');

        $this->artisan('accounting:dedupe-cutover', [
            '--company' => $company->id,
            '--after-journal-entry-id' => $highestIdInTheRollbackDump,
            '--dry-run' => true,
        ])->expectsOutputToContain('dual_posted=1')->assertExitCode(0)->run();
    }
}
