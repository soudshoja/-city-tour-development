<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Company;
use App\Models\SystemAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * For every existing company, map every purpose_code in the P1 vocabulary
 * (config('accounting.purpose_codes')) to its leaf Account and upsert a
 * system_accounts row. This is what lets P2 resolve accounts by role instead
 * of by name string (R7.3 / BUG-H6).
 *
 * Resolution is always structural, never a guess:
 *   - RECEIVABLE_CONTROL / PAYABLE_CONTROL are resolved by NAME + ANCESTOR
 *     CHAIN (not bare name — file 11 warns two accounts are both named
 *     "Clients": one pooled under Accounts Receivable/Assets, one under
 *     Refund Payable/Liabilities. Only the Accounts Receivable one is the
 *     receivable control).
 *   - RETAINED_EARNINGS / FX_GAIN_LOSS are resolved by the CODE file 11 gives
 *     verbatim (3400 / 5219).
 *   - GATEWAY_CLEARING_* is resolved under the "Payment Gateway" account
 *     rooted under Assets (there is also an unrelated "Payment Gateway" leaf
 *     rooted under Liabilities/Advances/Client in the seed COA — excluded by
 *     the same root-chain check).
 *   - SERVICE_PAYABLE / SERVICE_COST are resolved by the "Suppliers (X)" /
 *     "X Cost" leaf naming convention CoaSeeder already uses for all 12 task
 *     types.
 *   - SERVICE_REVENUE only exists for task types that have a dedicated
 *     revenue leaf in the current COA (flight, hotel) — see resolveServices().
 *   - VAT_OUTPUT and SUSPENSE have no dedicated leaf anywhere in the current
 *     COA (Kuwait v1 has no VAT — GCC VAT is P9), so every company reports a
 *     gap for these rather than being mapped onto an unrelated account.
 *
 * Every candidate must also be a true structural leaf
 * (children()->doesntExist()) at the moment the seeder runs — is_group is
 * documented as unreliable (file 11) and is never trusted here.
 *
 * Idempotent: every write is a SystemAccount::updateOrCreate() keyed on the
 * table's unique(company_id, purpose_code, service_type) index. Never
 * guesses and never creates an Account — a company that lacks a mappable
 * leaf for a purpose code is SKIPPED and REPORTED (console + log), not
 * force-mapped.
 */
