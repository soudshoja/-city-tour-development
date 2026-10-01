<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Services\Accounting\BalanceSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsParityFixtures;
use Tests\TestCase;

/**
 * XBRL-X1 (.planning/phases/xbrl-kuwait/PLAN.md, X1; review H-M2 and H-M4): the balance sheet must
 * foot when one or more PRIOR years were never closed by a year-end close (YEC).
 *
 * ── The defect ──────────────────────────────────────────────────────────────────────────────────
 * The synthetic "profit not yet closed" Equity line used to run from 1 January of the as-of year.
 * A year before that which has no YEC keeps its profit on the Income/Expenses leaves, which the
 * sheet never lists, and outside a calendar-year profit window. That profit was represented
 * nowhere, and the sheet missed by exactly that amount.
 *
 * ── Why the fix is cumulative, and not "the day after the last YEC" ──────────────────────────────
 * City Travelers fixed the same defect by starting the profit window on the day after the NEWEST
 * YEC. That still strands a gap year: FY2024 unclosed, FY2025 closed, so the window starts in 2026
 * and FY2024's profit is lost again ({@see self::test_a_gap_year_before_a_closed_year_is_not_stranded()}).
 * The line is now the cumulative Income/Expenses leaf balance through the as-of date, YEC lines
 * included. A YEC moves a year's profit off those leaves and into Retained Earnings, so what is
 * left on them is exactly the profit that has not been closed yet, in whatever order the years
 * were closed.
 *
 * ── The oracle ──────────────────────────────────────────────────────────────────────────────────
 * Every expected figure is a PINNED LITERAL worked by hand from the fixture below, and is ALSO
 * checked against {@see self::oracle()}: a direct SQL sum over `journal_entries` that applies the
 * engine discriminator itself (`transactions.doc_type IS NOT NULL AND transactions.posting_date IS
 * NOT NULL`, re-written here and not imported from LedgerSource), plus `deleted_at IS NULL`. It
 * never calls the service under test, ProfitLossService, TrialBalanceService or LedgerFigures
 * (which reads engine and legacy rows together; review H-M4). Every fixture carries one legacy
 * cash sale of 60.000 that an unrestricted oracle would count.
 *
 * ── The fixture (engine ON, KWD) ────────────────────────────────────────────────────────────────
 *   2024-01-01  OJV   BANK Dr 1000 / AP Cr 1000                   opening position, no P&L
 *   2024-05-10  INV   AR Dr 130 / SALES Cr 130                    FY2024 profit 130 (gap-year cases only)
 *   2025-03-01  INV   AR Dr 660 / SALES Cr 660
 *   2025-08-01  EXP   COGS Dr 200 / BANK Cr 200
 *   2025-12-31  INV   AR Dr 40 / SALES Cr 40                      activity ON the YEC day
 *                                                                FY2025 profit = 660 + 40 - 200 = 500
 *   2026-02-01  INV   AR Dr 450 / SALES Cr 450
 *   2026-06-01  EXP   COGS Dr 150 / AP Cr 150                     FY2026 profit = 300
 *   2026-03-01  legacy BANK Dr 60 / SALES Cr 60                   doc_type NULL: must be excluded
 */
class BalanceSheetUnclosedYearTest extends TestCase
{
    use BuildsParityFixtures;
    use RefreshDatabase;

    private int $companyId;

    /** @var array<string, int> */
    private array $acc = [];

    private function seedChart(): void
    {
        $this->companyId = $this->makeParityCompany();
        $this->acc = $this->seedParityChart($this->companyId);

        $equityRoot = $this->insertAccount($this->companyId, null, null, 'Q000', 'Equity', 1, true);
        $expensesRoot = (int) DB::table('accounts')->where('company_id', $this->companyId)->where('name', 'Expenses')->value('id');

        $this->acc['RETAINED'] = $this->insertAccount($this->companyId, $equityRoot, $equityRoot, 'Q3400', 'RETAINED EARNINGS', 2, false);
        $this->acc['COGS'] = $this->insertAccount($this->companyId, $expensesRoot, $expensesRoot, 'E5001', 'COST OF SALES', 2, false);
    }

