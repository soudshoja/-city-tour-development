<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Http\Controllers\ReportController;
use App\Models\Role;
use App\Models\User;
use App\Support\ReportDateRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsParityFixtures;
use Tests\TestCase;

/**
 * XBRL-X1: {@see ReportDateRange} (ported from City Travelers' CT-A12) wired into the trial-balance
 * screen's unbalanced-documents panel.
 *
 * The defect: `ReportController::trialBalance()` passed `Carbon::parse($dateTo)` (MIDNIGHT) to
 * `findUnbalancedTransactions()`. For a LEGACY row (`posting_date` NULL), the filter falls back to
 * the `transaction_date` DATETIME, so a document timed after 00:00 on the last day of the range fell
 * outside the panel while the totals beside it (whose `generate()` normalises its own bounds)
 * still counted it. The panel then reported a clean last day that had an unbalanced document on it.
 *
 * The fixture sits on the boundaries, one unbalanced legacy document at each instant:
 *   A  2026-09-01 00:00:00   first day, midnight     -> in the panel
 *   C  2026-09-30 23:59:00   last day, late          -> in the panel (THE DEFECT)
 *   D  2026-10-01 00:00:00   one day past the end    -> NOT in the panel
 *   E  2026-08-15 12:00:00   before the range        -> NOT in the panel
 */
class TrialBalanceUnbalancedPanelRangeTest extends TestCase
{
    use BuildsParityFixtures;
    use RefreshDatabase;

    private int $companyId;

    /** @var array<string, int> */
    private array $txn = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyId = $this->makeParityCompany();
        $acc = $this->seedParityChart($this->companyId);

        // Engine OFF: the panel reads legacy rows, which is the shape the defect bites.
        DB::table('companies')->where('id', $this->companyId)->update(['posting_engine_enabled' => false]);

        foreach (['A' => '2026-09-01 00:00:00', 'C' => '2026-09-30 23:59:00', 'D' => '2026-10-01 00:00:00', 'E' => '2026-08-15 12:00:00'] as $label => $at) {
            $id = $this->postSyntheticDocument($this->companyId, 'INV', 'LEGACY_INV', substr($at, 0, 10), [
                [$acc['AR_CONTROL'], 100.0, 0.0, null],
                [$acc['SALES'], 0.0, 95.0, null],
            ]);
            DB::table('transactions')->where('id', $id)->update([
                'doc_type' => null, 'posting_date' => null, 'transaction_date' => $at,
            ]);
            DB::table('journal_entries')->where('transaction_id', $id)->update([
                'posting_date' => null, 'transaction_date' => $at,
            ]);
            $this->txn[$label] = $id;
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @return list<int> */
    private function panelIds(array $query = ['date_from' => '2026-09-01', 'date_to' => '2026-09-30']): array
    {
        return $this->panel($query)[0];
    }

    /** @return array{0: list<int>, 1: array<string, mixed>} */
    private function panel(array $query): array
    {
        $user = User::factory()->create(['role_id' => Role::ADMIN]);
        $this->actingAs($user);
        session(['company_id' => $this->companyId]);

        $view = app(ReportController::class)->trialBalance(Request::create('/reports/trial-balance', 'GET', $query));
        $data = $view->getData();

        return [
            collect($data['unbalancedTransactions'])->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all(),
            $data,
        ];
    }

    public function test_the_panel_finds_an_unbalanced_document_late_on_the_last_day(): void
    {
        $found = $this->panelIds();

        $this->assertContains($this->txn['C'], $found, 'THE DEFECT: the 23:59 document on the last day is missing from the panel');
        $this->assertContains($this->txn['A'], $found, 'the first-day midnight document must stay in the panel');
        $this->assertNotContains($this->txn['D'], $found, 'the panel must not widen past the end of the range');
        $this->assertCount(2, $found);
    }

    /**
     * Verifier m-4: `?date_from=` present but EMPTY. The resolver used input()'s default, which a
     * present key bypasses, so the panel got a null lower bound and listed ALL-TIME unbalanced
     * documents (E, from August, and D, past the end). An empty bound now falls back to the
     * default, the start of the current month, for the totals and the panel alike.
     */
    public function test_an_empty_date_from_falls_back_to_the_month_start_for_the_panel_too(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-30 12:00:00'));

        [$found, $data] = $this->panel(['date_from' => '', 'date_to' => '2026-09-30']);

        $this->assertNotContains($this->txn['E'], $found, 'an empty date_from made the panel unfiltered: an August document is listed');
        $this->assertNotContains($this->txn['D'], $found, 'an empty date_from made the panel unfiltered: a document past the end is listed');
        $this->assertSame([$this->txn['A'], $this->txn['C']], $found);
        $this->assertSame('2026-09-01', $data['dateFrom'], 'the screen shows the defaulted range it used');
    }

    public function test_report_date_range_bounds(): void
    {
        $this->assertSame('2026-09-30 23:59:59', ReportDateRange::end('2026-09-30')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-30 23:59:59', ReportDateRange::end(Carbon::parse('2026-09-30'))->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-15 12:00:00', ReportDateRange::end('2026-09-15 12:00:00')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-01 00:00:00', ReportDateRange::start('2026-09-01 08:30:00')->format('Y-m-d H:i:s'));
        $this->assertNull(ReportDateRange::end(null));
        $this->assertNull(ReportDateRange::start(''));
    }
}
