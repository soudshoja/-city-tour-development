<?php

declare(strict_types=1);

namespace App\Services\Onboarding\Scope;

use App\Services\Accounting\SequenceService;
use Illuminate\Support\Facades\DB;

/**
 * CD-PORT — adaptation A-2: the id floor makes branch ids eight digits long, and City Travelers'
 * `transactions.reference_number` is `varchar(20)`.
 *
 * ── The failure, exactly as it presented ────────────────────────────────────────────────────────
 * {@see SequenceService::DEFAULT_MASK} is `{TYPE}{BRANCH:4}-{YYYY}-{SEQ:5}`, and `{BRANCH}` renders
 * the branch's PRIMARY KEY, zero-padded to at least four digits. In the Akeed-Ai pilot the legacy
 * branches were ids 1-4, so a document number came out as `OJV-0002-2025-00001` — 19 characters,
 * one under the limit, and nobody noticed the limit existed. Here the same branch is id 10,000,002
 * because the reserved band puts it there, and the number renders as `OJV-10000002-2025-00001` —
 * 23 characters into a 20-character column. The first real `legacy:replay` died on
 * `SQLSTATE[22001] … Data too long for column 'reference_number'` with the OJV half-posted and
 * rolled back.
 *
 * Note what this is and is not. It is NOT a City Travelers/Akeed schema divergence: both forks
 * declare `reference_number` as `varchar(20)` (migration
 * `2025_03_25_085421_add_columns_to_transactions_table`). It is a consequence of the id floor
 * meeting a column width that was always this tight, and it would have fired identically on Akeed
 * had the pilot used a floor. It therefore belongs to THIS lane to solve, and the solution must
 * not make it someone else's problem later.
 *
 * ── Why the fix is per-company `serial_schemas` rows and not a wider column ─────────────────────
 * Widening `transactions.reference_number` would be an ALTER on a 43,414-row table that City
 * Travelers' live mirror shares, to accommodate a company that is only visiting. It would also put
 * this lane's migration in front of every other lane's deploy. The column is not the thing that is
 * wrong.
 *
 * `serial_schemas.mask` is already per `(company_id, branch_id, doc_type, doc_year)` and already
 * the designed extension point — {@see SequenceService::next()} `firstOrCreate`s a row with the
 * default mask only when none exists. So this planner pre-creates those rows for the legacy
 * company alone, with a mask that carries a SHORT branch tag instead of the branch id:
 *
 *     {TYPE}-CO-{YYYY}-{SEQ:5}   ->  OJV-CO-2025-00001   (17 chars)
 *
 * The tag is the branch's own legacy `branch_code` from `map_branch` (CO / SH / MN / BY), so the
 * rendered number is more readable than the id form it replaces, not less. No engine file is
 * touched, no shared column changes, and every row this writes is inside the reserved band and is
 * removed by `legacy:unload` like any other.
 *
 * ── Uniqueness, checked rather than assumed ─────────────────────────────────────────────────────
 * `transactions` carries `UNIQUE (company_id, doc_type, reference_number)`. Dropping the branch id
 * out of the mask would collide across branches if the replacement tag were not itself unique per
 * branch, so {@see self::plan()} refuses when two branches of the same company would resolve to the
 * same tag, rather than discovering it as a duplicate-key error a few thousand documents into a
 * replay.
 *
 * ── The width is MEASURED, never assumed ────────────────────────────────────────────────────────
 * {@see self::referenceNumberLimit()} reads `CHARACTER_MAXIMUM_LENGTH` from
 * `information_schema.COLUMNS`. It is deliberately not the constant `20`: an oracle that hard-coded
 * the same number the code is supposed to be checking against would keep passing if the column were
 * later widened or narrowed, which is precisely the "expectation derived from the constant the code
 * reads" pitfall. The check compares a RENDERED sample number against the LIVE column.
 */
final class LegacySerialSchemaPlanner
{
    /**
     * Every `doc_type` {@see \App\Services\Accounting\PostingService::post()} accepts. Seeding all
     * of them (rather than only the ones a particular replay slice happens to need) costs a few
     * dozen rows and removes an entire class of "worked for OJV, died on INV three hours in".
     */
    public const DOC_TYPES = ['INV', 'RV', 'PV', 'JV', 'CRN', 'DBN', 'OJV', 'REV'];

