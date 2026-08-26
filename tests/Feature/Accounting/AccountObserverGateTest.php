<?php

namespace Tests\Feature\Accounting;

use App\Exceptions\Accounting\AccountValidationException;
use App\Models\Account;
use App\Models\Company;
use App\Services\Accounting\AccountService;
use Tests\Support\AccountingTestCase;

/**
 * Round-4 regression test for P1 blocker #3 ("AccountObserver is registered globally and arms an
 * app-wide outage the day P2 flips the engine flag").
 *
 * Before this fix, App\Observers\AccountObserver's `creating` backstop gated on
 * config('accounting.engine.enabled') — the SAME flag PostingService itself reads. That coupling
 * meant turning the engine on for P2's first feeder would simultaneously start rejecting every
 * plain Account::create()/save() call app-wide, including the ~10 legacy call sites
 * (AgentController, BranchController, ChargeController, SupplierCompanyController, TaskController,
 * InvoiceController, ChatController, ImportChartOfAccounts, CoaController) whose refactor onto
 * App\Services\Accounting\AccountService is explicitly deferred to P2 — an app-wide outage
 * triggered by an unrelated config flip.
 *
 * The fix gives the observer its own independent flag, config('accounting.account_observer.enabled')
 * (config/accounting.php), which AccountObserver now checks exclusively. This suite pins that:
 * turning the posting engine on must NOT, by itself, turn account-creation policing on, and the
 * observer flag must still work as its own gate in both directions.
 */
class AccountObserverGateTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        // Defensive reset so config mutated by one test can never leak into another test in the
        // suite (Laravel's config() array is process-global for the duration of the test run).
        config([
            'accounting.engine.enabled' => false,
            'accounting.account_observer.enabled' => false,
        ]);

        parent::tearDown();
    }

    /**
     * THE regression this fix exists to prevent: engine flag ON, observer flag OFF (the exact
     * state P2's first feeder cutover will be in) — a plain Account::create() that never goes
     * through AccountService must still succeed untouched, exactly as it does today.
     */
    public function test_plain_account_create_succeeds_when_engine_enabled_but_observer_disabled(): void
    {
        config([
            'accounting.engine.enabled' => true,
            'accounting.account_observer.enabled' => false,
        ]);

        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $this->assertFalse(
            AccountService::$creatingViaService,
            'Precondition: this call must not go through AccountService.'
        );

        $account = Account::factory()->create(['company_id' => $company->id]);

        $this->assertTrue($account->exists);
        $this->assertDatabaseHas('accounts', ['id' => $account->id, 'company_id' => $company->id]);
    }

    /**
     * The other half of the decoupling: the observer flag is a real, independent gate — turning
     * it on rejects a direct Account::create() even while the engine flag is OFF. Proves this
     * isn't simply "the observer never fires anymore," but that it now keys on its own flag
     * rather than the engine's.
     */
    public function test_plain_account_create_throws_when_observer_enabled_even_if_engine_disabled(): void
    {
        config([
            'accounting.engine.enabled' => false,
            'accounting.account_observer.enabled' => true,
        ]);

        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $this->expectException(AccountValidationException::class);

        Account::factory()->create(['company_id' => $company->id]);
    }

    /**
     * Sanity: with the observer flag on, the sanctioned path (AccountService::create(), which
     * sets the AccountService::$creatingViaService escape hatch around its own save()) still
     * works — the observer is a backstop against bypassing AccountService, not against account
     * creation itself.
     */
    public function test_account_service_create_still_succeeds_when_observer_enabled(): void
    {
        config([
            'accounting.engine.enabled' => true,
            'accounting.account_observer.enabled' => true,
        ]);

        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $account = app(AccountService::class)->create([
            'company_id' => $company->id,
            'name' => 'Assets',
        ]);

        $this->assertTrue($account->exists);
        $this->assertSame('Assets', $account->name);
    }

    /** Baseline: both flags at their documented defaults (false) — unchanged legacy behavior. */
    public function test_plain_account_create_succeeds_with_both_flags_at_default(): void
    {
        config([
            'accounting.engine.enabled' => false,
            'accounting.account_observer.enabled' => false,
        ]);

        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $account = Account::factory()->create(['company_id' => $company->id]);

        $this->assertTrue($account->exists);
    }
}
