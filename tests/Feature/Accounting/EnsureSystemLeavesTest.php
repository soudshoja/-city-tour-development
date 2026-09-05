<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\Company;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Support\AccountingTestCase;

/**
 * KEY: leaves+seeders. Covers task D: `accounting:ensure-system-leaves` backfills the leaves a
 * company's chart predates — 4132 "Markup Income", 2201 "Salaries & Wages Payable" (W1.3), and
 * (W2.1, residual 1) 5144 "KNET Charges" / 5145 "uPayment Charges" — via
 * App\Services\Accounting\AccountService::create() (never Account::create() directly), then
 * re-runs SystemAccountsSeeder's mapping for MARKUP_INCOME/SALARY_PAYABLE/
 * GATEWAY_FEE_EXPENSE_KNET/GATEWAY_FEE_EXPENSE_UPAYMENT.
 *
 * "Old company" is simulated by running the REAL, CURRENT CoaSeeder + SystemAccountsSeeder (per
 * this build's SEEDERS-IN-TESTS rule) and then deleting the four new leaves and their
 * system_accounts rows — reproducing "a company created before these leaves existed" without
 * needing a heavyweight git-history snapshot of an older CoaSeeder.
 *
 * Every assertion below is a direct SELECT against accounts/system_accounts, never a console
 * -output-string match: Tests\TestCase::setUp() runs `$this->artisan('db:seed', ['--class' =>
 * 'PermissionSeeder'])` for every RefreshDatabase test (which this class needs, via
 * AccountingTestCase), and that call permanently rebinds `Illuminate\Console\OutputStyle` in the
 * container to a fixed Mockery buffer for the rest of the test (Laravel's
 * InteractsWithConsole::mockConsoleOutput(), `$this->app->bind(OutputStyle::class, ...)` is never
 * unbound) — every LATER `Command::run()` in the same test, including ones invoked through the
 * `Artisan::call()` facade, resolves that same stale mock instead of the real buffer `Artisan::
 * output()` reads back from, so `Artisan::output()` reads empty here regardless of what this
 * command actually printed. DB state, not console text, is this suite's proof.
 */
