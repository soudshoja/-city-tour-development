<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA3;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceReceipt;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\SystemAccount;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * VERIFY CT-A3 STACK R2 — throwaway adversarial probes, part 2 (replay linkage, company scope,
 * coa-linkage reversibility).
 */
class Rv2Probe2Test extends AccountingTestCase
{
    use GrantsAccountingModule;

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    /** @return array{0: Company, 1: Branch, 2: Agent, 3: Client, 4: User} */
    private function makeFixture(bool $engine = true): array
    {
        $company = Company::factory()->create();
        $this->grantAccountingModule($company);
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);

        $agentUser = User::factory()->create();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentType->id]);

        $client = Client::factory()->create(['agent_id' => $agent->id, 'company_id' => $company->id]);
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $company->id]);

        $this->trackCompanyForInvariants($company->id);

        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder)->run();

        if ($engine) {
            Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        }

        Artisan::call('accounting:periods:init', ['--company' => $company->id]);

        return [$company, $branch, $agent, $client, $admin];
    }

    private function accountByCode(int $companyId, string $code): Account
    {
        return Account::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->firstOrFail();
    }

    private function netDebit(int $accountId): float
    {
        $rows = JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')->where('account_id', $accountId);

        return round((float) (clone $rows)->sum('debit') - (float) (clone $rows)->sum('credit'), 3);
    }

    private function makeUnpostedApprovedReceipt(Company $company, Branch $branch, Client $client, Invoice $invoice, float $amount = 100.0): InvoiceReceipt
    {
        return InvoiceReceipt::create([
            'type' => 'invoice',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'doc_date' => now()->subDay()->toDateString(),
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => $amount]],
            'remainder_amount' => 0,
            'remainder_policy' => 'credit',
            'bank_account_id' => $this->accountByCode($company->id, '1201')->id,
            'status' => InvoiceReceipt::STATUS_APPROVED,
            'transaction_id' => null,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════════════════════
    // PROBE 7 — R2-3. Replay a NULL-company receipt, then EDIT it, then DELETE it.
    // ════════════════════════════════════════════════════════════════════════════════════════

    public function test_probe7_replayed_null_company_receipt_edits_and_deletes(): void
    {
        [$company, $branch, $agent, $client, $admin] = $this->makeFixture();

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'amount' => 200.000,
            'status' => 'unpaid',
            'invoice_date' => now()->subDays(2),
        ]);

        $receipt = $this->makeUnpostedApprovedReceipt($company, $branch, $client, $invoice);

        Artisan::call('accounting:replay', ['--company' => (string) $company->id, '--class' => 'receipt']);

        $bank = $this->accountByCode($company->id, '1201');
        $this->assertSame(100.0, $this->netDebit($bank->id), 'precondition: the replay posted it');

        // The legacy shape an EARLIER backfill leaves behind: live document, no linkage, no company.
        InvoiceReceipt::withoutGlobalScopes()->whereKey($receipt->id)->update([
            'transaction_id' => null,
            'company_id' => null,
        ]);

        // EDIT — the revision path, on a row the controller cannot company-scope from its own column.
        $this->actingAs($admin)->put(route('receipt-voucher.update', $receipt->id), [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'docdate' => now()->toDateString(),
            'type' => 'invoice',
            'client_id' => $client->id,
            'amount' => 150.0,
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => 150.0]],
            'bank_account_id' => $bank->id,
        ])->assertRedirect();

        $keys = Transaction::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('idempotency_key', 'like', 'rv:'.$receipt->id.'%')
            ->orderBy('id')->pluck('idempotency_key')->all();

        fwrite(STDERR, "\n[PROBE7 after edit] bank=".$this->netDebit($bank->id).' keys='.implode(',', $keys)."\n");

        $this->assertSame(150.0, $this->netDebit($bank->id), 'the edit of a REPLAYED, unlinked, NULL-company receipt must land the new amount');
        $this->assertContains('rv:'.$receipt->id.':rev1', $keys);

        // DELETE — must reverse, not delete the rows, and not throw.
        InvoiceReceipt::withoutGlobalScopes()->whereKey($receipt->id)->update([
            'transaction_id' => null,
            'company_id' => null,
        ]);

        $this->actingAs($admin)->delete(route('receipt-voucher.destroy', $receipt->id))->assertRedirect();

        fwrite(STDERR, '[PROBE7 after destroy] bank='.$this->netDebit($bank->id)."\n");

        $this->assertSame(0.0, $this->netDebit($bank->id), 'destroy must reverse the live document');
        $this->assertGreaterThan(
            0,
            Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('posting_status', 'reversed')->count(),
            'reversed, not deleted'
        );
    }

    // ════════════════════════════════════════════════════════════════════════════════════════
    // PROBE 8 — R2-4. --company scope end to end, both directions, and the missing flag.
    // ════════════════════════════════════════════════════════════════════════════════════════

    public function test_probe8_company_scope_both_directions(): void
    {
        [$a, $branchA, $agentA, $clientA] = $this->makeFixture();
        [$b, $branchB, $agentB, $clientB] = $this->makeFixture();

        $invA = Invoice::factory()->create(['client_id' => $clientA->id, 'agent_id' => $agentA->id, 'amount' => 100, 'status' => 'unpaid', 'invoice_date' => now()->subDay()]);
        $invB = Invoice::factory()->create(['client_id' => $clientB->id, 'agent_id' => $agentB->id, 'amount' => 70, 'status' => 'unpaid', 'invoice_date' => now()->subDay()]);

        $rA = $this->makeUnpostedApprovedReceipt($a, $branchA, $clientA, $invA, 100.0);
        $rB = $this->makeUnpostedApprovedReceipt($b, $branchB, $clientB, $invB, 70.0);

        // Both rows in the legacy shape: no company_id of their own.
        InvoiceReceipt::withoutGlobalScopes()->whereIn('id', [$rA->id, $rB->id])->update(['company_id' => null]);

        Artisan::call('accounting:replay', ['--company' => (string) $a->id, '--class' => 'all']);

        $linesInB = JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')
            ->whereIn('transaction_id', Transaction::withoutGlobalScopes()->where('company_id', $b->id)->pluck('id'))
            ->count();

        fwrite(STDERR, "\n[PROBE8] after --company={$a->id}: company-{$b->id} lines={$linesInB}\n");
        $this->assertSame(0, $linesInB, 'a company-A replay must write nothing into company B');

        Artisan::call('accounting:replay', ['--company' => (string) $b->id, '--class' => 'all']);

        $bankB = $this->accountByCode($b->id, '1201');
        $this->assertSame(70.0, $this->netDebit($bankB->id), 'company B\'s own run must post B\'s row');

        // And every company-A document is still company A's.
        $foreign = Transaction::withoutGlobalScopes()
            ->where('company_id', $a->id)
            ->whereIn('idempotency_key', ['rv:'.$rB->id])
            ->count();
        $this->assertSame(0, $foreign);

        // The flag is mandatory.
        $exit = Artisan::call('accounting:replay', ['--class' => 'receipt']);
        fwrite(STDERR, "[PROBE8] replay without --company exit={$exit}\n");
        $this->assertNotSame(0, $exit, '--company must be mandatory');
    }

    // ════════════════════════════════════════════════════════════════════════════════════════
    // PROBE 9 — R2-5. Is `--rollback` actually "undo this run in full"?
    // ════════════════════════════════════════════════════════════════════════════════════════

    public function test_probe9_rollback_is_a_full_undo(): void
    {
        [$company] = $this->makeFixture();

        // DAMAGE the chart into the shape CT-A4 measured on the real one: an expense filed as a
        // balance-sheet line, a NULL account_type_id, a wrong is_group — and a system leaf that is
        // simply MISSING, so --apply has to mint one.
        $expense = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'Bank Charges')->firstOrFail();
        DB::table('accounts')->where('id', $expense->id)->update([
            'report_type' => Account::REPORT_TYPES['BALANCE_SHEET'],
            'is_group' => 1,
            'account_type_id' => null,
        ]);

        $missing = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '5131')->first();
        if ($missing !== null) {
            SystemAccount::withoutGlobalScopes()->where('company_id', $company->id)->where('account_id', $missing->id)->delete();
            DB::table('accounts')->where('id', $missing->id)->delete();
        }

        // CT-A3 R3-2: a FULL snapshot, not the three columns the pre-R3 rollback happened to
        // restore. `created_at` / `updated_at` are excluded and nothing else is: a rollback writes
        // rows, so their timestamps move, and asserting on them would test the clock rather than
        // the undo. Every column that says anything about the CHART is compared.
        $snapshotAccounts = fn (): array => DB::table('accounts')->where('company_id', $company->id)
            ->orderBy('id')->get()
            ->map(function ($r): array {
                $row = (array) $r;
                unset($row['created_at'], $row['updated_at']);

                return $row;
            })->all();

        $snapshotPurposes = fn (): array => DB::table('system_accounts')->where('company_id', $company->id)
            ->orderBy('id')->get(['id', 'company_id', 'purpose_code', 'service_type', 'account_id'])
            ->map(fn ($r) => (array) $r)->all();

        $accountsBefore = $snapshotAccounts();
        $accountIdsBefore = array_column($accountsBefore, 'id');
        $systemAccountsBefore = $snapshotPurposes();
        $purposesBefore = SystemAccount::withoutGlobalScopes()->where('company_id', $company->id)
            ->orderBy('id')->pluck('account_id', 'purpose_code')->all();

        Artisan::call('accounting:coa-linkage', ['--company' => (string) $company->id, '--apply' => true]);
        $applyOut = Artisan::output();

        $runId = (string) DB::table('coa_linkage_changes')->where('company_id', $company->id)
            ->orderByDesc('id')->value('run_id');
        $this->assertNotSame('', $runId, 'the apply run must record a run id');

        $accountIdsAfterApply = DB::table('accounts')->where('company_id', $company->id)->orderBy('id')->pluck('id')->all();
        $minted = array_values(array_diff($accountIdsAfterApply, $accountIdsBefore));

        $mappingsMinted = count(array_diff(
            array_column($snapshotPurposes(), 'id'),
            array_column($systemAccountsBefore, 'id')
        ));

        $rollbackExit = Artisan::call('accounting:coa-linkage', ['--rollback' => $runId]);
        $rollbackOut = Artisan::output();

        $accountsAfter = $snapshotAccounts();
        $systemAccountsAfter = $snapshotPurposes();

        $accountIdsAfterRollback = array_column($accountsAfter, 'id');
        $stillMinted = array_values(array_intersect($minted, $accountIdsAfterRollback));

        $purposesAfter = SystemAccount::withoutGlobalScopes()->where('company_id', $company->id)
            ->orderBy('id')->pluck('account_id', 'purpose_code')->all();

        $newPurposes = array_diff_key($purposesAfter, $purposesBefore);

        fwrite(STDERR, sprintf(
            "\n[PROBE9] minted=%d stillMintedAfterRollback=%d mappingsMintedByApply=%d purposesBefore=%d purposesAfterRollback=%d newPurposesSurviving=%d exit=%d\n",
            count($minted), count($stillMinted), $mappingsMinted, count($purposesBefore), count($purposesAfter), count($newPurposes), $rollbackExit
        ));
        fwrite(STDERR, '[PROBE9] rollback said: '.trim(preg_replace('/\s+/', ' ', $rollbackOut))."\n");
        fwrite(STDERR, '[PROBE9] apply claim line present: '.(str_contains($applyOut, 'Undo this run in full') ? 'YES' : 'no')."\n");

        // ── FINDING V1, INVERTED BY CT-A3 R3-2 ──────────────────────────────────────────────────
        // The verify-R2 lane committed this as a RISK witness: `--rollback` restored three columns
        // and printed "Undo this run in full", while 3 of 3 minted leaves and 2 created purpose
        // mappings survived — leaving "a chart state nobody chose: purposes still resolve to the
        // leaves the run minted, while report_type is back at its pre-repair value".
        //
        // The run must genuinely have had something to undo, or this case proves nothing.
        $this->assertNotSame([], $minted, 'the apply run must have minted at least one leaf for this case to mean anything');
        $this->assertGreaterThan(0, $mappingsMinted, 'and created at least one purpose mapping');

        $this->assertSame([], $stillMinted, 'R3-2: every leaf the run minted is removed by --rollback');
        $this->assertSame([], $newPurposes, 'R3-2: and every purpose mapping it created is removed too');

        // The whole point: byte-identical, not "the three columns are byte-identical".
        $this->assertSame($accountsBefore, $accountsAfter, 'R3-2: `accounts` is byte-identical after apply → rollback');
        $this->assertSame($systemAccountsBefore, $systemAccountsAfter, 'R3-2: `system_accounts` is byte-identical after apply → rollback');

        // A complete undo exits 0; an incomplete one exits non-zero and names what it refused.
        $this->assertSame(0, $rollbackExit, 'a complete undo exits 0');
        $this->assertStringNotContainsString('REFUSED', $rollbackOut);
    }

    /**
     * CT-A3 R3-2 — the one thing a full undo genuinely cannot do, and must SAY it cannot do.
     *
     * A leaf the repair minted, POSTED TO since, cannot be removed without destroying ledger rows.
     * The pre-R3 rollback removed nothing and said nothing; the R3 one removes what it can, REFUSES
     * that leaf by name, and exits non-zero so a runbook can gate on "the undo was complete".
     */
    public function test_probe9b_rollback_refuses_a_minted_leaf_that_now_carries_journal_activity(): void
    {
        [$company] = $this->makeFixture();

        $missing = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '5131')->first();

        if ($missing !== null) {
            SystemAccount::withoutGlobalScopes()->where('company_id', $company->id)->where('account_id', $missing->id)->delete();
            DB::table('accounts')->where('id', $missing->id)->delete();
        }

        $accountIdsBefore = DB::table('accounts')->where('company_id', $company->id)->pluck('id')->all();

        Artisan::call('accounting:coa-linkage', ['--company' => (string) $company->id, '--apply' => true]);

        $runId = (string) DB::table('coa_linkage_changes')->where('company_id', $company->id)
            ->orderByDesc('id')->value('run_id');

        $minted = array_values(array_diff(
            DB::table('accounts')->where('company_id', $company->id)->pluck('id')->all(),
            $accountIdsBefore
        ));

        $this->assertNotSame([], $minted, 'the apply run must have minted a leaf for this case to mean anything');

        // Post to one of the minted leaves — the state that makes the leaf undeletable.
        $target = (int) $minted[0];

        DB::table('journal_entries')->insert([
            'company_id' => $company->id,
            'account_id' => $target,
            'name' => 'probe9b',
            'description' => 'a posting that lands on a leaf the repair minted',
            'transaction_date' => now(),
            'amount' => 1.000,
            'exchange_rate' => 1.000000,
            'debit' => 1.000,
            'credit' => 0.000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // An EXPLICIT BufferedOutput, and `withoutMockingConsoleOutput()` first. Two independent
        // reasons the obvious `Artisan::call()` + `Artisan::output()` reads back EMPTY here — both
        // worth recording, because a test that silently asserts on an empty string asserts nothing:
        //   1. a Laravel test mocks the console output by default, so command writes go to the mock
        //      rather than to any buffer the caller passes;
        //   2. this command delegates to `accounting:ensure-system-leaves` with its own output
        //      handle, which replaces the console kernel's `lastOutput` — which is why the
        //      informational dump in probe9 above reads blank.
        $this->withoutMockingConsoleOutput();

        $buffer = new \Symfony\Component\Console\Output\BufferedOutput;
        $exit = Artisan::call('accounting:coa-linkage', ['--rollback' => $runId], $buffer);
        $out = $buffer->fetch();

        fwrite(STDERR, "\n[PROBE9b] exit={$exit} out=".trim(preg_replace('/\s+/', ' ', $out))."\n");

        $this->assertSame(1, $exit, 'an incomplete undo must exit non-zero');
        $this->assertStringContainsString('REFUSED', $out, 'and must name what it could not remove');
        $this->assertStringContainsString('#'.$target, $out, 'by id');
        $this->assertStringContainsString('This undo was NOT complete.', $out);

        $this->assertTrue(
            DB::table('accounts')->where('id', $target)->exists(),
            'the posted-to leaf is left in place, not deleted out from under its ledger rows'
        );

        $this->assertSame(
            1,
            DB::table('coa_linkage_changes')
                ->where('run_id', $runId)
                ->where('subject_table', 'accounts')
                ->where('column_name', \App\Models\CoaLinkageChange::ROW_CREATED)
                ->where('subject_id', $target)
                ->whereNull('rolled_back_at')
                ->count(),
            'the refused change stays un-rolled-back, so a second --rollback picks up where this one stopped'
        );

        // Scaffolding removed before AccountingTestCase's per-test invariants run: the row above is
        // deliberately a bare `journal_entries` insert with no `transaction_id`, which is exactly
        // the shape the orphaned-line invariant exists to catch. It is here to make the account
        // undeletable, not to model a real posting.
        DB::table('journal_entries')->where('account_id', $target)->where('name', 'probe9b')->delete();
    }

    /**
     * CT-A3 R3-2, FOUND BY THE SERVER RUN AND NOT BY A TEST — the other way a minted leaf can be
     * undeletable, and the one that used to abort the whole undo.
     *
     * `system_accounts.account_id` carries a real, enforced foreign key. A mapping this run did not
     * create, still pointing at a leaf the rollback wants to delete, makes the DELETE fail, the
     * surrounding transaction roll the WHOLE undo back, and the operator get a QueryException
     * instead of a rollback. Measured on `citycomm_ct_r3`: company 3's `system_accounts` rows #188
     * and #189, written on 2026-09-05 pointing at account ids 1738/1739 that did not then exist
     * (CT-A4's dangling-mapping finding, one id range later), silently became live pointers at
     * company 1's newly minted `21109 Creditors Control` and `4132 Markup Income` when --apply took
     * exactly those two ids.
     *
     * Refusing the one leaf by name leaves the rest of the undo intact.
     */
    public function test_probe9c_rollback_refuses_a_minted_leaf_a_foreign_purpose_mapping_still_points_at(): void
    {
        [$company] = $this->makeFixture();

        $missing = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '5131')->first();

        if ($missing !== null) {
            SystemAccount::withoutGlobalScopes()->where('company_id', $company->id)->where('account_id', $missing->id)->delete();
            DB::table('accounts')->where('id', $missing->id)->delete();
        }

        $accountIdsBefore = DB::table('accounts')->where('company_id', $company->id)->pluck('id')->all();

        Artisan::call('accounting:coa-linkage', ['--company' => (string) $company->id, '--apply' => true]);

        $runId = (string) DB::table('coa_linkage_changes')->where('company_id', $company->id)
            ->orderByDesc('id')->value('run_id');

        $minted = array_values(array_diff(
            DB::table('accounts')->where('company_id', $company->id)->pluck('id')->all(),
            $accountIdsBefore
        ));

        $this->assertNotSame([], $minted, 'the apply run must have minted a leaf for this case to mean anything');

        $target = (int) $minted[0];

        // A mapping belonging to ANOTHER company, landing on this leaf by id — the exact shape the
        // server run hit. Written directly, because that is how it got there on the real chart too:
        // it was dangling, and the id came into existence later.
        $otherCompany = Company::factory()->create();

        DB::table('system_accounts')->insert([
            'company_id' => $otherCompany->id,
            'purpose_code' => 'SERVICE_REVENUE',
            'service_type' => 'lounge',
            'account_id' => $target,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withoutMockingConsoleOutput();

        $buffer = new \Symfony\Component\Console\Output\BufferedOutput;
        $exit = Artisan::call('accounting:coa-linkage', ['--rollback' => $runId], $buffer);
        $out = $buffer->fetch();

        fwrite(STDERR, "\n[PROBE9c] exit={$exit} out=".trim(preg_replace('/\s+/', ' ', $out))."\n");

        $this->assertSame(1, $exit, 'an incomplete undo must exit non-zero');
        $this->assertStringContainsString('REFUSED', $out);
        $this->assertStringContainsString('#'.$target, $out);
        $this->assertStringContainsString('purpose mapping(s) this run did not create', $out);

        $this->assertTrue(
            DB::table('accounts')->where('id', $target)->exists(),
            'the referenced leaf is left in place rather than aborting the whole undo on a foreign key'
        );

        // …and the rest of the undo still happened: every OTHER minted leaf is gone.
        $stillMinted = array_values(array_intersect(
            $minted,
            DB::table('accounts')->where('company_id', $company->id)->pluck('id')->all()
        ));

        $this->assertSame([$target], $stillMinted, 'only the blocked leaf survives — the refusal is per leaf, not per run');

        DB::table('system_accounts')->where('account_id', $target)->where('company_id', $otherCompany->id)->delete();
    }
}
