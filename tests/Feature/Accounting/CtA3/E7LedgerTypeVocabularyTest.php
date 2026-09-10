<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting\CtA3;

use App\Enums\LedgerType;
use App\Models\Account;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostedDocument;
use App\Services\Accounting\PostingService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use FilesystemIterator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClassConstant;
use Tests\Support\AccountingTestCase;

/**
 * CT-A3 E7 — CT-F36 (`journal_entries.type` carries two disjoint vocabularies) and CT-F31 (the
 * AP/"Expenses" screen filters on a plural `'expenses'` that nothing real writes).
 *
 * See `App\Enums\LedgerType`'s own class docblock for the full mapping table and the reasoning
 * behind every non-obvious entry; this suite proves that mapping actually holds, both statically
 * (every canonical value, every audit label) and dynamically (a real document posted through the
 * real `PostingService`).
 */
class E7LedgerTypeVocabularyTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Reflect LedgerType::LEGACY_MAP directly rather than duplicating it in the test — a table
     * copy-pasted into the test would drift from the real map and stop proving anything.
     *
     * @return array<string, string>
     */
    private static function legacyMap(): array
    {
        return (new ReflectionClassConstant(LedgerType::class, 'LEGACY_MAP'))->getValue();
    }

    // ── Case 1: every canonical value round-trips to itself ────────────────────────────────────

    public function test_every_canonical_value_round_trips_through_the_mapping_to_itself(): void
    {
        foreach (LedgerType::cases() as $case) {
            $this->assertSame(
                $case->value,
                LedgerType::resolve($case->value, null),
                "Canonical ledgerType '{$case->value}' must resolve to itself."
            );
            $this->assertSame(
                $case->value,
                LedgerType::resolve(null, $case->value),
                "Canonical value '{$case->value}' arriving via the transactionType fallback must "
                .'also resolve to itself (it is already a valid LedgerType member).'
            );
        }
    }

    // ── Case 2: CT-F31's own fix, named explicitly ──────────────────────────────────────────────

    public function test_expenses_plural_maps_to_expense_ct_f31(): void
    {
        $this->assertSame(
            LedgerType::EXPENSE->value,
            LedgerType::resolve('expenses', null),
            'CT-F31: the legacy plural "expenses" (written only by the AP screen\'s own hand-keyed '
            .'form) must normalise to the canonical singular "expense".'
        );

        $this->assertContains(
            'expenses',
            LedgerType::expenseFilterValues(),
            'The report-filter helper must still include the legacy plural so historical hand-keyed '
            .'rows keep showing up without a data migration.'
        );
        $this->assertContains(LedgerType::EXPENSE->value, LedgerType::expenseFilterValues());
    }

    // ── Case 3: every audit label maps to its canonical counterpart, table-driven ──────────────

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function auditLabelProvider(): array
    {
        $cases = [];
        foreach (self::legacyMap() as $label => $canonical) {
            $cases["{$label} -> {$canonical}"] = [$label, $canonical];
        }

        return $cases;
    }

    /**
     * @dataProvider auditLabelProvider
     */
    public function test_each_audit_label_maps_to_its_canonical_counterpart(string $label, string $expectedCanonical): void
    {
        $this->assertSame(
            $expectedCanonical,
            LedgerType::resolve(null, $label),
            "transactionType '{$label}' must resolve to canonical '{$expectedCanonical}' when no "
            .'ledgerType is set (the exact fallback path PostingService::post():1407 exercises).'
        );

        // Every value on the right-hand side of LEGACY_MAP must itself be one of the nine
        // canonical members -- the map can never point at another legacy synonym.
        $this->assertNotNull(
            LedgerType::tryFrom($expectedCanonical),
            "LEGACY_MAP['{$label}'] = '{$expectedCanonical}' is not itself a canonical LedgerType value."
        );
    }

    public function test_worked_examples_from_the_ticket_itself(): void
    {
        $this->assertSame('receivable', LedgerType::resolve(null, 'CUSTOMERDEBITED'));
        $this->assertSame('payable', LedgerType::resolve(null, 'SUPPLIERCREDITED'));
        $this->assertSame('income', LedgerType::resolve(null, 'INCOME'));
        $this->assertSame('expense', LedgerType::resolve(null, 'COSTOFSALES'));
    }

    // ── Case 4: the real proof — post through PostingService, read journal_entries.type back ────

    /**
     * The assertion that fails if PostingService.php:1407 regresses back to `?? $transactionType`
     * verbatim: a LineDraft with NO ledgerType (only the engine's own audit label) must still land
     * a CANONICAL value in the database, never the raw label.
     */
    public function test_a_posted_engine_document_with_null_ledger_type_writes_canonical_type(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 15, 10));
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();
        $this->trackCompanyForInvariants($company->id);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
        Artisan::call('accounting:periods:init', ['--company' => $company->id]);

        $clients = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '1351')->firstOrFail();
        $revenue = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '4110')->firstOrFail();

        $amount = 42.000;

        $draft = new DocumentDraft(
            companyId: $company->id,
            branchId: 0,
            docType: 'INV',
            subType: 'SALE',
            docDate: Carbon::create(2026, 6, 15),
            narration: 'CT-A3 E7 null-ledgerType proof',
            lines: [
                new LineDraft(
                    purposeCode: '',
                    accountId: $clients->id,
                    side: 'debit',
                    amount: $amount,
                    currency: 'KWD',
                    originalAmount: $amount,
                    exchangeRate: 1.0,
                    transactionType: 'CUSTOMERDEBITED',
                    ledgerType: null,
                ),
                new LineDraft(
                    purposeCode: '',
                    accountId: $revenue->id,
                    side: 'credit',
                    amount: $amount,
                    currency: 'KWD',
                    originalAmount: $amount,
                    exchangeRate: 1.0,
                    transactionType: 'INCOME',
                    ledgerType: null,
                ),
            ],
            idempotencyKey: 'ct-a3-e7:null-ledger-type-proof',
        );

        /** @var PostedDocument $posted */
        $posted = app(PostingService::class)->post($draft);

        $rows = JournalEntry::withoutGlobalScopes()
            ->where('transaction_id', $posted->transaction->id)
            ->orderBy('account_id', 'desc') // deterministic: clients (13xx) before revenue (41xx) is not guaranteed by insert order alone
            ->get()
            ->keyBy('account_id');

        $clientLine = $rows->get($clients->id);
        $revenueLine = $rows->get($revenue->id);

        $this->assertNotNull($clientLine, 'The AR line was not written.');
        $this->assertNotNull($revenueLine, 'The revenue line was not written.');

        $this->assertSame(
            'receivable',
            $clientLine->type,
            'A LineDraft with transactionType=CUSTOMERDEBITED and ledgerType=null must write the '
            ."CANONICAL 'receivable', never the raw audit label 'CUSTOMERDEBITED' (CT-F36)."
        );
        $this->assertSame(
            'income',
            $revenueLine->type,
            'A LineDraft with transactionType=INCOME and ledgerType=null must write the CANONICAL '
            ."'income' (already lowercase-identical here, but must not be a coincidence — proven "
            .'separately by the CUSTOMERDEBITED line above using a genuinely different raw value).'
        );

        // Belt and suspenders: whatever landed in the column must be a real member of the
        // canonical enum, never an arbitrary string.
        $this->assertNotNull(LedgerType::tryFrom((string) $clientLine->type));
        $this->assertNotNull(LedgerType::tryFrom((string) $revenueLine->type));
    }

    // ── Case 5: source-scanning architecture case ──────────────────────────────────────────────

    /**
     * No file in app/ may still contain the literal `['payable', 'expenses']` / `'payable',
     * 'expenses'` filter-array shape CT-F31 forbids. The `in:payable,expenses` VALIDATION rule
     * (AccountingController.php's `storePayableDetail()`) is deliberately a different shape (a
     * pipe-delimited Laravel validation string, not a PHP array literal) and must keep matching —
     * requirement 4 explicitly keeps accepting the legacy plural from old forms/bookmarks — so the
     * regex below targets the array-literal shape only, never the validation string.
     */
    /**
     * Strips PHP comments/docblocks via token_get_all() before matching, so a comment that merely
     * CITES the forbidden shape (e.g. CheckMyFatoorahPayments.php's own "see
     * whereIn('type', ['payable','expenses'])" note, or LineDraft.php's class docblock, or this
     * very test file's own explanatory comments) never counts as an offender -- only real,
     * executable code does. This is deliberately more precise than a whole-file grep (this repo's
     * own ArchitectureTest convention) because CT-F31's fix landed comments that literally quote
     * the shape they replaced.
     */
    private static function codeWithoutComments(string $contents): string
    {
        $out = '';
        foreach (token_get_all($contents) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $out .= $token[1];
            } else {
                $out .= $token;
            }
        }

        return $out;
    }

    public function test_no_file_in_app_contains_the_payable_expenses_filter_array(): void
    {
        $appRoot = base_path('app');
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($appRoot, FilesystemIterator::SKIP_DOTS)
        );

        $pattern = '/\[\s*[\'"]payable[\'"]\s*,\s*[\'"]expenses[\'"]\s*\]/';

        $offenders = [];

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if ($contents === false) {
                continue;
            }

            if (preg_match($pattern, self::codeWithoutComments($contents)) === 1) {
                $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "These file(s) still contain the literal ['payable', 'expenses'] filter array CT-F31 "
            .'forbids -- route them through LedgerType::payableFilterValues() / '
            .'expenseFilterValues() instead: '.implode(', ', $offenders)
        );
    }

    /**
     * Mutation proof for the scan above: a throwaway file carrying the exact forbidden shape must
     * be caught by the same regex the live test uses, so a rotted regex fails loudly here instead
     * of the live test passing quietly because it stopped matching anything.
     */
    public function test_the_payable_expenses_scan_actually_bites_a_synthetic_violation(): void
    {
        $root = sys_get_temp_dir().'/ct-a3-e7-scan-mutation-'.uniqid();
        @mkdir($root, 0777, true);
        $victim = $root.'/SyntheticOffender.php';
        file_put_contents($victim, "<?php\n\$x = JournalEntry::whereIn('type', ['payable', 'expenses']);\n");

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
            );
            $pattern = '/\[\s*[\'"]payable[\'"]\s*,\s*[\'"]expenses[\'"]\s*\]/';
            $hit = false;

            foreach ($iterator as $file) {
                $contents = file_get_contents($file->getPathname());
                if ($contents !== false && preg_match($pattern, self::codeWithoutComments($contents)) === 1) {
                    $hit = true;
                }
            }

            $this->assertTrue($hit, 'The synthetic violation was not detected — the scan regex has rotted.');
        } finally {
            @unlink($victim);
            @rmdir($root);
        }
    }

    // ── Case 6: the AP screen's own query, with both singular and plural rows ─────────────────

    public function test_the_ap_screen_filter_returns_both_singular_expense_and_legacy_plural_rows(): void
    {
        [$company] = $this->makeMinimalCompany();

        $payableAccount = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('code', '2110')->first()
            ?? Account::factory()->create(['company_id' => $company->id]);

        $singular = JournalEntry::create([
            'transaction_id' => null, 'company_id' => $company->id, 'branch_id' => null,
            'account_id' => $payableAccount->id, 'transaction_date' => now(),
            'description' => 'E7 singular expense fixture',
            'debit' => 10.000, 'credit' => 0, 'name' => 'E7 fixture', 'type' => 'expense',
            'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => 10.000,
        ]);

        $plural = JournalEntry::create([
            'transaction_id' => null, 'company_id' => $company->id, 'branch_id' => null,
            'account_id' => $payableAccount->id, 'transaction_date' => now(),
            'description' => 'E7 legacy plural expense fixture (hand-keyed AP form)',
            'debit' => 20.000, 'credit' => 0, 'name' => 'E7 fixture', 'type' => 'expenses',
            'currency' => 'KWD', 'exchange_rate' => 1, 'amount' => 20.000,
        ]);

        // Exactly AccountingController::createPayableDetail()'s own query shape, post-fix.
        $found = JournalEntry::whereIn('type', [
            ...LedgerType::payableFilterValues(),
            ...LedgerType::expenseFilterValues(),
        ])
            ->where('company_id', $company->id)
            ->where('debit', '>', 0)
            ->pluck('id');

        $this->assertContains($singular->id, $found, "The AP/'Expenses' filter must see the canonical singular 'expense' row.");
        $this->assertContains($plural->id, $found, "The AP/'Expenses' filter must ALSO see the legacy plural 'expenses' row (CT-F31's own point: old hand-keyed rows keep showing up without a data migration).");
    }

    /**
     * Deliberately NOT tracked via trackCompanyForInvariants(): this test's two fixture rows are
     * intentionally orphaned (transaction_id null) and one-sided (no matching credit leg) — they
     * exist only to prove the AP screen's *read* filter, not to exercise a real posting, so they
     * must not be checked against AccountingTestCase's tearDown() write invariants (which would
     * correctly, but irrelevantly, flag them as orphans/unbalanced).
     *
     * @return array{0: Company}
     */
    private function makeMinimalCompany(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);

        return [$company];
    }
}
