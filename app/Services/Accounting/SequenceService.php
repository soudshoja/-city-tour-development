<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Atomic, per-tenant document numbering (BUG-H10, F6/F10/F13). Backs every INV/RV/PV/JV/CRN/DBN/
 * OJV/REV number PostingService mints — replaces the ad-hoc `Sequence::firstOrCreate -> read ->
 * increment` pattern and the four duplicated `sprintf('INV-%s-%05d')` sites, several of which lock
 * with no company filter (cross-tenant counter collision) or don't lock at all.
 *
 * File 11 §P1.0, L133-146, verbatim contract.
 *
 * BRANCH-SCOPED NUMBERING (P1 ROUND 4 fix — owner decision 2026-08-24): `serial_schemas` was
 * already keyed `(company_id, branch_id, doc_type, doc_year)` — each branch always had its own
 * counter — but the old DEFAULT_MASK `{TYPE}-{YYYY}-{SEQ:5}` and format()'s regex recognised only
 * TYPE|YYYY|SEQ, so the branch dimension never reached the rendered string: two branches of one
 * company minted byte-identical numbers (e.g. both `INV-2026-00001`). Fixed by adding a `{BRANCH}`
 * token (below) and putting it in the default mask, per the owner's explicit example shape
 * `INV-<BRANCH>-2026-00001`.
 *
 * `{BRANCH}` (and its `{BRANCH:N}` width form, same `:N` grammar as `{SEQ:N}`) renders to EMPTY —
 * not a zero, not a placeholder — when branchKey is the 0 "no branch" sentinel, and to
 * `-` + zero-padded-to-N-digits branch id otherwise; the leading `-` is part of the token's own
 * expansion, not a literal in the mask, so a branchless render never leaves a dangling separator.
 * With DEFAULT_MASK = `{TYPE}{BRANCH:4}-{YYYY}-{SEQ:5}`, a branchless company therefore renders
 * `INV-2026-00005` — byte-identical to the pre-branch-token legacy/engine shape, so
 * SerialSchema::seedFromLegacyMax()'s legacy-number regex keeps matching branchless numbers
 * unchanged — while a real branch renders `INV-0007-2026-00005`, a visibly different shape that
 * cannot collide with branch 0's numbers even if both happened to reach the same numeric serial.
 *
 * COLUMN-WIDTH CONSTRAINT — READ BEFORE CHANGING THE DEFAULT WIDTH: `transactions.reference_number`
 * is `VARCHAR(20)` (2025_03_25_085421_add_columns_to_transactions_table.php) and has never been
 * widened; MySQL strict mode is on for every connection (config/database.php), so an
 * over-length INSERT throws rather than silently truncating — fails loud, not corrupt, but still
 * a real outage for the doc_type/branch combination hitting it. Worst case is a 3-char doc_type
 * (INV/CRN/DBN/OJV/REV) with a branch present: `TYPE(3) + BRANCH-segment("-"+4 digits = 5) +
 * "-" + YYYY(4) + "-" + SEQ(5)` = 19 of 20 chars — 1 spare. `{BRANCH:4}` (0000-9999) was chosen
 * as the default width to fit this budget; `branches.id` is a single auto-increment shared across
 * every company on the platform (not per-company), so it is a real, if distant, long-term risk
 * that a platform-wide branch count above 9999 pushes some future company's numbers past 20 chars
 * and starts throwing on post for that branch. [USER-DECIDE]: if branch growth at that scale is
 * plausible, either widen `transactions.reference_number` ahead of time or shorten another
 * component of the mask — this class does not silently truncate the branch digits to make room,
 * matching {SEQ}'s existing never-truncate behavior, because silently dropping digits would risk
 * two different branches rendering the same number instead of failing loudly.
 */
final class SequenceService
{
    /**
     * Public so SerialSchema::seedFromLegacyMax() can insert new serial_schemas rows with the
     * exact same mask this service uses to render numbers, instead of duplicating the literal
     * and risking the two drifting apart (they did, briefly, during this round's {BRANCH} change).
     */
    public const DEFAULT_MASK = '{TYPE}{BRANCH:4}-{YYYY}-{SEQ:5}';

