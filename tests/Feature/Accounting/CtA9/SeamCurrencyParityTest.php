<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA9;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\InvoiceReceipt;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\LegacyLineCurrencyColumns;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\VoucherOptions;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * CT-A9 **T2** — the seam's OFF path must write the same currency facts as its ON path.
 *
 * ── The defect ─────────────────────────────────────────────────────────────────────────────────
 * `PostingSeam::post($draft, $legacy, $key)` runs `PostingService::post()` when
 * `accounting.engine.enabled` is true and the `$legacy` closure when it is not. Two writers were
 * purpose-built for parity — `BankPaymentController::writeLegacyTransaction()` and
 * `ReceiptVoucherController::writeLegacyTransaction()` — and both wrote `currency` and
 * `exchange_rate` while writing NEITHER `original_currency` NOR `original_amount`, which
 * `PostingService::post()` step 8 has written since W1.1 (CT-FX-EXPOSURE-2026-09-16.md §5.4
 * item 2). Same document, two shapes, depending on a flag.
 *
 * That is not cosmetic. `journal_entries.original_currency` is the column that says WHICH CURRENCY
 * a balance is denominated in, it is NULL on 99.88 % of the live ledger, and CT-FX's §6 conclusion
 * is that tagging FC balances is a PREREQUISITE for any revaluation policy, not a detail of one.
 * A seam that drops the tag on one side of a config flag guarantees the prerequisite is never met.
 *
 * ── The ReceiptVoucher writer had a second, worse gap ──────────────────────────────────────────
 * It wrote `'name' => $displayName` — the DOCUMENT's display name — on every line, dropping
 * `LineDraft::$partyName` entirely, while the ON path writes `$line->partyName ?? $account->name`.
 * `journal_entries.name` is what the client and supplier statement screens print, so the OFF path
 * was erasing the per-line party on exactly the rows a statement is built from. Fixed here as a
 * PARTY fact, alongside the currency ones.
 *
 * ── Why the rate is not simply copied ──────────────────────────────────────────────────────────
 * CT-A9 T1 (ruling R-CT13) makes the ENGINE derive a base-currency line's rate as 1.000000 rather
 * than copy the feeder's. Copying `$line->exchangeRate` into the OFF writers would therefore have
 * opened a FRESH drift on the same seam while closing the old one. Both writers go through
 * {@see LegacyLineCurrencyColumns}, which is the one implementation of that rule.
 */
class SeamCurrencyParityTest extends AccountingTestCase
{
    use GrantsAccountingModule;

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    /** @return array{0: Company, 1: Branch, 2: Agent, 3: Client, 4: User} */
    private function makeFixture(): array
    {
        $company = Company::factory()->create();
        $this->grantAccountingModule($company);
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);

