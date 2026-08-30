<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\AccountingAuditLog;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P2.5.F (p2_5-brief.md §P2.5.F): "APPEND-ONLY -- model has no update/delete ... no soft deletes."
 * Pins {@see AccountingAuditLog}'s boot guard directly, independent of any writer path.
 *
 * Fix-round 2026-08-30 (verify findings, CONFIRMED #1): the boot guard alone only intercepts
 * per-instance Eloquent paths ($model->update()/->delete(), ::destroy()) -- it is NEVER consulted
 * for a query-builder bulk mutation or raw SQL, because those never hydrate a model or fire model
 * events. The three tests below pin the DB-level trigger
 * (`database/migrations/2026_08_30_150001_p25f_add_append_only_triggers_to_accounting_audit_log.php`)
 * that closes exactly those gaps -- each one is the literal bypass the verify pass demonstrated.
 */
class AccountingAuditLogAppendOnlyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->skipPermissionSeeder = true;
        parent::setUp();
    }

    private function makeRow(): AccountingAuditLog
    {
        return AccountingAuditLog::create([
            'company_id' => 1,
            'action' => 'post',
            'actor_type' => 'system',
            'created_at' => now(),
        ]);
    }

    public function test_update_throws(): void
    {
        $row = $this->makeRow();

        $this->expectException(\RuntimeException::class);
        $row->update(['action' => 'tampered']);
    }

    public function test_force_fill_then_save_still_throws(): void
    {
        $row = $this->makeRow();

        $this->expectException(\RuntimeException::class);
        $row->forceFill(['action' => 'tampered'])->save();
    }

    public function test_delete_throws(): void
    {
        $row = $this->makeRow();

        $this->expectException(\RuntimeException::class);
        $row->delete();
    }

    public function test_destroy_throws_and_does_not_remove_the_row(): void
    {
        $row = $this->makeRow();

        try {
            AccountingAuditLog::destroy($row->id);
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertDatabaseHas('accounting_audit_log', ['id' => $row->id]);
    }

    public function test_model_has_no_soft_deletes(): void
    {
        $row = $this->makeRow();

        $this->assertArrayNotHasKey('deleted_at', $row->getAttributes());
        $this->assertFalse(method_exists($row, 'trashed'));
    }

    public function test_a_row_survives_untouched_after_a_failed_update_attempt(): void
    {
        $row = $this->makeRow();

        try {
            $row->update(['action' => 'tampered']);
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame('post', $row->fresh()->action);
    }

    // ── DB-trigger layer: paths the model boot guard cannot see ──────────────────────────────────

    public function test_eloquent_query_builder_bulk_update_is_blocked_by_the_db_trigger(): void
    {
        $row = $this->makeRow();

        // Never hydrates a model or fires ->updating() -- the boot guard is not consulted at all
        // on this path; only the BEFORE UPDATE trigger can stop it.
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('accounting_audit_log is append-only');

        try {
            AccountingAuditLog::where('id', $row->id)->update(['action' => 'tampered']);
        } finally {
            $this->assertSame('post', $row->fresh()->action);
        }
    }

    public function test_eloquent_query_builder_bulk_delete_is_blocked_by_the_db_trigger(): void
    {
        $row = $this->makeRow();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('accounting_audit_log is append-only');

        try {
            AccountingAuditLog::where('id', $row->id)->delete();
        } finally {
            $this->assertDatabaseHas('accounting_audit_log', ['id' => $row->id]);
        }
    }

    public function test_raw_query_builder_update_with_no_eloquent_involved_is_blocked_by_the_db_trigger(): void
    {
        $row = $this->makeRow();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('accounting_audit_log is append-only');

        try {
            DB::table('accounting_audit_log')->where('id', $row->id)->update(['action' => 'tampered']);
        } finally {
            $this->assertSame('post', $row->fresh()->action);
        }
    }

    public function test_raw_query_builder_delete_with_no_eloquent_involved_is_blocked_by_the_db_trigger(): void
    {
        $row = $this->makeRow();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('accounting_audit_log is append-only');

        try {
            DB::table('accounting_audit_log')->where('id', $row->id)->delete();
        } finally {
            $this->assertDatabaseHas('accounting_audit_log', ['id' => $row->id]);
        }
    }
}
