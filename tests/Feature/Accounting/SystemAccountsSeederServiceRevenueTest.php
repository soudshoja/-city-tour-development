<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostingService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * W3-prereq lane A: SystemAccountsSeeder::resolveServices() now maps SERVICE_REVENUE for all 12
 * task types config('accounting.purpose_codes.service_types') names, not only flight/hotel — see
 * that method's own docblock and this build's RULING (1)/(4). These are the acceptance tests for
 * the extension: a fresh CoaSeeder chart must map every type (never report a gap for the ten
 * non-flight/hotel types anymore), and the mapping must be reachable end-to-end through the real
 * posting pipeline (AccountResolver -> PostingService::post()), not merely as an isolated unit
 * check — tests/Unit/Services/Accounting/AccountResolverTest.php's own
 * test_resolves_correct_account_for_service_type_dimension() only proves the resolver logic in
 * isolation, with hand-inserted system_accounts rows for flight/hotel; this file proves the REAL
 * seeders produce a resolvable mapping for a type that previously had none (visa), through the
 * full engine.
 */
class SystemAccountsSeederServiceRevenueTest extends AccountingTestCase
{
    /**
     * RULING (1)/(4): all 12 types config('accounting.purpose_codes.service_types') names must be
     * mapped on a fresh chart — CoaSeeder now seeds a dedicated "{Type} Booking Revenue" leaf
     * under "Direct Income" for every one of them (see CoaSeeder's own W3-prereq lane A comment),
     * so SystemAccountsSeeder::resolveServices()'s mapByName() call for SERVICE_REVENUE finds an
     * unambiguous leaf for every service_type, never falling through to a reported gap.
     */
    public function test_seeder_maps_service_revenue_for_all_twelve_task_types_on_a_fresh_chart(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder())->run();

        $serviceTypes = config('accounting.purpose_codes.service_types');
        $this->assertSame(
            ['flight', 'hotel', 'visa', 'insurance', 'tour', 'cruise', 'car', 'rail', 'esim', 'event', 'lounge', 'ferry'],
            array_values($serviceTypes),
            'Precondition: config must still name the same 12 task types in the same order this test asserts against.'
        );

        foreach ($serviceTypes as $serviceType) {
            $expectedName = ucfirst($serviceType).' Booking Revenue';

            $mapping = DB::table('system_accounts')
                ->where('company_id', $company->id)
                ->where('purpose_code', 'SERVICE_REVENUE')
                ->where('service_type', $serviceType)
                ->first();

            $this->assertNotNull(
                $mapping,
                "SERVICE_REVENUE/{$serviceType} must be mapped on a fresh CoaSeeder chart, never reported as a gap."
            );

            $account = Account::withoutGlobalScopes()->find($mapping->account_id);

            $this->assertNotNull($account, "The mapped account for SERVICE_REVENUE/{$serviceType} must still exist.");
            $this->assertSame($expectedName, $account->name);
            $this->assertSame('Direct Income', $account->parent->name);
            $this->assertSame('Income', $account->root->name);
            $this->assertTrue(
                AccountResolver::isLeaf($account),
                "{$expectedName} must resolve to a true leaf (no children)."
            );
        }

        // No other purpose code silently regressed to a gap by this change.
        $mappedCount = DB::table('system_accounts')
            ->where('company_id', $company->id)
            ->where('purpose_code', 'SERVICE_REVENUE')
            ->count();
        $this->assertSame(12, $mappedCount, 'Exactly 12 SERVICE_REVENUE rows — one per task type, no duplicates.');
    }

    /**
     * RULING (4): AccountResolver resolves SERVICE_REVENUE for a 'visa' line on the REAL seeders
     * through the FULL PostingService::post() pipeline — visa had no dedicated revenue leaf
     * before this build (SystemAccountsSeeder::resolveServices() used to skip() it), so this is a
     * genuine end-to-end proof, not a regression pin on an already-working type.
     */
    public function test_account_resolver_resolves_service_revenue_for_visa_through_posting_service(): void
    {
        config(['accounting.engine.enabled' => true]);

        $company = tap(
            Company::factory()->create(),
            fn (Company $c) => $c->forceFill(['posting_engine_enabled' => true])->save()
        );
        $this->trackCompanyForInvariants($company->id);

        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder())->run();

        $branch = Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => User::factory()->create()->id,
        ]);

        $expectedRevenueAccount = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', 'Visa Booking Revenue')
            ->firstOrFail();

        $debitAccount = Account::factory()->create(['company_id' => $company->id]);

        $draft = new DocumentDraft(
            companyId: $company->id,
            branchId: $branch->id,
            docType: 'JV',
            subType: null,
            docDate: now(),
            narration: 'SERVICE_REVENUE/visa purpose-code resolution pin (W3-prereq lane A)',
            lines: [
                new LineDraft(
                    purposeCode: '',
                    accountId: $debitAccount->id,
                    side: 'debit',
                    amount: 50.000,
                    currency: 'KWD',
                    originalAmount: 50.000,
                    exchangeRate: 1.0,
                    transactionType: 'TEST_DEBIT',
                ),
                new LineDraft(
                    purposeCode: 'SERVICE_REVENUE',
                    accountId: null,
                    side: 'credit',
                    amount: 50.000,
                    currency: 'KWD',
                    originalAmount: 50.000,
                    exchangeRate: 1.0,
                    transactionType: 'INCOME',
                    serviceType: 'visa',
                ),
            ],
        );

        $posted = app(PostingService::class)->post($draft);

        $this->assertNotNull($posted);

        $creditLine = DB::table('journal_entries')
            ->where('company_id', $company->id)
            ->where('account_id', $expectedRevenueAccount->id)
            ->first();

        $this->assertNotNull(
            $creditLine,
            "PostingService::post() must have resolved SERVICE_REVENUE/visa to 'Visa Booking Revenue' (via "
                .'AccountResolver + the real seeders) and posted a line to it.'
        );
        $this->assertEqualsWithDelta(50.000, (float) $creditLine->credit, 0.0005);
        $this->assertEqualsWithDelta(0.0, (float) $creditLine->debit, 0.0005);
    }
}
