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
        ]);
    }

    /**
     * PDF variant of the same public link. Streams the file rendered at
     * issue time (VoucherService::renderPdf()) when it is still present on
     * the private disk; falls back to rendering the frozen snapshot again
     * on the fly only if that file is somehow missing, so the public PDF
     * link never hard-fails just because a stored file was cleaned up.
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
