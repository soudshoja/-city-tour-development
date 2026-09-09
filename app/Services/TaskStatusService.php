<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Exceptions\Accounting\ProtectedLineException;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\TaskController;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoicePartial;
use App\Models\InvoiceReceipt;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\SupplierStatusMap;
use App\Models\Task;
use App\Models\TaskStatusEvent;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostedDocument;
use App\Services\Accounting\PostingSeam;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\SaleDraftBuilder;
use App\Services\Accounting\SaleDraftInput;
use App\Services\Accounting\TaskIssuancePayableService;
use App\Services\TaskStatus\MappedStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * W6.S (w6-brief.md "Consolidation + fixes" item 1 + the two "W6.S --" owner-addition sections,
 * 2026-08-28). Single owner of:
 *   (1) status mapping         -- {@see mapStatus()}, replacing the hard-coded per-supplier
 *                                  branches in TaskController::store(), TaskWebhook::
 *                                  applyStatusMapping(), and processSingleReservation().
 *   (2) original_task_id linking -- {@see linkOriginalTask()}, replacing the duplicated logic in
 *                                  TaskController::store() and TaskWebhook::linkOriginalTask().
 *   (3) financial dispatch     -- {@see dispatchFinancial()}, the ONE call site every caller now
 *                                  routes through instead of calling
 *                                  TaskController::processTaskFinancial() (or reflecting into
 *                                  TaskController::processVoidTask()) directly. Deliberately
 *                                  BEHAVIOUR-PRESERVING in this sub-wave: it still calls the
 *                                  existing (unchanged) processTaskFinancial() switch -- W6.S
 *                                  consolidates WHO calls it, not WHAT it does. OFF-path parity
 *                                  vs HEAD is therefore trivially exact (nothing about the
 *                                  posting logic itself changed).
 *   (4) the hold/confirmed follow-up lifecycle -- {@see expire()} and {@see cancel()} -- a
 *                                  genuinely NEW capability (not a consolidation of existing
 *                                  behaviour), so OFF-path parity does not apply to it; see each
 *                                  method's own docblock. Neither writes to journal_entries/
 *                                  transactions -- see cancel()'s own docblock for the disposition
 *                                  audit trail this leaves instead when a deposit exists.
 *
 * OFF-path parity note (mapStatus() only): per w6-brief.md's own "Per-supplier status map"
 * section, "OFF-path parity is explicitly NOT required for status mapping ... the engine-OFF/ON
 * switch governs posting, not status resolution". mapStatus() itself has no engine-on/off branch.
 *
 * FIX-ROUND NOTE (financial dispatch, item 3 above): the previous build round left
 * TaskController::store()'s own completion-triggered dispatch (its own body, not one of the two
 * status-change helpers) and update()'s two actual dispatch sites (handleStatusChange(),
 * updateEnabledStatus()) calling processTaskFinancial() directly, making the claim above false
 * for 2 of 5 named callers. This fix round routes all four remaining direct call sites (plus
 * storeManualHotel(), the same class of bug, not one of the five named callers but the identical
 * pattern) through dispatchFinancial() -- see TaskController.php for the fixed call sites.
 *
 * FIX ROUND 2 (re-verify, CRITICAL): {@see self::dispatchFinancial()} previously special-cased
 * ONLY `issued`/`emd` for the ON-path engine routing decision -- `reissued` fell through
 * unconditionally to `processTaskFinancial()` -> `processIssuedTask()`, the raw legacy
 * Transaction/JournalEntry writer, REGARDLESS of the engine flag. This directly contradicted
 * w6-brief.md's own W6.I item 1, which names `issued`/`reissued` TOGETHER in the SAME sentence
 * ("Engine ON: TaskController::processIssuedTask() becomes unreachable dead code on the
 * issued/reissued cases ... Verify by grep: processIssuedTask unreachable when the engine is
 * ON"), and it violates the orchestrator's hard rule that every ledger write enters
 * PostingSeam::post(). The path is genuinely reachable: TaskController::store()'s own
 * pre-existing Jazeera/FlyDubai same-reference fare-delta heuristic creates a brand-new Task row
 * with `status='reissued'` (own `total` = the fare DELTA, not a fresh full fare) that flows
 * straight into dispatchFinancial() a few hundred lines later, as does AirFileParser's `^FO`
 * reissue code and Magic Holiday's `AM` mapping.
 *
 * Scope boundary this fix respects (still true): this method does NOT implement the void-wave's
 * own REISSUE/EXCHANGE kind (w6-brief.md "Model" Kind 4 -- reverse the ORIGINAL task's sale+cost,
 * then post the new one, fare-diff surfaced as an explicit DBN/CRN) -- that full reverse+repost
 * mechanic, keyed on an explicit user-initiated reissue ACTION via a `TaskStatusService::
 * reissue()` method, is W6.R's own scope ("Does not touch void/reissue/bulk-void"), not rebuilt
 * here. What changes here is narrower and squarely W6.I's: an import-time task that ARRIVES
 * already flagged `reissued` by the raw source data (no user action, no reversal of anything --
 * CT's own as-is behaviour, importer-status-contract.md Table 1: "Same processIssuedTask() as
 * issued, on the new task; original's JEs untouched -- no reversal") must, when the engine is ON,
 * post its own one atomic sale document THROUGH THE ENGINE via the same {@see self::issue()} path
 * `issued` uses, rather than through the raw legacy poster outside PostingSeam entirely. The
 * original task's own sale/cost is still left untouched by this method (no reversal, no fare-diff
 * DBN/CRN) -- that remaining gap is real, unresolved by this fix, and is W6.R's job; this fix
 * closes only the "posts outside the engine" violation the previous round left open.
 *
 * W6.R (w6-brief.md "Kinds" 4, {@see self::reissue()}): the "remaining gap" the paragraph above
 * calls out is now closed for the common case -- {@see self::dispatchFinancial()}'s own `reissued`
 * branch resolves `$task->originalTask` and, when it is resolvable AND already invoiced, routes
 * through {@see self::reissue()} (full reverse-the-original + post-new-lines-on-the-same-invoice)
 * instead of {@see self::issue()}. A `reissued` task with no such resolvable original still falls
 * back to `issue()` exactly as this fix round left it -- see reissue()'s and dispatchFinancial()'s
 * own docblocks for the narrower, honestly-reported remainder.
 */
class TaskStatusService
{
    /**
     * Translate a `supplier_status_maps.canonical_status` value into the literal string
     * `tasks.status` expects. Only one value differs: the canonical vocabulary spells the hold
     * state `on_hold` (underscore, ct-void-map.md / w6-brief.md's own ENUM literal), while
     * `tasks.status` keeps the EXISTING enum value `'on hold'` (with a space -- confirmed by
     * reading 2025_08_11_160058_update_status_enum_in_tasks_table.php; w6-brief.md explicitly
     * says "do not introduce a second on_hold spelling"). Every other canonical value is spelled
     * identically in both vocabularies.
     */
    public function toTaskStatusValue(string $canonicalStatus): string
    {
        return $canonicalStatus === 'on_hold' ? 'on hold' : $canonicalStatus;
    }

    /**
     * The ONLY place any raw supplier/channel status is turned into a canonical status
     * (w6-brief.md "Per-supplier status map" item 2). Resolution order (four levels -- see the
     * "resolution order, clarified" note below for why this is one level more than the brief's
     * own three-line summary):
     *   1. company_id + supplier_id + channel + raw_status  (this company's own supplier row)
     *   2. company_id + channel + raw_status, supplier_id NULL  (this company's channel default)
     *   3. company_id NULL + supplier_id + channel + raw_status  (a codebase-shipped default for
     *      THIS supplier, regardless of which company owns the task -- see note below)
     *   4. company_id NULL + supplier_id NULL + channel + raw_status  (global default, shipped)
     * Returns a `needs_review` MappedStatus (and writes an audit row) when no row matches at any
     * level -- caller MUST NOT dispatch financials for that result (w6-brief.md item 4: "no
     * financial dispatch ... never reaches issue()/void()/expire()").
     *
     * Resolution order, clarified (build decision, reported): `Supplier` rows in this codebase are
     * GLOBAL, not per-company (`Supplier::where('name', 'Jazeera Airways')->first()` -- one row,
     * used by every company). The brief's own worked test #1 requires the SEEDED DEFAULT rows
     * alone (no per-company row at all) to reproduce "Jazeera/FlyDubai/VFS on hold -> confirmed,
     * confirmed -> issued" for every company that books those suppliers -- which is only possible
     * if a `company_id IS NULL` row can ALSO be scoped to one specific `supplier_id` (a
     * codebase-shipped rule for that supplier, not a blanket channel-wide default for every
     * supplier). The brief's own three-line resolution-order summary collapses this into its
     * "global default row" bullet without spelling out the supplier-scoped case explicitly; this
     * method makes it a distinct, explicit level (3, between the company-level rows and the
     * true "any supplier, any company" fallback) since a codebase-shipped Jazeera-specific rule
     * and a true is-there-nothing-else-at-all fallback are two different levels of specificity,
     * not one.
     *
     * $context carries the ONE piece of extra information the brief's own table needs that a
     * static raw_status->canonical_status row cannot express: Magic Holiday's `AM` code maps to
     * `reissued` ordinarily, but to `refund` when the reservation's own total is <= 0
     * (w6-brief.md's table: "AM->reissued (AM+total<=0->refund)"). Pass `['total' => $total]` for
     * the `magic` channel when known; omitted/absent context never triggers the override.
     */
    public function mapStatus(?Supplier $supplier, string $channel, string $raw, int $companyId, array $context = []): MappedStatus
    {
        $raw = trim($raw);

        $query = fn () => SupplierStatusMap::where('channel', $channel)
            ->where('raw_status', $raw)
            ->where('active', true);

        $row = null;
        $level = MappedStatus::LEVEL_UNMAPPED;

        if ($supplier !== null) {
            $row = $query()->where('company_id', $companyId)->where('supplier_id', $supplier->id)
                ->orderByDesc('priority')->first();
            if ($row !== null) {
                $level = MappedStatus::LEVEL_SUPPLIER;
            }
        }

        if ($row === null) {
            $row = $query()->where('company_id', $companyId)->whereNull('supplier_id')
                ->orderByDesc('priority')->first();
            if ($row !== null) {
                $level = MappedStatus::LEVEL_COMPANY_DEFAULT;
            }
        }

        if ($row === null && $supplier !== null) {
            $row = $query()->whereNull('company_id')->where('supplier_id', $supplier->id)
                ->orderByDesc('priority')->first();
            if ($row !== null) {
                $level = MappedStatus::LEVEL_GLOBAL_SUPPLIER;
            }
        }

        if ($row === null) {
            $row = $query()->whereNull('company_id')->whereNull('supplier_id')
                ->orderByDesc('priority')->first();
            if ($row !== null) {
                $level = MappedStatus::LEVEL_GLOBAL_DEFAULT;
            }
        }

        if ($row === null) {
            $this->recordEvent('status_unmapped', $companyId, null, null, 'needs_review', $channel, $raw, [
                'supplier_id' => $supplier?->id,
            ]);

            Log::warning('task_status.unmapped', [
                'company_id' => $companyId,
                'supplier_id' => $supplier?->id,
                'channel' => $channel,
                'raw_status' => $raw,
            ]);

            return new MappedStatus('needs_review', MappedStatus::LEVEL_UNMAPPED);
        }

        $canonical = $row->canonical_status;
        $level0 = $level;

        // See this method's own docblock -- the ONE case the brief's table needs a
        // post-resolution override for, rather than a second static row.
        if ($channel === 'magic' && strtoupper($raw) === 'AM' && $canonical === 'reissued'
            && array_key_exists('total', $context) && (float) $context['total'] <= 0.0) {
            $canonical = 'refund';
            $level0 = MappedStatus::LEVEL_OVERRIDE;
        }

        return new MappedStatus($canonical, $level0, $row, $row->deadline_source);
    }

    /**
     * Single owner of `original_task_id` linking (w6-brief.md "Consolidation + fixes" item 1).
     * Reproduces TaskController::store()'s (and TaskWebhook::linkOriginalTask()'s) own two
     * branches EXACTLY, including their pre-existing `where(...)->orWhere(...)->where(...)`
     * clause-grouping quirk -- see this class's own PHPDoc note below. Deliberately NOT fixed
     * here: this sub-wave's mandate is behaviour-preserving consolidation, not a correctness pass
     * over the matching predicate, and fixing it would change which task a reissue/void/refund/
     * emd import links onto in production. Flagged in the W6.S build report as a pre-existing
     * defect for a dedicated fix wave.
     *
     * KNOWN PRE-EXISTING DEFECT (carried over verbatim, not introduced by this method): because
     * `orWhere()` only changes the boolean connector for its OWN clause and every builder call is
     * appended at the SAME nesting level (no implicit parentheses), the query below is NOT
     * `(reference = $originalReference OR reference = $reference) AND passenger_name = ... AND
     * company_id = ... AND status IN (...)` as the variable names might suggest -- it is
     * `reference = $originalReference OR (reference = $reference AND passenger_name = ... AND
     * company_id = ... AND status IN (...))`, per SQL's AND-binds-tighter-than-OR precedence. A
     * task whose `reference` happens to equal `$originalReference` therefore matches regardless of
     * passenger name, company, or status. This is HEAD's own existing behaviour (see
     * TaskController.php's pre-W6.S store() and TaskWebhook::linkOriginalTask()), reproduced
     * byte-for-byte here, not something this consolidation makes worse.
     */
    public function linkOriginalTask(string $status, ?string $reference, ?string $originalReference, ?string $passengerName, int $companyId): ?Task
    {
        if (in_array($status, ['reissued', 'refund', 'void', 'emd'], true)) {
            return Task::where('reference', $originalReference)
                ->orWhere('reference', $reference)
                ->where('passenger_name', $passengerName)
                ->where('company_id', $companyId)
                ->whereIn('status', ['issued', 'reissued'])
                ->first();
        }

        if ($status === 'issued') {
            return Task::where('reference', $reference)
                ->where('company_id', $companyId)
                ->where('status', 'confirmed')
                ->where('passenger_name', $passengerName)
                ->first();
        }

        return null;
    }

    /**
     * The ONE call site every caller (TaskController::store()/update()/toggleStatus()/
     * updateMulti()/bulkUpdate(), TaskWebhook, ProcessAirFiles, ProcessVoidTasksFinancials,
     * processSingleReservation) now routes through instead of calling
     * TaskController::processTaskFinancial() -- or reflecting into TaskController::
     * processVoidTask() -- directly.
     *
     * W6.I "Importer contract" item 1/2 (w6-brief.md) FIX ROUND 2: this method is no longer a
     * pure pass-through to `processTaskFinancial()` for every status -- it is now the single
     * ON/OFF routing decision for the statuses W6.I owns:
     *   - `issued` AND `reissued` (FIX ROUND 2 widens this from `issued`-only -- see class
     *     docblock's "FIX ROUND 2" note for exactly why and for the scope boundary this respects):
     *     when the engine is ON for this task's company, routes to {@see self::issue()} instead of
     *     `processTaskFinancial()` -- W3d's sale document (via `InvoiceController::
     *     autoGenerateInvoice()` -> `addJournalEntry()` -> `SaleDraftBuilder`) already carries the
     *     supplier-payable/cost leg, so calling `processTaskFinancial()` -> `processIssuedTask()`
     *     as well would double-post that leg raw, outside the engine entirely. `reissued` still
     *     does NOT reverse the original task's own sale/cost (that remains W6.R's job, not
     *     rebuilt here) -- only the posting of the reissued task's OWN atomic document now goes
     *     through the engine instead of the raw legacy writer, exactly per the brief's own
     *     "processIssuedTask() becomes unreachable dead code on the issued/reissued cases".
     *     When the engine is OFF, both statuses fall through to `processTaskFinancial()` exactly
     *     as before -- BYTE-FOR-BYTE OFF-path parity vs HEAD, per the brief's own explicit
     *     requirement (this is unchanged by FIX ROUND 2).
     *   - `emd`: when the engine is ON, routes to {@see self::postEmdAncillary()} (an ancillary
     *     line on the PARENT's existing invoice) instead of `processTaskFinancial()`'s own `emd`
     *     case (which -- now that W6.S has deleted the `emd`->`issued` rewrite -- would otherwise
     *     call `processIssuedTask()` and book a brand-new, unlinked payable+cost pair for what is
     *     really an ancillary charge on an already-ticketed booking). When OFF, falls through to
     *     `processTaskFinancial()` unchanged -- see {@see self::postEmdAncillary()}'s own docblock
     *     for why OFF-path parity is not claimed for this one status (the emd->issued rewrite this
     *     sub-wave's own prerequisite, W6.S, already removed is itself a pre-existing OFF-path
     *     behaviour change this method did not introduce).
     * Every other status (`void`, `refund`, `confirmed`, `on hold`, etc.) is UNCHANGED -- still a
     * plain pass-through to `processTaskFinancial()`.
     */
    public function dispatchFinancial(Task $task): void
    {
        $companyId = (int) $task->company_id;
        $status = strtolower((string) $task->status);
        $engineOn = $companyId > 0 && app(PostingSeam::class)->isEnabledFor($companyId);

        // CT-A3 wave 1 feeder E-iss (owner rulings 2026-09-09, R-CT3). Runs FIRST, on EVERY
        // status, and for the engine-ON path only. Owner: "anything comes into task where its
        // been issued/vouchered and needs to be paid to supplier we want to automatically add it
        // to the right account so we know how much we need to pay regardless of them being
        // invoiced".
        //
        // Called unconditionally rather than from inside the per-status branches below, because
        // status TRANSITIONS are what drive it in both directions and no single branch sees them
        // all: a task held today and confirmed tomorrow accrues on the later dispatch; a task
        // that reaches `void`/`cancelled`/`refund` has its accrual reversed on that dispatch.
        // Everything else about whether to post is decided by master data in
        // {@see \App\Services\Accounting\SupplierPayableRule}, not here — including the
        // "not hold or some supplier confirmed" gate — and the whole call is a cheap no-op when
        // the rule says nothing is due. It never posts for a task whose sale document already
        // carries the cost, so the `issued` -> auto-invoice path below is unaffected.
        if ($engineOn) {
            app(TaskIssuancePayableService::class)->postIfDue($task);
        }

        if ($engineOn && $status === 'issued') {
            $this->issue($task);

            return;
        }

        // W6.R (w6-brief.md "Kinds" 4): FIX ROUND 2 (see class docblock) routed EVERY
        // import-time `reissued` task through {@see self::issue()} -- posting its own sale but
        // never reversing the ORIGINAL task's sale, exactly the gap that method's own docblock
        // flagged as "real, unresolved ... W6.R's job". This closes it for the common case: when
        // `original_task_id` resolves to a task that was actually issued/invoiced (the store()
        // Jazeera/FlyDubai fare-delta heuristic and AirFileParser's `^FO` code both set this link
        // before financial dispatch ever runs -- {@see self::linkOriginalTask()}), route through
        // {@see self::reissue()} instead, which reverses the original's sale AND posts the new
        // one as new lines on the SAME invoice. A `reissued` task with no resolvable, already-
        // invoiced original (e.g. a standalone import with nothing to link onto) falls back to
        // {@see self::issue()} exactly as before -- the narrower, still-real gap
        // {@see self::reissue()}'s own docblock and this sub-wave's report call out honestly
        // rather than silently papering over with a fabricated "original".
        if ($engineOn && $status === 'reissued') {
            $original = $task->originalTask;

            if ($original !== null && InvoiceDetail::where('task_id', $original->id)->exists()) {
                $this->reissue($original, $task);

                return;
            }

            $this->issue($task);

            return;
        }

        if ($engineOn && $status === 'emd') {
            $this->postEmdAncillary($task);

            return;
        }

        // W6.V (w6-brief.md "Model"/"Kinds" 1-2): a `void`-status task, engine ON, now routes
        // through {@see self::void()} instead of the legacy processTaskFinancial()/processVoidTask()
        // pair. As-is (ct-void-map.md §1/§2), a void EVENT commonly arrives as its OWN, SEPARATE
        // Task row (imported by AirFileParser's `;VOID` code / TaskWebhook / Magic's `XX`/`XP`),
        // carrying `original_task_id` pointing at the ticket actually being voided -- the row
        // dispatchFinancial() is called on is NOT itself the ticket to reverse in that case. A
        // UI-initiated void action (W6.U, not this sub-wave's own scope), by contrast, calls
        // void() directly on the ticket task itself, with no separate void-row at all. This branch
        // covers the FIRST shape (import-driven): resolve the task actually being voided --
        // `$task->originalTask` when this row is itself a linked void-event row, else `$task`
        // itself (so calling dispatchFinancial() on the ticket task directly, e.g. from
        // ProcessVoidTasksFinancials once it revisits an ALREADY-void-status task, is also safe) --
        // and void() itself carries its OWN idempotency guard (`ticket_status === 'void'`
        // short-circuit), so a redundant dispatch here is always a safe no-op.
        if ($engineOn && $status === 'void') {
            $target = $task->originalTask ?? $task;

            if ($target === null) {
                Log::error('task_status.void_unlinked', [
                    'task_id' => $task->id,
                    'company_id' => $companyId,
                ]);

                return;
            }

            $this->void($target, ['sub_type' => 'VOID', 'triggering_task_id' => $task->id]);

            return;
        }

        app(TaskController::class)->processTaskFinancial($task);
    }

    /**
     * W6.I "Importer contract" item 1 (w6-brief.md; Accounting Gap/22-plan-amendments.md §16.1).
     * The single entry point every import path that lands a task at `status=issued` -- and, per
     * {@see self::dispatchFinancial()}'s FIX ROUND 2, an import-time `status=reissued` task too --
     * now calls (via {@see self::dispatchFinancial()}, when the posting engine is ON for the
     * task's company) instead of `TaskController::processTaskFinancial()`. Builds ONE atomic sale
     * document (client receivable + supplier payable + revenue/margin, per the task's own
     * `service_type.posting_basis`) plus the commission JV, and an UNCONDITIONAL server-numbered
     * invoice -- AR stays open until a real receipt applies -- by delegating to
     * `InvoiceController::autoGenerateInvoice()` with `$payment=null` (see that method's own
     * W6.I fix-round docblock for exactly how it handles the no-payment case; this method does
     * not duplicate that logic, it is the one caller responsible for deciding WHICH invoice the
     * task lands on).
     *
     * `invoice_grouping=per_pnr` (the shipped default): resolves an already-open invoice for
     * another task sharing this task's own booking reference/PNR (see
     * {@see self::findGroupingInvoice()}) and passes it through so `autoGenerateInvoice()` appends
     * this task as one more `InvoiceDetail` line rather than minting a second invoice header --
     * "all passengers sharing one AIR reference/PNR land on one invoice." `per_task`/
     * `per_passenger`/`per_day` all preserve today's one-task-one-invoice shape (no grouping
     * performed here); `per_day`'s own multi-day/multi-task combining stays `RunAutoBilling`'s
     * separate, manual/opt-in job, untouched by this method.
     *
     * Idempotent by construction: `autoGenerateInvoice()`'s own `InvoiceDetail::where('task_id',
     * ...)->lockForUpdate()` guard (pre-existing, W3a) makes a second call for an already-invoiced
     * task a safe no-op regardless of who calls it or how many times -- this is also what makes
     * the four pre-existing payment-first callers of `autoGenerateInvoice()`
     * (`TaskController.php` store(), `PaymentController.php`, `ConfirmBookingAfterPaymentJob.php`,
     * `TaskWebhook.php`) idempotent no-ops once THIS method has already invoiced the task, with
     * zero changes needed at any of those call sites.
     *
     * The supplier-charge-rule call site (`SupplierChargeRuleResolver`/`SupplierChargeLineBuilder`,
     * W6.C) is deliberately NOT wired into this method: `SaleDraftBuilder::buildLines()` (called
     * from inside `addJournalEntry()`) has no parameter for extra `LineDraft[]`, and threading one
     * through would mean editing `addJournalEntry()`'s/`postSaleJournalEntries()`'s own signatures
     * -- real surgery on the shared feeder every other sale-posting call site in
     * `InvoiceController` also depends on, correctly judged out of this sub-wave's "surgical edits
     * only" mandate. Flagged as a real, reported gap in this sub-wave's own build report, not
     * silently skipped: a company with active `supplier_charge_rules` today does not yet see them
     * applied at import-time auto-invoicing through this path.
     *
     * @return array The same shape {@see \App\Http\Controllers\InvoiceController::
     *               autoGenerateInvoice()} returns (`success`, `message`, `invoice_id`, or the
     *               idempotent-retry shape) -- callers that care about the outcome (tests, a
     *               future W6.U follow-up action) read this directly rather than this method
     *               inventing a second response shape.
     */
    public function issue(Task $task, ?Payment $payment = null): array
    {
        $companyId = (int) $task->company_id;

        $groupInvoice = $this->invoiceGrouping($companyId) === 'per_pnr'
            ? $this->findGroupingInvoice($task)
            : null;

        $result = app(InvoiceController::class)->autoGenerateInvoice($task, $payment, $groupInvoice);

        if (($result['success'] ?? false) !== true) {
            Log::error('task_status.issue_failed', [
                'task_id' => $task->id,
                'company_id' => $companyId,
                'result' => $result,
            ]);

            return $result;
        }

        // FIX ROUND (previous verify finding, CONFIRMED): w6-brief.md "Hold/confirmed follow-up
        // lifecycle" item 3 states verbatim "On issue() (W6.I), the advance is auto-applied to
        // the newly created invoice through the existing apply/allocation engine" -- this call
        // was entirely missing (zero references to any apply/allocation mechanism inside issue()
        // before this fix round). Runs unconditionally (idempotent by construction -- see
        // applyHoldDepositToInvoice()'s own docblock) whether this call just minted/appended the
        // invoice or was itself an idempotent retry, and regardless of whether $payment is set:
        // it only ever touches InvoiceReceipt rows this task's own on-hold/confirmed deposit
        // left `invoice_id`-unlinked, so a task with no deposit is a guaranteed no-op.
        if (! empty($result['invoice_id'])) {
            $this->applyHoldDepositToInvoice($task, (int) $result['invoice_id'], $companyId);
        }

        return $result;
    }

    /**
     * W6.S "Hold/confirmed follow-up lifecycle" item 3 (w6-brief.md), FIX ROUND -- the half of
     * that item the previous verify round found entirely unbuilt: "On issue() (W6.I), the
     * advance is auto-applied to the newly created invoice through the existing apply/allocation
     * engine (same mechanism W6.R uses to re-apply receipts on reissue)."
     *
     * A deposit taken while the task was `on hold`/`confirmed` is an `InvoiceReceipt` row
     * ({@see \App\Http\Controllers\ReceiptVoucherController}'s CREDIT/`task_id` shape, see that
     * class's own W6.S fix-round docblock) with `invoice_id` still NULL -- the receipt was posted
     * (Dr instrument / Cr `CLIENT_ADVANCE` 2632, `journal_entries.task_id` tagged) before any
     * invoice existed to allocate it against. Once {@see self::issue()} mints or appends this
     * task onto a real invoice, this method posts ONE additional JV -- Dr `CLIENT_ADVANCE` (2632)
     * / Cr `RECEIVABLE_CONTROL` -- moving that SAME pre-existing 2632 balance onto this invoice's
     * AR. This is the identical debit/credit shape
     * {@see \App\Services\Accounting\CreditApplicationDraftBuilder} already uses for "apply an
     * already-received client credit against an invoice" (that class is not called directly here:
     * it is keyed on {@see \App\Services\Accounting\CreditApplicationInput}/an `App\Models\Credit`
     * row, and this deposit has neither -- only an `InvoiceReceipt`/`task_id` pair; see that
     * class's own docblock for why a caller-supplied total must reconcile against a Credit-backed
     * application list, which does not fit a raw task-deposit sum).
     *
     * Only ever invoked from {@see self::issue()}, which {@see self::dispatchFinancial()} only
     * calls when the posting engine is confirmed ON for this task's company -- so, exactly like
     * every other void()/reissue() helper in this class, this method posts straight through
     * {@see PostingService::post()}, never {@see PostingSeam}: there is no OFF-path/legacy
     * behaviour to preserve for a feature (task-tagged hold deposits) that did not exist before
     * this sub-wave at all.
     *
     * Idempotent by construction, not by a bare idempotency-key check alone: the `InvoiceReceipt`
     * rows this method consumes are selected `whereNull('invoice_id')` and are stamped with this
     * invoice's id before returning, so a second call (a retry, or a second import event for a
     * task that already had its deposit applied) finds nothing left to apply and returns null
     * immediately. The document's own idempotency key (`deposit_apply:{task_id}`) is a second,
     * independent guard at the engine layer.
     *
     * Deliberately narrow, and honestly reported rather than silently over-engineered: applies at
     * most `min(deposit sum, this task's own invoice_detail task_price)`. A deposit LARGER than
     * the invoice line this task lands on (a genuine overpay case) still has every one of its
     * rows stamped with this invoice's id (so a future re-query never double-applies it against a
     * second invoice), but the JV itself only ever moves the capped amount -- splitting a single
     * `InvoiceReceipt` row's excess into a fresh, still-open unapplied remainder row is a real,
     * left-open gap for a future sub-wave (see this fix round's own build report), not something
     * silently mishandled: the excess is never posted as revenue, it is simply not represented as
     * a separately-tracked open credit balance until that follow-up ships.
     *
     * ── W6.U2 fix round (w6u-verify-2.md finding 1, BLOCKING) ──────────────────────────────────
     * Before this fix, the JV above was the ONLY record of "this deposit is now spent" -- nothing
     * marked the consumed `InvoiceReceipt` rows as such, so {@see self::depositHeld()} kept
     * summing them forever, and {@see self::voidDisposition()} (via {@see self::
     * paidAmountForTask()}) disposed of the SAME amount a second time on a later void. Two things
     * now make the consumption durable and visible exactly once, both additive:
     *   (a) each consumed row is stamped `applied_at`/`applied_transaction_id` (this JV's
     *       transaction) -- {@see self::depositHeld()} now excludes them (`whereNull('applied_at')`),
     *       so an applied deposit is invisible to the "still on hold, unconsumed" reading.
     *   (b) an `InvoicePartial` row is created for the CAPPED `$applyAmount` (never the raw,
     *       possibly-larger deposit sum -- see the paragraph above), the SAME durable "how much of
     *       this invoice has been paid" row {@see \App\Http\Controllers\ReceiptVoucherController::
     *       applyAllocationsToInvoices()} already creates for every other payment mechanism in this
     *       codebase (`invoicePartial_id` is stamped back onto each consumed `InvoiceReceipt` row --
     *       the column already existed on this table, unused by this method before this fix).
     *       {@see self::paidAmountForTask()} reads this row (never `depositHeld()`) to recover the
     *       consumed amount exactly once at void time.
     */
    private function applyHoldDepositToInvoice(Task $task, int $invoiceId, int $companyId): ?PostedDocument
    {
        $depositRows = InvoiceReceipt::where('task_id', $task->id)
            ->where('status', InvoiceReceipt::STATUS_APPROVED)
            ->whereNull('invoice_id')
            ->lockForUpdate()
            ->get();

        if ($depositRows->isEmpty()) {
            return null;
        }

        $depositAvailable = round((float) $depositRows->sum('amount'), 3);

        if ($depositAvailable <= 0.0005) {
            return null;
        }

        $invoiceDetail = InvoiceDetail::where('task_id', $task->id)
            ->where('invoice_id', $invoiceId)
            ->first();

        if ($invoiceDetail === null) {
            Log::warning('task_status.issue_deposit_apply_missing_invoice_detail', [
                'task_id' => $task->id,
                'invoice_id' => $invoiceId,
            ]);

            return null;
        }

        $invoice = Invoice::find($invoiceId);

        if ($invoice === null) {
            return null;
        }

        $applyAmount = round(min($depositAvailable, (float) $invoiceDetail->task_price), 3);

        if ($applyAmount <= 0.0005) {
            return null;
        }

        $currency = $invoice->currency ?: (string) config('accounting.engine.base_currency');
        $narration = sprintf('Deposit applied to invoice %s (task #%d)', $invoice->invoice_number, $task->id);
        $userId = Auth::id();

        $lines = [
            new LineDraft(
                purposeCode: 'CLIENT_ADVANCE',
                accountId: null,
                side: 'debit',
                amount: $applyAmount,
                currency: $currency,
                originalAmount: $applyAmount,
                exchangeRate: 1.0,
                transactionType: 'CUSTOMERDEBITED',
                description: $narration,
                partyAccountRef: $task->client_id,
                invoiceId: $invoice->id,
                invoiceDetailId: $invoiceDetail->id,
                taskId: $task->id,
                ledgerType: 'payable',
                partyName: $task->client_name,
            ),
            new LineDraft(
                purposeCode: 'RECEIVABLE_CONTROL',
                accountId: null,
                side: 'credit',
                amount: $applyAmount,
                currency: $currency,
                originalAmount: $applyAmount,
                exchangeRate: 1.0,
                transactionType: 'CUSTOMERCREDITED',
                description: $narration,
                partyAccountRef: $task->client_id,
                invoiceId: $invoice->id,
                invoiceDetailId: $invoiceDetail->id,
                taskId: $task->id,
                ledgerType: 'receivable',
                partyName: $task->client_name,
            ),
        ];

        $draft = new DocumentDraft(
            companyId: $companyId,
            branchId: (int) ($task->agent?->branch_id ?? 0),
            docType: 'JV',
            subType: 'DEPOSIT_APPLY',
            docDate: Carbon::now(),
            narration: $narration,
            lines: $lines,
            idempotencyKey: 'deposit_apply:'.$task->id,
            sourceType: 'Receipt',
            sourceId: $depositRows->first()->id,
            invoiceId: $invoice->id,
            userId: $userId,
        );

        $posted = app(PostingService::class)->post($draft, $userId);

        // W6.U2 fix (see this method's own docblock): ONE InvoicePartial for the CAPPED
        // $applyAmount, mirroring ReceiptVoucherController::applyAllocationsToInvoices()'s own
        // column shape verbatim -- this is the durable "how much of this invoice has been paid"
        // row every other read site in this codebase already relies on
        // ($invoice->invoicePartials(), ClientController.php:526, InvoiceController's own
        // refund/PDF/dashboard views). A SINGLE partial row (not one per deposit row) keeps its
        // own sum exactly equal to $applyAmount regardless of how many separate deposit receipts
        // fed it, which is exactly what {@see self::paidAmountForTask()} needs to read back.
        $partial = InvoicePartial::create([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'client_id' => $invoice->client_id,
            'service_charge' => 0,
            'amount' => $applyAmount,
            'status' => 'paid',
            'type' => 'full',
            'payment_gateway' => 'Deposit',
            'receipt_voucher_id' => $depositRows->first()->id,
        ]);

        foreach ($depositRows as $row) {
            $row->invoice_id = $invoiceId;
            $row->applied_at = Carbon::now();
            $row->applied_transaction_id = $posted->transaction->id;
            $row->invoice_partial_id = $partial->id;
            $row->save();
        }

        $invoiceDetail->paid = $applyAmount >= round((float) $invoiceDetail->task_price, 3) - 0.0005;
        $invoiceDetail->save();

        if ($invoiceDetail->paid && $invoice->status === 'unpaid') {
            $otherUnpaid = InvoiceDetail::where('invoice_id', $invoiceId)
                ->where('id', '!=', $invoiceDetail->id)
                ->where('paid', false)
                ->exists();

            if (! $otherUnpaid) {
                $invoice->status = 'paid';
                $invoice->paid_date = Carbon::now();
                $invoice->save();
            }
        }

        $this->recordEvent('deposit_applied', $companyId, $task->id, $task->status, $task->status, null, null, [
            'invoice_id' => $invoiceId,
            'amount_applied' => $applyAmount,
            'deposit_available' => $depositAvailable,
        ]);

        Log::info('task_status.deposit_applied', [
            'task_id' => $task->id,
            'company_id' => $companyId,
            'invoice_id' => $invoiceId,
            'amount_applied' => $applyAmount,
        ]);

        return $posted;
    }

    /**
     * W6.I item 1's `invoice_grouping` company option -- `per_task|per_pnr|per_passenger|per_day`,
     * default `per_pnr` per the brief. An unrecognised stored value falls back to the shipped
     * default rather than throwing (same defensive convention every other company-option reader
     * in this class already uses).
     */
    public function invoiceGrouping(int $companyId): string
    {
        $value = (string) $this->companyOption($companyId, 'accounting.invoice_grouping', 'per_pnr');

        return in_array($value, ['per_task', 'per_pnr', 'per_passenger', 'per_day'], true) ? $value : 'per_pnr';
    }

    /**
     * `invoice_grouping=per_pnr` support -- finds an already-existing invoice carrying another
     * task with the SAME `reference` (the AIR booking reference/PNR) in the SAME company, so
     * {@see self::issue()} can append this task to it instead of minting a second invoice header.
     * Deliberately does not filter by invoice `status` (an invoice already marked `paid` for one
     * passenger of a multi-passenger PNR can still legitimately gain another passenger's line --
     * `autoGenerateInvoice()`'s own grouping branch only flips status when a NEW payment is
     * attached, never strips an existing one). Returns null (no reuse -- a fresh invoice is
     * minted) when no other task shares this reference yet, or when the task has no reference at
     * all (never groups on an empty string).
     */
    private function findGroupingInvoice(Task $task): ?Invoice
    {
        if (empty($task->reference)) {
            return null;
        }

        return Invoice::whereHas('invoiceDetails.task', function ($query) use ($task) {
            $query->where('reference', $task->reference)
                ->where('company_id', $task->company_id)
                ->where('id', '!=', $task->id);
        })->orderBy('id')->first();
    }

    /**
     * W6.I "Importer contract" item 2 (w6-brief.md). `emd` no longer rewrites to `issued`
     * (W6.S already deleted that rewrite in `TaskController::store()`/`TaskWebhook::
     * applyStatusMapping()`) -- this method is what gives a genuine `emd` task its own posting:
     * ONE ancillary sale line on the PARENT ticket's EXISTING invoice, never a new invoice, per
     * IFRS 15 §22 (ancillary services are a separate performance obligation, distinct from the
     * transportation the parent ticket already recognised).
     *
     * Parent resolution: `original_task_id`, already set at import time by
     * {@see self::linkOriginalTask()} (called from `TaskController::store()`/`TaskWebhook::
     * linkOriginalTask()` BEFORE financial dispatch ever runs) -- this method does not re-run the
     * PNR/passenger match itself, it only reads the link `linkOriginalTask()` already made. When
     * no parent was found at import (or the parent itself was never invoiced -- e.g. a stray EMD
     * for a ticket this company never actually booked), this method NEVER falls back to posting
     * the EMD as an ordinary standalone issued task -- it writes an audit row and a warning log,
     * flagging the task for manual link, exactly as the brief requires ("never silently fall back
     * to issued").
     *
     * Posting shape: `Dr RECEIVABLE_CONTROL / Cr SERVICE_PAYABLE (parent's own service type, when
     * the EMD carries a real supplier cost) / Cr EMD_ANCILLARY_REVENUE (margin, or the full sell
     * when there is no supplier cost)` -- built by hand here rather than via
     * `SaleDraftBuilder::buildAgentBasisLines()`, because that method emits its `SERVICE_PAYABLE`
     * leg UNCONDITIONALLY even when `costAmount` is 0 and `PostingService::post()` rejects any
     * zero-amount line outright -- a real, pre-existing gap in `SaleDraftBuilder` for any
     * zero-supplier-cost agent-basis sale (most EMD ancillaries, e.g. a pure seat/baggage fee,
     * have none) that this method works around locally rather than editing the shared builder
     * every other agent-basis feeder in this codebase also depends on. Flagged in this sub-wave's
     * own build report as a `SaleDraftBuilder` gap for the owner, not fixed here.
     *
     * New leaf: `EMD_ANCILLARY_REVENUE` (global purpose code, resolves to CoaSeeder code 4138
     * "EMD Ancillary Revenue" under "Commission & Service Fee Income" -- see that seeder's own
     * comment). The supplier-cost leg, when present, reuses the PARENT task's own
     * `SERVICE_PAYABLE`/{type} leaf (the ancillary is charged by the SAME supplier family as the
     * ticket it belongs to) rather than minting a `SERVICE_PAYABLE`/emd leaf nobody else would
     * ever post to.
     */
    public function postEmdAncillary(Task $task): void
    {
        $companyId = (int) $task->company_id;

        if (InvoiceDetail::where('task_id', $task->id)->exists()) {
            // Already posted -- a re-dispatch (e.g. a webhook redelivery) must never double-post
            // the same ancillary line twice.
            return;
        }

        $parent = $task->original_task_id ? Task::find($task->original_task_id) : null;

        if ($parent === null) {
            $this->recordEvent('emd_unlinked', $companyId, $task->id, $task->status, $task->status, null, null, [
                'reference' => $task->reference,
                'passenger_name' => $task->passenger_name,
            ]);

            Log::warning('task_status.emd_unlinked', [
                'task_id' => $task->id,
                'company_id' => $companyId,
                'reference' => $task->reference,
            ]);

            return;
        }

        $parentInvoiceDetail = InvoiceDetail::where('task_id', $parent->id)->first();

        if ($parentInvoiceDetail === null) {
            $this->recordEvent('emd_parent_not_invoiced', $companyId, $task->id, $task->status, $task->status, null, null, [
                'original_task_id' => $parent->id,
            ]);

            Log::warning('task_status.emd_parent_not_invoiced', [
                'task_id' => $task->id,
                'original_task_id' => $parent->id,
            ]);

            return;
        }

        $invoice = Invoice::find($parentInvoiceDetail->invoice_id);
        $agent = $task->agent;

        if ($invoice === null || $agent === null) {
            Log::error('task_status.emd_ancillary_missing_context', [
                'task_id' => $task->id,
                'invoice_id' => $parentInvoiceDetail->invoice_id ?? null,
                'agent_id' => $task->agent_id,
            ]);

            return;
        }

        $sell = round((float) ($task->price ?? 0.0), 3);
        $cost = round((float) ($task->total ?? 0.0), 3);
        $tolerance = (float) config('accounting.engine.balance_tolerance', 0.0005);

        if ($sell <= $tolerance) {
            Log::warning('task_status.emd_ancillary_zero_sell', ['task_id' => $task->id]);

            return;
        }

        $invoiceDetail = InvoiceDetail::create([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'task_id' => $task->id,
            'task_description' => $task->description,
            'task_remark' => $task->remark,
            'client_notes' => $task->notes,
            'task_price' => $sell,
            'supplier_price' => $cost,
            'markup_price' => $sell - $cost,
            'paid' => false,
        ]);

        $invoice->sub_amount = (float) $invoice->sub_amount + $sell;
        $invoice->amount = (float) $invoice->amount + $sell;
        $invoice->save();

        $currency = (string) config('accounting.engine.base_currency');
        $lines = [
            new LineDraft(
                purposeCode: 'RECEIVABLE_CONTROL',
                accountId: null,
                side: 'debit',
                amount: $sell,
                currency: $currency,
                originalAmount: $sell,
                exchangeRate: 1.0,
                transactionType: 'CUSTOMERDEBITED',
                partyAccountRef: $task->client_id,
                description: 'EMD ancillary charge for '.$task->reference.' (parent #'.$parent->id.')',
                invoiceId: $invoice->id,
                invoiceDetailId: $invoiceDetail->id,
                taskId: $task->id,
                ledgerType: 'receivable',
                partyName: $task->client_name,
            ),
        ];

        if ($cost > $tolerance) {
            $lines[] = new LineDraft(
                purposeCode: 'SERVICE_PAYABLE',
                accountId: null,
                side: 'credit',
                amount: $cost,
                currency: $currency,
                originalAmount: $cost,
                exchangeRate: 1.0,
                transactionType: 'SUPPLIERCREDITED',
                partyAccountRef: $task->supplier_id,
                description: 'EMD ancillary cost owed to supplier for '.$task->reference,
                serviceType: (string) $parent->type,
                invoiceId: $invoice->id,
                invoiceDetailId: $invoiceDetail->id,
                taskId: $task->id,
                ledgerType: 'payable',
            );
        }

        $margin = round($sell - $cost, 3);

        if (abs($margin) > $tolerance) {
            $lines[] = new LineDraft(
                purposeCode: 'EMD_ANCILLARY_REVENUE',
                accountId: null,
                side: $margin > 0 ? 'credit' : 'debit',
                amount: abs($margin),
                currency: $currency,
                originalAmount: abs($margin),
                exchangeRate: 1.0,
                transactionType: $margin > 0 ? 'INCOME' : 'CONTRA_INCOME',
                description: 'EMD ancillary income for '.$task->reference,
                invoiceId: $invoice->id,
                invoiceDetailId: $invoiceDetail->id,
                taskId: $task->id,
                ledgerType: 'income',
            );
        }

        $draft = new DocumentDraft(
            companyId: $companyId,
            branchId: (int) ($agent->branch_id ?? 0),
            docType: 'INV',
            subType: 'EMD_ANCILLARY',
            docDate: Carbon::now(),
            narration: 'EMD ancillary charge for '.$task->reference.' (parent task #'.$parent->id.')',
            lines: $lines,
            idempotencyKey: 'task:'.$task->id.':emd-ancillary',
            invoiceId: $invoice->id,
        );

        $legacy = function () use ($task) {
            // OFF path: {@see self::dispatchFinancial()} only calls this method when the engine
            // is ON, so this closure only ever runs on PostingSeam's own narrow
            // engine_disabled_race window (both flags read ON a moment ago, flipped OFF by the
            // time PostingService::post() re-checked) -- see PostingSeam::post()'s own docblock.
            // There is no legacy EMD-ancillary poster to fall back to (the old emd->issued
            // rewrite this sub-wave's prerequisite, W6.S, already deleted was itself wrong -- a
            // brand-new unlinked payable+cost pair, not an ancillary line); this logs the race
            // rather than posting anything incorrect.
            Log::warning('task_status.emd_ancillary_off_path_race', ['task_id' => $task->id]);

            return null;
        };

        app(PostingSeam::class)->post($draft, $legacy, 'task.emd_ancillary');

        $this->recordEvent('emd_ancillary_posted', $companyId, $task->id, $task->status, $task->status, null, null, [
            'parent_task_id' => $parent->id,
            'invoice_id' => $invoice->id,
            'sell' => $sell,
            'cost' => $cost,
        ]);
    }

    /**
     * W6.V (w6-brief.md "Model" + "Kinds" 1-3 + "Commission" + "Preconditions"). Voids ONE task
     * that was actually issued/invoiced -- $task is always the TICKET being voided (the ORIGINAL,
     * never a separate import-linked void-event row; see {@see self::dispatchFinancial()}'s own
     * `void` branch for how that row gets resolved down to the ticket before this method is ever
     * called).
     *
     * ── Architecture note: why "ticket leg" + "client leg" collapse into ONE reverse() call ──────
     * w6-brief.md's own "Model" section describes two independent legs -- a supplier-side "ticket
     * leg" (reverse the cost/payable doc) and a client-side "client leg" (CRN reverse of the sale
     * lines). Under W3d's locked posting-basis decision, however, a task's sale is always ONE
     * atomic document (`SaleDraftBuilder::buildLines()`, keyed `invoice-detail:{id}:sale` --
     * confirmed by reading that class and {@see \App\Services\Accounting\RefundPostingService::
     * postCrnForDetail()}, which reverses the exact same single document for the refund case) --
     * client receivable, supplier payable, and margin all live on the SAME transaction. Reversing
     * that one document therefore reverses BOTH legs in a single, structurally-atomic step; this
     * method does not (and cannot, without inventing a second, non-existent document) reverse them
     * separately. `transactions.bsptype='VOID'` is stamped on the resulting reversal afterwards
     * (bsptype has no DocumentDraft carrier -- see migration 2026_08_27_130003's own note --
     * exactly the same direct-update convention RefundPostingService uses for its own `REFUND`
     * stamp).
     *
     * ── Idempotency (w6-brief.md "Preconditions": "Idempotent: engine keys void:{task_id}") ──────
     * The TOP-LEVEL guard is `$task->ticket_status === 'void'` (checked first, under a row lock --
     * see below): a task already voided short-circuits to a no-op summary immediately, before any
     * document is touched. Underneath that: the core sale reversal is idempotent by construction
     * (`PostingService::reverse()`'s own existing-reversal check, no separate key needed); the
     * satellite documents this method mints itself (fee DBN, fee-commission JV, disposition JV/PV)
     * each carry their own `void:{task_id}:{doc_type}` idempotency key, exactly mirroring
     * RefundPostingService's `refund:{id}:{doc_type}` namespacing convention. Together these make
     * re-running ANY caller (a redelivered webhook, ProcessVoidTasksFinancials re-processing the
     * same row, a duplicated bulk-void request) on an already-voided task a safe no-op.
     *
     * ── Preconditions (w6-brief.md "Preconditions") ────────────────────────────────────────────
     *   - No reconciled line on the sale document being reversed: `PostingService::reverse()`
     *     itself throws {@see ProtectedLineException} for a reconciled line; caught here and
     *     re-thrown as a plain, actionable \RuntimeException pointing at the refund flow (W4),
     *     exactly as the brief requires ("refuse with message pointing to refund flow instead").
     *   - `is_locked` (per-record, on the carrying INVOICE -- the same lock InvoiceController's own
     *     `checkLocked()` enforces, reused here as a plain boolean check via
     *     `Gate::check('manageLocks', ...)` rather than that controller-bound helper, which returns
     *     an HTTP response this service layer has no use for).
     *   - `ticket_status` must be `issued`/`reissued`. KNOWN GAP (reported, not fixed here): W6.I's
     *     `issue()`/`postEmdAncillary()` never stamp `ticket_status` on a freshly-issued task (grep
     *     confirms -- only the W6.S migration's one-time backfill ever wrote it, for PRE-EXISTING
     *     rows). A task issued strictly AFTER that migration therefore has `ticket_status = NULL`
     *     forever unless something else sets it. Rather than block void() from ever working on any
     *     task issued after this sub-wave (which would make the precondition meaningless for the
     *     one thing it is meant to protect), {@see self::normalizeTicketStatus()} opportunistically
     *     derives and stamps `ticket_status='issued'`/`client_status='open'` from the LEGACY
     *     `status` column the first time a null is seen, self-healing the gap as a side effect --
     *     flagged here for W6.I to fix at the source (stamping `ticket_status` inside `issue()`
     *     itself) rather than papering over it a second time in a future sub-wave.
     *   - Void only from `ticket_status` issued|reissued (post-normalization).
     *
     * @param  array{sub_type?: string, fee?: float|null, user_id?: int|null,
     *               triggering_task_id?: int|null} $opts
     * @return array{idempotent: bool, ticket_status: string, invoice_status: ?string,
     *               crn: ?PostedDocument,
     *               fee: ?PostedDocument,
     *               commission_unearn: ?PostedDocument,
     *               fee_commission: ?PostedDocument,
     *               disposition: ?PostedDocument}
     */
    public function void(Task $task, array $opts = []): array
    {
        $subType = (string) ($opts['sub_type'] ?? 'VOID');

        if (! in_array($subType, ['VOID', 'AUTO_VOID'], true)) {
            throw new \InvalidArgumentException(
                "TaskStatusService::void(): sub_type must be 'VOID' or 'AUTO_VOID', got '{$subType}'."
            );
        }

        $userId = $opts['user_id'] ?? Auth::id();
        $feeOverride = array_key_exists('fee', $opts) ? $opts['fee'] : null;

        return DB::transaction(function () use ($task, $subType, $userId, $feeOverride) {
            /** @var Task $task */
            $task = Task::withoutGlobalScopes()->whereNull('deleted_at')->lockForUpdate()->findOrFail($task->id);
            $companyId = (int) $task->company_id;

            if ($task->ticket_status === 'void') {
                // Top-level idempotency short-circuit -- see method docblock.
                return [
                    'idempotent' => true,
                    'ticket_status' => 'void',
                    'invoice_status' => $task->invoiceDetail?->invoice?->status,
                    'crn' => null,
                    'fee' => null,
                    'commission_unearn' => null,
                    'fee_commission' => null,
                    'disposition' => null,
                ];
            }

            // CT-A3 wave 1, feeder E-iss (owner ruling 2026-09-09): "Reversal on task
            // cancel/void". A task voided BEFORE it was ever invoiced still carries its issuance
            // accrual (Dr 1430 / Cr SERVICE_PAYABLE) — the agency no longer owes the supplier, so
            // the accrual comes off here, through PostingService::reverse(). A no-op when the
            // task was never accrued (already invoiced, supplier on hold, trigger not reached),
            // so it is safe on every void path. Deliberately BEFORE the sale reversal below: the
            // two documents are independent and reversing the accrual first keeps the AP control
            // monotonic for anyone watching it during the transaction.
            app(TaskIssuancePayableService::class)->reverseForTask($task);

            $this->normalizeTicketStatus($task);
            $this->assertVoidPreconditions($task);

            $invoiceDetail = $task->invoiceDetail;
            $invoice = $invoiceDetail?->invoice;
            $posting = app(PostingService::class);
            $docDate = Carbon::now();

            $crn = $this->voidReverseSale($task, $invoiceDetail, $posting, $docDate, $userId);
            $fee = $this->voidPostFee($task, $invoiceDetail, $invoice, $companyId, $docDate, $userId, $feeOverride, $posting);
            $unearn = $this->voidCommissionUnearn($task, $invoiceDetail, $companyId, $docDate, $userId, $posting);
            $feeCommission = $this->voidFeeCommission($task, $fee, $companyId, $docDate, $userId, $posting);
            $disposition = $this->voidDisposition($task, $invoiceDetail, $fee, $companyId, $docDate, $userId, $posting);

            $oldStatus = $task->status;
            $task->ticket_status = 'void';
            $task->client_status = 'credited';
            $task->status = 'void';
            $task->save();

            $invoiceStatus = $this->refreshInvoiceStatusAfterVoid($invoice?->id, $companyId);

            $this->recordEvent($subType === 'AUTO_VOID' ? 'auto_void' : 'void', $companyId, $task->id, $oldStatus, 'void', null, null, [
                'fee_posted' => $fee !== null,
                'commission_unearned' => $unearn !== null,
                'invoice_status' => $invoiceStatus,
            ]);

            Log::info('task_status.voided', [
                'task_id' => $task->id,
                'company_id' => $companyId,
                'sub_type' => $subType,
                'invoice_status' => $invoiceStatus,
            ]);

            return [
                'idempotent' => false,
                'ticket_status' => 'void',
                'invoice_status' => $invoiceStatus,
                'crn' => $crn,
                'fee' => $fee,
                'commission_unearn' => $unearn,
                'fee_commission' => $feeCommission,
                'disposition' => $disposition,
            ];
        });
    }

    /**
     * Self-heals the {@see self::void()} docblock's own reported W6.I gap: `ticket_status`/
     * `client_status` are stamped from the legacy `status` column the first time a NULL is seen on
     * a task that is actually issued/reissued, so the precondition below has something real to
     * check for every task, not only the ones the one-time migration backfill happened to cover.
     * A no-op for a task whose `ticket_status` is already non-null (never overwrites a value
     * something else already set).
     *
     * Also covers {@see self::autoVoidExpiredInvoiced()}'s own Kind 2 case: a task whose LEGACY
     * `status` is still `on hold`/`confirmed` but which nonetheless already carries an
     * `invoiceDetail` (i.e. it genuinely WAS issued/invoiced -- the brief's own "invoiced ->
     * AUTO_VOID through the service" case) is normalized to `ticket_status='issued'` from the
     * invoiceDetail's existence, not from the stale `status` column, so void()'s own precondition
     * does not wrongly refuse a task the brief explicitly requires it to accept.
     */
    private function normalizeTicketStatus(Task $task): void
    {
        if ($task->ticket_status !== null) {
            return;
        }

        $isIssuedByLegacyStatus = in_array($task->status, ['issued', 'reissued'], true);
        $isIssuedByInvoiceDetail = InvoiceDetail::where('task_id', $task->id)->exists();

        if ($isIssuedByLegacyStatus || $isIssuedByInvoiceDetail) {
            $task->ticket_status = in_array($task->status, ['issued', 'reissued'], true) ? $task->status : 'issued';
            $task->client_status = $task->client_status ?? 'open';
            $task->save();
        }
    }

    /**
     * w6-brief.md "Preconditions": reconciled-line refusal, per-record `is_locked` refusal,
     * `ticket_status` in issued|reissued. Throws \RuntimeException (never silently skips) --
     * matching every other precondition-violation convention in this codebase's engine layer
     * (RefundPostingService's own status/applied-invoice guards).
     */
    private function assertVoidPreconditions(Task $task): void
    {
        if (! in_array($task->ticket_status, ['issued', 'reissued'], true)) {
            throw new \RuntimeException(
                "TaskStatusService::void(): task #{$task->id} has ticket_status='{$task->ticket_status}' -- "
                .'only an issued/reissued ticket can be voided.'
            );
        }

        $invoice = $task->invoiceDetail?->invoice;

        if ($invoice !== null && $invoice->is_locked && ! Gate::check('manageLocks', User::class)) {
            throw new \RuntimeException(
                "TaskStatusService::void(): task #{$task->id}'s carrying invoice #{$invoice->id} is locked -- "
                .'contact your accountant to unlock it before voiding.'
            );
        }

        // The reconciled-line check itself lives inside PostingService::reverse() (throws
        // ProtectedLineException) -- caught and translated at the actual reverse() call site
        // ({@see self::voidReverseSale()}) rather than duplicated here, since reverse() is the one
        // place that actually knows which lines are reconciled.
    }

    /**
     * Core reversal -- see {@see self::void()}'s own "Architecture note" for why this single
     * reverse() call covers BOTH the ticket leg and the client leg. A no-op (returns null) when no
     * engine-posted sale document exists for this task at all (a pre-engine-cutover legacy invoice,
     * or a task whose `ticket_status` was normalized from legacy data with nothing ever posted
     * through the seam) -- the rest of void() still proceeds (fee/commission/disposition remain
     * meaningful even with nothing on the ledger to reverse).
     */
    private function voidReverseSale(
        Task $task,
        ?InvoiceDetail $invoiceDetail,
        PostingService $posting,
        \DateTimeInterface $docDate,
        ?int $userId
    ): ?PostedDocument {
        if ($invoiceDetail === null) {
            return null;
        }

        $saleKey = 'invoice-detail:'.$invoiceDetail->id.':sale';
        $companyId = (int) $task->company_id;

        $saleTransaction = Transaction::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('company_id', $companyId)
            ->where('idempotency_key', $saleKey)
            ->first();

        if ($saleTransaction === null) {
            Log::info('task_status.void_no_sale_to_reverse', ['task_id' => $task->id]);

            return null;
        }

        try {
            $reversed = $posting->reverse($saleTransaction, $docDate, $userId);
        } catch (ProtectedLineException $e) {
            throw new \RuntimeException(
                "TaskStatusService::void(): task #{$task->id}'s sale document has a reconciled line and cannot ".
                'be voided -- use the refund flow (RefundController/RefundPostingService, W4) instead, which '.
                'supports a reconciled original sale.',
                previous: $e
            );
        }

        Transaction::withoutGlobalScopes()->whereKey($reversed->transaction->id)->update(['bsptype' => 'VOID']);

        return $reversed;
    }

    /**
     * VOID WITH FEE (w6-brief.md Kind 3): `Dr RECEIVABLE_CONTROL / Cr VOID_FEE_INCOME` (4134),
     * `reason_tag='fee'` stamped on both lines post-post (no DocumentDraft/LineDraft carrier for
     * `journal_entries.reason_tag` exists yet -- same direct-update convention as `bsptype`
     * above). `VOID_FEE_INCOME` is a GLOBAL purpose code (config/accounting.php's own
     * 'purpose_codes'=>'global' list) -- never passed a `serviceType`, exactly like
     * RefundPostingService's `SERVICE_FEE_INCOME`/`PENALTY_PASSTHROUGH_RECOVERY` lines (passing one
     * would make AccountResolver look for a service-scoped mapping that was never seeded).
     * A no-op (returns null) when the resolved fee is <= tolerance.
     */
    private function voidPostFee(
        Task $task,
        ?InvoiceDetail $invoiceDetail,
        ?Invoice $invoice,
        int $companyId,
        \DateTimeInterface $docDate,
        ?int $userId,
        ?float $feeOverride,
        PostingService $posting
    ): ?PostedDocument {
        $sellAmount = (float) ($invoiceDetail?->task_price ?? $task->price ?? 0.0);
        $fee = $this->resolveFeeFromSchedule($companyId, (string) $task->type, $sellAmount, $feeOverride);

        $tolerance = (float) config('accounting.engine.balance_tolerance', 0.0005);

        if ($fee <= $tolerance) {
            return null;
        }

        $currency = (string) config('accounting.engine.base_currency');

        $lines = [
            new LineDraft(
                purposeCode: 'RECEIVABLE_CONTROL',
                accountId: null,
                side: 'debit',
                amount: $fee,
                currency: $currency,
                originalAmount: $fee,
                exchangeRate: 1.0,
                transactionType: 'VOID_FEE_RECEIVABLE',
                partyAccountRef: $task->client_id,
                description: 'Void fee for '.$task->reference,
                invoiceId: $invoiceDetail?->invoice_id,
                invoiceDetailId: $invoiceDetail?->id,
                taskId: $task->id,
                ledgerType: 'receivable',
                partyName: $task->client_name,
            ),
            new LineDraft(
                purposeCode: 'VOID_FEE_INCOME',
                accountId: null,
                side: 'credit',
                amount: $fee,
                currency: $currency,
                originalAmount: $fee,
                exchangeRate: 1.0,
                transactionType: 'VOID_FEE_INCOME',
                description: 'Void fee income for '.$task->reference,
                invoiceId: $invoiceDetail?->invoice_id,
                invoiceDetailId: $invoiceDetail?->id,
                taskId: $task->id,
                ledgerType: 'income',
            ),
        ];

        $draft = new DocumentDraft(
            companyId: $companyId,
            branchId: (int) ($task->agent?->branch_id ?? 0),
            docType: 'DBN',
            subType: 'VOID_FEE',
            docDate: $docDate,
            narration: 'Void fee for '.$task->reference,
            lines: $lines,
            idempotencyKey: 'void:'.$task->id.':fee',
            invoiceId: $invoiceDetail?->invoice_id,
        );

        $posted = $posting->post($draft, $userId);

        JournalEntry::whereIn('id', collect($posted->lines)->pluck('id'))->update(['reason_tag' => 'fee']);

        return $posted;
    }

    /**
     * w6-brief.md "Kinds" 3: fee defaults from the company fee table -- REUSES W4's own
     * `accounting.refund.fee_schedule.{type}.override|.percent|.amount` options verbatim (the
     * brief's own words: "W4 refund_fee_* options reused"), never a second void-specific key
     * namespace. Mirrors RefundController::applyRefundFeeSchedule()'s own resolution order
     * (override='free' forces 0; else percent-of-sell beats a flat amount; else the caller-supplied
     * override figure, when the company has configured neither). `needs_approval` (the shipped
     * default override value) is honoured the SAME way applyRefundFeeSchedule() already does: it
     * does not itself gate posting here (a UI approval step, W6.U, is what enforces the approval
     * gate before this method is ever reached with a caller-supplied $callerFee) -- this method's
     * only job is resolving the AMOUNT. $sellAmount is the task's own sell price -- the base a
     * configured `percent` resolves against (mirrors applyRefundFeeSchedule()'s own
     * `$taskData['original_invoice_price']` base exactly).
     *
     * Shared with {@see self::reissuePostFee()} (W6.R): the brief's own "Options registered"
     * section lists `fee_table`/`refund_fee_override` once, reused by BOTH void and reissue fees
     * (only the destination leaf -- 4134 vs 4135 -- and the idempotency-key namespace differ)
     * rather than a second, void-specific option namespace -- hence this method's own
     * generic name (it is no longer void-specific).
     */
    private function resolveFeeFromSchedule(int $companyId, string $serviceType, float $sellAmount, ?float $callerFee): float
    {
        $callerFee = round((float) ($callerFee ?? 0.0), 3);

        if ($serviceType === '') {
            return $callerFee;
        }

        $override = (string) Setting::getByKey($companyId, "accounting.refund.fee_schedule.{$serviceType}.override", 'needs_approval');

        if ($override === 'free') {
            return 0.0;
        }

        $percent = (float) Setting::getByKey($companyId, "accounting.refund.fee_schedule.{$serviceType}.percent", 0);
        $amount = (float) Setting::getByKey($companyId, "accounting.refund.fee_schedule.{$serviceType}.amount", 0);

        if ($percent > 0) {
            return round($sellAmount * $percent / 100, 3);
        }

        if ($amount > 0) {
            return round($amount, 3);
        }

        return $callerFee;
    }

    /**
     * Commission un-earn (w6-brief.md "Commission": "Void -> commission un-earn JV ... per
     * commission_on_refunded_sale default un_earn (same option as W4)"). Targets the ORIGINAL
     * sale's own per-detail commission document STRUCTURALLY by its `invoice-detail:{id}:
     * agent-commission` idempotency key -- IDENTICAL mechanism and key to
     * RefundPostingService::postCommissionUnearnForDetail(), reused verbatim rather than
     * reimplemented, since it is the exact same event (reverse the commission earned on a sale that
     * no longer stands) under a different name. A no-op (null) when the policy is not 'un_earn', or
     * when no live commission document exists under that key (no commission was ever earned, or the
     * sale predates the engine).
     */
    private function voidCommissionUnearn(
        Task $task,
        ?InvoiceDetail $invoiceDetail,
        int $companyId,
        \DateTimeInterface $docDate,
        ?int $userId,
        PostingService $posting
    ): ?PostedDocument {
        $policy = (string) $this->companyOption($companyId, 'accounting.refund.commission_on_refunded_sale', 'un_earn');

        if ($policy !== 'un_earn' || $invoiceDetail === null) {
            return null;
        }

        $commissionKey = 'invoice-detail:'.$invoiceDetail->id.':agent-commission';

        $commissionTransaction = Transaction::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('company_id', $companyId)
            ->where('idempotency_key', $commissionKey)
            ->where('posting_status', 'posted')
            ->first();

        if ($commissionTransaction === null) {
            return null;
        }

        return $posting->reverse($commissionTransaction, $docDate, $userId);
    }

    /**
     * w6-brief.md "Commission": "Fee commission per commissionable_fee_types" -- a BRAND NEW
     * commission JV on the VOID FEE's own margin (never touching the original sale's own
     * commission key, so it can never collide with {@see self::voidCommissionUnearn()}'s reversal).
     * Mirrors RefundPostingService::postCommissionEarnForRefundDetail() exactly: gated by the SAME
     * `commissionable_fee_types` company option (JSON array of service types), commission =
     * `agent->commission * fee`. A no-op when there was no fee, the task's own service type is not
     * listed, or the computed commission rounds to zero.
     */
    private function voidFeeCommission(
        Task $task,
        ?PostedDocument $feeDoc,
        int $companyId,
        \DateTimeInterface $docDate,
        ?int $userId,
        PostingService $posting
    ): ?PostedDocument {
        if ($feeDoc === null) {
            return null;
        }

        $agent = $task->agent;
        $serviceType = (string) $task->type;
        $commissionableTypes = $this->companyOptionJsonArray($companyId, 'accounting.commissionable_fee_types');

        if ($agent === null || $serviceType === '' || ! in_array($serviceType, $commissionableTypes, true)) {
            return null;
        }

        // The fee amount itself -- the RECEIVABLE_CONTROL leg's own amount (both lines share the
        // same amount by construction; either would do).
        $feeAmount = (float) ($feeDoc->lines[0]->amount ?? 0.0);
        $rate = (float) ($agent->commission ?? 0.0);
        $commission = round($rate * $feeAmount, 3);

        if ($commission == 0.0) {
            return null;
        }

        $absCommission = abs($commission);
        $expenseSide = $commission > 0 ? 'debit' : 'credit';
        $liabilitySide = $commission > 0 ? 'credit' : 'debit';
        $currency = (string) config('accounting.engine.base_currency');

        $draft = new DocumentDraft(
            companyId: $companyId,
            branchId: (int) ($agent->branch_id ?? 0),
            docType: 'JV',
            subType: 'VOID_COMMISSION',
            docDate: $docDate,
            narration: 'Agent commission on void fee: '.$task->reference,
            lines: [
                new LineDraft(
                    // CT-A3 E4 (CT-F38): commission is not payroll — see config('accounting.purpose_codes').
                    purposeCode: 'COMMISSION_EXPENSE',
                    accountId: null,
                    side: $expenseSide,
                    amount: $absCommission,
                    currency: $currency,
                    originalAmount: $absCommission,
                    exchangeRate: 1.0,
                    transactionType: 'VOID_FEE_COMMISSION_EXPENSE',
                    partyAccountRef: $agent->id,
                    description: 'Agent commission (void fee) for '.$task->reference,
                    taskId: $task->id,
                    ledgerType: 'expense',
                    partyName: $agent->name,
                ),
                new LineDraft(
                    // CT-A3 E4 (CT-F38): commission is not payroll — see config('accounting.purpose_codes').
                    purposeCode: 'COMMISSION_PAYABLE',
                    accountId: null,
                    side: $liabilitySide,
                    amount: $absCommission,
                    currency: $currency,
                    originalAmount: $absCommission,
                    exchangeRate: 1.0,
                    transactionType: 'VOID_FEE_COMMISSION_PAYABLE',
                    partyAccountRef: $agent->id,
                    description: 'Agent commission (void fee) for '.$task->reference,
                    taskId: $task->id,
                    ledgerType: 'payable',
                    partyName: $agent->name,
                ),
            ],
            idempotencyKey: 'void:'.$task->id.':fee-commission',
        );

        return $posting->post($draft, $userId);
    }

    /**
     * w6-brief.md "Kinds" 3 + "Model": disposition of any amount the client already paid toward
     * this now-voided task, per `invoice_overpay_cancel_policy` {credit (default) | refund_out |
     * manual} -- the SAME option/method {@see self::overpayCancelPolicy()} (built for the
     * hold/cancel lifecycle) already reads, reused verbatim. `manual` (a real option for VOID that
     * Refund's own disposition enum does not carry) posts nothing and writes an audit event
     * instead, exactly mirroring {@see self::cancel()}'s own "manual -> audit row flags ... for a
     * human decision" convention.
     *
     * "Paid amount" itself: reuses {@see self::paidAmountForTask()}, which resolves (in order) a
     * CONSUMED hold-deposit's own `InvoicePartial` amount, then an UNCONSUMED hold-deposit via
     * {@see self::depositHeld()}, then the plain `invoiceDetail->paid` flag fallback -- see that
     * method's own docblock for the full resolution order/rationale.
     *
     * ── W6.U2 fix round (w6u-verify-2.md finding 1, BLOCKING) -- POLARITY CORRECTED ──────────────
     * The line sides below are FLIPPED relative to the shape this method shipped with before this
     * fix round (which mirrored `RefundPostingService::postDisposition()`'s sides verbatim: `Cr
     * RECEIVABLE_CONTROL` / `Dr {creditPurpose}`). Worked through by full running-balance T-account
     * arithmetic against the exact repro this fix closes (500 sale / 350 cost / 200 hold-deposit,
     * same-day void, default `credit` policy):
     *   1. Sale (`issue()`): `Dr AR 500 / Cr Payable 350 / Cr Revenue 150` -- AR=+500.
     *   2. Deposit-apply (`issue()`, {@see self::applyHoldDepositToInvoice()}, UNCHANGED by this
     *      fix): `Dr 2632 200 / Cr AR 200` -- AR=+300, 2632=0.
     *   3. `voidReverseSale()`'s CRN -- `PostingService::reverse()` flips the ENTIRE original sale
     *      (step 1) regardless of how much of it was subsequently paid off: `Cr AR 500 / Dr
     *      Payable 350 / Dr Revenue 150` -- AR=+300-500=-200 (a CREDIT balance in the AR control
     *      account -- exactly what "the company now owes this client $200 back" looks like in a
     *      control account, a normal and expected intermediate state, not a bug).
     *   4. THIS disposition must clear that -200 credit balance back to zero and land the $200
     *      obligation in its correctly-classified account (2632 for `credit`, cash for
     *      `refund_out`). Clearing a CREDIT balance in an asset-normal account requires a further
     *      DEBIT to that account, not another credit -- so the correct shape is `Dr
     *      RECEIVABLE_CONTROL / Cr {creditPurpose}`, producing AR=-200+200=0 and 2632=0+200=+200:
     *      exactly this fix's own required final nets. The PREVIOUS shape (`Cr RECEIVABLE_CONTROL
     *      / Dr {creditPurpose}`) instead ADDED a third credit on top of an already-credit AR
     *      balance (driving it to -400, the exact number w6u-verify-2.md's repro reported) and
     *      DEBITED 2632 a second time (driving it to -200 instead of +200) -- i.e. fixing ONLY the
     *      double-counted amount (this fix's {@see self::appliedDepositAmountForTask()}) without
     *      also correcting this polarity would still have produced the same wrong final balances,
     *      just from a single (still wrongly-signed) disposition posting instead of two. Verified
     *      identically for the `refund_out` case (`Dr AR / Cr REFUND_PAYOUT_CASH_BANK` correctly
     *      pays cash out while leaving 2632 untouched) and for the plain non-deposit
     *      `invoiceDetail->paid` fallback (the same T-account derivation, done in this fix's own
     *      report, shows the OLD polarity would have driven AR to -1000 rather than 0 for a
     *      genuinely-received full payment; no existing test in this file asserts AR/2632 account
     *      balances, only disposition-existence/doc_type/Credit-row-count, so this correction does
     *      not regress any of them). NOT propagated to `RefundPostingService::postDisposition()`
     *      itself (out of this sub-wave's scope -- W4 is explicitly untouched -- flagged in this
     *      fix's own report as a possible parallel issue for that owner to check independently).
     *
     * NET OF FEE (unchanged by this fix, still matching RefundPostingService::postDisposition()'s
     * own `$clientNet` input pattern): the raw paid amount from {@see self::paidAmountForTask()} is
     * reduced by the void fee {@see self::voidPostFee()} just posted a few lines earlier in the
     * SAME void() call (`$feeDoc`), so the client's credit/refund-out/manual-disposition amount is
     * what remains AFTER the agency's own void fee is accounted for -- never the raw paid figure
     * with the fee still sitting on top of it. When the fee meets or exceeds the paid amount, the
     * net is clamped to 0 (nothing left to give back; the fee's own `Dr AR` line posted by
     * voidPostFee() already stands as a separate open receivable for whatever the paid amount
     * didn't cover -- this method never manufactures a negative disposition to net that out a
     * second time).
     */
    private function voidDisposition(
        Task $task,
        ?InvoiceDetail $invoiceDetail,
        ?PostedDocument $feeDoc,
        int $companyId,
        \DateTimeInterface $docDate,
        ?int $userId,
        PostingService $posting
    ): ?PostedDocument {
        $tolerance = (float) config('accounting.engine.balance_tolerance', 0.0005);
        $rawPaid = $this->paidAmountForTask($task, $invoiceDetail);
        $feeAmount = $feeDoc === null ? 0.0 : (float) ($feeDoc->lines[0]->amount ?? 0.0);
        $paid = round(max(0.0, $rawPaid - $feeAmount), 3);

        if ($paid <= $tolerance) {
            return null;
        }

        $policy = $this->overpayCancelPolicy($companyId);

        if ($policy === 'manual') {
            $this->recordEvent('void_disposition_manual_pending', $companyId, $task->id, 'void', 'void', null, null, [
                'paid_amount' => $paid,
            ]);

            return null;
        }

        $currency = (string) config('accounting.engine.base_currency');

        $lines = [
            new LineDraft(
                purposeCode: 'RECEIVABLE_CONTROL',
                accountId: null,
                side: 'debit',
                amount: $paid,
                currency: $currency,
                originalAmount: $paid,
                exchangeRate: 1.0,
                transactionType: 'VOID_DISPOSITION_RECEIVABLE',
                partyAccountRef: $task->client_id,
                description: 'Void disposition ('.$policy.'): '.$task->reference,
                ledgerType: 'receivable',
                partyName: $task->client_name,
            ),
        ];

        $creditPurpose = $policy === 'refund_out' ? 'REFUND_PAYOUT_CASH_BANK' : 'CLIENT_ADVANCE';

        $lines[] = new LineDraft(
            purposeCode: $creditPurpose,
            accountId: null,
            side: 'credit',
            amount: $paid,
            currency: $currency,
            originalAmount: $paid,
            exchangeRate: 1.0,
            transactionType: 'VOID_DISPOSITION_'.strtoupper($policy),
            partyAccountRef: $task->client_id,
            description: 'Void disposition ('.$policy.'): '.$task->reference,
            ledgerType: $policy === 'credit' ? 'liability' : 'asset',
            partyName: $task->client_name,
        );

        $draft = new DocumentDraft(
            companyId: $companyId,
            branchId: (int) ($task->agent?->branch_id ?? 0),
            docType: $policy === 'credit' ? 'JV' : 'PV',
            subType: 'VOID_DISPO',
            docDate: $docDate,
            narration: 'Void client-net disposition ('.$policy.') for '.$task->reference,
            lines: $lines,
            idempotencyKey: 'void:'.$task->id.':disposition',
        );

        $posted = $posting->post($draft, $userId);

        if ($policy === 'credit') {
            // Credit::boot()'s own creating() guard validates `type` against a closed enum
            // (INVOICE|TOPUP|INVOICE_REFUND|REFUND) that has no 'Void' member -- ct-void-map.md's
            // legacy `voidTask()` body writes 'Void' anyway (a pre-existing defect on the OFF path,
            // not this sub-wave's to fix). The engine-ON path here uses `Credit::REFUND`, the
            // closest validated type for "a client credit created by undoing a sale" (the same
            // type RefundPostingService::postDisposition() uses for its own credit-disposition
            // row) -- never a type this model would refuse to create.
            \App\Models\Credit::create([
                'company_id' => $companyId,
                'branch_id' => (int) ($task->agent?->branch_id ?? 0),
                'client_id' => $task->client_id,
                'type' => \App\Models\Credit::REFUND,
                'description' => 'Void credit: '.$task->reference,
                'amount' => $paid,
            ]);
        }

        return $posted;
    }

    /**
     * See {@see self::voidDisposition()}'s own docblock for the resolution order/rationale.
     *
     * W6.U2 fix (w6u-verify-2.md finding 1): a hold-deposit CONSUMED by {@see self::
     * applyHoldDepositToInvoice()} is now resolved FIRST, through its own `InvoicePartial` row --
     * the invoice's ordinary "how much has been paid" mechanism -- never through
     * {@see self::depositHeld()} again ({@see self::depositHeld()} itself now excludes consumed
     * rows by construction, so it would silently read 0 for this task and make the already-paid
     * deposit invisible to disposition entirely, rather than counted twice). The remaining two
     * branches are UNCHANGED from before this fix: an unconsumed hold-deposit (a task voided
     * without ever having been through `issue()`'s apply step -- not the ordinary path, but not
     * refused either), then the plain "whole invoice line paid" flag fallback.
     */
    private function paidAmountForTask(Task $task, ?InvoiceDetail $invoiceDetail): float
    {
        $viaAppliedDeposit = $this->appliedDepositAmountForTask($task);

        if ($viaAppliedDeposit > 0.0005) {
            return round($viaAppliedDeposit, 3);
        }

        $viaReceipts = $this->depositHeld($task);

        if ($viaReceipts > 0.0005) {
            return round($viaReceipts, 3);
        }

        if ($invoiceDetail !== null && (bool) $invoiceDetail->paid) {
            return round((float) $invoiceDetail->task_price, 3);
        }

        return 0.0;
    }

    /**
     * W6.U2 fix (w6u-verify-2.md finding 1 + finding 2). Sum of this task's own CONSUMED
     * hold-deposit `InvoicePartial` amount(s) -- rows {@see self::applyHoldDepositToInvoice()}
     * created and linked back onto the `InvoiceReceipt` rows it re-pointed (`invoice_partial_id`,
     * `applied_at IS NOT NULL`). Reads `invoice_partials.amount` -- the SAME durable "how much of
     * this invoice has been paid" mechanism {@see \App\Http\Controllers\ReceiptVoucherController::
     * applyAllocationsToInvoices()} and {@see \App\Services\PaymentApplicationService} already use
     * everywhere else in this codebase -- never `journal_entries.balance`/`accounts.
     * actual_balance` (feedback_accounting_boundary — forbidden reads), and never
     * {@see self::depositHeld()} (which excludes exactly these rows by construction). Returns 0.0
     * for a task whose deposit, if any, was never applied through that path.
     */
    private function appliedDepositAmountForTask(Task $task): float
    {
        $partialIds = InvoiceReceipt::where('task_id', $task->id)
            ->whereNotNull('applied_at')
            ->whereNotNull('invoice_partial_id')
            ->pluck('invoice_partial_id');

        if ($partialIds->isEmpty()) {
            return 0.0;
        }

        return (float) InvoicePartial::whereIn('id', $partialIds)->sum('amount');
    }

    /**
     * W6.R (w6-brief.md "Kinds" 4: REISSUE / EXCHANGE). Reissues $oldTask into $newTask: reverses
     * the ORIGINAL task's own atomic sale document (ticket+client leg, same single-document shape
     * {@see self::void()}'s own "Architecture note" explains), then posts the NEW task's sale+cost
     * as brand-new invoice lines on the SAME invoice -- never a second invoice, never a rewritten
     * total on the existing lines (w6-brief.md: "fare difference ... never folded into a rewritten
     * total"). Together the reversal (a structural CRN -- it credits back the full old sell) and
     * the new sale (a structural DBN -- it debits the full new sell) net to exactly the fare
     * difference on the client's own AR balance; {@see self::reissueFareDifference()} surfaces that
     * NET figure (never posts a third document for it) so a caller/UI can characterise it as
     * "DBN" (client owes more) or "CRN" (client owes less) without recomputing it independently of
     * what actually posted.
     *
     * ── Two callers this method unifies (both real, both already in this codebase) ────────────────
     *   1. `TaskController::store()`'s own Jazeera/FlyDubai same-reference fare-delta heuristic:
     *      $oldTask is genuinely `ticket_status=issued` (a real ticket sold and invoiced), $newTask
     *      is the brand-new Task row that heuristic creates with `status=reissued`, no
     *      invoiceDetail of its own yet.
     *   2. `TaskController::switchInvoiceTask()` (w6-brief.md: "becomes a thin wrapper over this
     *      flow ... its previously logged-only profit delta now posts"): $oldTask is the
     *      `status=confirmed` original that nonetheless already carries an `invoiceDetail` (a
     *      placeholder billed under the wrong task row), $newTask is the already-`issued` task
     *      that should really own that invoice line. {@see self::normalizeTicketStatus()} (reused
     *      verbatim, not reimplemented) derives `ticket_status='issued'` for this case from the
     *      invoiceDetail's own existence, exactly as it already does for {@see self::void()} --
     *      so the SAME precondition ("ticket_status must be issued") holds for both callers without
     *      this method needing to know which one it was invoked from.
     *
     * ── Idempotency (w6-brief.md "Preconditions": "reissue:{old_task_id}:{new_task_id}") ──────────
     * Top-level guard: `$newTask` already carrying an `invoice_detail` row is treated as "this
     * reissue already happened" and short-circuits to a no-op summary before anything is touched --
     * mirrors {@see self::void()}'s own `ticket_status==='void'` short-circuit, adapted to the one
     * fact that is unambiguous for $newTask specifically (a freshly-created row cannot have an
     * invoice_detail unless THIS method, or the equivalent legacy path it replaces, already gave it
     * one). Every satellite document below (fee, both commission JVs, disposition) additionally
     * carries its OWN fixed idempotency key under the `reissue:{old}:{new}:*` namespace, so a
     * concurrent/replayed call that races past the top-level guard still cannot double-post any
     * individual document (PostingService::post()'s own step-1 short-circuit).
     *
     * ── Preconditions (w6-brief.md "Preconditions"; see {@see self::assertReissuePreconditions()}) ─
     *   - `$oldTask->ticket_status` must be `issued` (post-{@see self::normalizeTicketStatus()}) --
     *     "reissue only from issued", stricter than void()'s issued|reissued (a task already
     *     reissued once cannot be the OLD leg of a second reissue through this method; chaining
     *     reissues is out of this sub-wave's scope, flagged in this sub-wave's own report).
     *   - `$oldTask` must have a resolvable `invoice_detail` (the SAME invoice the new lines post
     *     onto) -- unlike {@see self::void()}, which tolerates a null invoiceDetail (nothing to
     *     reverse, disposition/fee still meaningful), reissue's whole mechanic is "new lines on the
     *     SAME invoice", so a null invoiceDetail here is a genuine precondition failure, not a
     *     silent skip.
     *   - The carrying invoice's `is_locked` flag blocks (same `manageLocks` gate check as void()).
     *   - The reconciled-line check lives inside `PostingService::reverse()` itself (throws
     *     {@see ProtectedLineException}), caught here and re-thrown pointing at the refund flow,
     *     identical convention to {@see self::voidReverseSale()}.
     *
     * ── Status leg (ticket_status/client_status only -- legacy `status` deliberately untouched) ────
     * `$oldTask->ticket_status='reissued'` / `client_status='rebilled'` (the enum value the W6.S
     * migration added specifically for this case -- see that migration's own docblock);
     * `$newTask->ticket_status='issued'` / `client_status='open'`. Legacy `tasks.status` is left
     * exactly as the caller set it on BOTH tasks: {@see self::void()} could safely flip legacy
     * `status='void'` because that value is unambiguous under the as-is vocabulary, but there is no
     * single unambiguous legacy value for "this ticket was superseded by a reissue" on the OLD
     * task's side (`status='reissued'` already means something different in the as-is
     * vocabulary -- the NEW/replacement row, per importer-status-contract.md Table 1) -- inventing
     * one here would confuse the legacy readers the Traps note says must keep working, so this
     * method writes only the two W6.S columns that exist precisely so a nuance like this has
     * somewhere new to go, exactly as that migration's own docblock intends.
     *
     * ── What this method does NOT do (reported gaps, not silently skipped) ─────────────────────────
     *   - Chained reissues (reissuing an already-`reissued` ticket a second time) -- precondition
     *     refuses; W6.R's own scope is the single reverse-then-repost mechanic the brief describes.
     *   - `SaleDraftBuilder`'s pre-existing zero-supplier-cost/agent-basis gap (documented on
     *     {@see self::postEmdAncillary()}) applies here too: a reissued task with a real sell price
     *     but a genuinely $0 supplier cost, under agent-basis posting, still hits
     *     `PostingService::post()`'s reject-zero-amount-line rule via the SAME builder ordinary
     *     issue-time sales already use -- not a new gap this method introduces, inherited from W3d.
     *   - `W6.C` supplier-charge-rule lines are not resolved for the reissued sale (same documented
     *     boundary {@see self::issue()}'s own docblock states for the ordinary import path -- this
     *     sub-wave "does not touch void/reissue/bulk-void" per W6.C's own brief section).
     *
     * @param  array{fee?: float|null, user_id?: int|null}  $opts
     * @return array{idempotent: bool, reversal: ?PostedDocument, new_sale: ?PostedDocument,
     *               fee: ?PostedDocument, commission_unearn: ?PostedDocument,
     *               commission_earn: ?PostedDocument, fee_commission: ?PostedDocument,
     *               disposition: ?PostedDocument,
     *               fare_difference: array{type: string, amount: float}}
     */
    public function reissue(Task $oldTask, Task $newTask, array $opts = []): array
    {
        $userId = $opts['user_id'] ?? Auth::id();
        $feeOverride = array_key_exists('fee', $opts) ? $opts['fee'] : null;

        return DB::transaction(function () use ($oldTask, $newTask, $userId, $feeOverride) {
            /** @var Task $oldTask */
            $oldTask = Task::withoutGlobalScopes()->whereNull('deleted_at')->lockForUpdate()->findOrFail($oldTask->id);
            /** @var Task $newTask */
            $newTask = Task::withoutGlobalScopes()->whereNull('deleted_at')->lockForUpdate()->findOrFail($newTask->id);
            $companyId = (int) $oldTask->company_id;

            if (InvoiceDetail::where('task_id', $newTask->id)->exists()) {
                // Top-level idempotency short-circuit -- see method docblock.
                return [
                    'idempotent' => true,
                    'reversal' => null,
                    'new_sale' => null,
                    'fee' => null,
                    'commission_unearn' => null,
                    'commission_earn' => null,
                    'fee_commission' => null,
                    'disposition' => null,
                    'fare_difference' => ['type' => 'none', 'amount' => 0.0],
                ];
            }

            $this->normalizeTicketStatus($oldTask);
            $this->assertReissuePreconditions($oldTask, $newTask);

            $oldInvoiceDetail = $oldTask->invoiceDetail;
            $invoice = $oldInvoiceDetail->invoice;
            $posting = app(PostingService::class);
            $docDate = Carbon::now();

            $reversal = $this->reissueReverseOldSale($oldTask, $oldInvoiceDetail, $posting, $docDate, $userId);

            $newSaleResult = $this->reissuePostNewSale($oldTask, $newTask, $invoice, $companyId, $docDate, $userId, $posting);
            $newInvoiceDetail = $newSaleResult['invoice_detail'];
            $newSale = $newSaleResult['posted'];

            $oldSell = round((float) ($oldInvoiceDetail->task_price ?? $oldTask->price ?? 0.0), 3);
            $newSell = round((float) ($newTask->price ?? 0.0), 3);
            $fareDifference = $this->reissueFareDifference($oldSell, $newSell);

            $fee = $this->reissuePostFee($oldTask, $newTask, $newInvoiceDetail, $invoice, $companyId, $docDate, $userId, $feeOverride, $posting);
            $unearn = $this->voidCommissionUnearn($oldTask, $oldInvoiceDetail, $companyId, $docDate, $userId, $posting);
            $commissionEarn = $this->reissuePostCommission($newTask, $newInvoiceDetail, $companyId, $docDate, $userId, $posting);
            $feeCommission = $this->reissuePostFeeCommission($oldTask, $newTask, $fee, $companyId, $docDate, $userId, $posting);

            // "Existing receipt allocations re-applied to the new lines" (w6-brief.md Kind 4):
            // the paid amount is read BEFORE re-pointing (still tagged to $oldTask), then every
            // approved invoice_receipts row this task is holding is structurally re-pointed
            // (task_id, never resolved by description) onto $newTask -- the same durable per-task
            // deposit ledger {@see self::depositHeld()} already reads for the hold/deposit
            // lifecycle, reused rather than a second "apply engine" call invented for this one
            // case.
            $paidBefore = $this->paidAmountForTask($oldTask, $oldInvoiceDetail);
            InvoiceReceipt::where('task_id', $oldTask->id)->where('status', 'approved')->update(['task_id' => $newTask->id]);

            $disposition = $this->reissueDisposition($oldTask, $newTask, $paidBefore, $newSell, $fee, $companyId, $docDate, $userId, $posting);

            $oldTask->ticket_status = 'reissued';
            $oldTask->client_status = 'rebilled';
            $oldTask->save();

            $newTask->ticket_status = 'issued';
            $newTask->client_status = 'open';
            $newTask->save();

            $this->recordEvent('reissue', $companyId, $newTask->id, $oldTask->status, $newTask->status, null, null, [
                'old_task_id' => $oldTask->id,
                'new_task_id' => $newTask->id,
                'invoice_id' => $invoice->id,
                'fare_difference' => $fareDifference,
                'fee_posted' => $fee !== null,
                'commission_unearned' => $unearn !== null,
                'commission_earned' => $commissionEarn !== null,
            ]);

            Log::info('task_status.reissued', [
                'old_task_id' => $oldTask->id,
                'new_task_id' => $newTask->id,
                'company_id' => $companyId,
                'invoice_id' => $invoice->id,
                'fare_difference' => $fareDifference,
            ]);

            return [
                'idempotent' => false,
                'reversal' => $reversal,
                'new_sale' => $newSale,
                'fee' => $fee,
                'commission_unearn' => $unearn,
                'commission_earn' => $commissionEarn,
                'fee_commission' => $feeCommission,
                'disposition' => $disposition,
                'fare_difference' => $fareDifference,
            ];
        });
    }

    /**
     * w6-brief.md "Preconditions" as applied to {@see self::reissue()} -- see that method's own
     * docblock for the full rationale of each check.
     */
    private function assertReissuePreconditions(Task $oldTask, Task $newTask): void
    {
        if ($oldTask->ticket_status !== 'issued') {
            throw new \RuntimeException(
                "TaskStatusService::reissue(): task #{$oldTask->id} has ticket_status='{$oldTask->ticket_status}' -- "
                .'only an issued ticket can be reissued.'
            );
        }

        if ((int) $oldTask->company_id !== (int) $newTask->company_id) {
            throw new \RuntimeException(
                "TaskStatusService::reissue(): task #{$oldTask->id} (company #{$oldTask->company_id}) and "
                ."task #{$newTask->id} (company #{$newTask->company_id}) belong to different companies."
            );
        }

        $invoiceDetail = $oldTask->invoiceDetail;

        if ($invoiceDetail === null) {
            throw new \RuntimeException(
                "TaskStatusService::reissue(): task #{$oldTask->id} has no invoice_detail -- reissue posts the "
                .'new lines on the SAME invoice the original sale carries, so there is nothing to reissue onto.'
            );
        }

        $invoice = $invoiceDetail->invoice;

        if ($invoice === null) {
            // Data-integrity edge case, not a normal precondition (an invoice_detail row whose
            // invoice was hard-deleted out from under it) -- {@see self::reissuePostNewSale()}
            // requires a real Invoice to post the new lines onto, so this must refuse loudly
            // rather than let a null propagate into that method's non-nullable parameter.
            throw new \RuntimeException(
                "TaskStatusService::reissue(): task #{$oldTask->id}'s invoice_detail #{$invoiceDetail->id} has no "
                .'resolvable invoice -- cannot determine where to post the reissued lines.'
            );
        }

        if ($invoice->is_locked && ! Gate::check('manageLocks', User::class)) {
            throw new \RuntimeException(
                "TaskStatusService::reissue(): task #{$oldTask->id}'s carrying invoice #{$invoice->id} is locked -- "
                .'contact your accountant to unlock it before reissuing.'
            );
        }
    }

    /**
     * Reverses $oldTask's own atomic sale document -- same mechanism as
     * {@see self::voidReverseSale()} (structurally by idempotency_key, never description), stamping
     * `sub_type='REISSUE_REVERSAL'` (w6-brief.md Kind 4) rather than void()'s `bsptype='VOID'`:
     * `PostingService::reverse()` has no `DocumentDraft` carrier for a caller-chosen `sub_type` on
     * the reversal it builds internally (it always writes `subType: $posted->doc_type`), so this is
     * stamped directly on the row immediately after posting -- the identical convention void() uses
     * for `bsptype` (see that method's own "Architecture note").
     */
    private function reissueReverseOldSale(
        Task $oldTask,
        InvoiceDetail $invoiceDetail,
        PostingService $posting,
        \DateTimeInterface $docDate,
        ?int $userId
    ): ?PostedDocument {
        $saleKey = 'invoice-detail:'.$invoiceDetail->id.':sale';
        $companyId = (int) $oldTask->company_id;

        $saleTransaction = Transaction::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('company_id', $companyId)
            ->where('idempotency_key', $saleKey)
            ->first();

        if ($saleTransaction === null) {
            Log::info('task_status.reissue_no_sale_to_reverse', ['task_id' => $oldTask->id]);

            return null;
        }

        try {
            $reversed = $posting->reverse($saleTransaction, $docDate, $userId);
        } catch (ProtectedLineException $e) {
            throw new \RuntimeException(
                "TaskStatusService::reissue(): task #{$oldTask->id}'s sale document has a reconciled line and ".
                'cannot be reversed for reissue -- use the refund flow (RefundController/RefundPostingService, '.
                'W4) instead, which supports a reconciled original sale.',
                previous: $e
            );
        }

        Transaction::withoutGlobalScopes()->whereKey($reversed->transaction->id)->update(['sub_type' => 'REISSUE_REVERSAL']);

        return $reversed;
    }

    /**
     * Posts $newTask's own sale+cost as brand-new invoice lines on $invoice (the SAME invoice
     * $oldTask's own sale was carried on) -- w6-brief.md Kind 4: "post new task's sale + cost as
     * new invoice lines on the SAME invoice (sub_type=REISSUE)". Built via the SAME
     * {@see SaleDraftBuilder}/{@see SaleDraftInput} pair every ordinary issue-time sale uses
     * (`InvoiceController::postSaleJournalEntries()`), not a hand-rolled shape -- unlike
     * {@see self::postEmdAncillary()} (which hand-builds because its cost leg is genuinely often
     * zero), a reissued ticket is an ordinary full sale and should be built exactly like one; see
     * this method's own class-level "What this method does NOT do" note for the one pre-existing
     * `SaleDraftBuilder` gap (zero-cost/agent-basis) this inherits rather than introduces.
     *
     * `invoice.sub_amount`/`amount` are only ever ADDED to here (never reduced for the reversed old
     * line) -- the same convention {@see self::postEmdAncillary()} already uses: these two columns
     * are a running additive log of every line ever billed on the invoice, not a live "current
     * total" the ledger recomputes; the reversal above does not touch them either (mirroring
     * {@see self::void()}, which never reduces them when it reverses a task's sale). The ledger
     * (not these two Invoice columns) is the source of truth for what the client actually owes --
     * this is precisely why the brief insists "never a rewritten total": rewriting `invoice.amount`
     * down to a single new figure here would erase the paper trail these two real documents
     * (reversal + new sale) already provide.
     *
     * Idempotency key: `invoice-detail:{newDetailId}:sale` -- the SAME convention every other sale
     * document in this codebase uses (not a `reissue:`-namespaced key), deliberately: this is what
     * lets {@see self::voidCommissionUnearn()}/{@see self::voidReverseSale()} find and reverse THIS
     * document unmodified if the reissued task is later voided, with zero special-casing for
     * "this sale happened to originate from a reissue".
     *
     * @return array{invoice_detail: InvoiceDetail, posted: PostedDocument}
     */
    private function reissuePostNewSale(
        Task $oldTask,
        Task $newTask,
        Invoice $invoice,
        int $companyId,
        \DateTimeInterface $docDate,
        ?int $userId,
        PostingService $posting
    ): array {
        $sell = round((float) ($newTask->price ?? 0.0), 3);
        $cost = round((float) ($newTask->total ?? 0.0), 3);
        $serviceType = (string) $newTask->type;
        $agent = $newTask->agent;
        $supplier = $newTask->supplier;
        $client = $newTask->client;

        $invoiceDetail = InvoiceDetail::create([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'task_id' => $newTask->id,
            'task_description' => $newTask->description,
            'task_remark' => $newTask->remark,
            'client_notes' => $newTask->notes,
            'task_price' => $sell,
            'supplier_price' => $cost,
            'markup_price' => $sell - $cost,
            'paid' => false,
        ]);

        $invoice->sub_amount = (float) $invoice->sub_amount + $sell;
        $invoice->amount = (float) $invoice->amount + $sell;
        $invoice->save();

        $postingBasis = SaleDraftBuilder::resolvePostingBasis($companyId, $serviceType);
        // P2.5.D fix (verify finding): this is a real SaleDraftInput construction site (a brand
        // new sale document for the reissued task) and must resolve the same per-company
        // recognition-timing override as every other feeder, exactly the way $postingBasis
        // itself is resolved on the line above -- see SaleDraftBuilder::resolveRecognitionTiming()'s
        // own docblock ("all real construction sites... resolve this, right alongside their
        // existing resolvePostingBasis() call").
        $recognitionTiming = SaleDraftBuilder::resolveRecognitionTiming($companyId, $serviceType);

        $lines = (new SaleDraftBuilder)->buildLines(new SaleDraftInput(
            serviceType: $serviceType,
            sellAmount: $sell,
            costAmount: $cost,
            postingBasis: $postingBasis,
            clientId: $newTask->client_id,
            clientName: $client?->full_name ?? $newTask->client_name,
            supplierId: $supplier?->id,
            supplierName: $supplier?->name,
            agentId: $agent?->id,
            agentName: $agent?->name,
            invoiceId: $invoice->id,
            invoiceDetailId: $invoiceDetail->id,
            taskId: $newTask->id,
            currency: (string) config('accounting.engine.base_currency'),
            receivableDescription: 'Reissued sale for '.$newTask->reference,
            payableDescription: 'Cost of reissued '.$newTask->reference.' owed to supplier: '.($supplier?->name ?? 'Unknown Supplier'),
            revenueDescription: 'Reissued sale for '.$newTask->reference,
            marginPositiveDescription: 'Margin earned on reissued '.$newTask->reference,
            marginNegativeDescription: 'Margin shortfall (sold below cost) on reissued '.$newTask->reference,
            costDescription: 'Supplier cost booked for reissued '.$newTask->reference,
            recognitionTiming: $recognitionTiming,
        ));

        $draft = new DocumentDraft(
            companyId: $companyId,
            branchId: (int) ($agent?->branch_id ?? 0),
            docType: 'INV',
            subType: 'REISSUE',
            docDate: $docDate,
            narration: 'Reissued sale for '.$newTask->reference.' (replaces task #'.$oldTask->id.')',
            lines: $lines,
            idempotencyKey: 'invoice-detail:'.$invoiceDetail->id.':sale',
            invoiceId: $invoice->id,
        );

        $posted = $posting->post($draft, $userId);

        return ['invoice_detail' => $invoiceDetail, 'posted' => $posted];
    }

    /**
     * w6-brief.md Kind 4: "fare difference = net of new lines vs CRN, shown as DBN (client owes) or
     * CRN (client credited)". A pure computation over the two REAL documents
     * {@see self::reissueReverseOldSale()}/{@see self::reissuePostNewSale()} already posted -- never
     * a third ledger document (see {@see self::reissue()}'s own docblock for why).
     *
     * @return array{type: string, amount: float} `type` is `dbn` (newSell > oldSell -- the client
     *                                            now owes more), `crn` (newSell < oldSell -- the
     *                                            client is credited), or `none` (equal, within
     *                                            tolerance). `amount` is always >= 0.
     */
    private function reissueFareDifference(float $oldSell, float $newSell): array
    {
        $tolerance = (float) config('accounting.engine.balance_tolerance', 0.0005);
        $diff = round($newSell - $oldSell, 3);

        if (abs($diff) <= $tolerance) {
            return ['type' => 'none', 'amount' => 0.0];
        }

        return ['type' => $diff > 0 ? 'dbn' : 'crn', 'amount' => abs($diff)];
    }

    /**
     * REISSUE FEE (w6-brief.md Kind 4: "Reissue fee -> 4135 Reissue Fee Income"). Same shape and
     * same shared fee-schedule resolution as {@see self::voidPostFee()}
     * ({@see self::resolveFeeFromSchedule()}) -- only the destination purpose code
     * (`REISSUE_FEE_INCOME` vs `VOID_FEE_INCOME`) and the idempotency-key namespace differ. Fee
     * base is the NEW task's own sell price (the ticket actually being issued now), reason_tag=fee
     * stamped post-post, same convention as voidPostFee().
     */
    private function reissuePostFee(
        Task $oldTask,
        Task $newTask,
        ?InvoiceDetail $newInvoiceDetail,
        ?Invoice $invoice,
        int $companyId,
        \DateTimeInterface $docDate,
        ?int $userId,
        ?float $feeOverride,
        PostingService $posting
    ): ?PostedDocument {
        $sellAmount = (float) ($newInvoiceDetail?->task_price ?? $newTask->price ?? 0.0);
        $fee = $this->resolveFeeFromSchedule($companyId, (string) $newTask->type, $sellAmount, $feeOverride);

        $tolerance = (float) config('accounting.engine.balance_tolerance', 0.0005);

        if ($fee <= $tolerance) {
            return null;
        }

        $currency = (string) config('accounting.engine.base_currency');

        $lines = [
            new LineDraft(
                purposeCode: 'RECEIVABLE_CONTROL',
                accountId: null,
                side: 'debit',
                amount: $fee,
                currency: $currency,
                originalAmount: $fee,
                exchangeRate: 1.0,
                transactionType: 'REISSUE_FEE_RECEIVABLE',
                partyAccountRef: $newTask->client_id,
                description: 'Reissue fee for '.$newTask->reference,
                invoiceId: $newInvoiceDetail?->invoice_id,
                invoiceDetailId: $newInvoiceDetail?->id,
                taskId: $newTask->id,
                ledgerType: 'receivable',
                partyName: $newTask->client_name,
            ),
            new LineDraft(
                purposeCode: 'REISSUE_FEE_INCOME',
                accountId: null,
                side: 'credit',
                amount: $fee,
                currency: $currency,
                originalAmount: $fee,
                exchangeRate: 1.0,
                transactionType: 'REISSUE_FEE_INCOME',
                description: 'Reissue fee income for '.$newTask->reference,
                invoiceId: $newInvoiceDetail?->invoice_id,
                invoiceDetailId: $newInvoiceDetail?->id,
                taskId: $newTask->id,
                ledgerType: 'income',
            ),
        ];

        $draft = new DocumentDraft(
            companyId: $companyId,
            branchId: (int) ($newTask->agent?->branch_id ?? 0),
            docType: 'DBN',
            subType: 'REISSUE_FEE',
            docDate: $docDate,
            narration: 'Reissue fee for '.$newTask->reference,
            lines: $lines,
            idempotencyKey: 'reissue:'.$oldTask->id.':'.$newTask->id.':fee',
            invoiceId: $invoice?->id,
        );

        $posted = $posting->post($draft, $userId);

        JournalEntry::whereIn('id', collect($posted->lines)->pluck('id'))->update(['reason_tag' => 'fee']);

        return $posted;
    }

    /**
     * NEW commission JV on the reissued sale's own margin (w6-brief.md "Commission": "reissue ->
     * ... new JV on the reissued sale (net effect = commission on the new margin)"). Simplified
     * relative to `InvoiceController::addJournalEntry()`'s full profit/gateway-fee-share
     * calculation (this service layer has no `Payment`/gateway context to draw on at reissue time) --
     * commission = `agent->commission` rate * (sell - cost) margin, same simplification convention
     * {@see self::voidFeeCommission()} already uses for the void fee's own commission. A no-op when
     * there is no agent, or the margin/commission rounds to <= 0 (never posts a negative-commission
     * JV, matching every other commission feeder in this class).
     *
     * Idempotency key: `invoice-detail:{newDetailId}:agent-commission` -- the SAME convention
     * ordinary sale-time commission JVs use (see {@see self::voidCommissionUnearn()}'s own key
     * format), so a LATER void() of this reissued task automatically un-earns THIS commission with
     * zero special-casing, exactly like {@see self::reissuePostNewSale()}'s own sale key.
     */
    private function reissuePostCommission(
        Task $newTask,
        InvoiceDetail $newInvoiceDetail,
        int $companyId,
        \DateTimeInterface $docDate,
        ?int $userId,
        PostingService $posting
    ): ?PostedDocument {
        $agent = $newTask->agent;

        if ($agent === null) {
            return null;
        }

        $tolerance = (float) config('accounting.engine.balance_tolerance', 0.0005);
        $sell = round((float) ($newInvoiceDetail->task_price ?? 0.0), 3);
        $cost = round((float) ($newInvoiceDetail->supplier_price ?? 0.0), 3);
        $margin = round($sell - $cost, 3);

        if ($margin <= $tolerance) {
            return null;
        }

        $rate = (float) ($agent->commission ?? 0.0);
        $commission = round($rate * $margin, 3);

        if ($commission <= $tolerance) {
            return null;
        }

        $currency = (string) config('accounting.engine.base_currency');

        $draft = new DocumentDraft(
            companyId: $companyId,
            branchId: (int) ($agent->branch_id ?? 0),
            docType: 'JV',
            subType: 'AGENT_COMMISSION',
            docDate: $docDate,
            narration: 'Agent commission on reissued sale: '.$newTask->reference,
            lines: [
                new LineDraft(
                    // CT-A3 E4 (CT-F38): commission is not payroll — see config('accounting.purpose_codes').
                    purposeCode: 'COMMISSION_EXPENSE',
                    accountId: null,
                    side: 'debit',
                    amount: $commission,
                    currency: $currency,
                    originalAmount: $commission,
                    exchangeRate: 1.0,
                    transactionType: 'AGENT_COMMISSION_EXPENSE',
                    partyAccountRef: $agent->id,
                    description: 'Agent commission (reissued sale) for '.$newTask->reference,
                    invoiceId: $newInvoiceDetail->invoice_id,
                    invoiceDetailId: $newInvoiceDetail->id,
                    taskId: $newTask->id,
                    ledgerType: 'expense',
                    partyName: $agent->name,
                ),
                new LineDraft(
                    // CT-A3 E4 (CT-F38): commission is not payroll — see config('accounting.purpose_codes').
                    purposeCode: 'COMMISSION_PAYABLE',
                    accountId: null,
                    side: 'credit',
                    amount: $commission,
                    currency: $currency,
                    originalAmount: $commission,
                    exchangeRate: 1.0,
                    transactionType: 'AGENT_COMMISSION_PAYABLE',
                    partyAccountRef: $agent->id,
                    description: 'Agent commission (reissued sale) for '.$newTask->reference,
                    invoiceId: $newInvoiceDetail->invoice_id,
                    invoiceDetailId: $newInvoiceDetail->id,
                    taskId: $newTask->id,
                    ledgerType: 'payable',
                    partyName: $agent->name,
                ),
            ],
            idempotencyKey: 'invoice-detail:'.$newInvoiceDetail->id.':agent-commission',
            invoiceId: $newInvoiceDetail->invoice_id,
        );

        return $posting->post($draft, $userId);
    }

    /**
     * Fee commission on the REISSUE FEE (w6-brief.md "Commission": "Fee commission per
     * commissionable_fee_types") -- same shape and same gate as {@see self::voidFeeCommission()},
     * its own `reissue:{old}:{new}:fee-commission` key namespace (never colliding with
     * {@see self::reissuePostCommission()}'s `invoice-detail:{id}:agent-commission` key, which
     * targets the NEW SALE's own margin, not the fee).
     */
    private function reissuePostFeeCommission(
        Task $oldTask,
        Task $newTask,
        ?PostedDocument $feeDoc,
        int $companyId,
        \DateTimeInterface $docDate,
        ?int $userId,
        PostingService $posting
    ): ?PostedDocument {
        if ($feeDoc === null) {
            return null;
        }

        $agent = $newTask->agent;
        $serviceType = (string) $newTask->type;
        $commissionableTypes = $this->companyOptionJsonArray($companyId, 'accounting.commissionable_fee_types');

        if ($agent === null || $serviceType === '' || ! in_array($serviceType, $commissionableTypes, true)) {
            return null;
        }

        $feeAmount = (float) ($feeDoc->lines[0]->amount ?? 0.0);
        $rate = (float) ($agent->commission ?? 0.0);
        $commission = round($rate * $feeAmount, 3);

        if ($commission == 0.0) {
            return null;
        }

        $absCommission = abs($commission);
        $expenseSide = $commission > 0 ? 'debit' : 'credit';
        $liabilitySide = $commission > 0 ? 'credit' : 'debit';
        $currency = (string) config('accounting.engine.base_currency');

        $draft = new DocumentDraft(
            companyId: $companyId,
            branchId: (int) ($agent->branch_id ?? 0),
            docType: 'JV',
            subType: 'REISSUE_COMM',
            docDate: $docDate,
            narration: 'Agent commission on reissue fee: '.$newTask->reference,
            lines: [
                new LineDraft(
                    // CT-A3 E4 (CT-F38): commission is not payroll — see config('accounting.purpose_codes').
                    purposeCode: 'COMMISSION_EXPENSE',
                    accountId: null,
                    side: $expenseSide,
                    amount: $absCommission,
                    currency: $currency,
                    originalAmount: $absCommission,
                    exchangeRate: 1.0,
                    transactionType: 'REISSUE_FEE_COMMISSION_EXPENSE',
                    partyAccountRef: $agent->id,
                    description: 'Agent commission (reissue fee) for '.$newTask->reference,
                    taskId: $newTask->id,
                    ledgerType: 'expense',
                    partyName: $agent->name,
                ),
                new LineDraft(
                    // CT-A3 E4 (CT-F38): commission is not payroll — see config('accounting.purpose_codes').
                    purposeCode: 'COMMISSION_PAYABLE',
                    accountId: null,
                    side: $liabilitySide,
                    amount: $absCommission,
                    currency: $currency,
                    originalAmount: $absCommission,
                    exchangeRate: 1.0,
                    transactionType: 'REISSUE_FEE_COMMISSION_PAYABLE',
                    partyAccountRef: $agent->id,
                    description: 'Agent commission (reissue fee) for '.$newTask->reference,
                    taskId: $newTask->id,
                    ledgerType: 'payable',
                    partyName: $agent->name,
                ),
            ],
            idempotencyKey: 'reissue:'.$oldTask->id.':'.$newTask->id.':fee-commission',
        );

        return $posting->post($draft, $userId);
    }

    /**
     * Disposition of any OVERPAY left after re-pointing the old task's receipts onto $newTask
     * (w6-brief.md Kind 4: "remainder -> disposition"). $paidBefore is the amount already collected
     * against $oldTask (read BEFORE the receipt re-point, by the caller); when that exceeds the NEW
     * task's own sell price, the excess is disposed of exactly like {@see self::voidDisposition()}
     * (`invoice_overpay_cancel_policy`: credit default -> Cr 2632 + Credit row, refund_out -> PV,
     * manual -> audit event only, no document). When $paidBefore <= $newSell (the ordinary case --
     * the client now owes the difference, already reflected as open AR on the new sale's own
     * receivable line), this is a no-op: nothing further to disburse.
     *
     * NET OF FEE (fix, matching {@see self::voidDisposition()}'s own `$feeDoc` netting): the reissue
     * fee posted a few lines earlier in the same reissue() call (`$feeDoc`, DBN to 4135, Dr AR) is
     * money the client owes ON TOP of the new sale -- it is never itself part of what was
     * "overpaid". Before this fix, overpay was computed as paidBefore minus newSell only, so a
     * paid-in-full downgrade reissue with a fee overstated the client-advance credit by exactly the
     * fee amount (and, at the margin, could wrongly fire a disposition when the client still owed
     * money net of the fee). $feeAmount is read the same way voidDisposition() reads $feeDoc's own
     * line amount; overpay is clamped at 0 before the tolerance check, mirroring that method exactly.
     *
     * ── W7.P fix round (refund-disposition-polarity-audit.md "Side note") -- POLARITY CORRECTED
     * ───────────────────────────────────────────────────────────────────────────────────────────
     * The two line `side` values below are FLIPPED relative to the shape this method shipped with
     * before this fix (`Cr RECEIVABLE_CONTROL` / `Dr {creditPurpose}`) -- the SAME backwards
     * template {@see self::voidDisposition()} carried before its own W6.U2 fix, and
     * {@see \App\Services\Accounting\RefundPostingService::postDisposition()} carried before its
     * own W7.P fix (this method's own audit flagged this exact method as unfixed by W6.U2 and
     * worth the same scrutiny). Same running-balance shape as voidDisposition()'s own worked
     * example: after the old sale's reversal, AR sits at a CREDIT balance for the un-recouped
     * overpay; clearing that requires a further DEBIT to `RECEIVABLE_CONTROL`, not another credit
     * -- so the correct shape is `Dr RECEIVABLE_CONTROL / Cr {creditPurpose}`, matching
     * {@see self::voidDisposition()}'s corrected shape exactly. The OLD shape doubled the AR
     * credit balance instead of clearing it and left 2632/the payout leaf on the wrong side.
     */
    private function reissueDisposition(
        Task $oldTask,
        Task $newTask,
        float $paidBefore,
        float $newSell,
        ?PostedDocument $feeDoc,
        int $companyId,
        \DateTimeInterface $docDate,
        ?int $userId,
        PostingService $posting
    ): ?PostedDocument {
        $tolerance = (float) config('accounting.engine.balance_tolerance', 0.0005);
        $feeAmount = $feeDoc === null ? 0.0 : (float) ($feeDoc->lines[0]->amount ?? 0.0);
        $overpay = round(max(0.0, $paidBefore - $newSell - $feeAmount), 3);

        if ($overpay <= $tolerance) {
            return null;
        }

        $policy = $this->overpayCancelPolicy($companyId);

        if ($policy === 'manual') {
            $this->recordEvent('reissue_disposition_manual_pending', $companyId, $newTask->id, 'reissued', 'reissued', null, null, [
                'old_task_id' => $oldTask->id,
                'overpay_amount' => $overpay,
            ]);

            return null;
        }

        $currency = (string) config('accounting.engine.base_currency');

        $lines = [
            new LineDraft(
                purposeCode: 'RECEIVABLE_CONTROL',
                accountId: null,
                side: 'debit', // W7.P fix — was 'credit' (BACKWARDS, see method docblock).
                amount: $overpay,
                currency: $currency,
                originalAmount: $overpay,
                exchangeRate: 1.0,
                transactionType: 'REISSUE_DISPOSITION_RECEIVABLE',
                partyAccountRef: $newTask->client_id,
                description: 'Reissue disposition ('.$policy.'): '.$newTask->reference,
                ledgerType: 'receivable',
                partyName: $newTask->client_name,
            ),
        ];

        $creditPurpose = $policy === 'refund_out' ? 'REFUND_PAYOUT_CASH_BANK' : 'CLIENT_ADVANCE';

        $lines[] = new LineDraft(
            purposeCode: $creditPurpose,
            accountId: null,
            side: 'credit', // W7.P fix — was 'debit' (BACKWARDS, see method docblock).
            amount: $overpay,
            currency: $currency,
            originalAmount: $overpay,
            exchangeRate: 1.0,
            transactionType: 'REISSUE_DISPOSITION_'.strtoupper($policy),
            partyAccountRef: $newTask->client_id,
            description: 'Reissue disposition ('.$policy.'): '.$newTask->reference,
            ledgerType: $policy === 'credit' ? 'liability' : 'asset',
            partyName: $newTask->client_name,
        );

        $draft = new DocumentDraft(
            companyId: $companyId,
            branchId: (int) ($newTask->agent?->branch_id ?? 0),
            docType: $policy === 'credit' ? 'JV' : 'PV',
            subType: 'REISSUE_DISPO',
            docDate: $docDate,
            narration: 'Reissue overpay disposition ('.$policy.') for '.$newTask->reference,
            lines: $lines,
            idempotencyKey: 'reissue:'.$oldTask->id.':'.$newTask->id.':disposition',
        );

        $posted = $posting->post($draft, $userId);

        if ($policy === 'credit') {
            \App\Models\Credit::create([
                'company_id' => $companyId,
                'branch_id' => (int) ($newTask->agent?->branch_id ?? 0),
                'client_id' => $newTask->client_id,
                'type' => \App\Models\Credit::REFUND,
                'description' => 'Reissue credit: '.$newTask->reference,
                'amount' => $overpay,
            ]);
        }

        return $posted;
    }

    /**
     * w6-brief.md "Model": "invoices flip status via engine hooks (cancelled when all tasks void,
     * partial refund otherwise)". Mirrors RefundController::handlePaidRefund()'s own
     * all-tasks-refunded check (lines ~1252-1276) for the void case: every task whose
     * invoice_detail carries this invoice must itself be `ticket_status='void'` for the invoice to
     * flip to CANCELLED; otherwise PARTIAL_REFUND (the existing enum value this codebase already
     * uses for "some but not all tasks on this invoice have had their sale reversed" -- no separate
     * "partial void" status is invented). A no-op (returns null) when the task has no invoice at
     * all (nothing to flip).
     */
    private function refreshInvoiceStatusAfterVoid(?int $invoiceId, int $companyId): ?string
    {
        if ($invoiceId === null) {
            return null;
        }

        $invoice = Invoice::find($invoiceId);

        if ($invoice === null) {
            return null;
        }

        $taskIds = InvoiceDetail::where('invoice_id', $invoiceId)->pluck('task_id')->filter()->unique();

        if ($taskIds->isEmpty()) {
            return $invoice->status;
        }

        $voidedCount = Task::whereIn('id', $taskIds)->where('ticket_status', 'void')->count();

        $newStatus = $voidedCount >= $taskIds->count()
            ? InvoiceStatus::CANCELLED->value
            : InvoiceStatus::PARTIAL_REFUND->value;

        $invoice->status = $newStatus;
        $invoice->save();

        return $newStatus;
    }

    /**
     * W6.V Kind 2 (AUTO_VOID) -- w6-brief.md: "ProcessExpiredConfirmedTasks: never-invoiced ->
     * status only, invoiced -> AUTO_VOID through the service." {@see self::expire()} (W6.S)
     * already owns the FIRST half exactly ("only ever considers tasks that were NEVER
     * issued/invoiced" -- its own query is `whereDoesntHave('invoiceDetail')`), so this method is
     * the complementary SECOND half: `on hold`/`confirmed` tasks that DO already carry an
     * invoiceDetail (a genuinely issued/invoiced ticket sitting in a stale hold/confirmed status --
     * e.g. legacy data, or a status correction applied after issuance) whose deadline has passed
     * get voided through {@see self::void()} with `sub_type='AUTO_VOID'`, never a raw status
     * flip (ct-void-map.md §7 bug 8's own defect: the OLD command bypassed financial processing
     * entirely). Same `hold_auto_expire`/`hold_expire_grace_hours` company-option gating as
     * expire() (the brief's own Kind 2 shares that command, so the same opt-out applies to both
     * halves) -- deliberately NOT the void-specific preconditions (`ticket_status` etc.) checked
     * again here; {@see self::void()} enforces its own preconditions itself, so a task that fails
     * them (e.g. its invoice is locked) is skipped with the failure logged, not silently swallowed,
     * and does not abort the rest of the sweep.
     *
     * @return int Number of tasks actually auto-voided.
     */
    public function autoVoidExpiredInvoiced(?int $companyId = null): int
    {
        $now = Carbon::now();

        $query = Task::whereIn('status', ['on hold', 'confirmed'])
            ->whereHas('invoiceDetail')
            ->where(function ($q) {
                $q->whereNotNull('deadline_at')->orWhereNotNull('expiry_date');
            });

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        $voidedCount = 0;

        foreach ($query->get()->groupBy('company_id') as $groupCompanyId => $tasks) {
            $groupCompanyId = (int) $groupCompanyId;

            if (! $this->holdAutoExpire($groupCompanyId)) {
                continue;
            }

            $graceHours = $this->holdExpireGraceHours($groupCompanyId);

            foreach ($tasks as $task) {
                $deadline = $task->deadline_at ?? $task->expiry_date;
                if ($deadline === null) {
                    continue;
                }

                $cutoff = Carbon::instance($deadline)->addHours($graceHours);
                if ($now->lessThan($cutoff)) {
                    continue;
                }

                try {
                    $this->void($task, ['sub_type' => 'AUTO_VOID']);
                    $voidedCount++;
                } catch (\Throwable $e) {
                    Log::error('task_status.auto_void_failed', [
                        'task_id' => $task->id,
                        'company_id' => $groupCompanyId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $voidedCount;
    }

    /**
     * W6.S "Hold/confirmed follow-up lifecycle" item 2 (owner addition, 2026-08-28). Replaces
     * ProcessExpiredConfirmedTasks's Jazeera-only raw status flip (`confirmed` -> `void`, one
     * supplier family only, bypassing processTaskFinancial() entirely per
     * importer-status-contract.md Table 1 row 'void'). This method:
     *   - runs for ALL suppliers (no supplier-name filter);
     *   - only ever considers tasks that were NEVER issued/invoiced (`on hold`/`confirmed`
     *     status, no invoiceDetail);
     *   - flips status to the genuinely NEW `expired` value -- NOT `void`, which stays reserved
     *     for a real cancellation of an issued ticket (see the widening migration's own
     *     docblock);
     *   - writes an audit row via {@see recordEvent()};
     *   - NEVER routes through PostingSeam or processTaskFinancial() -- there is no document to
     *     reverse for a task that was never posted, per the brief's own "no ledger effect" rule
     *     for this whole lifecycle.
     * Gated per company by the `accounting.hold_auto_expire` option (default true) and
     * `accounting.hold_expire_grace_hours` (default 0, added to the task's own deadline before
     * it becomes eligible). The deadline used is `deadline_at` when set, else `expiry_date` (per
     * the deadline_at migration's own fallback contract).
     *
     * @param  int|null  $companyId  Restrict to one company; null processes every company (used by
     *                               the scheduled command).
     * @return int Number of tasks actually flipped to `expired`.
     */
    public function expire(?int $companyId = null): int
    {
        $now = Carbon::now();

        $query = Task::whereIn('status', ['on hold', 'confirmed'])
            ->whereDoesntHave('invoiceDetail')
            ->where(function ($q) {
                $q->whereNotNull('deadline_at')->orWhereNotNull('expiry_date');
            });

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        $expiredCount = 0;

        foreach ($query->get()->groupBy('company_id') as $groupCompanyId => $tasks) {
            $groupCompanyId = (int) $groupCompanyId;

            if (! $this->holdAutoExpire($groupCompanyId)) {
                continue;
            }

            $graceHours = $this->holdExpireGraceHours($groupCompanyId);

            foreach ($tasks as $task) {
                $deadline = $task->deadline_at ?? $task->expiry_date;
                if ($deadline === null) {
                    continue;
                }

                $cutoff = Carbon::instance($deadline)->addHours($graceHours);
                if ($now->lessThan($cutoff)) {
                    continue;
                }

                $oldStatus = $task->status;
                $task->status = 'expired';
                $task->save();

                $this->recordEvent('expire', $groupCompanyId, $task->id, $oldStatus, 'expired', null, null, [
                    'deadline' => $deadline->toIso8601String(),
                    'grace_hours' => $graceHours,
                ]);

                Log::info('task_status.expired', [
                    'task_id' => $task->id,
                    'company_id' => $groupCompanyId,
                    'from_status' => $oldStatus,
                ]);

                $expiredCount++;
            }
        }

        return $expiredCount;
    }

    /**
     * W6.S "Hold/confirmed follow-up lifecycle" -- the hold/confirmed lifecycle's third terminal
     * branch (`on hold`/`confirmed` -> `issued` | `expired` | `cancelled`), the one the previous
     * fix-round's own build report left entirely unbuilt. A human-initiated cancel (agent/admin
     * gives up on a booking that hasn't been issued), as opposed to {@see expire()}'s automatic
     * deadline-driven flip -- so, unlike expire(), this is a single-task action, not a scheduled
     * sweep, and it does not gate on `hold_auto_expire`/`hold_expire_grace_hours` at all.
     *
     * Per the brief's own item 1 ("No ledger effect for any of on hold/confirmed/expired/cancelled
     * -- only issued posts") this method NEVER writes to journal_entries/transactions itself,
     * regardless of whether a deposit exists or which `invoice_overpay_cancel_policy` the company
     * has chosen -- exactly like expire(). What differs per policy is only the AUDIT trail it
     * leaves for a human/W6.U to act on next:
     *   - no deposit at all -> plain status flip, one audit row, nothing else.
     *   - deposit present, policy=`credit` (default) -> the deposit simply stays exactly where the
     *     original receipt already posted it (a 2632 client-advance balance) -- disposition IS
     *     "do nothing further", so the audit row records that explicitly rather than silently
     *     matching the no-deposit case.
     *   - deposit present, policy=`manual` -> audit row flags the deposit for a human decision;
     *     no automatic disposition attempted.
     *   - deposit present, policy=`refund_out` -> the brief frames this as producing a PV *draft*
     *     (w4/w5's own draft-then-approve pattern, never an atomic auto-post) -- building that
     *     draft needs an operator-chosen bank/cash leaf this automatic status transition has no
     *     way to infer, so this method writes an audit row flagging "refund_out disposition
     *     pending an operator-initiated PV" rather than fabricating one. See this sub-wave's build
     *     report for why this one policy branch stops at the audit-flag stage.
     *
     * @throws \RuntimeException when $task is not currently `on hold`/`confirmed` (mirrors
     *                           expire()'s own "never touches an issued task" guarantee, but as a
     *                           hard guard here since this is a direct single-task action a
     *                           caller could invoke on the wrong task by mistake).
     */
    public function cancel(Task $task, ?string $reason = null): Task
    {
        if (! in_array($task->status, ['on hold', 'confirmed'], true)) {
            throw new \RuntimeException(
                "TaskStatusService::cancel() called on task {$task->id} with status '{$task->status}' -- only 'on hold'/'confirmed' tasks can be cancelled through this lifecycle."
            );
        }

        $companyId = (int) $task->company_id;
        $oldStatus = $task->status;
        $deposit = $this->depositHeld($task);

        $task->status = 'cancelled';
        $task->save();

        $meta = ['reason' => $reason, 'deposit_held' => $deposit];

        if ($deposit <= 0.0005) {
            $this->recordEvent('cancel', $companyId, $task->id, $oldStatus, 'cancelled', null, null, $meta);
        } else {
            $policy = $this->overpayCancelPolicy($companyId);
            $meta['disposition_policy'] = $policy;

            $this->recordEvent('cancel', $companyId, $task->id, $oldStatus, 'cancelled', null, null, $meta);

            $dispositionEvent = match ($policy) {
                'refund_out' => 'cancel_disposition_refund_out_pending',
                'manual' => 'cancel_disposition_manual_pending',
                default => 'cancel_disposition_credit_retained',
            };
            $this->recordEvent($dispositionEvent, $companyId, $task->id, 'cancelled', 'cancelled', null, null, $meta);
        }

        Log::info('task_status.cancelled', [
            'task_id' => $task->id,
            'company_id' => $companyId,
            'from_status' => $oldStatus,
            'deposit_held' => $deposit,
        ]);

        return $task;
    }

    /**
     * Sum of POSTED (approved -> transaction_id set) `credit`-type receipts tagged with this
     * task's id -- the deposit an on-hold/confirmed task is currently holding (w6-brief.md
     * "Hold/confirmed follow-up lifecycle" item 3). Deliberately reads `invoice_receipts.amount`
     * (this sub-wave's own durable document row), never `journal_entries.balance`/
     * `accounts.actual_balance` (feedback_accounting_boundary — forbidden reads).
     *
     * W6.U2 fix (w6u-verify-2.md finding 1): excludes rows {@see self::
     * applyHoldDepositToInvoice()} has already CONSUMED (`applied_at IS NOT NULL`) -- this method
     * answers "how much is this task STILL holding, unconsumed", not "how much was ever
     * deposited". A consumed deposit is read back through {@see self::paidAmountForTask()} via
     * its `InvoicePartial` row instead (never through this method again), which is what stops
     * {@see self::voidDisposition()} from disposing of it twice. `cancel()`'s own W6.S parity
     * case (a task that was NEVER issued) is unaffected: `applied_at` is only ever set by
     * `applyHoldDepositToInvoice()`, which only ever runs from `issue()`.
     */
    public function depositHeld(Task $task): float
    {
        return (float) InvoiceReceipt::where('task_id', $task->id)
            ->where('status', 'approved')
            ->whereNull('applied_at')
            ->sum('amount');
    }

    /**
     * Same key/default RefundPostingService/RefundController already use for the client-net
     * disposition after a refund (`accounting.refund.invoice_overpay_cancel_policy`, default
     * `credit`) -- w6-brief.md explicitly says the hold/confirmed lifecycle's cancel-with-deposit
     * disposition reuses this EXISTING option, "do not invent a second policy option for this
     * case".
     */
    public function overpayCancelPolicy(int $companyId): string
    {
        return (string) $this->companyOption($companyId, 'accounting.refund.invoice_overpay_cancel_policy', 'credit');
    }

    /**
     * W6.S audit trail (see task_status_events migration docblock for why this is a dedicated
     * table rather than the engine's Log::-based accounting.* convention). Used by mapStatus()'s
     * needs_review path, expire(), and available to the W6.U supplier-status-map/follow-up
     * screens for their own admin-write audit rows.
     */
    public function recordEvent(
        string $event,
        ?int $companyId,
        ?int $taskId,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?string $channel = null,
        ?string $rawStatus = null,
        array $meta = []
    ): void {
        TaskStatusEvent::create([
            'company_id' => $companyId,
            'task_id' => $taskId,
            'event' => $event,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'channel' => $channel,
            'raw_status' => $rawStatus,
            'meta' => $meta,
            'created_by' => Auth::id(),
            'created_at' => now(),
        ]);
    }

    /**
     * W6.S item (6) -- "options bulk_void_mode, commissionable_fee_types registered". Consumed by
     * {@see self::bulkVoid()} (W6.B) below. `commissionable_fee_types` already exists (W4/refund
     * lane, `SettingController::storeAccountingSettings()`) -- reused, not re-registered.
     */
    public function bulkVoidMode(int $companyId): string
    {
        return (string) $this->companyOption($companyId, 'accounting.bulk_void_mode', 'atomic');
    }

    /**
     * W6.B (w6-brief.md "## Kinds" 5 / "Model" -- "BULK VOID"). The single owner of
     * `POST /tasks/bulk-void` (via `TaskController::bulkVoid()`). Voids every task in $taskIds by
     * delegating to {@see self::void()} for each one -- no reimplementation of the void mechanics
     * here, this method is purely the batching/transaction-shape layer around it.
     *
     * ── ONE outer transaction, per the brief's own words ────────────────────────────────────────
     * The ENTIRE method body runs inside a single outer `DB::transaction()`. `$opts['mode']`
     * (falling back to the company's `bulk_void_mode` setting, default `atomic`) decides what
     * happens PER TASK inside that one outer transaction:
     *   - `atomic`: each {@see self::void()} call runs directly, still nested inside the one
     *     outer transaction -- Laravel's Connection automatically issues a SAVEPOINT for
     *     void()'s own internal `DB::transaction()` call once we are already inside one (see
     *     Illuminate\Database\Connection::transaction()/createTransaction()), but this method
     *     itself does NOT catch any exception a task throws: an uncaught exception propagates
     *     straight out of the outer `DB::transaction()` closure, which rolls the WHOLE thing back
     *     -- every task voided earlier in this same call, undone, exactly "any failure rolls back
     *     all".
     *   - `per_task_report`: each task's {@see self::void()} call is wrapped in ITS OWN
     *     `DB::transaction()` closure here -- since we are already inside the one outer
     *     transaction this method opened, that inner call is, again, a SAVEPOINT (never a second
     *     top-level transaction; there is exactly one BEGIN for the whole request, matching the
     *     brief's "ONE outer transaction" instruction literally even in this mode). A failing
     *     task's exception is caught immediately around that one savepoint -- Laravel's own
     *     `Connection::rollBack()` rolls back TO that savepoint (not the whole transaction) --
     *     recorded as a failed result row, and the loop continues to the next task; the outer
     *     transaction still commits at the end, so every task that succeeded stays voided. No
     *     second, independent top-level transaction is ever opened per task (grepped: the only
     *     `DB::transaction()` call sites in this method and in {@see self::void()} itself).
     *
     * ── Result shape ─────────────────────────────────────────────────────────────────────────────
     * Returns `['mode' => 'atomic'|'per_task_report', 'results' => list<array{task_id: int,
     * success: bool, error: ?string, result: ?array}>]` -- `result` is {@see self::void()}'s own
     * return array on success, null on failure. In `atomic` mode this array is only ever returned
     * on a fully-successful run (a failure throws out of the transaction instead -- the caller,
     * `TaskController::bulkVoid()`, catches that and reports the whole batch as failed with zero
     * `results` entries voided, per the brief's "any failure rolls back all").
     *
     * ── Duplicate ids ────────────────────────────────────────────────────────────────────────────
     * `$taskIds` is de-duplicated up front (`array_unique`) -- a duplicated id in the submitted
     * batch produces exactly one result row, not two (the second void() call on the same task
     * would otherwise just be void()'s own idempotency no-op, harmless but a misleading double
     * entry in the report).
     *
     * @param  int[]  $taskIds
     * @param  array{mode?: string, sub_type?: string, fee?: float|null, user_id?: int|null,
     *               company_id?: int|null}  $opts
     * @return array{mode: string, results: list<array{task_id: int, success: bool, error: ?string,
     *               result: ?array}>}
     */
    public function bulkVoid(array $taskIds, array $opts = []): array
    {
        $taskIds = array_values(array_unique(array_map('intval', $taskIds)));

        $voidOpts = [
            'sub_type' => (string) ($opts['sub_type'] ?? 'VOID'),
            'user_id' => $opts['user_id'] ?? Auth::id(),
        ];

        if (array_key_exists('fee', $opts)) {
            $voidOpts['fee'] = $opts['fee'];
        }

        $companyId = (int) ($opts['company_id'] ?? $this->resolveBulkVoidCompanyId($taskIds));
        $mode = (string) ($opts['mode'] ?? $this->bulkVoidMode($companyId));
        $mode = in_array($mode, ['atomic', 'per_task_report'], true) ? $mode : 'atomic';

        if (empty($taskIds)) {
            return ['mode' => $mode, 'results' => []];
        }

        return DB::transaction(function () use ($taskIds, $voidOpts, $mode) {
            $results = [];

            foreach ($taskIds as $taskId) {
                if ($mode === 'atomic') {
                    // No local try/catch: an exception here propagates straight out of THIS
                    // outer DB::transaction() closure, rolling back everything -- see this
                    // method's own docblock.
                    $task = Task::withoutGlobalScopes()->whereNull('deleted_at')->findOrFail($taskId);
                    $outcome = $this->void($task, $voidOpts);
                    $results[] = ['task_id' => $taskId, 'success' => true, 'error' => null, 'result' => $outcome];

                    continue;
                }

                // per_task_report: this task's own work runs as a SAVEPOINT of the one outer
                // transaction we are already inside (see method docblock) -- a failure here rolls
                // back only this task's savepoint, never the tasks already voided earlier in the
                // loop, and never the outer transaction itself.
                try {
                    $outcome = DB::transaction(function () use ($taskId, $voidOpts) {
                        $task = Task::withoutGlobalScopes()->whereNull('deleted_at')->findOrFail($taskId);

                        return $this->void($task, $voidOpts);
                    });
                    $results[] = ['task_id' => $taskId, 'success' => true, 'error' => null, 'result' => $outcome];
                } catch (\Throwable $e) {
                    Log::warning('task_status.bulk_void_task_failed', [
                        'task_id' => $taskId,
                        'mode' => $mode,
                        'error' => $e->getMessage(),
                    ]);
                    $results[] = ['task_id' => $taskId, 'success' => false, 'error' => $e->getMessage(), 'result' => null];
                }
            }

            return ['mode' => $mode, 'results' => $results];
        });
    }

    /**
     * Resolves the company id to read `bulk_void_mode` from when the caller did not pass one
     * explicitly -- the first resolvable task's own `company_id` (a bulk-void batch is always
     * scoped to one company in practice; TaskController::bulkVoid() authorizes/queries within the
     * authenticated user's own company). Returns 0 (falls through to the `atomic` default, since
     * {@see self::bulkVoidMode()}'s own `companyOption()` lookup is a safe no-op for company id 0)
     * when no task in the batch resolves at all -- the `findOrFail()` inside the transaction below
     * is what actually reports a missing/foreign task as a per-task failure, not this method.
     */
    private function resolveBulkVoidCompanyId(array $taskIds): int
    {
        if (empty($taskIds)) {
            return 0;
        }

        $task = Task::withoutGlobalScopes()->whereNull('deleted_at')->whereIn('id', $taskIds)->first();

        return (int) ($task->company_id ?? 0);
    }

    public function holdAutoExpire(int $companyId): bool
    {
        return $this->companyOptionBool($companyId, 'accounting.hold_auto_expire', true);
    }

    public function holdExpireGraceHours(int $companyId): int
    {
        return $this->companyOptionInt($companyId, 'accounting.hold_expire_grace_hours', 0);
    }

    /**
     * W6.U "Reminders" (owner addition, 2026-08-28) -- comma-separated hours-before-deadline the
     * `reminder:generate-deadlines` command creates one Reminder row per, default "24,2" per the
     * brief's own words. Returns a de-duplicated list of positive ints; a malformed/empty setting
     * falls back to the shipped default rather than generating zero reminders.
     *
     * @return int[]
     */
    public function holdReminderOffsetsHours(int $companyId): array
    {
        $raw = (string) $this->companyOption($companyId, 'accounting.hold_reminder_offsets_hours', '24,2');

        $offsets = array_values(array_unique(array_filter(
            array_map(static fn (string $part): int => (int) trim($part), explode(',', $raw)),
            static fn (int $hours): bool => $hours > 0
        )));

        return $offsets !== [] ? $offsets : [24, 2];
    }

    /**
     * W6.U "Reminders" -- `hold_client_nudge` company option, default false (w6-brief.md: "ship
     * this sub-wave WhatsApp-only ... optional client nudge is company option hold_client_nudge
     * (bool, default false)").
     */
    public function holdClientNudge(int $companyId): bool
    {
        return $this->companyOptionBool($companyId, 'accounting.hold_client_nudge', false);
    }

    /**
     * W6.U "Task actions" -- read-only preview for the reissue screen's "shows a DBN/CRN preview
     * ... before submit" requirement. Computes EXACTLY what {@see self::reissue()} itself would
     * compute for the fare-difference figure (same two source fields, same
     * {@see self::reissueFareDifference()} call), without posting anything -- so a feature test
     * can assert the preview literally equals what the real reissue() call later posts (w6-brief.md
     * verify criterion 3: "assert the DBN/CRN preview amount equals the posted document's amount").
     *
     * @return array{old_sell: float, new_sell: float, fare_difference: array{type: string, amount: float}}
     */
    public function previewReissue(Task $oldTask, Task $newTask): array
    {
        $oldSell = round((float) ($oldTask->invoiceDetail->task_price ?? $oldTask->price ?? 0.0), 3);
        $newSell = round((float) ($newTask->price ?? 0.0), 3);

        return [
            'old_sell' => $oldSell,
            'new_sell' => $newSell,
            'fare_difference' => $this->reissueFareDifference($oldSell, $newSell),
        ];
    }

    /**
     * W6.U "Task actions" -- read-only preview for the void-with-fee screen's "fee entry pre-filled
     * from the fee schedule ... for the task's service type" requirement. Wraps
     * {@see self::resolveFeeFromSchedule()} (the SAME method {@see self::void()}/{@see self::reissue()}
     * call at posting time) plus the raw override policy string, so the UI can show both the
     * pre-filled figure and whether typing a different number will need approval -- without
     * duplicating either lookup.
     *
     * @return array{schedule_fee: float, override_policy: string}
     */
    public function previewFee(int $companyId, string $serviceType, float $sellAmount): array
    {
        return [
            'schedule_fee' => $this->resolveFeeFromSchedule($companyId, $serviceType, $sellAmount, null),
            'override_policy' => (string) Setting::getByKey($companyId, "accounting.refund.fee_schedule.{$serviceType}.override", 'needs_approval'),
        ];
    }

    private function companyOption(int $companyId, string $key, mixed $default): mixed
    {
        $setting = Setting::where('company_id', $companyId)->where('key', $key)->first();

        return $setting?->value ?? $default;
    }

    private function companyOptionBool(int $companyId, string $key, bool $default): bool
    {
        $value = $this->companyOption($companyId, $key, $default);

        return is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function companyOptionInt(int $companyId, string $key, int $default): int
    {
        return (int) $this->companyOption($companyId, $key, $default);
    }

    /**
     * Same as {@see companyOption()} but for a JSON-encoded array option (e.g.
     * `accounting.commissionable_fee_types`) -- mirrors
     * RefundPostingService::companyOptionJsonArray() exactly. Returns an empty array -- never
     * null, never a scalar -- for a missing setting, malformed JSON, or a JSON value that didn't
     * decode to an array.
     */
    private function companyOptionJsonArray(int $companyId, string $key): array
    {
        $decoded = json_decode((string) $this->companyOption($companyId, $key, '[]'), true);

        return is_array($decoded) ? $decoded : [];
    }
}
