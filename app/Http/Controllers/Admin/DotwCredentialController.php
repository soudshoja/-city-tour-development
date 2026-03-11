<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDotwCredentialRequest;
use App\Models\Company;
use App\Models\CompanyDotwCredential;
use Illuminate\Http\JsonResponse;

/**
 * Admin REST controller for DOTW credential management.
 *
 * Provides upsert (store) and read (show) operations for per-company DOTW
 * API credentials. Credentials are stored encrypted via the CompanyDotwCredential
 * model and are NEVER returned in any API response — the $hidden array on the
 * model acts as the serialization-level security boundary.
 *
 * Routes:
 *   POST /api/admin/companies/{companyId}/dotw-credentials  → store()
 *   GET  /api/admin/companies/{companyId}/dotw-credentials  → show()
 *
 * Isolation: all queries are scoped to the {companyId} path parameter, ensuring
 * that credentials stored for Company A are never accessible when requesting
 * Company B context.
 */
class DotwCredentialController extends Controller
{
    /**
     * Store (upsert) DOTW credentials for a company.
     *
     * Creates a new credential row if none exists for the given company_id,
     * or updates the existing row. The unique constraint on company_id in the
     * database guarantees at most one row per company.
     *
     * Markup percent defaults to 20.00 if not provided in the request body.
     *
     * Response intentionally omits dotw_username and dotw_password — the
     * $hidden property on CompanyDotwCredential excludes them from serialization.
     *
     * @param  StoreDotwCredentialRequest  $request  Validated request (422 on invalid input)
     * @param  int  $companyId  Company ID from the URL path parameter
     * @return JsonResponse 200 on success, 404 if company does not exist
     */
    public function store(StoreDotwCredentialRequest $request, int $companyId): JsonResponse
    {
        // Verify the company exists — returns 404 automatically if not found
        $company = Company::findOrFail($companyId);

        // Upsert — update existing row or create new one.
        // The unique constraint on company_id prevents duplicate rows.
        $credential = CompanyDotwCredential::updateOrCreate(
            ['company_id' => $companyId],
            [
                'dotw_username'     => $request->input('dotw_username'),
                'dotw_password'     => $request->input('dotw_password'),
                'dotw_company_code' => $request->input('dotw_company_code'),
                'markup_percent'    => $request->input('markup_percent', 20.00),
                'is_active'         => true,
            ]
        );

        return response()->json([
            'success'        => true,
            'message'        => 'DOTW credentials saved successfully',
            'company_id'     => $companyId,
            'markup_percent' => $credential->markup_percent,
            // Note: dotw_username and dotw_password are intentionally NOT returned.
            // The $hidden property on CompanyDotwCredential excludes them from serialization.
        ], 200);
    }

    /**
     * Show the DOTW credential status for a company.
     *
     * Returns non-sensitive configuration fields only: dotw_company_code,
     * markup_percent, is_active, and timestamps. The credential fields
     * (dotw_username, dotw_password) are intentionally omitted — they are
     * excluded at the model level via $hidden and also not explicitly included
     * in the response payload here.
     *
     * Returns 404 with configured:false if no active credential exists for
     * the given company_id.
     *
     * @param  int  $companyId  Company ID from the URL path parameter
     * @return JsonResponse 200 with credential status, or 404 if not configured
     */
    public function show(int $companyId): JsonResponse
    {
        // Verify the company exists — returns 404 automatically if not found
        $company = Company::findOrFail($companyId);

        $credential = CompanyDotwCredential::where('company_id', $companyId)
            ->where('is_active', true)
            ->first();

        if (! $credential) {
            return response()->json([
                'success'    => false,
                'configured' => false,
                'company_id' => $companyId,
                'message'    => 'DOTW credentials not configured for this company',
            ], 404);
        }

        return response()->json([
            'success'           => true,
            'configured'        => true,
            'company_id'        => $companyId,
            'dotw_company_code' => $credential->dotw_company_code,
            'markup_percent'    => $credential->markup_percent,
            'is_active'         => $credential->is_active,
            'created_at'        => $credential->created_at,
            'updated_at'        => $credential->updated_at,
            // dotw_username and dotw_password are NOT returned — hidden at model level
        ]);
    }
}
