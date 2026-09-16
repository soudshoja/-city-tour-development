<?php

declare(strict_types=1);

namespace App\Services\Onboarding\Parity;

use RuntimeException;

/**
 * legacy-ledger-pilot LP4 -- writes the parity result as a machine-readable
 * JSON document and a human-readable markdown report.
 *
 * ------------------------------------------------------------------------
 * REDACTION IS A HARD GATE, NOT A COURTESY
 * ------------------------------------------------------------------------
 * PLAN.md §6 gate 5 requires "zero PII tripwire hits across the whole run",
 * and unlike every other artefact in this phase a parity REPORT is designed
 * to leave the machine -- it is what the owner reads, what LP6 quotes, and
 * what §7 proposes handing to the soud side as a capability description. So
 * this writer:
 *
 *   1. DROPS every value under a name-shaped key (name, party_name,
 *      partner_name, emp_name, description, narration, remarks, email,
 *      address, phone) anywhere in the structure, at any depth. The pilot's
 *      own data is pseudonymised already (PARTY-<id> / EMP-<id>, PLAN.md
 *      §5.2 O9), so nothing legitimate is lost -- but "the source is
 *      already clean" is an assumption about an input, and this is the last
 *      place that assumption can be checked.
 *
 *   2. RENDERS a party as `PARTY-<id>` and never as anything else. A bare
 *      integer party id in a report is not identifying, but it is also not
 *      readable; the PARTY- form is the phase's own vocabulary.
 *
 *   3. THROWS on an email-shaped string surviving the sweep. Refusing to
 *      write is the correct failure mode: a report that has already been
 *      written cannot be un-shared. This mirrors LP0.4's tripwire, which
 *      HALTS the phase rather than warning.
 *
 * The markdown report is generated from the redacted structure, never from
 * the raw one, so the two can never disagree about what was redacted.
 */
final class ParityReportWriter
{
    /**
     * Keys whose values never appear in a report, at any nesting depth.
     */
    private const REDACTED_KEYS = [
        'name', 'account_name', 'party_name', 'partner_name', 'partnername',
        'emp_name', 'empname', 'employee_name', 'customer_name', 'supplier_name',
        'description', 'narration', 'remarks', 'internal_remarks',
        'email', 'address', 'phone', 'mobile', 'contact',
    ];

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed> the REDACTED structure actually written
     */
    public function redact(array $result): array
    {
        /** @var array<string, mixed> $redacted */
        $redacted = $this->walk($result);

        $encoded = json_encode($redacted, JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            throw new RuntimeException('Parity result could not be encoded for the redaction tripwire — refusing to write a report that was not checked.');
        }

        if (preg_match('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', $encoded, $match) === 1) {
            throw new RuntimeException("PII TRIPWIRE: an email-shaped value ('{$match[0]}') survived redaction. Refusing to write the parity report — see PLAN.md §6 gate 5.");
        }

        return $redacted;
    }

