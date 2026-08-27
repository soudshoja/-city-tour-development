<?php

namespace App\Http\Controllers;

use App\Models\TravelVoucher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * The public tokenised voucher route (Step 4 item 2, plan section 3.6,
 * section 11.1): "/travel-voucher/{companyId}/{token}" -- collision-free
 * against the existing receipt-voucher public link and mirrors its shape
 * (ReceiptVoucherController::show, InvoiceController::generatePdf).
 *
 * No auth, no session (routes/web.php: this group sits OUTSIDE the outer
 * `Route::middleware(['auth'])` wrapper). Every lookup therefore carries
 * BOTH $companyId and $token explicitly -- TravelVoucher::scopeForPublicToken()
 * is the only allowed shape, and it also excludes every status in
 * TravelVoucher::PUBLICLY_DEAD_STATUSES (void_pending / cancelled /
 * superseded) so a killed link 404s neutrally with NO internal code, NO
 * "Cancel V", and NO stale data ever reachable this way (plan section 13-BIS).
 *
 * Renders from `snapshot` only -- never re-resolves live data (plan
 * section 11.1: "render from snapshot, else 404"), so this controller never
 * touches VoucherDataRepository at all.
 *
 * BLOCKER B3 (§13-BIS.A cross-referencing) and BLOCKER B2 (Arabic PDF)
 * both add PRESENTATION state alongside `payload` -- `voucherStatus` and
 * `crossReference` (TravelVoucher::crossReferenceContext(), computed
 * fresh from the live `superseded_by_id` / `previousVersion` relations)
 * are never written into the frozen snapshot; they ride next to it in the
 * view array exactly like `$isPdf`/`$sample` already do.
 */
class PublicVoucherController extends Controller
{
    public function show(int $companyId, string $token): View|Response
    {
        $voucher = TravelVoucher::query()
            ->with('voucherTemplate')
            ->forPublicToken($companyId, $token)
            ->first();

        if (! $voucher || ! $voucher->voucherTemplate) {
            return $this->unavailable();
        }

        return view($voucher->voucherTemplate->view_key, [
            'payload' => $voucher->snapshot,
            'isPdf' => false,
            'sample' => false,
            'voucherStatus' => $voucher->status,
            'crossReference' => $voucher->crossReferenceContext(),
        ]);
    }

    /**
     * PDF variant of the same public link. Streams the file rendered at
     * issue time (VoucherService::renderPdf()) when it is still present on
     * the private disk; falls back to rendering the frozen snapshot again
     * on the fly only if that file is somehow missing, so the public PDF
     * link never hard-fails just because a stored file was cleaned up.
     *
     * BLOCKER B2 -- an Arabic voucher never gets a live-rendered fallback
     * here. dompdf (barryvdh/laravel-dompdf, this app's only PDF engine)
     * performs no Arabic shaping and no bidi reordering, full stop --
     * checked against a real rendered file (VCH-000011, hotel/ARB) 2026-08-27
     * (see vouchers/partials/styles.blade.php for the full finding; the
     * exact codepoint counts are deliberately NOT cited here -- two
     * independent extractors of that same PDF disagreed with each other
     * and neither is trustworthy enough to cite). Plan §12
     * restored: "PDF attachment = EN templates only in v1" --
     * VoucherService::renderPdf() never stores a pdf_path for an ARB
     * voucher in the first place (so the branch below never has a stored
     * file to serve for one), and this fallback must not paper over that
     * by generating a broken PDF on the fly. An ARB voucher gets an
     * honest "view it as a web page" response instead.
     */
    public function pdf(int $companyId, string $token): Response
    {
        $voucher = TravelVoucher::query()
            ->with('voucherTemplate')
            ->forPublicToken($companyId, $token)
            ->first();

        if (! $voucher || ! $voucher->voucherTemplate) {
            return $this->unavailable();
        }

        if ($voucher->language === TravelVoucher::LANGUAGE_AR) {
            return response()->view('vouchers.public.pdf-unavailable-arabic', [
                'link' => route('travel-voucher.show', ['companyId' => $companyId, 'token' => $token]),
            ], 200);
        }

        $filename = "{$voucher->voucher_number}.pdf";

        if ($voucher->pdf_path && Storage::disk('local')->exists($voucher->pdf_path)) {
            return response(Storage::disk('local')->get($voucher->pdf_path), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "inline; filename=\"{$filename}\"",
            ]);
        }

        $pdf = Pdf::loadView($voucher->voucherTemplate->view_key, [
            'payload' => $voucher->snapshot,
            'isPdf' => true,
            'sample' => false,
            'voucherStatus' => $voucher->status,
            'crossReference' => $voucher->crossReferenceContext(),
        ]);

        return $pdf->stream($filename);
    }

    /**
     * A cancelled/void_pending/superseded/nonexistent voucher gets a
     * neutral "no longer available" page (plan section 13-BIS.C: "a customer
     * holding a dead link should get a neutral... not an internal code")
     * at a real 404 status -- never Laravel's generic error page (which
     * would look identical to "route does not exist" and give away
     * nothing extra, which is fine, but this copy is client-facing and
     * deliberately reassuring rather than a raw framework error page).
     */
    protected function unavailable(): Response
    {
        return response()->view('vouchers.public.unavailable', [], 404);
    }
}