class SystemAccountsSeeder extends Seeder
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $report = [
        'mapped' => [],
        'skipped' => [],
    ];

    /**
     * account_id => Account, memoized per company run to avoid re-walking the
     * parent chain for every purpose code.
     *
     * @var array<int, Account>
     */
    private array $accountCache = [];

    public function run(): void
    {
        $companies = Company::query()->select(['id', 'name'])->orderBy('id')->get();

        if ($companies->isEmpty()) {
            $this->line('SystemAccountsSeeder: no companies found — nothing to map.');

            return;
        }

        foreach ($companies as $company) {
            $this->accountCache = [];
            $this->seedCompany((int) $company->id, (string) ($company->name ?: "#{$company->id}"));
        }

        $this->printReport();
    }

    private function seedCompany(int $companyId, string $companyLabel): void
    {
        $this->resolveControls($companyId, $companyLabel);
        $this->resolveGatewayClearing($companyId, $companyLabel);
        $this->resolveServices($companyId, $companyLabel);
    }

    /**
     * The six global (service_type NULL) control purpose codes.
     */
    private function resolveControls(int $companyId, string $companyLabel): void
    {
        $this->mapByChain(
            $companyId,
            $companyLabel,
            'RECEIVABLE_CONTROL',
            null,
            'Clients',
            ['Accounts Receivable', 'Assets']
        );

        $this->mapByChain(
            $companyId,
            $companyLabel,
            'PAYABLE_CONTROL',
            null,
            'Creditors',
            ['Accounts Payable', 'Liabilities']
        );

        $this->mapByCode($companyId, $companyLabel, 'RETAINED_EARNINGS', null, '3400', 'Retained Earnings');
        $this->mapByCode($companyId, $companyLabel, 'FX_GAIN_LOSS', null, '5219', 'Exchange Gain/Loss');

        // No dedicated leaf exists anywhere in the current COA for either of these —
        // Kuwait v1 has no VAT (GCC VAT is P9), and no "Suspense" account is seeded.
        // Report the gap rather than guessing an unrelated account or creating one.
        $this->skip(
            $companyId,
            $companyLabel,
            'VAT_OUTPUT',
            null,
            'no VAT leaf exists in the current chart of accounts (Kuwait v1 has no VAT; GCC VAT is P9)'
        );

        $this->mapByName($companyId, $companyLabel, 'SUSPENSE', null, 'Suspense');
    }

    /**
     * GATEWAY_CLEARING_{gateway} for every gateway config('accounting.purpose_codes.gateways')
     * names. All resolve under the single "Payment Gateway" leaf rooted under Assets unless a
     * company has already split it into per-gateway children (matched by substring on the
     * gateway's label), in which case each matched gateway maps to its own child leaf.
     */
    private function resolveGatewayClearing(int $companyId, string $companyLabel): void
    {
        /** @var array<string, string> $gateways */
        $gateways = config('accounting.purpose_codes.gateways', []);

        $candidates = Account::query()
            ->where('company_id', $companyId)
            ->where('name', 'Payment Gateway')
            ->get()
            ->filter(fn (Account $account) => $this->rootNameOf($account) === 'Assets')
            ->values();

        if ($candidates->isEmpty()) {
            foreach ($gateways as $code => $label) {
                $this->skip(
                    $companyId,
                    $companyLabel,
                    "GATEWAY_CLEARING_{$code}",
                    null,
                    "no 'Payment Gateway' account rooted under Assets found for {$label}"
                );
            }

            return;
        }

        if ($candidates->count() > 1) {
            foreach ($gateways as $code => $label) {
                $this->skip(
                    $companyId,
                    $companyLabel,
                    "GATEWAY_CLEARING_{$code}",
                    null,
                    "ambiguous: {$candidates->count()} 'Payment Gateway' accounts rooted under Assets for {$label}"
                );
            }

            return;
        }

        $pool = $candidates->first();
        $children = $pool->children()->get();

        $unmatched = $gateways;

        foreach ($children as $child) {
            foreach ($gateways as $code => $label) {
                if (! array_key_exists($code, $unmatched)) {
                    continue;
                }

                if (stripos((string) $child->name, $label) === false) {
                    continue;
                }

                if ($child->children()->exists()) {
                    $this->skip(
                        $companyId,
                        $companyLabel,
                        "GATEWAY_CLEARING_{$code}",
                        null,
                        "'{$child->name}' (id={$child->id}) is a group account, not a leaf"
                    );
                } else {
                    $this->upsert($companyId, $companyLabel, "GATEWAY_CLEARING_{$code}", null, $child);
                }

                unset($unmatched[$code]);
            }
        }

        if (empty($unmatched)) {
            return;
        }

        // No per-gateway split found for the remaining gateways — fall back to the pooled
        // leaf itself, but only if it is genuinely a leaf (no children of its own at all).
        if ($pool->children()->exists()) {
            foreach ($unmatched as $code => $label) {
                $this->skip(
                    $companyId,
                    $companyLabel,
                    "GATEWAY_CLEARING_{$code}",
                    null,
                    "'Payment Gateway' has children but none match '{$label}', and the pooled account itself is a group account, not a leaf"
                );
            }

            return;
        }

        foreach ($unmatched as $code => $label) {
            $this->upsert($companyId, $companyLabel, "GATEWAY_CLEARING_{$code}", null, $pool);
        }
    }

    /**
     * SERVICE_PAYABLE / SERVICE_COST / SERVICE_REVENUE × the 12 task types.
     */
    private function resolveServices(int $companyId, string $companyLabel): void
    {
        $serviceTypes = config('accounting.purpose_codes.service_types', []);

        $payableNames = [
            'flight' => 'Suppliers (Flights)',
            'hotel' => 'Suppliers (Hotels)',
            'visa' => 'Suppliers (Visas)',
            'insurance' => 'Suppliers (Insurance)',
            'tour' => 'Suppliers (Tour)',
            'cruise' => 'Suppliers (Cruise)',
            'car' => 'Suppliers (Car)',
            'rail' => 'Suppliers (Rail)',
            'esim' => 'Suppliers (Esim)',
            'event' => 'Suppliers (Event)',
            'lounge' => 'Suppliers (Lounge)',
            'ferry' => 'Suppliers (Ferry)',
        ];

        $costNames = [
            'flight' => 'Flights Cost',
            'hotel' => 'Hotels Cost',
            'visa' => 'Visa Cost',
            'insurance' => 'Insurance Cost',
            'tour' => 'Tour Cost',
            'cruise' => 'Cruise Cost',
            'car' => 'Car Cost',
            'rail' => 'Rail Cost',
            'esim' => 'Esim Cost',
            'event' => 'Event Cost',
            'lounge' => 'Lounge Cost',
            'ferry' => 'Ferry Cost',
        ];

        // Only flight/hotel have a dedicated revenue leaf in the current COA. The other 10
        // task types have no per-service revenue account (they would otherwise pool under
        // "Sales" / "Services (other)", which was never designed as a per-service bucket) —
        // deliberately NOT auto-mapped there to avoid guessing at intent; reported as a gap.
        $revenueNames = [
            'flight' => 'Flight Booking Revenue',
            'hotel' => 'Hotel Booking Revenue',
        ];

        foreach ($serviceTypes as $serviceType) {
            if (isset($payableNames[$serviceType])) {
                $this->mapByName($companyId, $companyLabel, 'SERVICE_PAYABLE', $serviceType, $payableNames[$serviceType]);
            } else {
                $this->skip($companyId, $companyLabel, 'SERVICE_PAYABLE', $serviceType, "no naming convention known for service_type '{$serviceType}'");
            }

            if (isset($costNames[$serviceType])) {
                $this->mapByName($companyId, $companyLabel, 'SERVICE_COST', $serviceType, $costNames[$serviceType]);
            } else {
                $this->skip($companyId, $companyLabel, 'SERVICE_COST', $serviceType, "no naming convention known for service_type '{$serviceType}'");
            }

            if (isset($revenueNames[$serviceType])) {
                $this->mapByName($companyId, $companyLabel, 'SERVICE_REVENUE', $serviceType, $revenueNames[$serviceType]);
            } else {
                $this->skip(
                    $companyId,
                    $companyLabel,
                    'SERVICE_REVENUE',
                    $serviceType,
                    "no dedicated revenue leaf in the current COA for '{$serviceType}' (only flight/hotel have one)"
                );
            }
        }
    }

    /**
     * Resolve a leaf by exact name AND an exact chain of ancestor names (immediate parent
     * first). Used for the two purpose codes where the same account name is known to be
     * ambiguous (Clients, and — defensively — anything else sharing a name across branches).
     *
     * @param  string[]  $ancestorChain  immediate parent name first, then grandparent, etc.
     */
    private function mapByChain(
        int $companyId,
        string $companyLabel,
        string $purposeCode,
        ?string $serviceType,
        string $leafName,
        array $ancestorChain
    ): void {
        $candidates = Account::query()
            ->where('company_id', $companyId)
            ->where('name', $leafName)
            ->get()
            ->filter(fn (Account $account) => $this->ancestorChainMatches($account, $ancestorChain))
            ->values();

        $chainLabel = implode(' > ', $ancestorChain);

        if ($candidates->isEmpty()) {
            $this->skip($companyId, $companyLabel, $purposeCode, $serviceType, "no account named '{$leafName}' found under ancestor chain [{$chainLabel}]");

            return;
        }

        if ($candidates->count() > 1) {
            $this->skip($companyId, $companyLabel, $purposeCode, $serviceType, "ambiguous: {$candidates->count()} accounts named '{$leafName}' match ancestor chain [{$chainLabel}]");

            return;
        }

        $account = $candidates->first();

        if ($account->children()->exists()) {
            $this->skip($companyId, $companyLabel, $purposeCode, $serviceType, "'{$leafName}' (id={$account->id}, code={$account->code}) is a group account (has children), not a leaf");

            return;
        }

        $this->upsert($companyId, $companyLabel, $purposeCode, $serviceType, $account);
    }

    private function ancestorChainMatches(Account $account, array $ancestorChain): bool
    {
        $current = $account->parent;

        foreach ($ancestorChain as $expectedName) {
            if (! $current || $current->name !== $expectedName) {
                return false;
            }

            $current = $current->parent;
        }

        return true;
    }

    /**
     * Resolve a leaf by its account code (a structural fact file 11 gives explicitly for
     * RETAINED_EARNINGS/FX_GAIN_LOSS) rather than by name. Codes are the authoritative
     * anchor here, so a name mismatch is logged as a note but does not block the mapping;
     * a duplicate code (the known 2130/4130 CoaSeeder dedup bug, explicitly deferred) does
     * block it, since the seeder cannot know which duplicate is correct.
     */
    private function mapByCode(
        int $companyId,
        string $companyLabel,
        string $purposeCode,
        ?string $serviceType,
        string $code,
        string $expectedNameHint
    ): void {
        $candidates = Account::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->get();

        if ($candidates->isEmpty()) {
            $this->skip($companyId, $companyLabel, $purposeCode, $serviceType, "no account with code '{$code}' found");

            return;
        }

        if ($candidates->count() > 1) {
            $this->skip($companyId, $companyLabel, $purposeCode, $serviceType, "ambiguous: {$candidates->count()} accounts share code '{$code}' (duplicate-code bug, de-dup deferred)");

            return;
        }

        $account = $candidates->first();

        if ($account->children()->exists()) {
            $this->skip($companyId, $companyLabel, $purposeCode, $serviceType, "account code '{$code}' (name={$account->name}) is a group account, not a leaf");

            return;
        }

        if (stripos((string) $account->name, $expectedNameHint) === false) {
            Log::notice('SystemAccountsSeeder: mapped account name does not match the expected hint (code is authoritative, mapped anyway)', [
                'company_id' => $companyId,
                'purpose_code' => $purposeCode,
                'code' => $code,
                'expected_name_hint' => $expectedNameHint,
                'actual_name' => $account->name,
            ]);
        }

        $this->upsert($companyId, $companyLabel, $purposeCode, $serviceType, $account);
    }

    /**
     * Resolve a leaf by exact name only, anywhere in the company's tree. Used where the
     * name is already known to be unique in the seeded COA (per-service Suppliers/Cost
     * leaves, Suspense).
     */
    private function mapByName(int $companyId, string $companyLabel, string $purposeCode, ?string $serviceType, string $name): void
    {
        $candidates = Account::query()
            ->where('company_id', $companyId)
            ->where('name', $name)
            ->get();

        if ($candidates->isEmpty()) {
            $this->skip($companyId, $companyLabel, $purposeCode, $serviceType, "no account named '{$name}' found");

            return;
        }

        if ($candidates->count() > 1) {
            $this->skip($companyId, $companyLabel, $purposeCode, $serviceType, "ambiguous: {$candidates->count()} accounts named '{$name}'");

            return;
        }

        $account = $candidates->first();

        if ($account->children()->exists()) {
            $this->skip($companyId, $companyLabel, $purposeCode, $serviceType, "'{$name}' (id={$account->id}) is a group account, not a leaf");

            return;
        }

        $this->upsert($companyId, $companyLabel, $purposeCode, $serviceType, $account);
    }

    /**
     * Walk account_id -> root_id -> Account::name, scoped to the same company, so a
     * "Payment Gateway" rooted under Liabilities (via Advances > Client) never gets
     * mistaken for the Assets-rooted clearing account of the same name.
     */
    private function rootNameOf(Account $account): ?string
    {
        if ($account->root_id === null) {
            // The account itself is a root only when it has no parent.
            return $account->parent_id === null ? $account->name : null;
        }

        if (! isset($this->accountCache[$account->root_id])) {
            $root = Account::query()
                ->where('id', $account->root_id)
                ->where('company_id', $account->company_id)
                ->first();

            if ($root) {
                $this->accountCache[$account->root_id] = $root;
            }
        }

        return $this->accountCache[$account->root_id]->name ?? null;
    }

    private function upsert(int $companyId, string $companyLabel, string $purposeCode, ?string $serviceType, Account $account): void
    {
        SystemAccount::updateOrCreate(
            [
                'company_id' => $companyId,
                'purpose_code' => $purposeCode,
                'service_type' => $serviceType,
            ],
            [
                'account_id' => $account->id,
            ]
        );

        $this->report['mapped'][] = [
            'company' => $companyLabel,
            'company_id' => $companyId,
            'purpose_code' => $purposeCode,
            'service_type' => $serviceType,
            'account_id' => $account->id,
            'account_code' => $account->code,
            'account_name' => $account->name,
        ];
    }

    private function skip(int $companyId, string $companyLabel, string $purposeCode, ?string $serviceType, string $reason): void
    {
        $this->report['skipped'][] = [
            'company' => $companyLabel,
            'company_id' => $companyId,
            'purpose_code' => $purposeCode,
            'service_type' => $serviceType,
            'reason' => $reason,
        ];

        Log::warning('SystemAccountsSeeder: skipped purpose mapping', [
            'company_id' => $companyId,
            'purpose_code' => $purposeCode,
            'service_type' => $serviceType,
            'reason' => $reason,
        ]);
    }

    private function printReport(): void
    {
        $mapped = count($this->report['mapped']);
        $skipped = count($this->report['skipped']);

        $this->info("SystemAccountsSeeder: {$mapped} purpose(s) mapped, {$skipped} skipped.");

        foreach ($this->report['mapped'] as $row) {
            $this->line(sprintf(
                '  MAPPED  [%s] %s%s -> account #%d (%s / %s)',
                $row['company'],
                $row['purpose_code'],
                $row['service_type'] ? "/{$row['service_type']}" : '',
                $row['account_id'],
                $row['account_code'] ?? '—',
                $row['account_name']
            ));
        }

        foreach ($this->report['skipped'] as $row) {
            $this->warn(sprintf(
                '  SKIPPED [%s] %s%s -> %s',
                $row['company'],
                $row['purpose_code'],
                $row['service_type'] ? "/{$row['service_type']}" : '',
                $row['reason']
            ));
        }
    }

    private function info(string $message): void
    {
        $this->command?->getOutput()?->writeln("<info>{$message}</info>");
    }

    private function warn(string $message): void
    {
        $this->command?->getOutput()?->writeln("<comment>{$message}</comment>");
    }

    private function line(string $message): void
    {
        $this->command?->getOutput()?->writeln($message);
    }
}
