<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Services\Accounting\AccountResolver;
use Illuminate\Support\Carbon;

/**
 * accounting-builds T7 — post-fix re-verification.
 *
 * `GatewaySettlementService` refuses to drain more from `GATEWAY_CLEARING_{gw}` than that account
 * derivably holds (Σdebit − Σcredit over live `journal_entries`). That guard is the thing standing
 * between a bad payout report and a negative clearing account, so it has NO carve-out for
 * "companies with no receipt history" — which means a settlement test must first put the money in
 * clearing that the payout it is about to record claims to be releasing. That is not scaffolding
 * to keep a guard quiet; it is the precondition every real settlement has, and stating it makes
 * each test's arithmetic auditable (clearing in == clearing drained).
 *
 * This seeds it the shape a receipt does — Dr `GATEWAY_CLEARING_{gw}` / Cr receivable control —
 * as a fixture-level raw write, the same approach
 * {@see \Tests\Unit\Services\Accounting\ReconciliationAutoMatchServiceGatewaySettlementDetectorTest}
 * already uses for its own unreconciled clearing lines. The balancing credit leg keeps
 * {@see AccountingInvariants} (per-transaction Σdebit = Σcredit, asserted in tearDown) satisfied.
 */
trait SeedsGatewayClearing
{
    protected function seedGatewayClearing(Company $company, string $gateway, float $amount, string $date = '2026-08-01'): void
    {
        $clearing = app(AccountResolver::class)->resolve("GATEWAY_CLEARING_{$gateway}", $company->id);
        $receivable = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1351')->firstOrFail();
        $branchId = Branch::withoutGlobalScopes()->where('company_id', $company->id)->value('id');
        $when = Carbon::parse($date);

        $txn = Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branchId,
            'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'RV', 'amount' => $amount, 'description' => "Gateway receipt into clearing ({$gateway}) — fixture",
            'reference_type' => 'Receipt', 'reference_number' => 'SEED-'.substr(uniqid(), -8),
            'name' => 'Test', 'transaction_date' => $when, 'posting_date' => $when,
            'doc_type' => 'RV', 'doc_year' => (int) $when->format('Y'), 'posting_status' => 'posted',
            'total_debit' => $amount, 'total_credit' => $amount, 'idempotency_key' => uniqid('seed-clearing:'),
        ]);

        JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branchId,
            'account_id' => $clearing->id, 'transaction_date' => $when, 'posting_date' => $when,
            'description' => 'Gateway clearing', 'debit' => $amount, 'credit' => 0, 'name' => $clearing->name,
            'type' => 'bank', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => $amount, 'reconciled' => 0,
        ]);

        JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branchId,
            'account_id' => $receivable->id, 'transaction_date' => $when, 'posting_date' => $when,
            'description' => 'Balancing leg', 'debit' => 0, 'credit' => $amount, 'name' => $receivable->name,
            'type' => 'receivable', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => $amount,
        ]);
    }
}
