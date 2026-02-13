<?php

namespace App\Http\Controllers;

use App\Exports\BulkInvoiceTemplateExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * BulkInvoiceController
 *
 * Handles bulk invoice upload operations including template downloads.
 */
class BulkInvoiceController extends Controller
{
    /**
     * Download the bulk invoice template Excel file.
     *
     * Returns a multi-sheet Excel file with:
     * - Upload Template sheet (with column headers)
     * - Client List sheet (pre-filled with company's clients)
     */
    public function downloadTemplate(Request $request): BinaryFileResponse
    {
        $user = Auth::user();
        $companyId = getCompanyId($user);

        return Excel::download(
            new BulkInvoiceTemplateExport($companyId),
            'bulk-invoice-template.xlsx'
        );
    }
}
