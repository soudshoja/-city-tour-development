<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA9;

use App\Console\Commands\RepairTaskFxConversion;
use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * CT-A9 T3 — `accounting:repair-task-fx-conversion`.
 *
 * The fixture reproduces the SEVEN real tasks CT-FX-EXPOSURE-2026-09-16.md §3.2 found, by shape and
 * by number: four carrying their own contemporaneous `tasks.exchange_rate` (repairable exactly) and
 * three EUR ones carrying none (historical rate unrecoverable — refuse and disclose).
 *
 * The property this class exists to defend is the one an adversary will attack: **the three
 * unrecoverable tasks cannot be coaxed into being repaired.** No flag, no rate table, no
 * default and no ordering makes the command convert one on a rate this system does not hold. The
 * only way a rate that is not on the task itself may be used is an owner supplying it per task from
 * an external source, and every such repair is labelled an ESTIMATE in the before-image.
 */
class RepairTaskFxConversionTest extends AccountingTestCase
{
    use GrantsAccountingModule;

    private int $companyId;

    private int $branchId;

    private int $agentId;

    /** USD, its own rate 0.340000, booked at the foreign figure — task 15993's shape. REPAIRABLE. */
    private int $ownRateUsdId;

    /** USD, its own rate 0.305220 — task 9818's shape. REPAIRABLE. */
    private int $ownRateUsdSmallId;

    /** EUR, NO rate, total == original_total — task 12887's shape. REFUSED. */
    private int $noRateEurExactId;

    /** EUR, NO rate, total 6 % above original_total (markup) — task 9917's shape. REFUSED. */
    private int $noRateEurMarkupId;

    /** USD, its own rate, CORRECTLY converted. Must be left completely alone. */
    private int $correctId;

    /** A currency with no `currency_exchanges` row at all. UNTESTABLE. */
    private int $noTableRateId;

    /** A near-parity currency. UNTESTABLE — "looks unconverted" cannot mean anything there. */
    private int $nearParityId;

    /** USD, unconverted shape, carrying a rate the table never held that day — REFUSED. */
    private int $staleRateId;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        $this->companyId = (int) $company->id;
        $this->grantAccountingModule($company);
        CoaSeeder::run($this->companyId);

        $branchOwner = User::factory()->create();
        $this->branchId = (int) Branch::factory()->create([
            'company_id' => $this->companyId, 'user_id' => $branchOwner->id,
        ])->id;

        $agentUser = User::factory()->create();
        $agentType = AgentType::firstOrCreate(['id' => 2], ['name' => 'type-2']);
        $this->agentId = (int) Agent::factory()->create([
            'branch_id' => $this->branchId, 'user_id' => $agentUser->id, 'type_id' => $agentType->id,
        ])->id;

        session(['company_id' => $this->companyId]);
        $this->trackCompanyForInvariants($this->companyId);
        (new SystemAccountsSeeder)->run();

        // `base_currency` holds the FOREIGN code and `exchange_currency` the one it converts INTO —
        // the opposite of what the names suggest, and the reason the lookup is written out in the
        // command rather than inlined.
        $this->tableRate('USD', 0.310000);
        $this->tableRate('EUR', 0.353397);
        $this->tableRate('NPR', 0.950000);   // near parity

        // The real USD timeline. EUR and NPR get none, exactly as on the real data, where every
        // pair but USD and AED has zero recorded changes.
        $this->rateChange('USD', 0.305220, 0.340000, '2025-11-26 06:52:54');
        $this->rateChange('USD', 0.340000, 0.310000, '2026-08-20 17:06:08');

        // Two eras, two rates, each task carrying the rate in force the day it was created.
        $this->ownRateUsdId = $this->task('USD', originalTotal: 368.000, total: 368.000, rate: 0.340000, createdAt: '2026-06-02 10:00:00');
        $this->ownRateUsdSmallId = $this->task('USD', originalTotal: 32.450, total: 32.450, rate: 0.305220, createdAt: '2025-09-18 10:00:00');
        $this->noRateEurExactId = $this->task('EUR', originalTotal: 240.000, total: 240.000, rate: null);
        $this->noRateEurMarkupId = $this->task('EUR', originalTotal: 320.000, total: 339.261, rate: null);
        $this->correctId = $this->task('USD', originalTotal: 100.000, total: 31.000, rate: 0.310000, createdAt: '2026-09-01 10:00:00');
        $this->noTableRateId = $this->task('THB', originalTotal: 1000.000, total: 1000.000, rate: null);
        $this->nearParityId = $this->task('NPR', originalTotal: 500.000, total: 500.000, rate: null);

