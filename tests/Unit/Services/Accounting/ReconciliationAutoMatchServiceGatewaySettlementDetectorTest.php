<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Accounting;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\GatewaySettlement;
use App\Models\JournalEntry;
use App\Models\ReconciliationProposal;
use App\Models\ReconciliationRun;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\ReconciliationAutoMatchService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Tests\Support\AccountingTestCase;

/**
 * accounting-builds T7 (Lane D): {@see ReconciliationAutoMatchService::detectGatewaySettlementItems()}
 * — the "gateway-item detector" the plan names explicitly (PLAN.md §5, Lane D test list). Never
 * posts money (matches {@see ReconciliationAutoMatchServiceTest}'s own oracle for the other three
 * detectors); idempotent per line; never matches across the wrong gateway's clearing leaf.
 */
class ReconciliationAutoMatchServiceGatewaySettlementDetectorTest extends AccountingTestCase
{
    private function service(): ReconciliationAutoMatchService
    {
        return app(ReconciliationAutoMatchService::class);
    }

    /** @return array{0: Company, 1: Branch} */
    private function makeCompany(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();

        $owner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $owner->id]);
        $this->trackCompanyForInvariants($company->id);

        return [$company, $branch];
    }

    private function clearingLeaf(Company $company, string $gateway): Account
    {
        return app(AccountResolver::class)->resolve("GATEWAY_CLEARING_{$gateway}", $company->id);
    }

    private function makeUnreconciledClearingLine(Company $company, Branch $branch, Account $clearing, float $amount, Carbon $date, ?string $authNo = null): JournalEntry
    {
        $txn = Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => $branch->id,
            'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'RV', 'amount' => $amount, 'description' => 'Gateway receipt',
            'reference_type' => 'Receipt', 'reference_number' => 'GWD-'.substr(uniqid(), -8),
            'name' => 'Test', 'transaction_date' => $date, 'posting_date' => $date,
            'doc_type' => 'RV', 'doc_year' => (int) $date->format('Y'), 'posting_status' => 'posted',
            'total_debit' => $amount, 'total_credit' => $amount, 'idempotency_key' => uniqid('key:'),
        ]);

        $line = JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $clearing->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'Gateway clearing', 'debit' => $amount, 'credit' => 0, 'name' => $clearing->name,
            'type' => 'bank', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => $amount,
            'reconciled' => 0, 'auth_no' => $authNo,
        ]);

        // Balancing credit leg on the receivable control leaf — a fixture-only counterpart so
        // AccountingInvariants (per-transaction Σdebit=Σcredit, asserted in tearDown) does not
        // fail on this test's own bank-line fixture; unrelated to what this file is testing.
        $receivable = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1351')->firstOrFail();
        JournalEntry::create([
            'transaction_id' => $txn->id, 'company_id' => $company->id, 'branch_id' => $branch->id,
            'account_id' => $receivable->id, 'transaction_date' => $date, 'posting_date' => $date,
            'description' => 'Balancing leg', 'debit' => 0, 'credit' => $amount, 'name' => $receivable->name,
            'type' => 'receivable', 'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => $amount,
        ]);

        return $line;
    }

    private function makePostedSettlement(Company $company, string $gateway, array $payoutItems): GatewaySettlement
    {
        return GatewaySettlement::create([
            'company_id' => $company->id, 'gateway' => $gateway, 'settlement_channel' => strtolower($gateway),
            'payout_reference' => 'PO-'.uniqid(), 'payout_date' => '2026-08-20',
            'gross' => 100, 'fee' => 5, 'net' => 95, 'recognised_fee' => 0, 'currency' => 'KWD',
            'bank_account_id' => Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1201')->value('id'),
            'status' => GatewaySettlement::STATUS_POSTED, 'source' => GatewaySettlement::SOURCE_CSV,
            'raw' => ['payout_items' => $payoutItems],
        ]);
    }

    public function test_exact_match_on_auth_no(): void
    {
        [$company, $branch] = $this->makeCompany();
        $clearing = $this->clearingLeaf($company, 'TAP');
        $line = $this->makeUnreconciledClearingLine($company, $branch, $clearing, 50.000, Carbon::parse('2026-08-19'), 'AUTH-1');

        $this->makePostedSettlement($company, 'TAP', [
            ['reference' => 'AUTH-1', 'amount' => 50.000, 'date' => '2026-08-19'],
        ]);

        $run = $this->service()->run($company->id, ReconciliationRun::TRIGGER_MANUAL);

        $proposal = ReconciliationProposal::where('company_id', $company->id)
            ->where('kind', ReconciliationProposal::KIND_GATEWAY_SETTLEMENT)
            ->first();

        $this->assertNotNull($proposal);
        $this->assertSame($line->id, $proposal->book_journal_entry_id);
        $this->assertSame(ReconciliationProposal::CONFIDENCE_EXACT, $proposal->confidence);
        $this->assertSame('external', $proposal->source);
        $this->assertSame(0, (int) Transaction::withoutGlobalScopes()->where('company_id', $company->id)->whereNotNull('reversal_of_transaction_id')->count());
        $this->assertGreaterThanOrEqual(1, $run->proposals_created);
    }

    public function test_tolerance_match_on_amount_and_date_window_when_no_reference_match(): void
    {
        [$company, $branch] = $this->makeCompany();
        $clearing = $this->clearingLeaf($company, 'TAP');
        $line = $this->makeUnreconciledClearingLine($company, $branch, $clearing, 75.000, Carbon::parse('2026-08-18'), null);

        $this->makePostedSettlement($company, 'TAP', [
            ['reference' => null, 'amount' => 75.000, 'date' => '2026-08-20'],
        ]);

        $this->service()->run($company->id, ReconciliationRun::TRIGGER_MANUAL);

        $proposal = ReconciliationProposal::where('company_id', $company->id)
            ->where('kind', ReconciliationProposal::KIND_GATEWAY_SETTLEMENT)
            ->first();

        $this->assertNotNull($proposal);
        $this->assertSame($line->id, $proposal->book_journal_entry_id);
        $this->assertSame(ReconciliationProposal::CONFIDENCE_TOLERANCE, $proposal->confidence);
    }

    public function test_never_matches_a_line_on_a_different_gateways_clearing_leaf(): void
    {
        // Dedicated local fixture (not makeCompany()): KNET and uPayment are the only two
        // gateways EnsureSystemLeaves backfills a DEDICATED clearing leaf (1311/1312) for — every
        // other gateway (including TAP) shares the bare pool leaf by default, so this test picks
        // the one pair guaranteed to be genuinely distinct accounts regardless of seeding order.
        // EnsureSystemLeaves runs BEFORE SystemAccountsSeeder so the purpose-code mapping sees the
        // final COA shape directly, rather than mapping onto the bare pool first and going stale
        // once a sibling leaf appears (see NonLeafAccountException hit while developing this test).
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        \Illuminate\Support\Facades\Artisan::call('accounting:ensure-system-leaves', ['--company' => $company->id]);
        (new SystemAccountsSeeder)->run();

        $owner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $owner->id]);
        $this->trackCompanyForInvariants($company->id);

        $knetClearing = $this->clearingLeaf($company, 'KNET');
        $upaymentClearing = $this->clearingLeaf($company, 'UPAYMENT');
        $this->assertNotSame($knetClearing->id, $upaymentClearing->id, 'fixture sanity: KNET and uPayment must resolve to different clearing leaves for this test to be meaningful.');

        // An uPayment line with the exact same auth_no/amount as what the KNET settlement's
        // payout item names must never be picked up — only KNET's own clearing leaf is in scope
        // for a KNET settlement's items. A deliberately unique amount (33.777) so no later line
        // in this test could ever accidentally tolerance-match it instead.
        $this->makeUnreconciledClearingLine($company, $branch, $upaymentClearing, 33.777, Carbon::parse('2026-08-19'), 'AUTH-CROSS');

        $this->makePostedSettlement($company, 'KNET', [
            ['reference' => 'AUTH-CROSS', 'amount' => 33.777, 'date' => '2026-08-19'],
        ]);

        $this->service()->run($company->id, ReconciliationRun::TRIGGER_MANUAL);

        $this->assertSame(
            0,
            ReconciliationProposal::where('company_id', $company->id)->where('kind', ReconciliationProposal::KIND_GATEWAY_SETTLEMENT)->count(),
            'a KNET settlement item must never match a line on the uPayment clearing leaf, even with an identical amount and reference.'
        );

        // Sanity: if the SAME leaf were KNET, it would match (proves the test itself is meaningful).
        $this->makeUnreconciledClearingLine($company, $branch, $knetClearing, 50.000, Carbon::parse('2026-08-19'), 'AUTH-CROSS-2');
        $this->makePostedSettlement($company, 'KNET', [
            ['reference' => 'AUTH-CROSS-2', 'amount' => 50.000, 'date' => '2026-08-19'],
        ]);
        $this->service()->run($company->id, ReconciliationRun::TRIGGER_MANUAL);
        $this->assertSame(1, ReconciliationProposal::where('company_id', $company->id)->where('kind', ReconciliationProposal::KIND_GATEWAY_SETTLEMENT)->count());
    }

    public function test_idempotent_re_run_never_duplicates_a_pending_proposal(): void
    {
        [$company, $branch] = $this->makeCompany();
        $clearing = $this->clearingLeaf($company, 'TAP');
        $this->makeUnreconciledClearingLine($company, $branch, $clearing, 50.000, Carbon::parse('2026-08-19'), 'AUTH-IDEMP');

        $this->makePostedSettlement($company, 'TAP', [
            ['reference' => 'AUTH-IDEMP', 'amount' => 50.000, 'date' => '2026-08-19'],
        ]);

        $this->service()->run($company->id, ReconciliationRun::TRIGGER_MANUAL);
        $this->service()->run($company->id, ReconciliationRun::TRIGGER_MANUAL);

        $this->assertSame(
            1,
            ReconciliationProposal::where('company_id', $company->id)->where('kind', ReconciliationProposal::KIND_GATEWAY_SETTLEMENT)->count(),
            'a second run over the same unreconciled line + settlement must not duplicate the proposal.'
        );
    }

    public function test_run_never_writes_journal_entries_only_proposes(): void
    {
        [$company, $branch] = $this->makeCompany();
        $clearing = $this->clearingLeaf($company, 'TAP');
        $this->makeUnreconciledClearingLine($company, $branch, $clearing, 50.000, Carbon::parse('2026-08-19'), 'AUTH-NEVERPOST');
        $this->makePostedSettlement($company, 'TAP', [
            ['reference' => 'AUTH-NEVERPOST', 'amount' => 50.000, 'date' => '2026-08-19'],
        ]);

        $countBefore = JournalEntry::withoutGlobalScopes()->where('company_id', $company->id)->count();
        $this->service()->run($company->id, ReconciliationRun::TRIGGER_MANUAL);
        $countAfter = JournalEntry::withoutGlobalScopes()->where('company_id', $company->id)->count();

        $this->assertSame($countBefore, $countAfter, 'this detector must never post a ledger line — proposals only.');
    }
}
