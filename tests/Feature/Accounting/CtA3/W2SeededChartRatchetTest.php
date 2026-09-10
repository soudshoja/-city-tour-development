<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA3;

use App\Console\Commands\EnsureSystemLeaves;
use App\Models\Account;
use App\Models\Company;
use App\Services\Accounting\AccountResolver;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * CT-A3 wave 2 — two ratchets, both added because a real defect got past the existing ones.
 *
 * ── Ratchet 1: no two seeded leaves share an account code ───────────────────────────────────────
 * Wave 2's first cut gave `SUPPLIER_REFUND_LOSS` code **5126**, which is reserved for P5.13's
 * not-yet-built `Loss Recovery (Agents)` leaf. That reservation is recorded in four places —
 * `PostingService`'s resolved-gap #9 note, `InvoiceController::postAgentLossRecoveryHook()`'s
 * docblock, `CoaSeeder`'s own 5125/5127 comments, and `config('accounting')`'s §56 note — and in
 * exactly one test, `SystemAccountsSeederVoucherAnchorsTest`, which is what caught it. That test
 * guards ONE code. A reservation that lives in comments is one the next wave steps on, so this
 * generalises it: **every code the seeded chart mints must be unique**, whether or not anyone
 * remembered to write a note about it.
 *
 * Deliberately scoped to a FRESHLY SEEDED company. CT-A1 §1.4 measured 290 accounts sharing 28
 * duplicate codes on the real City Travelers chart — years of hand-editing, and CT-A4's problem to
 * clean up. What this lane can guarantee is that the seeders themselves never ADD a collision.
 *
 * ── Ratchet 2: a purpose code used as a LineDraft purpose must be mappable ──────────────────────
 * CT-A4 found that `REFUND_PAYOUT_CASH_BANK` — the leaf a `refund_out` disposition credits, used
 * as a bare `purposeCode:` in five places since W4.R — was never listed in
 * `config('accounting.purpose_codes.global')`. `AccountResolver` still finds a hand-inserted
 * `system_accounts` row, which is why every existing test passes (they all insert one), but
 * `App\Http\Livewire\Accounting\PurposeMappingIndex` enumerates exactly that array — so on any
 * company nobody had hand-mapped, `refund_out` threw `UnmappedPurposeException` and there was **no
 * way to map it from the UI**. An unlistable purpose code is an unfixable one.
 */
class W2SeededChartRatchetTest extends AccountingTestCase
{
    /**
     * PRE-EXISTING collisions this ratchet documents rather than fixes. The list only ever
     * shrinks — the same convention `ArchitectureTest::ALLOW_LISTED_RAW_WRITER_FILES` uses for
     * legacy ledger writers.
     *
     *   2130 — `CoaSeeder` mints it TWICE, as `Suppliers (Hotels)` (line 149) and
     *          `Suppliers (Ferry)` (line 159), both level 3 under `Accounts Payable`. Found by
     *          this ratchet on its first run. It is real: every freshly seeded chart in this
     *          codebase carries two accounts numbered 2130, and CT-A1 §1.4 measured 290 accounts
     *          sharing 28 duplicate codes on the live City Travelers chart, of which this is one
     *          source. `AccountResolver` resolves by NAME and id, never by code, so nothing posts
     *          to the wrong place today — but any report or import keyed on code cannot tell the
     *          two apart.
     *
     *          NOT fixed here, deliberately: renumbering a seeded account is a chart decision and
     *          CT-A4 (PR #5) owns the chart. Handed over rather than grabbed, so the two lanes do
     *          not both edit CoaSeeder.
     */
    private const KNOWN_SEEDER_CODE_COLLISIONS = ['2130'];

    // ────────────────────────────────────────────────────────────────────────────────────────
    // Ratchet 1
    // ────────────────────────────────────────────────────────────────────────────────────────

    /** The static definition tables, checked without touching a database. */
    public function test_the_seeder_definitions_declare_no_duplicate_code(): void
    {
        $ensureCodes = array_map(
            static fn (array $spec) => (string) $spec['code'],
            EnsureSystemLeaves::leafSpecs()
        );

        $duplicatesWithin = array_keys(array_filter(array_count_values($ensureCodes), fn ($n) => $n > 1));
        $this->assertSame([], $duplicatesWithin, 'EnsureSystemLeaves::LEAVES declares a code twice: '.implode(', ', $duplicatesWithin));

        // 5126 by name, because it is the one this ratchet exists for: reserved for P5.13's
        // agent-loss-recovery leaf, and nothing this or any later wave seeds may claim it.
        $this->assertNotContains(
            '5126',
            $ensureCodes,
            '5126 is reserved for P5.13\'s Loss Recovery (Agents) leaf — see PostingService resolved-gap #9 '
            .'and InvoiceController::postAgentLossRecoveryHook().'
        );
    }

