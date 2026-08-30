<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Client;
use App\Models\Supplier;
use App\Services\Accounting\StatementOptions;
use App\Services\Accounting\StatementService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * P2.5.H (p2_5-brief.md §P2.5.H). One screen + one PDF endpoint, reused across all three party
 * types (client/supplier/agent) -- {@see StatementService} already carries the per-party-type
 * source selection, so this controller only resolves the party model + authorizes the request,
 * exactly mirroring how `ClientController::showCredit()` gates its own client-facing statement
 * (Gate::authorize('view', $client)) rather than inventing a new permission for a screen that is,
 * at bottom, "may this user see this party's own account".
 */
class StatementController extends Controller
{
    public function __construct(private readonly StatementService $statements) {}

    public function show(Request $request, string $partyType, int $partyId): View|RedirectResponse
    {
        [$companyId, $party] = $this->resolveAndAuthorize($request, $partyType, $partyId);

        $asOf = $this->resolveAsOf($request);
        $modeOverride = $this->resolveModeOverride($request);
        $periodStart = $request->filled('period_start') ? Carbon::parse($request->input('period_start')) : null;

        $statement = $this->statements->generate($companyId ?? 0, $partyType, $partyId, $asOf, $modeOverride, $periodStart);

        return view('accounting.statements.show', [
            'partyType' => $partyType,
            'party' => $party,
            'statement' => $statement,
            'companyMode' => StatementOptions::mode($companyId ?? 0),
            'asOf' => $asOf,
            'periodStart' => $periodStart,
        ]);
    }

    public function pdf(Request $request, string $partyType, int $partyId): Response
    {
        [$companyId, $party] = $this->resolveAndAuthorize($request, $partyType, $partyId);

        $asOf = $this->resolveAsOf($request);
        $modeOverride = $this->resolveModeOverride($request);
        $periodStart = $request->filled('period_start') ? Carbon::parse($request->input('period_start')) : null;

        $statement = $this->statements->generate($companyId ?? 0, $partyType, $partyId, $asOf, $modeOverride, $periodStart);

        $pdf = Pdf::loadView('accounting.statements.pdf', [
            'partyType' => $partyType,
            'party' => $party,
            'statement' => $statement,
            'asOf' => $asOf,
            'generatedAt' => now(),
        ]);
        $pdf->setPaper('A4', 'portrait');

        $filename = 'statement-'.$partyType.'-'.$partyId.'-'.$asOf->toDateString().'.pdf';

        return $pdf->download($filename);
    }

    /**
     * @return array{0: ?int, 1: Client|Supplier|Agent}
     */
    private function resolveAndAuthorize(Request $request, string $partyType, int $partyId): array
    {
        $user = Auth::user();
        $companyId = getCompanyId($user);

        switch ($partyType) {
            case StatementService::PARTY_CLIENT:
                $party = Client::findOrFail($partyId);
                Gate::authorize('view', $party);
                break;
            case StatementService::PARTY_SUPPLIER:
                $party = Supplier::findOrFail($partyId);
                Gate::authorize('view', Supplier::class);
                break;
            case StatementService::PARTY_AGENT:
                $party = Agent::findOrFail($partyId);
                Gate::authorize('view', $party);
                break;
            default:
                abort(404);
        }

        return [$companyId, $party];
    }

    private function resolveAsOf(Request $request): Carbon
    {
        return $request->filled('as_of') ? Carbon::parse($request->input('as_of'))->endOfDay() : now()->endOfDay();
    }

    private function resolveModeOverride(Request $request): ?string
    {
        $mode = $request->input('mode');

        return in_array($mode, StatementOptions::MODES, true) ? $mode : null;
    }
}
