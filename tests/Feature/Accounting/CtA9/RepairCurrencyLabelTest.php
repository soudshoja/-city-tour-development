<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA9;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * CT-A9 T3 — `accounting:repair-currency-label`.
 *
 * The properties under test are the ones that decide whether a 4,581-row relabel is safe to run on
 * a business's real ledger:
 *
 *   - dry-run is the DEFAULT and writes nothing;
 *   - a row that asserts NO foreign fact (no `original_amount`, rate 0 or 1) is relabelled;
 *   - a row carrying a REAL RATE is REFUSED — CT-FX proved the label is wrong on those 510 live
 *     rows but never established what the right label is;
 *   - a row carrying a real `original_amount` is REFUSED — that is the only FC fact on the ledger;
 *   - a row whose two currency columns disagree is REFUSED;
 *   - NULL-currency rows are out of scope entirely;
 *   - `debit`, `credit` and `amount` never move;
 *   - the rate half's CONSUMER IMPACT is reported, because "zero risk" was not true as stated;
 *   - per-row before-images, exact rollback, and an ownership guard that is symmetric against the
 *     other command that writes `journal_entries` before-images.
 */
class RepairCurrencyLabelTest extends AccountingTestCase
{
    use GrantsAccountingModule;

    private int $companyId;

    private int $branchId;

    /** currency 'USD', rate 1.000000, no FC amount — the 4,553-row shape. REPAIRABLE. */
    private int $labelOnlyId;

    /** currency 'USD', rate 0.000000, no FC amount — the 28-row shape. REPAIRABLE, rate moves. */
    private int $labelAndRateId;

    /** currency 'USD', rate 0.080000 (the AED rate) — REFUSED. */
    private int $realRateId;

    /** currency 'USD', a real FC amount — REFUSED. */
    private int $fcAmountId;

    /** currency 'USD', original_currency 'AED' — REFUSED. */
    private int $disagreeId;

    /** currency NULL, rate 0 — OUT OF SCOPE. */
    private int $nullCurrencyId;

    /** currency 'KWD' — OUT OF SCOPE. */
    private int $baseId;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        $this->companyId = (int) $company->id;
        $this->grantAccountingModule($company);
        CoaSeeder::run($this->companyId);

        $branchOwner = User::factory()->create();
        $this->branchId = (int) Branch::factory()->create([
            'company_id' => $this->companyId, 'user_id' => $branchOwner->id,
        ])->id;

        session(['company_id' => $this->companyId]);
        $this->trackCompanyForInvariants($this->companyId);
        (new SystemAccountsSeeder)->run();

