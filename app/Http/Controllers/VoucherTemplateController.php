<?php

namespace App\Http\Controllers;

use App\Models\VoucherTemplate;
use App\Services\Vouchers\VoucherCatalogue;
use App\Services\Vouchers\VoucherDataRepository;
use App\Services\Vouchers\VoucherSampleFixtures;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The Settings -> Voucher Templates tab (plan §16 step 3, §8). Lists the
 * five shipped designs and lets staff preview each against the company's
 * own most recent booking of that type — or a clearly-labelled sample
 * when they have none yet. Nothing here issues a voucher: no
 * travel_vouchers row is created, no number is consumed, no token is
 * minted (plan §8 — that is Step 4's issue/send flow, a separate piece
 * of work). Clients never reach this controller at all; the shipped
 * designs are the only choice (plan §14.8, this step's own rule 5) — there
 * is no create/edit/upload action anywhere in this class.
 *
 * Both routes live inside the authenticated `settings` route group
 * (routes/web.php) — never the pattern the `terms` route group uses
 * today (unauthenticated, no company scoping on mutations, plan §11.4).
 * Every lookup here is scoped by getCompanyId(Auth::user()) explicitly,
 * even though auth already gates the route, because that is the
 * discipline the whole feature is built on (plan §2.4).
 */
class VoucherTemplateController extends Controller
{
    public function __construct(private readonly VoucherDataRepository $repository) {}

    /**
     * JSON feed for the gallery tab's Alpine.js grid (mirrors
     * TermController::index()'s fetch-on-tab-open pattern, minus that
     * controller's un-company-scoped mutations — this endpoint has none).
     */
    public function gallery(Request $request): JsonResponse
    {
        $companyId = getCompanyId(Auth::user());

        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'No company context for this account.'], 422);
        }

        $cards = [];

        foreach (VoucherCatalogue::entries() as $entry) {
            $taskType = $entry['task_type'];

            $latestTask = $taskType === VoucherCatalogue::TASK_TYPE_GENERIC
                ? $this->repository->latestTaskForGenericTypes($companyId)
                : $this->repository->latestTaskForType($companyId, $taskType);

            $languages = [];
            foreach (VoucherCatalogue::LANGUAGES as $language) {
                $languages[$language] = [
                    'preview_url' => route('settings.voucher-templates.preview', [
                        'taskType' => $taskType,
                        'language' => strtolower($language),
                    ]),
                ];
            }

            $cards[] = [
                'task_type' => $taskType,
                'name' => $entry['name'],
                'has_real_booking' => (bool) $latestTask,
                'source_reference' => $latestTask?->reference,
                // Card note (plan §8: "the card note 'Showing sample data
                // — you have no {type} bookings yet.'"). English only —
                // this is staff-facing chrome around the gallery, not
                // voucher content, so it does not need the ARB/EN split
                // the voucher documents themselves carry.
                'sample_note' => $latestTask ? null : "Showing sample data — you have no {$entry['label']} bookings yet.",
                'languages' => $languages,
            ];
        }

        return response()->json(['success' => true, 'cards' => $cards]);
    }

    /**
     * Renders one shipped design as plain HTML — auth-only, GET, no
     * side effects (plan §8: "no PDF generated, no travel_vouchers row
     * created, no number consumed, no token minted"). Opened in a new
     * browser tab from the gallery card, so it also doubles as a live
     * check that the design actually renders (this step's own
     * instruction: "Verify by generating an actual PDF and LOOKING at
     * it" — for the browser-HTML half of that; the PDF half is verified
     * separately per booking, not through this route, since Step 3 ships
     * no PDF-generation route — that is Step 4's issue/send flow).
     */
    public function preview(Request $request, string $taskType, string $language): View
    {
        abort_unless(VoucherCatalogue::isValidTaskType($taskType), 404);
        abort_unless(VoucherCatalogue::isValidLanguage($language), 404);

        $language = strtoupper($language) === VoucherTemplate::LANGUAGE_AR ? VoucherTemplate::LANGUAGE_AR : VoucherTemplate::LANGUAGE_EN;

        $companyId = getCompanyId(Auth::user());
        abort_if(! $companyId, 404);

        $entry = VoucherCatalogue::find($taskType);

        // Company override row wins over the shipped system row when one
        // exists (plan §3.1 resolution rule) — no override rows exist yet
        // in this step (no create/edit UI ships here), so in practice this
        // always resolves the system row; the ordering is future-proofing
        // for when Phase C adds company-level toggles.
        $template = VoucherTemplate::query()
            ->where('task_type', $taskType)
            ->where('language', $language)
            ->where('is_active', true)
            ->visibleTo($companyId)
            ->orderByRaw('company_id IS NOT NULL DESC')
            ->first();

        $task = $taskType === VoucherCatalogue::TASK_TYPE_GENERIC
            ? $this->repository->latestTaskForGenericTypes($companyId)
            : $this->repository->latestTaskForType($companyId, $taskType);

        $voucherMeta = ['language' => $language];

        if ($task) {
            $payload = $this->repository->payloadForTask($task, $companyId, $template, $voucherMeta);
            $sample = false;
        } else {
            $shell = $this->repository->shellForCompany($companyId, $template, $voucherMeta);
            $payload = VoucherSampleFixtures::forType($taskType, $language, $shell);
            $sample = true;
        }

        $view = $template?->view_key ?? $entry['view_key'];

        return view($view, ['payload' => $payload, 'isPdf' => false, 'sample' => $sample]);
    }
}
