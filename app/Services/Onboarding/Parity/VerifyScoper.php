<?php

declare(strict_types=1);

namespace App\Services\Onboarding\Parity;

use App\Services\Accounting\RvPvInvariantChecker;
use Illuminate\Support\Facades\DB;

/**
 * legacy-ledger-pilot LP4 -- MAPPING-RULES.md §11 decision O7-verify,
 * adopted by PLAN.md §5.3.
 *
 * THE PROBLEM. `accounting:verify` (App\Console\Commands\AccountingVerify,
 * a thin wrapper over RvPvInvariantChecker) enforces three RV/PV
 * invariants: the document balances, it has a cash-or-bank counter-leg, and
 * every line carries a voucher_number. The counter-leg rule asks
 * AccountResolver::isCashOrBankLeaf(), which matches an account GROUP NAME
 * exactly against config('accounting.engine.bank_group_name') /
 * ('cash_group_name') -- 'Bank Accounts' / 'Cash In Hand' in the seeded
 * Akeed chart. The legacy chart's groups are BANK ACCOUNTS / CASH ACCOUNTS
 * / PETTY CASH. So roughly 8,730 correctly-replayed FRV/CRV/BPV/CPV/BRV
 * documents would be flagged for a difference in capitalisation and
 * vocabulary, and nothing else.
 *
 * THE ADOPTED FIX, in two halves, both of them here:
 *
 *   1. Override bank/cash group names to the legacy chart's own for the
 *      DURATION OF THE VERIFY RUN ONLY. `config()->set()` on the live
 *      container is deliberately paired with a restore in a finally block:
 *      these names also steer AccountResolver on the POSTING path, and a
 *      harness that leaves them changed would silently re-point a later
 *      posting in the same process.
 *
 *   2. Scope the counter-leg rule OUT for legacy-replayed documents
 *      (sub_type LIKE 'LEGACY_%'). A third legacy group (PETTY CASH) has no
 *      config slot to be renamed into, and genuine no-cash-leg mirrors
 *      exist in the source by design (MAPPING-RULES §1.7). The BALANCE and
 *      VOUCHER-NUMBER rules stay in force for every document, legacy or
 *      not -- those two are real invariants that a replay must satisfy.
 *
 * WHAT IS EXPLICITLY NOT DONE: re-mapping CRV/BPV to doc_type 'JV' to dodge
 * the check. That would take those documents out of the RV/PV population
 * entirely and destroy the document-type census parity LP4 check 4 exists
 * to prove (MAPPING-RULES §11 O7-verify, PLAN.md §5.3).
 *
 * SCOPING OUT IS NOT SUPPRESSING. Every violation the checker produced is
 * counted and reported per (sub_type, rule); the scoped-out ones move to a
 * `scoped_out` bucket that the report prints, rather than being dropped.
 * PLAN.md §5.1 R9 ("vacuous green") is the reason: a check that quietly
 * removes its own findings is worse than no check.
 *
 * HOW A VIOLATION IS CLASSIFIED. RvPvInvariantChecker returns human-
 * readable strings, not structured rows. Each is labelled
 * "<DOC_TYPE> #<transaction id> (reference_number=...)" by that class's own
 * $label, so the transaction id is recovered with one anchored pattern and
 * the rule from three fixed phrases it emits. An unparseable violation is
 * NOT discarded -- it lands in `unclassified`, which is a FAIL condition,
 * because a rule this harness does not recognise is exactly the case where
 * silently ignoring it would matter.
 */
final class VerifyScoper
{
    public const RULE_UNBALANCED = 'unbalanced';

    public const RULE_NO_CASH_BANK_LEG = 'no_cash_or_bank_counter_leg';

    public const RULE_MISSING_VOUCHER = 'missing_voucher_number';

    public const RULE_UNRECOGNISED = 'unrecognised';

    public function __construct(private readonly RvPvInvariantChecker $checker) {}

    /**
     * @param  array<string, mixed>  $overrides  config key => value
     * @return array{status: string, checked: int, enforced: list<array<string, mixed>>, scoped_out: list<array<string, mixed>>, residuals_by_type: array<string, array<string, int>>, scoped_out_by_type: array<string, array<string, int>>, unclassified: list<string>, overrides_applied: array<string, mixed>}
     */
    public function run(int $companyId, array $overrides, bool $scopeOutCashBankForLegacy, string $legacySubTypePrefix): array
    {
        $original = [];

        foreach ($overrides as $key => $value) {
            $original[$key] = config($key);
            config([$key => $value]);
        }

        try {
            $result = $this->checker->check($companyId);
        } finally {
            foreach ($original as $key => $value) {
                config([$key => $value]);
            }
        }

        $subTypes = $this->subTypesByTransactionId($companyId);

        $enforced = [];
        $scopedOut = [];
        $unclassified = [];

        foreach ($result['violations'] as $violation) {
            $transactionId = $this->transactionIdFrom($violation);
            $rule = $this->ruleFrom($violation);
            $subType = $transactionId !== null ? ($subTypes[$transactionId] ?? null) : null;
            $isLegacy = $subType !== null && str_starts_with($subType, $legacySubTypePrefix);

            $row = [
                'transaction_id' => $transactionId,
                'sub_type' => $subType,
                'rule' => $rule,
                'violation' => $violation,
            ];

            if ($rule === self::RULE_UNRECOGNISED) {
                $unclassified[] = $violation;
                $enforced[] = $row;

                continue;
            }

            if ($scopeOutCashBankForLegacy && $isLegacy && $rule === self::RULE_NO_CASH_BANK_LEG) {
                $scopedOut[] = $row;

                continue;
            }

            $enforced[] = $row;
        }

        return [
            'status' => $enforced === [] ? 'pass' : 'fail',
            'checked' => $result['checked'],
            'enforced' => $enforced,
            'scoped_out' => $scopedOut,
            'residuals_by_type' => $this->tally($enforced),
            'scoped_out_by_type' => $this->tally($scopedOut),
            'unclassified' => $unclassified,
            'overrides_applied' => $overrides,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array<string, int>>
     */
    private function tally(array $rows): array
    {
        $out = [];

        foreach ($rows as $row) {
            $subType = (string) ($row['sub_type'] ?? '(unknown)');
            $rule = (string) $row['rule'];
            $out[$subType][$rule] = ($out[$subType][$rule] ?? 0) + 1;
        }

        return $out;
    }

    /**
     * @return array<int, string>
     */
    private function subTypesByTransactionId(int $companyId): array
    {
        $rows = DB::table('transactions')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereIn('doc_type', ['RV', 'PV'])
            ->get(['id', 'sub_type']);

        $out = [];

        foreach ($rows as $row) {
            $out[(int) $row->id] = (string) ($row->sub_type ?? '');
        }

        return $out;
    }

    private function transactionIdFrom(string $violation): ?int
    {
        // RvPvInvariantChecker::checkTransaction()'s $label:
        //   sprintf('%s #%d (reference_number=%s)', doc_type, id, ref)
        return preg_match('/^[A-Z]{2,8} #(\d+) \(reference_number=/', $violation, $m) === 1
            ? (int) $m[1]
            : null;
    }

    private function ruleFrom(string $violation): string
    {
        return match (true) {
            str_contains($violation, 'is NOT balanced') => self::RULE_UNBALANCED,
            str_contains($violation, 'no cash/bank counter-leg') => self::RULE_NO_CASH_BANK_LEG,
            str_contains($violation, 'no voucher_number') => self::RULE_MISSING_VOUCHER,
            default => self::RULE_UNRECOGNISED,
        };
    }
}
