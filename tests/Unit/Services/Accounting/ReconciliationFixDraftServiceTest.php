<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Accounting;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\ReconciliationFixDraft;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\ReconciliationFixDraftService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Tests\Support\AccountingTestCase;

/**
 * P2.5.G (p2_5-brief.md §P2.5.G): "fix-now creates a DRAFT never a posted doc." Also exercises
 * the distinct, separate post() step — the "normal approval path" — and discard().
 */
class ReconciliationFixDraftServiceTest extends AccountingTestCase
{
    private function service(): ReconciliationFixDraftService
    {
        return app(ReconciliationFixDraftService::class);
    }

    /** @return array{0: Company, 1: Branch} */
    private function makeCompany(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();

        $owner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $owner->id]);

        // post() actually calls PostingService, unlike every other service in this sub-wave
        // (grid/proposal actions never touch the seam) -- the engine must be ON for this company.
        config(['accounting.engine.enabled' => true]);
        \Illuminate\Support\Facades\Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $this->trackCompanyForInvariants($company->id);

        return [$company, $branch];
    }

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    private function accountByCode(int $companyId, string $code): Account
    {
        return Account::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->firstOrFail();
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::ADMIN]);
    }

    public function test_create_writes_a_draft_row_and_posts_nothing(): void
    {
        [$company, $branch] = $this->makeCompany();
        $bank = $this->accountByCode($company->id, '1201');

        $transactionCountBefore = Transaction::withoutGlobalScopes()->count();

        $draft = $this->service()->create(
            $company->id,
            $branch->id,
            $bank->id,
            ReconciliationFixDraft::KIND_BANK_CHARGE_PV,
            5.500,
            'Unrecorded bank charge',
            null,
            $this->admin(),
        );

        $this->assertSame(ReconciliationFixDraft::STATUS_DRAFT, $draft->status);
        $this->assertNull($draft->transaction_id);
        $this->assertSame($transactionCountBefore, Transaction::withoutGlobalScopes()->count(), 'create() must never post a ledger document.');
    }

    public function test_post_turns_a_draft_into_a_real_balanced_posted_transaction(): void
    {
        [$company, $branch] = $this->makeCompany();
        $bank = $this->accountByCode($company->id, '1201');

        $draft = $this->service()->create(
            $company->id,
            $branch->id,
            $bank->id,
            ReconciliationFixDraft::KIND_BANK_CHARGE_PV,
            5.500,
            'Unrecorded bank charge',
            null,
            $this->admin(),
        );

        $posted = $this->service()->post($draft, $this->admin());

        $this->assertSame(ReconciliationFixDraft::STATUS_POSTED, $posted->status);
        $this->assertNotNull($posted->transaction_id);

        $txn = Transaction::withoutGlobalScopes()->find($posted->transaction_id);
        $this->assertNotNull($txn);
        $this->assertSame('posted', $txn->posting_status);
        $this->assertEqualsWithDelta(5.500, (float) $txn->total_debit, 0.001);
        $this->assertEqualsWithDelta(5.500, (float) $txn->total_credit, 0.001);
    }

    public function test_post_refuses_a_non_draft(): void
    {
        [$company, $branch] = $this->makeCompany();
        $bank = $this->accountByCode($company->id, '1201');
        $draft = $this->service()->create($company->id, $branch->id, $bank->id, ReconciliationFixDraft::KIND_BANK_CHARGE_PV, 5.000, 'x', null, $this->admin());
        $posted = $this->service()->post($draft, $this->admin());

        $this->expectException(\RuntimeException::class);
        $this->service()->post($posted, $this->admin());
    }

    public function test_unapply_reapply_kind_cannot_be_auto_posted(): void
    {
        [$company, $branch] = $this->makeCompany();
        $bank = $this->accountByCode($company->id, '1201');

        $draft = $this->service()->create(
            $company->id, $branch->id, $bank->id,
            ReconciliationFixDraft::KIND_UNAPPLY_REAPPLY_RECEIPT, 20.000, 'possible duplicate', null, $this->admin(),
        );

        $this->assertSame(ReconciliationFixDraft::STATUS_DRAFT, $draft->status);
        $this->assertFalse($draft->isPostable());

        $this->expectException(\RuntimeException::class);
        $this->service()->post($draft, $this->admin());
    }

    public function test_discard_requires_a_reason(): void
    {
        [$company, $branch] = $this->makeCompany();
        $bank = $this->accountByCode($company->id, '1201');
        $draft = $this->service()->create($company->id, $branch->id, $bank->id, ReconciliationFixDraft::KIND_WRITEOFF_PROPOSAL, 12.000, 'stale item', null, $this->admin());

        $this->expectException(\InvalidArgumentException::class);
        $this->service()->discard($draft, '', $this->admin());
    }

    public function test_discard_marks_the_draft_discarded(): void
    {
        [$company, $branch] = $this->makeCompany();
        $bank = $this->accountByCode($company->id, '1201');
        $draft = $this->service()->create($company->id, $branch->id, $bank->id, ReconciliationFixDraft::KIND_WRITEOFF_PROPOSAL, 12.000, 'stale item', null, $this->admin());

        $result = $this->service()->discard($draft, 'not needed', $this->admin());

        $this->assertSame(ReconciliationFixDraft::STATUS_DISCARDED, $result->status);
        $this->assertSame('not needed', $result->reason);
    }
}
