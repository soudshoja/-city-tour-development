<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA3;

use App\Models\Account;
use App\Models\Company;
use App\Services\Accounting\AccountResolver;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\File;
use Tests\Support\AccountingTestCase;

/**
 * CT-A3 E4 — CT-F38: the engine booked an agent's SALES COMMISSION through the PAYROLL pair.
 *
 * CT-A2 §5 row 5 and §4.2: every commission feeder resolved `SALARY_EXPENSE` (5160 "Agent
 * Salaries") / `SALARY_PAYABLE` (2201 "Salaries & Wages Payable") — KWD 15,207.752 on the replayed
 * population. A commission on a sale is not payroll; it belongs in 5130 "Commissions Expense
 * (Agents)" against 2210 "Commissions (Agents)", which is where the LEGACY ledger already put it
 * (`InvoiceController.php:3327`/`:3351`) and which CoaSeeder has always seeded.
 *
 * Two dedicated purpose codes now carry it, and `SALARY_EXPENSE`/`SALARY_PAYABLE` keep their one
 * genuinely-payroll call site (`AgentController::update()`'s monthly salary accrual).
 */
class E4CommissionPurposeTest extends AccountingTestCase
{
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        CoaSeeder::run($this->company->id);
        (new SystemAccountsSeeder)->run();
        $this->trackCompanyForInvariants($this->company->id);
    }

    private function resolvedCode(string $purposeCode): string
    {
        return (string) app(AccountResolver::class)->resolve($purposeCode, $this->company->id)->code;
    }

    public function test_commission_purposes_resolve_to_5130_and_2210(): void
    {
        $this->assertSame(
            '5130',
            $this->resolvedCode('COMMISSION_EXPENSE'),
            'A sales commission is a cost of sales, not payroll.'
        );
        $this->assertSame(
            '2210',
            $this->resolvedCode('COMMISSION_PAYABLE'),
            'The matching liability is Commissions (Agents), not Salaries & Wages Payable.'
        );
    }

    public function test_the_commission_leaves_sit_under_the_right_roots(): void
    {
        $expense = app(AccountResolver::class)->resolve('COMMISSION_EXPENSE', $this->company->id);
        $payable = app(AccountResolver::class)->resolve('COMMISSION_PAYABLE', $this->company->id);

        $rootName = fn (Account $a) => (string) Account::query()->withoutGlobalScopes()->find($a->root_id)?->name;

        $this->assertSame('Expenses', $rootName($expense));
        $this->assertSame('Liabilities', $rootName($payable));
        $this->assertSame('Commissions Expense (Agents)', $expense->name);
        $this->assertSame('Commissions (Agents)', $payable->name);
    }

    public function test_the_payroll_pair_still_resolves_to_its_own_accounts(): void
    {
        // E4 must not break the genuinely-payroll feeder that shares this vocabulary.
        $this->assertSame('5160', $this->resolvedCode('SALARY_EXPENSE'));
        $this->assertSame('2201', $this->resolvedCode('SALARY_PAYABLE'));
    }

    public function test_the_commission_and_payroll_purposes_never_resolve_to_the_same_leaf(): void
    {
        $this->assertNotSame($this->resolvedCode('COMMISSION_EXPENSE'), $this->resolvedCode('SALARY_EXPENSE'));
        $this->assertNotSame($this->resolvedCode('COMMISSION_PAYABLE'), $this->resolvedCode('SALARY_PAYABLE'));
    }

    /**
     * The architecture half: no commission FEEDER may still name the payroll purpose codes. Every
     * `SALARY_EXPENSE` / `SALARY_PAYABLE` construction site left in `app/` must belong to
     * AgentController's salary accrual — the one real payroll event.
     */
    public function test_no_commission_feeder_still_uses_the_payroll_purpose_codes(): void
    {
        $offenders = [];

        foreach (File::allFiles(app_path()) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace('\\', '/', $file->getRelativePathname());

            if ($relative === 'Http/Controllers/AgentController.php') {
                continue; // the one genuine payroll feeder
            }

            $contents = (string) file_get_contents($file->getPathname());

            if (preg_match("/purposeCode:\s*'SALARY_(EXPENSE|PAYABLE)'/", $contents)) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'CT-F38: a commission must never resolve the payroll pair. Offending file(s): '
            .implode(', ', $offenders)
        );
    }
}
