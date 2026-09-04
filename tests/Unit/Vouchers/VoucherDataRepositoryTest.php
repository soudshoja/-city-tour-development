<?php

namespace Tests\Unit\Vouchers;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Task;
use App\Models\TaskFlightDetail;
use App\Models\TaskHotelDetail;
use App\Models\TaskInsuranceDetail;
use App\Models\TaskPackage;
use App\Models\TaskVisaDetail;
use App\Models\Term;
use App\Models\User;
use App\Models\VoucherTemplate;
use App\Services\Vouchers\Exceptions\VoucherCompanyMismatchException;
use App\Services\Vouchers\VoucherDataRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Per-type null/missing-data coverage for VoucherDataRepository (plan §6:
 * "a template rendering against a fully-null payload is a required test").
 * Every test either proves a field resolves to null/[] instead of a fatal,
 * or proves a company-isolation guard actually fires.
 *
 * Run ONLY as:
 *   DB_TEST_DATABASE=city_tour_test_v_env4 DB_DATABASE_MAP=city_tour_test_v_env4_map \
 *   php artisan test tests/Unit/Vouchers/VoucherDataRepositoryTest.php
 * Never against city_tour_test, laravel_testing, or map_data_citytour.
 */
class VoucherDataRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private VoucherDataRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new VoucherDataRepository;
    }

    // ------------------------------------------------------------------
    // Fixtures
    // ------------------------------------------------------------------

    private function makeCompany(array $overrides = []): Company
    {
        return Company::factory()->create($overrides);
    }

    private function makeTask(Company $company, array $overrides = []): Task
    {
        // Merge fixup: TaskFactory::definition() randomizes 'status' across
        // ['issued','confirmed','refund','void','reissued'] (pre-existing on both
        // merge parents, unmodified here) — flightRoster()/liveSiblings() treat
        // void/refund/superseded as dead, so this suite intermittently failed
        // depending on faker's roll. Pin a deterministically-live default; any
        // test that specifically needs a dead/void status still overrides it.
        return Task::factory()->create(array_merge([
            'company_id' => $company->id,
            'client_id' => null,
            'agent_id' => null,
            'status' => 'confirmed',
        ], $overrides));
    }

    // ------------------------------------------------------------------
    // Flight
    // ------------------------------------------------------------------

    public function test_flight_task_with_detail_returns_legs_and_roster(): void
    {
        $company = $this->makeCompany();
        $task = $this->makeTask($company, ['type' => 'flight', 'gds_reference' => 'ABC123']);

        TaskFlightDetail::factory()->create([
            'task_id' => $task->id,
            'is_ancillary' => false,
            'airport_from' => 'KWI',
            'airport_to' => 'DXB',
            'seat_no' => '12A',
        ]);

        $payload = $this->repo->payloadForTask($task, $company->id);

        $this->assertSame('flight', $payload['resolved_type']);
        $this->assertNull($payload['segment']);
        $this->assertCount(1, $payload['flight']['legs']);
        $this->assertSame('KWI', $payload['flight']['legs'][0]['airport_from']);
        $this->assertCount(1, $payload['flight']['roster']);
        $this->assertSame('12A', $payload['flight']['roster'][0]['seat_no']);
    }

    public function test_flight_task_with_no_detail_row_degrades_to_generic_segment(): void
    {
        // Verified live 2026-08-27: 78 flight tasks exist with zero
        // task_flight_details rows. This must never fatal.
        $company = $this->makeCompany();
        $task = $this->makeTask($company, [
            'type' => 'flight',
            'venue' => 'KWI-DXB',
            'additional_info' => 'Free text fallback',
        ]);

        $payload = $this->repo->payloadForTask($task, $company->id);

        $this->assertSame('generic', $payload['resolved_type']);
        $this->assertNull($payload['flight']);
        $this->assertNotNull($payload['segment']);
        $this->assertSame('KWI-DXB', $payload['segment']['venue']);
    }

    public function test_flight_roster_groups_siblings_by_gds_reference_scoped_to_company(): void
    {
        $company = $this->makeCompany();
        $otherCompany = $this->makeCompany();

        $task = $this->makeTask($company, ['type' => 'flight', 'gds_reference' => 'PNR001', 'passenger_name' => 'MR ONE']);
        $sibling = $this->makeTask($company, ['type' => 'flight', 'gds_reference' => 'PNR001', 'passenger_name' => 'MR TWO']);
        // Same PNR string, but a DIFFERENT company — must never appear in the roster.
        $this->makeTask($otherCompany, ['type' => 'flight', 'gds_reference' => 'PNR001', 'passenger_name' => 'INTRUDER']);

        TaskFlightDetail::factory()->create(['task_id' => $task->id, 'is_ancillary' => false]);
        TaskFlightDetail::factory()->create(['task_id' => $sibling->id, 'is_ancillary' => false]);

        $payload = $this->repo->payloadForTask($task, $company->id);

        $names = array_column($payload['flight']['roster'], 'passenger_name');
        $this->assertCount(2, $names);
        $this->assertContains('MR ONE', $names);
        $this->assertContains('MR TWO', $names);
        $this->assertNotContains('INTRUDER', $names);
    }

    // ------------------------------------------------------------------
    // Hotel
    // ------------------------------------------------------------------

    public function test_hotel_task_with_detail_returns_hotel_block(): void
    {
        $company = $this->makeCompany();
        $task = $this->makeTask($company, ['type' => 'hotel']);

        // Merge fixup: task_hotel_details.hotel_id is NOT NULL and FK-constrained
        // (ON DELETE CASCADE, so an orphan can't occur through normal deletion
        // either) — a literal null always violated the schema on the real MySQL
        // connection this suite runs against. A nonexistent id reaches the exact
        // same "no hotels row" outcome the assertion below checks for
        // (Hotel::find() still returns null) without touching NOT NULL; FK
        // checking is disabled only around this one insert.
        \DB::statement('SET FOREIGN_KEY_CHECKS=0');
        TaskHotelDetail::factory()->create([
            'task_id' => $task->id,
            'hotel_id' => 999999999,
            'check_in' => '2026-09-01',
            'check_out' => '2026-09-04',
            'meal_type' => 'BB',
            'is_refundable' => 1,
        ]);
        \DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $payload = $this->repo->payloadForTask($task, $company->id);

        $this->assertSame('hotel', $payload['resolved_type']);
        $this->assertNull($payload['segment']);
        $this->assertSame(3, $payload['hotel']['nights']);
        $this->assertSame('Bed & Breakfast', $payload['hotel']['meal_type_label']);
        $this->assertTrue($payload['hotel']['is_refundable']);
        $this->assertNull($payload['hotel']['hotel']); // hotel_id null -> no hotels row, must not fatal
    }

    public function test_hotel_task_with_no_detail_row_degrades_to_generic_segment(): void
    {
        // Verified live 2026-08-27: 8 hotel tasks exist with zero task_hotel_details rows.
        $company = $this->makeCompany();
        $task = $this->makeTask($company, ['type' => 'hotel']);

        $payload = $this->repo->payloadForTask($task, $company->id);

        $this->assertSame('generic', $payload['resolved_type']);
        $this->assertNull($payload['hotel']);
        $this->assertNotNull($payload['segment']);
    }

    public function test_hotel_nights_null_when_check_in_or_check_out_missing(): void
    {
        $company = $this->makeCompany();
        $task = $this->makeTask($company, ['type' => 'hotel']);

        TaskHotelDetail::factory()->create([
            'task_id' => $task->id,
            'check_in' => null,
            'check_out' => null,
        ]);

        $payload = $this->repo->payloadForTask($task, $company->id);

        $this->assertNull($payload['hotel']['nights']);
    }

    public function test_hotel_room_details_double_encoded_or_malformed_json_never_fatals(): void
    {
        $company = $this->makeCompany();
        $task = $this->makeTask($company, ['type' => 'hotel']);

        TaskHotelDetail::factory()->create([
            'task_id' => $task->id,
            'room_details' => 'not-json-at-all{{{',
            'room_type' => 'Standard',
        ]);

        $payload = $this->repo->payloadForTask($task, $company->id);

        $this->assertSame('Standard', $payload['hotel']['room_name']);
    }

    // ------------------------------------------------------------------
    // Visa / Insurance
    // ------------------------------------------------------------------

    public function test_visa_task_with_detail_returns_visa_block(): void
    {
        $company = $this->makeCompany();
        $task = $this->makeTask($company, ['type' => 'visa']);

        TaskVisaDetail::create([
            'task_id' => $task->id,
            'visa_type' => 'Tourist',
            'application_number' => 'APP-1',
            'issuing_country' => 'UAE',
        ]);

        $payload = $this->repo->payloadForTask($task, $company->id);

        $this->assertSame('visa', $payload['resolved_type']);
        $this->assertSame('Tourist', $payload['visa']['visa_type']);
        $this->assertNull($payload['segment']);
    }

    public function test_visa_task_with_no_detail_row_degrades_to_generic_segment(): void
    {
        $company = $this->makeCompany();
        $task = $this->makeTask($company, ['type' => 'visa']);

        $payload = $this->repo->payloadForTask($task, $company->id);

        $this->assertSame('generic', $payload['resolved_type']);
        $this->assertNull($payload['visa']);
        $this->assertNotNull($payload['segment']);
    }

    public function test_insurance_task_with_detail_returns_insurance_block(): void
    {
        $company = $this->makeCompany();
        $task = $this->makeTask($company, ['type' => 'insurance']);

        TaskInsuranceDetail::create([
            'task_id' => $task->id,
            'insurance_type' => 'Travel',
            'destination' => 'Schengen',
        ]);

        $payload = $this->repo->payloadForTask($task, $company->id);

        $this->assertSame('insurance', $payload['resolved_type']);
        $this->assertSame('Travel', $payload['insurance']['insurance_type']);
    }

    public function test_insurance_task_with_no_detail_row_degrades_to_generic_segment(): void
    {
        $company = $this->makeCompany();
        $task = $this->makeTask($company, ['type' => 'insurance']);

        $payload = $this->repo->payloadForTask($task, $company->id);

        $this->assertSame('generic', $payload['resolved_type']);
        $this->assertNull($payload['insurance']);
        $this->assertNotNull($payload['segment']);
    }

    // ------------------------------------------------------------------
    // Generic segment (car/rail/tour/esim/event — no detail table at all)
    // ------------------------------------------------------------------

    public function test_typeless_task_types_always_render_generic_segment(): void
    {
        $company = $this->makeCompany();

        foreach (['car', 'rail', 'tour', 'esim', 'event'] as $type) {
            $task = $this->makeTask($company, [
                'type' => $type,
                'venue' => 'Some venue',
                'additional_info' => 'Transfer from A to B',
            ]);

            $payload = $this->repo->payloadForTask($task, $company->id);

            $this->assertSame('generic', $payload['resolved_type'], "type={$type}");
            $this->assertNotNull($payload['segment'], "type={$type}");
            $this->assertSame('Transfer from A to B', $payload['segment']['additional_info'], "type={$type}");
        }
    }

    public function test_null_or_unknown_task_type_never_fatals(): void
    {
        $company = $this->makeCompany();
        // Merge fixup: tasks.type became a fixed 12-value ENUM in migration
        // 2025_08_02_022106_add_enum_in_tasks_table.php (pre-existing on both
        // merge parents, unmodified here) — neither null nor an arbitrary string
        // is insertable any more (NOT NULL + ENUM both reject it on the real
        // MySQL connection this suite runs against). resolveTypeBlocks()'s switch
        // only has cases for flight/hotel/visa/insurance; 'tour' is a real,
        // schema-legal type that still exercises its default/generic branch —
        // the actual "unknown to this switch" case this test's name covers.
        $task = $this->makeTask($company, ['type' => 'tour']);

        $payload = $this->repo->payloadForTask($task, $company->id);

        $this->assertSame('generic', $payload['resolved_type']);
        // 'tour' (see the makeTask() call above): genericSegmentBlock() only
        // falls back to the literal 'Service' label when $task->type is falsy,
        // which the ENUM now makes unreachable via a real row — the schema-legal
        // stand-in title-cases the type itself instead.
        $this->assertSame('Tour', $payload['segment']['type_label']);
    }

    // ------------------------------------------------------------------
    // Fully-null payload (plan §6's required test)
    // ------------------------------------------------------------------

    public function test_fully_null_task_never_fatals_and_renders_honest_nulls(): void
    {
        $company = $this->makeCompany();
        $task = $this->makeTask($company, [
            'type' => 'flight',
            'client_id' => null,
            'agent_id' => null,
            'gds_reference' => null,
            'venue' => null,
            'additional_info' => null,
            'cancellation_policy' => null,
        ]);

        $payload = $this->repo->payloadForTask($task, $company->id);

        $this->assertNull($payload['client']);
        $this->assertNull($payload['agent']);
        $this->assertSame([], $payload['task']['cancellation_policy']);
        $this->assertNull($payload['flight']); // no detail row either
        $this->assertNotNull($payload['segment']);
        $this->assertNull($payload['money']); // off by default, no template
        $this->assertNull($payload['payment']); // off by default, no template
        $this->assertNull($payload['terms']); // no terms rows exist for this company
        $this->assertNull($payload['voucher']['number']); // no voucherMeta passed (preview shape)
    }

    // ------------------------------------------------------------------
    // Client / company isolation
    // ------------------------------------------------------------------

    public function test_task_with_no_client_id_returns_null_client_block(): void
    {
        $company = $this->makeCompany();
        $task = $this->makeTask($company, ['client_id' => null]);

        $payload = $this->repo->payloadForTask($task, $company->id);

        $this->assertNull($payload['client']);
    }

    public function test_client_belonging_to_a_different_company_is_not_leaked(): void
    {
        $company = $this->makeCompany();
        $otherCompany = $this->makeCompany();

        $foreignClient = Client::factory()->create(['company_id' => $otherCompany->id]);
        $task = $this->makeTask($company, ['client_id' => $foreignClient->id]);

        $payload = $this->repo->payloadForTask($task, $company->id);

        $this->assertNull($payload['client']);
    }

    public function test_payload_for_task_throws_on_company_mismatch(): void
    {
        $company = $this->makeCompany();
        $otherCompany = $this->makeCompany();
        $task = $this->makeTask($company);

        $this->expectException(VoucherCompanyMismatchException::class);
        $this->repo->payloadForTask($task, $otherCompany->id);
    }

    public function test_payload_for_task_throws_on_template_company_mismatch(): void
    {
        $company = $this->makeCompany();
        $otherCompany = $this->makeCompany();
        $task = $this->makeTask($company);

        $template = VoucherTemplate::create([
            'company_id' => $otherCompany->id,
            'task_type' => 'flight',
            'name' => 'Other co template',
            'view_key' => 'vouchers.flight-classic',
        ]);

        $this->expectException(VoucherCompanyMismatchException::class);
        $this->repo->payloadForTask($task, $company->id, $template);
    }

    public function test_system_template_with_null_company_id_is_allowed_for_any_company(): void
    {
        $company = $this->makeCompany();
        $task = $this->makeTask($company, ['type' => 'flight']);

        $template = VoucherTemplate::create([
            'company_id' => null, // shipped system template
            'task_type' => 'flight',
            'name' => 'System flight template',
            'view_key' => 'vouchers.flight-classic',
        ]);

        $payload = $this->repo->payloadForTask($task, $company->id, $template);

        $this->assertSame('flight', $payload['task_type']);
    }

    // ------------------------------------------------------------------
    // Money / payment-status toggles
    // ------------------------------------------------------------------

    public function test_money_and_payment_blocks_are_null_without_a_template(): void
    {
        $company = $this->makeCompany();
        $task = $this->makeTask($company);

        $payload = $this->repo->payloadForTask($task, $company->id);

        $this->assertNull($payload['money']);
        $this->assertNull($payload['payment']);
    }

    public function test_money_and_payment_blocks_appear_when_template_toggles_them_on(): void
    {
        $company = $this->makeCompany();
        $task = $this->makeTask($company, ['price' => 100, 'total' => 120, 'exchange_currency' => 'KWD']);
        // Merge fixup: Task has no 'total' cast, so the freshly-created in-memory
        // model still holds the plain PHP int 120 passed above rather than the
        // "120.00" a real read of the decimal(10,2) column returns — refresh()
        // round-trips through the DB the way every real caller (route-model
        // binding, a fresh query) already does.
        $task->refresh();

        $template = VoucherTemplate::create([
            'company_id' => $company->id,
            'task_type' => 'flight',
            'name' => 'Priced template',
            'view_key' => 'vouchers.flight-classic',
            'show_price' => true,
            'show_payment_status' => true,
        ]);

        $payload = $this->repo->payloadForTask($task, $company->id, $template);

        // Merge fixup: tasks.total is decimal(10,3) as of migration
        // 2025_09_21_200554_update_decimal_point_in_tasks_table.php (pre-existing
        // on both merge parents, unmodified here) — a real DB read (see the
        // refresh() above) returns 3 decimal places, not the 2 this test
        // originally assumed.
        $this->assertSame('120.000', (string) $payload['money']['total']);
        $this->assertSame('KWD', $payload['money']['currency']);
        $this->assertSame('not_invoiced', $payload['payment']['state']);
    }

    // ------------------------------------------------------------------
    // Payment state — invoice_details.paid + invoices.status ONLY
    // ------------------------------------------------------------------

    public function test_payment_state_not_invoiced_when_no_invoice_details_row(): void
    {
        $company = $this->makeCompany();
        $task = $this->makeTask($company);

        $state = $this->repo->paymentState($task, $company->id);

        $this->assertSame('not_invoiced', $state['state']);
        $this->assertFalse($state['is_paid']);
    }

    public function test_payment_state_reflects_paid_invoice(): void
    {
        $company = $this->makeCompany();
        $task = $this->makeTask($company);
        $invoice = Invoice::factory()->create(['status' => 'paid', 'paid_date' => '2026-08-01']);
        InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id, 'paid' => 0]);

        $state = $this->repo->paymentState($task, $company->id);

        $this->assertSame('paid', $state['state']);
        $this->assertTrue($state['is_paid']);
        $this->assertSame('2026-08-01', $state['paid_date']);
    }

    public function test_payment_state_reflects_unpaid_invoice(): void
    {
        $company = $this->makeCompany();
        $task = $this->makeTask($company);
        $invoice = Invoice::factory()->create(['status' => 'unpaid']);
        InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id]);

        $state = $this->repo->paymentState($task, $company->id);

        $this->assertSame('unpaid', $state['state']);
        $this->assertFalse($state['is_paid']);
    }

    public function test_payment_state_reflects_partial_invoice(): void
    {
        // Verified live 2026-08-27: on real 'partial' invoices every
        // invoice_details.paid line is 0 — the invoice-level status is
        // the only reliable signal for this state today.
        $company = $this->makeCompany();
        $task = $this->makeTask($company);
        $invoice = Invoice::factory()->create(['status' => 'partial']);
        InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id, 'paid' => 0]);

        $state = $this->repo->paymentState($task, $company->id);

        $this->assertSame('partial', $state['state']);
        $this->assertFalse($state['is_paid']);
    }

    public function test_payment_state_reflects_refunded_invoice(): void
    {
        $company = $this->makeCompany();
        $task = $this->makeTask($company);
        $invoice = Invoice::factory()->create(['status' => 'refunded']);
        InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id]);

        $state = $this->repo->paymentState($task, $company->id);

        $this->assertSame('refunded', $state['state']);
    }

    public function test_payment_state_line_level_paid_flag_overrides_to_paid(): void
    {
        // Defensive: if invoice_details.paid is ever actually set to 1 on
        // a line whose parent invoice is still 'partial', the line wins.
        $company = $this->makeCompany();
        $task = $this->makeTask($company);
        $invoice = Invoice::factory()->create(['status' => 'partial']);
        InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id, 'paid' => 1]);

        $state = $this->repo->paymentState($task, $company->id);

        $this->assertSame('paid', $state['state']);
        $this->assertTrue($state['line_paid']);
    }

    public function test_payment_state_not_invoiced_when_invoice_row_soft_deleted(): void
    {
        $company = $this->makeCompany();
        $task = $this->makeTask($company);
        $invoice = Invoice::factory()->create(['status' => 'paid']);
        InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id]);
        $invoice->delete(); // soft delete

        $state = $this->repo->paymentState($task, $company->id);

        $this->assertSame('not_invoiced', $state['state']);
    }

    public function test_payment_state_ignores_soft_deleted_invoice_detail_row(): void
    {
        $company = $this->makeCompany();
        $task = $this->makeTask($company);
        $invoice = Invoice::factory()->create(['status' => 'paid']);
        $detail = InvoiceDetail::factory()->create(['invoice_id' => $invoice->id, 'task_id' => $task->id]);
        $detail->delete();

        $state = $this->repo->paymentState($task, $company->id);

        $this->assertSame('not_invoiced', $state['state']);
    }

    // ------------------------------------------------------------------
    // Terms
    // ------------------------------------------------------------------

    public function test_terms_block_null_when_no_terms_exist(): void
    {
        $company = $this->makeCompany();
        $task = $this->makeTask($company);

        $payload = $this->repo->payloadForTask($task, $company->id);

        $this->assertNull($payload['terms']);
    }

    public function test_terms_block_falls_back_to_company_default_in_voucher_language(): void
    {
        $company = $this->makeCompany();
        $user = User::factory()->create();
        $task = $this->makeTask($company, ['type' => 'flight']);

        Term::create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'title' => 'English Terms',
            'content' => 'EN content',
            'language' => 'EN',
            'is_default' => true,
            'is_active' => true,
        ]);
        Term::create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'title' => 'Arabic Terms',
            'content' => 'ARB content',
            'language' => 'ARB',
            'is_default' => true,
            'is_active' => true,
        ]);

        $template = VoucherTemplate::create([
            'company_id' => $company->id,
            'task_type' => 'flight',
            'name' => 'Arabic flight template',
            'view_key' => 'vouchers.flight-classic',
            'language' => 'ARB',
        ]);

        $payload = $this->repo->payloadForTask($task, $company->id, $template, ['language' => 'ARB']);

        $this->assertSame('Arabic Terms', $payload['terms']['title']);
    }

    // ------------------------------------------------------------------
    // Package composition
    // ------------------------------------------------------------------

    public function test_payload_for_package_orders_items_by_sort_order(): void
    {
        $company = $this->makeCompany();
        $user = User::factory()->create();

        $taskLater = $this->makeTask($company, ['type' => 'hotel']);
        $taskEarlier = $this->makeTask($company, ['type' => 'flight']);

        $package = TaskPackage::create([
            'company_id' => $company->id,
            'client_id' => null,
            'reference' => 'PKG-TEST-1',
            'name' => 'Test package',
            'package_type' => 'Hotel + Flight',
            'status' => 'open',
            'created_by' => $user->id,
        ]);

        $package->tasks()->attach($taskLater->id, ['sort_order' => 2]);
        $package->tasks()->attach($taskEarlier->id, ['sort_order' => 1, 'section_label' => 'Outbound']);

        $payload = $this->repo->payloadForPackage($package, $company->id);

        $this->assertCount(2, $payload['items']);
        $this->assertSame($taskEarlier->id, $payload['items'][0]['task']['id']);
        $this->assertSame('Outbound', $payload['items'][0]['section_label']);
        $this->assertSame($taskLater->id, $payload['items'][1]['task']['id']);
    }

    public function test_payload_for_package_throws_on_company_mismatch(): void
    {
        $company = $this->makeCompany();
        $otherCompany = $this->makeCompany();
        $user = User::factory()->create();

        $package = TaskPackage::create([
            'company_id' => $company->id,
            'reference' => 'PKG-TEST-2',
            'name' => 'Test package',
            'package_type' => 'Hotel',
            'status' => 'open',
            'created_by' => $user->id,
        ]);

        $this->expectException(VoucherCompanyMismatchException::class);
        $this->repo->payloadForPackage($package, $otherCompany->id);
    }

    public function test_package_payment_state_all_paid(): void
    {
        $company = $this->makeCompany();
        $user = User::factory()->create();

        $taskA = $this->makeTask($company);
        $taskB = $this->makeTask($company);
        $invoiceA = Invoice::factory()->create(['status' => 'paid']);
        $invoiceB = Invoice::factory()->create(['status' => 'paid']);
        InvoiceDetail::factory()->create(['invoice_id' => $invoiceA->id, 'task_id' => $taskA->id]);
        InvoiceDetail::factory()->create(['invoice_id' => $invoiceB->id, 'task_id' => $taskB->id]);

        $package = TaskPackage::create([
            'company_id' => $company->id,
            'reference' => 'PKG-PAID',
            'name' => 'Paid package',
            'package_type' => 'Hotel',
            'status' => 'open',
            'created_by' => $user->id,
        ]);
        $package->tasks()->attach([$taskA->id => ['sort_order' => 1], $taskB->id => ['sort_order' => 2]]);

        $state = $this->repo->packagePaymentState($package, $company->id);

        $this->assertSame('paid', $state['state']);
    }

    public function test_package_payment_state_mixed_is_partial(): void
    {
        $company = $this->makeCompany();
        $user = User::factory()->create();

        $taskA = $this->makeTask($company);
        $taskB = $this->makeTask($company);
        $invoiceA = Invoice::factory()->create(['status' => 'paid']);
        InvoiceDetail::factory()->create(['invoice_id' => $invoiceA->id, 'task_id' => $taskA->id]);
        // taskB never invoiced at all.

        $package = TaskPackage::create([
            'company_id' => $company->id,
            'reference' => 'PKG-MIXED',
            'name' => 'Mixed package',
            'package_type' => 'Hotel',
            'status' => 'open',
            'created_by' => $user->id,
        ]);
        $package->tasks()->attach([$taskA->id => ['sort_order' => 1], $taskB->id => ['sort_order' => 2]]);

        $state = $this->repo->packagePaymentState($package, $company->id);

        $this->assertSame('partial', $state['state']);
    }

    public function test_package_payment_state_none_invoiced_is_unpaid(): void
    {
        $company = $this->makeCompany();
        $user = User::factory()->create();

        $taskA = $this->makeTask($company);

        $package = TaskPackage::create([
            'company_id' => $company->id,
            'reference' => 'PKG-NONE',
            'name' => 'Uninvoiced package',
            'package_type' => 'Hotel',
            'status' => 'open',
            'created_by' => $user->id,
        ]);
        $package->tasks()->attach([$taskA->id => ['sort_order' => 1]]);

        $state = $this->repo->packagePaymentState($package, $company->id);

        $this->assertSame('unpaid', $state['state']);
    }

    public function test_package_with_no_items_never_fatals(): void
    {
        $company = $this->makeCompany();
        $user = User::factory()->create();

        $package = TaskPackage::create([
            'company_id' => $company->id,
            'reference' => 'PKG-EMPTY',
            'name' => 'Empty package',
            'package_type' => 'Hotel',
            'status' => 'open',
            'created_by' => $user->id,
        ]);

        $payload = $this->repo->payloadForPackage($package, $company->id);

        $this->assertSame([], $payload['items']);
        $this->assertSame('', $payload['package']['segment_summary']['summary_line']);
    }

    public function test_package_money_block_flags_mixed_currency(): void
    {
        $company = $this->makeCompany();
        $user = User::factory()->create();

        $taskA = $this->makeTask($company, ['exchange_currency' => 'KWD', 'total' => 100]);
        $taskB = $this->makeTask($company, ['exchange_currency' => 'USD', 'total' => 50]);

        $template = VoucherTemplate::create([
            'company_id' => $company->id,
            'task_type' => 'package',
            'name' => 'Priced package template',
            'view_key' => 'vouchers.package-classic',
            'show_price' => true,
        ]);

        $package = TaskPackage::create([
            'company_id' => $company->id,
            'reference' => 'PKG-CURRENCY',
            'name' => 'Currency package',
            'package_type' => 'Hotel + Flight',
            'status' => 'open',
            'created_by' => $user->id,
        ]);
        $package->tasks()->attach([$taskA->id => ['sort_order' => 1], $taskB->id => ['sort_order' => 2]]);

        $payload = $this->repo->payloadForPackage($package, $company->id, $template);

        $this->assertTrue($payload['money']['mixed_currency']);
        $this->assertSame(150.0, $payload['money']['total']);
    }
}
