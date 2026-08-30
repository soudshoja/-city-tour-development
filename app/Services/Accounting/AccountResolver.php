<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Exceptions\Accounting\AccountNotUnderGroupException;
use App\Exceptions\Accounting\CrossTenantAccountException;
use App\Exceptions\Accounting\FrozenAccountException;
use App\Exceptions\Accounting\NonLeafAccountException;
use App\Exceptions\Accounting\UnmappedPurposeException;
use App\Models\Account;
use Illuminate\Support\Facades\DB;

/**
 * Resolves a leaf account by purpose code for a company — the fix vehicle for R7.3 / BUG-H6 /
 * T-Finding-1's account half: PostingService (and everything downstream of it) must never do
 * `Account::where('name', ...)` again.
 *
 * MUST work with no Auth context (queue/webhook safe): every query here goes through the plain
 * query builder / `withoutGlobalScopes()` rather than a scoped Eloquent query, so behavior is
 * identical whether or not `auth()->check()` is true — unlike Account's own `BelongsToCompany`
 * global scope and Transaction's `booted()` global scope, both of which silently no-op (i.e.
 * under-filter) when unauthenticated.
 *
 * File 11 §P1.1, L294-301, verbatim contract.
 */
final class AccountResolver
{
    /**
     * @throws UnmappedPurposeException when no system_accounts row maps this
     *                                  (companyId, purposeCode, serviceType) triple, or when the row it finds points at an
     *                                  account that no longer exists — the engine must never silently skip a leg.
     * @throws CrossTenantAccountException when the mapped row points at an account belonging to a
     *                                     different company than $companyId — BLOCKER fix (verification finding: PostingService
     *                                     only asserts same-tenant on the explicit-accountId branch; this branch was previously
     *                                     unchecked, so a mis-seeded/tampered system_accounts row could write a cross-tenant
     *                                     journal line with no exception, no log). $companyId is filtered into the WHERE clause
     *                                     above, but that only proves the *mapping row* belongs to the right company — it says
     *                                     nothing about the *account* the mapping points at, since account_id has no DB-level FK
     *                                     constraint tying it back to system_accounts.company_id (see the system_accounts
     *                                     migration's own comment: "enforced in code"). This is that code.
     * @throws NonLeafAccountException belt-and-braces (not merely PostingService's own step 3d check, which runs
     *                                 later and only on the post() path): a purpose-code caller must never receive an
     *                                 unpostable account from this resolver, whether or not it goes through PostingService.
     * @throws FrozenAccountException belt-and-braces, mirrors PostingService step 3e for the same reason as above.
     */
    public function resolve(string $purposeCode, int $companyId, ?string $serviceType = null): Account
    {
        $mapping = DB::table('system_accounts')
            ->where('company_id', $companyId)
            ->where('purpose_code', $purposeCode)
            ->where(function ($query) use ($serviceType) {
                $serviceType === null
                    ? $query->whereNull('service_type')
                    : $query->where('service_type', $serviceType);
            })
            ->first();

        if ($mapping === null) {
            throw new UnmappedPurposeException($purposeCode, $companyId, $serviceType);
        }

        $account = Account::query()
            ->withoutGlobalScopes()
            ->find($mapping->account_id);

        if ($account === null) {
            throw new UnmappedPurposeException(
                $purposeCode,
                $companyId,
                $serviceType,
                sprintf(
                    'system_accounts row #%d maps purpose_code=%s to accounts.id=%d, which no longer exists.',
                    $mapping->id,
                    $purposeCode,
                    $mapping->account_id
                )
            );
        }

        // Tenant assertion (the fix): system_accounts.company_id was already filtered into the
        // mapping query above, but account_id itself carries no DB-level constraint back to it —
        // this is the ONLY place that ever re-checks the account this row actually points at.
        if ((int) $account->company_id !== $companyId) {
            throw new CrossTenantAccountException(
                (int) $account->id,
                (int) $account->company_id,
                $companyId,
                sprintf(
                    'system_accounts row #%d maps purpose_code=%s (company_id=%d) to accounts.id=%d, '
                    .'which belongs to company_id=%d.',
                    $mapping->id,
                    $purposeCode,
                    $companyId,
                    $account->id,
                    $account->company_id
                )
            );
        }

        // Belt-and-braces (see @throws docblock above): never hand back an unpostable account.
        if (! self::isLeaf($account)) {
            throw new NonLeafAccountException(
                $account->id,
                $account->name,
                sprintf(
                    'system_accounts row #%d maps purpose_code=%s to accounts.id=%d (%s), which is not '
                    .'a leaf account.',
                    $mapping->id,
                    $purposeCode,
                    $account->id,
                    $account->name
                )
            );
        }

        if ((bool) $account->disabled) {
            throw new FrozenAccountException(
                $account->id,
                $account->name,
                sprintf(
                    'system_accounts row #%d maps purpose_code=%s to accounts.id=%d (%s), which is disabled.',
                    $mapping->id,
                    $purposeCode,
                    $account->id,
                    $account->name
                )
            );
        }

        return $account;
    }

