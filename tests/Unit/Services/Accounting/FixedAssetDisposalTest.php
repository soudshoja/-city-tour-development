<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Accounting;

use App\Exceptions\Accounting\BankLeafCurrencyMismatchException;
use App\Models\Account;
use App\Models\Company;
use App\Models\FixedAsset;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\FixedAssets\DepreciationRunService;
use App\Services\Accounting\FixedAssets\FixedAssetService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\AccountingTestCase;

/**
 * accounting-builds T4 (Lane B): {@see FixedAssetService::dispose()} — gain AND loss cases at
 * exact fils, the proceeds-line routing (bank/cash/credit), idempotency (MP-4-3), and the
 * gain/loss leaf identity (MP-4-1) / derived-NBV (MP-4-2) anchors.
 */
class FixedAssetDisposalTest extends AccountingTestCase
{
    private function service(): FixedAssetService
    {
        return app(FixedAssetService::class);
    }

    private function depreciation(): DepreciationRunService
    {
        return app(DepreciationRunService::class);
    }

    private function makeEngineOnCompany(): Company
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();
        User::factory()->create();

        config(['accounting.engine.enabled' => true]);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $this->trackCompanyForInvariants($company->id);

        return $company;
    }

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    /** Cost 1000, salvage 0, life 5 -> exactly 200.000/month, no rounding noise. */
    private function makeAssetWithTwoMonthsDepreciated(Company $company): FixedAsset
    {
        $asset = $this->service()->create([
            'company_id' => $company->id,
            'asset_class' => 'CAPITAL_EQUIPMENT',
            'name' => 'Disposal Test Asset',
            'cost' => 1000.000,
            'salvage' => 0.000,
            'acquisition_date' => Carbon::create(2026, 1, 1),
            'in_service_date' => Carbon::create(2026, 1, 1),
            'useful_life_months' => 5,
            'status' => FixedAsset::STATUS_ACTIVE,
        ]);

        $this->depreciation()->runForMonth($company->id, 2026, 1);
        $this->depreciation()->runForMonth($company->id, 2026, 2);

        $asset->refresh();
        $this->assertSame(600.0, $this->service()->nbv($asset), 'Fixture precondition: NBV must be 600 after two months.');

        return $asset;
    }

    public function test_disposal_at_a_gain_posts_exact_fils(): void
    {
        $company = $this->makeEngineOnCompany();
        $asset = $this->makeAssetWithTwoMonthsDepreciated($company);

        $result = $this->service()->dispose($asset, Carbon::create(2026, 3, 15), 650.000);

        $this->assertNotNull($result);
        $asset->refresh();
        $this->assertSame(FixedAsset::STATUS_DISPOSED, $asset->status);
        $this->assertSame(650.0, (float) $asset->disposal_proceeds);

        $lines = JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('transaction_id', $result->transaction->id)->get()->keyBy(fn ($l) => (int) $l->account_id);

        $accumAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1881')->firstOrFail();
        $costAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1810')->firstOrFail();
        $gainAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '4141')->firstOrFail();

        $this->assertSame(400.0, (float) $lines[$accumAccount->id]->debit, 'Accumulated depreciation cleared must equal 400 (200/month x 2).');
        $this->assertSame(0.0, (float) $lines[$accumAccount->id]->credit);

        $this->assertSame(1000.0, (float) $lines[$costAccount->id]->credit, 'Full cost must be removed.');
        $this->assertSame(50.0, (float) $lines[$gainAccount->id]->credit, 'Gain = proceeds(650) - NBV(600) = 50.');
        $this->assertSame(0.0, (float) $lines[$gainAccount->id]->debit);

        $totalDebit = $lines->sum('debit');
        $totalCredit = $lines->sum('credit');
        $this->assertEqualsWithDelta($totalDebit, $totalCredit, 0.0005, 'Disposal document must balance.');
    }

    public function test_disposal_at_a_loss_posts_exact_fils(): void
    {
        $company = $this->makeEngineOnCompany();
        $asset = $this->makeAssetWithTwoMonthsDepreciated($company);

        $result = $this->service()->dispose($asset, Carbon::create(2026, 3, 15), 550.000);

        $this->assertNotNull($result);

        $lines = JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('transaction_id', $result->transaction->id)->get()->keyBy(fn ($l) => (int) $l->account_id);

        $lossAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '5220')->firstOrFail();
        $costAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1810')->firstOrFail();

        $this->assertSame(50.0, (float) $lines[$lossAccount->id]->debit, 'Loss = NBV(600) - proceeds(550) = 50.');
        $this->assertSame(0.0, (float) $lines[$lossAccount->id]->credit);
        $this->assertSame(1000.0, (float) $lines[$costAccount->id]->credit);

        $totalDebit = $lines->sum('debit');
        $totalCredit = $lines->sum('credit');
        $this->assertEqualsWithDelta($totalDebit, $totalCredit, 0.0005, 'Disposal document must balance.');
    }

    public function test_disposal_via_explicit_bank_account_uses_the_named_leaf(): void
    {
        $company = $this->makeEngineOnCompany();
        $asset = $this->makeAssetWithTwoMonthsDepreciated($company);
        $bankAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1201')->firstOrFail();

        $result = $this->service()->dispose($asset, Carbon::create(2026, 3, 15), 650.000, $bankAccount->id);

        $this->assertNotNull($result);

        $bankLine = JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('transaction_id', $result->transaction->id)->where('account_id', $bankAccount->id)->first();

        $this->assertNotNull($bankLine, 'Proceeds must land on the explicit bank leaf.');
        $this->assertSame(650.0, (float) $bankLine->debit);
    }

    /**
     * accounting-builds Wave 3 lane I item A1 (T10 §12 / Lane B sign-off finding): the proceeds
     * leaf on a disposal is validated by the same
     * {@see \App\Services\Accounting\AccountResolver::assertUnderBankGroup()} currency guard as
     * capitalise() — a USD-denominated bank leaf for a KWD asset's disposal proceeds must be
     * rejected.
     */
    public function test_dispose_via_explicit_bank_account_rejects_a_usd_bank_leaf(): void
    {
        $company = $this->makeEngineOnCompany();
        $asset = $this->makeAssetWithTwoMonthsDepreciated($company);
        $bankAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1201')->firstOrFail();
        $bankAccount->currency = 'USD';
        $bankAccount->save();

        try {
            $this->service()->dispose($asset, Carbon::create(2026, 3, 15), 650.000, $bankAccount->id);
            $this->fail('Expected BankLeafCurrencyMismatchException.');
        } catch (BankLeafCurrencyMismatchException $e) {
            $this->assertSame($bankAccount->id, $e->accountId);
            $this->assertSame('USD', $e->accountCurrency);
            $this->assertSame('KWD', $e->documentCurrency);
        }

        $asset->refresh();
        $this->assertSame(FixedAsset::STATUS_ACTIVE, $asset->status, 'A currency-refused dispose() must post nothing and leave the asset undisposed.');
    }

    /**
     * MP-4-3: disposing an already-disposed asset must return the SAME document, never post a
     * second one.
     */
    public function test_disposing_twice_returns_the_existing_document(): void
    {
        $company = $this->makeEngineOnCompany();
        $asset = $this->makeAssetWithTwoMonthsDepreciated($company);

        $first = $this->service()->dispose($asset, Carbon::create(2026, 3, 15), 650.000);
        $asset->refresh();

        $second = $this->service()->dispose($asset, Carbon::create(2026, 3, 15), 999.000); // different proceeds — must be ignored

        $this->assertSame($first->transaction->id, $second->transaction->id);
        $this->assertSame(
            1,
            Transaction::withoutGlobalScopes()->where('company_id', $company->id)->where('doc_type', 'DSP')->count(),
            'Only one DSP document must ever exist for this asset.'
        );
        $asset->refresh();
        $this->assertSame(650.0, (float) $asset->disposal_proceeds, 'The second call must not overwrite the recorded proceeds with the new (ignored) value.');
    }

    public function test_dispose_engine_off_is_a_logged_noop(): void
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();

        $asset = FixedAsset::create([
            'company_id' => $company->id,
            'asset_class' => 'CAPITAL_EQUIPMENT',
            'name' => 'Off Path Disposal Asset',
            'cost' => 500.000,
            'salvage' => 0.000,
            'acquisition_date' => Carbon::create(2026, 1, 1),
            'in_service_date' => Carbon::create(2026, 1, 1),
            'useful_life_months' => 5,
            'status' => FixedAsset::STATUS_ACTIVE,
        ]);

        $result = $this->service()->dispose($asset, Carbon::create(2026, 3, 15), 400.000);

        $this->assertNull($result);
        $asset->refresh();
        $this->assertSame(FixedAsset::STATUS_ACTIVE, $asset->status, 'Engine OFF must never flip status.');
        $this->assertNull($asset->disposal_transaction_id);
    }
}
