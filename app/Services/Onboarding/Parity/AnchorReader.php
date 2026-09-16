<?php

declare(strict_types=1);

namespace App\Services\Onboarding\Parity;

use App\Services\Onboarding\LegacyPathGuard;
use App\Services\Onboarding\LegacyStagingCast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * legacy-ledger-pilot LP4 -- reads the five parity ANCHORS out of the
 * quarantined stg_* landing tables.
 *
 * Anchors are read from `legacy_pilot`, never from the CSVs on disk. That
 * is not a convenience: LegacyCsvLoader records a SHA-256 of every file it
 * stages (legacy_load_audit), so a figure quoted by the parity report can
 * be traced to the exact bytes it came from. A harness that re-read
 * D:\akeedac directly would compare against whatever the file says TODAY
 * and quietly lose that chain -- and would need the data root open at
 * report time, which PLAN.md §3.8's teardown does not guarantee.
 *
 * Nothing here transforms a legacy figure. EVERY stg_* column is nullable
 * TEXT -- the loader types nothing (LegacyCsvLoader's docblock explains
 * why) -- so their OpeningNet / PeriodDr / PeriodCr / ClosingNet arrive as
 * strings and are coerced ONCE, here, through
 * App\Services\Onboarding\LegacyStagingCast: `toDecimal()` for every
 * amount (3 dp, KWD, refusing non-numeric text rather than casting it to a
 * plausible 0.000) and `toInt()` for every id. No raw `(float)`/`(int)`
 * cast of a staging value appears in this class, and none should: LP1b's
 * own regression -- MastersAuditor reporting 37,134 unposted headers
 * against a true 37, because a TEXT column was compared to a literal --
 * is what that helper exists to prevent. The identity `ClosingNet = OpeningNet + PeriodDr - PeriodCr`
 * (README-MANIFEST.md line 51) is ASSERTED per row rather than recomputed:
 * if their own file does not satisfy its own definition, that is a staging
 * or export defect and must surface as such, not be silently "fixed" into
 * agreement with us.
 *
 * Column names are the loader's normalised form (LegacyColumn::name()):
 * "AccCode" -> acccode, "AccID_FK" -> accid_fk, "OpeningNet" -> openingnet,
 * and so on.
 */
final class AnchorReader
{
    /**
     * Per-row shape returned by trialBalance():
     *   acc_code, legacy_acc_id, opening_net, period_dr, period_cr, closing_net
     *
     * @return array<string, array<string, mixed>> keyed by acc_code
     */
    public function trialBalance(string $stgTable, ?int $expectedRows = null): array
    {
        $rows = $this->rowsFrom($stgTable, $expectedRows, ['acccode', 'accid_fk', 'openingnet', 'perioddr', 'periodcr', 'closingnet']);

        $out = [];

        foreach ($rows as $row) {
            $accCode = trim((string) $row->acccode);

            if ($accCode === '') {
                throw new RuntimeException("Anchor {$stgTable} has a row with a blank AccCode — refusing to compare against an unidentifiable account.");
            }

            if (isset($out[$accCode])) {
                // Consolidated anchors are BranchID='ALL', one row per
                // account (PLAN.md §1.1). A duplicate means a by-branch file
                // was staged into a consolidated slot -- silently summing
                // them would produce a plausible-looking but wrong anchor.
                throw new RuntimeException("Anchor {$stgTable} has a duplicate AccCode '{$accCode}' — a consolidated anchor holds exactly one row per account; a by-branch file may have been staged here.");
            }

            $opening = $this->money($row->openingnet, $stgTable, $accCode, 'OpeningNet');
            $periodDr = $this->money($row->perioddr, $stgTable, $accCode, 'PeriodDr');
            $periodCr = $this->money($row->periodcr, $stgTable, $accCode, 'PeriodCr');
            $closing = $this->money($row->closingnet, $stgTable, $accCode, 'ClosingNet');

            $derived = $opening + $periodDr - $periodCr;

            if (abs($derived - $closing) > 0.0005) {
                throw new RuntimeException(sprintf(
                    'Anchor %s fails its own identity on AccCode %s: OpeningNet(%.3f) + PeriodDr(%.3f) - PeriodCr(%.3f) = %.3f, but ClosingNet says %.3f. The staged anchor is defective; fix the load before reading a parity result off it.',
                    $stgTable, $accCode, $opening, $periodDr, $periodCr, $derived, $closing
                ));
            }

            $out[$accCode] = [
                'acc_code' => $accCode,
                'legacy_acc_id' => $this->nullableInt($row->accid_fk),
                'opening_net' => $opening,
                'period_dr' => $periodDr,
                'period_cr' => $periodCr,
                'closing_net' => $closing,
            ];
        }

        return $out;
    }

