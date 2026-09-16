<?php

declare(strict_types=1);

namespace App\Console\Commands\Legacy;

use App\Services\Onboarding\Parity\AccountDrillDown;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * legacy-ledger-pilot LP4 -- the drill-down half of the parity deliverable.
 *
 * PLAN.md §LP4: "a total that is off by 0.003 is useless without the two
 * documents that caused it". `legacy:parity` names the account; this
 * command names the documents on both sides of it.
 *
 * Prints THEIR lines (legacy AccDetailID / DocID / DocNo / SubType / DocDt)
 * and OURS (journal entry id / transaction id / reference_number /
 * idempotency key), plus each side's net and the delta between them, so a
 * per-account residual can be walked to the document that produced it in
 * one step.
 *
 * No amounts are redacted here and none need to be: this command prints
 * account codes, document ids, dates and figures -- there is no name field
 * on either side of the comparison, and the pooled party appears only as
 * its numeric id (rendered PARTY-<id>).
 */
class LegacyParityDiffCommand extends Command
{
    protected $signature = 'legacy:parity-diff
                            {--account= : The LEGACY AccCode to drill into (required)}
                            {--as-of= : Include lines up to this date (default: legacy_pilot.parity.as_of)}
                            {--company= : Company id (default: legacy_pilot.default_company_id)}
                            {--limit=500 : Max lines listed per side}';

    protected $description = 'List the legacy lines and the Akeed journal lines behind one account (LP4 parity drill-down).';

    public function handle(AccountDrillDown $drillDown): int
    {
        $accCode = (string) ($this->option('account') ?? '');

        if (trim($accCode) === '') {
            $this->error('--account=<AccCode> is required. Use the LEGACY account code as it appears in the parity report.');

            return self::INVALID;
        }

        $asOf = CarbonImmutable::parse((string) ($this->option('as-of') ?: config('legacy_pilot.parity.as_of', '2025-12-31')));
        $companyId = (int) ($this->option('company') ?: config('legacy_pilot.default_company_id', 1));
        $limit = max(1, (int) $this->option('limit'));

        try {
            $result = $drillDown->forAccountCode($companyId, trim($accCode), $asOf, $limit);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'legacy:parity-diff — AccCode %s (legacy Acc_ID %d) → account_id %s, resolution=%s%s, as of %s',
            $result['acc_code'],
            $result['legacy_acc_id'],
            $result['account_id'] === null ? 'NULL' : (string) $result['account_id'],
            $result['resolution'],
            $result['party_id'] === null ? '' : ', PARTY-'.$result['party_id'],
            $result['as_of'],
        ));

        if (! $result['map_document_line_available']) {
            $this->warn('map_document_line is not present on this database (LP3 has not run here) — our lines are tied to legacy documents by idempotency key only.');
        }

        $this->newLine();
        $this->line('LEGACY lines ('.count($result['legacy_lines']).'):');
        $this->table(
            ['AccDetailID', 'DocID', 'DocNo', 'SubType', 'DocDt', 'Posted', 'DC', 'Debit', 'Credit'],
            array_map(fn ($line) => [
                (string) ($line['legacy_acc_detail_id'] ?? '—'),
                (string) ($line['legacy_doc_id'] ?? '—'),
                (string) ($line['legacy_doc_no'] ?? '—'),
                (string) ($line['sub_type'] ?? '—'),
                (string) ($line['doc_date'] ?? '—'),
                // `posted` is a bool|null after LegacyStagingCast::toBool();
                // (string) false is '' and would render as a blank cell that
                // reads like "no data" rather than "not posted".
                $line['posted'] === null ? '—' : ($line['posted'] ? 'yes' : 'NO'),
                (string) ($line['dc'] ?? '—'),
                $this->amount($line['debit']),
                $this->amount($line['credit']),
            ], $result['legacy_lines']),
        );

        $this->newLine();
        $this->line('AKEED lines ('.count($result['akeed_lines']).'):');
        $this->table(
            ['JE id', 'Txn id', 'Idempotency key', 'Ref', 'SubType', 'Txn date', 'Posting date', 'Party', 'Legacy AccDetailID', 'Debit', 'Credit'],
            array_map(fn ($line) => [
                (string) $line['journal_entry_id'],
                (string) ($line['transaction_id'] ?? '—'),
                (string) ($line['idempotency_key'] ?? '—'),
                (string) ($line['reference_number'] ?? '—'),
                (string) ($line['sub_type'] ?? '—'),
                (string) ($line['transaction_date'] ?? '—'),
                (string) ($line['posting_date'] ?? '—'),
                $line['party_id'] === null ? '—' : 'PARTY-'.$line['party_id'],
                (string) ($line['legacy_acc_detail_id'] ?? '—'),
                $this->amount($line['debit']),
                $this->amount($line['credit']),
            ], $result['akeed_lines']),
        );

        $this->newLine();
        $this->line(sprintf(
            'legacy net %s · akeed net %s · delta %s',
            $this->amount($result['legacy_net']),
            $this->amount($result['akeed_net']),
            $this->amount($result['delta']),
        ));

        return self::SUCCESS;
    }

    private function amount(mixed $value): string
    {
        return $value === null ? '—' : number_format((float) $value, 3, '.', '');
    }
}