    /** And the chart those definitions actually produce. */
    public function test_a_freshly_seeded_chart_has_no_duplicate_account_code(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();
        Artisan::call('accounting:ensure-system-leaves', ['--company' => $company->id]);

        $duplicates = DB::table('accounts')
            ->select('code', DB::raw('COUNT(*) as n'), DB::raw('GROUP_CONCAT(name) as names'))
            ->where('company_id', $company->id)
            ->whereNull('deleted_at')
            ->whereNotNull('code')
            ->where('code', '<>', '')
            ->groupBy('code')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->reject(fn ($d) => in_array((string) $d->code, self::KNOWN_SEEDER_CODE_COLLISIONS, true))
            ->values();

        $this->assertCount(
            0,
            $duplicates,
            'A freshly seeded chart must mint every account code exactly once. NEW collisions: '
            .$duplicates->map(fn ($d) => $d->code.' × '.$d->n.' ('.$d->names.')')->implode('; ')
        );

        // The allow-list is not an excuse: prove the known collision is STILL there, so that when
        // CT-A4 fixes it this test fails and the entry gets deleted rather than quietly rotting.
        foreach (self::KNOWN_SEEDER_CODE_COLLISIONS as $code) {
            $this->assertGreaterThan(
                1,
                Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', $code)->count(),
                "Code {$code} is on KNOWN_SEEDER_CODE_COLLISIONS but no longer collides — delete the entry."
            );
        }

        // 5126 must still be free on a chart this codebase seeds.
        $this->assertFalse(
            Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '5126')->exists(),
            '5126 is reserved for P5.13 and must remain unused.'
        );

        // And wave 2's own leaf is where it says it is.
        $loss = app(AccountResolver::class)->resolve('SUPPLIER_REFUND_LOSS', $company->id);
        $this->assertSame('5131', (string) $loss->code);
        $this->assertSame('Supplier Refund Loss', $loss->name);
    }

    // ────────────────────────────────────────────────────────────────────────────────────────
    // Ratchet 2
    // ────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Every purpose code passed as a literal `purposeCode:` anywhere in `app/` must be reachable
     * from the purpose-mapping screen — i.e. present in `global`, in `per_service`, or produced by
     * one of the two documented key expansions (`gateways` → `GATEWAY_CLEARING_{KEY}` /
     * `GATEWAY_FEE_EXPENSE_{KEY}`, `fixed_assets.classes` → `FA_ACCUM_DEP_{KEY}`).
     *
     * A code that fails this is not necessarily broken today — `AccountResolver` will happily use a
     * hand-inserted `system_accounts` row — but it is UNMAPPABLE, which means the only way to fix a
     * company that hits it is a manual INSERT. That is what `REFUND_PAYOUT_CASH_BANK` was.
     */
    public function test_every_line_draft_purpose_code_is_registered_and_therefore_mappable(): void
    {
        $registered = array_merge(
            (array) config('accounting.purpose_codes.global', []),
            (array) config('accounting.purpose_codes.per_service', []),
            (array) config('accounting.purpose_codes.anchors', []),
        );

        foreach (array_keys((array) config('accounting.purpose_codes.gateways', [])) as $key) {
            $registered[] = 'GATEWAY_CLEARING_'.$key;
            $registered[] = 'GATEWAY_FEE_EXPENSE_'.$key;
        }

        foreach (array_keys((array) config('accounting.fixed_assets.classes', [])) as $key) {
            $registered[] = 'FA_ACCUM_DEP_'.strtoupper((string) $key);
        }

        $registered = array_values(array_unique(array_filter(array_map('strval', $registered))));

        $used = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path()));

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $body = file_get_contents($file->getPathname());

            if (! preg_match_all("/purposeCode:\s*'([A-Z0-9_]+)'/", $body, $matches)) {
                continue;
            }

            foreach ($matches[1] as $code) {
                $used[$code] ??= [];
                $used[$code][] = str_replace(app_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        $this->assertNotEmpty($used, 'The scanner found no purposeCode: literals at all — it has stopped matching.');

        $unregistered = [];

        foreach ($used as $code => $sites) {
            if (! in_array($code, $registered, true)) {
                $unregistered[] = $code.' (used in '.implode(', ', array_unique($sites)).')';
            }
        }

        $this->assertSame(
            [],
            $unregistered,
            'These purpose codes are used as a LineDraft purpose but are not in the purpose registry, so '
            .'App\\Http\\Livewire\\Accounting\\PurposeMappingIndex cannot list them and an operator cannot map '
            ."them:\n  ".implode("\n  ", $unregistered)
        );
    }

    /** Mutation proof for ratchet 2: remove the code CT-A4 found and the scanner must fail. */
    public function test_the_purpose_registry_ratchet_actually_detects_an_unregistered_code(): void
    {
        $global = array_values(array_filter(
            (array) config('accounting.purpose_codes.global', []),
            static fn ($c) => $c !== 'REFUND_PAYOUT_CASH_BANK'
        ));

        config(['accounting.purpose_codes.global' => $global]);

        $thrown = null;

        try {
            $this->test_every_line_draft_purpose_code_is_registered_and_therefore_mappable();
        } catch (\PHPUnit\Framework\AssertionFailedError $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, 'With REFUND_PAYOUT_CASH_BANK removed from the registry the ratchet must fail.');
        $this->assertStringContainsString('REFUND_PAYOUT_CASH_BANK', $thrown->getMessage());
    }
}
