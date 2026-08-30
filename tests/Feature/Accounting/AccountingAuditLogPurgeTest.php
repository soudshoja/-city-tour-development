<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Console\Commands\AccountingAuditLogPurge;
use App\Models\AccountingAuditLog;
use App\Models\Company;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * P2.5.F fix-round (2026-08-30): regression coverage for the interaction between
 * {@see AccountingAuditLogPurge} (an already-shipped, documented, opt-in exception to
 * append-only — "archival job only when set") and the new DB-level append-only trigger added in
 * this same fix round (migration
 * `2026_08_30_150001_p25f_add_append_only_triggers_to_accounting_audit_log.php`). The trigger's
 * first version blocked every DELETE unconditionally, which would have silently broken this
 * command outright; the fixed trigger gates its DELETE block on a session-scoped MySQL user
 * variable (`@accounting_audit_log_allow_delete`) that only this command's own delete call sets.
 */
class AccountingAuditLogPurgeTest extends AccountingTestCase
{
    public function test_purge_deletes_rows_past_the_retention_window_through_the_db_trigger(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        Setting::create([
            'company_id' => $company->id,
            'key' => 'accounting.audit_log_retention_months',
            'value' => 1,
            'type' => 'integer',
        ]);

        $old = AccountingAuditLog::create([
            'company_id' => $company->id,
            'action' => 'post',
            'actor_type' => 'system',
            'created_at' => now()->subMonths(3),
        ]);
        $recent = AccountingAuditLog::create([
            'company_id' => $company->id,
            'action' => 'post',
            'actor_type' => 'system',
            'created_at' => now(),
        ]);

        $this->artisan(AccountingAuditLogPurge::class, ['--company' => $company->id])
            ->assertExitCode(0);

        $this->assertDatabaseMissing('accounting_audit_log', ['id' => $old->id]);
        $this->assertDatabaseHas('accounting_audit_log', ['id' => $recent->id]);
    }

    public function test_purge_skips_a_company_with_no_retention_option_set(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $old = AccountingAuditLog::create([
            'company_id' => $company->id,
            'action' => 'post',
            'actor_type' => 'system',
            'created_at' => now()->subYears(5),
        ]);

        $this->artisan(AccountingAuditLogPurge::class, ['--company' => $company->id])
            ->assertExitCode(0);

        $this->assertDatabaseHas('accounting_audit_log', ['id' => $old->id]);
    }

    /**
     * The escape hatch is scoped to the purge command's own delete only — the session variable it
     * sets is reset to 0 immediately after, so a normal caller on that same connection right after
     * the command finishes is still blocked exactly as before.
     */
    public function test_delete_escape_hatch_is_closed_again_immediately_after_the_purge_runs(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        Setting::create([
            'company_id' => $company->id,
            'key' => 'accounting.audit_log_retention_months',
            'value' => 1,
            'type' => 'integer',
        ]);

        AccountingAuditLog::create([
            'company_id' => $company->id,
            'action' => 'post',
            'actor_type' => 'system',
            'created_at' => now()->subMonths(3),
        ]);

        $this->artisan(AccountingAuditLogPurge::class, ['--company' => $company->id])
            ->assertExitCode(0);

        $survivor = AccountingAuditLog::create([
            'company_id' => $company->id,
            'action' => 'post',
            'actor_type' => 'system',
            'created_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->expectExceptionMessage('accounting_audit_log is append-only');

        try {
            DB::table('accounting_audit_log')->where('id', $survivor->id)->delete();
        } finally {
            $this->assertDatabaseHas('accounting_audit_log', ['id' => $survivor->id]);
        }
    }

    public function test_dry_run_deletes_nothing(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        Setting::create([
            'company_id' => $company->id,
            'key' => 'accounting.audit_log_retention_months',
            'value' => 1,
            'type' => 'integer',
        ]);

        $old = AccountingAuditLog::create([
            'company_id' => $company->id,
            'action' => 'post',
            'actor_type' => 'system',
            'created_at' => now()->subMonths(3),
        ]);

        $this->artisan(AccountingAuditLogPurge::class, ['--company' => $company->id, '--dry-run' => true])
            ->assertExitCode(0);

        $this->assertDatabaseHas('accounting_audit_log', ['id' => $old->id]);
    }
}