    /**
     * W3.A2 (Accounting Gap/22-plan-amendments.md §2.1a row "W3.A2 — anchor purpose codes +
     * engine change", orchestrator ruling A11) — resolves an ANCHOR purpose code
     * (config('accounting.purpose_codes.anchors'), e.g. `AGENT_COMMISSION_PAYABLE_GROUP`,
     * `AGENT_RECEIVABLE_GROUP`) to the GROUP account a new per-party leaf is meant to mint under,
     * via the exact same `system_accounts` mapping mechanism {@see resolve()} uses for ordinary
     * posting-target leaves.
     *
     * Deliberately DOES NOT require the resolved account to be a leaf (the opposite of
     * resolve()'s own guarantee) — an anchor's whole purpose is to already BE (or become) a
     * group with children minted under it, so enforcing isLeaf() here would make this method
     * reject the very accounts it exists to resolve. It still runs every other resolve() safety
     * check that has nothing to do with leaf-ness: the mapping must exist, the account it points
     * at must still exist, must belong to $companyId, and must not be disabled.
     *
     * Scope note: this method is the resolution primitive only. Wiring a real consumer —
     * {@see \App\Services\Accounting\AccountService::ensurePartyLeaf()} minting a per-agent leaf
     * under the resolved anchor instead of a hardcoded parent id, and the companion
     * band-collision guard the design doc names (also called `assertAnchorIsSafeToExpand()`
     * there) — is explicitly W3.A, a separate, out-of-scope lane for this build: AccountService
     * already has its OWN, differently-scoped `assertAnchorIsSafeToExpand()` (guarding
     * `ensurePartyLeaf()`'s existing `resolve()`-based anchor against demoting a still-live
     * posting-target leaf) which this method's docblock deliberately does not touch, rename, or
     * duplicate, to avoid colliding with that already-shipped, already-tested guard.
     *
     * @throws UnmappedPurposeException when no system_accounts row maps this (companyId,
     *                                  purposeCode) pair, or the row it finds points at an account that no longer exists.
     * @throws CrossTenantAccountException when the mapped account belongs to a different company
     *                                     than $companyId.
     * @throws FrozenAccountException when the mapped account is disabled.
     */
    public function resolveAnchor(string $purposeCode, int $companyId): Account
    {
        $mapping = DB::table('system_accounts')
            ->where('company_id', $companyId)
            ->where('purpose_code', $purposeCode)
            ->whereNull('service_type')
            ->first();

        if ($mapping === null) {
            throw new UnmappedPurposeException($purposeCode, $companyId, null);
        }

        $account = Account::query()
            ->withoutGlobalScopes()
            ->find($mapping->account_id);

        if ($account === null) {
            throw new UnmappedPurposeException(
                $purposeCode,
                $companyId,
                null,
                sprintf(
                    'system_accounts row #%d maps anchor purpose_code=%s to accounts.id=%d, which no longer exists.',
                    $mapping->id,
                    $purposeCode,
                    $mapping->account_id
                )
            );
        }

        if ((int) $account->company_id !== $companyId) {
            throw new CrossTenantAccountException(
                (int) $account->id,
                (int) $account->company_id,
                $companyId,
                sprintf(
                    'system_accounts row #%d maps anchor purpose_code=%s (company_id=%d) to accounts.id=%d, '
                    .'which belongs to company_id=%d.',
                    $mapping->id,
                    $purposeCode,
                    $companyId,
                    $account->id,
                    $account->company_id
                )
            );
        }

        if ((bool) $account->disabled) {
            throw new FrozenAccountException(
                $account->id,
                $account->name,
                sprintf(
                    'system_accounts row #%d maps anchor purpose_code=%s to accounts.id=%d (%s), which is disabled.',
                    $mapping->id,
                    $purposeCode,
                    $account->id,
                    $account->name
                )
            );
        }

        return $account;
    }