    /**
     * Atomically reserve the next document number for a company/branch/type/year.
     *
     * INVARIANT: MUST be called inside the caller's DB::transaction(); this takes a
     * SELECT ... FOR UPDATE row lock on serial_schemas and relies on the caller's transaction
     * boundary to hold that lock until the whole document (header + lines) is committed. Calling
     * it outside a transaction is a programmer error and throws immediately rather than silently
     * racing.
     *
     * @return array{0: string, 1: int} [formattedNumber, numericValue]. Never returns a numeric
     *                                  value already returned for the same (companyId, branchId, docType, docYear) key.
     */
    public function next(string $docType, int $companyId, ?int $branchId, \DateTimeInterface $date): array
    {
        $this->assertInsideTransaction();

        $docYear = (int) $date->format('Y');

        // serial_schemas.branch_id has no default meaning for "no branch" other than the one
        // this service defines: MySQL's unique index does NOT enforce uniqueness between NULLs,
        // so two concurrent "no branch" callers could both pass the existence check below before
        // either has inserted, giving the company two live counters for the same
        // (doc_type, doc_year) and, downstream, two documents with the same number. This service
        // never writes NULL into that column: branchId is normalized to the sentinel 0 for both
        // lookup and storage, so the DB-level unique(company_id, branch_id, doc_type, doc_year)
        // index gives a real, enforced uniqueness guarantee even for companies with no branch
        // dimension. The migration matches this convention exactly: branch_id is NOT NULL,
        // default 0, and carries no foreign key to `branches` (branches.id starts at 1, so 0
        // never collides with a real branch — see the migration's docblock for the full
        // rationale, including why a `branches` FK on this column is actively wrong).
        $branchKey = $branchId ?? 0;

        $schema = $this->lockSchemaRow($companyId, $branchKey, $docType, $docYear);

        if ($schema === null) {
            $this->createSchemaRow($companyId, $branchKey, $docType, $docYear);
            $schema = $this->lockSchemaRow($companyId, $branchKey, $docType, $docYear);
        }

        if ($schema === null) {
            throw new \RuntimeException(sprintf(
                'Failed to establish a serial_schemas row for company_id=%d branch_id=%s doc_type=%s doc_year=%d.',
                $companyId,
                $branchId === null ? 'NULL' : (string) $branchId,
                $docType,
                $docYear
            ));
        }

        $increment = max(1, (int) $schema->increment);
        $nextSerial = (int) $schema->last_serial + $increment;

        DB::table('serial_schemas')
            ->where('id', $schema->id)
            ->update([
                'last_serial' => $nextSerial,
                'updated_at' => now(),
            ]);

        return [$this->format((string) $schema->mask, $docType, $docYear, $nextSerial, $branchKey), $nextSerial];
    }

    private function lockSchemaRow(int $companyId, int $branchKey, string $docType, int $docYear): ?object
    {
        return DB::table('serial_schemas')
            ->where('company_id', $companyId)
            ->where('branch_id', $branchKey)
            ->where('doc_type', $docType)
            ->where('doc_year', $docYear)
            ->lockForUpdate()
            ->first();
    }

    private function createSchemaRow(int $companyId, int $branchKey, string $docType, int $docYear): void
    {
        try {
            DB::table('serial_schemas')->insert([
                'company_id' => $companyId,
                'branch_id' => $branchKey,
                'doc_type' => $docType,
                'doc_year' => $docYear,
                'mask' => self::DEFAULT_MASK,
                'last_serial' => 0,
                'increment' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException $e) {
            if (! $this->isDuplicateKeyViolation($e)) {
                throw $e;
            }
            // Lost the create race to a concurrent transaction — that's fine, the row now exists
            // and the caller re-locks/re-reads it below. MySQL/InnoDB does not poison the whole
            // transaction on a duplicate-key statement error the way Postgres does, so it is safe
            // to continue using this connection/transaction after catching this.
        }
    }

    /**
     * True only for MySQL error 1062 (duplicate entry on a unique index) — a real lost create
     * race on unique(company_id, branch_id, doc_type, doc_year), which is safe to swallow and
     * retry (see the catch site).
     *
     * Deliberately NOT matching on SQLSTATE '23000' alone: that class covers every integrity
     * constraint violation MySQL raises, including a foreign-key violation (error 1452) on
     * company_id if a caller ever passes a company id that does not exist. Matching the bare
     * SQLSTATE would swallow that FK error as if it were a lost create race, re-lock, get NULL
     * back, and surface the caller-facing RuntimeException ('Failed to establish a serial_schemas
     * row…') instead of the real cause — exactly the failure mode this method exists to avoid.
     */
    private function isDuplicateKeyViolation(QueryException $e): bool
    {
        return (int) ($e->errorInfo[1] ?? 0) === 1062;
    }

    /**
     * $branchKey is the already-normalized sentinel (0 = no branch, never a real branches.id —
     * see next()'s docblock). {BRANCH} is handled specially, not via the generic pad-and-splice
     * SEQ/TYPE/YYYY use: it renders to '' (not '0000') for the sentinel, and owns its own leading
     * '-' when a real branch is present, so the surrounding mask text never needs a conditional
     * separator — see the class docblock's "BRANCH-SCOPED NUMBERING" section for the full
     * rationale and the resulting rendered shapes.
     */
    private function format(string $mask, string $docType, int $docYear, int $serial, int $branchKey): string
    {
        return (string) preg_replace_callback(
            '/\{(TYPE|YYYY|SEQ|BRANCH)(?::(\d+))?\}/',
            static function (array $m) use ($docType, $docYear, $serial, $branchKey): string {
                return match ($m[1]) {
                    'TYPE' => $docType,
                    'YYYY' => (string) $docYear,
                    'SEQ' => str_pad((string) $serial, isset($m[2]) ? (int) $m[2] : strlen((string) $serial), '0', STR_PAD_LEFT),
                    'BRANCH' => $branchKey === 0
                        ? ''
                        : '-'.str_pad((string) $branchKey, isset($m[2]) ? (int) $m[2] : strlen((string) $branchKey), '0', STR_PAD_LEFT),
                    default => $m[0],
                };
            },
            $mask
        );
    }

    private function assertInsideTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException(sprintf(
                '%s::next() must be called inside an open DB::transaction() (it takes a SELECT ... FOR UPDATE lock that must live inside the caller\'s transaction boundary).',
                self::class
            ));
        }
    }
}
