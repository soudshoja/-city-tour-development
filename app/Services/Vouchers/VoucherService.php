<?php

namespace App\Services\Vouchers;

use App\Models\Task;
use App\Models\TaskPackage;
use App\Models\TravelVoucher;
use App\Models\VoucherNumberSequence;
use App\Models\VoucherTemplate;
use App\Services\Vouchers\Exceptions\VoucherCompanyMismatchException;
use App\Services\Vouchers\Exceptions\VoucherSubjectDeadException;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Issue + lifecycle for travel_vouchers (Step 4, plan section 10, 13-BIS, 16).
 *
 * issue() is the only entry point that creates a NEW voucher record: it
 * freezes the resolved VoucherDataRepository payload into `snapshot`,
 * mints the per-company `voucher_number` (its own sequence -- never
 * serial_schemas, plan section 14.6) and the 64-char public `token` (minted by
 * TravelVoucher::booted(), never chosen here), and renders the PDF via the
 * app's existing dompdf engine (composer.json: barryvdh/laravel-dompdf,
 * confirmed -- no new library). Available regardless of paid/unpaid; this
 * class never reads a payment state to decide whether to issue (plan section 9,
 * section 10.1: "the voucher is never gated on payment").
 *
 * The remaining methods are the section 13-BIS lifecycle primitives the schema
 * was built for (reissue/refund keep the original and spawn a new one;
 * void updates the same voucher in place or expires it to "Cancel V").
 * None of them is wired to an automatic trigger in this step -- that would
 * mean hooking into wherever tasks transition to void/reissued/refund
 * across the app, which is out of Step 4's explicit scope (issue, public
 * route, PDF, send, legacy-route fix). A future scheduled sweep or a
 * task-status observer calls these; staff can also invoke cancel()
 * manually today via VoucherController::cancel().
 *
 * Every method takes an explicit $companyId and re-asserts every record
 * it touches actually belongs to it (plan section 2.4, section 11.2) -- no reliance on
 * Auth::user() or the BelongsToCompany global scope anywhere in this class.
 */
class VoucherService
{
    public function __construct(private readonly VoucherDataRepository $repository) {}

    /**
     * Issue a new voucher for a Task or a TaskPackage (plan section 10.1). The
     * caller resolves $template (VoucherTemplate::resolveEffective()) and
     * is responsible for it actually matching $subject's type -- this
     * method only guards company ownership, not type/template pairing.
     *
     * F4: a Task subject that is itself dead (status=void, or superseded
     * by another task's original_task_id) is refused with
     * VoucherSubjectDeadException rather than silently issuing a voucher
     * for it -- see assertSubjectNotDead() below.
     */
    public function issue(Model $subject, VoucherTemplate $template, string $language, int $companyId, ?int $userId): TravelVoucher
    {
        $this->assertSubjectSupported($subject);
        $this->assertSubjectBelongsToCompany($subject, $companyId);
        $this->assertTemplateBelongsToCompany($template, $companyId);
        $this->assertSubjectNotDead($subject, $companyId);

        return DB::transaction(function () use ($subject, $template, $language, $companyId, $userId) {
            $voucherNumber = $this->nextVoucherNumber($companyId);

            $voucher = new TravelVoucher([
                'company_id' => $companyId,
                'voucher_number' => $voucherNumber,
                'voucher_template_id' => $template->id,
                'language' => $language,
                'snapshot' => [], // placeholder -- snapshot column is NOT NULL; frozen for real just below
                'version' => 1,
                'status' => TravelVoucher::STATUS_ISSUED,
                'created_by' => $userId,
            ]);
            $voucher->subject()->associate($subject);
            $voucher->save(); // token minted in TravelVoucher::booted()

            $this->resolveAndFreezeSnapshot($voucher, $subject, $template, $companyId);
            $this->renderPdf($voucher, $template, $companyId);

            return $voucher->fresh();
        });
    }