        $agentUser = User::factory()->create();
        AgentType::firstOrCreate(['id' => 1], ['name' => 'type-1']);
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentType->id,
        ]);

        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $company->id]);
        $this->trackCompanyForInvariants($company->id);

        (new SystemAccountsSeeder)->run();

        // Inserted raw. This test builds TWO companies in one test method, and by the second one
        // there is already an authenticated user from the first; Setting's own company binding
        // would attach the row to the wrong company and the voucher would sit at 'pending'
        // instead of auto-approving, which is how this fixture failed the first time it was run.
        DB::table('settings')->insert([
            'company_id' => $company->id,
            'key' => VoucherOptions::APPROVAL_THRESHOLD_KEY,
            'value' => '1000',
            'type' => 'string',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$company, $branch, $agent, $client, $admin];
    }

    private function accountByCode(int $companyId, string $code): Account
    {
        return Account::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->firstOrFail();
    }

    /**
     * ONE company, TWO receipts: the first posted with the engine on, the second with the global
     * flag off. Same company, same chart, same threshold setting, same amount, same account — the
     * only thing that differs between the two documents is which writer ran, which is exactly what
     * makes the comparison a parity test rather than a coincidence.
     *
     * @return array{0: \Illuminate\Support\Collection<int, JournalEntry>, 1: \Illuminate\Support\Collection<int, JournalEntry>}
     */
    private function postOneReceiptEachWay()
    {
        [$company, $branch, , , $admin] = $this->makeFixture();

        config(['accounting.engine.enabled' => true]);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $on = $this->storeReceipt($company, $branch, $admin, 'CT-A9 parity, engine ON', 'ON');

        // The kill switch, exactly as an operator would throw it.
        config(['accounting.engine.enabled' => false]);

        $off = $this->storeReceipt($company, $branch, $admin, 'CT-A9 parity, engine OFF', 'OFF');

        return [$on, $off];
    }

    /** @return \Illuminate\Support\Collection<int, JournalEntry> */
    private function storeReceipt(Company $company, Branch $branch, User $admin, string $remark, string $label)
    {
        $account = $this->accountByCode((int) $company->id, '2110');

        $this->actingAs($admin)->post(route('receipt-voucher.store'), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'type' => 'account',
            'account_id' => $account->id,
            'amount' => 50,
            'remarks_create' => $remark,
        ])->assertRedirect();

        $receipt = InvoiceReceipt::where('company_id', $company->id)->latest('id')->first();
        $this->assertNotNull($receipt, "fixture precondition ({$label}): the receipt was created");

        $lines = JournalEntry::withoutGlobalScopes()
            ->where('transaction_id', $receipt->transaction_id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $lines, sprintf(
            'fixture precondition (%s): a two-legged voucher; receipt#%d status=%s txn=%s',
            $label, $receipt->id, (string) $receipt->status, (string) $receipt->transaction_id
        ));

        return $lines;
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // THE PARITY, measured by posting the same document both ways
    // ════════════════════════════════════════════════════════════════════════════════════════════

    /**
     * ── MUTATION PROOF M-A9-T2-PARITY ───────────────────────────────────────────────────────────
     * The oracle is the ON path's own output, read at runtime — NOT a hard-coded list of expected
     * values. That matters: an expectation written as `assertSame('KWD', $off->original_currency)`
     * would keep passing if the ENGINE stopped writing the column, and the property under test is
     * "the two sides agree", not "one side writes KWD".
     *
     * Before CT-A9 the OFF row has `original_currency = NULL` and `original_amount = NULL` while
     * the ON row has 'KWD' and 50.000, so this test fails on the first of the four columns with a
     * NULL-vs-'KWD' diff. It is the measured defect, reproduced.
     */
    public function test_the_off_path_writes_the_same_currency_facts_as_the_on_path(): void
    {
        [$on, $off] = $this->postOneReceiptEachWay();

        foreach ([0, 1] as $i) {
            foreach (['currency', 'exchange_rate', 'original_currency', 'original_amount'] as $column) {
                $this->assertSame(
                    (string) $on[$i]->{$column},
                    (string) $off[$i]->{$column},
                    "line {$i}: journal_entries.{$column} must not depend on accounting.engine.enabled"
                );
            }

            // Stated absolutely as well as relatively, so a build in which BOTH sides wrote NULL
            // could not pass the loop above by agreeing with each other about nothing.
            $this->assertNotNull($off[$i]->original_currency, 'the OFF path must say what the line is denominated in');
            $this->assertNotNull($off[$i]->original_amount, 'the OFF path must carry the foreign face value');
            $this->assertSame('KWD', (string) $off[$i]->original_currency);
        }
    }

    /**
     * The PARTY half of the same finding: the ReceiptVoucher OFF writer used to stamp the document's
     * display name on every line and drop `LineDraft::$partyName`.
     *
     * Proved at the unit boundary rather than through the controller, because the store() route's
     * own drafts do not always set `partyName` — the defect is about what the writer does with the
     * field when a feeder DOES set it, and the assertion has to be about that.
     */
    public function test_the_receipt_off_writer_keeps_a_line_party_name(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/ReceiptVoucherController.php'));
        $this->assertIsString($source);

        // Sliced to the JOURNAL LINE write only. The `transactions` header a few lines above it
        // legitimately writes `'name' => $displayName` — that IS the document's own name — so a
        // slice that started at the method would fail the negative assertion on the right code.
        $writer = $this->writerBody($source, 'JournalEntry::create([', 'return $txn;');

        $this->assertStringContainsString(
            "'name' => \$line->partyName ?? \$displayName,",
            $writer,
            'the OFF writer must prefer the LINE\'s own party over the document display name, as step 8 does'
        );
        $this->assertStringNotContainsString(
            "'name' => \$displayName,\n",
            $writer,
            'the pre-CT-A9 shape — the document name stamped on every line — must be gone'
        );
    }

    /**
     * Both purpose-built OFF writers must go through the ONE implementation of the rule. A ratchet,
     * because a future writer that hand-rolled the four columns would pass the behavioural test
     * above on the day it was written and drift the moment R-CT13 changed.
     */
    public function test_both_off_writers_use_the_single_currency_column_rule(): void
    {
        foreach ([
            'Http/Controllers/BankPaymentController.php',
            'Http/Controllers/ReceiptVoucherController.php',
        ] as $relative) {
            $source = file_get_contents(app_path($relative));
            $this->assertIsString($source);

            $this->assertStringContainsString(
                '...LegacyLineCurrencyColumns::for($line),',
                $source,
                $relative.' must derive its four currency columns from LegacyLineCurrencyColumns'
            );
        }
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // THE RULE ITSELF
    // ════════════════════════════════════════════════════════════════════════════════════════════

    /**
     * R-CT13 applies on the OFF path too. A base-currency line carrying a stray rate is written at
     * 1.000000, exactly as the engine writes it — otherwise closing the `original_*` gap would have
     * opened an `exchange_rate` one.
     */
    public function test_a_base_currency_line_is_normalised_to_rate_one_on_the_off_path_too(): void
    {
        $columns = LegacyLineCurrencyColumns::for($this->line('KWD', 100.0, 100.0, 0.340000), 'KWD');

        $this->assertSame(1.0, $columns['exchange_rate']);
        $this->assertSame('KWD', $columns['currency']);
        $this->assertSame('KWD', $columns['original_currency']);
        $this->assertSame(100.0, $columns['original_amount']);
    }

    /** A genuinely foreign line keeps its rate on the OFF path, same as on the ON path. */
    public function test_a_foreign_line_keeps_its_rate_on_the_off_path(): void
    {
        $columns = LegacyLineCurrencyColumns::for($this->line('EGP', 7.807, 1239.0, 0.006301), 'KWD');

        $this->assertSame(0.006301, $columns['exchange_rate']);
        $this->assertSame('EGP', $columns['original_currency']);
        $this->assertSame(1239.0, $columns['original_amount']);
    }

    /** Case-normalised, exactly as step 3f compares — 'kwd' is the base currency. */
    public function test_a_lowercase_base_tag_is_base_on_the_off_path(): void
    {
        $columns = LegacyLineCurrencyColumns::for($this->line('kwd', 5.0, 5.0, 9.0), 'KWD');

        $this->assertSame(1.0, $columns['exchange_rate']);
    }

    /**
     * The OFF path does NOT refuse. Its contract is "write what the draft says"; making it throw
     * would change which documents post depending on a config flag, which is the opposite of
     * parity. A triple the engine would refuse is written through here, unchanged.
     */
    public function test_the_off_rule_never_refuses_an_inconsistent_foreign_triple(): void
    {
        // Task 15993's numbers — the engine refuses this; the OFF passthrough must not throw.
        $columns = LegacyLineCurrencyColumns::for($this->line('USD', 368.0, 368.0, 0.340000), 'KWD');

        $this->assertSame(0.340000, $columns['exchange_rate']);
        $this->assertSame(368.0, $columns['original_amount']);
    }

    private function line(string $currency, float $amount, float $originalAmount, float $rate): LineDraft
    {
        return new LineDraft(
            purposeCode: '', accountId: 1, side: 'debit', amount: $amount,
            currency: $currency, originalAmount: $originalAmount, exchangeRate: $rate,
            transactionType: 'JOURNAL',
        );
    }

    private function writerBody(string $source, string $startNeedle, string $endNeedle): string
    {
        $start = strpos($source, $startNeedle);
        $this->assertNotFalse($start, 'CT-A9 anchor missing: '.$startNeedle);

        $end = strpos($source, $endNeedle, $start);
        $this->assertNotFalse($end, 'CT-A9 anchor missing: '.$endNeedle);

        return substr($source, $start, $end - $start);
    }
}
