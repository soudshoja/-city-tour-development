<?php

namespace Tests\Unit\Services\Accounting;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Services\Accounting\SequenceService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * P1 acceptance test for SequenceService (Accounting Gap/11-technical-implementation-plan.md
 * L133-146). Test name pinned verbatim to the build contract:
 * SequenceServiceTest::next_is_unique_under_concurrency.
 *
 * NOTE on "concurrency": per this build's explicit instruction, true
 * parallel DB access is simulated via SEQUENTIAL calls, not spawned
 * processes/threads — PHPUnit runs single-threaded here and this
 * environment must not fork processes against a shared database. This test
 * instead drives many sequential calls inside one transaction and asserts
 * the numbering is monotonic and never repeats — the property the
 * SELECT ... FOR UPDATE row lock exists to guarantee once real concurrent
 * callers are involved. A genuine concurrent race (two separate DB
 * connections calling next() at the same instant) can only be exercised
 * against a real database with parallel workers, which is out of scope for
 * a unit test and is called out explicitly rather than faked.
 */
class SequenceServiceTest extends AccountingTestCase
{
    /**
     * Pins: SequenceService::next() — "Returns [formattedNumber,
     * numericValue]. Never returns a value already returned." N sequential
     * reservations for the same (docType, companyId, branchId, year) must
     * yield N distinct, strictly increasing numericValues and N distinct
     * formattedNumber strings.
     */
    public function test_next_is_unique_under_concurrency(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $service = app(SequenceService::class);
        $date = now();
        $callCount = 25;

        $results = DB::transaction(function () use ($service, $company, $date, $callCount) {
            $collected = [];
            for ($i = 0; $i < $callCount; $i++) {
                $collected[] = $service->next('INV', $company->id, null, $date);
            }

            return $collected;
        });

        $this->assertCount($callCount, $results);

        foreach ($results as $result) {
            $this->assertIsArray($result);
            $this->assertCount(2, $result, 'next() must return exactly [formattedNumber, numericValue].');
        }

        $formattedNumbers = array_map(fn ($r) => $r[0], $results);
        $numericValues = array_map(fn ($r) => $r[1], $results);

        $this->assertSame(
            array_values(array_unique($numericValues)),
            array_values($numericValues),
            'next() must never return a numericValue already returned for the same (company, docType, year).'
        );
        $this->assertSame(
            array_values(array_unique($formattedNumbers)),
            array_values($formattedNumbers),
            'next() must never return a formattedNumber already returned for the same (company, docType, year).'
        );

        $sorted = $numericValues;
        sort($sorted, SORT_NUMERIC);
        $this->assertSame(
            $sorted,
            $numericValues,
            'Sequential next() calls within one company/docType/year must be monotonically increasing in call order.'
        );

        for ($i = 1; $i < count($numericValues); $i++) {
            $this->assertGreaterThan(
                $numericValues[$i - 1],
                $numericValues[$i],
                'Each reservation must be strictly greater than the previous one — no repeats, no plateaus.'
            );
        }
    }

    /**
     * Extra coverage: the unique index backing serial_schemas is
     * (company_id, branch_id, doc_type, doc_year) — two different companies,
     * or two different doc_types for the same company, must each get their
     * own independent counter rather than continuing one shared sequence.
     */
    public function test_next_scopes_the_counter_by_company_and_doc_type(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $this->trackCompanyForInvariants($companyA->id);
        $this->trackCompanyForInvariants($companyB->id);

        $service = app(SequenceService::class);
        $date = now();

        [, $firstForA] = DB::transaction(fn () => $service->next('INV', $companyA->id, null, $date));
        [, $firstForB] = DB::transaction(fn () => $service->next('INV', $companyB->id, null, $date));
        [, $firstJvForA] = DB::transaction(fn () => $service->next('JV', $companyA->id, null, $date));

        $this->assertSame(
            $firstForA,
            $firstForB,
            'Two different companies must each start their own INV/{year} sequence independently.'
        );
        $this->assertSame(
            $firstForA,
            $firstJvForA,
            'Two different doc_types for the same company must not share a counter.'
        );
    }

