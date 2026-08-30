<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Accounting;

use App\Models\AccountingAuditLog;
use App\Models\AccountingPeriod;
use App\Services\Accounting\AccountingLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P2.5.F: unit-level pins for {@see AccountingLog}'s two writer helpers, independent of any real
 * feeder — see AccountingAuditLogWritersTest for the end-to-end feeder pins.
 */
class AccountingLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->skipPermissionSeeder = true;
        parent::setUp();
    }

    private function makeCompanyId(): int
    {
        return \App\Models\Company::factory()->create()->id;
    }

    public function test_write_persists_every_field(): void
    {
        $row = AccountingLog::write(
            action: 'post',
            companyId: 5,
            subjectType: 'transaction',
            subjectId: 10,
            transactionId: 10,
            before: ['status' => 'draft'],
            after: ['status' => 'posted'],
            reason: 'because',
            actorId: 3,
            actorType: 'user',
            postingPeriod: '2026-08',
        );

        $fresh = AccountingAuditLog::find($row->id);
        $this->assertSame('post', $fresh->action);
        $this->assertSame(5, $fresh->company_id);
        $this->assertSame('transaction', $fresh->subject_type);
        $this->assertSame(10, $fresh->subject_id);
        $this->assertSame(10, $fresh->transaction_id);
        $this->assertSame(['status' => 'draft'], $fresh->before);
        $this->assertSame(['status' => 'posted'], $fresh->after);
        $this->assertSame('because', $fresh->reason);
        $this->assertSame(3, $fresh->actor_id);
        $this->assertSame('user', $fresh->actor_type);
        $this->assertSame('2026-08', $fresh->posting_period);
    }

    public function test_write_defaults_actor_type_to_system_when_no_actor_given(): void
    {
        $row = AccountingLog::write(action: 'legacy_path', companyId: 1);

        $this->assertSame('system', $row->actor_type);
        $this->assertNull($row->actor_id);
    }

    public function test_write_defaults_actor_type_to_user_when_an_actor_id_is_given(): void
    {
        $row = AccountingLog::write(action: 'post', companyId: 1, actorId: 42);

        $this->assertSame('user', $row->actor_type);
    }

    public function test_event_writes_a_row_with_generic_field_extraction(): void
    {
        AccountingLog::event('revenue_recognized', [
            'company_id' => 7,
            'task_id' => 55,
            'transaction_id' => 99,
        ]);

        $row = AccountingAuditLog::where('action', 'revenue_recognized')->first();
        $this->assertNotNull($row);
        $this->assertSame(7, $row->company_id);
        $this->assertSame('task', $row->subject_type);
        $this->assertSame(55, $row->subject_id);
        $this->assertSame(99, $row->transaction_id);
        $this->assertSame(99, $row->after['transaction_id']);
    }

    public function test_event_resolves_a_real_accounting_period_id_when_one_exists(): void
    {
        $companyId = $this->makeCompanyId();
        $period = AccountingPeriod::create(['company_id' => $companyId, 'year' => 2026, 'month' => 8, 'status' => 'locked']);
        $userId = \App\Models\User::factory()->create()->id;

        AccountingLog::event('period_locked_override', [
            'company_id' => $companyId,
            'year' => 2026,
            'month' => 8,
            'user_id' => $userId,
        ]);

        $row = AccountingAuditLog::where('action', 'period_locked_override')->first();
        $this->assertSame('accounting_period', $row->subject_type);
        $this->assertSame($period->id, $row->subject_id);
        $this->assertSame($userId, $row->actor_id);
        $this->assertSame('2026-08', $row->posting_period);
    }

    public function test_event_falls_back_to_null_subject_id_when_no_period_row_exists_yet(): void
    {
        AccountingLog::event('period_locked_override', [
            'company_id' => $this->makeCompanyId(),
            'year' => 2030,
            'month' => 1,
        ]);

        $row = AccountingAuditLog::where('action', 'period_locked_override')->first();
        $this->assertSame('accounting_period', $row->subject_type);
        $this->assertNull($row->subject_id);
    }

    public function test_normalize_subject_type_public_maps_known_fqcns_to_short_names(): void
    {
        $this->assertSame('transaction', AccountingLog::normalizeSubjectTypePublic(\App\Models\Transaction::class));
        $this->assertSame('journal_entry', AccountingLog::normalizeSubjectTypePublic(\App\Models\JournalEntry::class));
    }
}