        // A task carrying a rate the table NEVER held on its creation date — the contemporaneity
        // gate's subject. Same unconverted shape as the repairable ones in every other respect.
        $this->staleRateId = $this->task('USD', originalTotal: 500.000, total: 500.000, rate: 0.400000, createdAt: '2026-06-02 10:00:00');
    }

    // ── fixture helpers ─────────────────────────────────────────────────────────────────────────

    /**
     * A `currency_exchanges` row, with its timestamps under the test's control — CT-A11, 25-1.
     *
     * `updated_at` is load-bearing now: it is how the command sees that the rate table was WRITTEN
     * after a task was created. The default is the real table's own earliest creation date, which
     * is before every task in this fixture, so a row nobody has touched since it was created can
     * never trip the unlogged-write guard — exactly as on the real data, where 16 of the 20 rows
     * still carry `updated_at = created_at`.
     */
    private function tableRate(string $foreign, float $rate, string $touchedAt = '2025-08-04 15:41:58'): void
    {
        DB::table('currency_exchanges')->insert([
            'company_id' => $this->companyId,
            'base_currency' => $foreign,
            'exchange_currency' => 'KWD',
            'exchange_rate' => $rate,
            'is_manual' => 0,
            'created_at' => $touchedAt,
            'updated_at' => $touchedAt,
        ]);
    }

    /**
     * The UNLOGGED write — `CurrencyExchangeController::updateAuto()` — CT-A11, 25-1.
     *
     * It saves the rate and bumps `updated_at`, and writes NO `exchange_rate_histories` row. This
     * is the shape `currency_exchanges` id 1 (BHD) and id 3 (MAD) carry on the real development
     * database: `updated_at != created_at`, zero history rows.
     */
    private function unloggedRateChange(string $foreign, float $newRate, string $changedAt): void
    {
        DB::table('currency_exchanges')
            ->where('company_id', $this->companyId)->where('base_currency', $foreign)
            ->update(['exchange_rate' => $newRate, 'updated_at' => $changedAt]);
    }

    private function task(string $currency, float $originalTotal, float $total, ?float $rate, string $createdAt = '2026-06-02 10:00:00'): int
    {
        $id = (int) Task::factory()->create([
            'company_id' => $this->companyId,
            'agent_id' => $this->agentId,
            'supplier_id' => Supplier::factory()->create()->id,
            'type' => 'hotel',
            'status' => 'issued',
        ])->id;

        // Written raw so no model cast, fillable list or observer can quietly reshape the exact
        // triple under test. `created_at` is load-bearing now: the contemporaneity gate
        // reconstructs the rate in force ON THAT DATE and refuses a task carrying anything else.
        DB::table('tasks')->where('id', $id)->update([
            'price' => $total,
            'total' => $total,
            'original_price' => $originalTotal,
            'original_total' => $originalTotal,
            'original_currency' => $currency,
            'exchange_currency' => 'KWD',
            'exchange_rate' => $rate,
            'created_at' => $createdAt,
        ]);

        return $id;
    }

    /**
     * One recorded rate change, in the shape `exchange_rate_histories` really holds them.
     *
     * The fixture models the REAL USD timeline on the development database, because the
     * contemporaneity gate is only meaningful against a pair whose rate actually moved:
     *
     *     2025-11-26   0.305220 -> 0.340000
     *     2026-08-20   0.340000 -> 0.310000   (0.310000 is today's table rate)
     */
    private function rateChange(string $foreign, float $oldRate, float $newRate, string $changedAt): void
    {
        // `updateManual()` saves the rate row FIRST and then writes the history row, so a LOGGED
        // change always leaves `currency_exchanges.updated_at` at (or just before) the history
        // row's `changed_at` — equal to the second for both real pairs. Modelling that here is what
        // makes the unlogged-write guard's "covered window" case real rather than assumed.
        DB::table('currency_exchanges')
            ->where('company_id', $this->companyId)->where('base_currency', $foreign)
            ->update(['exchange_rate' => $newRate, 'is_manual' => 1, 'updated_at' => $changedAt]);

        DB::table('exchange_rate_histories')->insert([
            'currency_exchange_id' => (int) DB::table('currency_exchanges')
                ->where('company_id', $this->companyId)->where('base_currency', $foreign)->value('id'),
            'base_currency' => $foreign,
            'exchange_currency' => 'KWD',
            'old_rate' => $oldRate,
            'new_rate' => $newRate,
            'method' => 'manual',
            'changed_at' => $changedAt,
            'created_at' => $changedAt,
            'updated_at' => $changedAt,
        ]);
    }

    private function accountByCode(string $code): Account
    {
        return Account::withoutGlobalScopes()
            ->where('company_id', $this->companyId)->where('code', $code)
            ->whereNull('deleted_at')->firstOrFail();
    }

    /** A balanced posted document carrying a task id — the ledger footprint the command must NOT touch. */
    private function postedLinesFor(int $taskId, float $amount): void
    {
        $txn = Transaction::forceCreate([
            'company_id' => $this->companyId, 'branch_id' => $this->branchId,
            'entity_id' => $this->companyId, 'entity_type' => 'company',
            'transaction_type' => 'INV', 'amount' => $amount, 'description' => 'sale',
            'reference_type' => 'Invoice', 'reference_number' => 'A9FX-'.substr(uniqid(), -8),
            'name' => 'sale', 'transaction_date' => now()->subMonths(4),
            'total_debit' => $amount, 'total_credit' => $amount,
        ]);

        foreach ([[$this->accountByCode('1351')->id, $amount, 0.0], [$this->accountByCode('4120')->id, 0.0, $amount]] as [$accountId, $dr, $cr]) {
            JournalEntry::create([
                'transaction_id' => $txn->id, 'company_id' => $this->companyId, 'branch_id' => $this->branchId,
                'account_id' => $accountId, 'transaction_date' => now()->subMonths(4),
                'task_id' => $taskId, 'description' => 'sale', 'debit' => $dr, 'credit' => $cr,
                'name' => 'party', 'type' => $dr > 0 ? 'receivable' : 'income',
                'currency' => 'KWD', 'exchange_rate' => 1.0, 'amount' => $amount, 'voucher_number' => 'A9FX',
            ]);
        }
    }

    /** @param array<string, mixed> $options */
    private function repair(array $options = []): int
    {
        return Artisan::call('accounting:repair-task-fx-conversion', $options + ['--company' => $this->companyId]);
    }

    private function totalOf(int $taskId): string
    {
        return (string) DB::table('tasks')->where('id', $taskId)->value('total');
    }

    private function priceOf(int $taskId): string
    {
        return (string) DB::table('tasks')->where('id', $taskId)->value('price');
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // THE FOUR THAT ARE REPAIRABLE
    // ════════════════════════════════════════════════════════════════════════════════════════════

    public function test_the_default_is_a_dry_run_and_writes_nothing(): void
    {
        $this->assertSame(0, $this->repair());

        $this->assertSame('368.000', $this->totalOf($this->ownRateUsdId));
        $this->assertSame(0, DB::table('coa_linkage_changes')->count());
    }

    /**
     * The arithmetic, on the task's OWN recorded rate. 368.000 × 0.340000 = 125.120 and
     * 32.450 × 0.305220 = 9.904 — both are CT-FX-EXPOSURE §3.2's own published figures for tasks
     * 15993 and 9818, written here as literals rather than recomputed from the same inputs the
     * command uses, so the expectation is the finding and not the implementation.
     */
    public function test_a_task_with_its_own_rate_is_converted_exactly(): void
    {
        $this->assertSame(0, $this->repair(['--apply' => true]));

        $this->assertSame('125.120', $this->totalOf($this->ownRateUsdId));
        $this->assertSame('125.120', $this->priceOf($this->ownRateUsdId));
        $this->assertSame('9.904', $this->totalOf($this->ownRateUsdSmallId));
    }

    public function test_a_correctly_converted_task_is_left_completely_alone(): void
    {
        $this->repair(['--apply' => true]);

        $this->assertSame('31.000', $this->totalOf($this->correctId));
        $this->assertSame(
            0,
            DB::table('coa_linkage_changes')->where('subject_id', $this->correctId)->count(),
            'a task the engine would accept is not a defect and gets no before-image'
        );
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // THE NARROWING (CT-A9 verify) — the signature, and contemporaneity
    // ════════════════════════════════════════════════════════════════════════════════════════════

    /**
     * ── MUTATION PROOF M-A9-SIGNATURE ───────────────────────────────────────────────────────────
     * The first cut of this command flagged any task whose `total` failed to reconcile with
     * `original_total × rate`. Against the real development database that returned **130 tasks
     * instead of 7**, and **109 of the 130 would have RAISED** the booked total — a net
     * **+KWD 6,148.524**, against a finding of KWD 1,628.919 OVERstated. Tasks fail reconciliation
     * for entirely ordinary reasons.
     *
     * This test builds one of those ordinary tasks: a correctly-converted booking that then carries
     * markup, so `total` is 40 % above `original_total × rate` and it fails reconciliation — but its
     * local figure is nothing like its foreign figure, so it is NOT the defect. The widened
     * predicate flags it; the signature predicate does not.
     *
     * The oracle is TWO-SIDED, so that a build which narrowed too far is caught by the same test:
     * the marked-up task must be left alone AND the genuinely unconverted one must still be
     * repaired, in the same run.
     */
    public function test_a_task_that_merely_fails_to_reconcile_is_not_this_defect(): void
    {
        // original_total 100 USD at the era rate 0.340000 implies 34.000; booked 47.600 (40 % markup).
        // Fails `|total - original*rate| <= max(0.01, 1 %)` by a mile; ratio 0.476 is nowhere near 1.
        $markedUp = $this->task('USD', originalTotal: 100.000, total: 47.600, rate: 0.340000, createdAt: '2026-06-02 10:00:00');

        $this->assertSame(0, $this->repair(['--apply' => true]));

        $this->assertSame('47.600', $this->totalOf($markedUp), 'markup is not a defect — the signature gate must exclude it');
        $this->assertSame(
            0,
            DB::table('coa_linkage_changes')->where('subject_id', $markedUp)->count(),
            'and it gets no before-image, because nothing was considered'
        );

        // The control, in the same run: a genuinely unconverted task IS still repaired.
        $this->assertSame('125.120', $this->totalOf($this->ownRateUsdId));
    }

    /**
     * The count the narrowing is calibrated against, expressed as a property rather than a number
     * copied from a report: every task this command would convert has a local figure that IS
     * (near enough) its foreign figure. Nothing outside that signature is ever written.
     */
    public function test_every_task_it_would_convert_carries_the_unconverted_signature(): void
    {
        $this->task('USD', originalTotal: 100.000, total: 47.600, rate: 0.340000, createdAt: '2026-06-02 10:00:00');
        $this->task('USD', originalTotal: 200.000, total: 12.000, rate: 0.340000, createdAt: '2026-06-02 10:00:00');

        $this->repair(['--apply' => true]);

        $written = DB::table('coa_linkage_changes')
            ->where('subject_table', 'tasks')->where('column_name', 'total')->pluck('subject_id');

        $this->assertNotEmpty($written, 'fixture precondition: something was repaired');

        foreach ($written as $taskId) {
            $t = DB::table('tasks')->where('id', $taskId)->first(['original_total']);
            $before = (float) DB::table('coa_linkage_changes')
                ->where('subject_table', 'tasks')->where('column_name', 'total')
                ->where('subject_id', $taskId)->value('before_value');

            $this->assertLessThanOrEqual(
                0.10,
                abs($before / (float) $t->original_total - 1.0),
                "task #{$taskId} was converted without carrying the unconverted signature"
            );
        }
    }

    /**
     * ── MUTATION PROOF M-A9-CONTEMPORANEITY ─────────────────────────────────────────────────────
     * The command converts on the task's own stored rate, and the whole justification for that is
     * that the rate was captured when the task was created. A dry run appeared to destroy the
     * premise — 354 of 490 in-scope tasks carry TODAY's table rate to six decimals — until the USD
     * history showed why: every pair but USD and AED has never had its rate changed, so for them a
     * contemporaneous capture and today's rate are necessarily the same number. On USD, the one
     * pair that moved twice, **211 of 211 tasks carry exactly the rate in force on their own
     * creation date**.
     *
     * So the premise holds — and it is now CHECKED rather than asserted. This test gives a task a
     * rate the table never held on the day it was created (0.400000, when 0.340000 was in force) and
     * requires a REFUSAL.
     *
     * The CONTROL is the point: `ownRateUsdSmallId` carries 0.305220, which is ALSO not today's
     * 0.310000 — a naive "refuse anything that differs from today's rate" would refuse it too, and
     * would have refused 354 perfectly good tasks. It must still be repaired, in the same run.
     */
    public function test_a_rate_the_table_never_held_that_day_is_refused(): void
    {
        $this->artisan('accounting:repair-task-fx-conversion', ['--company' => $this->companyId, '--apply' => true])
            ->expectsOutputToContain('REFUSED task #'.$this->staleRateId.' (USD): its stored rate is NOT contemporaneous.')
            ->expectsOutputToContain('stored rate 0.400000, but the rate in force that day was 0.340000')
            ->assertExitCode(0);

        $this->assertSame('500.000', $this->totalOf($this->staleRateId), 'nothing written for a rate we cannot vouch for');

        // THE CONTROL — a rate that differs from TODAY's but WAS in force on the day is accepted.
        $this->assertSame('9.904', $this->totalOf($this->ownRateUsdSmallId), 'an older era rate is still contemporaneous evidence');
        $this->assertSame('125.120', $this->totalOf($this->ownRateUsdId));
    }

    /**
     * The other half of the same control, stated directly: a pair with NO recorded change has one
     * rate for its whole life, so today's rate IS the rate in force on any date and equality proves
     * nothing either way. Such a task must not be refused for the equality alone.
     */
    public function test_equality_with_todays_rate_is_not_by_itself_a_refusal(): void
    {
        // EUR has no history row in this fixture, exactly as on the real data. Give a EUR task the
        // table rate and the unconverted shape; it is repairable, not refused.
        $eur = $this->task('EUR', originalTotal: 240.000, total: 240.000, rate: 0.353397, createdAt: '2026-01-12 10:00:00');

        $this->repair(['--apply' => true]);

        // 240.000 x 0.353397 = 84.815
        $this->assertSame('84.815', $this->totalOf($eur), 'a static pair\'s rate is contemporaneous by construction');
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // CT-A11 FINDING 25-1 — `exchange_rate_histories` IS A MANUAL-ONLY LOG
    // ════════════════════════════════════════════════════════════════════════════════════════════

    /**
     * ── MUTATION PROOF M-A11-UNLOGGED ───────────────────────────────────────────────────────────
     * `CurrencyExchangeController::updateManual()` writes the rate row AND a history row.
     * `updateAuto()`, a few lines above it, writes the rate and NO history row. Both are live PUT
     * routes on the currency-exchange screen. The fingerprint is in the data: `currency_exchanges`
     * id 1 (BHD) and id 3 (MAD) both carry `updated_at != created_at` with ZERO history rows.
     *
     * The failure this proves is the SILENT one, and it is the reason the guard refuses rather than
     * returning null. BHD here carries TODAY's table rate on a task created BEFORE an unlogged
     * change. `rateInForceAt()` walks today's rate backwards through the logged changes, finds
     * none, and hands back today's rate — which MATCHES the task's stored rate. Without the guard
     * the task is therefore accepted as contemporaneous on no evidence whatsoever and CONVERTED:
     * 400.000 -> 324.702. With the guard it is refused by name and nothing is written.
     *
     * BHD is the right currency for this, and not only because it is one of the two real offenders:
     * at 0.811755 it is the one pair where an ordinary markup (+10.9 %) reaches the unconverted
     * signature at all — see RepairTaskFxConversion::UNCONVERTED_RATIO_BAND.
     *
     * TWO CONTROLS RUN IN THE SAME INVOCATION, because a build that simply refused everything would
     * otherwise pass:
     *   (a) `ownRateUsdId` — USD's write IS covered by a logged change, and must still be repaired;
     *   (b) a second BHD task created AFTER the unlogged write — its window is not crossed at all,
     *       so it must be repaired even though the very same pair refused (a).
     */
    public function test_an_unlogged_rate_table_write_after_the_task_is_refused_by_name(): void
    {
        // The table held 0.750000 when the subject task was created. Nothing records that: the
        // move to 0.811755 was made through the automatic path and left no history row.
        $this->tableRate('BHD', 0.750000);

        // The subject: created 2025-09-01, carrying a rate that is TODAY's but was NOT in force
        // that day. That is the shape the contemporaneity gate exists to catch, and the one it
        // cannot see through an unlogged change.
        $before = $this->task('BHD', originalTotal: 400.000, total: 400.000, rate: 0.811755, createdAt: '2025-09-01 10:00:00');

        // CONTROL (b): same pair, created AFTER the unlogged write — nothing crosses its window.
        $after = $this->task('BHD', originalTotal: 200.000, total: 200.000, rate: 0.811755, createdAt: '2025-11-20 10:00:00');

        // `updateAuto()`: the rate row is written, no history row is written. This is the real
        // id-1 shape — updated_at moves to 2025-10-08, exchange_rate_histories stays empty for BHD.
        $this->unloggedRateChange('BHD', 0.811755, '2025-10-08 09:53:43');

        // The VALUE is asserted first and on its own, so that under the mutation the failure names
        // the number a blind reconstruction would have written (400.000 x 0.811755 = 324.702)
        // rather than only reporting a missing line of output.
        $this->assertSame(0, $this->repair(['--apply' => true]));

        $this->assertSame('400.000', $this->totalOf($before), 'an unlogged change means the reconstruction is blind — nothing may be written');
        $this->assertSame(
            0,
            DB::table('coa_linkage_changes')->where('subject_id', $before)->count(),
            'and no before-image, because nothing was converted'
        );

        // CONTROL (b): 200.000 x 0.811755 = 162.351.
        $this->assertSame('162.351', $this->totalOf($after), 'a task created after the unlogged write has an uncrossed window');

        // CONTROL (a): USD's write at 2026-08-20 17:06:08 IS covered by the logged change of the
        // same instant, so the covered case must still convert.
        $this->assertSame('125.120', $this->totalOf($this->ownRateUsdId), 'a logged change still covers its own window');

        // And the refusal says WHY, by name, in the report.
        $this->artisan('accounting:repair-task-fx-conversion', ['--company' => $this->companyId])
            ->expectsOutputToContain('REFUSED task #'.$before.' (BHD): the rate table was written AFTER this task and the write was NOT logged.')
            ->expectsOutputToContain('currency_exchanges row was last written 2025-10-08 09:53:43')
            ->expectsOutputToContain('newest exchange_rate_histories row for that pair: NONE.')
            ->assertExitCode(0);
    }

    /**
     * The other direction of the same defect, kept separate so the report is readable: a task
     * carrying the OLD rate after an unlogged change was already being refused, but for the WRONG
     * REASON — "not contemporaneous", which blames the task. It is the log that is incomplete, and
     * the message now says so.
     */
    public function test_an_unlogged_change_is_named_as_such_rather_than_blamed_on_the_task(): void
    {
        $this->tableRate('BHD', 0.750000);
        $stale = $this->task('BHD', originalTotal: 400.000, total: 400.000, rate: 0.750000, createdAt: '2025-09-01 10:00:00');
        $this->unloggedRateChange('BHD', 0.850000, '2025-10-08 09:53:43');

        $this->artisan('accounting:repair-task-fx-conversion', ['--company' => $this->companyId])
            ->expectsOutputToContain('REFUSED task #'.$stale.' (BHD): the rate table was written AFTER this task and the write was NOT logged.')
            ->assertExitCode(0);

        $this->assertSame('400.000', $this->totalOf($stale));
    }

    /**
     * ── MUTATION PROOF M-A11-HISTORY-COMPANY (finding 25-2) ─────────────────────────────────────
     * `rateChanges()` took a `$companyId`, cached by it, and filtered on the currency codes ALONE.
     * `exchange_rate_histories` has no `company_id`; the link is
     * `currency_exchange_id -> currency_exchanges.company_id`, and the join was not made.
     *
     * Here a SECOND company records a USD change on 2026-07-01 away from 0.900000. The date is
     * chosen deliberately: the walk runs newest-first and each applicable change overwrites the
     * last, so what decides the answer is the OLDEST change later than the task — and 2026-07-01
     * sits between `ownRateUsdId`'s creation (2026-06-02) and company 1's own next change
     * (2026-08-20). An unscoped read therefore concludes the rate in force on 2026-06-02 was
     * 0.900000 and REFUSES task 15993's shape as non-contemporaneous. Scoped, the other company's
     * history is invisible and the task converts.
     *
     * The CONTROL is that company 1's own two USD changes are still read: `ownRateUsdSmallId`
     * carries 0.305220, which is only correct if the 2025-11-26 change is walked back. A build that
     * "fixed" this by reading no history at all fails on that line.
     */
    public function test_another_companys_recorded_rate_change_does_not_move_this_companys_reconstruction(): void
    {
        $otherCompanyId = (int) Company::factory()->create()->id;

        DB::table('currency_exchanges')->insert([
            'company_id' => $otherCompanyId, 'base_currency' => 'USD', 'exchange_currency' => 'KWD',
            'exchange_rate' => 0.310000, 'is_manual' => 1,
            'created_at' => '2025-08-04 15:42:11', 'updated_at' => '2026-07-01 12:00:00',
        ]);

        DB::table('exchange_rate_histories')->insert([
            'currency_exchange_id' => (int) DB::table('currency_exchanges')
                ->where('company_id', $otherCompanyId)->where('base_currency', 'USD')->value('id'),
            'base_currency' => 'USD', 'exchange_currency' => 'KWD',
            'old_rate' => 0.900000, 'new_rate' => 0.310000, 'method' => 'manual',
            'changed_at' => '2026-07-01 12:00:00',
            'created_at' => '2026-07-01 12:00:00', 'updated_at' => '2026-07-01 12:00:00',
        ]);

        $this->repair(['--apply' => true]);

        $this->assertSame('125.120', $this->totalOf($this->ownRateUsdId), "another company's rate history is not evidence about this one");

        // CONTROL — this company's OWN history is still read and still walked back.
        $this->assertSame('9.904', $this->totalOf($this->ownRateUsdSmallId), 'the 2025-11-26 change must still be undone for an older task');
    }

    /**
     * ── MUTATION PROOF M-A11-CHRONOLOGY (finding 25-3) ──────────────────────────────────────────
     * The contemporaneity walk compared timestamps with `strcmp`. Lexicographic order equals
     * chronological order only while BOTH sides are exactly `Y-m-d H:i:s` — and the failure mode is
     * the bad kind: a `T` separator, fractional seconds or a timezone suffix from a driver option
     * or a column-type change reorders the walk silently, with no exception and no signal. The gate
     * keeps running and keeps returning a number.
     *
     * This drives `rateInForceAt()` directly, because the misordering enters through the STRING and
     * a MySQL `datetime` column can never hand one back.
     *
     * The case has to be a SAME-DAY one or the bug hides: on two different days the strings differ
     * inside `Y-m-d` and both implementations agree by luck. Here the task is created on the
     * MORNING of 2026-08-20 and the recorded change is at 17:06:08 the same afternoon — so the
     * change is chronologically after the task, must be undone, and the rate in force that morning
     * was **0.340000**.
     *
     * Under `strcmp` the comparison reaches offset 10, where the change's space (0x20) sorts BELOW
     * the ISO `T` (0x54). The change therefore reads as "not after the task", is not undone, and
     * the method hands back today's **0.310000** — a wrong rate, with no exception and no warning.
     */
    public function test_the_contemporaneity_walk_orders_by_instant_not_by_string(): void
    {
        $method = new \ReflectionMethod(RepairTaskFxConversion::class, 'rateInForceAt');
        $method->setAccessible(true);

        $inForce = $method->invoke(
            new RepairTaskFxConversion,
            $this->companyId,
            'USD',
            '2026-08-20T10:00:00.000000Z',   // hours before the 17:06:08 change, in a shape strcmp misreads
            0.310000
        );

        $this->assertSame(0.340000, $inForce, 'an ISO-8601 created_at must still order BEFORE a same-day change made later that afternoon');

        // CONTROL — the plain `Y-m-d H:i:s` shape both implementations agree on still answers the
        // same, so this test cannot pass by the method having stopped working altogether.
        $this->assertSame(
            0.340000,
            $method->invoke(new RepairTaskFxConversion, $this->companyId, 'USD', '2026-08-20 10:00:00', 0.310000)
        );

        // And the era either side, so the walk is proved to MOVE rather than to return a constant.
        $this->assertSame(
            0.305220,
            $method->invoke(new RepairTaskFxConversion, $this->companyId, 'USD', '2025-09-18 15:34:22', 0.310000)
        );
        $this->assertSame(
            0.310000,
            $method->invoke(new RepairTaskFxConversion, $this->companyId, 'USD', '2026-09-01 10:00:00', 0.310000)
        );
    }

    /**
     * The guard must not fire on a pair nobody has touched since the row was created — which is 16
     * of the 20 real `currency_exchanges` rows, and every currency in this fixture but USD. Stated
     * as its own test so that a build which refused on `updated_at` alone, without asking whether a
     * history row covers it, is caught.
     */
    public function test_a_table_row_never_written_since_creation_never_trips_the_guard(): void
    {
        $eur = $this->task('EUR', originalTotal: 240.000, total: 240.000, rate: 0.353397, createdAt: '2026-01-12 10:00:00');

        $this->repair(['--apply' => true]);

        $this->assertSame('84.815', $this->totalOf($eur), 'EUR has no history row AND no write since creation — there is nothing to refuse');
    }

    /** Every untestable task now says WHY — the silent counter was a reporting gap. */
    public function test_every_untestable_task_reports_a_reason(): void
    {
        $this->artisan('accounting:repair-task-fx-conversion', ['--company' => $this->companyId])
            ->expectsOutputToContain('UNTESTABLE task #'.$this->noTableRateId)
            ->expectsOutputToContain('there is no THB row in')
            ->expectsOutputToContain('UNTESTABLE task #'.$this->nearParityId)
            ->expectsOutputToContain('too near parity')
            ->assertExitCode(0);
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // THE THREE THAT ARE NOT — AND CANNOT BE COAXED
    // ════════════════════════════════════════════════════════════════════════════════════════════

    public function test_a_task_with_no_rate_is_refused_and_reported_for_disclosure(): void
    {
        $this->artisan('accounting:repair-task-fx-conversion', ['--company' => $this->companyId, '--apply' => true])
            ->expectsOutputToContain('REFUSED task #'.$this->noRateEurExactId)
            ->expectsOutputToContain('UNRECOVERABLE')
            ->expectsOutputToContain('Owner disclosure required.')
            ->assertExitCode(0);

        $this->assertSame('240.000', $this->totalOf($this->noRateEurExactId));
        $this->assertSame('339.261', $this->totalOf($this->noRateEurMarkupId));
    }

    /**
     * ── MUTATION PROOF M-A9-FX-NO-TABLE-RATE ────────────────────────────────────────────────────
     * The single most tempting wrong implementation is "fall back to `currency_exchanges` when the
     * task has no rate of its own". It looks helpful, it repairs all seven instead of four, and it
     * is exactly what CT-FX-EXPOSURE §8.2 says must not happen: *"The only options are today's
     * table rate (an approximation of unknown error) or an external historical source the owner
     * supplies."*
     *
     * The proof moves the table rate to an ABSURD value and asserts nothing changes. A build that
     * converted on the table rate writes 240.000 × 0.700000 = 168.000 here and fails on the very
     * first assertion; a build that used the REAL table rate would write 84.815 and fail it too.
     * The detection must still fire, so the second half asserts the task is still REPORTED — an
     * implementation that "passed" by simply not looking at these tasks at all fails that.
     */
    public function test_the_table_rate_never_drives_a_correction_however_absurd_it_is(): void
    {
        DB::table('currency_exchanges')
            ->where('company_id', $this->companyId)->where('base_currency', 'EUR')
            ->update(['exchange_rate' => 0.700000]);

        $this->repair(['--apply' => true]);

        $this->assertSame('240.000', $this->totalOf($this->noRateEurExactId), 'no table rate, however plausible, converts a task');
        $this->assertSame('339.261', $this->totalOf($this->noRateEurMarkupId));

        $this->artisan('accounting:repair-task-fx-conversion', ['--company' => $this->companyId])
            ->expectsOutputToContain('REFUSED task #'.$this->noRateEurExactId)
            ->assertExitCode(0);
    }

    /**
     * The other half of the same proof: the table rate IS allowed to DETECT. With no EUR row at all
     * the command cannot tell whether the figure is wrong, and says so — it does not fall back to
     * calling it defective, and it does not fall back to calling it fine.
     */
    public function test_a_currency_with_no_table_rate_is_untestable_not_refused(): void
    {
        $this->artisan('accounting:repair-task-fx-conversion', ['--company' => $this->companyId])
            ->expectsOutputToContain('UNTESTABLE task #'.$this->noTableRateId)
            ->expectsOutputToContain('there is no THB row in')
            ->assertExitCode(0);

        $this->assertSame('1000.000', $this->totalOf($this->noTableRateId));
    }

    /** A near-parity currency cannot support the detection at all, and the command says which. */
    public function test_a_near_parity_currency_is_untestable(): void
    {
        $this->artisan('accounting:repair-task-fx-conversion', ['--company' => $this->companyId])
            ->expectsOutputToContain('UNTESTABLE task #'.$this->nearParityId)
            ->expectsOutputToContain('too near parity')
            ->assertExitCode(0);
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // THE OPERATOR-SUPPLIED RATE — the only door, and it is locked in four ways
    // ════════════════════════════════════════════════════════════════════════════════════════════

    public function test_a_supplied_rate_requires_apply(): void
    {
        $this->assertSame(1, $this->repair(['--historical-rate' => [$this->noRateEurExactId.':0.312500']]));
        $this->assertSame('240.000', $this->totalOf($this->noRateEurExactId));
    }

    public function test_a_malformed_supplied_rate_is_refused_rather_than_skipped(): void
    {
        $this->assertSame(1, $this->repair(['--historical-rate' => ['not-a-pair'], '--apply' => true]));
        $this->assertSame(1, $this->repair(['--historical-rate' => [$this->noRateEurExactId.':0'], '--apply' => true]));
        $this->assertSame('240.000', $this->totalOf($this->noRateEurExactId));
    }

    /**
     * A supplied rate may only stand in for a rate that does not exist. Overriding CONTEMPORANEOUS
     * evidence with an assertion made today is precisely the coaxing this command must refuse — and
     * the task is still repaired, on its own rate, so the refusal is of the override and not of the
     * repair.
     */
    public function test_a_supplied_rate_is_refused_for_a_task_that_has_its_own(): void
    {
        $this->artisan('accounting:repair-task-fx-conversion', [
            '--company' => $this->companyId,
            '--apply' => true,
            '--historical-rate' => [$this->ownRateUsdId.':0.900000'],
        ])
            ->expectsOutputToContain('REFUSED --historical-rate for task #'.$this->ownRateUsdId)
            ->expectsOutputToContain('it already carries its own recorded rate 0.340000')
            ->assertExitCode(0);

        $this->assertSame(
            '368.000',
            $this->totalOf($this->ownRateUsdId),
            'the whole task is skipped when its supplied rate is refused — it is not half-repaired'
        );
    }

    /** Used properly, it converts — and says loudly that the result is an estimate. */
    public function test_a_supplied_rate_converts_and_is_labelled_an_estimate(): void
    {
        $this->artisan('accounting:repair-task-fx-conversion', [
            '--company' => $this->companyId,
            '--apply' => true,
            '--historical-rate' => [$this->noRateEurExactId.':0.312500'],
        ])
            ->expectsOutputToContain('ESTIMATE task #'.$this->noRateEurExactId)
            ->expectsOutputToContain('OPERATOR-SUPPLIED rate 0.312500')
            ->expectsOutputToContain('DISCLOSURE')
            ->assertExitCode(0);

        // 240.000 x 0.312500 = 75.000
        $this->assertSame('75.000', $this->totalOf($this->noRateEurExactId));

        $sentinel = DB::table('coa_linkage_changes')
            ->where('subject_table', 'tasks')
            ->where('subject_id', $this->noRateEurExactId)
            ->where('column_name', RepairTaskFxConversion::OPERATOR_SUPPLIED_RATE)
            ->first();

        $this->assertNotNull($sentinel, 'the estimate must be labelled IN the before-image, not only on screen');
        $this->assertSame('0.312500', (string) $sentinel->after_value);

        // The one NOT supplied is still refused in the same run — a supplied rate is per task.
        $this->assertSame('339.261', $this->totalOf($this->noRateEurMarkupId));
    }

    public function test_a_supplied_rate_for_a_task_that_is_not_defective_is_reported_as_unused(): void
    {
        $this->artisan('accounting:repair-task-fx-conversion', [
            '--company' => $this->companyId,
            '--apply' => true,
            '--historical-rate' => [$this->correctId.':0.500000'],
        ])
            ->expectsOutputToContain('were NOT used')
            ->assertExitCode(0);

        $this->assertSame('31.000', $this->totalOf($this->correctId));
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // THE LEDGER IS REPORTED, NEVER REWRITTEN
    // ════════════════════════════════════════════════════════════════════════════════════════════

    /**
     * A posted ledger is corrected by a correcting DOCUMENT, never by an UPDATE. The assertion is a
     * byte-for-byte comparison of every journal row before and after, so any write at all — even
     * one that happened to produce the same totals — fails it.
     */
    public function test_journal_entries_are_reported_and_never_touched(): void
    {
        $this->postedLinesFor($this->ownRateUsdId, 368.000);
        $this->postedLinesFor($this->noRateEurExactId, 240.000);

        $before = DB::table('journal_entries')->where('company_id', $this->companyId)->orderBy('id')->get()->toJson();

        $this->artisan('accounting:repair-task-fx-conversion', ['--company' => $this->companyId, '--apply' => true])
            ->expectsOutputToContain('LEDGER FOOTPRINT')
            ->expectsOutputToContain('NOT touched by this command')
            ->expectsOutputToContain('raising a correcting document through the posting engine')
            ->assertExitCode(0);

        $after = DB::table('journal_entries')->where('company_id', $this->companyId)->orderBy('id')->get()->toJson();

        $this->assertSame($before, $after, 'this command must never write journal_entries, in any mode');
    }

    // ════════════════════════════════════════════════════════════════════════════════════════════
    // BEFORE-IMAGES, ROLLBACK, OWNERSHIP, PAGING
    // ════════════════════════════════════════════════════════════════════════════════════════════

    public function test_rollback_restores_every_task_the_run_converted(): void
    {
        $this->repair(['--apply' => true]);
        $runId = (string) DB::table('coa_linkage_changes')->value('run_id');

        $this->assertSame(0, Artisan::call('accounting:repair-task-fx-conversion', ['--rollback' => $runId]));

        $this->assertSame('368.000', $this->totalOf($this->ownRateUsdId));
        $this->assertSame('368.000', $this->priceOf($this->ownRateUsdId));
        $this->assertSame('32.450', $this->totalOf($this->ownRateUsdSmallId));
    }

    public function test_rollback_of_an_estimate_says_the_estimate_is_withdrawn(): void
    {
        $this->repair(['--apply' => true, '--historical-rate' => [$this->noRateEurExactId.':0.312500']]);
        $runId = (string) DB::table('coa_linkage_changes')->value('run_id');

        $this->artisan('accounting:repair-task-fx-conversion', ['--rollback' => $runId])
            ->expectsOutputToContain('estimate withdrawn')
            ->assertExitCode(0);

        $this->assertSame('240.000', $this->totalOf($this->noRateEurExactId));
    }

    public function test_rollback_refuses_a_task_that_has_moved_since_the_run(): void
    {
        $this->repair(['--apply' => true]);
        $runId = (string) DB::table('coa_linkage_changes')->value('run_id');

        DB::table('tasks')->where('id', $this->ownRateUsdId)->update(['total' => 999.000]);

        $this->assertSame(1, Artisan::call('accounting:repair-task-fx-conversion', ['--rollback' => $runId]));
        $this->assertSame('999.000', $this->totalOf($this->ownRateUsdId));
    }

    public function test_a_second_apply_is_a_no_op(): void
    {
        $this->repair(['--apply' => true]);
        $images = DB::table('coa_linkage_changes')->count();

        $this->repair(['--apply' => true]);

        $this->assertSame($images, DB::table('coa_linkage_changes')->count());
    }

    /**
     * ── MUTATION PROOF M-A9-FX-PAGING ───────────────────────────────────────────────────────────
     * REFUSED and UNTESTABLE tasks stay defective forever, so a loop that re-queried the defect
     * condition would hand back the same batch and never terminate. `--batch-size=1` with a refused
     * task ahead of a repairable one in id order both hangs and fails if the paging regresses.
     */
    public function test_a_refused_task_does_not_stall_the_paging(): void
    {
        $later = $this->task('USD', originalTotal: 315.000, total: 315.000, rate: 0.340000, createdAt: '2026-06-02 10:00:00');
        $this->assertGreaterThan($this->noRateEurExactId, $later, 'the proof rests on this order');

        $this->repair(['--apply' => true, '--batch-size' => 1]);

        $this->assertSame('240.000', $this->totalOf($this->noRateEurExactId), 'still refused');
        // 315.000 x 0.340000 = 107.100 — CT-FX-EXPOSURE §3.2's figure for task 15994.
        $this->assertSame('107.100', $this->totalOf($later), 'a refused task must not stop the walk');
    }

    public function test_the_label_repair_refuses_an_fx_run_and_names_this_command(): void
    {
        $this->repair(['--apply' => true]);
        $runId = (string) DB::table('coa_linkage_changes')->value('run_id');

        $this->artisan('accounting:repair-currency-label', ['--rollback' => $runId])
            ->expectsOutputToContain('contains before-images this command does not own')
            ->expectsOutputToContain('php artisan accounting:repair-task-fx-conversion --rollback='.$runId)
            ->assertExitCode(1);

        $this->assertSame('125.120', $this->totalOf($this->ownRateUsdId), 'the wrong command restored nothing');
    }

    public function test_dry_run_and_apply_together_are_refused(): void
    {
        $this->assertSame(1, $this->repair(['--apply' => true, '--dry-run' => true]));
    }

    public function test_rollback_cannot_be_combined_with_apply(): void
    {
        $this->assertSame(1, Artisan::call('accounting:repair-task-fx-conversion', ['--rollback' => 'X', '--apply' => true]));
    }
}
