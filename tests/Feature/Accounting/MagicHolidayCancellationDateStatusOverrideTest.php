<?php

namespace Tests\Feature\Accounting;

use App\Http\Controllers\TaskController;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Country;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierStatusMap;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test for a previous-verify FAIL (criterion 11): TaskController::processSingleReservation()
 * used to recompute $status from a raw cancellation-policy date check AFTER
 * TaskStatusService::mapStatus() had already decided the canonical status for the reservation's
 * raw ('OK'/'RQ') supplier code -- unconditionally clobbering mapStatus()'s decision (needs_review,
 * on_hold, or any company-configured supplier_status_maps row) whenever a cancellation-policy date
 * was present. w6-brief.md's own W6.S mandate is that mapStatus() is "the ONLY place any raw status
 * is turned into a canonical tasks.status" -- this pins that the cancellation-policy date parsing
 * (still needed for `cancellation_deadline` storage) no longer re-derives $status at all.
 *
 * The scenario: give this company its OWN supplier_status_maps row overriding Magic Holiday's 'RQ'
 * raw code to 'needs_review' (the global default seed maps RQ -> confirmed, see
 * TaskStatusServiceTest's own parity table) -- a company-configured mapping the pre-fix code would
 * have silently overridden to 'confirmed'/'issued' whenever the reservation carried a
 * cancellationPolicy date, since 'RQ' is one of the two codes ('OK'/'RQ') the buggy block matched.
 */
class MagicHolidayCancellationDateStatusOverrideTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Agent $agent;
    private Supplier $magic;

    protected function setUp(): void
    {
        parent::setUp();

        Company::forgetModuleCache();

        $country = Country::factory()->create();
        $owner = User::factory()->create(['role_id' => Role::COMPANY]);
        $this->company = Company::factory()->create([
            'user_id' => $owner->id,
            'country_id' => $country->id,
        ]);

        $branchUser = User::factory()->create();
        $branch = Branch::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $branchUser->id,
        ]);
        $agentType = AgentType::factory()->create();
        $agentUser = User::factory()->create();
        $this->agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'type_id' => $agentType->id,
            'user_id' => $agentUser->id,
        ]);

        $this->magic = Supplier::factory()->create(['name' => 'Magic Holiday']);

        // Company-configured override: 'RQ' -> needs_review (NOT the global default 'confirmed').
        // Per w6-brief.md W6.S, this row must be the ONLY thing that decides canonical status.
        SupplierStatusMap::create([
            'company_id' => $this->company->id,
            'supplier_id' => $this->magic->id,
            'channel' => 'magic',
            'raw_status' => 'RQ',
            'canonical_status' => 'needs_review',
            'priority' => 0,
            'active' => true,
        ]);
    }

    private function buildReservation(string $rawStatus, string $cancellationDate, string $reference): array
    {
        return [
            'id' => $reference,
            'clientRef' => 'CR-' . $reference,
            'added' => ['time' => now()->subDay()->toDateTimeString()],
            'service' => [
                'status' => $rawStatus,
                'pnr' => 'PNR-' . $reference,
                'passengers' => [
                    ['paxId' => 1, 'type' => 'adult', 'firstName' => 'John', 'lastName' => 'Doe'],
                ],
                'hotel' => ['name' => 'Test Hotel', 'countryId' => 'KW'],
                'serviceDates' => [
                    'duration' => 3,
                    'startDate' => now()->addDays(5)->toDateString(),
                    'endDate' => now()->addDays(8)->toDateString(),
                ],
                'prices' => [
                    'issue' => ['selling' => ['value' => 100.0]],
                    'total' => ['selling' => ['value' => 100.0]],
                ],
                'payment' => ['type' => 'card'],
                'cancellationPolicy' => [
                    'date' => $cancellationDate,
                    'policies' => [
                        ['type' => 'standard', 'charge' => ['value' => 10.0]],
                    ],
                ],
                'rooms' => [
                    [
                        'id' => 'ROOM-1',
                        'number' => 1,
                        'name' => 'Standard Room',
                        'board' => 'BB',
                        'info' => 'refundable',
                        'passengers' => [1],
                    ],
                ],
            ],
        ];
    }

    public function test_cancellation_date_no_longer_overrides_map_status_when_date_has_passed(): void
    {
        // Cancellation date already in the past: the OLD buggy block would set $status = 'issued'
        // here (Date::now() >= cancellationDate), clobbering the company's 'needs_review' mapping.
        $reservation = $this->buildReservation('RQ', now()->subDays(2)->toDateTimeString(), 'MH-PAST-1');

        $result = app(TaskController::class)->processSingleReservation($reservation, $this->agent->id, $this->company->id);

        $this->assertIsArray($result, 'processSingleReservation() returned an error instead of processing the reservation: ' . json_encode($result));
        $this->assertEmpty($result['failed'] ?? [], 'Reservation processing reported a failure: ' . json_encode($result['failed'] ?? []));

        $task = Task::where('reference', 'MH-PAST-1')->where('company_id', $this->company->id)->first();
        $this->assertNotNull($task, 'Expected a Task to have been created for the reservation.');

        $this->assertSame(
            'needs_review',
            $task->status,
            'mapStatus() decided needs_review for this company\'s RQ override, but the cancellation-date '
            . 'block overrode it to "' . $task->status . '" -- this is the exact regression the previous '
            . 'verify pass flagged (criterion 11).'
        );
    }

    public function test_cancellation_date_no_longer_overrides_map_status_when_date_is_future(): void
    {
        // Cancellation date still in the future: the OLD buggy block would set $status = 'confirmed'
        // here, still clobbering the company's 'needs_review' mapping.
        $reservation = $this->buildReservation('RQ', now()->addDays(10)->toDateTimeString(), 'MH-FUTURE-1');

        $result = app(TaskController::class)->processSingleReservation($reservation, $this->agent->id, $this->company->id);

        $this->assertIsArray($result, 'processSingleReservation() returned an error instead of processing the reservation: ' . json_encode($result));
        $this->assertEmpty($result['failed'] ?? [], 'Reservation processing reported a failure: ' . json_encode($result['failed'] ?? []));

        $task = Task::where('reference', 'MH-FUTURE-1')->where('company_id', $this->company->id)->first();
        $this->assertNotNull($task, 'Expected a Task to have been created for the reservation.');

        $this->assertSame(
            'needs_review',
            $task->status,
            'mapStatus() decided needs_review for this company\'s RQ override, but the cancellation-date '
            . 'block overrode it to "' . $task->status . '" -- this is the exact regression the previous '
            . 'verify pass flagged (criterion 11).'
        );
    }

    public function test_cancellation_deadline_is_still_stored_for_reporting(): void
    {
        // The fix must not silently drop cancellation_deadline storage -- only the $status
        // re-derivation was the bug; the deadline itself is still legitimate data.
        $cancellationDate = now()->addDays(10)->toDateTimeString();
        $reservation = $this->buildReservation('RQ', $cancellationDate, 'MH-DEADLINE-1');

        app(TaskController::class)->processSingleReservation($reservation, $this->agent->id, $this->company->id);

        $task = Task::where('reference', 'MH-DEADLINE-1')->where('company_id', $this->company->id)->first();
        $this->assertNotNull($task);
        $this->assertNotNull($task->cancellation_deadline, 'cancellation_deadline should still be populated from the reservation\'s cancellationPolicy date.');
        $this->assertSame(
            Carbon::parse($cancellationDate)->toDateTimeString(),
            Carbon::parse($task->cancellation_deadline)->toDateTimeString()
        );
    }
}
