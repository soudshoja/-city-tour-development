<?php

declare(strict_types=1);

namespace Tests\Feature\Legacy;

use App\Models\Transaction;
use App\Services\Accounting\RvPvInvariantChecker;

/**
 * legacy-ledger-pilot LP3 -- decision O7-verify (MAPPING-RULES.md §1.7 / §11,
 * PLAN.md §5.3).
 *
 * `RvPvInvariantChecker` is the only engine-adjacent class this task touches, and
 * it is touched behind ONE pilot flag,
 * `legacy_pilot.replay.verify.exclude_sub_type_like`, which is NULL by default.
 * The first test below is the "the flag defaults off" proof the task brief
 * requires; the second proves the flag, when a pilot instance sets it,
 * suppresses ONLY the cash/bank rule and leaves the balance and voucher-number
 * rules in force.
 */
class LegacyVerifyPilotFlagTest extends LegacyReplayTestCase
{
    /**
     * A replayed RV with no cash/bank leaf (the legacy chart's groups are named
     * BANK ACCOUNTS / CASH ACCOUNTS / PETTY CASH, none of which matches
     * accounting.engine.bank_group_name's 'Bank Accounts') is reported by
     * default -- i.e. the flag changes nothing on a non-pilot instance.
     */
    public function test_the_pilot_exclusion_flag_is_off_by_default_and_the_rule_still_fires(): void
    {
        $this->assertNull(
            config('legacy_pilot.replay.verify.exclude_sub_type_like'),
            'the pilot verify exclusion must be NULL on every non-pilot instance'
        );

        $this->stageReplayedReceipt();

        $result = app(RvPvInvariantChecker::class)->check($this->company->id);

        $this->assertSame(1, $result['checked']);
        $this->assertNotEmpty($result['violations']);
        $this->assertStringContainsString('no cash/bank counter-leg', implode("\n", $result['violations']));
    }

    /**
     * With the flag set, the cash/bank rule is skipped for LEGACY_* documents --
     * the ~8,730 replayed RV/PV false positives O7-verify names -- while the
     * balance and voucher-number rules stay in force.
     */
    public function test_the_flag_suppresses_only_the_cash_bank_rule_for_legacy_documents(): void
    {
        config(['legacy_pilot.replay.verify.exclude_sub_type_like' => 'LEGACY_%']);

        $this->stageReplayedReceipt();

        $result = app(RvPvInvariantChecker::class)->check($this->company->id);

        $this->assertSame(1, $result['checked']);
        $this->assertSame([], $result['violations']);

        // Now break the voucher-number rule on the same LEGACY_ document: it must
        // still be reported, because the flag suppresses one rule and one only.
        \Illuminate\Support\Facades\DB::table('journal_entries')->update(['voucher_number' => null]);

        $violations = app(RvPvInvariantChecker::class)->check($this->company->id)['violations'];
        $this->assertNotEmpty($violations);
        $this->assertStringContainsString('no voucher_number', implode("\n", $violations));
    }

    /** One replayed CRV whose only lines sit on a receivable control and an income leaf. */
    private function stageReplayedReceipt(): void
    {
        $docId = $this->stageHeader(['SubType' => 'CRV', 'DocType' => 'RV', 'DocDt' => '2025-03-01', 'DocNo' => 'CRV/CO/25/1']);
        $this->stageLine($docId, ['AccID_FK' => self::LEGACY_CUSTOMER_ACC, 'Debit' => '10.000', 'DC' => 'D', 'DocDt' => '2025-03-01']);
        $this->stageLine($docId, ['AccID_FK' => self::LEGACY_INCOME_ACC, 'Credit' => '10.000', 'DC' => 'C', 'DocDt' => '2025-03-01']);

        $this->assertSame(1, $this->replay()['posted']);
        $this->assertSame('RV', Transaction::withoutGlobalScopes()->value('doc_type'));
    }
}
