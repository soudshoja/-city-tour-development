<?php

declare(strict_types=1);

namespace App\Http\Livewire\Accounting;

use App\Console\Commands\EnsureSystemLeaves;
use App\Exceptions\Accounting\AccountValidationException;
use App\Models\Account;
use App\Models\CoaCategory;
use App\Models\SystemAccount;
use App\Services\Accounting\AccountingLog;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\AccountService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * COA UI lane (2026-08-31, COA audit finding 1): "system_accounts purpose mappings have NO UI —
 * gaps surface only as runtime UnmappedPurposeException with console-only repair." This screen
 * lists every purpose code {@see AccountResolver} can be asked to resolve for company 1 (the
 * single-tenant lock — see getCompanyId()), shows what it currently maps to (or that it does not
 * map to anything at all), and offers two repair actions: map it onto an existing leaf account, or
 * — for the fixed set of USER-DECIDED leaves {@see EnsureSystemLeaves::LEAVES} already knows how to
 * create — invoke {@see AccountService::createSystemLeaf()} the exact same way that console command
 * does, then map the newly-created leaf.
 *
 * Enumerates the SAME vocabulary {@see \Database\Seeders\SystemAccountsSeeder} maps and
 * {@see AccountResolver} resolves against — never a hand-maintained second list:
 *   - config('accounting.purpose_codes.global') — ordinary (purposeCode, service_type=null) codes.
 *   - config('accounting.purpose_codes.gateways') — expanded into GATEWAY_FEE_EXPENSE_{key} and
 *     GATEWAY_CLEARING_{key} for every configured gateway (service_type=null), the exact naming
 *     convention SystemAccountsSeeder::resolveGatewayClearing()/resolveGatewayFeeExpense() use.
 *   - config('accounting.purpose_codes.per_service') x config('accounting.purpose_codes.service_types')
 *     — SERVICE_REVENUE/SERVICE_PAYABLE/SERVICE_COST resolved per service_type (the third
 *     AccountResolver::resolve() argument), matching SystemAccountsSeeder::resolveServices().
 *   - config('accounting.purpose_codes.anchors') — shown separately, READ-ONLY: these resolve via
 *     AccountResolver::resolveAnchor() (a GROUP, not a leaf) and are explicitly out of this build's
 *     scope to seed/repair (see that config key's own docblock: "P2 scope") — flagged for
 *     visibility only, no repair action offered, so this screen never invites an operator to "fix"
 *     something the engine does not actually consume yet.
 */
class PurposeMappingIndex extends Component
{
    /** @var array<int,string>|null memoised current-repair target ["PURPOSE_CODE", "service_type"|null] */
    public ?array $repairTarget = null;

    public string $accountSearch = '';

    public string $flashMessage = '';

    public string $flashType = 'success';

    public function mount(): void
    {
        Gate::authorize('viewAny', CoaCategory::class);
    }

    private function resolveCompanyId(): ?int
    {
        $user = Auth::user();

        return $user !== null ? getCompanyId($user) : null;
    }

    public function startRepair(string $purposeCode, ?string $serviceType): void
    {
        Gate::authorize('update', CoaCategory::class);

        $this->repairTarget = [$purposeCode, $serviceType];
        $this->accountSearch = '';
    }

    public function cancelRepair(): void
    {
        $this->repairTarget = null;
        $this->accountSearch = '';
    }

    /** @return Collection<int,Account> */
    public function repairCandidates(): Collection
    {
        $companyId = $this->resolveCompanyId();

        if ($companyId === null || $this->repairTarget === null) {
            return collect();
        }

        $term = trim($this->accountSearch);

        $parentIdsWithChildren = Account::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNotNull('parent_id')
            ->pluck('parent_id')
            ->unique()
            ->flip();

        return Account::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('disabled', false)
            ->when($term !== '', fn ($q) => $q->where(function ($q2) use ($term) {
                $q2->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%");
            }))
            ->orderBy('code')
            ->limit(200)
            ->get(['id', 'name', 'code'])
            ->reject(fn (Account $a) => $parentIdsWithChildren->has($a->id))
            ->take(15)
            ->values();
    }

    /**
     * Map the row currently under repair onto an existing account. Validated the same way
     * {@see AccountResolver::resolve()} itself would refuse the mapping at read time (leaf,
     * company-scoped, not disabled) — never write a mapping the resolver would immediately throw
     * on the next time something posts through it.
     */
    public function mapToAccount(int $accountId): void
    {
        Gate::authorize('update', CoaCategory::class);

        if ($this->repairTarget === null) {
            return;
        }

        [$purposeCode, $serviceType] = $this->repairTarget;
        $companyId = $this->resolveCompanyId();

        if ($companyId === null) {
            $this->flash('error', 'Could not resolve the current company.');

            return;
        }

        $account = Account::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->find($accountId);

        if ($account === null) {
            $this->flash('error', 'That account no longer exists.');

            return;
        }

        if (! AccountResolver::isLeaf($account)) {
            $this->flash('error', "\"{$account->name}\" is not a leaf account (it has children) and cannot be a posting target.");

            return;
        }

        if ((bool) $account->disabled) {
            $this->flash('error', "\"{$account->name}\" is disabled. Enable it first, or choose a different account.");

            return;
        }

        $this->writeMapping($companyId, $purposeCode, $serviceType, $account->id);

        $this->flash('success', "{$purposeCode}".($serviceType !== null ? "/{$serviceType}" : '')." now maps to \"{$account->name}\" ({$account->code}).");
        $this->repairTarget = null;
        $this->accountSearch = '';
    }

    /**
     * Create-and-map for a purpose code {@see EnsureSystemLeaves::leafSpecs()} already knows how
     * to create (a fixed USER-DECIDED leafName/code/parentChain) — invokes
     * {@see AccountService::createSystemLeaf()} exactly the way `accounting:ensure-system-leaves`
     * does, then maps the (possibly pre-existing, idempotent) resulting leaf onto this purpose code.
     */
    public function createLeaf(string $purposeCode): void
    {
        Gate::authorize('update', CoaCategory::class);

        $companyId = $this->resolveCompanyId();

        if ($companyId === null) {
            $this->flash('error', 'Could not resolve the current company.');

            return;
        }

        $spec = collect(EnsureSystemLeaves::leafSpecs())->firstWhere('purposeCode', $purposeCode);

        if ($spec === null) {
            $this->flash('error', "{$purposeCode} has no fixed leaf definition to create automatically. Use \"Map to existing account\" instead.");

            return;
        }

        try {
            $account = app(AccountService::class)->createSystemLeaf(
                $companyId,
                $spec['parentChain'],
                $spec['leafName'],
                $spec['code']
            );
        } catch (AccountValidationException $e) {
            $this->flash('error', $e->getMessage());

            return;
        }

        $this->writeMapping($companyId, $purposeCode, null, $account->id);

        $this->flash('success', "Created \"{$account->name}\" ({$account->code}) and mapped {$purposeCode} to it.");
        $this->repairTarget = null;
    }

    private function writeMapping(int $companyId, string $purposeCode, ?string $serviceType, int $accountId): void
    {
        $existing = SystemAccount::query()
            ->where('company_id', $companyId)
            ->where('purpose_code', $purposeCode)
            ->where(function ($q) use ($serviceType) {
                $serviceType === null ? $q->whereNull('service_type') : $q->where('service_type', $serviceType);
            })
            ->first();

        $before = $existing !== null ? ['account_id' => $existing->account_id] : null;

        if ($existing !== null) {
            $existing->account_id = $accountId;
            $existing->save();
            $row = $existing;
        } else {
            $row = SystemAccount::create([
                'company_id' => $companyId,
                'purpose_code' => $purposeCode,
                'service_type' => $serviceType,
                'account_id' => $accountId,
            ]);
        }

        AccountingLog::write(
            action: 'purpose_mapping_repaired',
            companyId: $companyId,
            subjectType: 'system_account',
            subjectId: $row->id,
            before: $before,
            after: ['account_id' => $accountId, 'purpose_code' => $purposeCode, 'service_type' => $serviceType],
            reason: $before === null ? 'gap repaired' : 'remapped',
            actorId: Auth::id(),
        );
    }

    private function flash(string $type, string $message): void
    {
        $this->flashType = $type;
        $this->flashMessage = $message;
    }

    /**
     * @return array<int, array{
     *     purposeCode: string, serviceType: ?string, label: string, mapped: bool,
     *     account: ?Account, invalidLeaf: bool, disabledAccount: bool, creatable: bool
     * }>
     */
    private function buildRows(int $companyId): array
    {
        $codes = [];

        foreach (config('accounting.purpose_codes.global', []) as $code) {
            $codes[] = [$code, null, $code];
        }

        foreach (config('accounting.purpose_codes.gateways', []) as $key => $label) {
            $codes[] = ["GATEWAY_FEE_EXPENSE_{$key}", null, "Gateway fee expense — {$label}"];
            $codes[] = ["GATEWAY_CLEARING_{$key}", null, "Gateway clearing — {$label}"];
        }

        // accounting-builds T0a: FA_COST_{key}/FA_ACCUM_DEP_{key} for every fixed-asset class,
        // same key-expansion pattern as 'gateways' immediately above.
        foreach (config('accounting.purpose_codes.fixed_asset_classes', []) as $key => $spec) {
            $label = $spec['label'] ?? $key;
            $codes[] = ["FA_COST_{$key}", null, "Fixed asset cost — {$label}"];
            $codes[] = ["FA_ACCUM_DEP_{$key}", null, "Fixed asset accumulated depreciation — {$label}"];
        }

        foreach (config('accounting.purpose_codes.per_service', []) as $base) {
            foreach (config('accounting.purpose_codes.service_types', []) as $type) {
                $codes[] = [$base, $type, "{$base} — {$type}"];
            }
        }

        $mappings = SystemAccount::query()
            ->where('company_id', $companyId)
            ->get()
            ->keyBy(fn (SystemAccount $m) => $m->purpose_code.'|'.($m->service_type ?? ''));

        $accountIds = $mappings->pluck('account_id')->filter()->unique();

        $accounts = Account::withoutGlobalScopes()
            ->whereIn('id', $accountIds)
            ->get()
            ->keyBy('id');

        $parentIdsWithChildren = Account::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNotNull('parent_id')
            ->pluck('parent_id')
            ->unique()
            ->flip();

        $creatableCodes = collect(EnsureSystemLeaves::leafSpecs())->pluck('purposeCode')->flip();

        $rows = [];

        foreach ($codes as [$code, $serviceType, $label]) {
            $mapping = $mappings->get($code.'|'.($serviceType ?? ''));
            $account = $mapping !== null ? $accounts->get($mapping->account_id) : null;

            $invalidLeaf = $account !== null && $parentIdsWithChildren->has($account->id);
            $disabledAccount = $account !== null && (bool) $account->disabled;

            $rows[] = [
                'purposeCode' => $code,
                'serviceType' => $serviceType,
                'label' => $label,
                'mapped' => $account !== null,
                'account' => $account,
                'invalidLeaf' => $invalidLeaf,
                'disabledAccount' => $disabledAccount,
                'creatable' => $creatableCodes->has($code),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array{purposeCode: string, label: string, mapped: bool, account: ?Account}>
     */
    private function buildAnchorRows(int $companyId): array
    {
        $anchors = config('accounting.purpose_codes.anchors', []);

        $mappings = SystemAccount::query()
            ->where('company_id', $companyId)
            ->whereIn('purpose_code', $anchors)
            ->whereNull('service_type')
            ->get()
            ->keyBy('purpose_code');

        $accountIds = $mappings->pluck('account_id')->filter()->unique();
        $accounts = Account::withoutGlobalScopes()->whereIn('id', $accountIds)->get()->keyBy('id');

        $rows = [];

        foreach ($anchors as $code) {
            $mapping = $mappings->get($code);
            $account = $mapping !== null ? $accounts->get($mapping->account_id) : null;

            $rows[] = [
                'purposeCode' => $code,
                'label' => $code,
                'mapped' => $account !== null,
                'account' => $account,
            ];
        }

        return $rows;
    }

    public function render(): View
    {
        $companyId = $this->resolveCompanyId();
        $rows = $companyId !== null ? $this->buildRows($companyId) : [];
        $anchorRows = $companyId !== null ? $this->buildAnchorRows($companyId) : [];

        $gapCount = collect($rows)->where('mapped', false)->count()
            + collect($rows)->where('invalidLeaf', true)->count();

        return view('livewire.accounting.purpose-mapping-index', [
            'companyId' => $companyId,
            'rows' => $rows,
            'anchorRows' => $anchorRows,
            'gapCount' => $gapCount,
            'repairCandidates' => $this->repairCandidates(),
        ]);
    }
}
