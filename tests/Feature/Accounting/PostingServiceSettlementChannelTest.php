<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostingService;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * accounting-builds T0b (M1, L12): `journal_entries.settlement_channel` — written ONLY at
 * PostingService::post()'s own step-8 INSERT, carried over (unchanged) by reverse()'s line
 * reconstruction, null by default for any line that doesn't set it. Follows
 * PostingServiceVoucherPrerequisitesTest's own fixture conventions exactly (same
 * makeCompany/makeBranch/draftWithLines shape, explicit accountId lines).
 */
class PostingServiceSettlementChannelTest extends AccountingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['accounting.engine.enabled' => true]);
    }

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);

        parent::tearDown();
    }

    private function makeCompany(): Company
    {
        $company = tap(Company::factory()->create(), fn (Company $c) => $c->forceFill(['posting_engine_enabled' => true])->save());
        $this->trackCompanyForInvariants($company->id);

        return $company;
    }

    private function makeBranch(Company $company): Branch
    {
        return Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => User::factory()->create()->id,
        ]);
    }

    private function draftWithLines(Company $company, Branch $branch, array $lines, ?string $idempotencyKey = null): DocumentDraft
    {
        return new DocumentDraft(
            companyId: $company->id,
            branchId: $branch->id,
            docType: 'JV',
            subType: null,
            docDate: now(),
            narration: 'PostingServiceSettlementChannelTest fixture document',
            lines: $lines,
            idempotencyKey: $idempotencyKey,
        );
    }

    public function test_post_writes_settlement_channel_on_the_line_that_sets_it_and_leaves_it_null_on_the_other(): void
    {
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company);

        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);

        $draft = $this->draftWithLines(
            $company,
            $branch,
            [
                new LineDraft(
                    purposeCode: '',
                    accountId: $debitAccount->id,
                    side: 'debit',
                    amount: 25.000,
                    currency: 'KWD',
                    originalAmount: 25.000,
                    exchangeRate: 1.0,
                    transactionType: 'TEST_DEBIT',
                    settlementChannel: 'tap:knet',
                ),
                new LineDraft(
                    purposeCode: '',
                    accountId: $creditAccount->id,
                    side: 'credit',
                    amount: 25.000,
                    currency: 'KWD',
                    originalAmount: 25.000,
                    exchangeRate: 1.0,
                    transactionType: 'TEST_CREDIT',
                ),
            ],
            'test:t0b-settlement-channel-'.uniqid()
        );

        $posted = app(PostingService::class)->post($draft);

        $debitRow = DB::table('journal_entries')
            ->where('transaction_id', $posted->transaction->id)
            ->where('account_id', $debitAccount->id)
            ->first();

        $this->assertSame('tap:knet', $debitRow->settlement_channel);

        $creditRow = DB::table('journal_entries')
            ->where('transaction_id', $posted->transaction->id)
            ->where('account_id', $creditAccount->id)
            ->first();

        $this->assertNull(
            $creditRow->settlement_channel,
            'A line that does not set settlementChannel must persist a real NULL — null by default, '
                .'unchanged behaviour for every existing feeder that predates this field.'
        );
    }

    public function test_reverse_carries_over_settlement_channel(): void
    {
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company);

        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);

        $draft = $this->draftWithLines(
            $company,
            $branch,
            [
                new LineDraft(
                    purposeCode: '',
                    accountId: $debitAccount->id,
                    side: 'debit',
                    amount: 30.000,
                    currency: 'KWD',
                    originalAmount: 30.000,
                    exchangeRate: 1.0,
                    transactionType: 'TEST_DEBIT',
                    settlementChannel: 'bank:transfer',
                ),
                new LineDraft(
                    purposeCode: '',
                    accountId: $creditAccount->id,
                    side: 'credit',
                    amount: 30.000,
                    currency: 'KWD',
                    originalAmount: 30.000,
                    exchangeRate: 1.0,
                    transactionType: 'TEST_CREDIT',
                ),
            ],
            'test:t0b-settlement-channel-reverse-'.uniqid()
        );

        $service = app(PostingService::class);
        $posted = $service->post($draft);
        $reversed = $service->reverse($posted->transaction, now(), null);

        $reversedDebitLeg = DB::table('journal_entries')
            ->where('transaction_id', $reversed->transaction->id)
            ->where('account_id', $debitAccount->id)
            ->first();

        $this->assertSame(
            'bank:transfer',
            $reversedDebitLeg->settlement_channel,
            'reverse() must carry settlement_channel over from the original line — MP-0b-1: '
                .'dropping this from the reversal copy must make this assertion fail.'
        );
    }
}
