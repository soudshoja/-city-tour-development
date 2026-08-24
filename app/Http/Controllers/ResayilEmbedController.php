<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\Resayil\ResayilProvisioningService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Renders the Resayil WhatsApp CRM as a full-page view inside TravelERP
 * (Module 5). TravelERP's own header/menu stay visible; this fills the
 * content area with the Resayil iframe at full size.
 *
 * The iframe src is the SERVER-CONFIGURED Resayil URL
 * (config('resayil.embed_url')), never a user-supplied parameter — the SSRF
 * mitigation ported from aircon: the front-end can only ever frame the one
 * configured origin.
 *
 * Access is gated entirely by the `module:resayil` route middleware
 * (routes/web.php) — a company without the module gets a 404 before this
 * controller ever runs.
 */
class ResayilEmbedController extends Controller
{
    public function index(Request $request, ResayilProvisioningService $provisioning): View
    {
        $user = $request->user();
        $companyId = getCompanyId($user);
        $company = $companyId ? Company::find($companyId) : null;

        $embedUrl = config('resayil.embed_url');

        // Idempotent — cheap when already provisioned/terminal, at most one
        // outbound HTTP call the first time a company's first user visits.
        $account = $provisioning->ensureUserProvisioned($user);

        return view('resayil.full', [
            'embedUrl' => $embedUrl,
            'notConfigured' => empty($embedUrl),
            'account' => $account,
            'capReached' => $company ? $provisioning->capReached($company) : false,
            'maxAutoUsers' => (int) config('resayil.max_auto_users', 9),
        ]);
    }
}