    /** The world every case starts from: opening position, FY2025 and FY2026, plus the legacy noise. */
    private function seedWorld(bool $withFy2024Profit = false): void
    {
        $this->seedChart();
        $a = $this->acc;

        $this->postSyntheticDocument($this->companyId, 'OJV', 'LEGACY_OJV', '2024-01-01', [
            [$a['BANK'], 1000.0, 0.0, null],
            [$a['AP_CONTROL'], 0.0, 1000.0, null],
        ]);

        if ($withFy2024Profit) {
            $this->postSyntheticDocument($this->companyId, 'INV', 'INV', '2024-05-10', [
                [$a['AR_CONTROL'], 130.0, 0.0, null],
                [$a['SALES'], 0.0, 130.0, null],
            ]);
        }

        $this->postSyntheticDocument($this->companyId, 'INV', 'INV', '2025-03-01', [
            [$a['AR_CONTROL'], 660.0, 0.0, null],
            [$a['SALES'], 0.0, 660.0, null],
        ]);
        $this->postSyntheticDocument($this->companyId, 'JV', 'EXP', '2025-08-01', [
            [$a['COGS'], 200.0, 0.0, null],
            [$a['BANK'], 0.0, 200.0, null],
        ]);
        $this->postSyntheticDocument($this->companyId, 'INV', 'INV', '2025-12-31', [
            [$a['AR_CONTROL'], 40.0, 0.0, null],
            [$a['SALES'], 0.0, 40.0, null],
        ]);

        $this->postSyntheticDocument($this->companyId, 'INV', 'INV', '2026-02-01', [
            [$a['AR_CONTROL'], 450.0, 0.0, null],
            [$a['SALES'], 0.0, 450.0, null],
        ]);
        $this->postSyntheticDocument($this->companyId, 'JV', 'EXP', '2026-06-01', [
            [$a['COGS'], 150.0, 0.0, null],
            [$a['AP_CONTROL'], 0.0, 150.0, null],
        ]);

        // A legacy cash sale: the header has neither doc_type nor posting_date, so with the engine
        // ON it belongs to no report. An oracle that forgot the discriminator would be 60.000 off.
        $legacy = $this->postSyntheticDocument($this->companyId, 'INV', 'LEGACY_CASH', '2026-03-01', [
            [$a['BANK'], 60.0, 0.0, null],
            [$a['SALES'], 0.0, 60.0, null],
        ]);
        DB::table('transactions')->where('id', $legacy)->update(['doc_type' => null, 'posting_date' => null]);
        DB::table('journal_entries')->where('transaction_id', $legacy)->update(['posting_date' => null]);
    }

    /** A year-end close sweeping $sales and $cogs into Retained Earnings, dated $date. */
    private function postYec(string $date, float $sales, float $cogs): int
    {
        $lines = [[$this->acc['SALES'], $sales, 0.0, null]];

        if ($cogs > 0) {
            $lines[] = [$this->acc['COGS'], 0.0, $cogs, null];
        }

        $lines[] = [$this->acc['RETAINED'], 0.0, round($sales - $cogs, 3), null];

        return $this->postSyntheticDocument($this->companyId, 'YEC', 'YEC', $date, $lines);
    }

    /**
     * Independent oracle: signed (debit - credit) per root, through $asOf inclusive, engine rows
     * only. Written out here on purpose; see the class docblock.
     *
     * @return array{assets: float, liabilities: float, equity: float, unswept_profit: float}
     */
    private function oracle(string $asOf): array
    {
        $rows = DB::table('journal_entries as je')
            ->join('transactions as t', 't.id', '=', 'je.transaction_id')
            ->join('accounts as a', 'a.id', '=', 'je.account_id')
            ->join('accounts as root', 'root.id', '=', 'a.root_id')
            ->where('je.company_id', $this->companyId)
            ->whereNull('je.deleted_at')
            ->whereNotNull('t.doc_type')
            ->whereNotNull('t.posting_date')
            ->whereRaw('COALESCE(je.posting_date, je.transaction_date) <= ?', [$asOf.' 23:59:59'])
            ->groupBy('root.name')
            ->selectRaw('root.name AS root_name, SUM(je.debit) - SUM(je.credit) AS net')
            ->pluck('net', 'root_name');

        $net = fn (string $root): float => round((float) ($rows[$root] ?? 0), 3);

        return [
            'assets' => $net('Assets'),
            'liabilities' => -$net('Liabilities'),
            'equity' => -$net('Equity'),
            'unswept_profit' => -round($net('Income') + $net('Expenses'), 3),
        ];
    }

    private function sheet(string $asOf): array
    {
        return (new BalanceSheetService)->generate($this->companyId, Carbon::parse($asOf));
    }

    /**
     * Pins the sheet to literals, and the literals to the oracle. The difference is asserted FIRST
     * so that a regression reports the size of the miss, not merely "false is not true".
     */
    private function assertSheet(string $asOf, float $assets, float $liabilities, float $realEquity, float $unswept): void
    {
        $oracle = $this->oracle($asOf);
        $this->assertEqualsWithDelta($assets, $oracle['assets'], 0.0005, 'fixture: oracle assets');
        $this->assertEqualsWithDelta($liabilities, $oracle['liabilities'], 0.0005, 'fixture: oracle liabilities');
        $this->assertEqualsWithDelta($realEquity, $oracle['equity'], 0.0005, 'fixture: oracle equity leaves');
        $this->assertEqualsWithDelta($unswept, $oracle['unswept_profit'], 0.0005, 'fixture: oracle unswept profit');

        $bs = $this->sheet($asOf);

        $this->assertEqualsWithDelta(
            0.0,
            (float) $bs['totals']['difference'],
            0.0005,
            "Balance sheet as of {$asOf} does not foot: difference (assets - liabilities - equity) = "
                .number_format((float) $bs['totals']['difference'], 3).', net_profit line = '
                .number_format((float) $bs['net_profit'], 3).", expected net_profit {$unswept}"
        );
        $this->assertTrue($bs['totals']['is_balanced']);
        $this->assertEqualsWithDelta($unswept, (float) $bs['net_profit'], 0.0005, 'net_profit line');
        $this->assertEqualsWithDelta($assets, (float) $bs['totals']['assets'], 0.0005, 'assets');
        $this->assertEqualsWithDelta($liabilities, (float) $bs['totals']['liabilities'], 0.0005, 'liabilities');
        $this->assertEqualsWithDelta($realEquity + $unswept, (float) $bs['totals']['equity'], 0.0005, 'equity');
    }

