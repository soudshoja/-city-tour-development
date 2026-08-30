<?php

namespace Tests\Unit\Models;

use App\Models\Company;
use App\Models\Supplier;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * W6.I "Importer contract" item 3 (w6-brief.md; importer-status-contract.md Table 4).
 * `Task::computeImportKey()` -- pure function, no DB needed for these cases. The two `creating()`
 * hook tests at the bottom DO need a real DB round-trip (RefreshDatabase) since that is precisely
 * what they are proving: the model event wiring itself, not just the pure function.
 */
class TaskImportKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_number_and_airline_and_date_wins_when_all_present(): void
    {
        $key = Task::computeImportKey('1234567890123', 'KU', '2026-08-29', 'PNR-REF', 'John Doe');

        $this->assertSame('TKT:1234567890123:KU:2026-08-29', $key);
    }

    public function test_falls_back_to_reference_passenger_date_when_no_ticket_number(): void
    {
        $key = Task::computeImportKey(null, null, '2026-08-29', 'PNR-REF', 'John Doe');

        $this->assertSame('REF:PNR-REF:John Doe:2026-08-29', $key);
    }

    public function test_falls_back_when_ticket_number_present_but_airline_code_missing(): void
    {
        // Half of the primary shape present -- must not build a partial/misleading key from it,
        // falls through to the reference shape instead.
        $key = Task::computeImportKey('1234567890123', null, '2026-08-29', 'PNR-REF', 'John Doe');

        $this->assertSame('REF:PNR-REF:John Doe:2026-08-29', $key);
    }

    public function test_returns_null_when_no_issue_date_at_all(): void
    {
        $this->assertNull(Task::computeImportKey('1234567890123', 'KU', null, 'PNR-REF', 'John Doe'));
    }

    public function test_returns_null_when_neither_shape_has_enough_data(): void
    {
        $this->assertNull(Task::computeImportKey(null, null, '2026-08-29', null, 'John Doe'));
        $this->assertNull(Task::computeImportKey(null, null, '2026-08-29', 'PNR-REF', null));
    }

    public function test_date_component_is_normalised_to_a_bare_date_ignoring_time(): void
    {
        $key = Task::computeImportKey('1234567890123', 'KU', '2026-08-29 14:35:00', 'PNR-REF', 'John Doe');

        $this->assertSame('TKT:1234567890123:KU:2026-08-29', $key);
    }

    public function test_two_different_passengers_sharing_one_pnr_get_distinct_keys(): void
    {
        $keyA = Task::computeImportKey(null, null, '2026-08-29', 'SHARED-PNR', 'Passenger A');
        $keyB = Task::computeImportKey(null, null, '2026-08-29', 'SHARED-PNR', 'Passenger B');

        $this->assertNotSame($keyA, $keyB, 'Multi-passenger AIR files must never collide onto one import_key.');
    }

    // -------------------------------------------------------------------------------------------
    // End-to-end: the model's own creating() hook actually sets the column.
    // -------------------------------------------------------------------------------------------

    public function test_creating_hook_auto_populates_import_key_on_a_real_saved_task(): void
    {
        $company = Company::factory()->create();
        $supplier = Supplier::factory()->create();

        $task = Task::factory()->create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'ticket_number' => '9998887776665',
            'airline_reference' => 'KU',
            'issued_date' => '2026-08-29',
            'reference' => 'AUTO-REF',
            'passenger_name' => 'Auto Passenger',
        ]);

        $task->refresh();

        $this->assertSame('TKT:9998887776665:KU:2026-08-29', $task->import_key);
    }

    public function test_creating_hook_never_overwrites_an_explicitly_set_import_key(): void
    {
        $company = Company::factory()->create();
        $supplier = Supplier::factory()->create();

        $task = Task::factory()->create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'ticket_number' => '111',
            'airline_reference' => 'KU',
            'issued_date' => '2026-08-29',
            'import_key' => 'MANUALLY-SET-KEY',
        ]);

        $task->refresh();

        $this->assertSame('MANUALLY-SET-KEY', $task->import_key);
    }
}
