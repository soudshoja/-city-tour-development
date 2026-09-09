<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Controllers\ReceiptVoucherController;
use App\Models\InvoiceReceipt;
use Illuminate\Console\Command;

/**
 * CT-A3 E2 data-repair command, half (b) of the CT-F35 fix (owner-specified, both halves
 * required; see `.planning/phases/citytravelers-accounting-audit/CT-A2-ENGINE-REPLAY-
 * 2026-09-08.md` §3.2 and CT-A1 §2.1): `invoice_receipts.company_id` is NULL on every one of the
 * 109 legacy-imported receipt voucher rows. `ReceiptVoucherController::buildVoucherDraft()` used
 * to cast that NULL to the sentinel `0` before its first `AccountResolver::resolve()` call, which
 * always threw `UnmappedPurposeException` for company 0 -- 109 of 109 refused to post.
 *
 * Half (a) of the fix is in `ReceiptVoucherController::buildVoucherDraft()`: it now derives the
 * company from the same resolution chain this command calls, so a still-NULL row posts correctly
 * on demand even before this command ever runs. This command exists so:
 *
 *  - a NULL `company_id` does not have to be re-derived on every single post from here on;
 *  - anything that queries/report on `invoice_receipts.company_id` directly (rather than posting
 *    the row through the controller) also sees a correct, durable value.
 *
 * SINGLE IMPLEMENTATION: the resolution chain itself lives in exactly one place --
 * {@see ReceiptVoucherController::resolveReceiptCompanyId()} (public, static, no controller-
 * instance state needed) -- and this command calls it verbatim rather than re-implementing the
 * chain a second time, so the feeder half and the repair half of this fix can never silently drift
 * out of agreement on what a given row resolves to. See that method's own docblock for the exact
 * precedence (invoice -> client/agent -> task -> account -> branch) and why.
 *
 * --dry-run / --apply (SAFE DEFAULT): `--apply` is the ONLY flag that writes anything. Passing
 * `--dry-run`, passing neither flag, or passing `--dry-run` alongside `--apply` all behave as a
 * preview (only an explicit, unambiguous `--apply` with no `--dry-run` writes) -- doing nothing
 * destructive is the default, matching this build's own `accounting:periods:init` / `accounting:
 * ensure-system-leaves` convention of running the identical resolution code under `--dry-run` as
 * under a real run, never a second, could-drift read-only re-implementation.
 *
 * Idempotent: only ever selects `whereNull('company_id')`, so a row this command already
 * back-filled is never revisited by a later run, and a row that already carried a company_id
 * (populated by ANY means, at any time -- including a value that deliberately differs from what
 * this chain would itself compute) is never selected, let alone overwritten. The per-row loop
 * below re-checks `$row->company_id !== null` defensively before writing, so this guarantee holds
 * even if the query above is ever changed to something broader.
 *
 * Per-row reporting: every row is reported individually -- SET/WOULD SET <company_id>, or
 * UNRESOLVED with the row's own link columns (invoice_id/client_id/task_id/account_id/
 * bank_account_id/branch_id) so an operator can see exactly what data is missing.
 *
 * Exit code: SUCCESS (0) for a preview (no `--apply`), regardless of how many rows are
 * unresolved -- a preview never fails, it only reports. For `--apply`: SUCCESS only if every
 * selected row resolved and was written; FAILURE (1) if any row was left UNRESOLVED after the
 * run, so a caller gating a deploy/migration on this command's exit code (rather than grepping its
 * console text) can tell whether every legacy row is now postable.
 */
class RepairReceiptCompany extends Command
{
    protected $signature = 'accounting:repair-receipt-company
                            {--dry-run : Preview what would be back-filled without writing anything (the default whenever --apply is not also given)}
                            {--apply : Actually write the resolved company_id values -- the ONLY flag that writes}';

    protected $description = 'Back-fill invoice_receipts.company_id (CT-F35) from the invoice/client/task/account/branch chain for legacy rows where it is NULL; never overwrites a populated value, never writes the 0 sentinel.';

    public function handle(): int
    {
        // Only an explicit --apply with no --dry-run writes anything -- see class docblock's
        // "SAFE DEFAULT" note. --dry-run is accepted explicitly (rather than simply being the
        // absence of --apply) purely so an operator's intent is visible in shell history/CI logs;
        // it has no effect on behaviour beyond confirming the safe default.
        $apply = (bool) $this->option('apply') && ! (bool) $this->option('dry-run');

        $rows = InvoiceReceipt::whereNull('company_id')->orderBy('id')->get();

        if ($rows->isEmpty()) {
            $this->info('No invoice_receipts rows with a NULL company_id -- nothing to do.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s%d invoice_receipts row(s) with company_id IS NULL...',
            $apply ? 'Resolving and applying ' : '[dry-run] Resolving (not writing) ',
            $rows->count()
        ));

        $resolvedCount = 0;
        $unresolvedCount = 0;

        foreach ($rows as $row) {
            // Defensive re-check (belt-and-braces, see class docblock's "Idempotent" note): the
            // whereNull() above already guarantees this for the CURRENT query, but a row already
            // carrying a company_id must never be overwritten by this command under any
            // circumstance, including a future change to the query that selects $rows.
            if ($row->company_id !== null) {
                continue;
            }

            $resolved = ReceiptVoucherController::resolveReceiptCompanyId($row);

            if ($resolved === null || $resolved <= 0) {
                $unresolvedCount++;
                $this->warn(sprintf(
                    '  [id=%d] UNRESOLVED -- invoice_id=%s, client_id=%s, task_id=%s, account_id=%s, bank_account_id=%s, branch_id=%s: no link in the chain resolves to a positive company id.',
                    $row->id,
                    $row->invoice_id ?? 'null',
                    $row->client_id ?? 'null',
                    $row->task_id ?? 'null',
                    $row->account_id ?? 'null',
                    $row->bank_account_id ?? 'null',
                    $row->branch_id ?? 'null',
                ));

                continue;
            }

            $resolvedCount++;
            $this->line(sprintf(
                '  [id=%d] %s company_id=%d',
                $row->id,
                $apply ? 'SET' : 'WOULD SET',
                $resolved
            ));

            if ($apply) {
                $row->company_id = $resolved;
                $row->save();
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%s%d row(s), %d left unresolved.',
            $apply ? 'Back-filled ' : '[dry-run] Would back-fill ',
            $resolvedCount,
            $unresolvedCount
        ));

        if (! $apply) {
            $this->warn('Re-run with --apply (and no --dry-run) to write these values.');

            return self::SUCCESS;
        }

        if ($unresolvedCount > 0) {
            $this->warn("{$unresolvedCount} row(s) remain UNRESOLVED after --apply -- see the UNRESOLVED lines above.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
