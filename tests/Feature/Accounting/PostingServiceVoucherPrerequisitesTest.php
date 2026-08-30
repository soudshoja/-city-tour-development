<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * W5.L (w5-brief.md §W5.L) — PostingService/LineDraft prerequisites for the vouchers lane:
 *   1. cheque/bank/auth instrument fields persist onto journal_entries (item 1).
 *   3. AST's reference_type mapping ('Payment', not the 'Invoice' fallback). Sub_type GOVERNANCE
 *      itself (item 3's "sub_type lists") is deliberately NOT a PostingService-level check — see
 *      VoucherSubTypeGuardTest (tests/Unit/Services/Accounting) and PostingService's own "W5.L FIX
 *      ROUND" docblock note for why, and this file's own proof test below that post() still
 *      accepts an already-shipped PV sub_type outside that vocabulary.
 *
 * Follows PostingServiceBalanceTest's own fixture conventions (makeCompany/makeBranch, explicit
 * accountId lines so these tests never depend on system_accounts/AccountResolver being seeded).
 */
class PostingServiceVoucherPrerequisitesTest extends AccountingTestCase
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

    private function draftWithLines(Company $company, Branch $branch, array $lines, string $docType, ?string $subType, ?string $idempotencyKey = null): DocumentDraft
    {
        return new DocumentDraft(
            companyId: $company->id,
            branchId: $branch->id,
            docType: $docType,
            subType: $subType,
            docDate: now(),
            narration: 'PostingServiceVoucherPrerequisitesTest fixture document',
            lines: $lines,
            idempotencyKey: $idempotencyKey,
        );
    }

    // ── item 1: instrument fields ──────────────────────────────────────────────────────────────

    public function test_post_persists_cheque_instrument_fields_onto_journal_entries(): void
    {
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company);

        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);

        $chequeDate = Carbon::parse('2026-09-01 00:00:00');
        $clearanceDate = Carbon::parse('2026-09-20');

        $draft = $this->draftWithLines(
            $company,
            $branch,
            [
                new LineDraft(
                    purposeCode: '',
                    accountId: $debitAccount->id,
                    side: 'debit',
                    amount: 55.000,
                    currency: 'KWD',
                    originalAmount: 55.000,
                    exchangeRate: 1.0,
                    transactionType: 'TEST_DEBIT',
                    chequeNo: 'CHQ-000456',
                    chequeDate: $chequeDate,
                    bankInfo: 'NBK - Main Branch',
                    authNo: 'AUTH-1122',
                    chequeClearanceDate: $clearanceDate,
                ),
                new LineDraft(
                    purposeCode: '',
                    accountId: $creditAccount->id,
                    side: 'credit',
                    amount: 55.000,
                    currency: 'KWD',
                    originalAmount: 55.000,
                    exchangeRate: 1.0,
                    transactionType: 'TEST_CREDIT',
                ),
            ],
            'RV',
            'ACCOUNT',
            'test:w5l-cheque-fields-'.uniqid()
        );

        $posted = app(PostingService::class)->post($draft);

        $debitRow = DB::table('journal_entries')
            ->where('transaction_id', $posted->transaction->id)
            ->where('account_id', $debitAccount->id)
            ->first();

        $this->assertSame('CHQ-000456', $debitRow->cheque_no);
        $this->assertSame('2026-09-01 00:00:00', Carbon::parse($debitRow->cheque_date)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-20', Carbon::parse($debitRow->cheque_clearance_date)->format('Y-m-d'));
        $this->assertSame('NBK - Main Branch', $debitRow->bank_info);
        $this->assertSame('AUTH-1122', $debitRow->auth_no);

        $creditRow = DB::table('journal_entries')
            ->where('transaction_id', $posted->transaction->id)
            ->where('account_id', $creditAccount->id)
            ->first();

        $this->assertNull($creditRow->cheque_no);
        $this->assertNull(
            $creditRow->cheque_date,
            'A line that does not set chequeDate must persist a real NULL — the pre-W5.L behaviour '
                .'(the column omitted from the INSERT entirely) let cheque_date\'s DB-level '
                .'useCurrent() default silently stamp "now" on every engine-posted line instead.'
        );
        $this->assertNull($creditRow->cheque_clearance_date);
        $this->assertNull($creditRow->bank_info);
        $this->assertNull($creditRow->auth_no);
    }

    public function test_reverse_carries_over_cheque_identity_but_not_clearance_date(): void
    {
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company);

        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);

        $chequeDate = Carbon::parse('2026-09-01 00:00:00');

        $draft = $this->draftWithLines(
            $company,
            $branch,
            [
                new LineDraft(
                    purposeCode: '',
                    accountId: $debitAccount->id,
                    side: 'debit',
                    amount: 40.000,
                    currency: 'KWD',
                    originalAmount: 40.000,
                    exchangeRate: 1.0,
                    transactionType: 'TEST_DEBIT',
                    chequeNo: 'CHQ-000789',
                    chequeDate: $chequeDate,
                    bankInfo: 'Ahli United',
                    authNo: 'AUTH-3344',
                    chequeClearanceDate: Carbon::parse('2026-09-10'),
                ),
                new LineDraft(
                    purposeCode: '',
                    accountId: $creditAccount->id,
                    side: 'credit',
                    amount: 40.000,
                    currency: 'KWD',
                    originalAmount: 40.000,
                    exchangeRate: 1.0,
                    transactionType: 'TEST_CREDIT',
                ),
            ],
            'RV',
            'ACCOUNT',
            'test:w5l-reverse-cheque-'.uniqid()
        );

        $service = app(PostingService::class);
        $posted = $service->post($draft);
        $reversed = $service->reverse($posted->transaction, now(), null);

        $reversedDebitLeg = DB::table('journal_entries')
            ->where('transaction_id', $reversed->transaction->id)
            ->where('account_id', $debitAccount->id)
            ->first();

        $this->assertSame(
            'CHQ-000789',
            $reversedDebitLeg->cheque_no,
            'reverse() must carry the cheque identity fields over — they identify WHICH document '
                .'is being undone, the same audit-trail reasoning already applied to partyAccountRef/partyName.'
        );
        $this->assertSame('2026-09-01 00:00:00', Carbon::parse($reversedDebitLeg->cheque_date)->format('Y-m-d H:i:s'));
        $this->assertSame('Ahli United', $reversedDebitLeg->bank_info);
        $this->assertSame('AUTH-3344', $reversedDebitLeg->auth_no);
        $this->assertNull(
            $reversedDebitLeg->cheque_clearance_date,
            'reverse() must NOT carry chequeClearanceDate over — clearance is a state of a specific '
                .'instance in time, not audit-trail identity; a reversal does not itself clear anything.'
        );
    }

    // ── item 3: AST reference_type mapping (sub_type GOVERNANCE itself is tested separately in
    //    tests/Unit/Services/Accounting/VoucherSubTypeGuardTest.php — see PostingService's own
    //    "W5.L FIX ROUND" docblock note for why that governance is deliberately NOT enforced here,
    //    inside post() itself) ───────────────────────────────────────────────────────────────────

    private function twoLineDraftFor(Company $company, Branch $branch, string $docType, ?string $subType): DocumentDraft
    {
        $debitAccount = Account::factory()->create(['company_id' => $company->id]);
        $creditAccount = Account::factory()->create(['company_id' => $company->id]);

        return $this->draftWithLines(
            $company,
            $branch,
            [
                new LineDraft(
                    purposeCode: '',
                    accountId: $debitAccount->id,
                    side: 'debit',
                    amount: 20.000,
                    currency: 'KWD',
                    originalAmount: 20.000,
                    exchangeRate: 1.0,
                    transactionType: 'TEST_DEBIT',
                ),
                new LineDraft(
                    purposeCode: '',
                    accountId: $creditAccount->id,
                    side: 'credit',
                    amount: 20.000,
                    currency: 'KWD',
                    originalAmount: 20.000,
                    exchangeRate: 1.0,
                    transactionType: 'TEST_CREDIT',
                ),
            ],
            $docType,
            $subType,
            "test:w5l-subtype-{$docType}-".uniqid()
        );
    }

    /**
     * PROOF this design choice is correct (see PostingService's own "W5.L FIX ROUND" docblock
     * note): post() itself must accept a docType='PV' document carrying a sub_type that is NOT a
     * member of config('accounting.sub_types')['PV'] at all — the exact shape every one of
     * RefundPostingService's/ClientController's/HandleGatewayRefundStatusChanged's already-shipped
     * PV feeders already relies on. A regression here would break real, shipped functionality.
     */
    public function test_post_accepts_a_pv_document_with_a_sub_type_outside_the_registered_vocabulary(): void
    {
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company);

        // Not a member of config('accounting.sub_types')['PV'] — the real shape
        // RefundPostingService::disposition() uses (docType PV, subType 'REFUND_DISPO').
        $draft = $this->twoLineDraftFor($company, $branch, 'PV', 'REFUND_DISPO');

        $posted = app(PostingService::class)->post($draft);

        $this->assertSame(
            'REFUND_DISPO',
            DB::table('transactions')->where('id', $posted->transaction->id)->value('sub_type')
        );
    }

    /**
     * resolveReferenceType()'s fallback map must not send an AST document to 'Invoice' (its
     * pre-W5.L default for any unmapped docType) — an agent settlement is not a sale.
     */
    public function test_ast_doc_type_resolves_reference_type_to_payment(): void
    {
        $company = $this->makeCompany();
        $branch = $this->makeBranch($company);

        $draft = $this->twoLineDraftFor($company, $branch, 'AST', 'LEGACY');

        $posted = app(PostingService::class)->post($draft);

        $this->assertSame(
            'Payment',
            DB::table('transactions')->where('id', $posted->transaction->id)->value('reference_type')
        );
    }
}