    /**
     * THE single leaf rule for the P1 engine (P1 FIX ROUND 3, HIGH regression fix). Deliberately
     * matches PostingService::post() step 3d (PostingService.php, "HIGH — the leaf test no longer
     * trusts `accounts.is_group`" in that file's own docblock) verbatim: a leaf is any account
     * with zero child rows, full stop. `accounts.is_group` is NEVER consulted, here or anywhere
     * else in the P1 engine — the column defaults TRUE (migration
     * 2025_04_03_112301_add_new_columns_in_accounts_table.php) and the `is_group =
     * EXISTS(child)` backfill (file 11 §P1.0) that would make it trustworthy is explicitly
     * deferred out of this build's scope (verification: ~42,401 accounts flagged is_group vs 25
     * genuine leaf violations). Trusting is_group here — as ROUND 2 briefly did — rejects nearly
     * every real account and silently disables purpose-code resolution entirely (see this file's
     * git history / P1-VERIFICATION-FINDINGS.json HIGH REGRESSION 1); trusting it the other way
     * round (treating is_group=true as a reliable "already promoted" signal, as
     * AccountService::assertAnchorIsSafeToExpand() briefly did) is just as unsafe, since that
     * TRUE default has nothing to do with whether the account is actually still someone's live
     * posting target.
     *
     * THIS is the one place the rule is written. resolve() above and
     * AccountService::assertAnchorIsSafeToExpand() both call it — no other call site in the P1
     * engine may re-derive a leaf test from is_group. PostingService's own step 3d implements the
     * identical predicate as a batched query (one `WHERE parent_id IN (...)` for every line's
     * account instead of N single-account exists() calls) purely for performance — that file is
     * owned by a different fixer in this round and is out of this file's edit scope, so it is not
     * refactored to call this method directly, but its predicate must never diverge from this
     * one. If it ever needs to, change both in the same commit.
     */
    public static function isLeaf(Account $account): bool
    {
        return ! Account::query()
            ->withoutGlobalScopes()
            ->where('parent_id', $account->id)
            ->exists();
    }

