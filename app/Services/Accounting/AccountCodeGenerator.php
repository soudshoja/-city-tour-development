<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Account;

/**
 * Structural account-code generator (BUG-H1). Replaces every ad-hoc `'AGT-' . rand()`,
 * hardcoded-per-gateway code, supplier-code-equals-parent-code-plus-one, and lexicographic
 * MAX()/orderBy-on-varchar-code pattern found across AgentController, BranchController,
 * ChargeController, SupplierCompanyController, InvoiceController, TaskController and
 * CoaController.
 *
 * Algorithm per 08-prioritized-bug-list.md BUG-H1's fix text: "numeric max among siblings, pad to
 * sibling width, fallback to row id."
 *
 * DEVIATION from file 11's RECOMMENDED `generate(): string` signature: this returns `?string`.
 * BUG-H1's fix text names two distinct strategies — the numeric-max one, and a separate
 * "fallback to row id" for when there is no numeric sibling to extend from. The row-id fallback
 * needs the new row's own auto-increment id, which does not exist until AFTER it is inserted —
 * i.e. after generate() (called before that insert) has already returned. Returning null is the
 * explicit signal "no sibling basis; create the row first, then set its code via fallbackCode()"
 * — see AccountService::create(), which is the only consumer of both methods.
 */
final class AccountCodeGenerator
{
    /**
     * Best-effort next sibling code under $parent (or among the fixed roots when $parent is
     * null), as a numeric string padded to the width of its numeric siblings. Returns null when
     * there is no numeric sibling to derive a base/width from — see class docblock.
     */
    public function generate(?Account $parent, int $companyId): ?string
    {
        $siblingCodes = Account::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->when(
                $parent === null,
                fn ($q) => $q->whereNull('parent_id'),
                fn ($q) => $q->where('parent_id', $parent->id)
            )
            ->whereNotNull('code')
            ->pluck('code');

        $numericCodes = [];
        $width = 0;

        foreach ($siblingCodes as $siblingCode) {
            $siblingCode = (string) $siblingCode;
            if ($siblingCode === '' || ! ctype_digit($siblingCode)) {
                continue;
            }
            $numericCodes[] = (int) $siblingCode;
            $width = max($width, strlen($siblingCode));
        }

        if ($numericCodes === []) {
            return null;
        }

        $candidate = max($numericCodes) + 1;
        $code = str_pad((string) $candidate, $width, '0', STR_PAD_LEFT);

        // Defensive retry: no unique(company_id, code) constraint exists yet in P1 (the
        // CoaSeeder de-dup + constraint is explicitly deferred to a later phase), so a collision
        // with a pre-existing legacy duplicate is possible even on an otherwise clean tree. Never
        // hand back a code that is already taken.
        while ($this->codeExists($companyId, $code)) {
            $candidate++;
            $code = str_pad((string) $candidate, $width, '0', STR_PAD_LEFT);
        }

        return $code;
    }

    /**
     * BUG-H1's documented fallback path: use the (already-persisted) account's own row id as its
     * code. Call only after the account has been saved (so getKey() is non-null).
     */
    public function fallbackCode(Account $account): string
    {
        return (string) $account->getKey();
    }

    private function codeExists(int $companyId, string $code): bool
    {
        return Account::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->exists();
    }
}