    /**
     * Section 13-BIS.A -- reissue/refund: the ORIGINAL survives, annotated with
     * what happened, and a NEW voucher is issued for the replacement task.
     * Both exist; the history is deliberately visible on the original's
     * public link (owner's own words: "we show them the action on
     * original and generate new one").
     *
     * BLOCKER B3 fix: the original's public page (HTML and, when its own
     * language is EN, PDF) must actually SAY it was superseded and name
     * the replacement -- before this fix the original kept serving its
     * unchanged pre-supersede snapshot forever (only `status` and
     * `superseded_by_id` were written; nothing re-rendered). The status
     * flip + link is presentation state layered on top of the still-frozen
     * `snapshot` (TravelVoucher::crossReferenceContext(), computed fresh
     * from `superseded_by_id` at render time -- see PublicVoucherController
     * and renderPdf() below), so re-rendering the STORED PDFs here is only
     * needed to keep those files in sync with the same live facts the
     * public HTML route already recomputes on every request.
     *
     * BOTH sides are re-rendered, in this order, and only AFTER the
     * `superseded_by_id` link is saved -- verified live 2026-08-27: issue()
     * (called on $newTask below) renders the NEW voucher's PDF from
     * inside its OWN transaction step, before $original->superseded_by_id
     * is set, so at that point $new->crossReferenceContext() still finds
     * no `previousVersion` and its stored PDF silently omits the "replaces
     * VCH-xxx" line while the live HTML route (computed fresh on every
     * request, well after this transaction commits) already shows it
     * correctly -- a real PDF/HTML mismatch caught by actually diffing
     * the rendered PDF text against the HTML output, not just re-reading
     * the code. Re-rendering $new here, after the link exists, is what
     * fixes it.
     */
    public function supersede(TravelVoucher $original, Task $newTask, VoucherTemplate $template, string $language, int $companyId, ?int $userId, string $reason): TravelVoucher
    {
        if (! in_array($reason, [TravelVoucher::STATUS_REISSUED, TravelVoucher::STATUS_REFUNDED], true)) {
            throw new InvalidArgumentException("supersede() reason must be 'reissued' or 'refunded', got: {$reason}");
        }

        $this->assertVoucherBelongsToCompany($original, $companyId);

        return DB::transaction(function () use ($original, $newTask, $template, $language, $companyId, $userId, $reason) {
            $new = $this->issue($newTask, $template, $language, $companyId, $userId);

            $original->forceFill([
                'status' => $reason,
                'superseded_by_id' => $new->id,
            ])->save();

            $this->renderPdf($original, $original->voucherTemplate, $companyId);
            $this->renderPdf($new, $new->voucherTemplate, $companyId);

            return $new->fresh();
        });
    }

    /**
     * Section 13-BIS ordering resolution: the FIRST thing that happens on any
     * void is this -- kill the public link immediately (safe whichever of
     * B/C it later becomes), before it's known which branch applies.
     */
    public function markVoidPending(TravelVoucher $voucher, int $companyId): TravelVoucher
    {
        $this->assertVoucherBelongsToCompany($voucher, $companyId);
        $voucher->forceFill(['status' => TravelVoucher::STATUS_VOID_PENDING])->save();

        return $voucher;
    }

    /**
     * Section 13-BIS.B -- void followed by the SAME details re-issued: the
     * EXISTING voucher record is updated in place. Same voucher_number,
     * same token (never regenerated), so a link already sent to a
     * customer keeps working and silently shows the corrected document.
     * The prior snapshot moves into snapshot_history (operator-only,
     * `$hidden` on the model -- plan section 13-BIS.B) rather than being shown or
     * superseded. No trace of the void reaches any client-facing surface.
     */
    public function updateInPlaceAfterVoid(TravelVoucher $voucher, Task $newTask, int $companyId, ?int $userId): TravelVoucher
    {
        $this->assertVoucherBelongsToCompany($voucher, $companyId);
        $this->assertSubjectBelongsToCompany($newTask, $companyId);

        return DB::transaction(function () use ($voucher, $newTask, $companyId) {
            $history = $voucher->snapshot_history ?? [];
            $history[] = [
                'replaced_at' => now()->toIso8601String(),
                'snapshot' => $voucher->snapshot,
                'previous_subject_type' => $voucher->subject_type,
                'previous_subject_id' => $voucher->subject_id,
            ];

            $template = $voucher->voucherTemplate;

            $voucher->subject()->associate($newTask);
            $voucher->status = TravelVoucher::STATUS_ISSUED;
            $voucher->version = $voucher->version + 1;
            $voucher->snapshot_history = $history;
            $voucher->save();

            $this->resolveAndFreezeSnapshot($voucher, $newTask, $template, $companyId);
            $this->renderPdf($voucher, $template, $companyId);

            return $voucher->fresh();
        });
    }

