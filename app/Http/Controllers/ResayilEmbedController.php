<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\ResayilAccount;
use App\Models\Role;
use App\Services\Resayil\ResayilProvisioningService;
use Illuminate\Http\RedirectResponse;
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
 *
 * SECURITY FIX (2026-08-26 — wave-2 adversarial verification, blockers 1 & 3):
 * index() used to call ResayilProvisioningService::ensureUserProvisioned()
 * inline, on every GET, with no role check. That single line meant:
 *  - the first agent/branch/accountant user to open this page became the
 *    company's PERMANENT Resayil workspace identity (an external customer
 *    account was created under their name/email);
 *  - combined with getCompanyId()'s old ADMIN-falls-back-to-company-1 bug,
 *    a platform operator merely viewing this page — for ANY company, or
 *    none at all — could get silently enrolled as a Resayil TEAM MEMBER on
 *    a real customer's live WhatsApp number (proven against company 1,
 *    City Travelers, in verification).
 *
 * index() is now READ-ONLY: it renders whatever Resayil state already
 * exists locally and never makes an outbound call. The only thing that may
 * create or link a Resayil identity is provision(), which is an explicit
 * POST, CSRF-protected like every other POST route in this app, and gated
 * by `can:manage-resayil` on the route (ADMIN + COMPANY only — the same
 * gate as Settings -> WhatsApp). A GET here can no longer write anything,
 * for any role.
 */
class ResayilEmbedController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $companyId = getCompanyId($user);
        $company = $companyId ? Company::find($companyId) : null;

        $embedUrl = config('resayil.embed_url');

        // READ-ONLY lookup. No provisioning call — a page render must
        // never cause an external write (see class docblock). The admin
        // row (if any) already exists because a company owner explicitly
        // provisioned it via provision() below, the post-signup queued
        // job, or an operator running `resayil:provision-company --sync`.
        $adminRow = $companyId ? ResayilAccount::adminFor($companyId) : null;

        return view('resayil.full', [
            'embedUrl' => $embedUrl,
            'notConfigured' => empty($embedUrl),
            'workspaceProvisioned' => (bool) $adminRow?->resayil_customer_id,
            'adoptionPending' => $adminRow?->status === ResayilAccount::STATUS_ADOPTION_PENDING,
            'canProvision' => $user && in_array((int) $user->role_id, [Role::ADMIN, Role::COMPANY], true),
            'capReached' => $company ? app(ResayilProvisioningService::class)->capReached($company) : false,
            'maxAutoUsers' => (int) config('resayil.max_auto_users', 9),
        ]);
    }

    /**
     * Explicit, role-gated action that sets up (or repairs) this company's
     * Resayil workspace. This is the ONLY place left that may create an
     * external Resayil customer from Module 5 — see class docblock.
     *
     * The route applies `can:manage-resayil` (ADMIN + COMPANY only); this
     * method does not re-check the role itself, matching the pattern
     * already used by every other route in the `resayil-admin.` group
     * (ResayilAdminController) — the gate lives on the route.
     */
    public function provision(Request $request, ResayilProvisioningService $provisioning): RedirectResponse
    {
        $user = $request->user();

        // A PLATFORM OPERATOR MUST CHOOSE A COMPANY EXPLICITLY BEFORE THIS RUNS.
        //
        // This action creates a real, billable third-party Resayil workspace,
        // so it must never be attributed to a guessed company. getCompanyId()
        // falls back to company 1 for an operator who has not used the sidebar
        // company switcher — harmless for reads, but for THIS action it would
        // silently create or link a workspace against City Travelers' real
        // account. That exact mechanism (operator page view -> company-1
        // fallback -> live write) was proven during the 2026-08 security
        // verification.
        //
        // Guarding it here, at the one irreversible external write, rather than
        // by making getCompanyId() return null globally: that broader change
        // was tried and reverted because ~100 call sites assume an operator
        // always resolves to a company, and it 404'd the whole gated surface.
        if ((int) $user->role_id === \App\Models\Role::ADMIN && ! session()->has('company_id')) {
            return back()->withErrors([
                'resayil' => 'Choose a company first — use the company switcher, then set up WhatsApp for it. This creates a real WhatsApp workspace, so it is never done for a guessed company.',
            ]);
        }

        $companyId = getCompanyId($user);

        if ($companyId === null) {
            return back()->withErrors([
                'resayil' => 'Select a company before setting up WhatsApp.',
            ]);
        }

        $account = $provisioning->provisionCompanyAdmin($companyId, $user);

        return match ($account->status) {
            ResayilAccount::STATUS_PROVISIONED => back()->with(
                'success',
                'Your WhatsApp workspace is set up.'
            ),
            ResayilAccount::STATUS_ADOPTION_PENDING => back()->with(
                'success',
                'We found an existing Resayil account under this email. Our team is confirming it belongs to your company before linking it — nothing is broken meanwhile.'
            ),
            ResayilAccount::STATUS_LIMIT_REACHED => back()->withErrors([
                'resayil' => 'Your company has reached its included WhatsApp seats. Contact your account manager to add more.',
            ]),
            default => back()->withErrors([
                'resayil' => "We couldn't finish setting up WhatsApp right now. Please try again shortly, or contact support if it keeps happening.",
            ]),
        };
    }
}