class EnsureSystemLeavesTest extends AccountingTestCase
{
    private function makeOldCompany(): Company
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder())->run();

        // Simulate a company whose chart predates the four leaves this command backfills: remove
        // them and their system_accounts mappings, leaving every other seeded account untouched.
        $markup = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '4132')->firstOrFail();
        $salaryPayable = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '2201')->firstOrFail();
        $knetCharges = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '5144')->firstOrFail();
        $upaymentCharges = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '5145')->firstOrFail();

        DB::table('system_accounts')
            ->where('company_id', $company->id)
            ->whereIn('purpose_code', ['MARKUP_INCOME', 'SALARY_PAYABLE', 'GATEWAY_FEE_EXPENSE_KNET', 'GATEWAY_FEE_EXPENSE_UPAYMENT'])
            ->delete();

        Account::withoutGlobalScopes()->whereIn('id', [$markup->id, $salaryPayable->id, $knetCharges->id, $upaymentCharges->id])->delete();

        return $company;
    }

    /**
     * The REAL pre-W1.3-shaped chart (residual #15 fix): makeOldCompany() above simulates a
     * company missing the two new leaves, but leaves "Gateway Fee Recovery" at its CURRENT,
     * already-fixed code '4131' — so it never exercises the actual duplicate-code shape residuals
     * #1/#2 lived in ('Gateway Fee Recovery' sharing code '4130' with its own parent, 'Commission
     * & Service Fee Income', exactly as the pre-task-A CoaSeeder produced). This helper additionally
     * resets that code back to '4130', reproducing the real old shape end to end.
     */
    /**
     * RESIDUAL R-4 FIX (W2.2) fixture: a company whose 'Payment Gateway Charges' (5140) has NEVER
     * been split into per-gateway children — the Tap/MyFatoorah/Hesabe children CoaSeeder normally
     * seeds are removed too, leaving the pool a genuinely bare leaf — then a single
     * SystemAccountsSeeder run maps ALL FIVE GATEWAY_FEE_EXPENSE_* purpose codes onto the pool
     * itself (resolveGatewayFeeExpense()'s "pool has no children at all" branch), reproducing the
     * lead's own SL-H2 counterexample exactly.
     */
    private function makeCompanyWithBareGatewayFeePool(): Company
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);

        $pool = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', 'Payment Gateway Charges')
            ->firstOrFail();

        Account::withoutGlobalScopes()->where('parent_id', $pool->id)->delete();

        (new SystemAccountsSeeder())->run();

        return $company;
    }

    private function makeOldCompanyWithDuplicateGatewayFeeCode(): Company
    {
        $company = $this->makeOldCompany();

        DB::table('accounts')
            ->where('company_id', $company->id)
            ->where('name', 'Gateway Fee Recovery')
            ->update(['code' => '4130']);

        return $company;
    }

    /**
     * W3-prereq lane A fixture: a company whose "Ferry Booking Revenue" leaf (and its
     * SERVICE_REVENUE/ferry mapping) predates CoaSeeder seeding one — the exact legacy shape
     * `accounting:ensure-system-leaves` now backfills. Every other Booking Revenue leaf (Flight,
     * Hotel, and the other nine non-flight/hotel types) stays intact, same "surgically remove only
     * what's being tested" pattern as makeOldCompany() itself.
     */
    private function makeOldCompanyMissingFerryBookingRevenue(): Company
    {
        $company = $this->makeOldCompany();

        $ferryRevenue = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', 'Ferry Booking Revenue')
            ->firstOrFail();

        DB::table('system_accounts')
            ->where('company_id', $company->id)
            ->where('purpose_code', 'SERVICE_REVENUE')
            ->where('service_type', 'ferry')
            ->delete();

        Account::withoutGlobalScopes()->where('id', $ferryRevenue->id)->delete();

        return $company;
    }

    public function test_dry_run_creates_nothing(): void
    {
        $company = $this->makeOldCompany();

        $this->assertSame(0, Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '4132')->count());
        $this->assertSame(0, Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '2201')->count());

        Artisan::call('accounting:ensure-system-leaves', ['--company' => $company->id, '--dry-run' => true]);

        $this->assertSame(
            0,
            Account::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'Markup Income')->count(),
            '--dry-run must not write any account row.'
        );
        $this->assertSame(
            0,
            Account::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'Salaries & Wages Payable')->count()
        );
        $this->assertSame(
            0,
            DB::table('system_accounts')->where('company_id', $company->id)->whereIn('purpose_code', ['MARKUP_INCOME', 'SALARY_PAYABLE'])->count(),
            '--dry-run must not write any system_accounts row either.'
        );
    }

    public function test_first_run_creates_and_maps_all_four_leaves_second_run_creates_nothing(): void
    {
        $company = $this->makeOldCompany();
        $this->trackCompanyForInvariants($company->id);

        // ── First run: creates all four leaves via AccountService::createSystemLeaf(), then re-maps. ──
        Artisan::call('accounting:ensure-system-leaves', ['--company' => $company->id]);

        $markup = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', 'Markup Income')
            ->first();
        $salaryPayable = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', 'Salaries & Wages Payable')
            ->first();
        $knetCharges = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', 'KNET Charges')
            ->first();
        $upaymentCharges = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', 'uPayment Charges')
            ->first();

        $this->assertNotNull($markup, 'AccountService::createSystemLeaf() must have created the Markup Income leaf.');
        $this->assertNotNull($salaryPayable, 'AccountService::createSystemLeaf() must have created the Salaries & Wages Payable leaf.');
        $this->assertNotNull($knetCharges, 'AccountService::createSystemLeaf() must have created the KNET Charges leaf (residual 1).');
        $this->assertNotNull($upaymentCharges, 'AccountService::createSystemLeaf() must have created the uPayment Charges leaf (residual 1).');
        $this->assertSame('Commission & Service Fee Income', $markup->parent->name);
        $this->assertSame('Income', $markup->root->name);
        $this->assertSame('Accrued Expenses', $salaryPayable->parent->name);
        $this->assertSame('Liabilities', $salaryPayable->root->name);
        $this->assertSame('Payment Gateway Charges', $knetCharges->parent->name);
        $this->assertSame('Payment Gateway Charges', $upaymentCharges->parent->name);
        $this->assertSame('Expenses', $knetCharges->root->name);
        $this->assertSame('Expenses', $upaymentCharges->root->name);
        $this->assertFalse((bool) $markup->is_group, 'A newly created leaf via AccountService::createSystemLeaf() must be a leaf (is_group=false), never a group.');
        $this->assertFalse((bool) $knetCharges->is_group);
        $this->assertFalse((bool) $upaymentCharges->is_group);

        // HIGH #2 fix proof: createSystemLeaf() must land on the USER-DECIDED codes exactly, not
        // whatever AccountCodeGenerator's max(numeric sibling)+1 would have produced (2231 for an
        // existing company whose "Accrued Expenses" already has 2210/2220/2230).
        $this->assertSame('4132', $markup->code, 'USER DECISION 2026-08-27: Markup Income must be code 4132, even for an existing company.');
        $this->assertSame('2201', $salaryPayable->code, 'USER DECISION 2026-08-27: Salaries & Wages Payable must be code 2201, even for an existing company (HIGH #2 — AccountCodeGenerator would otherwise land on 2231).');
        $this->assertSame('5144', $knetCharges->code, "PATTERN NAME: 'KNET Charges' must be code 5144, even for an existing company.");
        $this->assertSame('5145', $upaymentCharges->code, "PATTERN NAME: 'uPayment Charges' must be code 5145, even for an existing company.");

        // SELECT proof: exactly one row of each name for this company (no duplicate creation).
        $this->assertSame(1, Account::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'Markup Income')->count());
        $this->assertSame(1, Account::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'Salaries & Wages Payable')->count());
        $this->assertSame(1, Account::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'KNET Charges')->count());
        $this->assertSame(1, Account::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'uPayment Charges')->count());
        $this->assertNoDuplicateAccountCodes($company->id);

        // Mapping re-run proof.
        $mappedMarkup = DB::table('system_accounts')
            ->where('company_id', $company->id)
            ->where('purpose_code', 'MARKUP_INCOME')
            ->value('account_id');
        $mappedSalaryPayable = DB::table('system_accounts')
            ->where('company_id', $company->id)
            ->where('purpose_code', 'SALARY_PAYABLE')
            ->value('account_id');
        $mappedKnet = DB::table('system_accounts')
            ->where('company_id', $company->id)
            ->where('purpose_code', 'GATEWAY_FEE_EXPENSE_KNET')
            ->value('account_id');
        $mappedUpayment = DB::table('system_accounts')
            ->where('company_id', $company->id)
            ->where('purpose_code', 'GATEWAY_FEE_EXPENSE_UPAYMENT')
            ->value('account_id');

        $this->assertSame($markup->id, $mappedMarkup, 'MARKUP_INCOME must now be mapped to the newly created leaf.');
        $this->assertSame($salaryPayable->id, $mappedSalaryPayable, 'SALARY_PAYABLE must now be mapped to the newly created leaf.');
        $this->assertSame($knetCharges->id, $mappedKnet, 'GATEWAY_FEE_EXPENSE_KNET must now be mapped to the newly created leaf (residual 1).');
        $this->assertSame($upaymentCharges->id, $mappedUpayment, 'GATEWAY_FEE_EXPENSE_UPAYMENT must now be mapped to the newly created leaf (residual 1).');

        // ── Second run: idempotent — creates nothing, no duplicate accounts or mappings. ──
        Artisan::call('accounting:ensure-system-leaves', ['--company' => $company->id]);

        $this->assertSame(
            1,
            Account::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'Markup Income')->count(),
            'Second run must not create a duplicate Markup Income leaf.'
        );
        $this->assertSame(
            1,
            Account::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'Salaries & Wages Payable')->count(),
            'Second run must not create a duplicate Salaries & Wages Payable leaf.'
        );
        $this->assertSame(
            1,
            Account::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'KNET Charges')->count(),
            'Second run must not create a duplicate KNET Charges leaf.'
        );
        $this->assertSame(
            1,
            Account::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'uPayment Charges')->count(),
            'Second run must not create a duplicate uPayment Charges leaf.'
        );
        $this->assertSame(
            1,
            DB::table('system_accounts')->where('company_id', $company->id)->where('purpose_code', 'MARKUP_INCOME')->count()
        );
        $this->assertSame(
            1,
            DB::table('system_accounts')->where('company_id', $company->id)->where('purpose_code', 'SALARY_PAYABLE')->count()
        );
        $this->assertSame(
            1,
            DB::table('system_accounts')->where('company_id', $company->id)->where('purpose_code', 'GATEWAY_FEE_EXPENSE_KNET')->count()
        );
        $this->assertSame(
            1,
            DB::table('system_accounts')->where('company_id', $company->id)->where('purpose_code', 'GATEWAY_FEE_EXPENSE_UPAYMENT')->count()
        );
    }

    public function test_every_company_default_processes_all_companies(): void
    {
        $companyA = $this->makeOldCompany();
        $companyB = $this->makeOldCompany();
        $this->trackCompanyForInvariants($companyA->id);
        $this->trackCompanyForInvariants($companyB->id);

        Artisan::call('accounting:ensure-system-leaves');

        foreach ([$companyA, $companyB] as $company) {
            $this->assertSame(
                1,
                Account::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'Markup Income')->count(),
                "Company #{$company->id} must have its Markup Income leaf backfilled by the default (no --company) run."
            );
            $this->assertSame(
                1,
                DB::table('system_accounts')->where('company_id', $company->id)->where('purpose_code', 'SALARY_PAYABLE')->count()
            );
        }
    }

    /**
     * Residual 6 fix proof (W2.1): reproduces dev's exact real-world collision shape —
     * AgentController::update()'s OWN max(sibling code)+1 generator under "Agent Profit Payable"
     * (2230), reimplemented here verbatim (AgentController.php's own logic: first leaf =
     * profitGroup.code + 1 = 2231, then max(sibling)+1 per subsequent agent), landing exactly on
     * 2240 for the 10th agent — the code "Salaries & Wages Payable" USED to be decided at before
     * this fix. With the decided code moved to 2201 (a code that generator can never reach — it
     * only ever grows upward from 2230), backfilling an old company that already has all ten
     * agent-profit leaves must succeed with zero collisions, and every other new leaf (Markup
     * Income, KNET/uPayment Charges) must land correctly alongside them.
     */
    public function test_salary_payable_leaf_does_not_collide_with_ten_agent_profit_leaves_reaching_2240(): void
    {
        $company = $this->makeOldCompany();
        $this->trackCompanyForInvariants($company->id);

        $profitGroup = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', 'Agent Profit Payable')
            ->firstOrFail();
        $this->assertSame('2230', $profitGroup->code, 'Precondition: CoaSeeder must still seed "Agent Profit Payable" at 2230.');

        // AgentController::update()'s exact generator (app/Http/Controllers/AgentController.php,
        // ~line 682): $lastProfitCode = max(sibling code); $profitCode = ($lastProfitCode ?:
        // $profitGroup->code) + 1.
        for ($i = 1; $i <= 10; $i++) {
            $lastProfitCode = DB::table('accounts')->where('parent_id', $profitGroup->id)->max('code');
            $profitCode = (string) (($lastProfitCode ? (int) $lastProfitCode : (int) $profitGroup->code) + 1);

            DB::table('accounts')->insert([
                'company_id' => $company->id,
                'parent_id' => $profitGroup->id,
                'name' => "Agent #{$i}",
                'code' => $profitCode,
                'agent_id' => null,
                'level' => $profitGroup->level + 1,
                'root_id' => $profitGroup->root_id,
                'account_type' => $profitGroup->account_type,
                'report_type' => $profitGroup->report_type,
                'is_group' => false,
                'currency' => 'KWD',
                'actual_balance' => 0,
                'opening_balance' => 0,
                'budget_balance' => 0,
                'variance' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->assertSame(
            '2240',
            (string) DB::table('accounts')->where('parent_id', $profitGroup->id)->max('code'),
            'Precondition: the 10th agent-profit leaf must land exactly on 2240 — the code "Salaries & Wages '
                .'Payable" used to be decided at.'
        );

        $exitCode = Artisan::call('accounting:ensure-system-leaves', ['--company' => $company->id]);
        $this->assertSame(0, $exitCode, 'A healthy company with 10 pre-existing agent-profit leaves must not fail.');

        $salaryPayable = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', 'Salaries & Wages Payable')
            ->first();

        $this->assertNotNull($salaryPayable, 'The leaf must still be created even with all ten agent-profit leaves present.');
        $this->assertSame(
            '2201',
            $salaryPayable->code,
            'residual 6: must land on 2201, never colliding with the agent-profit leaf already occupying 2240.'
        );
        $this->assertNoDuplicateAccountCodes($company->id);

        $mapped = DB::table('system_accounts')
            ->where('company_id', $company->id)
            ->where('purpose_code', 'SALARY_PAYABLE')
            ->value('account_id');
        $this->assertSame($salaryPayable->id, $mapped);

        // Every other new leaf still lands correctly alongside the ten agent-profit leaves.
        $this->assertSame('4132', Account::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'Markup Income')->value('code'));
        $this->assertSame('5144', Account::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'KNET Charges')->value('code'));
        $this->assertSame('5145', Account::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'uPayment Charges')->value('code'));
    }

    /**
     * RESIDUAL R-4 FIX (W2.2), superseding the residual-1 test this replaces: the old assertion
     * here was itself the bug R-4 closes — it required KNET's fallback to land on "the first leaf
     * child of the pool", oldest id first, with NO check on what that child was named. In THIS
     * exact scenario (Tap/MyFatoorah/Hesabe children present, KNET's own missing) that always
     * meant KNET's fee silently posted to one of the OTHER three gateways' own dedicated accounts.
     * The fallback now excludes any child whose name identifies a DIFFERENT gateway; with every
     * remaining child gateway-named and no pre-existing mapping onto the pool itself to preserve
     * (makeOldCompany() deleted GATEWAY_FEE_EXPENSE_KNET's row along with the leaf), there is no
     * safe target left — the correct outcome is a reported gap, not a guess.
     */
    public function test_gateway_fee_expense_skips_rather_than_falling_back_to_another_gateways_child(): void
    {
        // makeOldCompany() removes the KNET Charges (5144) and uPayment Charges (5145) leaves but
        // leaves Tap/MyFatoorah/Hesabe children in place — a genuinely PARTIAL split (3 of 5
        // gateways have a dedicated child), not the "pool has zero children" shape the bare-pool
        // branch already handles.
        $company = $this->makeOldCompany();

        $this->assertNull(
            Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '5144')->first(),
            'Precondition: makeOldCompany() must have removed the KNET Charges leaf.'
        );

        (new SystemAccountsSeeder())->run();

        $mappedKnet = DB::table('system_accounts')
            ->where('company_id', $company->id)
            ->where('purpose_code', 'GATEWAY_FEE_EXPENSE_KNET')
            ->value('account_id');

        $this->assertNull(
            $mappedKnet,
            'R-4: with no dedicated KNET child, no neutral (non-gateway-named) child, and no pre-existing '
            .'mapping onto the pool to preserve, GATEWAY_FEE_EXPENSE_KNET must be reported as a gap, never '
            ."silently pointed at another gateway's own dedicated child (Tap/MyFatoorah/Hesabe Charges)."
        );

        // None of the OTHER gateways' own dedicated children were disturbed by KNET's gap.
        foreach (['TAP', 'MYFATOORAH', 'HESABE'] as $code) {
            $this->assertNotNull(
                DB::table('system_accounts')
                    ->where('company_id', $company->id)
                    ->where('purpose_code', "GATEWAY_FEE_EXPENSE_{$code}")
                    ->value('account_id'),
                "GATEWAY_FEE_EXPENSE_{$code} must still be mapped to its own dedicated child."
            );
        }
    }

    /**
     * RESIDUAL R-4 FIX (W2.2) — the LEAD's own acceptance scenario. 'Payment Gateway Charges' is a
     * genuinely BARE leaf (no per-gateway split has ever happened for this company), so ALL FIVE
     * gateways' fee mappings validly point at the pool itself. Backfilling KNET's and uPayment's
     * own dedicated children (via `ensure-system-leaves`) and re-running SystemAccountsSeeder must
     * move ONLY KNET and uPayment onto their brand-new children — MyFatoorah/Hesabe/Tap, which
     * still have no dedicated child of their own, must stay exactly where they already validly
     * were (the pool), never silently re-pointed onto KNET's or uPayment's new leaf the way the
     * pre-fix "any leaf" fallback did (this is the exact shape the lead's SL-H2 counterfactual
     * measured as "5 of 5 purpose codes silently re-pointed").
     */
    public function test_bare_pool_gateways_without_a_dedicated_child_stay_on_the_pool_after_leaves_created(): void
    {
        $company = $this->makeCompanyWithBareGatewayFeePool();
        $this->trackCompanyForInvariants($company->id);

        $pool = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', 'Payment Gateway Charges')
            ->firstOrFail();

        foreach (['MYFATOORAH', 'HESABE', 'TAP', 'KNET', 'UPAYMENT'] as $code) {
            $this->assertSame(
                $pool->id,
                DB::table('system_accounts')->where('company_id', $company->id)->where('purpose_code', "GATEWAY_FEE_EXPENSE_{$code}")->value('account_id'),
                "Precondition: GATEWAY_FEE_EXPENSE_{$code} must start out mapped to the bare pool itself."
            );
        }

        Artisan::call('accounting:ensure-system-leaves', ['--company' => $company->id]);

        $knetCharges = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '5144')->firstOrFail();
        $upaymentCharges = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '5145')->firstOrFail();

        // KNET/uPayment moved onto their own new dedicated children.
        $this->assertSame(
            $knetCharges->id,
            DB::table('system_accounts')->where('company_id', $company->id)->where('purpose_code', 'GATEWAY_FEE_EXPENSE_KNET')->value('account_id'),
            'GATEWAY_FEE_EXPENSE_KNET must move onto its own newly created dedicated child.'
        );
        $this->assertSame(
            $upaymentCharges->id,
            DB::table('system_accounts')->where('company_id', $company->id)->where('purpose_code', 'GATEWAY_FEE_EXPENSE_UPAYMENT')->value('account_id'),
            'GATEWAY_FEE_EXPENSE_UPAYMENT must move onto its own newly created dedicated child.'
        );

        // MyFatoorah/Hesabe/Tap stayed on the pool — R-4: never silently re-pointed onto a
        // DIFFERENT gateway's brand-new dedicated child.
        foreach (['MYFATOORAH', 'HESABE', 'TAP'] as $code) {
            $this->assertSame(
                $pool->id,
                DB::table('system_accounts')->where('company_id', $company->id)->where('purpose_code', "GATEWAY_FEE_EXPENSE_{$code}")->value('account_id'),
                "R-4: GATEWAY_FEE_EXPENSE_{$code} has no dedicated child of its own yet and must stay on the pool, never move onto a DIFFERENT gateway's child."
            );
        }
    }

    /**
     * R-a fix (W2b). `EnsureSystemLeaves::handle()` called `(new SystemAccountsSeeder())->run()`
     * with no `setCommand($this)` — `Illuminate\Database\Seeder::$command` has no default and
     * `SystemAccountsSeeder::info()/warn()/line()` are all
     * `$this->command?->getOutput()?->writeln(...)`, so the null-safe operator silently
     * discarded every CHANGED/MAPPED/SKIPPED line the seeder tried to print on the only path
     * that calls it from this command — exit 0, a purpose code silently re-pointed, not one
     * printed word. This fixture (same one as the test above) moves exactly two purpose codes
     * (KNET, uPayment) and must now print a CHANGED line for each.
     *
     * Uses `$this->artisan()` (Laravel's own mocked-console-output test helper) rather than
     * `Artisan::call()` — see `AccountingEngineCommandTest`'s own docblock for why:
     * `Tests\TestCase::setUp()`'s `db:seed --class=PermissionSeeder` call permanently rebinds
     * `OutputStyle::class` to a Mockery buffer for the rest of the test, and only
     * `$this->artisan()->expectsOutputToContain()` reads that same rebound buffer back.
     */
    public function test_gateway_leaf_remap_prints_a_changed_line_for_every_purpose_code_it_moves(): void
    {
        $company = $this->makeCompanyWithBareGatewayFeePool();
        $this->trackCompanyForInvariants($company->id);

        $this->artisan('accounting:ensure-system-leaves', ['--company' => $company->id])
            ->assertExitCode(0)
            ->expectsOutputToContain('CHANGED')
            ->expectsOutputToContain('GATEWAY_FEE_EXPENSE_KNET')
            ->expectsOutputToContain('GATEWAY_FEE_EXPENSE_UPAYMENT');
    }

    /**
     * TASK 3 (COA blocker fix, 2026-08-31), mirrors
     * test_first_run_creates_and_maps_all_four_leaves_second_run_creates_nothing above for the
     * GATEWAY_CLEARING side: 'Knet' (1311) / 'uPayment' (1312) are OPTIONAL leaves this command
     * backfills as children of the 'Payment Gateway' (1300, Assets) pool. Unlike the fee-expense
     * pair, CoaSeeder never seeds these for a fresh company either (task 3's own scope is
     * EnsureSystemLeaves only), so a plain makeOldCompany() already lacks them — no special
     * fixture deletion needed. Before the backfill, GATEWAY_CLEARING_KNET/UPAYMENT resolve onto
     * the bare pool itself (SystemAccountsSeeder's own bare-pool branch); after it, they must move
     * onto their own new dedicated leaves, while MyFatoorah/Hesabe/Tap — which still have no
     * dedicated child of their own — stay safely mapped to the pool (the "already mapped to pool"
     * preserve rule resolveGatewayClearing() now carries, mirroring GATEWAY_FEE_EXPENSE's own R-4
     * fix one pool family over).
     */
    public function test_creates_and_maps_gateway_clearing_knet_and_upayment_leaves(): void
    {
        $company = $this->makeOldCompany();
        $this->trackCompanyForInvariants($company->id);

        $this->assertSame(
            0,
            Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1311')->count(),
            'Precondition: CoaSeeder never seeds a dedicated Knet clearing child.'
        );
        $this->assertSame(
            0,
            Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1312')->count(),
            'Precondition: CoaSeeder never seeds a dedicated uPayment clearing child.'
        );

        $pool = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', 'Payment Gateway')
            ->where('level', 2)
            ->firstOrFail();

        $this->assertSame(
            $pool->id,
            DB::table('system_accounts')->where('company_id', $company->id)->where('purpose_code', 'GATEWAY_CLEARING_KNET')->value('account_id'),
            'Precondition: before the backfill, GATEWAY_CLEARING_KNET resolves onto the bare pool.'
        );
        $this->assertSame(
            $pool->id,
            DB::table('system_accounts')->where('company_id', $company->id)->where('purpose_code', 'GATEWAY_CLEARING_UPAYMENT')->value('account_id'),
            'Precondition: before the backfill, GATEWAY_CLEARING_UPAYMENT resolves onto the bare pool.'
        );

        Artisan::call('accounting:ensure-system-leaves', ['--company' => $company->id]);

        $knet = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'Knet')->first();
        $upayment = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'uPayment')->first();

        $this->assertNotNull($knet, 'AccountService::createSystemLeaf() must have created the Knet clearing leaf.');
        $this->assertNotNull($upayment, 'AccountService::createSystemLeaf() must have created the uPayment clearing leaf.');
        $this->assertSame('Payment Gateway', $knet->parent->name);
        $this->assertSame('Assets', $knet->root->name);
        $this->assertSame('Payment Gateway', $upayment->parent->name);
        $this->assertSame('Assets', $upayment->root->name);
        $this->assertSame('1311', $knet->code);
        $this->assertSame('1312', $upayment->code);
        $this->assertFalse((bool) $knet->is_group, 'A newly created leaf via AccountService::createSystemLeaf() must be a leaf (is_group=false), never a group.');
        $this->assertFalse((bool) $upayment->is_group);
        $this->assertNoDuplicateAccountCodes($company->id);

        $mappedKnet = DB::table('system_accounts')->where('company_id', $company->id)->where('purpose_code', 'GATEWAY_CLEARING_KNET')->value('account_id');
        $mappedUpayment = DB::table('system_accounts')->where('company_id', $company->id)->where('purpose_code', 'GATEWAY_CLEARING_UPAYMENT')->value('account_id');

        $this->assertSame($knet->id, $mappedKnet, 'GATEWAY_CLEARING_KNET must move off the bare pool onto its own new dedicated leaf.');
        $this->assertSame($upayment->id, $mappedUpayment, 'GATEWAY_CLEARING_UPAYMENT must move off the bare pool onto its own new dedicated leaf.');

        // MyFatoorah/Hesabe/Tap have no dedicated clearing child of their own and must stay
        // exactly where they already validly were (the pool) — never silently disturbed by
        // Knet/uPayment's own brand-new children landing in the same pool.
        foreach (['MYFATOORAH', 'HESABE', 'TAP'] as $code) {
            $this->assertSame(
                $pool->id,
                DB::table('system_accounts')->where('company_id', $company->id)->where('purpose_code', "GATEWAY_CLEARING_{$code}")->value('account_id'),
                "GATEWAY_CLEARING_{$code} has no dedicated child of its own and must stay mapped to the pool."
            );
        }

        // Idempotent second run: creates nothing further.
        Artisan::call('accounting:ensure-system-leaves', ['--company' => $company->id]);
        $this->assertSame(
            1,
            Account::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'Knet')->count(),
            'Second run must not create a duplicate Knet clearing leaf.'
        );
        $this->assertSame(
            1,
            Account::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'uPayment')->count(),
            'Second run must not create a duplicate uPayment clearing leaf.'
        );
    }

    /**
     * RESIDUAL R-3 FIX (W2.2) acceptance test — the LEAD's own scenario: a chart with NO 'Payment
     * Gateway Charges' (5140) pool at all (the KNET/uPayment leaves are OPTIONAL/best-effort,
     * since their parent is not guaranteed to exist on a legacy chart), but WITH the two CORE
     * leaves' own parents intact ('Commission & Service Fee Income', 'Accrued Expenses'). The
     * command must still create/verify both core leaves inside the SAME per-company transaction
     * the optional gateway leaves fail in — a missing optional pool must never roll back the core
     * leaves — and exit SUCCESS, not FAILURE (see EnsureSystemLeaves::handle()'s own docblock for
     * why SUCCESS, not a distinct exit code, was chosen).
     */
    public function test_missing_gateway_pool_skips_optional_leaves_but_still_creates_core_leaves(): void
    {
        $company = $this->makeOldCompany();
        $this->trackCompanyForInvariants($company->id);

        // Strip the ENTIRE 'Payment Gateway Charges' pool (and every child under it) — the
        // legacy-company shape R-3 targets: a chart with no gateway-charge pool at all, not merely
        // one missing its KNET/uPayment children.
        $pool = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', 'Payment Gateway Charges')
            ->first();
        $this->assertNotNull($pool, 'Precondition: makeOldCompany() must still have the pool before this test strips it.');

        $childIds = Account::withoutGlobalScopes()->where('parent_id', $pool->id)->pluck('id');

        // makeOldCompany() already ran SystemAccountsSeeder once, so the pool's Tap/MyFatoorah/
        // Hesabe children are already referenced by system_accounts rows (foreign key on
        // account_id) — those mapping rows must go before the accounts they point at can be
        // deleted.
        DB::table('system_accounts')
            ->where('company_id', $company->id)
            ->whereIn('account_id', $childIds->push($pool->id))
            ->delete();

        Account::withoutGlobalScopes()->where('parent_id', $pool->id)->delete();
        $pool->delete();

        $this->assertSame(
            0,
            Account::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'Payment Gateway Charges')->count(),
            'Precondition: the pool must be fully gone before the command runs.'
        );

        $exitCode = Artisan::call('accounting:ensure-system-leaves', ['--company' => $company->id]);

        $this->assertSame(
            0,
            $exitCode,
            'R-3: a missing OPTIONAL gateway pool must not fail the whole command — the core leaves still landed.'
        );

        $markup = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '4132')->first();
        $salaryPayable = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '2201')->first();

        $this->assertNotNull($markup, 'R-3: the CORE Markup Income leaf must still be created even though the optional gateway pool is missing.');
        $this->assertNotNull($salaryPayable, 'R-3: the CORE Salaries & Wages Payable leaf must still be created even though the optional gateway pool is missing.');
        $this->assertSame('Markup Income', $markup->name);
        $this->assertSame('Salaries & Wages Payable', $salaryPayable->name);

        $mappedMarkup = DB::table('system_accounts')->where('company_id', $company->id)->where('purpose_code', 'MARKUP_INCOME')->value('account_id');
        $mappedSalaryPayable = DB::table('system_accounts')->where('company_id', $company->id)->where('purpose_code', 'SALARY_PAYABLE')->value('account_id');
        $this->assertSame($markup->id, $mappedMarkup, 'The core leaves must still be re-mapped by the post-loop SystemAccountsSeeder run.');
        $this->assertSame($salaryPayable->id, $mappedSalaryPayable, 'The core leaves must still be re-mapped by the post-loop SystemAccountsSeeder run.');

        $this->assertSame(
            0,
            Account::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'KNET Charges')->count(),
            'The optional KNET Charges leaf must NOT be created with no pool for it to live under.'
        );
        $this->assertSame(
            0,
            Account::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'uPayment Charges')->count(),
            'The optional uPayment Charges leaf must NOT be created with no pool for it to live under.'
        );
        $this->assertSame(
            0,
            DB::table('system_accounts')->where('company_id', $company->id)->where('purpose_code', 'GATEWAY_FEE_EXPENSE_KNET')->count()
        );
        $this->assertSame(
            0,
            DB::table('system_accounts')->where('company_id', $company->id)->where('purpose_code', 'GATEWAY_FEE_EXPENSE_UPAYMENT')->count()
        );
    }

    public function test_fix_duplicate_code_dry_run_writes_nothing(): void
    {
        $company = $this->makeOldCompany();

        // Simulate the pre-fix CoaSeeder bug: "Gateway Fee Recovery" carrying the same code as
        // its own parent (4130), exactly as the un-fixed seeder produced before task A.
        DB::table('accounts')
            ->where('company_id', $company->id)
            ->where('name', 'Gateway Fee Recovery')
            ->update(['code' => '4130']);

        Artisan::call('accounting:ensure-system-leaves', [
            '--company' => $company->id,
            '--dry-run' => true,
            '--fix-duplicate-code' => true,
        ]);

        $this->assertSame(
            '4130',
            DB::table('accounts')->where('company_id', $company->id)->where('name', 'Gateway Fee Recovery')->value('code'),
            '--dry-run must not actually renumber anything.'
        );
    }

    public function test_fix_duplicate_code_renumbers_4130_to_4131_only_when_flag_passed(): void
    {
        $company = $this->makeOldCompany();
        $this->trackCompanyForInvariants($company->id);

        DB::table('accounts')
            ->where('company_id', $company->id)
            ->where('name', 'Gateway Fee Recovery')
            ->update(['code' => '4130']);

        // Without the flag: no renumbering happens.
        Artisan::call('accounting:ensure-system-leaves', ['--company' => $company->id]);
        $this->assertSame(
            '4130',
            DB::table('accounts')->where('company_id', $company->id)->where('name', 'Gateway Fee Recovery')->value('code'),
            'Without --fix-duplicate-code, the duplicate code must be left untouched.'
        );

        // With the flag: renumbered to 4131.
        Artisan::call('accounting:ensure-system-leaves', ['--company' => $company->id, '--fix-duplicate-code' => true]);

        $this->assertSame(
            '4131',
            DB::table('accounts')->where('company_id', $company->id)->where('name', 'Gateway Fee Recovery')->value('code'),
            '--fix-duplicate-code must renumber the duplicate-code child to 4131.'
        );
    }

    /**
     * HIGH #1 fix, positive proof, "intended order" (residual #15 fix — a REAL pre-W1.3-shaped
     * chart: "Gateway Fee Recovery" duplicating its own parent's code '4130', neither new leaf
     * present). --fix-duplicate-code passed on the ONE AND ONLY run. Must land on exactly
     * 4131/4132/2201 with no new duplicate code — the outcome §5 of the W1.3 lead report calls
     * the "only safe ordering."
     */
    public function test_intended_order_fix_duplicate_code_on_first_run_ends_correct(): void
    {
        $company = $this->makeOldCompanyWithDuplicateGatewayFeeCode();
        $this->trackCompanyForInvariants($company->id);

        $this->assertSame(
            '4130',
            DB::table('accounts')->where('company_id', $company->id)->where('name', 'Gateway Fee Recovery')->value('code'),
            'Precondition: the real old shape must have Gateway Fee Recovery duplicating its own parent\'s code 4130.'
        );

        Artisan::call('accounting:ensure-system-leaves', ['--company' => $company->id, '--fix-duplicate-code' => true]);

        $this->assertSame('4131', DB::table('accounts')->where('company_id', $company->id)->where('name', 'Gateway Fee Recovery')->value('code'));
        $this->assertSame('4132', DB::table('accounts')->where('company_id', $company->id)->where('name', 'Markup Income')->value('code'));
        $this->assertSame('2201', DB::table('accounts')->where('company_id', $company->id)->where('name', 'Salaries & Wages Payable')->value('code'));

        // No duplicate code anywhere for this company other than the one pre-existing, explicitly
        // deferred CoaSeeder pair (code 2130) — the exact class of defect HIGH #1 was.
        $this->assertNoDuplicateAccountCodes($company->id);
        $this->addToAssertionCount(1);
    }

    /**
     * HIGH #1 + HIGH #2 fix, positive proof, "default order" — the ordering the lead report
     * documented as the one that USED TO create a collision (bare run first creating "Markup
     * Income" at the generator's next-free code 4131, THEN --fix-duplicate-code trying to also put
     * "Gateway Fee Recovery" at 4131). With createSystemLeaf()'s explicit codes, "Markup Income"
     * can never land anywhere but 4132, so the follow-up renumber never collides — both orderings
     * converge on the same correct end state.
     */
    public function test_default_order_bare_run_then_fix_duplicate_code_ends_correct(): void
    {
        $company = $this->makeOldCompanyWithDuplicateGatewayFeeCode();
        $this->trackCompanyForInvariants($company->id);

        // Bare run first — the DEFAULT, --fix-duplicate-code is OFF.
        Artisan::call('accounting:ensure-system-leaves', ['--company' => $company->id]);

        $this->assertSame(
            '4130',
            DB::table('accounts')->where('company_id', $company->id)->where('name', 'Gateway Fee Recovery')->value('code'),
            'Without the flag, Gateway Fee Recovery must be left exactly as found.'
        );
        $this->assertSame('4132', DB::table('accounts')->where('company_id', $company->id)->where('name', 'Markup Income')->value('code'));
        $this->assertSame('2201', DB::table('accounts')->where('company_id', $company->id)->where('name', 'Salaries & Wages Payable')->value('code'));

        // Follow-up run with the flag.
        Artisan::call('accounting:ensure-system-leaves', ['--company' => $company->id, '--fix-duplicate-code' => true]);

        $this->assertSame('4131', DB::table('accounts')->where('company_id', $company->id)->where('name', 'Gateway Fee Recovery')->value('code'));
        $this->assertSame('4132', DB::table('accounts')->where('company_id', $company->id)->where('name', 'Markup Income')->value('code'));
        $this->assertSame('2201', DB::table('accounts')->where('company_id', $company->id)->where('name', 'Salaries & Wages Payable')->value('code'));

        $this->assertNoDuplicateAccountCodes($company->id);
        $this->addToAssertionCount(1);
    }

    /**
     * HIGH #1 fix, negative proof (the exact test the fix instructions asked for): code 4131 is
     * ALREADY occupied by a different account when --fix-duplicate-code runs. Must refuse with no
     * change, never silently create a second account at 4131.
     */
    public function test_fix_duplicate_code_refuses_when_4131_is_already_taken_by_another_account(): void
    {
        $company = $this->makeOldCompanyWithDuplicateGatewayFeeCode();

        // Occupy 4131 with an unrelated account before the fix runs.
        $occupier = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'Gateway Fee Recovery')->firstOrFail();
        DB::table('accounts')->insert([
            'company_id' => $company->id,
            'parent_id' => $occupier->parent_id,
            'name' => 'Some Other Leaf',
            'code' => '4131',
            'level' => $occupier->level,
            'root_id' => $occupier->root_id,
            'account_type' => $occupier->account_type,
            'report_type' => $occupier->report_type,
            'is_group' => false,
            'currency' => 'KWD',
            'actual_balance' => 0,
            'opening_balance' => 0,
            'budget_balance' => 0,
            'variance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Artisan::call('accounting:ensure-system-leaves', ['--company' => $company->id, '--fix-duplicate-code' => true]);

        $this->assertSame(
            '4130',
            DB::table('accounts')->where('company_id', $company->id)->where('name', 'Gateway Fee Recovery')->value('code'),
            'A collision on 4131 must refuse the renumber, leaving Gateway Fee Recovery exactly as it was.'
        );
        $this->assertSame(
            1,
            DB::table('accounts')->where('company_id', $company->id)->where('code', '4131')->count(),
            'The refusal must not create a second account at 4131 — only the pre-existing occupier remains.'
        );
    }

    /**
     * Per-company isolation fix (task 4): a company whose chart already has a DIFFERENT account
     * occupying code '2201' (a real, if unusual, chart-shape conflict) must fail and roll back
     * ONLY itself — including the "Markup Income" leaf that would otherwise have been created
     * successfully before the failure — while a healthy sibling company processed in the same
     * default (no --company) run must still get both its leaves.
     */
    public function test_a_failure_on_one_company_does_not_block_a_healthy_sibling_company(): void
    {
        $brokenCompany = $this->makeOldCompany();
        $healthyCompany = $this->makeOldCompany();
        $this->trackCompanyForInvariants($healthyCompany->id);

        // Occupy code '2201' with an unrelated account for the broken company, so
        // createSystemLeaf()'s collision guard refuses to create "Salaries & Wages Payable" there
        // — this happens AFTER "Markup Income" (self::LEAVES' first entry) would have already
        // been created in the same per-company transaction, proving that success is rolled back
        // too.
        $accruedExpenses = Account::withoutGlobalScopes()
            ->where('company_id', $brokenCompany->id)
            ->where('name', 'Accrued Expenses')
            ->firstOrFail();
        DB::table('accounts')->insert([
            'company_id' => $brokenCompany->id,
            'parent_id' => $accruedExpenses->id,
            'name' => 'Some Other Payable',
            'code' => '2201',
            'level' => $accruedExpenses->level + 1,
            'root_id' => $accruedExpenses->root_id,
            'account_type' => $accruedExpenses->account_type,
            'report_type' => $accruedExpenses->report_type,
            'is_group' => false,
            'currency' => 'KWD',
            'actual_balance' => 0,
            'opening_balance' => 0,
            'budget_balance' => 0,
            'variance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exitCode = Artisan::call('accounting:ensure-system-leaves');

        // Residual 11 fix (W2.1): handle() must return FAILURE when ANY company failed, even
        // though the healthy sibling below succeeded in the very same run — a caller gating on
        // the exit code, not just grepping console text, must be able to see this.
        $this->assertSame(
            1,
            $exitCode,
            'handle() must return Command::FAILURE (1) when any company failed, not SUCCESS (0).'
        );

        $this->assertSame(
            0,
            Account::withoutGlobalScopes()->where('company_id', $brokenCompany->id)->where('name', 'Markup Income')->count(),
            'The broken company\'s whole per-company transaction must roll back — including the leaf that would '
                .'otherwise have succeeded before the chain-walk failure.'
        );
        $this->assertSame(
            1,
            Account::withoutGlobalScopes()->where('company_id', $healthyCompany->id)->where('name', 'Markup Income')->count(),
            'A failure on one company must not block a healthy sibling company in the same run.'
        );
        $this->assertSame(
            1,
            DB::table('system_accounts')->where('company_id', $healthyCompany->id)->where('purpose_code', 'MARKUP_INCOME')->count()
        );
    }

    /**
     * Residual 11 fix, positive proof: when every company succeeds, handle() still returns
     * SUCCESS (0) — the FAILURE return is conditional on an actual failure, not unconditional.
     */
    public function test_exit_code_is_success_when_no_company_fails(): void
    {
        $company = $this->makeOldCompany();
        $this->trackCompanyForInvariants($company->id);

        $exitCode = Artisan::call('accounting:ensure-system-leaves', ['--company' => $company->id]);

        $this->assertSame(0, $exitCode, 'A healthy run with no failures must return Command::SUCCESS (0).');
    }

    /**
     * Residual 11 fix: a Throwable that is NOT an AccountValidationException (e.g. a transient
     * QueryException) must be caught the same way — logged, reported, and the run continues to
     * the next company — never allowed to propagate out of handle() uncaught (which would abort
     * every company after it AND skip the post-loop SystemAccountsSeeder re-map silently). This
     * is a difficult failure mode to induce with a real driver exception without corrupting the
     * shared test connection, so it is exercised the same way the codebase's own docblock
     * describes processCompany()'s contract: ANY Throwable, not just AccountValidationException,
     * reaching the per-company catch is treated identically. The account-occupied-by-a-different-
     * account case below IS a real, driver-independent Throwable (AccountValidationException,
     * which is itself just one concrete Throwable) proving the catch-and-continue contract holds
     * for the exact class of failure residual 11 was about; test_a_failure_on_one_company_does_not
     * _block_a_healthy_sibling_company above is this suite's proof for that shape.
     */
    public function test_occupied_code_failure_message_names_the_occupying_account(): void
    {
        $company = $this->makeOldCompany();

        $accruedExpenses = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', 'Accrued Expenses')
            ->firstOrFail();

        DB::table('accounts')->insert([
            'company_id' => $company->id,
            'parent_id' => $accruedExpenses->id,
            'name' => 'Some Occupying Leaf',
            'code' => '2201',
            'level' => $accruedExpenses->level + 1,
            'root_id' => $accruedExpenses->root_id,
            'account_type' => $accruedExpenses->account_type,
            'report_type' => $accruedExpenses->report_type,
            'is_group' => false,
            'currency' => 'KWD',
            'actual_balance' => 0,
            'opening_balance' => 0,
            'budget_balance' => 0,
            'variance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $occupier = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'Some Occupying Leaf')->firstOrFail();

        Log::spy();

        Artisan::call('accounting:ensure-system-leaves', ['--company' => $company->id]);

        Log::shouldHaveReceived('error')->withArgs(function (string $message, array $context) use ($occupier) {
            return $message === 'accounting.ensure_system_leaves_failed'
                && str_contains($context['message'] ?? '', (string) $occupier->id)
                && str_contains($context['message'] ?? '', $occupier->name);
        })->once();
    }

    /**
     * Task 2 (COA blocker fix, 2026-08-31): processCompany()'s new PRE-VALIDATION pass must
     * surface EVERY CORE leaf collision for a company TOGETHER, in one run — not just whichever
     * one self::LEAVES happens to reach first before the old flow aborted the whole per-company
     * transaction. Two DIFFERENT CORE leaves — 'Salaries & Wages Payable' (code 2201, under
     * 'Accrued Expenses') and 'Markup Income' (code 4132, under 'Commission & Service Fee
     * Income') — are the only two CORE leaves makeOldCompany() actually removes (every other
     * CORE leaf in self::LEAVES is already seeded by the real, current CoaSeeder and would just
     * resolve idempotently, not collide), so they double as this suite's two simultaneous
     * collisions: each is pre-occupied by an unrelated account before the command runs. The
     * single FAILED log line this produces must name BOTH occupying accounts (id, name, and
     * colliding code), proving both collisions were collected and reported in the same pass
     * rather than the operator needing two separate runs to discover the second one.
     */
    public function test_two_simultaneous_core_leaf_collisions_are_both_reported_together(): void
    {
        $company = $this->makeOldCompany();

        $accruedExpenses = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', 'Accrued Expenses')
            ->firstOrFail();
        $commissionServiceFee = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', 'Commission & Service Fee Income')
            ->firstOrFail();

        DB::table('accounts')->insert([
            'company_id' => $company->id,
            'parent_id' => $accruedExpenses->id,
            'name' => 'Some Occupying Payable',
            'code' => '2201',
            'level' => $accruedExpenses->level + 1,
            'root_id' => $accruedExpenses->root_id,
            'account_type' => $accruedExpenses->account_type,
            'report_type' => $accruedExpenses->report_type,
            'is_group' => false,
            'currency' => 'KWD',
            'actual_balance' => 0,
            'opening_balance' => 0,
            'budget_balance' => 0,
            'variance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $payableOccupier = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', 'Some Occupying Payable')
            ->firstOrFail();

        DB::table('accounts')->insert([
            'company_id' => $company->id,
            'parent_id' => $commissionServiceFee->id,
            'name' => 'Some Occupying Income',
            'code' => '4132',
            'level' => $commissionServiceFee->level + 1,
            'root_id' => $commissionServiceFee->root_id,
            'account_type' => $commissionServiceFee->account_type,
            'report_type' => $commissionServiceFee->report_type,
            'is_group' => false,
            'currency' => 'KWD',
            'actual_balance' => 0,
            'opening_balance' => 0,
            'budget_balance' => 0,
            'variance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $incomeOccupier = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', 'Some Occupying Income')
            ->firstOrFail();

        Log::spy();

        $exitCode = Artisan::call('accounting:ensure-system-leaves', ['--company' => $company->id]);

        $this->assertSame(1, $exitCode, 'A company with any CORE leaf collision must fail.');

        // Neither colliding code was actually created as its intended leaf — the accounts at
        // 2201/4132 are still the unrelated occupiers, never overwritten or duplicated.
        $this->assertSame(
            0,
            Account::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'Salaries & Wages Payable')->count()
        );
        $this->assertSame(
            0,
            Account::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'Markup Income')->count()
        );

        Log::shouldHaveReceived('error')
            ->withArgs(function (string $message, array $context) use ($payableOccupier, $incomeOccupier) {
                $logged = $context['message'] ?? '';

                return $message === 'accounting.ensure_system_leaves_failed'
                    && str_contains($logged, (string) $payableOccupier->id)
                    && str_contains($logged, $payableOccupier->name)
                    && str_contains($logged, '2201')
                    && str_contains($logged, (string) $incomeOccupier->id)
                    && str_contains($logged, $incomeOccupier->name)
                    && str_contains($logged, '4132');
            })
            ->once();
    }

    /**
     * W3-prereq lane A, RULING (2): a missing per-service SERVICE_REVENUE leaf is backfilled via
     * AccountService::createSystemLeaf(), landing on the SAME code legacy
     * `InvoiceController::addJournalEntry()` would have picked ("highest existing 'Direct Income'
     * sibling code" + 5), then mapped by the post-loop SystemAccountsSeeder re-run.
     */
    public function test_creates_missing_ferry_booking_revenue_leaf_and_maps_it(): void
    {
        $company = $this->makeOldCompanyMissingFerryBookingRevenue();
        $this->trackCompanyForInvariants($company->id);

        $this->assertSame(
            0,
            Account::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'Ferry Booking Revenue')->count(),
            'Precondition: the fixture must have removed the Ferry Booking Revenue leaf.'
        );

        $directIncome = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', 'Direct Income')
            ->firstOrFail();

        $lastSiblingCode = (int) Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('parent_id', $directIncome->id)
            ->orderByDesc('code')
            ->value('code');
        $expectedCode = str_pad((string) ($lastSiblingCode + 5), 4, '0', STR_PAD_LEFT);

        $exitCode = Artisan::call('accounting:ensure-system-leaves', ['--company' => $company->id]);
        $this->assertSame(0, $exitCode, 'A healthy company missing only the Ferry Booking Revenue leaf must not fail.');

        $ferryRevenue = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', 'Ferry Booking Revenue')
            ->first();

        $this->assertNotNull($ferryRevenue, 'The missing Ferry Booking Revenue leaf must be created.');
        $this->assertSame('Direct Income', $ferryRevenue->parent->name);
        $this->assertSame('Income', $ferryRevenue->root->name);
        $this->assertFalse((bool) $ferryRevenue->is_group, 'A newly created leaf must be a leaf, never a group.');
        $this->assertSame(
            $expectedCode,
            $ferryRevenue->code,
            "Must follow the SAME 'highest existing Direct Income sibling code + 5' rule legacy "
                .'InvoiceController::addJournalEntry() already uses to auto-create this exact leaf.'
        );
        $this->assertSame(
            1,
            Account::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'Ferry Booking Revenue')->count(),
            'No duplicate creation.'
        );
        $this->assertNoDuplicateAccountCodes($company->id);

        $mapped = DB::table('system_accounts')
            ->where('company_id', $company->id)
            ->where('purpose_code', 'SERVICE_REVENUE')
            ->where('service_type', 'ferry')
            ->value('account_id');

        $this->assertSame($ferryRevenue->id, $mapped, 'SERVICE_REVENUE/ferry must now be mapped to the newly created leaf.');

        // Idempotent second run: creates nothing further.
        Artisan::call('accounting:ensure-system-leaves', ['--company' => $company->id]);
        $this->assertSame(
            1,
            Account::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'Ferry Booking Revenue')->count(),
            'Second run must not create a duplicate Ferry Booking Revenue leaf.'
        );
    }

    /**
     * W3-prereq lane A, RULING (3): duplicates by name within a company must never be picked from
     * silently, and never pooled — the command must refuse outright rather than create a third
     * account or guess which of the two existing ones is correct. RULING (5)'s own exit-code
     * requirement: this matches the SAME Command::FAILURE convention every other unguessable
     * chart-shape problem in this command already uses (see
     * test_a_failure_on_one_company_does_not_block_a_healthy_sibling_company above) — the whole
     * per-company transaction (including the core leaves created earlier in the SAME run) rolls
     * back too.
     */
    public function test_ambiguous_duplicate_booking_revenue_name_refuses_and_fails_the_company(): void
    {
        $company = $this->makeOldCompanyMissingFerryBookingRevenue();

        $salesGroup = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', 'Sales')
            ->firstOrFail();

        foreach (['9991', '9992'] as $code) {
            DB::table('accounts')->insert([
                'company_id' => $company->id,
                'parent_id' => $salesGroup->id,
                'name' => 'Ferry Booking Revenue',
                'code' => $code,
                'level' => $salesGroup->level + 1,
                'root_id' => $salesGroup->root_id,
                'account_type' => $salesGroup->account_type,
                'report_type' => $salesGroup->report_type,
                'is_group' => false,
                'currency' => 'KWD',
                'actual_balance' => 0,
                'opening_balance' => 0,
                'budget_balance' => 0,
                'variance' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->assertSame(
            2,
            Account::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'Ferry Booking Revenue')->count(),
            'Precondition: two accounts must already share this exact name.'
        );

        $exitCode = Artisan::call('accounting:ensure-system-leaves', ['--company' => $company->id]);

        $this->assertSame(
            1,
            $exitCode,
            'An ambiguous pre-existing Booking Revenue name must fail this company — matching this command\'s '
                .'existing exit-code convention for any refused, unguessable chart shape — never silently pick '
                .'one or create a third.'
        );
        $this->assertSame(
            2,
            Account::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'Ferry Booking Revenue')->count(),
            'No third account may be created.'
        );
        $this->assertSame(
            0,
            DB::table('system_accounts')->where('company_id', $company->id)->where('purpose_code', 'SERVICE_REVENUE')->where('service_type', 'ferry')->count(),
            'SERVICE_REVENUE/ferry must remain unmapped — never silently pooled onto either duplicate.'
        );
        $this->assertSame(
            0,
            Account::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'Markup Income')->count(),
            'The WHOLE per-company transaction must roll back, including the core leaves created earlier in the same run.'
        );
    }

    /**
     * accounting-builds T0a: the two NEW core leaves for FX_GAIN_REALISED (4139) and
     * ASSET_DISPOSAL_GAIN (4141, NOT 4140 — see CoaSeeder's own comment). "Old company" simulated
     * the same way makeOldCompany() does for the pre-existing four leaves: seed with the REAL,
     * CURRENT CoaSeeder+SystemAccountsSeeder, then delete these two leaves and their
     * system_accounts rows.
     */
    public function test_creates_and_maps_realised_fx_gain_and_asset_disposal_gain_leaves(): void
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder())->run();

        $fxGain = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '4139')->firstOrFail();
        $disposalGain = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '4141')->firstOrFail();

        DB::table('system_accounts')
            ->where('company_id', $company->id)
            ->whereIn('purpose_code', ['FX_GAIN_REALISED', 'ASSET_DISPOSAL_GAIN'])
            ->delete();
        Account::withoutGlobalScopes()->whereIn('id', [$fxGain->id, $disposalGain->id])->delete();

        $this->trackCompanyForInvariants($company->id);

        Artisan::call('accounting:ensure-system-leaves', ['--company' => $company->id]);

        $fxGain = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'Realised Exchange Gain')->first();
        $disposalGain = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'Gain on Asset Disposal')->first();

        $this->assertNotNull($fxGain, 'AccountService::createSystemLeaf() must have created the Realised Exchange Gain leaf.');
        $this->assertNotNull($disposalGain, 'AccountService::createSystemLeaf() must have created the Gain on Asset Disposal leaf.');
        $this->assertSame('4139', $fxGain->code);
        $this->assertSame('4141', $disposalGain->code, 'CODE 4141, NOT 4140 — 4140 is a real, pre-existing Sales leaf.');
        $this->assertSame('Commission & Service Fee Income', $fxGain->parent->name);
        $this->assertSame('Commission & Service Fee Income', $disposalGain->parent->name);
        $this->assertSame('Income', $fxGain->root->name);
        $this->assertFalse((bool) $fxGain->is_group);
        $this->assertFalse((bool) $disposalGain->is_group);
        $this->assertNoDuplicateAccountCodes($company->id);

        $this->assertSame(
            $fxGain->id,
            DB::table('system_accounts')->where('company_id', $company->id)->where('purpose_code', 'FX_GAIN_REALISED')->value('account_id')
        );
        $this->assertSame(
            $disposalGain->id,
            DB::table('system_accounts')->where('company_id', $company->id)->where('purpose_code', 'ASSET_DISPOSAL_GAIN')->value('account_id')
        );

        // Idempotent second run.
        Artisan::call('accounting:ensure-system-leaves', ['--company' => $company->id]);
        $this->assertSame(1, Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '4139')->count());
        $this->assertSame(1, Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '4141')->count());
    }

    /**
     * accounting-builds T0a (L7, positive path — 1880 has no journal lines): the seven per-class
     * accumulated-depreciation contras are minted as children of 1880 and mapped to their
     * FA_ACCUM_DEP_{class} purposes. The guard-refusal path (1880 HAS journal lines) is covered by
     * the dedicated FixedAssetContraGuardTest, not here.
     */
    public function test_creates_and_maps_fixed_asset_contra_leaves_when_1880_has_no_journal_lines(): void
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder())->run();

        $contraCodes = ['1881', '1882', '1883', '1884', '1885', '1886', '1887'];
        $contraIds = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereIn('code', $contraCodes)
            ->pluck('id', 'code');

        $this->assertCount(7, $contraIds, 'Precondition: CoaSeeder must have seeded all 7 contra leaves for a fresh company.');

        DB::table('system_accounts')
            ->where('company_id', $company->id)
            ->where('purpose_code', 'like', 'FA_ACCUM_DEP_%')
            ->delete();
        Account::withoutGlobalScopes()->whereIn('id', $contraIds->values())->delete();

        $this->trackCompanyForInvariants($company->id);

        $accumulatedDepreciation = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1880')->firstOrFail();
        $this->assertSame(
            0,
            DB::table('journal_entries')->where('account_id', $accumulatedDepreciation->id)->count(),
            'Precondition: 1880 must carry no journal lines for this test to exercise the unguarded path.'
        );

        Artisan::call('accounting:ensure-system-leaves', ['--company' => $company->id]);

        $expected = [
            '1881' => ['name' => 'Accumulated Depreciation — Capital Equipments', 'purpose' => 'FA_ACCUM_DEP_CAPITAL_EQUIPMENT'],
            '1882' => ['name' => 'Accumulated Depreciation — Electronic Equipments', 'purpose' => 'FA_ACCUM_DEP_ELECTRONIC_EQUIPMENT'],
            '1883' => ['name' => 'Accumulated Depreciation — Furniture and Fixtures', 'purpose' => 'FA_ACCUM_DEP_FURNITURE_FIXTURES'],
            '1884' => ['name' => 'Accumulated Depreciation — Office Equipments', 'purpose' => 'FA_ACCUM_DEP_OFFICE_EQUIPMENT'],
            '1885' => ['name' => 'Accumulated Depreciation — Plants and Machineries', 'purpose' => 'FA_ACCUM_DEP_PLANT_MACHINERY'],
            '1886' => ['name' => 'Accumulated Depreciation — Buildings', 'purpose' => 'FA_ACCUM_DEP_BUILDINGS'],
            '1887' => ['name' => 'Accumulated Depreciation — Softwares', 'purpose' => 'FA_ACCUM_DEP_SOFTWARE'],
        ];

        foreach ($expected as $code => $spec) {
            $account = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', $code)->first();

            $this->assertNotNull($account, "Contra leaf code={$code} must have been created.");
            $this->assertSame($spec['name'], $account->name);
            $this->assertSame('Accumulated Depreciation', $account->parent->name);
            $this->assertSame('Assets', $account->root->name);
            $this->assertFalse((bool) $account->is_group);
            $this->assertSame(
                $account->id,
                DB::table('system_accounts')->where('company_id', $company->id)->where('purpose_code', $spec['purpose'])->value('account_id'),
                "{$spec['purpose']} must now be mapped to the newly created leaf."
            );
        }

        $this->assertNoDuplicateAccountCodes($company->id);

        // 1880 itself is now a group (has children) — never posted to directly again.
        $accumulatedDepreciation->refresh();
        $this->assertTrue($accumulatedDepreciation->children()->exists());

        // Idempotent second run: creates nothing further.
        Artisan::call('accounting:ensure-system-leaves', ['--company' => $company->id]);
        foreach (array_keys($expected) as $code) {
            $this->assertSame(1, Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', $code)->count());
        }
    }
}
