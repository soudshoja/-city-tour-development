<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskPackage;
use App\Models\TravelVoucher;
use App\Models\VoucherTemplate;
use App\Services\Vouchers\Exceptions\VoucherSubjectDeadException;
use App\Services\Vouchers\VoucherSendService;
use App\Services\Vouchers\VoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Staff-authenticated voucher actions on a task or a package (Step 4
 * items 1 and 4, plan section 10, section 16). Sits INSIDE the authenticated
 * route group (routes/web.php) -- never the pattern the legacy
 * tasks/pdf/* routes used, never the terms group's holes (plan section 11.4).
 *
 * Issuing/sending needs only normal, already-authenticated task access
 * (plan section 11.5: "issuing/sending vouchers needs only normal task
 * access" -- no new dedicated permission for this step). Every method
 * still scopes explicitly by getCompanyId(Auth::user()) rather than
 * trusting route-model-binding alone (plan section 2.4 discipline, applied
 * even where auth already narrows things).
 *
 * This is a NEW controller, not an edit to InvoiceController/PaymentController
 * (both OFF LIMITS -- accounting boundary, plan section 2.1).
 */
class VoucherController extends Controller
{
    public function __construct(
        private readonly VoucherService $vouchers,
        private readonly VoucherSendService $sender,
    ) {}

    /**
     * The task-scoped "Vouchers" mini page: existing issued vouchers for
     * this task, the one catalogue design that matches its type, and an
     * Issue button. Reachable from the new action-menu item added to
     * tasks/index.blade.php (Step 4 item 1).
     */
    public function indexForTask(Request $request, Task $task): View
    {
        $companyId = getCompanyId(Auth::user());
        abort_if(! $companyId || (int) $task->company_id !== $companyId, 404);

        $catalogType = \App\Services\Vouchers\VoucherCatalogue::catalogTaskTypeFor($task->type);
        $entry = \App\Services\Vouchers\VoucherCatalogue::find($catalogType);

        $vouchers = TravelVoucher::query()
            ->forCompany($companyId)
            ->where('subject_type', Task::class)
            ->where('subject_id', $task->id)
            ->with('supersededBy')
            ->latest('id')
            ->get();

        return view('tasks.vouchers', [
            'task' => $task,
            'catalogEntry' => $entry,
            'catalogType' => $catalogType,
            'vouchers' => $vouchers,
            'hasClient' => (bool) $task->client_id,
        ]);
    }

    /**
     * Issue a voucher for one task (plan section 10.1). Available whether the
     * task is paid or unpaid -- this method never looks at payment state
     * at all, matching the plan's own "never gated on payment" rule.
     */
    public function issueForTask(Request $request, Task $task): JsonResponse|RedirectResponse
    {
        $companyId = getCompanyId(Auth::user());
        abort_if(! $companyId || (int) $task->company_id !== $companyId, 404);

        $validated = $request->validate([
            'language' => 'nullable|string|in:EN,ARB',
        ]);
        $language = $validated['language'] ?? VoucherTemplate::LANGUAGE_EN;

        $catalogType = \App\Services\Vouchers\VoucherCatalogue::catalogTaskTypeFor($task->type);
        $template = VoucherTemplate::resolveEffective($companyId, $catalogType, $language);

        if (! $template) {
            return $this->respond($request, false, "No active {$catalogType}/{$language} voucher template is available.", 422);
        }

        // F4: refuse a dead task (void, or superseded by a later task)
        // with a clear staff-facing message instead of a silent no-op or
        // an exception page.
        try {
            $voucher = $this->vouchers->issue($task, $template, $language, $companyId, Auth::id());
        } catch (VoucherSubjectDeadException $e) {
            return $this->respond($request, false, $e->getMessage(), 422);
        }

        return $this->respond($request, true, "Voucher {$voucher->voucher_number} issued.", 200, [
            'voucher' => $this->voucherPayload($voucher, $companyId),
        ]);
    }

    /**
     * Issue a voucher for a package (plan section 10.1's "from a task or a
     * package"). No package-creation UI ships in this step (Phase B,
     * plan section 13) -- this endpoint exists so the service-level contract
     * is complete for whenever a task_packages row exists, created either
     * by a future Phase B UI or directly.
     */
    public function issueForPackage(Request $request, TaskPackage $package): JsonResponse|RedirectResponse
    {
        $companyId = getCompanyId(Auth::user());
        abort_if(! $companyId || (int) $package->company_id !== $companyId, 404);

        $validated = $request->validate([
            'language' => 'nullable|string|in:EN,ARB',
        ]);
        $language = $validated['language'] ?? VoucherTemplate::LANGUAGE_EN;

        $template = VoucherTemplate::resolveEffective($companyId, VoucherTemplate::TASK_TYPE_PACKAGE, $language);

        if (! $template) {
            return $this->respond($request, false, "No active package/{$language} voucher template is available.", 422);
        }

        $voucher = $this->vouchers->issue($package, $template, $language, $companyId, Auth::id());

        return $this->respond($request, true, "Voucher {$voucher->voucher_number} issued.", 200, [
            'voucher' => $this->voucherPayload($voucher, $companyId),
        ]);
    }