    /**
     * @param  list<int>  $years
     * @return array{rows: list<array<string,mixed>>, sample: string, limit: int, tags: array<int,string>}
     *
     * @throws LegacyScopeRefused
     */
    public function plan(LegacyLoadScope $scope, array $years): array
    {
        $limit = $this->referenceNumberLimit();

        $branches = DB::table('branches')
            ->where('company_id', $scope->companyId)
            ->whereBetween('id', [$scope->idFloor, $scope->idCeiling])
            ->orderBy('id')
            ->pluck('name', 'id');

        if ($branches->isEmpty()) {
            throw new LegacyScopeRefused(
                'Refused: company '.$scope->companyId.' has no branches inside the reserved band, so '.
                'there is nothing to plan document numbering for. Run the provisioning step and '.
                '`legacy:import-masters` first.'
            );
        }

        $tags = $this->branchTags($scope, $branches->keys()->all());

        $rows = [];
        $longest = '';

        foreach ($branches as $branchId => $_name) {
            $tag = $tags[(int) $branchId];

            foreach (self::DOC_TYPES as $docType) {
                foreach ($years as $year) {
                    $mask = '{TYPE}-'.$tag.'-{YYYY}-{SEQ:5}';
                    $sample = $this->render($mask, $docType, (int) $year, 99999);

                    if (strlen($sample) > $limit) {
                        throw new LegacyScopeRefused(
                            "Refused: the planned document number '{$sample}' is ".strlen($sample).
                            " characters, and transactions.reference_number holds {$limit}. Shorten ".
                            'the branch tag (legacy_pilot.ct_scope.branch_tags) before loading — a '.
                            'replay that starts with this mask dies mid-document on SQLSTATE[22001].'
                        );
                    }

                    if (strlen($sample) > strlen($longest)) {
                        $longest = $sample;
                    }

                    $rows[] = [
                        'company_id' => $scope->companyId,
                        'branch_id' => (int) $branchId,
                        'doc_type' => $docType,
                        'doc_year' => (int) $year,
                        'mask' => $mask,
                        'last_serial' => 0,
                        'increment' => 1,
                    ];
                }
            }
        }

        return ['rows' => $rows, 'sample' => $longest, 'limit' => $limit, 'tags' => $tags];
    }

    /**
     * ROUND 2, finding F5 (second half) — refuse to START a replay whose document numbering is not
     * fully pre-seeded.
     *
     * {@see \App\Services\Accounting\SequenceService::next()} `firstOrCreate`s a `serial_schemas`
     * row with `DEFAULT_MASK` for any `(company, branch, doc_type, doc_year)` that has none. On
     * this company that mask renders the EIGHT-DIGIT branch id and produces a 22-23 character
     * number for a `varchar(20)` column — the exact `SQLSTATE[22001]` this planner exists to
     * prevent, arriving mid-run instead of before it.
     *
     * Round 1 relied on the planner having been run with the right `--years`, and `--years`
     * defaulted through two config keys that do not exist. That is two silent dependencies in a
     * row, and their joint failure mode is a replay that dies thousands of documents in.
     *
     * This asserts the whole grid — every branch the company owns, every doc type the engine can
     * emit, every year in range — is present AND that each present row's mask still renders inside
     * the live column width, because a hand-edited mask is as dangerous as a missing row.
     *
     * @param  list<int>  $years
     *
     * @throws LegacyScopeRefused
     */
    public function assertSeeded(LegacyLoadScope $scope, array $years): void
    {
        $plan = $this->plan($scope, $years);
        $limit = $plan['limit'];

        $missing = [];
        $tooLong = [];

        foreach ($plan['rows'] as $row) {
            $existing = DB::table('serial_schemas')
                ->where('company_id', $row['company_id'])
                ->where('branch_id', $row['branch_id'])
                ->where('doc_type', $row['doc_type'])
                ->where('doc_year', $row['doc_year'])
                ->first();

            if ($existing === null) {
                $missing[] = sprintf('%s/%s/%d', $row['doc_type'], $row['branch_id'], $row['doc_year']);

                continue;
            }

            $sample = $this->render((string) $existing->mask, $row['doc_type'], (int) $row['doc_year'], 99999);

            if (strlen($sample) > $limit) {
                $tooLong[] = sprintf(
                    '%s/%s/%d renders %s (%d chars)',
                    $row['doc_type'],
                    $row['branch_id'],
                    $row['doc_year'],
                    $sample,
                    strlen($sample)
                );
            }
        }

        if ($missing !== []) {
            throw new LegacyScopeRefused(
                'Refused before the first document: '.count($missing).' (doc_type/branch/year) '.
                'combination(s) have no pre-seeded serial_schemas row — '.
                implode(', ', array_slice($missing, 0, 12)).
                (count($missing) > 12 ? ', and '.(count($missing) - 12).' more' : '').
                '. SequenceService would mint each one with its DEFAULT_MASK, which renders this '.
                'company\'s eight-digit branch id and overflows transactions.reference_number '.
                '('.$limit.' chars) part-way through the run. Run `php artisan legacy:serials '.
                '--company='.$scope->companyId.' --apply` first.'
            );
        }

        if ($tooLong !== []) {
            throw new LegacyScopeRefused(
                'Refused before the first document: '.count($tooLong).' pre-seeded serial_schemas '.
                'mask(s) render longer than transactions.reference_number holds ('.$limit.
                ' chars, read from information_schema) — '.implode('; ', array_slice($tooLong, 0, 6)).'.'
            );
        }
    }