    /**
     * Section 13-BIS.C -- void with nothing qualifying arriving in the grace
     * window: status cancelled ("Cancel V" -- an internal label only,
     * TravelVoucher::STATUS_CANCELLED's own docblock; the public route
     * never surfaces it, it just 404s neutrally). Staff can also call
     * this directly to kill a voucher outside the void flow.
     */
    public function cancel(TravelVoucher $voucher, int $companyId): TravelVoucher
    {
        $this->assertVoucherBelongsToCompany($voucher, $companyId);
        $voucher->forceFill(['status' => TravelVoucher::STATUS_CANCELLED])->save();

        return $voucher;
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    protected function resolveAndFreezeSnapshot(TravelVoucher $voucher, Model $subject, VoucherTemplate $template, int $companyId): void
    {
        $voucherMeta = [
            'number' => $voucher->voucher_number,
            'version' => $voucher->version,
            'issued_at' => optional($voucher->created_at)->toIso8601String() ?? now()->toIso8601String(),
            'language' => $voucher->language,
            'qr_url' => null, // Phase C (plan section 14.6 note / section 13 Phase C) -- not built in this step
        ];

        $payload = $subject instanceof TaskPackage
            ? $this->repository->payloadForPackage($subject, $companyId, $template, $voucherMeta)
            : $this->repository->payloadForTask($subject, $companyId, $template, $voucherMeta);

        $voucher->forceFill(['snapshot' => $payload])->save();
    }

    /**
     * Renders the voucher's CURRENT snapshot to PDF via dompdf (the app's
     * existing engine -- composer.json: barryvdh/laravel-dompdf ^3.1,
     * confirmed in the plan section 1 fact 1 and this step's own instructions;
     * no new library added) and stores it on the PRIVATE local disk
     * (plan section 11.6: "never public/", storage/app root -- never Storage
     * disk('public')). Same view_key the public HTML route and the
     * Settings-gallery preview both already render, so the PDF and the
     * public HTML are always pixel-identical for identical data.
     *
     * BLOCKER B2 -- restored plan section 12: "PDF attachment = EN templates
     * only in v1". dompdf cannot shape Arabic (proven live 2026-08-27, see
     * vouchers/partials/styles.blade.php for the codepoint evidence that
     * overturns the earlier "renders well" finding), so an ARB voucher
     * never gets a stored PDF at all -- `pdf_path` stays null and this
     * method is a deliberate no-op for one. Called again from supersede()
     * on the ORIGINAL voucher purely to keep its stored file in sync with
     * the cross-reference banner (BLOCKER B3); for an ARB original that
     * again resolves to this same no-op, which is correct -- it never had
     * a file to begin with.
     */
    protected function renderPdf(TravelVoucher $voucher, VoucherTemplate $template, int $companyId): void
    {
        if ($voucher->language === TravelVoucher::LANGUAGE_AR) {
            return;
        }

        $pdf = Pdf::loadView($template->view_key, [
            'payload' => $voucher->snapshot,
            'isPdf' => true,
            'sample' => false,
            'voucherStatus' => $voucher->status,
            'crossReference' => $voucher->crossReferenceContext(),
        ]);

        $filename = "{$voucher->voucher_number}-v{$voucher->version}.pdf";
        $relativePath = "vouchers/{$companyId}/{$filename}";

        Storage::disk('local')->put($relativePath, $pdf->output());

        $voucher->forceFill(['pdf_path' => $relativePath])->save();
    }

    /**
     * Per-company VCH-{seq} numbering (plan section 14.6) on its OWN table --
     * see VoucherNumberSequence's docblock for why this does not reuse
     * the accounting-adjacent `sequences` table PaymentController already
     * uses for a differently-shaped voucher number. lockForUpdate() must
     * run inside the caller's transaction (it does -- issue() wraps this).
     */
    protected function nextVoucherNumber(int $companyId): string
    {
        $sequence = VoucherNumberSequence::where('company_id', $companyId)->lockForUpdate()->first();

        if (! $sequence) {
            $sequence = VoucherNumberSequence::create(['company_id' => $companyId, 'current_sequence' => 0]);
            $sequence = VoucherNumberSequence::where('company_id', $companyId)->lockForUpdate()->first();
        }

        $next = $sequence->current_sequence + 1;
        $sequence->update(['current_sequence' => $next]);

        return sprintf('VCH-%06d', $next);
    }

    protected function assertSubjectSupported(Model $subject): void
    {
        if (! $subject instanceof Task && ! $subject instanceof TaskPackage) {
            throw new InvalidArgumentException('Voucher subject must be a Task or TaskPackage, got: '.get_class($subject));
        }
    }

    /**
     * F4: refuse to issue a voucher for a dead Task — its own status is
     * 'void' or 'refund', or another task in this company points at it via
     * original_task_id (it has been superseded). Packages are not
     * checked here (a package's own member tasks are each already
     * subject to this same rule wherever they are issued individually;
     * this step does not extend the check into per-item package
     * validation, which is out of scope).
     *
     * BUG 2 fix, verified live on PNR 4J9RCM: task 19621 (status=refund,
     * original_task_id=18254) had no guard against issuing it directly --
     * VoucherDataRepository::deadSiblingIds() already excludes a `refund`
     * status from ROSTER rendering (so it renders zero passenger rows),
     * but that exclusion never reached this issuance guard, so the
     * voucher endpoint still returned HTTP 200 for a refunded task with
     * no travel details behind it. `refund` now refuses issuance the same
     * way `void` always has, with the same clean staff-facing message
     * pattern.
     */
    protected function assertSubjectNotDead(Model $subject, int $companyId): void
    {
        if (! $subject instanceof Task) {
            return;
        }

        if ($subject->status === 'void') {
            throw VoucherSubjectDeadException::forTask($subject->id, 'the task itself is void.');
        }

        if ($subject->status === 'refund') {
            throw VoucherSubjectDeadException::forTask($subject->id, 'the task itself is a refund.');
        }

        $isSuperseded = Task::where('company_id', $companyId)
            ->where('original_task_id', $subject->id)
            ->exists();

        if ($isSuperseded) {
            throw VoucherSubjectDeadException::forTask($subject->id, 'it has been superseded by a later task (reissued/refunded/voided).');
        }
    }

    protected function assertSubjectBelongsToCompany(Model $subject, int $companyId): void
    {
        if ((int) $subject->company_id !== $companyId) {
            throw VoucherCompanyMismatchException::forSubject(
                $subject instanceof Task ? 'task' : 'task_package',
                $subject->id,
                $subject->company_id,
                $companyId
            );
        }
    }

    protected function assertTemplateBelongsToCompany(VoucherTemplate $template, int $companyId): void
    {
        if ($template->company_id !== null && (int) $template->company_id !== $companyId) {
            throw VoucherCompanyMismatchException::forSubject('voucher_template', $template->id, $template->company_id, $companyId);
        }
    }

    protected function assertVoucherBelongsToCompany(TravelVoucher $voucher, int $companyId): void
    {
        if ((int) $voucher->company_id !== $companyId) {
            throw VoucherCompanyMismatchException::forSubject('travel_voucher', $voucher->id, $voucher->company_id, $companyId);
        }
    }
}