    /**
     * W5.L (w5-brief.md §W5.L item 4: "bank leaf on a voucher is passed by account id and
     * validated to sit under the BANK group") — resolves and validates an account id a caller
     * (a future RV/PV feeder, W5.R/W5.P) names as "the bank leaf this voucher pays into/out of".
     *
     * Unlike resolve()/resolveAnchor(), this does NOT go through the `system_accounts` purpose-code
     * mapping — a company may have several bank leaves (CoaSeeder seeds two: 'Kuwait International
     * Bank' 1201, 'Ahli United Bank Kuwait' 1204), and a voucher's bank leaf is a per-document
     * CHOICE the user makes, not a single fixed anchor a purpose code could name. What this method
     * DOES enforce is the structural fact any such choice must satisfy: the account exists, belongs
     * to $companyId, is not disabled, is a genuine leaf (self::isLeaf(), the same predicate resolve()
     * uses), and has config('accounting.engine.bank_group_name') ('Bank Accounts' in the seed COA)
     * somewhere in its ancestor chain — never a name/root_id/parent_id string lookup, matching this
     * whole class's own R7.3/BUG-H6 mandate.
     *
     * @throws CrossTenantAccountException when the account does not exist, or belongs to a
     *                                     different company than $companyId.
     * @throws FrozenAccountException when the account is disabled.
     * @throws NonLeafAccountException when the account is not a leaf.
     * @throws AccountNotUnderGroupException when no ancestor of the account is named
     *                                       config('accounting.engine.bank_group_name').
     */
    public function assertUnderBankGroup(int $accountId, int $companyId): Account
    {
        $account = Account::query()->withoutGlobalScopes()->find($accountId);

        if ($account === null) {
            // Same sentinel convention PostingService::post() step 2 already uses for a
            // nonexistent explicit-accountId line: a nonexistent account has no owning company to
            // report, so accountCompanyId is 0 (not applicable).
            throw new CrossTenantAccountException($accountId, 0, $companyId, "Account #{$accountId} does not exist.");
        }

        if ((int) $account->company_id !== $companyId) {
            throw new CrossTenantAccountException((int) $account->id, (int) $account->company_id, $companyId);
        }

        if ((bool) $account->disabled) {
            throw new FrozenAccountException(
                $account->id,
                $account->name,
                "Account #{$account->id} ({$account->name}) is disabled."
            );
        }

        if (! self::isLeaf($account)) {
            throw new NonLeafAccountException(
                $account->id,
                $account->name,
                "Account #{$account->id} ({$account->name}) is not a leaf account."
            );
        }

        $bankGroupName = (string) config('accounting.engine.bank_group_name', 'Bank Accounts');

        if (! $this->hasAncestorNamed($account, $bankGroupName)) {
            throw new AccountNotUnderGroupException($account->id, $account->name, $bankGroupName);
        }

        return $account;
    }

    /**
     * W5.X (w5-brief.md §W5.X item 1, invariant A21) — classifies a LEAF account (any account;
     * this is a read-only classification helper for report/invariant code, not a posting-target
     * resolver, so unlike resolve()/assertUnderBankGroup() it does not itself require the account
     * to be a leaf) as "cash or bank" by the SAME ancestor-walk primitive
     * {@see assertUnderBankGroup()} already uses for the bank half — never a direct
     * `accounts.name`/`accounts.root_id` equality check at the call site, which is the exact
     * per-report-reimplementation risk A21 exists to close off. An account IS itself named
     * `config('accounting.engine.bank_group_name')`/`cash_group_name`, or has either name
     * somewhere in its ancestor chain, counts.
     *
     * Report/invariant code (ReportController, TrialBalanceService, `accounting:verify`) should
     * call THIS method to decide "is this journal line a cash/bank movement", never re-derive the
     * same classification from `doc_type`/`reference_type` — that substitution (selecting a
     * receipts/Day Book report's rows by `doc_type IN ('RV','PV')` instead of by which LEAF the
     * line actually posted to) is precisely the defect A21 forbids: a cash movement posted under
     * any other doc_type (an `AST` settlement, a spot-commission `PV` variant not yet built, a
     * plain `JV` cash correction) would silently disappear from such a report.
     */
    public function isCashOrBankLeaf(Account $account): bool
    {
        $bankGroupName = (string) config('accounting.engine.bank_group_name', 'Bank Accounts');
        $cashGroupName = (string) config('accounting.engine.cash_group_name', 'Cash In Hand');

        if ($account->name === $bankGroupName || $account->name === $cashGroupName) {
            return true;
        }

        return $this->hasAncestorNamed($account, $bankGroupName) || $this->hasAncestorNamed($account, $cashGroupName);
    }

    /**
     * Walks parent_id -> withoutGlobalScopes()->find() one level at a time, never
     * `$account->parent` (Eloquent's lazy-loaded BelongsTo relation, which — unlike every other
     * lookup in this class — is NOT run through withoutGlobalScopes(): App\Traits\BelongsToCompany's
     * global 'company' scope applies to every Account query whenever Auth::check() is true, exactly
     * the gap AccountService::ancestorChainMatches()'s own "Residual 17 fix" already closed for the
     * identical chain-walk shape one class over — mirrored here rather than re-discovered).
     */
    private function hasAncestorNamed(Account $account, string $groupName): bool
    {
        $currentParentId = $account->parent_id;

        while ($currentParentId !== null) {
            $current = Account::query()->withoutGlobalScopes()->find($currentParentId);

            if ($current === null) {
                return false;
            }

            if ($current->name === $groupName) {
                return true;
            }

            $currentParentId = $current->parent_id;
        }

        return false;
    }