    /**
     * Inserts the planned rows, skipping any `(company, branch, doc_type, doc_year)` that already
     * has one.
     *
     * Deliberately `insert`-if-absent rather than `updateOrInsert`: an existing row may already
     * carry a non-zero `last_serial`, and overwriting its mask mid-load would make two documents
     * of the same type and year render under different shapes — worse than either shape alone.
     *
     * @param  list<array<string,mixed>>  $rows
     * @return array{inserted:int, existing:int}
     */
    public function apply(array $rows): array
    {
        $inserted = 0;
        $existing = 0;

        foreach ($rows as $row) {
            $already = DB::table('serial_schemas')
                ->where('company_id', $row['company_id'])
                ->where('branch_id', $row['branch_id'])
                ->where('doc_type', $row['doc_type'])
                ->where('doc_year', $row['doc_year'])
                ->exists();

            if ($already) {
                $existing++;

                continue;
            }

            DB::table('serial_schemas')->insert($row + ['created_at' => now(), 'updated_at' => now()]);
            $inserted++;
        }

        return ['inserted' => $inserted, 'existing' => $existing];
    }

    /**
     * A short, per-branch, unique tag: the branch's own legacy code from `map_branch` when the
     * masters import has run, and `B<n>` (its offset inside the band) otherwise — which is the
     * case for the branch `CompanyProvisioner` mints before any legacy data arrives.
     *
     * @param  list<int|string>  $branchIds
     * @return array<int,string>
     *
     * @throws LegacyScopeRefused on a tag collision
     */
    private function branchTags(LegacyLoadScope $scope, array $branchIds): array
    {
        $override = (array) config('legacy_pilot.ct_scope.branch_tags', []);

        $codes = [];

        if (DB::connection('legacy_pilot')->getSchemaBuilder()->hasTable('map_branch')) {
            $codes = DB::connection('legacy_pilot')->table('map_branch')
                ->where('company_id', $scope->companyId)
                ->whereNotNull('akeed_branch_id')
                ->pluck('branch_code', 'akeed_branch_id')
                ->all();
        }

        $tags = [];

        foreach ($branchIds as $id) {
            $id = (int) $id;

            $tag = $override[$id]
                ?? ($codes[$id] ?? null)
                ?? ('B'.($id - $scope->idFloor + 1));

            $tags[$id] = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $tag) ?: ('B'.$id));
        }

        $seen = [];

        foreach ($tags as $id => $tag) {
            if (isset($seen[$tag])) {
                throw new LegacyScopeRefused(
                    "Refused: branches {$seen[$tag]} and {$id} would both number documents under the ".
                    "tag '{$tag}'. transactions carries UNIQUE (company_id, doc_type, ".
                    'reference_number), so the two branches would collide on their first shared '.
                    'serial. Set distinct tags in legacy_pilot.ct_scope.branch_tags.'
                );
            }

            $seen[$tag] = $id;
        }

        return $tags;
    }

    /**
     * The LIVE width of `transactions.reference_number`, read from the database rather than
     * restated as a constant — see the class docblock's last paragraph for why that distinction is
     * load-bearing for this check's honesty.
     */
    public function referenceNumberLimit(): int
    {
        $row = DB::selectOne(
            'SELECT CHARACTER_MAXIMUM_LENGTH AS len FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [DB::connection()->getDatabaseName(), 'transactions', 'reference_number']
        );

        if ($row === null || $row->len === null) {
            throw new LegacyScopeRefused(
                'Refused: cannot read the width of transactions.reference_number from '.
                'information_schema. Without it there is nothing to check a planned document number '.
                'against, and an unchecked plan is what produced the SQLSTATE[22001] this class '.
                'exists to prevent.'
            );
        }

        return (int) $row->len;
    }

    /**
     * The same substitution {@see SequenceService::format()} performs, for the `{TYPE}` / `{YYYY}` /
     * `{SEQ}` tokens this planner's masks use.
     *
     * It is a deliberate second implementation rather than a call into SequenceService: that
     * method is private, and making it public purely so a guard could preview a string would widen
     * the engine's own API for this lane's convenience. The masks produced here contain no
     * `{BRANCH}` token at all — that is the entire point of them — so the branch-rendering half of
     * the engine's formatter has nothing to diverge from.
     */
    private function render(string $mask, string $docType, int $year, int $serial): string
    {
        return (string) preg_replace_callback(
            '/\{(TYPE|YYYY|SEQ)(?::(\d+))?\}/',
            static fn (array $m): string => match ($m[1]) {
                'TYPE' => $docType,
                'YYYY' => (string) $year,
                'SEQ' => str_pad((string) $serial, isset($m[2]) ? (int) $m[2] : strlen((string) $serial), '0', STR_PAD_LEFT),
                default => $m[0],
            },
            $mask
        );
    }
}