    /**
     * BLOCKER 2 regression pin (.planning/P1-VERIFICATION-FINDINGS.json blockers[1]):
     * "SequenceService writes branch_id = 0 into a column with a real FK to branches — every
     * branchless post() and every reverse() of a legacy NULL-branch transaction fails, and the
     * real error is masked." next() normalises a null $branchId to the sentinel 0 for both lookup
     * and storage; before the fix, serial_schemas.branch_id carried a real FK to branches whose
     * ids start at 1, so 0 never existed and every branchless call raised MySQL 1452 — masked by
     * isDuplicateKeyViolation() treating SQLSTATE 23000 (which also covers FK violations) as a
     * lost create race, surfacing the misleading "Failed to establish a serial_schemas row"
     * RuntimeException instead of the real cause. A company with no branch dimension (the natural
     * DocumentDraft::$branchId = 0 case, since that property is a non-nullable int) must be able
     * to reserve document numbers at all.
     */
    public function test_next_succeeds_with_no_branch(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $service = app(SequenceService::class);
        $date = now();

        $first = DB::transaction(fn () => $service->next('JV', $company->id, null, $date));

        $this->assertIsArray($first);
        $this->assertCount(2, $first, 'next() must return exactly [formattedNumber, numericValue].');
        $this->assertIsString($first[0]);
        $this->assertSame(
            1,
            $first[1],
            'The first branchless reservation for a fresh (company, docType, year) must be numeric value 1.'
        );

        // P1 ROUND 4: branch_id=0 must render with the {BRANCH} segment fully OMITTED, not a
        // "0000" placeholder — the exact same shape the pre-branch-token mask always produced, so
        // a branchless company's numbers stay clean, stay parseable by
        // SerialSchema::seedFromLegacyMax()'s legacy-number regex, and can never collide with a
        // real branch's numbers (which always carry a visibly different shape — see
        // test_two_branches_of_one_company_produce_different_numbers below).
        $this->assertMatchesRegularExpression(
            '/^JV-\d{4}-\d{5}$/',
            $first[0],
            'A branchless (branch_id=0) reservation must render TYPE-YYYY-SEQ with no {BRANCH} segment at all.'
        );

        // The counter must persist and advance across calls, not just succeed once.
        $second = DB::transaction(fn () => $service->next('JV', $company->id, null, $date));
        $this->assertSame(2, $second[1], 'A second branchless reservation must continue the same counter, not collide or reset.');
    }