    /**
     * P2.5.G verify fix (drift risk): every bank/cash/gateway-clearing LEAF for a company — the
     * exact account set both {@see \App\Services\Accounting\ReconciliationCenterService} (the
     * Reconciliation Center grid) and {@see \App\Services\Accounting\PeriodCloseChecklistService}
     * (the month-end close checklist, class (a)) need for "which leaves count as bank/cash".
     * Previously each class re-derived this identical leaf-walk independently; resolved HERE once
     * so a future change to which leaves count (a new instrument code, a new gateway) can never
     * leave the grid and the checklist silently disagreeing about the account set — the real drift
     * risk a verify pass flagged, since the two classes legitimately still compute DIFFERENT things
     * from this same set (a "book vs. confirmed vs. gap" grid row vs. a "reconciled/unreconciled
     * count" close-gate row).
     *
     * @return int[]
     */
    public function bankCashLeafIds(int $companyId): array
    {
        $companyAccounts = Account::withoutGlobalScopes()->where('company_id', $companyId)->get(['id', 'parent_id', 'name']);
        $parentIds = $companyAccounts->pluck('parent_id')->filter()->flip();

        $leafIds = $companyAccounts
            ->reject(fn (Account $a): bool => $parentIds->has($a->id))
            ->filter(fn (Account $leaf): bool => $this->isCashOrBankLeaf($leaf))
            ->pluck('id')
            ->all();

        foreach (array_keys(config('accounting.period_close.cash_bank_instrument_codes', [])) as $code) {
            $account = Account::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->first();
            if ($account !== null) {
                $leafIds[] = $account->id;
            }
        }

        foreach (array_keys(config('accounting.purpose_codes.gateways', [])) as $gatewayKey) {
            try {
                $leafIds[] = $this->resolve('GATEWAY_CLEARING_'.$gatewayKey, $companyId)->id;
            } catch (UnmappedPurposeException) {
                // Not every company has every gateway mapped.
            }
        }

        return array_values(array_unique($leafIds));
    }

    /**
     * P2.5.G verify fix (drift risk, same rationale as {@see self::bankCashLeafIds()}): every
     * control-account GROUP (class (b): AR/AP control leaves + agent sub-ledger anchors) for a
     * company, resolved from `config('accounting.period_close.control_purpose_codes')` /
     * `'agent_control_anchors'` in ONE place. A purpose code / anchor with nothing mapped or
     * minted yet still appears in the returned list with an EMPTY `account_ids` — each caller
     * decides what an empty group means for its own output shape (the checklist screen emits a
     * `not_configured` placeholder row so an admin can see it needs mapping; the Reconciliation
     * Center grid simply omits the row, exactly as it already did before this fix).
     *
     * @return list<array{purpose_code:string,label:string,account_ids:int[]}>
     */
    public function controlAccountGroups(int $companyId): array
    {
        $groups = [];

        foreach (config('accounting.period_close.control_purpose_codes', []) as $purposeCode => $label) {
            try {
                $accountIds = [$this->resolve($purposeCode, $companyId)->id];
            } catch (UnmappedPurposeException) {
                $accountIds = [];
            }

            $groups[] = ['purpose_code' => $purposeCode, 'label' => $label, 'account_ids' => $accountIds];
        }

        foreach (config('accounting.period_close.agent_control_anchors', []) as $purposeCode => $label) {
            try {
                $anchor = $this->resolveAnchor($purposeCode, $companyId);
                $accountIds = Account::withoutGlobalScopes()->where('company_id', $companyId)->where('parent_id', $anchor->id)->pluck('id')->all();
            } catch (UnmappedPurposeException) {
                $accountIds = [];
            }

            $groups[] = ['purpose_code' => $purposeCode, 'label' => $label, 'account_ids' => $accountIds];
        }

        return $groups;
    }
}
