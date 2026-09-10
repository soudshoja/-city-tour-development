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

        fwrite(STDERR, "\n[PROBE7 after edit] bank=".$this->netDebit($bank->id)." keys=".implode(',', $keys)."\n");

        $this->assertSame(150.0, $this->netDebit($bank->id), 'the edit of a REPLAYED, unlinked, NULL-company receipt must land the new amount');
        $this->assertContains('rv:'.$receipt->id.':rev1', $keys);

        // DELETE — must reverse, not delete the rows, and not throw.
        InvoiceReceipt::withoutGlobalScopes()->whereKey($receipt->id)->update([
            'transaction_id' => null,
            'company_id' => null,
        ]);

        $this->actingAs($admin)->delete(route('receipt-voucher.destroy', $receipt->id))->assertRedirect();

        fwrite(STDERR, "[PROBE7 after destroy] bank=".$this->netDebit($bank->id)."\n");

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

    public function test_probe9_rollback_is_a_partial_undo(): void
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

        $accountsBefore = DB::table('accounts')->where('company_id', $company->id)
            ->orderBy('id')->get(['id', 'code', 'name', 'report_type', 'is_group', 'account_type_id'])
            ->map(fn ($r) => (array) $r)->all();
        $accountIdsBefore = array_column($accountsBefore, 'id');
        $purposesBefore = SystemAccount::withoutGlobalScopes()->where('company_id', $company->id)
            ->orderBy('id')->pluck('account_id', 'purpose_code')->all();

        Artisan::call('accounting:coa-linkage', ['--company' => (string) $company->id, '--apply' => true]);
        $applyOut = Artisan::output();

        $runId = (string) DB::table('coa_linkage_changes')->where('company_id', $company->id)
            ->orderByDesc('id')->value('run_id');
        $this->assertNotSame('', $runId, 'the apply run must record a run id');

        $accountIdsAfterApply = DB::table('accounts')->where('company_id', $company->id)->orderBy('id')->pluck('id')->all();
        $minted = array_values(array_diff($accountIdsAfterApply, $accountIdsBefore));

        Artisan::call('accounting:coa-linkage', ['--rollback' => $runId]);
        $rollbackOut = Artisan::output();

        $accountsAfter = DB::table('accounts')->where('company_id', $company->id)
            ->whereIn('id', $accountIdsBefore)
            ->orderBy('id')->get(['id', 'code', 'name', 'report_type', 'is_group', 'account_type_id'])
            ->map(fn ($r) => (array) $r)->all();

        $accountIdsAfterRollback = DB::table('accounts')->where('company_id', $company->id)->orderBy('id')->pluck('id')->all();
        $stillMinted = array_values(array_intersect($minted, $accountIdsAfterRollback));

        $purposesAfter = SystemAccount::withoutGlobalScopes()->where('company_id', $company->id)
            ->orderBy('id')->pluck('account_id', 'purpose_code')->all();

        $newPurposes = array_diff_key($purposesAfter, $purposesBefore);

        fwrite(STDERR, sprintf(
            "\n[PROBE9] minted=%d stillMintedAfterRollback=%d purposesBefore=%d purposesAfterRollback=%d newPurposesSurviving=%d\n",
            count($minted), count($stillMinted), count($purposesBefore), count($purposesAfter), count($newPurposes)
        ));
        fwrite(STDERR, "[PROBE9] rollback said: ".trim(preg_replace('/\s+/', ' ', $rollbackOut))."\n");
        fwrite(STDERR, "[PROBE9] apply claim line present: ".(str_contains($applyOut, 'Undo this run in full') ? 'YES' : 'no')."\n");

        // The three columns ARE restored byte-identically on every pre-existing account.
        $this->assertSame($accountsBefore, $accountsAfter, 'report_type / is_group / account_type_id must be byte-identical after --rollback');

        // …and this is the half the command's own "Undo this run in full" does NOT do. RECORDED,
        // not asserted-away: --rollback restores three COLUMNS on pre-existing accounts and
        // nothing else. Leaves the run minted survive, and so do the system_accounts purpose
        // mappings it created — so a rolled-back chart is a HYBRID: purposes still resolve to the
        // new leaves while report_type is back at its pre-repair (wrong) value.
        $this->assertNotSame([], $stillMinted, 'FINDING V1: minted leaves survive --rollback');
        $this->assertNotSame([], $newPurposes, 'FINDING V1: purpose mappings survive --rollback');
    }
}
