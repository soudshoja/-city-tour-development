<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Accounting;

use App\Exceptions\Accounting\BankLeafCurrencyMismatchException;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\FixedAsset;
use App\Models\User;
use App\Services\Accounting\FixedAssets\DepreciationRunService;
use App\Services\Accounting\FixedAssets\FixedAssetService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\AccountingTestCase;

/**
 * accounting-builds T2 (Lane B): {@see FixedAssetService} — register validation, optional
 * capitalisation posting (idempotent, engine-OFF no-op), and derived NBV (L8, MP-2-1/MP-2-2).
 */
class FixedAssetServiceTest extends AccountingTestCase
{
    private function service(): FixedAssetService
    {
        return app(FixedAssetService::class);
    }

    /** @return array{0: Company, 1: Branch} */
    private function makeEngineOnCompany(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();

        $owner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $owner->id]);

        config(['accounting.engine.enabled' => true]);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $this->trackCompanyForInvariants($company->id);

        return [$company, $branch];
    }

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    private function makeAsset(Company $company, array $overrides = []): FixedAsset
    {
        return $this->service()->create(array_merge([
            'company_id' => $company->id,
            'branch_id' => null,
            'asset_class' => 'CAPITAL_EQUIPMENT',
            'name' => 'Test Laptop Fleet',
            'code' => 'FA-TEST-001',
            'cost' => 1200.000,
            'salvage' => 0.000,
            'acquisition_date' => Carbon::create(2026, 1, 1),
            'in_service_date' => Carbon::create(2026, 1, 1),
            'useful_life_months' => 12,
        ], $overrides));
    }

    public function test_create_rejects_salvage_equal_to_cost(): void
    {
        [$company] = $this->makeEngineOnCompany();

        $this->expectException(\InvalidArgumentException::class);

        $this->makeAsset($company, ['cost' => 500.000, 'salvage' => 500.000]);
    }

    public function test_create_rejects_salvage_greater_than_cost(): void
    {
        [$company] = $this->makeEngineOnCompany();

        $this->expectException(\InvalidArgumentException::class);

        $this->makeAsset($company, ['cost' => 500.000, 'salvage' => 600.000]);
    }

    public function test_create_rejects_life_less_than_one_month(): void
    {
        [$company] = $this->makeEngineOnCompany();

        $this->expectException(\InvalidArgumentException::class);

        $this->makeAsset($company, ['useful_life_months' => 0]);
    }

    /**
     * MP-2-2: an unknown asset_class must THROW, never silently be accepted/skipped.
     */
    public function test_create_rejects_unknown_asset_class(): void
    {
        [$company] = $this->makeEngineOnCompany();

        $this->expectException(\InvalidArgumentException::class);

        $this->makeAsset($company, ['asset_class' => 'SPACESHIP']);
    }

    public function test_nbv_of_a_freshly_created_asset_equals_cost(): void
    {
        [$company] = $this->makeEngineOnCompany();
        $asset = $this->makeAsset($company);

        $this->assertSame(1200.0, $this->service()->nbv($asset));
    }

    public function test_capitalise_posts_a_balanced_document_and_is_idempotent(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $asset = $this->makeAsset($company, ['branch_id' => $branch->id]);

        $bankAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1201')->firstOrFail();

        $first = $this->service()->capitalise($asset, null, $bankAccount->id);
        $this->assertNotNull($first);
        $asset->refresh();
        $this->assertSame(FixedAsset::STATUS_ACTIVE, $asset->status);
        $this->assertNotNull($asset->acquisition_transaction_id);
        $firstTransactionId = $asset->acquisition_transaction_id;

        // Second call must return the SAME document, not post again.
        $second = $this->service()->capitalise($asset, null, $bankAccount->id);
        $this->assertNotNull($second);
        $this->assertSame($first->transaction->id, $second->transaction->id);

        $asset->refresh();
        $this->assertSame($firstTransactionId, $asset->acquisition_transaction_id);

        $costAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1810')->firstOrFail();
        $lineCount = \App\Models\JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('account_id', $costAccount->id)->where('task_id', $asset->id)->count();
        $this->assertSame(1, $lineCount, 'A second capitalise() call must not create a second cost line.');
    }

    /**
     * accounting-builds Wave 3 lane I item A1 (T10 §12 / Lane B sign-off finding): a bank leaf
     * explicitly denominated in a currency other than the document's (a fixed-asset document has
     * none of its own — every line here is hardcoded 'KWD', so the document's currency is the
     * company's base currency) must be rejected by
     * {@see \App\Services\Accounting\AccountResolver::assertUnderBankGroup()}'s own currency
     * guard, composed transparently through {@see FixedAssetService::capitalise()}.
     */
    public function test_capitalise_rejects_a_usd_bank_leaf_for_a_kwd_asset(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $asset = $this->makeAsset($company, ['branch_id' => $branch->id]);

        $bankAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1201')->firstOrFail();
        $bankAccount->currency = 'USD';
        $bankAccount->save();

        try {
            $this->service()->capitalise($asset, null, $bankAccount->id);
            $this->fail('Expected BankLeafCurrencyMismatchException.');
        } catch (BankLeafCurrencyMismatchException $e) {
            $this->assertSame($bankAccount->id, $e->accountId);
            $this->assertSame('USD', $e->accountCurrency);
            $this->assertSame('KWD', $e->documentCurrency);
        }

        $asset->refresh();
        $this->assertNull($asset->acquisition_transaction_id, 'A currency-refused capitalise() must post nothing and leave the asset uncapitalised.');
    }

    /**
     * Companion acceptance case: a bank leaf explicitly denominated in KWD (the document's own
     * currency) must be accepted, exactly as before this guard existed.
     */
    public function test_capitalise_accepts_a_kwd_bank_leaf(): void
    {
        [$company, $branch] = $this->makeEngineOnCompany();
        $asset = $this->makeAsset($company, ['branch_id' => $branch->id]);

        $bankAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1201')->firstOrFail();
        $bankAccount->currency = 'KWD';
        $bankAccount->save();

        $result = $this->service()->capitalise($asset, null, $bankAccount->id);

        $this->assertNotNull($result);
        $asset->refresh();
        $this->assertSame(FixedAsset::STATUS_ACTIVE, $asset->status);
    }

    public function test_capitalise_engine_off_is_a_logged_noop(): void
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();
        // Engine deliberately left OFF for this company (no accounting:engine --enable call).

        $asset = $this->makeAsset($company);
        $bankAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1201')->firstOrFail();

        $result = $this->service()->capitalise($asset, null, $bankAccount->id);

        $this->assertNull($result, 'Engine OFF must return null, never a raw write.');
        $asset->refresh();
        $this->assertNull($asset->acquisition_transaction_id);
        $this->assertSame(0, \App\Models\Transaction::withoutGlobalScopes()->where('company_id', $company->id)->count());
    }

    /**
     * MP-2-1 anchor: NBV must be RE-DERIVED on every call, never cached — posting a second
     * month of depreciation must change the very next nbv() read with no stale intermediate
     * value. (Adversarial verification mutates FixedAssetService::nbv() to memoize and confirms
     * this exact assertion sequence fails.)
     */
    public function test_nbv_is_freshly_derived_after_each_posted_depreciation_month(): void
    {
        [$company] = $this->makeEngineOnCompany();
        $asset = $this->makeAsset($company, [
            'cost' => 1200.000,
            'salvage' => 0.000,
            'useful_life_months' => 12,
            'status' => FixedAsset::STATUS_ACTIVE,
        ]);

        $costAtStart = $this->service()->nbv($asset);
        $this->assertSame(1200.0, $costAtStart);

        $depreciation = app(DepreciationRunService::class);

        $depreciation->runForMonth($company->id, 2026, 1);
        $nbvAfterMonth1 = $this->service()->nbv($asset);
        $this->assertSame(1100.0, $nbvAfterMonth1);

        $depreciation->runForMonth($company->id, 2026, 2);
        $nbvAfterMonth2 = $this->service()->nbv($asset);
        $this->assertSame(1000.0, $nbvAfterMonth2);
        $this->assertNotSame($nbvAfterMonth1, $nbvAfterMonth2, 'A cached NBV would return the stale month-1 value here.');
    }
}
