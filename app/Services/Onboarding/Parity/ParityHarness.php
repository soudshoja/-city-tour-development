<?php

declare(strict_types=1);

namespace App\Services\Onboarding\Parity;

use App\Services\Onboarding\LegacyPathGuard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * legacy-ledger-pilot LP4 -- the parity harness.
 *
 * Compares the replayed Akeed ledger against the legacy system's own
 * exported anchors and returns a machine-readable result. `legacy:parity`
 * is a thin CLI wrapper over this class (the same shape AccountingVerify
 * has over RvPvInvariantChecker) so every check is testable against its
 * return value rather than against console text.
 *
 * ------------------------------------------------------------------------
 * THE PASS LINE (PLAN.md §5.2 O5, RATIFIED 2026-09-07)
 * ------------------------------------------------------------------------
 * "PASS is exact at 3 decimals per account (0.001 KWD) for both the opening
 * and the closing TB, per-type/per-month document counts must match, and
 * there must be zero unclassified documents."
 *
 * Implemented literally: both sides are rounded to 3 dp FIRST and then
 * compared with a 0.0005 epsilon that exists only to absorb IEEE-754 noise
 * in the rounded values. There is no per-account slack, no "close enough",
 * and no tolerance ledger. An account off by 0.001 fails and is named.
 *
 * ------------------------------------------------------------------------
 * THE THREE FOLDS (MAPPING-RULES §9.1)
 * ------------------------------------------------------------------------
 * Their chart has 1,351 accounts; ours has fewer, because LP1 folded party
 * leaves onto RECEIVABLE_CONTROL / PAYABLE_CONTROL and eleven yearly
 * "Profit & Loss Account <year>" leaves onto one RETAINED_EARNINGS. The
 * comparison therefore runs THEIR side GROUPED BY OUR account_id: every
 * anchor row is resolved through legacy_acc_map and summed onto the Akeed
 * account it folded to. That single grouping implements folds 1 (party
 * pooling, at the pooled level) and 2 (retained earnings) without either
 * being special-cased, because legacy_acc_map already records where each
 * leaf went. Fold 3 (refunds-payable excluded from the payable pool,
 * decision O8-refunds) needs nothing here at all: LP1 imports those leaves
 * as `direct`, so they group one-to-one.
 *
 * The DECOMPOSED level of fold 1 -- proving the pooling lost nothing -- is
 * the separate ar/ap check, which reverses the fold per party through
 * map_party and compares leaf by leaf.
 *
 * ------------------------------------------------------------------------
 * WHAT A FAILING RUN MUST STILL PRODUCE
 * ------------------------------------------------------------------------
 * Every check runs even after an earlier one fails (PLAN.md §5.2 O4:
 * tag-and-continue at the single-document level; a first-failure abort
 * would hide the other nine checks and force a re-run per defect). Failures
 * accumulate into diffs; the overall status is the AND of every check.
 */
final class ParityHarness
{
    public const ANCHOR_ALL = 'all';