    /**
     * The X1 acceptance case. FY2025 has no YEC; FY2026 has activity.
     * Assets: BANK 1000 - 200 = 800, AR 660 + 40 + 450 = 1150 -> 1950. AP 1000 + 150 = 1150.
     * Unswept profit 500 (FY2025) + 300 (FY2026) = 800. 1150 + 800 = 1950.
     * The old 1-January window shows 300 and misses by 500.000, the FY2025 profit.
     */
    public function test_an_unclosed_prior_year_is_carried_in_the_profit_line(): void
    {
        $this->seedWorld();

        $this->assertSheet('2026-12-31', 1950.0, 1150.0, 0.0, 800.0);
    }

    /** FY2024 (130) and FY2025 (500) both unclosed; FY2026 300. Old code misses by 630.000. */
    public function test_two_consecutive_unclosed_years(): void
    {
        $this->seedWorld(withFy2024Profit: true);

        $this->assertSheet('2026-12-31', 2080.0, 1150.0, 0.0, 930.0);
    }

    /**
     * FY2025 closed on 2025-12-31 (sales 700, cost 200, RE 500). The profit line is FY2026 only,
     * i.e. it starts on 2026-01-01; it must NOT pick up the 40.000 sale dated on the YEC day, which
     * that YEC already swept.
     */
    public function test_after_a_year_end_close_the_profit_line_is_the_following_year_only(): void
    {
        $this->seedWorld();
        $this->postYec('2025-12-31', 700.0, 200.0);

        $this->assertSheet('2026-12-31', 1950.0, 1150.0, 500.0, 300.0);
    }

    /**
     * Review H-M2. FY2024 unclosed (130), FY2025 closed, FY2026 300. The window "from the day after
     * the newest YEC" starts on 2026-01-01 and strands FY2024: it misses by 130.000.
     * Assets: BANK 800, AR 1150 + 130 = 1280 -> 2080. AP 1150. RE 500 + unswept 430 = 930.
     */
    public function test_a_gap_year_before_a_closed_year_is_not_stranded(): void
    {
        $this->seedWorld(withFy2024Profit: true);
        $this->postYec('2025-12-31', 700.0, 200.0);

        $this->assertSheet('2026-12-31', 2080.0, 1150.0, 500.0, 430.0);
    }

    /**
     * A YEC dated mid-year (2025-06-30) sweeping the only H1 activity, the 660.000 sale.
     * As of 2025-12-31: BANK 800, AR 700 -> 1500. AP 1000. RE 660, unswept 40 - 200 = -160.
     * A 1-January window counts the swept 660 again and misses by -660.000.
     */
    public function test_a_mid_year_close(): void
    {
        $this->seedWorld();
        $this->postYec('2025-06-30', 660.0, 0.0);

        $this->assertSheet('2025-12-31', 1500.0, 1000.0, 660.0, -160.0);
    }

    /** A chart with no journal lines at all: everything is zero, and it foots. */
    public function test_an_empty_ledger(): void
    {
        $this->seedChart();

        $this->assertSheet('2026-12-31', 0.0, 0.0, 0.0, 0.0);
    }

    /**
     * The FY2025 YEC was posted and then soft-deleted, header and lines together. It no longer
     * counts: FY2025 is unclosed again, so the result is the acceptance case's.
     */
    public function test_a_soft_deleted_year_end_close_does_not_count(): void
    {
        $this->seedWorld();
        $yec = $this->postYec('2025-12-31', 700.0, 200.0);

        DB::table('transactions')->where('id', $yec)->update(['deleted_at' => now()]);
        DB::table('journal_entries')->where('transaction_id', $yec)->update(['deleted_at' => now()]);

        $this->assertSheet('2026-12-31', 1950.0, 1150.0, 0.0, 800.0);
    }

    /**
     * The inconsistent shape: the YEC header is soft-deleted but its lines are not. The lines are
     * still in the ledger (Retained Earnings holds 500), so the sheet must follow the lines and
     * still foot. A window that reads the header's deleted_at instead would count FY2025 twice
     * and miss by -500.000.
     */
    public function test_a_year_end_close_whose_header_alone_is_soft_deleted_still_foots(): void
    {
        $this->seedWorld();
        $yec = $this->postYec('2025-12-31', 700.0, 200.0);

        DB::table('transactions')->where('id', $yec)->update(['deleted_at' => now()]);

        $this->assertSheet('2026-12-31', 1950.0, 1150.0, 500.0, 300.0);
    }
}
