<?php

namespace App\Models;

use App\Services\Accounting\SequenceService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Per-tenant document numbering counter backing
 * App\Services\Accounting\SequenceService::next() (BUG-H10, F6/F10/F13).
 *
 * One row per (company, branch, doc_type, doc_year); last_serial is advanced
 * atomically under a `SELECT … FOR UPDATE` by SequenceService, never read here
 * and incremented in PHP. No BelongsToCompany global scope for the same reason
 * as SystemAccount: the sequence must be safe to reserve from unauthenticated
 * gateway/queue context, so callers filter company_id explicitly.
 *
 * branch_id = 0 is the "no branch" sentinel this engine writes and reads
 * everywhere — see SequenceService::next() and the serial_schemas migration
 * docblock for the full rationale. Never a real branches.id (those start at 1).
 */
class SerialSchema extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'doc_type',
        'doc_year',
        'mask',
        'last_serial',
        'increment',
    ];

    protected $casts = [
        'doc_year' => 'integer',
        'last_serial' => 'integer',
        'increment' => 'integer',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Pre-cutover, one-shot seeding: raises last_serial for
     * (companyId, branchId, docType, targetYear) so the row will never issue a
     * document number that some pre-engine legacy code path already used.
     *
     * Fixes P1-VERIFICATION-FINDINGS.json HIGH finding "serial_schemas counters
     * start at zero and are never seeded from the legacy numbering — duplicate
     * document numbers at P2 cutover". NOT wired to a route, job, or scheduler —
     * call it deliberately, once per company, immediately before that company is
     * switched onto the engine. See App\Console\Commands\SeedAccountingSerialSchemas
     * for the CLI wrapper (`php artisan accounting:seed-serial-schemas`).
     *
     * SOURCE OF TRUTH — `transactions.reference_number` only, scanned for values
     * matching literally `{docType}-{4 digits}-{digits}` (the DEFAULT_MASK shape
     * every legacy number generator already produces: `sprintf('INV-%s-%05d', …)`
     * / `'VOU-%s-%05d'` / `'RF-%s-%05d'`). Chosen deliberately over the legacy
     * counter tables:
     *   - `transactions` already carries `company_id` and `branch_id` in exactly
     *     the shape this table needs — no inference required.
     *   - `invoices.invoice_number` is unique and would be a strong extra source
     *     for docType 'INV', but the `invoices` table carries NO `company_id`
     *     column in the current schema (file 11 §P4 adds it — still pending), so
     *     attributing a legacy invoice number to a company would require an
     *     unverified join through agent/client that this method deliberately
     *     does not guess at.
     *   - `App\Models\Sequence` / `InvoiceSequence` / `RefundSequence` are each a
     *     single running counter PER COMPANY ONLY — no doc_type, no branch, and
     *     (confirmed against PaymentController::generateVoucherNumber()) the
     *     'Sequence' counter mints ONE shared 'VOU-' prefix for both receipt and
     *     payment vouchers, which this engine numbers separately as 'RV'/'PV'.
     *     There is no reliable way to split that history back into RV vs PV, so
     *     it is not used. This is not a numbering-safety gap: legacy code never
     *     wrote an 'RV-' or 'PV-' (or 'JV-'/'OJV-'/'CRN-'/'DBN-'/'REV-') reference
     *     number — confirmed by repo-wide grep — so those doc_types correctly
     *     seed to whatever `last_serial` already holds (0 on a fresh row): there
     *     is nothing legacy to collide with. Only 'INV' has a real, confirmed
     *     collision risk (legacy and engine both mint 'INV-YYYY-NNNNN').
     * Operators seeding a company with meaningful pre-engine invoice volume
     * should additionally eyeball MAX(invoices.invoice_number) and
     * InvoiceSequence.current_sequence by hand before go-live as a belt-and-
     * braces check this method cannot perform safely on its own.
     *
     * Source rows are scanned system-wide (not filtered to $targetYear): legacy
     * numbers are NOT year-scoped counters — the year in the string is only the
     * year the document happened to be generated, the underlying counter never
     * resets — so the all-time max per branch is the safe, conservative floor to
     * seed the target year's counter with, regardless of which year each legacy
     * number carries.
     *
     * SAFE / IDEMPOTENT: only ever RAISES last_serial
     * (`max(existing last_serial, legacy max)`), never lowers it. Safe to re-run,
     * including after the engine has already started issuing numbers for that
     * (company, branch, doc_type, year) — it will never undo real progress.
     *
     * P1 ROUND 3 fix (.planning/P1-VERIFICATION-FINDINGS.json MEDIUM finding —
     * under-seeding): this method used to bucket the scanned reference_numbers by
     * `transactions.branch_id` and seed EACH branch's serial_schemas row to only
     * THAT branch's own bucket maximum. That silently assumed the legacy counter
     * was itself branch-scoped — it is not (see "SOURCE OF TRUTH" above:
     * `Sequence`/`InvoiceSequence` are each a SINGLE running counter PER COMPANY,
     * with no branch dimension at all). `$branchMax` only records *which branch a
     * document ended up filed under*, not how the counter that number came from was
     * scoped — a branch whose own bucket happened to be low (or entirely absent
     * from the scan) could therefore be seeded far below the counter's true
     * historical ceiling. Since `serial_schemas` gives every branch its OWN
     * independent counter going forward (unique key includes branch_id), that
     * under-seeded branch's engine counter could then mint a number the single
     * legacy counter had already handed to a DIFFERENT branch's document —
     * `reference_number` colliding across two unrelated transactions, exactly the
     * duplicate-numbering risk this whole class exists to close.
     *
     * FIX: compute one COMPANY-WIDE ceiling — the max across every bucket the scan
     * found, i.e. the true historical maximum the single legacy counter ever
     * reached for this (company, docType), branch notwithstanding — and seed EVERY
     * branch this company could plausibly post under (every real row in `branches`
     * for this company, plus the branchless `0` sentinel bucket, plus any
     * `branch_id` the scan found even if that branch row no longer exists) to that
     * SAME ceiling. This trades a small amount of "wasted" numeric headroom on
     * low-traffic branches (their counter jumps ahead further than their own
     * bucket strictly needed) for the only property that actually matters:
     * NO branch's post-cutover counter can ever mint a number the legacy
     * single counter already used, regardless of which branch used it.
     *
     * P1 ROUND 4 re-verification (both still hold, unchanged by this round): (1) the
     * year-unconstrained scan above — `\d{4}` matches any 4-digit year, not just $targetYear —
     * remains the documented deliberate choice, not a bug: constraining it to $targetYear would
     * let a legacy `INV-2024-00500` under-seed doc_year=2026 and reopen exactly the collision this
     * method exists to prevent. (2) the P1 ROUND 3 company-wide-ceiling fix above already covers
     * this round's new `{BRANCH}` numbering token (SequenceService::DEFAULT_MASK) with no further
     * change needed here: this method's regex only ever matches the OLD, branchless
     * `{TYPE}-{YYYY}-{SEQ}` shape (legacy numbers necessarily predate branch-scoped numbering), a
     * shape that now also happens to be exactly what a branchless engine post still renders (see
     * SequenceService's class docblock) — so it keeps finding every real legacy/branchless number
     * and correctly ignores real branch-scoped ones (`INV-0007-2026-00005` has a non-digit `-`
     * inside what would need to be the trailing `\d+$` group, so it never matches). And the
     * per-branch seeding this method performs is about raising `last_serial` numeric ceilings, not
     * about `mask`/token shape, so it was never coupled to the `{BRANCH}` token in the first place.
     *
     * @return array<int, array{branch_id:int, previous_last_serial:int, legacy_max:int, seeded_last_serial:int, changed:bool}>
     *                                                                                                                          one row per branch this company could post under (every real
     *                                                                                                                          `branches.id` for $companyId, plus the 0 no-branch sentinel,
     *                                                                                                                          plus any orphaned branch_id the legacy scan found) — `legacy_max`
     *                                                                                                                          is the SAME company-wide ceiling on every row, by design (see fix
     *                                                                                                                          note above), not a per-branch figure
     */
    public static function seedFromLegacyMax(int $companyId, string $docType, int $targetYear, bool $dryRun = false): array
    {
        $branchMax = [];

        DB::table('transactions')
            ->select(['id', 'branch_id', 'reference_number'])
            ->where('company_id', $companyId)
            ->where('reference_number', 'like', $docType.'-%')
            ->chunkById(1000, function ($rows) use (&$branchMax, $docType) {
                $pattern = '/^'.preg_quote($docType, '/').'-\d{4}-0*(\d+)$/';

                foreach ($rows as $row) {
                    if (! preg_match($pattern, (string) $row->reference_number, $m)) {
                        // Doesn't match the {TYPE}-{YYYY}-{SEQ} shape — not a number this
                        // method can safely attribute a sequence value to. Skipped, not
                        // guessed at.
                        continue;
                    }

                    $branchKey = (int) ($row->branch_id ?? 0);
                    $seq = (int) $m[1];

                    if (! isset($branchMax[$branchKey]) || $seq > $branchMax[$branchKey]) {
                        $branchMax[$branchKey] = $seq;
                    }
                }
            });

        // P1 ROUND 3 fix — see the class docblock's "P1 ROUND 3 fix" note above. The legacy
        // counter this scan reconstructs was PER COMPANY, not per branch, so the single
        // company-wide ceiling — not each bucket's own max — is what every branch must be
        // seeded to. max() over an empty array would throw, hence the explicit [] check;
        // an empty scan legitimately means "nothing legacy found", i.e. a ceiling of 0.
        $companyWideLegacyMax = $branchMax === [] ? 0 : max($branchMax);

        // Every branch this company could plausibly post under once the engine is live:
        // every real `branches` row for this company (a branch with zero legacy history
        // still needs the company-wide ceiling — its FIRST engine-minted number must not
        // collide with a legacy number some OTHER branch's document already used), the `0`
        // no-branch sentinel bucket (always seeded, matching every other doc_type/branch
        // combination this class seeds), and any branch_id the scan itself found even if
        // that branch row has since been deleted (its historical numbers still count
        // toward the ceiling and its bucket is still worth a report row).
        $branchIdsToSeed = DB::table('branches')
            ->where('company_id', $companyId)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        $branchIdsToSeed[] = 0;
        $branchIdsToSeed = array_values(array_unique(array_merge($branchIdsToSeed, array_keys($branchMax))));

        $report = [];

        foreach ($branchIdsToSeed as $branchKey) {
            $report[] = DB::transaction(function () use ($companyId, $branchKey, $docType, $targetYear, $companyWideLegacyMax, $dryRun) {
                $existing = DB::table('serial_schemas')
                    ->where('company_id', $companyId)
                    ->where('branch_id', $branchKey)
                    ->where('doc_type', $docType)
                    ->where('doc_year', $targetYear)
                    ->lockForUpdate()
                    ->first();

                $previous = $existing !== null ? (int) $existing->last_serial : 0;
                $seeded = max($previous, $companyWideLegacyMax);

                if (! $dryRun) {
                    if ($existing === null) {
                        DB::table('serial_schemas')->insert([
                            'company_id' => $companyId,
                            'branch_id' => $branchKey,
                            'doc_type' => $docType,
                            'doc_year' => $targetYear,
                            // P1 ROUND 4: reference SequenceService's own default mask rather than
                            // re-hardcoding the literal here — this insert and next()'s new-schema
                            // create() must always agree on what a freshly-seeded row's mask is, and
                            // a duplicated literal is exactly how the two would silently drift the
                            // next time one of them changes (as nearly happened when {BRANCH} was
                            // added: this literal was still the pre-branch-token shape).
                            'mask' => SequenceService::DEFAULT_MASK,
                            'last_serial' => $seeded,
                            'increment' => 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } elseif ($seeded !== $previous) {
                        DB::table('serial_schemas')
                            ->where('id', $existing->id)
                            ->update(['last_serial' => $seeded, 'updated_at' => now()]);
                    }
                }

                return [
                    'branch_id' => $branchKey,
                    'previous_last_serial' => $previous,
                    'legacy_max' => $companyWideLegacyMax,
                    'seeded_last_serial' => $seeded,
                    'changed' => $seeded !== $previous,
                ];
            });
        }

        return $report;
    }
}