    private function walk(mixed $value, ?string $key = null): mixed
    {
        if (is_array($value)) {
            $out = [];

            foreach ($value as $k => $v) {
                $normalisedKey = is_string($k) ? strtolower($k) : $k;

                if (is_string($normalisedKey) && in_array($normalisedKey, self::REDACTED_KEYS, true)) {
                    // Dropped outright rather than replaced with a
                    // placeholder: a "[redacted]" marker in a diff table
                    // invites the next reader to go and look it up.
                    continue;
                }

                if ($normalisedKey === 'party_id' && $v !== null) {
                    $out['party'] = 'PARTY-'.$v;

                    continue;
                }

                $out[$k] = $this->walk($v, is_string($normalisedKey) ? $normalisedKey : null);
            }

            return $out;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $result  the ALREADY-REDACTED structure
     */
    public function toJson(array $result): string
    {
        $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException('Parity result could not be encoded to JSON.');
        }

        return $json."\n";
    }

    /**
     * @param  array<string, mixed>  $result  the ALREADY-REDACTED structure
     */
    public function toMarkdown(array $result): string
    {
        $status = strtoupper((string) $result['status']);
        $lines = [];

        $lines[] = '# Legacy-ledger parity report — '.$status;
        $lines[] = '';
        $lines[] = '| | |';
        $lines[] = '|---|---|';
        $lines[] = '| Run | `'.$result['run_key'].'` |';
        $lines[] = '| Company | '.$result['company_id'].' |';
        $lines[] = '| As of | '.$result['as_of'].' |';
        $lines[] = '| Period start | '.$result['period_start'].' |';
        $lines[] = '| Anchor scope | '.$result['anchor'].' |';
        $lines[] = '| Checks | '.$result['checks_total'].' total · '.$result['checks_failed'].' failed · '.$result['checks_skipped'].' skipped |';
        $lines[] = '| Accounts compared | '.$result['accounts_compared'].' |';
        $lines[] = '| Diffs | '.$result['accounts_out_of_tolerance'].' |';
        $lines[] = '';
        $lines[] = '**Pass line (PLAN.md §5.2 O5, ratified):** exact at 3 decimal places (0.001 KWD) per '
            .'account for the opening and closing trial balances, document counts matching per type per '
            .'month, and zero unclassified documents.';
        $lines[] = '';

        $lines[] = '## Definitions used';
        $lines[] = '';

        foreach ((array) $result['definitions'] as $key => $definition) {
            $lines[] = '- **'.$key.'** — '.$definition;
        }

        $lines[] = '';
        $lines[] = '## Checks';
        $lines[] = '';
        $lines[] = '| Check | Status | Compared | Diffs |';
        $lines[] = '|---|---|---:|---:|';

        foreach ((array) $result['checks'] as $check) {
            $lines[] = sprintf('| %s | **%s** | %d | %d |', $check['title'], strtoupper((string) $check['status']), $check['compared'], count($check['diffs']));
        }

        $lines[] = '';

        foreach ((array) $result['checks'] as $check) {
            $lines[] = '### '.$check['title'].' — '.strtoupper((string) $check['status']);
            $lines[] = '';

            foreach ((array) $check['summary'] as $key => $value) {
                $lines[] = '- `'.$key.'`: '.(is_scalar($value) || $value === null ? var_export($value, true) : json_encode($value, JSON_UNESCAPED_SLASHES));
            }

            $lines[] = '';

            if ($check['diffs'] === []) {
                $lines[] = '_No differences._';
                $lines[] = '';

                continue;
            }

            $lines[] = '| Their code | Our account | Party | Dimension | Legacy | Akeed | Delta | Classification |';
            $lines[] = '|---|---:|---|---|---:|---:|---:|---|';

            foreach ($check['diffs'] as $diff) {
                $lines[] = sprintf(
                    '| %s | %s | %s | %s | %s | %s | %s | `%s` |',
                    $diff['acc_code'] ?? '—',
                    $diff['account_id'] ?? '—',
                    $diff['party'] ?? '—',
                    $diff['dimension'] ?? '—',
                    $this->amount($diff['legacy_value'] ?? null),
                    $this->amount($diff['akeed_value'] ?? null),
                    $this->amount($diff['delta'] ?? null),
                    $diff['classification'],
                );
            }

            $lines[] = '';
            $lines[] = '<details><summary>Detail</summary>';
            $lines[] = '';

            foreach ($check['diffs'] as $diff) {
                $lines[] = '- **'.($diff['acc_code'] ?? $diff['dimension'] ?? 'diff').'** — '.($diff['detail'] ?? '');
            }

            $lines[] = '';
            $lines[] = '</details>';
            $lines[] = '';
        }

        $lines[] = '---';
        $lines[] = '';
        $lines[] = 'Drill down on any account above with:';
        $lines[] = '';
        $lines[] = '```';
        $lines[] = 'php artisan legacy:parity-diff --account=<AccCode>';
        $lines[] = '```';
        $lines[] = '';
        $lines[] = '_No personal data appears in this report: account codes, pooled party ids as `PARTY-<id>`, '
            .'counts and amounts only (PLAN.md §6 gate 5)._';
        $lines[] = '';

        return implode("\n", $lines);
    }

    private function amount(mixed $value): string
    {
        return $value === null ? '—' : number_format((float) $value, 3, '.', '');
    }
}
