<?php

namespace Tests\Feature\Accounting;

use App\Http\Controllers\TaskController;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * W6.I "Importer contract" item 3 (w6-brief.md) -- `TaskController::store()`'s own import_key
 * dedupe short-circuit, inserted BEFORE the existing reference/status/passenger duplicate-
 * detection query (a distinct, earlier check: this one detects the exact same physical ticket
 * being re-imported, e.g. a cron re-processing the same AIR file).
 *
 * Deliberately exercises ONLY the dedupe short-circuit itself (pre-seeding the "already imported"
 * task directly via the factory, never through store()'s own several-hundred-line success path,
 * which needs a fully-wired agent/supplier/financial fixture unrelated to what this test proves)
 * -- see this sub-wave's own build report for why a full HTTP-level store() test is out of
 * proportion to this one behaviour.
 */
class TaskImportKeyDedupeTest extends TestCase
{
    use RefreshDatabase;

    public function test_reimporting_the_same_ticket_returns_the_existing_task_and_creates_nothing_new(): void
    {
        $company = Company::factory()->create();
        $supplier = Supplier::factory()->create();

        $existing = Task::factory()->create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'status' => 'issued',
            'ticket_number' => '5551112223334',
            'airline_reference' => 'KU',
            'issued_date' => '2026-08-29',
            'reference' => 'PNR-ORIGINAL',
            'passenger_name' => 'Jane Passenger',
        ]);

        $this->assertSame('TKT:5551112223334:KU:2026-08-29', $existing->import_key);

        $countBefore = Task::count();

        $request = new Request([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'status' => 'issued',
            // Deliberately a DIFFERENT reference from the existing row -- the ticket-number-based
            // import_key is what must catch this, not the reference-based duplicate check further
            // down in store().
            'reference' => 'PNR-REIMPORTED-DIFFERENT',
            'client_name' => 'Jane Passenger',
            'ticket_number' => '5551112223334',
            'airline_reference' => 'KU',
            'issued_date' => '2026-08-29',
            'exchange_currency' => 'KWD',
        ]);

        $response = app(TaskController::class)->store($request);
        $payload = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('success', $payload['status']);
        $this->assertSame($existing->id, $payload['data']['id']);
        $this->assertSame($countBefore, Task::count(), 'A re-imported ticket must create ZERO new task rows.');
    }

    public function test_a_fare_change_on_the_same_ticket_number_is_not_absorbed_as_a_duplicate(): void
    {
        // Guards against the dedupe check colliding with the Jazeera/FlyDubai same-reference
        // REISSUE heuristic a few lines below it in store() -- a genuine fare/total change must
        // fall through to that logic, not be silently swallowed as "already imported, no-op".
        $company = Company::factory()->create();
        $supplier = Supplier::factory()->create(['name' => 'Jazeera Airways']);

        $existing = Task::factory()->create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'status' => 'issued',
            'ticket_number' => '7778889990001',
            'airline_reference' => 'J9',
            'issued_date' => '2026-08-29',
            'reference' => 'PNR-FARE-CHANGE',
            'passenger_name' => 'Fare Passenger',
            'total' => 200.0,
            'supplier_status' => 'confirmed',
        ]);

        $request = new Request([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'supplier_name' => 'Jazeera Airways',
            'status' => 'issued',
            'supplier_status' => 'confirmed',
            'reference' => 'PNR-FARE-CHANGE',
            'client_name' => 'Fare Passenger',
            'ticket_number' => '7778889990001',
            'airline_reference' => 'J9',
            'issued_date' => '2026-08-29',
            'total' => 250.0, // Genuinely different -- a fare correction, not a re-import.
            'exchange_currency' => 'KWD',
        ]);

        $response = app(TaskController::class)->store($request);
        $payload = json_decode($response->getContent(), true);

        // Must NOT be the dedupe short-circuit's "already imported, no-op" response.
        $this->assertNotSame('This ticket was already imported (idempotent re-import, no-op).', $payload['message'] ?? null);
    }

    /**
     * FIX ROUND (re-verify, CRITICAL): the original dedupe short-circuit compared ONLY `total`,
     * never `status` -- a real-world VOID notification for an already-issued ticket routinely
     * carries the SAME ticket_number/airline_reference/issued_date/total as the original issue
     * (voiding a ticket does not change its fare), so it used to match this guard's "pure
     * re-import" branch and get silently swallowed as a no-op: zero new task, the existing task's
     * status frozen at `issued` forever, no reversal ever posted. `status` must now also be
     * compared -- any status change on the same import_key must fall through past this guard.
     */
    public function test_a_status_change_on_the_same_ticket_and_total_is_not_absorbed_as_a_duplicate(): void
    {
        $company = Company::factory()->create();
        $supplier = Supplier::factory()->create();

        $existing = Task::factory()->create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'status' => 'issued',
            'ticket_number' => '4443332221110',
            'airline_reference' => 'KU',
            'issued_date' => '2026-08-29',
            'reference' => 'PNR-VOID-NOTICE',
            'passenger_name' => 'Void Passenger',
            'total' => 250.0,
        ]);

        $request = new Request([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            // Deliberately the SAME ticket_number/airline_reference/issued_date/total as the
            // existing issued task -- the ordinary real-world shape of a VOID notification for an
            // already-issued ticket. Only `status` differs.
            'status' => 'void',
            'reference' => 'PNR-VOID-NOTICE',
            'client_name' => 'Void Passenger',
            'ticket_number' => '4443332221110',
            'airline_reference' => 'KU',
            'issued_date' => '2026-08-29',
            'total' => 250.0,
            'original_task_id' => $existing->id,
            'exchange_currency' => 'KWD',
        ]);

        $response = app(TaskController::class)->store($request);
        $payload = json_decode($response->getContent(), true);

        // Must NOT be the dedupe short-circuit's "already imported, no-op" response -- a status
        // change must never be swallowed as a pure re-import, regardless of what happens to it
        // further down store()'s own logic (a bare Request fixture with no SupplierCompany/
        // agent/account wiring may still fail LATER in store() for reasons unrelated to this
        // guard -- exactly the same caveat the sibling fare-change test above documents; the one
        // thing under test here is that this EARLY guard no longer absorbs the status change).
        $this->assertNotSame(
            'This ticket was already imported (idempotent re-import, no-op).',
            $payload['message'] ?? null,
            'A VOID notification for the same ticket/total as an already-issued task must fall through past the import_key dedupe guard, not be swallowed as a no-op.'
        );

        $existing->refresh();
        $this->assertSame('issued', $existing->status, 'The dedupe guard itself must never mutate the existing task in place -- it only decides whether to short-circuit.');
    }

    public function test_a_genuinely_different_ticket_is_not_caught_by_the_dedupe_check(): void
    {
        $company = Company::factory()->create();
        $supplier = Supplier::factory()->create();

        Task::factory()->create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'status' => 'issued',
            'ticket_number' => '1112223334445',
            'airline_reference' => 'KU',
            'issued_date' => '2026-08-29',
            'reference' => 'PNR-A',
            'passenger_name' => 'Passenger A',
        ]);

        // Same PNR, DIFFERENT passenger/ticket number -- a legitimate second passenger on the
        // same booking, must never be caught by the ticket-level dedupe check.
        $computed = Task::computeImportKey('9998887776665', 'KU', '2026-08-29', 'PNR-A', 'Passenger B');

        $this->assertNotNull($computed);
        $this->assertSame(0, Task::where('import_key', $computed)->count(), 'A genuinely different ticket must not collide with an existing import_key.');
    }
}
