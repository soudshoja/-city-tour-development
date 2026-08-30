<?php

namespace Tests\Unit\Services;

use App\Models\Company;
use App\Models\Country;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\SupplierStatusMap;
use App\Models\Task;
use App\Models\TaskStatusEvent;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TaskStatusService;
use Carbon\Carbon;
use Database\Seeders\SupplierStatusMapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * W6.S "Per-supplier status map" + "Hold/confirmed follow-up lifecycle" (w6-brief.md, owner
 * additions 2026-08-28). Covers:
 *  - the table-driven parity test the brief itself requires (seeded defaults reproduce every
 *    deleted hard-coded branch byte-for-byte);
 *  - the needs_review path (no row at any level -> no financial dispatch, audit row written);
 *  - the 4-level resolution order (see TaskStatusService::mapStatus()'s own docblock for why this
 *    sub-wave clarifies the brief's 3-line summary into 4 explicit levels);
 *  - linkOriginalTask()'s consolidated behaviour;
 *  - expire()'s lifecycle contract (grace hours, hold_auto_expire option, audit row, zero ledger
 *    rows, issued tasks untouched).
 */
class TaskStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    private TaskStatusService $service;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TaskStatusService;

        $country = Country::factory()->create();
        $owner = User::factory()->create(['role_id' => Role::COMPANY]);
        $this->company = Company::factory()->create([
            'user_id' => $owner->id,
            'country_id' => $country->id,
        ]);
    }

    private function seedDefaults(): void
    {
        (new SupplierStatusMapSeeder)->run();
    }

    // ---------------------------------------------------------------------------------------
    // Table-driven parity test (w6-brief.md's own worked table)
    // ---------------------------------------------------------------------------------------

    public function test_seeded_defaults_reproduce_every_deleted_hard_coded_branch(): void
    {
        $jazeera = Supplier::factory()->create(['name' => 'Jazeera Airways']);
        $flyDubai = Supplier::factory()->create(['name' => 'Fly Dubai']);
        $vfs = Supplier::factory()->create(['name' => 'VFS']);
        $magic = Supplier::factory()->create(['name' => 'Magic Holiday']);
        $otherSupplier = Supplier::factory()->create(['name' => 'Some Other Airline']);

        $this->seedDefaults();

        foreach (['air', 'webhook'] as $channel) {
            foreach ([$jazeera, $flyDubai, $vfs] as $supplier) {
                $onHold = $this->service->mapStatus($supplier, $channel, 'on hold', $this->company->id);
                $this->assertSame('confirmed', $onHold->canonicalStatus, "{$supplier->name}/{$channel} on hold->confirmed");

                $confirmed = $this->service->mapStatus($supplier, $channel, 'confirmed', $this->company->id);
                $this->assertSame('issued', $confirmed->canonicalStatus, "{$supplier->name}/{$channel} confirmed->issued");
            }
        }

        // Magic Holiday
        $this->assertSame('issued', $this->service->mapStatus($magic, 'magic', 'OK', $this->company->id)->canonicalStatus);
        $this->assertSame('reissued', $this->service->mapStatus($magic, 'magic', 'AM', $this->company->id, ['total' => 50])->canonicalStatus);
        $this->assertSame('refund', $this->service->mapStatus($magic, 'magic', 'AM', $this->company->id, ['total' => 0])->canonicalStatus);
        $this->assertSame('refund', $this->service->mapStatus($magic, 'magic', 'AM', $this->company->id, ['total' => -10])->canonicalStatus);
        $this->assertSame('confirmed', $this->service->mapStatus($magic, 'magic', 'RQ', $this->company->id)->canonicalStatus);
        $this->assertSame('void', $this->service->mapStatus($magic, 'magic', 'XX', $this->company->id)->canonicalStatus);
        $this->assertSame('void', $this->service->mapStatus($magic, 'magic', 'XP', $this->company->id)->canonicalStatus);

        // AIR channel raw codes (already near-canonical from AirFileParser::extractStatus()).
        $this->assertSame('refund', $this->service->mapStatus(null, 'air', 'RF', $this->company->id)->canonicalStatus);
        $this->assertSame('void', $this->service->mapStatus(null, 'air', 'VOID', $this->company->id)->canonicalStatus);
        $this->assertSame('reissued', $this->service->mapStatus(null, 'air', 'FO', $this->company->id)->canonicalStatus);
        $this->assertSame('emd', $this->service->mapStatus(null, 'air', 'EMD', $this->company->id)->canonicalStatus);

        // default (no matching row for THIS supplier) -> issued, for a supplier NOT one of the
        // three named ones.
        $this->assertSame(
            'issued',
            $this->service->mapStatus($otherSupplier, 'air', 'confirmed', $this->company->id)->canonicalStatus
        );

        // Owner intent: "no on-hold booking from GDS at this stage" -- an 'on hold' raw status
        // from any OTHER supplier has NO default row, so it is needs_review, not a silent
        // pass-through.
        $unmapped = $this->service->mapStatus($otherSupplier, 'air', 'on hold', $this->company->id);
        $this->assertTrue($unmapped->isUnmapped());
        $this->assertSame('needs_review', $unmapped->canonicalStatus);
    }

    public function test_on_hold_never_produced_for_unmapped_supplier_even_with_only_seeded_defaults(): void
    {
        $this->seedDefaults();
        $otherSupplier = Supplier::factory()->create(['name' => 'Random Consolidator']);

        $result = $this->service->mapStatus($otherSupplier, 'air', 'on hold', $this->company->id);

        $this->assertSame('needs_review', $result->canonicalStatus);

        // Once a company adds its OWN explicit on_hold-producing row, it resolves normally.
        SupplierStatusMap::create([
            'company_id' => $this->company->id,
            'supplier_id' => $otherSupplier->id,
            'channel' => 'air',
            'raw_status' => 'on hold',
            'canonical_status' => 'on_hold',
            'priority' => 0,
            'active' => true,
        ]);

        $afterOverride = $this->service->mapStatus($otherSupplier, 'air', 'on hold', $this->company->id);
        $this->assertSame('on_hold', $afterOverride->canonicalStatus);
        $this->assertSame('on hold', $this->service->toTaskStatusValue($afterOverride->canonicalStatus));
    }

    // ---------------------------------------------------------------------------------------
    // needs_review + audit row
    // ---------------------------------------------------------------------------------------

    public function test_unmapped_raw_status_writes_an_audit_row_and_never_dispatches_financials(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Totally Unknown Supplier']);

        $result = $this->service->mapStatus($supplier, 'air', 'SOME-UNKNOWN-CODE', $this->company->id);

        $this->assertTrue($result->isUnmapped());
        $this->assertDatabaseHas('task_status_events', [
            'company_id' => $this->company->id,
            'event' => 'status_unmapped',
            'channel' => 'air',
            'raw_status' => 'SOME-UNKNOWN-CODE',
        ]);
    }

    // ---------------------------------------------------------------------------------------
    // Resolution order
    // ---------------------------------------------------------------------------------------

    public function test_resolution_order_company_supplier_beats_company_default_beats_global(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Order Test Airline']);

        // Global default (level 4).
        SupplierStatusMap::create([
            'company_id' => null, 'supplier_id' => null,
            'channel' => 'air', 'raw_status' => 'X1',
            'canonical_status' => 'issued', 'active' => true, 'priority' => 0,
        ]);
        $this->assertSame('issued', $this->service->mapStatus($supplier, 'air', 'X1', $this->company->id)->canonicalStatus);

        // Company-wide default (level 2) overrides the global default.
        SupplierStatusMap::create([
            'company_id' => $this->company->id, 'supplier_id' => null,
            'channel' => 'air', 'raw_status' => 'X1',
            'canonical_status' => 'confirmed', 'active' => true, 'priority' => 0,
        ]);
        $this->assertSame('confirmed', $this->service->mapStatus($supplier, 'air', 'X1', $this->company->id)->canonicalStatus);

        // Company+supplier (level 1) overrides the company-wide default.
        SupplierStatusMap::create([
            'company_id' => $this->company->id, 'supplier_id' => $supplier->id,
            'channel' => 'air', 'raw_status' => 'X1',
            'canonical_status' => 'void', 'active' => true, 'priority' => 0,
        ]);
        $this->assertSame('void', $this->service->mapStatus($supplier, 'air', 'X1', $this->company->id)->canonicalStatus);
    }

    // ---------------------------------------------------------------------------------------
    // linkOriginalTask
    // ---------------------------------------------------------------------------------------

    public function test_link_original_task_links_reissued_void_refund_emd_to_issued_or_reissued_original(): void
    {
        $original = Task::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'issued',
            'reference' => 'ORIG-001',
            'passenger_name' => 'Jane Doe',
        ]);

        $found = $this->service->linkOriginalTask('void', 'ORIG-001', 'ORIG-001', 'Jane Doe', $this->company->id);

        $this->assertNotNull($found);
        $this->assertSame($original->id, $found->id);
    }

    public function test_link_original_task_links_issued_to_confirmed(): void
    {
        $confirmed = Task::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'confirmed',
            'reference' => 'REF-002',
            'passenger_name' => 'John Roe',
        ]);

        $found = $this->service->linkOriginalTask('issued', 'REF-002', null, 'John Roe', $this->company->id);

        $this->assertNotNull($found);
        $this->assertSame($confirmed->id, $found->id);
    }

    public function test_link_original_task_returns_null_for_other_statuses(): void
    {
        $this->assertNull($this->service->linkOriginalTask('confirmed', 'REF-003', null, 'X', $this->company->id));
    }

    // ---------------------------------------------------------------------------------------
    // expire() -- hold/confirmed follow-up lifecycle
    // ---------------------------------------------------------------------------------------

    public function test_expire_flips_confirmed_task_past_deadline_for_any_supplier_no_ledger_rows(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Any Supplier At All']);

        $task = Task::factory()->create([
            'company_id' => $this->company->id,
            'supplier_id' => $supplier->id,
            'status' => 'confirmed',
            'deadline_at' => Carbon::now()->subHour(),
        ]);

        $count = $this->service->expire($this->company->id);

        $this->assertSame(1, $count);
        $task->refresh();
        $this->assertSame('expired', $task->status);

        $this->assertDatabaseHas('task_status_events', [
            'task_id' => $task->id,
            'event' => 'expire',
            'from_status' => 'confirmed',
            'to_status' => 'expired',
        ]);

        $this->assertSame(0, JournalEntry::where('task_id', $task->id)->count());
        $this->assertSame(0, Transaction::where('entity_id', $task->company_id)->where('description', 'like', '%'.$task->reference.'%')->count());
    }

    public function test_expire_never_touches_an_issued_task(): void
    {
        $task = Task::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'issued',
            'deadline_at' => Carbon::now()->subHour(),
        ]);

        $this->service->expire($this->company->id);

        $task->refresh();
        $this->assertSame('issued', $task->status);
    }

    public function test_expire_respects_hold_auto_expire_company_option(): void
    {
        Setting::create([
            'company_id' => $this->company->id,
            'key' => 'accounting.hold_auto_expire',
            'type' => 'boolean',
            'value' => false,
        ]);

        $task = Task::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'confirmed',
            'deadline_at' => Carbon::now()->subHour(),
        ]);

        $count = $this->service->expire($this->company->id);

        $this->assertSame(0, $count);
        $task->refresh();
        $this->assertSame('confirmed', $task->status);
    }

    public function test_expire_respects_grace_hours(): void
    {
        Setting::create([
            'company_id' => $this->company->id,
            'key' => 'accounting.hold_expire_grace_hours',
            'type' => 'integer',
            'value' => 24,
        ]);

        // Deadline was 2 hours ago -- inside the 24h grace period, not yet eligible.
        $task = Task::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'confirmed',
            'deadline_at' => Carbon::now()->subHours(2),
        ]);

        $count = $this->service->expire($this->company->id);

        $this->assertSame(0, $count);
        $task->refresh();
        $this->assertSame('confirmed', $task->status);
    }

    public function test_expire_falls_back_to_expiry_date_when_deadline_at_is_null(): void
    {
        $task = Task::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'on hold',
            'deadline_at' => null,
            'expiry_date' => Carbon::now()->subHour(),
        ]);

        $count = $this->service->expire($this->company->id);

        $this->assertSame(1, $count);
        $task->refresh();
        $this->assertSame('expired', $task->status);
    }

    // ---------------------------------------------------------------------------------------
    // cancel() -- hold/confirmed lifecycle's third terminal branch (fix round: previously
    // entirely unbuilt -- w6-brief.md "on hold -> confirmed -> issued | expired | cancelled").
    // ---------------------------------------------------------------------------------------

    public function test_cancel_flips_on_hold_task_to_cancelled_with_no_deposit_zero_ledger_rows(): void
    {
        $task = Task::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'on hold',
        ]);

        $result = $this->service->cancel($task, 'client changed their mind');

        $this->assertSame('cancelled', $result->status);
        $task->refresh();
        $this->assertSame('cancelled', $task->status);

        $this->assertSame(0, JournalEntry::where('task_id', $task->id)->count());
        $this->assertSame(0, Transaction::where('description', 'like', '%'.$task->reference.'%')->count());

        $this->assertDatabaseHas('task_status_events', [
            'task_id' => $task->id,
            'event' => 'cancel',
            'from_status' => 'on hold',
            'to_status' => 'cancelled',
        ]);

        // No deposit -> exactly one audit row (the plain status-flip event), no disposition event.
        $this->assertSame(1, TaskStatusEvent::where('task_id', $task->id)->count());
    }

    public function test_cancel_flips_confirmed_task_to_cancelled(): void
    {
        $task = Task::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'confirmed',
        ]);

        $this->service->cancel($task);

        $task->refresh();
        $this->assertSame('cancelled', $task->status);
    }

    public function test_cancel_rejects_an_issued_task(): void
    {
        $task = Task::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'issued',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->service->cancel($task);
    }

    public function test_cancel_with_deposit_writes_disposition_only_never_posts_ledger_rows(): void
    {
        $task = Task::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'on hold',
        ]);

        \App\Models\InvoiceReceipt::create([
            'type' => 'credit',
            'company_id' => $this->company->id,
            'task_id' => $task->id,
            'amount' => 30,
            'status' => 'approved',
            'transaction_id' => null,
        ]);

        $this->assertEqualsWithDelta(30.0, $this->service->depositHeld($task), 0.001);

        $this->service->cancel($task, 'deadline missed, client asked to cancel');

        $task->refresh();
        $this->assertSame('cancelled', $task->status);

        // Disposition never posts a ledger row itself (w6-brief.md item 1: "No ledger effect for
        // any of on hold/confirmed/expired/cancelled") -- only the audit trail records it.
        $this->assertSame(0, JournalEntry::where('task_id', $task->id)->count());

        $this->assertDatabaseHas('task_status_events', [
            'task_id' => $task->id,
            'event' => 'cancel',
            'to_status' => 'cancelled',
        ]);
        // Default policy is 'credit' -- deposit stays exactly where it already posted.
        $this->assertDatabaseHas('task_status_events', [
            'task_id' => $task->id,
            'event' => 'cancel_disposition_credit_retained',
        ]);
    }

    public function test_cancel_with_deposit_and_refund_out_policy_flags_pending_disposition(): void
    {
        Setting::create([
            'company_id' => $this->company->id,
            'key' => 'accounting.refund.invoice_overpay_cancel_policy',
            'type' => 'string',
            'value' => 'refund_out',
        ]);

        $task = Task::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'confirmed',
        ]);

        \App\Models\InvoiceReceipt::create([
            'type' => 'credit',
            'company_id' => $this->company->id,
            'task_id' => $task->id,
            'amount' => 15,
            'status' => 'approved',
            'transaction_id' => null,
        ]);

        $this->service->cancel($task);

        $this->assertSame(0, JournalEntry::where('task_id', $task->id)->count());
        $this->assertDatabaseHas('task_status_events', [
            'task_id' => $task->id,
            'event' => 'cancel_disposition_refund_out_pending',
        ]);
    }

    public function test_cancel_with_deposit_and_manual_policy_flags_pending_disposition(): void
    {
        Setting::create([
            'company_id' => $this->company->id,
            'key' => 'accounting.refund.invoice_overpay_cancel_policy',
            'type' => 'string',
            'value' => 'manual',
        ]);

        $task = Task::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'on hold',
        ]);

        \App\Models\InvoiceReceipt::create([
            'type' => 'credit',
            'company_id' => $this->company->id,
            'task_id' => $task->id,
            'amount' => 5,
            'status' => 'approved',
            'transaction_id' => null,
        ]);

        $this->service->cancel($task);

        $this->assertSame(0, JournalEntry::where('task_id', $task->id)->count());
        $this->assertDatabaseHas('task_status_events', [
            'task_id' => $task->id,
            'event' => 'cancel_disposition_manual_pending',
        ]);
    }

    public function test_deposit_held_only_counts_approved_receipts_for_this_task(): void
    {
        $task = Task::factory()->create(['company_id' => $this->company->id, 'status' => 'on hold']);
        $otherTask = Task::factory()->create(['company_id' => $this->company->id, 'status' => 'on hold']);

        \App\Models\InvoiceReceipt::create([
            'type' => 'credit', 'company_id' => $this->company->id, 'task_id' => $task->id,
            'amount' => 20, 'status' => 'approved', 'transaction_id' => null,
        ]);
        \App\Models\InvoiceReceipt::create([
            'type' => 'credit', 'company_id' => $this->company->id, 'task_id' => $task->id,
            'amount' => 999, 'status' => 'pending', 'transaction_id' => null,
        ]);
        \App\Models\InvoiceReceipt::create([
            'type' => 'credit', 'company_id' => $this->company->id, 'task_id' => $otherTask->id,
            'amount' => 999, 'status' => 'approved', 'transaction_id' => null,
        ]);

        $this->assertEqualsWithDelta(20.0, $this->service->depositHeld($task), 0.001);
    }

    public function test_expire_skips_a_task_that_already_has_an_invoice_detail(): void
    {
        $task = Task::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'confirmed',
            'deadline_at' => Carbon::now()->subHour(),
        ]);

        $agentType = \App\Models\AgentType::firstOrCreate(['name' => 'w6s-test-type']);
        $branch = \App\Models\Branch::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => User::factory()->create()->id,
        ]);
        $agent = \App\Models\Agent::factory()->create([
            'branch_id' => $branch->id,
            'type_id' => $agentType->id,
            'user_id' => User::factory()->create()->id,
        ]);
        $client = \App\Models\Client::factory()->create(['agent_id' => $agent->id]);
        $invoice = \App\Models\Invoice::factory()->create([
            'client_id' => $client->id,
            'agent_id' => $agent->id,
        ]);
        \App\Models\InvoiceDetail::factory()->create(['task_id' => $task->id, 'invoice_id' => $invoice->id]);

        $count = $this->service->expire($this->company->id);

        $this->assertSame(0, $count);
        $task->refresh();
        $this->assertSame('confirmed', $task->status);
    }
}
