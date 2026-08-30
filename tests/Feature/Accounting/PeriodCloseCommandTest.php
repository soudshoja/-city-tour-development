<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\AccountingTestCase;

/**
 * P2.5.C: `accounting:period:close` CLI wiring around {@see \App\Services\Accounting\PeriodCloseService}.
 */
class PeriodCloseCommandTest extends AccountingTestCase
{
    private function makeCompany(): Company
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        return $company;
    }

    public function test_close_with_soft_flag_soft_closes_the_period(): void
    {
        $company = $this->makeCompany();
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);

        $exit = Artisan::call('accounting:period:close', [
            'company' => $company->id, 'period' => '2026-03', '--soft' => true, '--user' => $admin->id,
        ]);

        $this->assertSame(0, $exit);
        $this->assertDatabaseHas('accounting_periods', [
            'company_id' => $company->id, 'year' => 2026, 'month' => 3, 'status' => AccountingPeriod::STATUS_SOFT_CLOSED,
        ]);
    }

    public function test_close_with_lock_flag_locks_the_period(): void
    {
        $company = $this->makeCompany();
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);

        $exit = Artisan::call('accounting:period:close', [
            'company' => $company->id, 'period' => '2026-03', '--lock' => true, '--user' => $admin->id,
        ]);

        $this->assertSame(0, $exit);
        $this->assertDatabaseHas('accounting_periods', [
            'company_id' => $company->id, 'year' => 2026, 'month' => 3, 'status' => AccountingPeriod::STATUS_LOCKED,
        ]);
    }

    public function test_close_requires_exactly_one_of_soft_or_lock(): void
    {
        $company = $this->makeCompany();
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);

        $exit = Artisan::call('accounting:period:close', [
            'company' => $company->id, 'period' => '2026-03', '--user' => $admin->id,
        ]);

        $this->assertSame(1, $exit);
    }

    public function test_close_fails_with_invalid_period_format(): void
    {
        $company = $this->makeCompany();
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);

        $exit = Artisan::call('accounting:period:close', [
            'company' => $company->id, 'period' => 'not-a-period', '--lock' => true, '--user' => $admin->id,
        ]);

        $this->assertSame(1, $exit);
    }

    public function test_close_is_blocked_by_a_draft_document_in_the_period(): void
    {
        $company = $this->makeCompany();
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);

        Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => 1, 'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'JV', 'amount' => 10, 'description' => 'draft', 'reference_type' => 'Invoice',
            'reference_number' => 'D-'.uniqid(), 'name' => 'draft', 'transaction_date' => '2026-03-05',
            'doc_type' => 'JV', 'doc_year' => 2026, 'posting_status' => 'draft',
            'total_debit' => 10, 'total_credit' => 10, 'idempotency_key' => uniqid('draft:'),
        ]);

        $exit = Artisan::call('accounting:period:close', [
            'company' => $company->id, 'period' => '2026-03', '--lock' => true, '--user' => $admin->id,
        ]);

        $this->assertSame(1, $exit);
        $this->assertDatabaseMissing('accounting_periods', [
            'company_id' => $company->id, 'year' => 2026, 'month' => 3, 'status' => AccountingPeriod::STATUS_LOCKED,
        ]);
    }

    public function test_reopen_writes_audit_row(): void
    {
        $company = $this->makeCompany();
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        AccountingPeriod::create(['company_id' => $company->id, 'year' => 2026, 'month' => 3, 'status' => AccountingPeriod::STATUS_LOCKED]);

        $exit = Artisan::call('accounting:period:close', [
            'company' => $company->id, 'period' => '2026-03', '--reopen' => true, '--reason' => 'late audit fix', '--user' => $admin->id,
        ]);

        $this->assertSame(0, $exit);
        $this->assertDatabaseHas('accounting_periods', [
            'company_id' => $company->id, 'year' => 2026, 'month' => 3, 'status' => AccountingPeriod::STATUS_OPEN,
            'reopened_by' => $admin->id, 'reopen_reason' => 'late audit fix',
        ]);
    }

    public function test_reopen_requires_accounting_period_reopen_permission(): void
    {
        $company = $this->makeCompany();
        $agent = User::factory()->create(['role_id' => Role::AGENT]);
        AccountingPeriod::create(['company_id' => $company->id, 'year' => 2026, 'month' => 3, 'status' => AccountingPeriod::STATUS_LOCKED]);

        $exit = Artisan::call('accounting:period:close', [
            'company' => $company->id, 'period' => '2026-03', '--reopen' => true, '--reason' => 'x', '--user' => $agent->id,
        ]);

        $this->assertSame(1, $exit);
        $this->assertDatabaseHas('accounting_periods', [
            'company_id' => $company->id, 'year' => 2026, 'month' => 3, 'status' => AccountingPeriod::STATUS_LOCKED,
        ]);
    }
}
