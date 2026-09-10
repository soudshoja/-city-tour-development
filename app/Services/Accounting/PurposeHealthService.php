<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Exceptions\Accounting\NonLeafAccountException;
use App\Models\Account;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * CT-A5a — "does every engine purpose actually resolve for this company?", asked once, in one
 * place.
 *
 * Three callers need that answer and must never disagree about it:
 *   - {@see \App\Console\Commands\CoaLinkage} (`accounting:coa-linkage`), which REPAIRS the chart
 *     and reports what still does not resolve;
 *   - {@see \App\Console\Commands\AccountingPurposeHealth} (`accounting:purpose-health`), which is
 *     the read-only operator view of the same question across every company;
 *   - {@see \App\Services\CompanyProvisioner}, which must REFUSE to hand over a company whose
 *     chart cannot carry the engine's first posting (§0.5 — the CT-A5 onboarding item).
 *
 * The purpose vocabulary itself is assembled from config exactly the way `SystemAccountsSeeder`
 * assembles it, never hand-copied. TE-1's onboarding defect ("purpose mapping ran before the chart
 * finished changing shape", travelerp PLAN-TE1 §, ported here) is a NON-LEAF result, not a missing
 * mapping — a row that resolves today and stops resolving the moment supplier activation gives its
 * target a child. That is why {@see self::inspect()} reports `non_leaf` as its own bucket rather
 * than folding it into `unresolved`: the two have different causes and different fixes.
 */
class PurposeHealthService
{
    /**
     * Purposes whose absence is deliberate, keyed by code. Kept verbatim in sync with
     * `CoaLinkage::NON_BLOCKING_PURPOSES` — that command owns the repair, this service owns the
     * question, and both must call the same gap deliberate.
     *
     * @var array<string, string>
     */
    public const NON_BLOCKING_PURPOSES = [
        'SUSPENSE' => 'deliberate: the engine posts nothing to suspense (CT-A3 §3.3); mapping it would hand a feeder a plug account.',
        'VAT_OUTPUT' => 'deliberate: Kuwait v1 has no VAT; GCC VAT is P9.',
    ];

    /**
     * Purpose FAMILIES (prefix-matched) whose absence is a configuration choice, not a defect.
     *
     * @var array<string, string>
     */
    public const NON_BLOCKING_PURPOSE_PREFIXES = [
        'GATEWAY_CLEARING_' => 'deliberate: a per-gateway clearing leaf exists only for a gateway the company actually transacts on.',
        'GATEWAY_FEE_EXPENSE_' => 'deliberate: same as GATEWAY_CLEARING_ — a fee leaf for an unused gateway is not a defect.',
    ];

    public function __construct(private readonly AccountResolver $resolver) {}

    /**
     * The engine's full purpose vocabulary, assembled from config the way `SystemAccountsSeeder`
     * assembles it. `anchors` are excluded on purpose: they resolve through `resolveAnchor()`,
     * name a GROUP rather than a leaf, and this build deliberately does not seed them.
     *
     * @return array<int, array{0: string, 1: string|null}>
     */
    public function requiredPurposes(): array
    {
        $out = [];

        foreach ((array) config('accounting.purpose_codes.global', []) as $code) {
            $out[] = [$code, null];
        }

        foreach (array_keys((array) config('accounting.purpose_codes.gateways', [])) as $key) {
            $out[] = ["GATEWAY_CLEARING_{$key}", null];
            $out[] = ["GATEWAY_FEE_EXPENSE_{$key}", null];
        }

        foreach (array_keys((array) config('accounting.purpose_codes.fixed_asset_classes', [])) as $key) {
            $out[] = ["FA_COST_{$key}", null];
            $out[] = ["FA_ACCUM_DEP_{$key}", null];
        }

        foreach ((array) config('accounting.purpose_codes.per_service', []) as $code) {
            foreach ((array) config('accounting.purpose_codes.service_types', []) as $serviceType) {
                $out[] = [$code, $serviceType];
            }
        }

        return $out;
    }

