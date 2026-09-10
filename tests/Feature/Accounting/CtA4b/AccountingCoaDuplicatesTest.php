<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA4b;

use App\Models\Account;
use App\Models\Company;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * CT-A4b — `accounting:coa-duplicates`, the report/renumber tool for the duplicate-code groups
 * already sitting on a real, already-populated chart (CT-A4 §1.6: 28 groups, 290 accounts on
 * company 1 alone) — the other half of this lane from the allocator fix, which only stops NEW
 * duplicates from being minted.
 *
 * Fixture shape mirrors CT-A4's own finding: a control/pool account and a per-supplier leaf
 * sharing one code. Two groups are built per test as needed — one where every member is safe to
 * renumber (zero journal activity), one where a member carries activity and must never be
 * renumbered out from under it.
 */
class AccountingCoaDuplicatesTest extends AccountingTestCase
{
    private function makeDuplicateGroup(Company $company, string $code, array $names): array
    {
        $pool = Account::factory()->create(['company_id' => $company->id, 'code' => '9'.$code, 'name' => 'Pool for '.$code]);

        $accounts = [];
        foreach ($names as $name) {
            $accounts[] = Account::factory()->create([
                'company_id' => $company->id,
                'parent_id' => $pool->id,
                'code' => $code,
                'name' => $name,
            ]);
        }

        return $accounts;
    }

    /**
     * journal_entries has no factory that matches its real (2026-08-24 engine) schema — the
     * stock JournalEntryFactory still writes a pre-engine 'entry_date'/'user_id' shape. Insert
     * the minimal real NOT-NULL columns directly rather than fight a factory this lane does not
     * own.
     */
    private function postOneJournalRow(int $companyId, int $accountId): void
    {
        DB::table('journal_entries')->insert([
            'company_id' => $companyId,
            'account_id' => $accountId,
            'name' => 'CT-A4b fixture row',
            'description' => 'CT-A4b fixture row',
            'transaction_date' => now(),
            'debit' => 10,
            'credit' => 0,
            'amount' => 10,
            'currency' => 'KWD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function lastRunId(int $companyId): string
    {
        return (string) DB::table('coa_linkage_changes')
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->value('run_id');
    }

    public function test_report_lists_every_duplicate_group_and_writes_nothing(): void
    {
        $company = Company::factory()->create();

        [$a, $b] = $this->makeDuplicateGroup($company, '4444', ['Dup A', 'Dup B']);

        // $this->artisan()->expectsOutputToContain(), never Artisan::call()+Artisan::output(): see
        // tests/Feature/Accounting/EnsureSystemLeavesTest.php's own docblock for this codebase's
        // documented Tests\TestCase::setUp() console-mock-rebinding quirk.
        $this->artisan('accounting:coa-duplicates', ['--company' => (string) $company->id, '--report' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain("code '4444':")
            ->expectsOutputToContain('Dup A')
            ->expectsOutputToContain('Dup B');

        $this->assertSame('4444', $a->fresh()->code);
        $this->assertSame('4444', $b->fresh()->code);
        $this->assertSame(0, DB::table('coa_linkage_changes')->where('company_id', $company->id)->count());
    }

    public function test_renumber_dry_run_shows_the_plan_and_writes_nothing(): void
    {
        $company = Company::factory()->create();

        [$a, $b] = $this->makeDuplicateGroup($company, '4444', ['Dup A', 'Dup B']);

        $this->artisan('accounting:coa-duplicates', [
            '--company' => (string) $company->id,
            '--renumber' => true,
            '--dry-run' => true,
        ])
            ->assertExitCode(0)
            ->expectsOutputToContain('would renumber');

        $this->assertSame('4444', $a->fresh()->code, 'dry-run must not write');
        $this->assertSame('4444', $b->fresh()->code, 'dry-run must not write');
        $this->assertSame(0, DB::table('coa_linkage_changes')->where('company_id', $company->id)->count());
    }

    /**
     * The two dup-group fixture required by the brief: one group with two zero-activity
     * accounts (renumber the higher id, keep the code on the lower id), one group where one
     * member carries journal activity (renumber the OTHER member, never the one with activity).
     */
    public function test_renumber_only_touches_zero_activity_accounts_and_records_a_rollback_able_change(): void
    {
        $company = Company::factory()->create();

        // Group 1: both zero-activity.
        [$a, $b] = $this->makeDuplicateGroup($company, '4444', ['Dup A', 'Dup B']);

        // Group 2: one member has journal activity.
        [$c, $d] = $this->makeDuplicateGroup($company, '5555', ['Active C', 'Idle D']);
        $this->postOneJournalRow($company->id, $c->id);

        $exit = Artisan::call('accounting:coa-duplicates', [
            '--company' => (string) $company->id,
            '--renumber' => true,
        ]);

        $this->assertSame(0, $exit, 'Both groups here are fully resolvable — exit must be 0.');

        // Group 1: lower id (a) keeps '4444', higher id (b) is renumbered off it.
        $this->assertSame('4444', $a->fresh()->code);
        $this->assertNotSame('4444', $b->fresh()->code);

        // Group 2: the account WITH activity keeps '5555'; the idle one is moved.
        $this->assertSame('5555', $c->fresh()->code);
        $this->assertNotSame('5555', $d->fresh()->code);

        // No two accounts share a code any more.
        $duplicates = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereNull('deleted_at')
            ->pluck('code')
            ->countBy()
            ->filter(fn (int $n) => $n > 1);
        $this->assertTrue($duplicates->isEmpty(), 'duplicates remaining: '.$duplicates->keys()->implode(', '));

        // A rollback-able before-image was recorded for both renumbers, under one run id.
        $runId = $this->lastRunId($company->id);
        $this->assertNotSame('', $runId);

        $changes = DB::table('coa_linkage_changes')->where('run_id', $runId)->get();
        $this->assertCount(2, $changes);
        foreach ($changes as $change) {
            $this->assertSame('accounts', $change->subject_table);
            $this->assertSame('code', $change->column_name);
        }

        // Rollback reuses accounting:coa-linkage's OWN mechanism — not a second one.
        $rollbackExit = Artisan::call('accounting:coa-linkage', ['--rollback' => $runId]);
        $this->assertSame(0, $rollbackExit);

        $this->assertSame('4444', $b->fresh()->code, 'rollback must restore the renumbered code');
        $this->assertSame('5555', $d->fresh()->code, 'rollback must restore the renumbered code');
    }

    public function test_a_group_where_every_member_carries_activity_is_reported_never_renumbered(): void
    {
        $company = Company::factory()->create();

        [$a, $b] = $this->makeDuplicateGroup($company, '6666', ['Both Active A', 'Both Active B']);
        $this->postOneJournalRow($company->id, $a->id);
        $this->postOneJournalRow($company->id, $b->id);

        $this->artisan('accounting:coa-duplicates', [
            '--company' => (string) $company->id,
            '--renumber' => true,
        ])
            ->assertExitCode(1)
            ->expectsOutputToContain('REPORTED, NOT RENUMBERED');

        // Neither account was touched.
        $this->assertSame('6666', $a->fresh()->code);
        $this->assertSame('6666', $b->fresh()->code);
        $this->assertSame(0, DB::table('coa_linkage_changes')->where('company_id', $company->id)->count());
    }
}