    /**
     * profit_loss_2025.csv: per income (3%) / expense (4%) account, posted
     * non-OJV activity, NetIncome = Credit - Debit (README-MANIFEST.md
     * line 81). Note the CREDIT-positive sign, the opposite convention from
     * the trial balance's debit-positive ClosingNet -- getting this
     * backwards inverts every P&L account at once.
     *
     * @return array<string, array<string, mixed>> keyed by acc_code
     */
    public function profitLoss(string $stgTable, ?int $expectedRows = null): array
    {
        $rows = $this->rowsFrom($stgTable, $expectedRows, ['acccode', 'accid_fk', 'cr', 'dr', 'netincome']);

        $out = [];

        foreach ($rows as $row) {
            $accCode = trim((string) $row->acccode);

            if ($accCode === '') {
                throw new RuntimeException("Anchor {$stgTable} has a row with a blank AccCode — refusing to compare against an unidentifiable account.");
            }

            $credit = $this->money($row->cr, $stgTable, $accCode, 'Cr');
            $debit = $this->money($row->dr, $stgTable, $accCode, 'Dr');
            $net = $this->money($row->netincome, $stgTable, $accCode, 'NetIncome');

            if (abs(($credit - $debit) - $net) > 0.0005) {
                throw new RuntimeException(sprintf(
                    'Anchor %s fails its own identity on AccCode %s: Cr(%.3f) - Dr(%.3f) = %.3f, but NetIncome says %.3f.',
                    $stgTable, $accCode, $credit, $debit, $credit - $debit, $net
                ));
            }

            $out[$accCode] = [
                'acc_code' => $accCode,
                'legacy_acc_id' => $this->nullableInt($row->accid_fk),
                'credit' => $credit,
                'debit' => $debit,
                'net_income' => $net,
                'class' => isset($row->class) ? trim((string) $row->class) : null,
            ];
        }

        return $out;
    }

    /**
     * ar_balances_* / ap_balances_*: closing net, Dr positive, per party
     * leaf; zero balances omitted (README-MANIFEST.md lines 82-83).
     *
     * THE OMISSION IS NOT ONLY ZEROES (LP4b). These two files are also
     * GROUP-SCOPED reports: the AR export covers the customer-receivable
     * family (`10904%`) and the AP export covers `206%`. A party leaf parked
     * outside those groups -- UNCLASSIFIED-274 §1's bucket B, partner-FK
     * leaves under BANK ACCOUNTS / CASH IN HAND / RECEIVABLES-GENERAL which
     * pool cross-root under rule R-b -- is absent from these files AT ANY
     * BALANCE. Staging run #6 reported 15 such leaves as "we hold a position
     * the legacy system does not", when every one of them matched its own
     * closing trial-balance figure to the fils.
     *
     * So absence here asserts NOTHING about our side, in either direction:
     * it means "zero balance OR out of this report's scope", and the caller
     * must resolve which. ParityHarness::partyCheck() does that by falling
     * back to the consolidated closing trial balance, which carries every
     * leaf. Nothing is invented here: this reader returns the rows the file
     * has, and no 0.000 row it does not.
     *
     * @return array<string, array<string, mixed>> keyed by acc_code
     */
    public function partyBalances(string $stgTable, ?int $expectedRows = null): array
    {
        $rows = $this->rowsFrom($stgTable, $expectedRows, ['acccode', 'accid_fk', 'closingnet']);

        $out = [];

        foreach ($rows as $row) {
            $accCode = trim((string) $row->acccode);

            if ($accCode === '') {
                throw new RuntimeException("Anchor {$stgTable} has a row with a blank AccCode — refusing to compare against an unidentifiable account.");
            }

            $out[$accCode] = [
                'acc_code' => $accCode,
                'legacy_acc_id' => $this->nullableInt($row->accid_fk),
                'closing_net' => $this->money($row->closingnet, $stgTable, $accCode, 'ClosingNet'),
            ];
        }

        return $out;
    }

    public function hasTable(string $stgTable): bool
    {
        return Schema::connection('legacy_pilot')->hasTable($stgTable);
    }

    /**
     * @param  string[]  $requiredColumns
     * @return list<object>
     */
    private function rowsFrom(string $stgTable, ?int $expectedRows, array $requiredColumns): array
    {
        LegacyPathGuard::assertQuarantinedConnection('legacy_pilot');

        if (! Schema::connection('legacy_pilot')->hasTable($stgTable)) {
            throw new RuntimeException("Anchor table {$stgTable} is not staged. Run `php artisan legacy:load` for the corresponding export file before running legacy:parity.");
        }

        foreach ($requiredColumns as $column) {
            if (! Schema::connection('legacy_pilot')->hasColumn($stgTable, $column)) {
                throw new RuntimeException("Anchor table {$stgTable} has no column '{$column}' — the staged file's header does not match the anchor shape this harness compares.");
            }
        }

        $rows = DB::connection('legacy_pilot')->table($stgTable)->get()->all();

        if ($expectedRows !== null && count($rows) !== $expectedRows) {
            throw new RuntimeException(sprintf(
                'Anchor table %s holds %d row(s), expected exactly %d (config/legacy_pilot.php parity.anchors). An anchor that changed size between load and compare is a staging defect, not a parity result.',
                $stgTable, count($rows), $expectedRows
            ));
        }

        return $rows;
    }

    /**
     * One thin wrapper over LegacyStagingCast::toDecimal() -- it exists
     * only to build the context string ("Anchor stg_tb_... AccCode 1010:
     * column OpeningNet") the helper echoes back when it refuses a
     * non-numeric cell, so a refusal names the exact anchor cell instead of
     * just "bad number". Every rounding and refusal rule lives in the
     * helper; nothing is duplicated here.
     */
    private function money(mixed $raw, string $stgTable, string $accCode, string $column): float
    {
        return LegacyStagingCast::toDecimal(
            $raw,
            (int) config('legacy_pilot.parity.decimals', 3),
            "Anchor {$stgTable} AccCode {$accCode}: column {$column}",
        );
    }

    private function nullableInt(mixed $raw): ?int
    {
        return LegacyStagingCast::toInt($raw);
    }
}