    public function isDeliberateGap(string $purposeCode): bool
    {
        if (isset(self::NON_BLOCKING_PURPOSES[$purposeCode])) {
            return true;
        }

        foreach (array_keys(self::NON_BLOCKING_PURPOSE_PREFIXES) as $prefix) {
            if (str_starts_with($purposeCode, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * One company's purpose health, read-only.
     *
     * @return array{
     *     company_id: int,
     *     total: int,
     *     resolved: int,
     *     unresolved: array<int, array{purpose: string, service_type: string|null, exception: string, deliberate: bool}>,
     *     non_leaf: array<int, array{purpose: string, service_type: string|null, account_id: int, account_name: string, child_count: int}>,
     *     dangling: array<int, array{system_account_id: int, purpose: string, account_id: int}>,
     *     blocking: int
     * }
     */
    public function inspect(int $companyId): array
    {
        $resolved = 0;
        $unresolved = [];
        $nonLeaf = [];
        $blocking = 0;

        foreach ($this->requiredPurposes() as [$purposeCode, $serviceType]) {
            try {
                $this->resolver->resolve($purposeCode, $companyId, $serviceType);
                $resolved++;
            } catch (NonLeafAccountException $e) {
                // The TE-1 shape: a mapping written while the target was still a leaf, which
                // supplier activation then turned into a group. Reported separately because the
                // fix is "re-map after the chart settles", not "add a missing row".
                $blocking++;
                $nonLeaf[] = array_merge(
                    ['purpose' => $purposeCode, 'service_type' => $serviceType],
                    $this->describeMappedAccount($companyId, $purposeCode, $serviceType)
                );
            } catch (Throwable $e) {
                $deliberate = $this->isDeliberateGap($purposeCode);

                if (! $deliberate) {
                    $blocking++;
                }

                $unresolved[] = [
                    'purpose' => $purposeCode,
                    'service_type' => $serviceType,
                    'exception' => class_basename($e),
                    'deliberate' => $deliberate,
                ];
            }
        }

        return [
            'company_id' => $companyId,
            'total' => count($this->requiredPurposes()),
            'resolved' => $resolved,
            'unresolved' => $unresolved,
            'non_leaf' => $nonLeaf,
            'dangling' => $this->danglingFor($companyId),
            'blocking' => $blocking,
        ];
    }

    /**
     * `system_accounts` rows for this company whose `account_id` names an account that does not
     * exist. CT-A4 measured 33 of these on company 1 and CT-D1 71 across the tenant set; CT-A3 R3
     * proved they are worse than inert, because a later mint can make one RESOLVE — at another
     * tenant's account.
     *
     * @return array<int, array{system_account_id: int, purpose: string, account_id: int}>
     */
    public function danglingFor(int $companyId): array
    {
        return DB::table('system_accounts as sa')
            ->leftJoin('accounts as a', 'a.id', '=', 'sa.account_id')
            ->where('sa.company_id', $companyId)
            ->whereNull('a.id')
            ->get(['sa.id', 'sa.purpose_code', 'sa.account_id'])
            ->map(fn ($r) => [
                'system_account_id' => (int) $r->id,
                'purpose' => (string) $r->purpose_code,
                'account_id' => (int) $r->account_id,
            ])
            ->all();
    }

    /** @return array{account_id: int, account_name: string, child_count: int} */
    private function describeMappedAccount(int $companyId, string $purposeCode, ?string $serviceType): array
    {
        $row = DB::table('system_accounts')
            ->where('company_id', $companyId)
            ->where('purpose_code', $purposeCode)
            ->when($serviceType === null, fn ($q) => $q->whereNull('service_type'))
            ->when($serviceType !== null, fn ($q) => $q->where('service_type', $serviceType))
            ->first(['account_id']);

        $accountId = $row !== null ? (int) $row->account_id : 0;

        $account = $accountId > 0
            ? Account::query()->withoutGlobalScopes()->find($accountId)
            : null;

        return [
            'account_id' => $accountId,
            'account_name' => (string) ($account->name ?? 'unknown'),
            'child_count' => $accountId > 0
                ? (int) Account::query()->withoutGlobalScopes()->where('parent_id', $accountId)->count()
                : 0,
        ];
    }
}
