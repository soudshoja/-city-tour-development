<?php

declare(strict_types=1);

namespace App\Services\Accounting;

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
}