    public function __construct(
        private readonly AnchorReader $anchors,
        private readonly LedgerFigures $ledger,
        private readonly LegacyMapReader $maps,
        private readonly DocumentCensus $census,
        private readonly VerifyScoper $verify,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(int $companyId, CarbonImmutable $asOf, string $anchor = self::ANCHOR_ALL): array
    {
        LegacyPathGuard::assertQuarantinedConnection('legacy_pilot');

        $config = (array) config('legacy_pilot.parity', []);
        $periodStart = CarbonImmutable::parse((string) ($config['period_start'] ?? '2025-01-01'));
        $openingSubType = (string) ($config['opening_sub_type'] ?? 'LEGACY_OJV');
        $legacyPrefix = (string) ($config['legacy_sub_type_prefix'] ?? 'LEGACY_');
        $decimals = (int) ($config['decimals'] ?? 3);
        $tolerance = (float) ($config['tolerance'] ?? 0.0005);
        $anchorConfig = (array) ($config['anchors'] ?? []);

        $runKey = (string) Str::ulid();
        $startedAt = now();

        $figures = $this->ledger->perAccount($companyId, $periodStart, $asOf, $openingSubType);
        $accountMap = $this->maps->accountMapByCode($companyId);

        $checks = [];

        if ($this->wants($anchor, 'opening')) {
            $checks[] = $this->openingCheck($anchorConfig, $accountMap, $figures, $decimals, $tolerance);
        }

        if ($this->wants($anchor, 'closing')) {
            $checks[] = $this->closingCheck($anchorConfig, $accountMap, $figures, $decimals, $tolerance, $config);
        }

        if ($this->wants($anchor, 'pl')) {
            $checks[] = $this->profitLossCheck($anchorConfig, $accountMap, $companyId, $periodStart, $asOf, $openingSubType, $decimals, $tolerance);
        }

        $partyResolutions = ['ar' => LegacyMapReader::RESOLUTION_POOLED_RECEIVABLE, 'ap' => LegacyMapReader::RESOLUTION_POOLED_PAYABLE];

        // The closing trial-balance anchor is the per-leaf figure the party
        // checks fall back to (LP4b bucket B2), so it is read even when the
        // caller narrowed to --anchor=ar. Read once, not per check.
        $closingByLegacyAccId = [];

        foreach (array_keys($partyResolutions) as $key) {
            if ($this->wants($anchor, $key)) {
                $closingByLegacyAccId = $this->closingAnchorByLegacyAccId($anchorConfig);

                break;
            }
        }

        foreach ($partyResolutions as $key => $resolution) {
            if ($this->wants($anchor, $key)) {
                $checks[] = $this->partyCheck($key, $resolution, $anchorConfig, $accountMap, $figures, $closingByLegacyAccId, $companyId, $asOf, $decimals, $tolerance);
            }
        }

        // The four non-anchor checks always run: they are properties of the
        // replay itself, not of one anchor, and a caller narrowing to
        // --anchor=closing still needs to know its posting dates held.
        $checks[] = $this->documentCountCheck($companyId, (int) $asOf->year);
        $checks[] = $this->postingDateCheck($companyId, $legacyPrefix);
        $checks[] = $this->verifyCheck($companyId, $config, $legacyPrefix);

        $failed = array_values(array_filter($checks, fn ($c) => $c['status'] === 'fail'));
        $skipped = array_values(array_filter($checks, fn ($c) => $c['status'] === 'skipped'));

        $accountsCompared = 0;
        $accountsOut = 0;

        foreach ($checks as $check) {
            $accountsCompared += (int) ($check['compared'] ?? 0);
            $accountsOut += count($check['diffs'] ?? []);
        }

        return [
            'run_key' => $runKey,
            'company_id' => $companyId,
            'as_of' => $asOf->toDateString(),
            'period_start' => $periodStart->toDateString(),
            'anchor' => $anchor,
            'status' => $failed === [] ? 'pass' : 'fail',
            'started_at' => $startedAt->toIso8601String(),
            'finished_at' => now()->toIso8601String(),
            'definitions' => [
                'source' => 'App\\Services\\TrialBalanceService (date basis, leaf test, YEC exclusion); README-MANIFEST.md lines 49-51, 81-83 (anchor definitions)',
                'opening' => 'SUM(debit - credit) over journal_entries where COALESCE(posting_date, transaction_date) < '.$periodStart->toDateString().' OR the line belongs to an opening-journal document (sub_type='.$openingSubType.') dated on or before '.$asOf->toDateString().' — see LedgerFigures::openingNet(), "the OJV date trap"',
                'period' => 'SUM(debit), SUM(credit) over journal_entries where COALESCE(posting_date, transaction_date) BETWEEN '.$periodStart->toDateString().' AND '.$asOf->toDateString().', excluding whole doc_type=YEC and whole sub_type='.$openingSubType.' documents',
                'closing' => 'opening + period_dr - period_cr (debit-positive, per README-MANIFEST.md line 51 — NOT normal-side signed)',
                'tolerance' => 'both sides rounded to '.$decimals.' dp, compared with epsilon '.$tolerance.' (PLAN.md §5.2 O5, exact at 0.001 KWD)',
            ],
            'checks' => $checks,
            'checks_total' => count($checks),
            'checks_failed' => count($failed),
            'checks_skipped' => count($skipped),
            'accounts_compared' => $accountsCompared,
            'accounts_out_of_tolerance' => $accountsOut,
        ];
    }

    private function wants(string $anchor, string $key): bool
    {
        return $anchor === self::ANCHOR_ALL || $anchor === $key;
    }

    /**
     * Opening anchor (2024-12-31): their ClosingNet is the position our
     * replayed opening journal alone must reproduce. Their own file has no
     * period activity by definition (README-MANIFEST.md line 50: "2024-12-31
     * file: none"), which is asserted rather than assumed -- a 2024 file
     * carrying period movement would mean a different file was staged into
     * this slot.
     *
     * @param  array<string, array<string, mixed>>  $accountMap
     * @param  array<int, array<string, mixed>>  $figures
     * @return array<string, mixed>
     */
    private function openingCheck(array $anchorConfig, array $accountMap, array $figures, int $decimals, float $tolerance): array
    {
        $spec = $anchorConfig['opening'] ?? null;

        if ($spec === null) {
            return $this->skippedCheck('tb_opening', 'Opening trial balance (2024-12-31)', 'No `opening` anchor is configured in legacy_pilot.parity.anchors.');
        }

        if (! $this->anchors->hasTable((string) $spec['table'])) {
            return $this->skippedCheck('tb_opening', 'Opening trial balance (2024-12-31)', 'Anchor table '.$spec['table'].' is not staged — run `php artisan legacy:load`.');
        }

        $rows = $this->anchors->trialBalance((string) $spec['table'], isset($spec['rows']) ? (int) $spec['rows'] : null);

        $diffs = [];

        foreach ($rows as $row) {
            if (abs($row['period_dr']) > $tolerance || abs($row['period_cr']) > $tolerance) {
                $diffs[] = [
                    'check_key' => 'tb_opening',
                    'acc_code' => (string) $row['acc_code'],
                    'legacy_acc_id' => $row['legacy_acc_id'],
                    'account_id' => null,
                    'dimension' => 'period_dr',
                    'legacy_value' => $row['period_dr'],
                    'akeed_value' => 0.0,
                    'delta' => $row['period_dr'],
                    'classification' => 'anchor_defect',
                    'detail' => 'The 2024-12-31 anchor must carry no period activity (README-MANIFEST.md line 50). A file with movement was staged into the opening slot.',
                ];
            }
        }

        $legacy = $this->groupByOurAccount($rows, $accountMap, 'tb_opening', ['closing_net' => 'opening'], $diffs);

        $ours = [];

        foreach ($figures as $accountId => $figure) {
            $ours[$accountId] = ['opening' => $figure['opening_net']];
        }

        $compared = $this->compareByAccount('tb_opening', $legacy, $ours, $figures, $decimals, $tolerance, $diffs);

        return $this->check('tb_opening', 'Opening trial balance (2024-12-31)', $diffs, $compared, [
            'anchor_rows' => count($rows),
            'legacy_closing_sum' => round(array_sum(array_column($rows, 'closing_net')), $decimals),
        ]);
    }

    /**
     * Closing anchor (2025-12-31): all four dimensions, plus the aggregate
     * Dr = Cr assertion and the "closing sums to 0.000" identity that
     * PLAN.md §5.0 row 4 puts in front of the owner.
     *
     * @param  array<string, array<string, mixed>>  $accountMap
     * @param  array<int, array<string, mixed>>  $figures
     * @return array<string, mixed>
     */
    private function closingCheck(array $anchorConfig, array $accountMap, array $figures, int $decimals, float $tolerance, array $config): array
    {
        $spec = $anchorConfig['closing'] ?? null;

        if ($spec === null) {
            return $this->skippedCheck('tb_closing', 'Closing trial balance', 'No `closing` anchor is configured in legacy_pilot.parity.anchors.');
        }

        if (! $this->anchors->hasTable((string) $spec['table'])) {
            return $this->skippedCheck('tb_closing', 'Closing trial balance', 'Anchor table '.$spec['table'].' is not staged — run `php artisan legacy:load`.');
        }

        $rows = $this->anchors->trialBalance((string) $spec['table'], isset($spec['rows']) ? (int) $spec['rows'] : null);

        $diffs = [];

        $legacy = $this->groupByOurAccount($rows, $accountMap, 'tb_closing', [
            'opening_net' => 'opening',
            'period_dr' => 'period_dr',
            'period_cr' => 'period_cr',
            'closing_net' => 'closing',
        ], $diffs);

        $ours = [];

        foreach ($figures as $accountId => $figure) {
            $ours[$accountId] = [
                'opening' => $figure['opening_net'],
                'period_dr' => $figure['period_dr'],
                'period_cr' => $figure['period_cr'],
                'closing' => $figure['closing_net'],
            ];
        }

        $compared = $this->compareByAccount('tb_closing', $legacy, $ours, $figures, $decimals, $tolerance, $diffs);

        // ---- aggregates -------------------------------------------------
        $legacyDr = round(array_sum(array_column($rows, 'period_dr')), $decimals);
        $legacyCr = round(array_sum(array_column($rows, 'period_cr')), $decimals);
        $legacyClosingSum = round(array_sum(array_column($rows, 'closing_net')), $decimals);

        $ourDr = round(array_sum(array_column($figures, 'period_dr')), $decimals);
        $ourCr = round(array_sum(array_column($figures, 'period_cr')), $decimals);
        $ourClosingSum = round(array_sum(array_column($figures, 'closing_net')), $decimals);

        $aggregates = [
            ['dimension' => 'aggregate_period_dr', 'legacy' => $legacyDr, 'akeed' => $ourDr],
            ['dimension' => 'aggregate_period_cr', 'legacy' => $legacyCr, 'akeed' => $ourCr],
            ['dimension' => 'aggregate_closing_sum', 'legacy' => $legacyClosingSum, 'akeed' => $ourClosingSum],
        ];

        foreach ($aggregates as $aggregate) {
            if (abs($aggregate['legacy'] - $aggregate['akeed']) > $tolerance) {
                $diffs[] = [
                    'check_key' => 'tb_closing',
                    'acc_code' => null,
                    'legacy_acc_id' => null,
                    'account_id' => null,
                    'dimension' => $aggregate['dimension'],
                    'legacy_value' => $aggregate['legacy'],
                    'akeed_value' => $aggregate['akeed'],
                    'delta' => round($aggregate['akeed'] - $aggregate['legacy'], $decimals),
                    'classification' => 'aggregate_mismatch',
                    'detail' => 'Consolidated aggregate does not reconcile.',
                ];
            }
        }

        // Our OWN books must balance, independently of any anchor. Two
        // separate identities, because they catch different corruptions and
        // neither implies the other:
        //
        //   period Dr = period Cr   catches a half-posted 2025 document;
        //   Σ closing = 0.000       catches a one-sided OPENING line, which
        //                           leaves period movement perfectly
        //                           balanced and still puts the books out.
        //
        // PLAN.md §5.0 rows 4-5 put both in front of the owner as "closing
        // net across all accounts = 0.000".
        $selfChecks = [
            ['dimension' => 'our_dr_equals_cr', 'value' => round($ourDr - $ourCr, $decimals), 'detail' => 'Our own replayed period movement does not balance: SUM(debit) != SUM(credit).'],
            ['dimension' => 'our_closing_sums_to_zero', 'value' => $ourClosingSum, 'detail' => 'Our closing net across every account is not 0.000 — the replayed books do not balance (a one-sided opening line does exactly this while leaving period Dr = Cr intact).'],
        ];

        foreach ($selfChecks as $selfCheck) {
            if (abs($selfCheck['value']) > $tolerance) {
                $diffs[] = [
                    'check_key' => 'tb_closing',
                    'acc_code' => null,
                    'legacy_acc_id' => null,
                    'account_id' => null,
                    'dimension' => $selfCheck['dimension'],
                    'legacy_value' => 0.0,
                    'akeed_value' => $selfCheck['value'],
                    'delta' => $selfCheck['value'],
                    'classification' => 'ledger_unbalanced',
                    'detail' => $selfCheck['detail'],
                ];
            }
        }

        // PLAN.md §5.0 row 4 / §1.3(2): the legacy consolidated closing
        // total. NULL disables it (synthetic fixtures).
        $expectedTotal = $config['expected_closing_total'] ?? null;

        if ($expectedTotal !== null) {
            $expected = round((float) $expectedTotal, $decimals);

            foreach (['legacy_period_dr' => $legacyDr, 'legacy_period_cr' => $legacyCr] as $label => $actual) {
                if (abs($actual - $expected) > $tolerance) {
                    $diffs[] = [
                        'check_key' => 'tb_closing',
                        'acc_code' => null,
                        'legacy_acc_id' => null,
                        'account_id' => null,
                        'dimension' => $label,
                        'legacy_value' => $actual,
                        'akeed_value' => $expected,
                        'delta' => round($actual - $expected, $decimals),
                        'classification' => 'anchor_defect',
                        'detail' => 'The STAGED closing anchor no longer sums to the figure PLAN.md §1.3 verified — the anchor changed, which must never be mistaken for a replay drift.',
                    ];
                }
            }
        }

        return $this->check('tb_closing', 'Closing trial balance', $diffs, $compared, [
            'anchor_rows' => count($rows),
            'legacy_period_dr' => $legacyDr,
            'legacy_period_cr' => $legacyCr,
            'legacy_closing_sum' => $legacyClosingSum,
            'akeed_period_dr' => $ourDr,
            'akeed_period_cr' => $ourCr,
            'akeed_closing_sum' => $ourClosingSum,
        ]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $accountMap
     * @return array<string, mixed>
     */
    private function profitLossCheck(array $anchorConfig, array $accountMap, int $companyId, CarbonImmutable $periodStart, CarbonImmutable $asOf, string $openingSubType, int $decimals, float $tolerance): array
    {
        $spec = $anchorConfig['pl'] ?? null;

        if ($spec === null) {
            return $this->skippedCheck('pl', 'Profit & loss', 'No `pl` anchor is configured in legacy_pilot.parity.anchors.');
        }

        if (! $this->anchors->hasTable((string) $spec['table'])) {
            return $this->skippedCheck('pl', 'Profit & loss', 'Anchor table '.$spec['table'].' is not staged — run `php artisan legacy:load`.');
        }

        $rows = $this->anchors->profitLoss((string) $spec['table'], isset($spec['rows']) ? (int) $spec['rows'] : null);
        $ourMovement = $this->ledger->profitAndLoss($companyId, $periodStart, $asOf, $openingSubType);

        $diffs = [];

        $legacy = $this->groupByOurAccount($rows, $accountMap, 'pl', [
            'debit' => 'debit',
            'credit' => 'credit',
            'net_income' => 'net_income',
        ], $diffs);

        $ours = [];

        // Only accounts the anchor actually names participate: the P&L
        // anchor is scoped to income (3%) and expense (4%) accounts, so
        // every other account of ours having zero P&L movement is not a
        // finding, it is the definition. An account IN the anchor with no
        // movement on our side still surfaces, via the union in
        // compareByAccount().
        foreach (array_keys($legacy) as $accountId) {
            $ours[$accountId] = [
                'debit' => $ourMovement[$accountId]['debit'] ?? 0.0,
                'credit' => $ourMovement[$accountId]['credit'] ?? 0.0,
                'net_income' => $ourMovement[$accountId]['net_income'] ?? 0.0,
            ];
        }

        $compared = $this->compareByAccount('pl', $legacy, $ours, [], $decimals, $tolerance, $diffs);

        $legacyNet = round(array_sum(array_column($rows, 'net_income')), $decimals);
        $ourNet = round(array_sum(array_map(fn ($v) => $v['net_income'], $ours)), $decimals);

        if (abs($legacyNet - $ourNet) > $tolerance) {
            $diffs[] = [
                'check_key' => 'pl',
                'acc_code' => null,
                'legacy_acc_id' => null,
                'account_id' => null,
                'dimension' => 'net_income',
                'legacy_value' => $legacyNet,
                'akeed_value' => $ourNet,
                'delta' => round($ourNet - $legacyNet, $decimals),
                'classification' => 'aggregate_mismatch',
                'detail' => 'Net profit for the period does not reconcile against profit_loss anchor.',
            ];
        }

        return $this->check('pl', 'Profit & loss', $diffs, $compared, [
            'anchor_rows' => count($rows),
            'legacy_net_income' => $legacyNet,
            'akeed_net_income' => $ourNet,
        ]);
    }

    /**
     * The consolidated CLOSING trial-balance anchor, re-keyed by THEIR
     * Acc_ID, for the party checks' per-leaf fallback.
     *
     * WHY THE PARTY CHECKS NEED IT. `ar_balances_*` / `ap_balances_*` are
     * GROUP-SCOPED reports, not "every party leaf that has a balance":
     * the AR export covers the customer-receivable family (`10904%`) and
     * the AP export covers `206%`. Real party leaves parked outside those
     * groups -- UNCLASSIFIED-274 §1's bucket B, partner-FK leaves sitting
     * under BANK ACCOUNTS / CASH IN HAND / RECEIVABLES-GENERAL, which pool
     * cross-root under rule R-b -- are absent from both party anchors AT
     * ANY BALANCE. Run #6 reported 15 of them as `missing_in_legacy` on the
     * strength of a comment ("anchors omit ZERO balances only") that is
     * simply not true of this export, while all 15 matched their own
     * closing trial-balance figure to the fils.
     *
     * The closing TB carries EVERY leaf, is already staged, and is already
     * asserted against its own `Opening + Dr - Cr` identity by
     * AnchorReader. It is therefore the honest per-leaf legacy figure for a
     * leaf the party report never covered -- and using it makes the check
     * STRICTER, not looser: those 15 leaves go from "reported without a
     * legacy figure" to "compared against one".
     *
     * This is not the trial-balance check in disguise. That check compares
     * our POOLED CONTROL total; this one compares the per-leaf
     * DECOMPOSITION of that same pool, which is exactly the "prove the
     * pooling lost nothing" job MAPPING-RULES §9.1 fold 1 (ii) gives it.
     *
     * @param  array<string, mixed>  $anchorConfig
     * @return array<int, float> legacy Acc_ID => ClosingNet
     */
    private function closingAnchorByLegacyAccId(array $anchorConfig): array
    {
        $spec = $anchorConfig['closing'] ?? null;

        if ($spec === null || ! $this->anchors->hasTable((string) $spec['table'])) {
            return [];
        }

        $out = [];

        foreach ($this->anchors->trialBalance((string) $spec['table'], isset($spec['rows']) ? (int) $spec['rows'] : null) as $row) {
            $accId = $row['legacy_acc_id'];

            if ($accId === null) {
                continue;
            }

            $out[(int) $accId] = (float) $row['closing_net'];
        }

        return $out;
    }

    /**
     * AR / AP per party leaf -- the DECOMPOSED half of fold 1
     * (MAPPING-RULES §9.3), and the only check that proves the pooling onto
     * a control account lost nothing.
     *
     * ------------------------------------------------------------------------
     * THE UNIT OF COMPARISON IS A LEGACY LEAF, NOT A PARTY (LP4b)
     * ------------------------------------------------------------------------
     * Every row compared here is one legacy leaf `Acc_ID`, and BOTH sides of
     * that row are chosen by what `legacy_acc_map` says the leaf IS. Keying
     * the comparison on "a party id decomposed out of the control account"
     * alone was the cause of all 31 party diffs in staging run #6, in two
     * distinct shapes -- neither of them a money defect (see
     * LP4B-PARTY-DIFFS-2026-09-07.md):
     *
     *  OUR side, per leaf:
     *    - `resolution` = this check's pooling resolution -> the party
     *      position decomposed out of the control account.
     *    - ANY other resolution (`direct`, `folded_*`, `synthetic`) -> that
     *      leaf's OWN account's closing net. 16 anchor rows -- the 14 AP and
     *      2 AR leaves LP1 imports `direct` because no `tblPartner` FK points
     *      at them (O8-refunds' general form; the per-branch REFUNDS PAYABLE
     *      leaves 2403003/4/5 are three of them) -- have their money on their
     *      own account and NEVER on the pool. Looking for them in the pool
     *      the rules explicitly keep them out of reported every one of them
     *      as `missing_in_akeed` against a 0.000 we never held.
     *
     *  THEIR side, per leaf, in strict precedence:
     *    1. the ar_/ap_balances anchor row, when the anchor lists the leaf;
     *    2. else the consolidated CLOSING trial-balance anchor's row for that
     *       leaf (see closingAnchorByLegacyAccId() for why absence from a
     *       party anchor does NOT mean zero);
     *    3. else 0.000 -- absent from BOTH anchors genuinely is a zero
     *       position, and only THAT is a `missing_in_legacy`.
     *
     * Every diff carries `legacy_source` naming which of the three the legacy
     * figure came from, so no reader has to infer it.
     *
     * The comparison universe is unchanged: every anchor row, plus every
     * pooled leaf on which we hold a non-zero position. A pooled leaf we hold
     * NOTHING on and no anchor lists is not a row.
     *
     * @param  array<string, array<string, mixed>>  $accountMap  legacy_acc_map keyed by THEIR AccCode
     * @param  array<int, array<string, mixed>>  $figures  LedgerFigures::perAccount(), keyed by our account_id
     * @param  array<int, float>  $closingByLegacyAccId
     * @return array<string, mixed>
     */
    private function partyCheck(
        string $key,
        string $resolution,
        array $anchorConfig,
        array $accountMap,
        array $figures,
        array $closingByLegacyAccId,
        int $companyId,
        CarbonImmutable $asOf,
        int $decimals,
        float $tolerance
    ): array {
        $title = $key === 'ar' ? 'Accounts receivable per party leaf' : 'Accounts payable per party leaf';
        $spec = $anchorConfig[$key] ?? null;

        if ($spec === null) {
            return $this->skippedCheck($key, $title, "No `{$key}` anchor is configured in legacy_pilot.parity.anchors.");
        }

        if (! $this->anchors->hasTable((string) $spec['table'])) {
            return $this->skippedCheck($key, $title, 'Anchor table '.$spec['table'].' is not staged — run `php artisan legacy:load`.');
        }

        $role = (string) ($spec['role'] ?? ($key === 'ar' ? 'customer' : 'supplier'));
        $rows = $this->anchors->partyBalances((string) $spec['table'], isset($spec['rows']) ? (int) $spec['rows'] : null);

        $controlIds = $this->maps->controlAccountIds($companyId, $resolution);
        $derived = $this->ledger->partyPositions($companyId, $controlIds, $asOf);
        $leafByParty = $this->maps->partyRoleLeafByParty($companyId, $role);

        // legacy_acc_map re-keyed by THEIR Acc_ID -- the join key every row
        // below is built on. accountMapByCode() already carries acc_id,
        // account_id and resolution, so no second read is needed.
        $mapByAccId = [];

        foreach ($accountMap as $mapRow) {
            $mapByAccId[(int) $mapRow['legacy_acc_id']] = $mapRow;
        }

        $diffs = [];

        /** @var array<int, array<string, mixed>> $entries legacy Acc_ID => row under construction */
        $entries = [];

        // ---- our side: reverse the pooling, party by party -------------
        foreach ($derived['positions'] as $partyId => $net) {
            $leafAccId = $leafByParty[$partyId] ?? null;

            if ($leafAccId === null) {
                $diffs[] = [
                    'check_key' => $key,
                    'acc_code' => null,
                    'legacy_acc_id' => null,
                    'account_id' => $controlIds[0] ?? null,
                    'party_id' => $partyId,
                    'dimension' => 'closing',
                    'legacy_value' => null,
                    'akeed_value' => round($net, $decimals),
                    'delta' => round($net, $decimals),
                    'classification' => 'party_without_role_leaf',
                    'legacy_source' => 'none',
                    'detail' => "PARTY-{$partyId} holds a position on the {$role} control account but map_party records no {$role} role account for it — the fold cannot be reversed for this party.",
                ];

                continue;
            }

            $mapRow = $mapByAccId[$leafAccId] ?? null;

            if ($mapRow === null || (string) $mapRow['acc_code'] === '') {
                $diffs[] = [
                    'check_key' => $key,
                    'acc_code' => null,
                    'legacy_acc_id' => $leafAccId,
                    'account_id' => $controlIds[0] ?? null,
                    'party_id' => $partyId,
                    'dimension' => 'closing',
                    'legacy_value' => null,
                    'akeed_value' => round($net, $decimals),
                    'delta' => round($net, $decimals),
                    'classification' => 'party_leaf_not_in_account_map',
                    'legacy_source' => 'none',
                    'detail' => "PARTY-{$partyId}'s {$role} leaf (legacy Acc_ID {$leafAccId}) has no AccCode in legacy_acc_map.",
                ];

                continue;
            }

            // Two parties sharing one leaf sum onto it rather than
            // last-one-wins; the anchor has one row for the leaf either way.
            $entries[$leafAccId]['pooled_net'] = ($entries[$leafAccId]['pooled_net'] ?? 0.0) + $net;
            $entries[$leafAccId]['party_id'] = $partyId;
        }

        foreach ($derived['undecomposable'] as $orphan) {
            $diffs[] = [
                'check_key' => $key,
                'acc_code' => null,
                'legacy_acc_id' => null,
                'account_id' => $orphan['account_id'],
                'party_id' => null,
                'dimension' => 'closing',
                'legacy_value' => null,
                'akeed_value' => round($orphan['debit'] - $orphan['credit'], $decimals),
                'delta' => round($orphan['debit'] - $orphan['credit'], $decimals),
                'classification' => 'undecomposable_pooled_line',
                'legacy_source' => 'none',
                'detail' => 'journal_entries.id='.$orphan['journal_entry_id'].' sits on a pooled control account with a NULL type_reference_id. MAPPING-RULES §9.3: this is a hard failure of the party-required rule and should not exist.',
            ];
        }

        // ---- their side: every anchor row joins onto a leaf -------------
        foreach ($rows as $code => $row) {
            $code = (string) $code;
            $leafAccId = $row['legacy_acc_id'];

            // The anchor's own AccID_FK identifies the legacy account; the
            // AccCode is the fallback when a staged file has no id column.
            if ($leafAccId === null || ! isset($mapByAccId[(int) $leafAccId])) {
                $leafAccId = isset($accountMap[$code]) ? (int) $accountMap[$code]['legacy_acc_id'] : null;
            }

            if ($leafAccId === null || ! isset($mapByAccId[(int) $leafAccId])) {
                $diffs[] = [
                    'check_key' => $key,
                    'acc_code' => $code,
                    'legacy_acc_id' => $row['legacy_acc_id'],
                    'account_id' => null,
                    'party_id' => null,
                    'dimension' => 'closing',
                    'legacy_value' => round($row['closing_net'], $decimals),
                    'akeed_value' => null,
                    'delta' => null,
                    'classification' => 'anchor_leaf_not_in_account_map',
                    'legacy_source' => $key.' anchor',
                    'detail' => "The {$key} anchor lists AccCode {$code}, but legacy_acc_map holds no row for it — LP1 never imported this leaf, so there is no Akeed side to compare it to.",
                ];

                continue;
            }

            $entries[(int) $leafAccId]['anchor_net'] = round($row['closing_net'], $decimals);
        }

        // ---- resolve every row ------------------------------------------
        $compared = 0;
        $pooledCompared = 0;
        $directCompared = 0;
        $outsideAnchorCompared = 0;

        ksort($entries);

        foreach ($entries as $leafAccId => $entry) {
            $mapRow = $mapByAccId[$leafAccId];
            $code = (string) $mapRow['acc_code'];
            $isPooled = (string) $mapRow['resolution'] === $resolution;
            $anchored = array_key_exists('anchor_net', $entry);

            if ($isPooled) {
                $ourAccountId = $controlIds[0] ?? null;
                $ourValue = round((float) ($entry['pooled_net'] ?? 0.0), $decimals);
            } else {
                // A `direct` leaf keeps its own account: its balance never
                // touched the pool, so the party decomposition cannot see it.
                $ourAccountId = $mapRow['account_id'] === null ? null : (int) $mapRow['account_id'];
                $ourValue = round((float) ($figures[$ourAccountId]['closing_net'] ?? 0.0), $decimals);
            }

            if ($anchored) {
                $legacyValue = (float) $entry['anchor_net'];
                $legacySource = $key.' anchor';
            } elseif (array_key_exists($leafAccId, $closingByLegacyAccId)) {
                $legacyValue = round($closingByLegacyAccId[$leafAccId], $decimals);
                $legacySource = 'closing trial-balance anchor';
            } else {
                $legacyValue = 0.0;
                $legacySource = 'absent from both anchors';
            }

            // An unanchored leaf we hold nothing on is not a row: it is a
            // party with no position, which asserts nothing either way.
            if (! $anchored && abs($ourValue) <= $tolerance && abs($legacyValue) <= $tolerance) {
                continue;
            }

            $compared++;

            if ($anchored) {
                $isPooled ? $pooledCompared++ : $directCompared++;
            } else {
                $outsideAnchorCompared++;
            }

            if (abs($legacyValue - $ourValue) <= $tolerance) {
                continue;
            }

            if (! $anchored && $legacySource === 'absent from both anchors') {
                $classification = 'missing_in_legacy';
                $detail = "We hold a non-zero position for this leaf and NEITHER the {$key} anchor nor the closing trial-balance anchor lists it.";
            } elseif (abs($ourValue) <= $tolerance) {
                // We hold NOTHING for this leaf while the anchor does --
                // `missing_in_akeed` means exactly that and nothing looser.
                $classification = 'missing_in_akeed';
                $detail = $isPooled
                    ? 'The anchor holds a position for this leaf; our decomposition of the pooled control account holds none.'
                    : "This leaf was imported `{$mapRow['resolution']}` (its own Akeed account, never pooled), and that account has no balance.";
            } else {
                $classification = 'balance_mismatch';
                $detail = $isPooled
                    ? 'Per-leaf party position derived from the pooled control account does not match the legacy figure.'
                    : "This leaf was imported `{$mapRow['resolution']}`, so it is compared as an ACCOUNT (its own closing net), not as a party position.";
            }

            $diffs[] = [
                'check_key' => $key,
                'acc_code' => $code,
                'legacy_acc_id' => $leafAccId,
                'account_id' => $ourAccountId,
                'party_id' => $entry['party_id'] ?? null,
                'dimension' => 'closing',
                'legacy_value' => $legacyValue,
                'akeed_value' => $ourValue,
                'delta' => round($ourValue - $legacyValue, $decimals),
                'classification' => $classification,
                'legacy_source' => $legacySource,
                'detail' => $detail.' Legacy figure read from: '.$legacySource.'.',
            ];
        }

        return $this->check($key, $title, $diffs, $compared, [
            'anchor_rows' => count($rows),
            'control_account_ids' => $controlIds,
            'parties_derived' => count($derived['positions']),
            'undecomposable_lines' => count($derived['undecomposable']),
            'pooled_leaves_compared' => $pooledCompared,
            'direct_leaves_compared' => $directCompared,
            'outside_party_anchor_compared' => $outsideAnchorCompared,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function documentCountCheck(int $companyId, int $year): array
    {
        $result = $this->census->compare($companyId, $year);

        if ($result['status'] === 'skipped') {
            return $this->skippedCheck('doc_counts', 'Document counts per SubType per month', (string) $result['reason']);
        }

        $diffs = [];

        foreach ($result['rows'] as $row) {
            if ($row['matches']) {
                continue;
            }

            $diffs[] = [
                'check_key' => 'doc_counts',
                'acc_code' => null,
                'legacy_acc_id' => null,
                'account_id' => null,
                'dimension' => 'count',
                'legacy_value' => (float) $row['legacy_posted'],
                'akeed_value' => (float) $row['akeed_accounted_for'],
                'delta' => (float) $row['delta'],
                'classification' => match (true) {
                    $row['refused'] > 0 => 'refused_documents',
                    $row['unposted_delta'] !== 0 => 'unposted_count_mismatch',
                    default => 'count_mismatch',
                },
                'detail' => sprintf(
                    '%s %s: legacy posted %d, accounted for %d (%s); legacy unposted %d vs our unposted-status rows %d; refused %d. MAPPING-RULES §9.4 check 4 requires replayed + skipped + excluded + refused == census, and O5 requires refused == 0.',
                    $row['sub_type'],
                    $row['month'],
                    $row['legacy_posted'],
                    $row['akeed_accounted_for'],
                    json_encode($row['by_status']),
                    $row['legacy_unposted'],
                    $row['akeed_unposted'],
                    $row['refused'],
                ),
            ];
        }

        return $this->check('doc_counts', 'Document counts per SubType per month', $diffs, count($result['rows']), [
            'legacy_total' => $result['legacy_total'],
            'akeed_total' => $result['akeed_total'],
            'unposted_total' => $result['unposted_total'],
            'refused_total' => $result['refused_total'],
            'buckets_compared' => count($result['rows']),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function postingDateCheck(int $companyId, string $legacyPrefix): array
    {
        $result = $this->ledger->postingDateIntegrity($companyId, $legacyPrefix);

        $diffs = [];

        foreach ($result['samples'] as $sample) {
            $diffs[] = [
                'check_key' => 'posting_date',
                'acc_code' => null,
                'legacy_acc_id' => null,
                'account_id' => $sample['account_id'],
                'dimension' => 'posting_date',
                'legacy_value' => null,
                'akeed_value' => null,
                'delta' => null,
                'classification' => 'posting_date_shifted',
                'detail' => sprintf(
                    'journal_entries.id=%d (%s, transaction %s): transaction_date=%s but posting_date=%s. Check 11 requires posting_date = transaction_date on 100%% of legacy-fed lines.',
                    $sample['journal_entry_id'],
                    $sample['sub_type'],
                    $sample['transaction_id'] === null ? 'NULL' : (string) $sample['transaction_id'],
                    $sample['transaction_date'] ?? 'NULL',
                    $sample['posting_date'] ?? 'NULL',
                ),
            ];
        }

        $status = $result['shifted'] === 0 ? 'pass' : 'fail';

        return [
            'key' => 'posting_date',
            'title' => 'Posting-date integrity (check 11)',
            'status' => $status,
            'compared' => $result['total'],
            'diffs' => $diffs,
            'summary' => [
                'legacy_lines' => $result['total'],
                'shifted' => $result['shifted'],
                'samples_listed' => count($result['samples']),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function verifyCheck(int $companyId, array $config, string $legacyPrefix): array
    {
        $result = $this->verify->run(
            $companyId,
            (array) ($config['verify_overrides'] ?? []),
            (bool) ($config['verify_scope_out_cash_bank_for_legacy'] ?? true),
            $legacyPrefix,
        );

        $diffs = [];

        foreach ($result['enforced'] as $row) {
            $diffs[] = [
                'check_key' => 'verify_scope',
                'acc_code' => null,
                'legacy_acc_id' => null,
                'account_id' => null,
                'dimension' => 'invariant',
                'legacy_value' => null,
                'akeed_value' => null,
                'delta' => null,
                'classification' => 'verify_'.$row['rule'],
                'detail' => sprintf('[%s] %s', $row['sub_type'] ?? '(unknown sub_type)', $row['violation']),
            ];
        }

        return [
            'key' => 'verify_scope',
            'title' => 'accounting:verify with the O7-verify pilot overrides',
            'status' => $result['status'],
            'compared' => $result['checked'],
            'diffs' => $diffs,
            'summary' => [
                'rv_pv_documents_checked' => $result['checked'],
                'residuals_by_type' => $result['residuals_by_type'],
                'scoped_out_by_type' => $result['scoped_out_by_type'],
                'scoped_out_total' => count($result['scoped_out']),
                'unclassified_violations' => count($result['unclassified']),
                'overrides_applied' => $result['overrides_applied'],
            ],
        ];
    }

    /**
     * Resolves every anchor row through legacy_acc_map and SUMS the named
     * dimensions onto the Akeed account it folded to. This is where folds 1
     * and 2 happen; see the class docblock.
     *
     * @param  array<string, array<string, mixed>>  $rows
     * @param  array<string, array<string, mixed>>  $accountMap
     * @param  array<string, string>  $dimensions  anchor column => dimension name
     * @param  list<array<string, mixed>>  $diffs
     * @return array<int, array<string, mixed>>
     */
    private function groupByOurAccount(array $rows, array $accountMap, string $checkKey, array $dimensions, array &$diffs): array
    {
        $out = [];

        foreach ($rows as $key => $row) {
            // PHP coerces a numeric-string array key to int, so the AccCode
            // is taken from the ROW, never from the key -- a diff row
            // reporting int 1010 instead of '1010' is unjoinable against
            // legacy_acc_map.acc_code, which is a varchar.
            $code = (string) $row['acc_code'];
            $mapping = $accountMap[$key] ?? null;

            if ($mapping === null || $mapping['account_id'] === null) {
                $diffs[] = [
                    'check_key' => $checkKey,
                    'acc_code' => $code,
                    'legacy_acc_id' => $row['legacy_acc_id'] ?? null,
                    'account_id' => null,
                    'dimension' => array_values($dimensions)[0] ?? 'closing',
                    'legacy_value' => (float) ($row[array_key_first($dimensions)] ?? 0.0),
                    'akeed_value' => null,
                    'delta' => null,
                    'classification' => $mapping === null ? 'unmapped_account' : 'mapped_to_null_account',
                    'detail' => $mapping === null
                        ? "Anchor AccCode {$code} has no legacy_acc_map row — LP1 did not import this account, so nothing on our side can be compared to it."
                        : "Anchor AccCode {$code} maps to a NULL account_id in legacy_acc_map (resolution={$mapping['resolution']}).",
                ];

                continue;
            }

            $accountId = (int) $mapping['account_id'];

            foreach ($dimensions as $column => $dimension) {
                $out[$accountId][$dimension] = ($out[$accountId][$dimension] ?? 0.0) + (float) $row[$column];
            }

            $out[$accountId]['acc_codes'][] = $code;
        }

        return $out;
    }

    /**
     * Compares one dimension set per Akeed account, at exactly $decimals.
     *
     * @param  array<int, array<string, mixed>>  $legacy
     * @param  array<int, array<string, float>>  $ours
     * @param  array<int, array<string, mixed>>  $figures  our account metadata, for codes in diff rows
     * @param  list<array<string, mixed>>  $diffs
     */
    private function compareByAccount(string $checkKey, array $legacy, array $ours, array $figures, int $decimals, float $tolerance, array &$diffs): int
    {
        $accountIds = array_values(array_unique(array_merge(array_keys($legacy), array_keys($ours))));
        sort($accountIds);

        $compared = 0;

        foreach ($accountIds as $accountId) {
            $legacyRow = $legacy[$accountId] ?? [];
            $ourRow = $ours[$accountId] ?? [];
            $accCodes = $legacyRow['acc_codes'] ?? [];
            unset($legacyRow['acc_codes']);

            $dimensions = array_values(array_unique(array_merge(array_keys($legacyRow), array_keys($ourRow))));

            if ($dimensions === []) {
                continue;
            }

            $compared++;

            foreach ($dimensions as $dimension) {
                $legacyValue = round((float) ($legacyRow[$dimension] ?? 0.0), $decimals);
                $ourValue = round((float) ($ourRow[$dimension] ?? 0.0), $decimals);

                if (abs($legacyValue - $ourValue) <= $tolerance) {
                    continue;
                }

                $classification = match (true) {
                    $legacyRow === [] => 'missing_in_legacy',
                    $ourRow === [] => 'missing_in_akeed',
                    default => 'balance_mismatch',
                };

                $diffs[] = [
                    'check_key' => $checkKey,
                    'acc_code' => $accCodes === [] ? null : implode('+', $accCodes),
                    'legacy_acc_id' => null,
                    'account_id' => $accountId,
                    'our_code' => $figures[$accountId]['code'] ?? null,
                    'dimension' => $dimension,
                    'legacy_value' => $legacyValue,
                    'akeed_value' => $ourValue,
                    'delta' => round($ourValue - $legacyValue, $decimals),
                    'classification' => $classification,
                    'detail' => sprintf('%s: legacy %.3f vs akeed %.3f (delta %.3f) at %d dp.', $dimension, $legacyValue, $ourValue, $ourValue - $legacyValue, $decimals),
                ];
            }
        }

        return $compared;
    }

    /**
     * A "missing_in_legacy" account with every dimension at 0.000 is not a
     * finding -- the seeded Akeed default chart (CoaSeeder) contributes ~171
     * leaves that no legacy anchor row will ever name, and every one of them
     * is silent. compareByAccount() already drops them because a 0.000 vs
     * 0.000 comparison is inside tolerance; this note exists so a future
     * reader does not "fix" that by emitting them.
     *
     * @param  list<array<string, mixed>>  $diffs
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function check(string $key, string $title, array $diffs, int $compared, array $summary): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'status' => $diffs === [] ? 'pass' : 'fail',
            'compared' => $compared,
            'diffs' => $diffs,
            'summary' => $summary,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function skippedCheck(string $key, string $title, string $reason): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'status' => 'skipped',
            'compared' => 0,
            'diffs' => [],
            'summary' => ['reason' => $reason],
        ];
    }

    /**
     * Persists the run and its diffs on the quarantined connection.
     * Returns the parity_run id.
     *
     * @param  array<string, mixed>  $result
     */
    public function persist(array $result, ?string $reportPath, ?string $jsonPath): int
    {
        LegacyPathGuard::assertQuarantinedConnection('legacy_pilot');

        $connection = DB::connection('legacy_pilot');

        if (! $connection->getSchemaBuilder()->hasTable('parity_run')) {
            throw new RuntimeException('legacy_pilot.parity_run does not exist. Run `php artisan migrate --path=database/migrations/legacy_pilot --database=legacy_pilot`.');
        }

        $runId = (int) $connection->table('parity_run')->insertGetId([
            'company_id' => $result['company_id'],
            'run_key' => $result['run_key'],
            'as_of' => $result['as_of'],
            'anchor' => $result['anchor'],
            'status' => $result['status'],
            'checks_total' => $result['checks_total'],
            'checks_failed' => $result['checks_failed'],
            'accounts_compared' => $result['accounts_compared'],
            'accounts_out_of_tolerance' => $result['accounts_out_of_tolerance'],
            'summary_json' => json_encode($result, JSON_UNESCAPED_SLASHES),
            'report_path' => $reportPath,
            'json_path' => $jsonPath,
            // CarbonImmutable::parse, not the ISO-8601 string as-is: MySQL
            // DATETIME rejects the offset suffix ("+08:00") outright.
            'started_at' => CarbonImmutable::parse($result['started_at'])->toDateTimeString(),
            'finished_at' => CarbonImmutable::parse($result['finished_at'])->toDateTimeString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $inserts = [];

        foreach ($result['checks'] as $check) {
            foreach ($check['diffs'] as $diff) {
                $inserts[] = [
                    'parity_run_id' => $runId,
                    'check_key' => $diff['check_key'],
                    'anchor' => $result['anchor'],
                    'acc_code' => $diff['acc_code'] ?? null,
                    'legacy_acc_id' => $diff['legacy_acc_id'] ?? null,
                    'account_id' => $diff['account_id'] ?? null,
                    'party_id' => $diff['party_id'] ?? null,
                    'dimension' => $diff['dimension'] ?? null,
                    'legacy_value' => $diff['legacy_value'] ?? null,
                    'akeed_value' => $diff['akeed_value'] ?? null,
                    'delta' => $diff['delta'] ?? null,
                    'classification' => $diff['classification'],
                    'detail_json' => json_encode($diff, JSON_UNESCAPED_SLASHES),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($inserts, 200) as $chunk) {
            $connection->table('parity_diff')->insert($chunk);
        }

        return $runId;
    }
}
