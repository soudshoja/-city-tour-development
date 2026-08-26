<?php

declare(strict_types=1);

namespace App\Observers;

use App\Exceptions\Accounting\AccountValidationException;
use App\Models\Account;
use App\Services\Accounting\AccountService;

/**
 * `creating` backstop for the accounts table (file 11 §P1.0: "A `creating` model observer
 * backstops [AccountService] so imports/tinker cannot bypass it").
 *
 * Registered by App\Providers\AccountingServiceProvider::boot(). Deliberately inert while its own
 * gate is off: config('accounting.account_observer.enabled') defaults false. So every one of the
 * ~10 still-unrefactored legacy Account::create() call sites (AgentController, BranchController,
 * ChargeController, SupplierCompanyController, TaskController, InvoiceController, ChatController,
 * ImportChartOfAccounts, CoaController) keeps working exactly as before. Only once this flag is
 * turned on (P2+, after those sites are migrated onto AccountService) does this start rejecting
 * direct Account::create()/save() calls that didn't go through AccountService.
 *
 * ROUND 4 FIX (P1 blocker #3): this used to gate on config('accounting.engine.enabled') — the
 * SAME flag that turns the posting engine itself on for a company. That coupling meant the day
 * P2 flips the engine flag on to wire its first feeder, this observer would simultaneously start
 * policing EVERY Account::create() app-wide, including every legacy call site above whose
 * refactor onto AccountService is explicitly deferred to P2 — an app-wide outage triggered by an
 * unrelated config change. This observer now checks its OWN independent flag,
 * 'accounting.account_observer.enabled' (config/accounting.php), and does not consult
 * 'engine.enabled' at all. Enabling the posting engine no longer enables account-creation
 * policing, and vice versa — see config/accounting.php's 'account_observer' block for the
 * operational rule (only turn this on after the legacy call sites are migrated).
 *
 * NOTE (kept from the original build task, still relevant): an earlier task brief named the gate
 * as the flat key config('accounting.posting_engine_enabled'). No such config key exists — do not
 * reintroduce it. Gating on that literal string would make this observer permanently dead code,
 * since nothing populates it.
 */
class AccountObserver
{
    public function creating(Account $account): void
    {
        if (! config('accounting.account_observer.enabled', false)) {
            return;
        }

        if (AccountService::$creatingViaService) {
            return;
        }

        throw new AccountValidationException(
            'Accounts may only be created via App\\Services\\Accounting\\AccountService::create() '
            .'while the account-creation observer is enabled (config("accounting.account_observer.enabled")).'
        );
    }
}