    /**
     * Staff-authenticated PDF download -- distinct from the public token
     * route (this one is reachable for ANY status, including a cancelled
     * voucher, so staff always keep their own record).
     *
     * F5: same ARB language guard PublicVoucherController::pdf() already
     * enforces -- VoucherService::renderPdf() is a deliberate no-op for
     * TravelVoucher::LANGUAGE_AR (dompdf cannot shape Arabic), so an ARB
     * voucher should never have a servable pdf_path in the first place.
     * This route had NO guard at all before this fix: five legacy ARB
     * rows (travel_vouchers ids 13, 15, 17, 19, 21) still carried a
     * non-null pdf_path with a real file on disk, and this staff route
     * would happily stream it (verified live: HTTP 200, magic %PDF,
     * 887001 bytes for id 13). The rows themselves are cleared to
     * pdf_path=NULL as part of this same fix; this guard additionally
     * stops anything reaching that state from being served, ever.
     */
    public function download(TravelVoucher $voucher)
    {
        $companyId = getCompanyId(Auth::user());
        abort_if(! $companyId || (int) $voucher->company_id !== $companyId, 404);

        if ($voucher->language === TravelVoucher::LANGUAGE_AR) {
            abort(404, 'A PDF is not available for Arabic vouchers -- use the voucher\'s public link instead.');
        }

        if (! $voucher->pdf_path || ! Storage::disk('local')->exists($voucher->pdf_path)) {
            abort(404, 'Voucher PDF not found.');
        }

        return Storage::disk('local')->download($voucher->pdf_path, "{$voucher->voucher_number}.pdf");
    }

    /**
     * WhatsApp send (Step 4 item 4). Gated on module.resayil INSIDE
     * VoucherSendService, not here -- one place decides that, matching
     * the plan's "issuing/downloading needs no module" split (section 10.2).
     */
    public function send(Request $request, TravelVoucher $voucher): JsonResponse|RedirectResponse
    {
        $companyId = getCompanyId(Auth::user());
        abort_if(! $companyId || (int) $voucher->company_id !== $companyId, 404);

        $result = $this->sender->send($voucher, $companyId, Auth::id());

        return $this->respond($request, $result['success'], $result['message'], $result['success'] ? 200 : 422, [
            'error' => $result['error'] ?? null,
        ]);
    }

    /**
     * Manual "Cancel V" (plan section 13-BIS.C) -- staff can kill a voucher's
     * public link directly, independent of the void-detection flow this
     * step does not wire automatically (see VoucherService's own docblock).
     */
    public function cancel(Request $request, TravelVoucher $voucher): JsonResponse|RedirectResponse
    {
        $companyId = getCompanyId(Auth::user());
        abort_if(! $companyId || (int) $voucher->company_id !== $companyId, 404);

        $this->vouchers->cancel($voucher, $companyId);

        return $this->respond($request, true, "Voucher {$voucher->voucher_number} cancelled.", 200);
    }

    /**
     * The attach-client empty state (plan section 10.4): the send button's
     * own prompt when a task has no client_id yet. Deliberately a small,
     * dedicated write -- NOT a call into TaskController::update(), which
     * carries a much larger validation surface this single-field action
     * does not need and should not risk triggering.
     */
    public function attachClient(Request $request, Task $task): JsonResponse|RedirectResponse
    {
        $companyId = getCompanyId(Auth::user());
        abort_if(! $companyId || (int) $task->company_id !== $companyId, 404);

        $validated = $request->validate([
            'client_id' => 'required|integer|exists:clients,id',
        ]);

        $client = \App\Models\Client::where('id', $validated['client_id'])->where('company_id', $companyId)->first();

        if (! $client) {
            return $this->respond($request, false, 'That client does not belong to this company.', 422);
        }

        $task->client_id = $client->id;
        $task->save();

        return $this->respond($request, true, "Client attached: {$client->full_name}.", 200, [
            'client' => ['id' => $client->id, 'name' => $client->full_name, 'phone' => $client->phone_number],
        ]);
    }

    protected function voucherPayload(TravelVoucher $voucher, int $companyId): array
    {
        return [
            'id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'status' => $voucher->status,
            'language' => $voucher->language,
            'version' => $voucher->version,
            'public_url' => route('travel-voucher.show', ['companyId' => $companyId, 'token' => $voucher->token]),
            'pdf_url' => route('travel-voucher.pdf', ['companyId' => $companyId, 'token' => $voucher->token]),
            'download_url' => route('vouchers.download', $voucher->id),
            'sent_to_phone' => $voucher->sent_to_phone,
            'sent_at' => optional($voucher->sent_at)->toIso8601String(),
        ];
    }

    protected function respond(Request $request, bool $success, string $message, int $status = 200, array $extra = []): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(array_merge(['success' => $success, 'message' => $message], $extra), $status);
        }

        return redirect()->back()->with($success ? 'success' : 'error', $message);
    }
}
