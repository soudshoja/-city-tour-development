<?php

declare(strict_types=1);

namespace App\Console\Commands\Legacy;

use App\Services\Onboarding\Parity\ParityHarness;
use App\Services\Onboarding\Parity\ParityReportWriter;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * legacy-ledger-pilot LP4.
 *
 * PLAN.md §2.2 records that no parity command exists in Akeed-Ai: the whole
 * `accounting:` surface is engine / verify / periods:init / period:close /
 * year:close / ensure-system-leaves / seed-serial-schemas / reconcile /
 * recognize-revenue / gateway-settle / audit-log:purge, and
 * `accounting:verify` is only the RV/PV invariant checker. This command is
 * new.
 *
 * NAMED `legacy:parity`, NOT `accounting:parity`. The plan sketched the
 * latter, but every command that reads or writes the quarantined
 * legacy_pilot connection already lives in the `legacy:` namespace
 * (legacy:load, legacy:audit-masters, legacy:import-coa), and this one
 * cannot function without that connection. Putting a staging-only,
 * anchor-comparing command under `accounting:` would advertise it on the
 * production command list next to the engine's own operational commands --
 * exactly the confusion PLAN.md §6's "stays staging-only, permanently"
 * rule exists to prevent. The capability handed to the soud side (§7 item
 * 3) is the comparison mode, not the string.
 *
 * EXIT CODE. Non-zero on FAIL, per the task's contract, so a CI or cron
 * caller can gate on it. But PLAN.md §5.1 R9 warns that every command in
 * this codebase exits 0 while achieving nothing -- so the OUTPUT is the
 * gate: this command prints the per-check table, the skipped checks with
 * their reasons, and the pass line it was measured against, and it treats
 * a SKIPPED check as neither pass nor fail (it is listed, loudly).
 */
class LegacyParityCommand extends Command
{
    protected $signature = 'legacy:parity
                            {--as-of= : Comparison date (default: legacy_pilot.parity.as_of)}
                            {--anchor=all : opening|closing|pl|ar|ap|all}
                            {--report= : Write the markdown report here (a relative path resolves under storage/app)}
                            {--company= : Company id (default: legacy_pilot.default_company_id)}
                            {--no-persist : Do not write parity_run / parity_diff rows}';

    protected $description = 'Compare the replayed Akeed ledger against the legacy export anchors (LP4 parity harness).';

    public function handle(ParityHarness $harness, ParityReportWriter $writer): int
    {
        $config = (array) config('legacy_pilot.parity', []);

        $anchor = (string) $this->option('anchor');
        $valid = array_merge(array_keys((array) ($config['anchors'] ?? [])), [ParityHarness::ANCHOR_ALL]);

        if (! in_array($anchor, $valid, true)) {
            $this->error("Unknown --anchor='{$anchor}'. Expected one of: ".implode(', ', $valid).'.');

            return self::INVALID;
        }

        $asOf = CarbonImmutable::parse((string) ($this->option('as-of') ?: ($config['as_of'] ?? '2025-12-31')));
        $companyId = (int) ($this->option('company') ?: config('legacy_pilot.default_company_id', 1));

        $this->info(sprintf('legacy:parity — company %d, as of %s, anchor %s', $companyId, $asOf->toDateString(), $anchor));

        $result = $harness->run($companyId, $asOf, $anchor);

        // Redact BEFORE anything is printed, not only before it is written:
        // the console is an artifact too (it lands in agent transcripts and
        // CI logs). The report is rendered from the redacted structure so
        // the two can never disagree.
        $redacted = $writer->redact($result);

        $this->newLine();
        $this->table(
            ['Check', 'Status', 'Compared', 'Diffs'],
            array_map(fn ($check) => [
                $check['title'],
                strtoupper((string) $check['status']),
                (string) $check['compared'],
                (string) count($check['diffs']),
            ], $redacted['checks']),
        );

        foreach ($redacted['checks'] as $check) {
            if ($check['status'] === 'skipped') {
                $this->warn('SKIPPED — '.$check['title'].': '.($check['summary']['reason'] ?? 'no reason recorded'));
            }
        }

        foreach ($redacted['checks'] as $check) {
            foreach ($check['diffs'] as $diff) {
                $this->line(sprintf(
                    '  [%s] %s %s: legacy=%s akeed=%s delta=%s (%s)',
                    $check['key'],
                    $diff['acc_code'] ?? '—',
                    $diff['dimension'] ?? '—',
                    $this->amount($diff['legacy_value'] ?? null),
                    $this->amount($diff['akeed_value'] ?? null),
                    $this->amount($diff['delta'] ?? null),
                    $diff['classification'],
                ));
            }
        }

        [$markdownPath, $jsonPath] = $this->writeReports($writer, $redacted);

        if (! $this->option('no-persist')) {
            $runId = $harness->persist($result, $markdownPath, $jsonPath);
            $this->line("parity_run id={$runId} (run_key={$result['run_key']})");
        }

        $this->newLine();

        if ($result['status'] === 'pass') {
            $this->info(sprintf(
                'PASS — %d check(s), %d account(s) compared, 0 differences at 3 dp. (%d check(s) SKIPPED — a skipped check is not a passed check.)',
                $result['checks_total'],
                $result['accounts_compared'],
                $result['checks_skipped'],
            ));

            return self::SUCCESS;
        }

        $this->error(sprintf(
            'FAIL — %d of %d check(s) failed, %d difference(s) at 3 dp. Pass line is PLAN.md §5.2 O5: exact 0.001 KWD per account, counts matching per type per month, zero unclassified.',
            $result['checks_failed'],
            $result['checks_total'],
            $result['accounts_out_of_tolerance'],
        ));

        return self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $redacted
     * @return array{0: string, 1: string}
     */
    private function writeReports(ParityReportWriter $writer, array $redacted): array
    {
        $requested = (string) ($this->option('report') ?? '');

        if ($requested === '') {
            $dir = (string) config('legacy_pilot.parity.report_dir', 'legacy-parity');
            $requested = $dir.'/parity-'.$redacted['as_of'].'-'.$redacted['run_key'].'.md';
        }

        $markdownPath = $this->resolvePath($requested);
        $jsonPath = preg_replace('/\.md$/i', '', $markdownPath).'.json';

        File::ensureDirectoryExists(dirname($markdownPath));

        File::put($markdownPath, $writer->toMarkdown($redacted));
        File::put($jsonPath, $writer->toJson($redacted));

        $this->line('report: '.$markdownPath);
        $this->line('json:   '.$jsonPath);

        return [$markdownPath, $jsonPath];
    }

    private function resolvePath(string $path): string
    {
        $normalised = str_replace('\\', '/', $path);

        // An absolute path (drive letter or leading slash) is used as
        // given; anything else lands under storage/app, never the repo.
        if (preg_match('#^([A-Za-z]:/|/)#', $normalised) === 1) {
            return $normalised;
        }

        return storage_path('app/'.ltrim($normalised, '/'));
    }

    private function amount(mixed $value): string
    {
        return $value === null ? '—' : number_format((float) $value, 3, '.', '');
    }
}
