<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * legacy-ledger-pilot LP1e (ruling R-verify) — report (and, on request, set)
 * the two `accounting.engine.*` group names the pilot instance needs.
 *
 * ── The problem ─────────────────────────────────────────────────────────────
 * `accounting:verify`'s RV/PV cash-or-bank invariant, and
 * {@see \App\Services\Accounting\AccountResolver}'s own bank/cash-leaf
 * classification, match account GROUP NAMES exactly against
 * `config('accounting.engine.bank_group_name')` / `cash_group_name` — 'Bank
 * Accounts' / 'Cash In Hand' in the seeded chart. The imported legacy chart
 * names those groups 'BANK ACCOUNTS' / 'CASH ACCOUNTS', so on an
 * un-overridden instance every one of the ~8,730 replayed RV/PV documents is
 * later reported as a violation for a naming difference alone. Staging run #5
 * printed that warning twice and it is PLAN.md §5.3 O7-verify's own
 * requirement.
 *
 * ── The mechanism, named exactly ────────────────────────────────────────────
 * Two env keys, read by config/accounting.php:
 *
 *     ACCOUNTING_BANK_GROUP_NAME="BANK ACCOUNTS"
 *     ACCOUNTING_CASH_GROUP_NAME="CASH ACCOUNTS"
 *
 * Blank or unset keeps the seeded-chart defaults, so setting them is a
 * PILOT-INSTANCE-ONLY act. This class reports what to set, VERIFIES the name
 * against the imported chart rather than trusting config, and writes the two
 * lines into the instance's `.env` only when explicitly asked
 * (`legacy:import-masters --write-env`). It never mutates runtime config: a
 * staging override belongs in staging's `.env`, not in a command's side
 * effects.
 */
final class LegacyVerifyConfigReporter
{
    /** env key => [config key, legacy_pilot config key holding the required value] */
    private const KEYS = [
        'ACCOUNTING_BANK_GROUP_NAME' => ['accounting.engine.bank_group_name', 'legacy_pilot.replay.verify.bank_group_name'],
        'ACCOUNTING_CASH_GROUP_NAME' => ['accounting.engine.cash_group_name', 'legacy_pilot.replay.verify.cash_group_name'],
    ];

    /**
     * @return array{ok:bool,entries:array<int, array{env_key:string,config_key:string,current:string,required:string,ok:bool,present_in_chart:?bool}>,candidates:array<string, array<int, string>>}
     */
    public function report(int $companyId): array
    {
        $entries = [];
        $ok = true;

        foreach (self::KEYS as $envKey => [$configKey, $requiredKey]) {
            $required = (string) config($requiredKey, '');
            $current = (string) config($configKey, '');
            $matches = $required !== '' && $current === $required;
            $ok = $ok && $matches;

            $entries[] = [
                'env_key' => $envKey,
                'config_key' => $configKey,
                'current' => $current,
                'required' => $required,
                'ok' => $matches,
                'present_in_chart' => $required === '' ? null : $this->groupExists($companyId, $required),
            ];
        }

        return [
            'ok' => $ok,
            'entries' => $entries,
            'candidates' => [
                'bank' => $this->groupCandidates($companyId, 'BANK'),
                'cash' => $this->groupCandidates($companyId, 'CASH'),
            ],
        ];
    }

    /**
     * Write the two keys into the instance's `.env`, replacing an existing
     * line rather than appending a duplicate. Returns the keys written.
     *
     * @return array<int, string>
     */
    public function writeEnv(int $companyId, string $envPath): array
    {
        if (! is_file($envPath) || ! is_writable($envPath)) {
            throw new RuntimeException("{$envPath} does not exist or is not writable — set the two keys by hand.");
        }

        $contents = (string) file_get_contents($envPath);
        $written = [];

        foreach ($this->report($companyId)['entries'] as $entry) {
            if ($entry['required'] === '') {
                continue;
            }

            $line = $entry['env_key'].'="'.$entry['required'].'"';
            $pattern = '/^'.preg_quote($entry['env_key'], '/').'=.*$/m';

            $contents = preg_match($pattern, $contents) === 1
                ? (string) preg_replace($pattern, $line, $contents)
                : rtrim($contents, "\r\n")."\n\n# legacy-ledger-pilot O7-verify (legacy:import-masters --write-env)\n".$line."\n";

            $written[] = $entry['env_key'];
        }

        file_put_contents($envPath, $contents);

        return $written;
    }

    /**
     * Does a NON-LEAF account with this exact name exist in the company's
     * chart? Answering from the chart rather than from config is the point:
     * a configured name that no group carries would silently "pass" a
     * config-only comparison and still flag every document.
     */
    private function groupExists(int $companyId, string $name): ?bool
    {
        if (! DB::getSchemaBuilder()->hasTable('accounts')) {
            return null;
        }

        return DB::table('accounts as parent')
            ->where('parent.company_id', $companyId)
            ->where('parent.name', $name)
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('accounts as child')->whereColumn('child.parent_id', 'parent.id'))
            ->exists();
    }

    /**
     * Group names in the imported chart containing the token — what to set
     * the env key to when the configured name is not the one this chart uses.
     *
     * @return array<int, string>
     */
    private function groupCandidates(int $companyId, string $token): array
    {
        if (! DB::getSchemaBuilder()->hasTable('accounts')) {
            return [];
        }

        return DB::table('accounts as parent')
            ->where('parent.company_id', $companyId)
            ->where('parent.name', 'like', '%'.$token.'%')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('accounts as child')->whereColumn('child.parent_id', 'parent.id'))
            ->orderBy('parent.name')
            ->distinct()
            ->pluck('parent.name')
            ->map(static fn ($name): string => (string) $name)
            ->values()
            ->all();
    }
}