        $this->labelOnlyId = $this->historicalLine(['currency' => 'USD', 'exchange_rate' => 1.0]);
        $this->labelAndRateId = $this->historicalLine(['currency' => 'USD', 'exchange_rate' => 0.0]);
        $this->realRateId = $this->historicalLine(['currency' => 'USD', 'exchange_rate' => 0.080000]);
        $this->fcAmountId = $this->historicalLine([
            'currency' => 'USD', 'exchange_rate' => 1.0, 'original_amount' => 100.000, 'original_currency' => 'USD',
        ]);
        $this->disagreeId = $this->historicalLine([
            'currency' => 'USD', 'exchange_rate' => 1.0, 'original_currency' => 'AED',
        ]);
        $this->nullCurrencyId = $this->historicalLine(['currency' => null, 'exchange_rate' => 0.0]);
        $this->baseId = $this->historicalLine(['currency' => 'KWD', 'exchange_rate' => 1.0]);
    }

    // ── fixture helpers ─────────────────────────────────────────────────────────────────────────

    private function accountByCode(string $code): Account
    {
        return Account::withoutGlobalScopes()
            ->where('company_id', $this->companyId)->where('code', $code)
            ->whereNull('deleted_at')->firstOrFail();
    }

    /**
     * A BALANCED two-line legacy document whose FIRST leg carries the currency shape under test and
     * whose contra is an ordinary KWD line. Balanced because AccountingTestCase asserts the
     * company's trial balance in tearDown — a fixture that did not balance would fail every test in
     * this class for a reason that has nothing to do with the repair.
     *
     * @param  array<string, mixed>  $subject
     */
    private function historicalLine(array $subject): int
    {
        $amount = 120.000;

        $txn = Transaction::forceCreate([
            'company_id' => $this->companyId, 'branch_id' => $this->branchId,
            'entity_id' => $this->companyId, 'entity_type' => 'company',
            'transaction_type' => 'INV', 'amount' => $amount, 'description' => 'historical sale',
            'reference_type' => 'Invoice', 'reference_number' => 'A9-'.substr(uniqid(), -8),
            'name' => 'historical sale', 'transaction_date' => now()->subMonths(6),
            'total_debit' => $amount, 'total_credit' => $amount,
        ]);

        $id = (int) JournalEntry::create($subject + [
            'transaction_id' => $txn->id, 'company_id' => $this->companyId, 'branch_id' => $this->branchId,
            'account_id' => $this->accountByCode('1351')->id,
            'transaction_date' => now()->subMonths(6),
            'description' => 'historical sale', 'debit' => $amount, 'credit' => 0,
            'name' => 'a party', 'type' => 'receivable', 'amount' => $amount,
            'voucher_number' => 'A9',
        ])->id;

        JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $this->companyId, 'branch_id' => $this->branchId,
            'account_id' => $this->accountByCode('4110')->id,
            'transaction_date' => now()->subMonths(6),
            'description' => 'historical sale (contra)', 'debit' => 0, 'credit' => $amount,
            'name' => 'revenue', 'type' => 'income', 'currency' => 'KWD', 'exchange_rate' => 1.0,
            'amount' => $amount, 'voucher_number' => 'A9',
        ]);

        return $id;
    }

    /** @param array<string, mixed> $options */
    private function repair(array $options = []): int
    {
        return Artisan::call('accounting:repair-currency-label', $options + ['--company' => $this->companyId]);
    }

    private function row(int $id): object
    {
        return DB::table('journal_entries')->where('id', $id)->first();
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // THE PREDICATE
    // ════════════════════════════════════════════════════════════════════════════════════════════

    public function test_the_default_is_a_dry_run_and_writes_nothing(): void
    {
        $this->assertSame(0, $this->repair());

        $this->assertSame('USD', $this->row($this->labelOnlyId)->currency);
        $this->assertSame('USD', $this->row($this->labelAndRateId)->currency);
        $this->assertSame(0, DB::table('coa_linkage_changes')->count());
    }

    public function test_apply_relabels_only_the_rows_that_assert_no_foreign_fact(): void
    {
        $this->assertSame(0, $this->repair(['--apply' => true]));

        $this->assertSame('KWD', $this->row($this->labelOnlyId)->currency, 'rate 1, no FC amount — demonstrably KWD');
        $this->assertSame('KWD', $this->row($this->labelAndRateId)->currency, 'rate 0, no FC amount — demonstrably KWD');

        $this->assertSame('USD', $this->row($this->realRateId)->currency, 'a real rate is a claim; refuse');
        $this->assertSame('USD', $this->row($this->fcAmountId)->currency, 'a real FC amount is a claim; refuse');
        $this->assertSame('USD', $this->row($this->disagreeId)->currency, 'two currency columns disagreeing is a claim; refuse');
        $this->assertNull($this->row($this->nullCurrencyId)->currency, 'a NULL currency asserts nothing — out of scope');
        $this->assertSame('KWD', $this->row($this->baseId)->currency);
    }

    /**
     * ── MUTATION PROOF M-A9-LABEL-REFUSAL ───────────────────────────────────────────────────────
     * The tempting wrong implementation is "relabel every row whose currency is not the base
     * currency" — it repairs 4,581 live rows instead of 4,553 and reads as more thorough.
     *
     * This test makes that implementation visibly wrong in THREE different ways at once, and pins
     * the reason for each refusal by its own reported text, so a build that refused the right rows
     * for the wrong reason (say, by filtering them out of the query and never reporting them) also
     * fails. The rate-half row is the one that matters most: `exchange_rate = 0.080000` on a row
     * labelled 'USD' is the AED rate, and CT-FX proved only that the label is WRONG — not what the
     * right one is.
     */
    public function test_every_row_that_asserts_a_foreign_fact_is_refused_and_the_reason_is_reported(): void
    {
        $this->artisan('accounting:repair-currency-label', ['--company' => $this->companyId, '--apply' => true])
            ->expectsOutputToContain('carries a real exchange_rate (neither 0 nor 1)')
            ->expectsOutputToContain('carries original_amount > 0 (a real foreign face value)')
            ->expectsOutputToContain('currency and original_currency name different codes')
            ->expectsOutputToContain('Nothing written; nothing guessed.')
            ->assertExitCode(0);

        foreach ([$this->realRateId, $this->fcAmountId, $this->disagreeId] as $id) {
            $this->assertSame('USD', $this->row($id)->currency);
        }
    }

    public function test_the_rate_moves_only_where_it_was_zero(): void
    {
        $this->repair(['--apply' => true]);

        $this->assertSame('1.000000', (string) $this->row($this->labelAndRateId)->exchange_rate);
        $this->assertSame('1.000000', (string) $this->row($this->labelOnlyId)->exchange_rate);

        // No before-image for a rate that was already 1.000000 — a rollback list full of no-ops is
        // a rollback list nobody trusts.
        $rateImages = DB::table('coa_linkage_changes')
            ->where('subject_table', 'journal_entries')->where('column_name', 'exchange_rate')->get();

        $this->assertCount(1, $rateImages);
        $this->assertSame($this->labelAndRateId, (int) $rateImages->first()->subject_id);
    }

    public function test_label_only_leaves_the_rate_alone(): void
    {
        $this->repair(['--apply' => true, '--label-only' => true]);

        $this->assertSame('KWD', $this->row($this->labelAndRateId)->currency);
        $this->assertSame('0.000000', (string) $this->row($this->labelAndRateId)->exchange_rate);
    }

    /**
     * The "zero risk" claim, verified rather than accepted. CT-FX-EXPOSURE §8.2 calls this repair
     * zero risk; two services branch on `exchange_rate > 0`, so the rate half is not inert and the
     * command has to say so every time it would move one.
     */
    public function test_the_rate_half_reports_its_consumer_impact(): void
    {
        $this->artisan('accounting:repair-currency-label', ['--company' => $this->companyId])
            ->expectsOutputToContain('CONSUMER IMPACT')
            ->expectsOutputToContain('RealisedFxService::compute() stops SKIPPING them')
            ->expectsOutputToContain('resolvePostedInvoiceRate() stops returning null')
            ->assertExitCode(0);
    }

    /** No money moves. The whole repair is a label. */
    public function test_no_debit_credit_or_amount_moves(): void
    {
        $before = DB::table('journal_entries')->where('company_id', $this->companyId)
            ->orderBy('id')->get(['id', 'debit', 'credit', 'amount', 'account_id'])->toJson();

        $this->repair(['--apply' => true]);

        $after = DB::table('journal_entries')->where('company_id', $this->companyId)
            ->orderBy('id')->get(['id', 'debit', 'credit', 'amount', 'account_id'])->toJson();

        $this->assertSame($before, $after);
    }

    public function test_a_second_apply_is_a_no_op(): void
    {
        $this->repair(['--apply' => true]);
        $firstImages = DB::table('coa_linkage_changes')->count();

        $this->repair(['--apply' => true]);

        $this->assertSame($firstImages, DB::table('coa_linkage_changes')->count());
    }

    /**
     * ── MUTATION PROOF M-A9-LABEL-PAGING ────────────────────────────────────────────────────────
     * REFUSED rows keep their foreign label forever, so a loop that re-queried `currency <> base`
     * would hand back the same refused batch and never terminate. Paging is by `id > lastSeen`.
     *
     * The proof is `--batch-size=1` with refused rows sitting AHEAD of a repairable one in id
     * order: a re-querying implementation never reaches the later row, so this test both hangs and
     * fails its assertion if the paging regresses.
     */
    public function test_a_refused_row_does_not_stall_the_paging(): void
    {
        $later = $this->historicalLine(['currency' => 'USD', 'exchange_rate' => 1.0]);
        $this->assertGreaterThan($this->realRateId, $later, 'the proof rests on this order');

        $this->repair(['--apply' => true, '--batch-size' => 1]);

        $this->assertSame('USD', $this->row($this->realRateId)->currency);
        $this->assertSame('KWD', $this->row($later)->currency, 'a refused row must not stop the walk reaching the ones after it');
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // BEFORE-IMAGES, ROLLBACK, OWNERSHIP
    // ════════════════════════════════════════════════════════════════════════════════════════════

    public function test_rollback_restores_every_value_the_run_wrote(): void
    {
        $this->repair(['--apply' => true]);
        $runId = (string) DB::table('coa_linkage_changes')->value('run_id');

        $this->assertSame(0, Artisan::call('accounting:repair-currency-label', ['--rollback' => $runId]));

        $this->assertSame('USD', $this->row($this->labelOnlyId)->currency);
        $this->assertSame('USD', $this->row($this->labelAndRateId)->currency);
        $this->assertSame('0.000000', (string) $this->row($this->labelAndRateId)->exchange_rate);
    }

    public function test_rollback_refuses_a_row_that_has_moved_since_the_run(): void
    {
        $this->repair(['--apply' => true]);
        $runId = (string) DB::table('coa_linkage_changes')->value('run_id');

        DB::table('journal_entries')->where('id', $this->labelOnlyId)->update(['currency' => 'GBP']);

        $this->assertSame(1, Artisan::call('accounting:repair-currency-label', ['--rollback' => $runId]));
        $this->assertSame('GBP', $this->row($this->labelOnlyId)->currency, 'someone else\'s later, deliberate value is left alone');
    }

    public function test_an_unrecognised_run_id_exits_non_zero(): void
    {
        $this->assertSame(1, Artisan::call('accounting:repair-currency-label', ['--rollback' => 'NOT-A-RUN']));
    }

    public function test_a_repeat_rollback_of_a_fully_undone_run_exits_zero(): void
    {
        $this->repair(['--apply' => true]);
        $runId = (string) DB::table('coa_linkage_changes')->value('run_id');

        Artisan::call('accounting:repair-currency-label', ['--rollback' => $runId]);

        $this->assertSame(0, Artisan::call('accounting:repair-currency-label', ['--rollback' => $runId]));
    }

    /**
     * ── MUTATION PROOF M-A9-OWNERSHIP ───────────────────────────────────────────────────────────
     * Both commands write `journal_entries` before-images now, so `subject_table` alone stopped
     * being a discriminator. Before CT-A9, `accounting:backfill-payable-party --rollback` SELECTed
     * every `journal_entries` row of a run — so handed this command's run id it would have walked
     * currency before-images, compared each against `type_reference_id`, found no match and
     * reported them all as "left alone" while exiting FAILURE; and a run containing both would have
     * written a currency code INTO `type_reference_id`.
     *
     * The guard is keyed on the PAIR in both directions, and each command names the other.
     */
    public function test_the_party_backfill_refuses_a_label_run_and_names_this_command(): void
    {
        $this->repair(['--apply' => true]);
        $runId = (string) DB::table('coa_linkage_changes')->value('run_id');

        $this->artisan('accounting:backfill-payable-party', ['--rollback' => $runId])
            ->expectsOutputToContain('contains before-images this command does not own')
            ->expectsOutputToContain('journal_entries.currency')
            ->expectsOutputToContain('php artisan accounting:repair-currency-label --rollback='.$runId)
            ->expectsOutputToContain('Nothing was restored.')
            ->assertExitCode(1);

        $this->assertSame('KWD', $this->row($this->labelOnlyId)->currency, 'the wrong command restored nothing');
    }

    public function test_the_linkage_command_refuses_a_label_run_and_names_this_command(): void
    {
        $this->repair(['--apply' => true]);
        $runId = (string) DB::table('coa_linkage_changes')->value('run_id');

        $this->artisan('accounting:coa-linkage', ['--rollback' => $runId])
            ->expectsOutputToContain('php artisan accounting:repair-currency-label --rollback='.$runId)
            ->assertExitCode(1);
    }

    public function test_dry_run_and_apply_together_are_refused(): void
    {
        $this->assertSame(1, $this->repair(['--apply' => true, '--dry-run' => true]));
    }

    public function test_rollback_cannot_be_combined_with_apply(): void
    {
        $this->assertSame(1, Artisan::call('accounting:repair-currency-label', ['--rollback' => 'X', '--apply' => true]));
    }
}