    private function makeBranch(Company $company): Branch
    {
        return Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => User::factory()->create()->id,
        ]);
    }

    /**
     * BLOCKER 1 regression pin (P1 ROUND 4, owner decision 2026-08-24): "two branches of one
     * company mint identical document numbers" — PROVEN by execution before this round's fix
     * (`branch1=INV-2026-00001 branch2=INV-2026-00001`). serial_schemas was already keyed
     * `(company_id, branch_id, doc_type, doc_year)`, so each branch always had its OWN counter —
     * the bug was that the rendered string never carried the branch dimension, so two
     * independent-but-identically-numbered counters produced byte-identical output. Fixed by the
     * new `{BRANCH}` mask token (SequenceService::format()); this test pins that the two branches'
     * FIRST reservation of the same (docType, year) is both numerically independent (each starts
     * at 1) AND textually distinct (the rendered strings differ) — either alone would leave the
     * original collision half-fixed.
     */
    public function test_two_branches_of_one_company_produce_different_numbers(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $branchA = $this->makeBranch($company);
        $branchB = $this->makeBranch($company);

        $service = app(SequenceService::class);
        $date = now();

        [$formattedA, $numericA] = DB::transaction(fn () => $service->next('INV', $company->id, $branchA->id, $date));
        [$formattedB, $numericB] = DB::transaction(fn () => $service->next('INV', $company->id, $branchB->id, $date));

        $this->assertSame(1, $numericA, 'Branch A first INV reservation for a fresh year must be numeric value 1 — its own counter.');
        $this->assertSame(1, $numericB, 'Branch B first INV reservation for a fresh year must independently also be numeric value 1 — its own counter.');

        $this->assertNotSame(
            $formattedA,
            $formattedB,
            'Two branches of the same company must never render the same document number, even when their numeric serials coincide (both 1 here) — this is the exact BLOCKER 1 collision.'
        );

        // Pin the actual rendered shape, not just "they differ": each formatted number must
        // carry its OWN branch's zero-padded id as a distinct dash-delimited segment.
        $branchASegment = str_pad((string) $branchA->id, 4, '0', STR_PAD_LEFT);
        $branchBSegment = str_pad((string) $branchB->id, 4, '0', STR_PAD_LEFT);

        $this->assertSame("INV-{$branchASegment}-{$date->format('Y')}-00001", $formattedA);
        $this->assertSame("INV-{$branchBSegment}-{$date->format('Y')}-00001", $formattedB);
    }

    /**
     * Companion to test_two_branches_of_one_company_produce_different_numbers: a single real
     * branch's own counter must still be strictly monotonic across repeated reservations — the
     * {BRANCH} token change must not disturb the per-branch increment behavior
     * test_next_is_unique_under_concurrency already pins for the branchless case.
     */
    public function test_next_is_unique_under_concurrency_for_a_real_branch(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        $branch = $this->makeBranch($company);

        $service = app(SequenceService::class);
        $date = now();
        $callCount = 10;

        $results = DB::transaction(function () use ($service, $company, $branch, $date, $callCount) {
            $collected = [];
            for ($i = 0; $i < $callCount; $i++) {
                $collected[] = $service->next('RV', $company->id, $branch->id, $date);
            }

            return $collected;
        });

        $numericValues = array_map(fn ($r) => $r[1], $results);
        $formattedNumbers = array_map(fn ($r) => $r[0], $results);

        $this->assertSame(range(1, $callCount), $numericValues, 'A real branch counter must start at 1 and increment by exactly 1 per call, same as the branchless case.');
        $this->assertSame(array_values(array_unique($formattedNumbers)), array_values($formattedNumbers), 'A real branch must never repeat a formatted number.');

        $branchSegment = str_pad((string) $branch->id, 4, '0', STR_PAD_LEFT);
        foreach ($formattedNumbers as $formatted) {
            $this->assertStringContainsString(
                "-{$branchSegment}-",
                $formatted,
                'Every number reserved for this branch must carry its own zero-padded branch segment.'
            );
        }
    }

    /**
     * BLOCKER 1 database backstop (P1 ROUND 4): migration
     * 2026_08_24_120008_add_unique_reference_number_to_transactions_table.php adds
     * `unique(company_id, doc_type, reference_number)` to `transactions` so a bug in
     * SequenceService — or any future write path that bypasses it — cannot silently persist two
     * documents under the same number for one company/doc_type, even if the application-level
     * counter is somehow wrong. This test proves the constraint has exactly the shape claimed:
     * it rejects a real duplicate (company_id + doc_type both set, matching reference_number) and
     * — the specific legacy-safety property the migration's own docblock argues for — it does NOT
     * reject two rows that share a duplicate reference_number while doc_type is NULL, which is the
     * shape of every pre-engine legacy transaction row (see the migration's "NULL-safety /
     * legacy-collision analysis").
     */
    public function test_transactions_unique_constraint_rejects_duplicate_and_spares_legacy_null_doc_type(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $baseRow = [
            'entity_id' => $company->id,
            'entity_type' => 'company',
            'transaction_type' => 'INV',
            'amount' => 100,
            'description' => 'SequenceServiceTest unique-constraint probe',
            'reference_type' => 'Invoice',
            'company_id' => $company->id,
            'reference_number' => 'INV-2026-00001',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Two engine-shaped rows (doc_type IS NOT NULL, matching company_id + reference_number):
        // the constraint must reject the second insert.
        DB::table('transactions')->insert(array_merge($baseRow, ['doc_type' => 'INV']));

        $this->expectException(QueryException::class);

        try {
            DB::table('transactions')->insert(array_merge($baseRow, ['doc_type' => 'INV']));
        } finally {
            // Confirm the legacy-safety half of the claim regardless of whether the assertion
            // above throws as expected: two rows sharing the same company_id + reference_number
            // but with doc_type left NULL (the shape of every real pre-engine transaction row —
            // see PostingService is the only writer that has ever set doc_type) must NOT collide,
            // because MySQL exempts any row with a NULL indexed column from a unique check.
            DB::table('transactions')->insert(array_merge($baseRow, ['doc_type' => null, 'reference_number' => 'INV-2026-99999']));
            DB::table('transactions')->insert(array_merge($baseRow, ['doc_type' => null, 'reference_number' => 'INV-2026-99999']));

            $legacyShapedCount = DB::table('transactions')
                ->where('company_id', $company->id)
                ->whereNull('doc_type')
                ->where('reference_number', 'INV-2026-99999')
                ->count();

            $this->assertSame(
                2,
                $legacyShapedCount,
                'Two rows sharing company_id + reference_number with doc_type NULL (the legacy shape) must both persist — the unique constraint must not reject legacy-shaped duplicates.'
            );
        }
    }
}
