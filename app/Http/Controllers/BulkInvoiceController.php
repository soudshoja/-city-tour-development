<?php

namespace App\Http\Controllers;

use App\Exports\BulkInvoiceTemplateExport;
use App\Exports\BulkUploadErrorReportExport;
use App\Models\BulkUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
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

    /**
     * Download error report for a specific bulk upload.
     *
     * Returns an Excel file containing all error and flagged rows with:
     * - Original row data
     * - Error messages
     * - Flag reasons
     * - Color-coded rows (red for errors, yellow for flagged)
     *
     * @param  int  $id  The bulk upload ID
     */
    public function downloadErrorReport(int $id): BinaryFileResponse|RedirectResponse
    {
        try {
            $user = Auth::user();
            $companyId = getCompanyId($user);

            // Find the bulk upload scoped to agent's company (multi-tenant isolation)
            $bulkUpload = BulkUpload::where('id', $id)
                ->where('company_id', $companyId)
                ->firstOrFail();

            // Check if there are errors to report
            if ($bulkUpload->error_rows === 0 && $bulkUpload->flagged_rows === 0) {
                return redirect()->back()->with('message', 'No errors or flagged rows to export.');
            }

            // Generate filename
            $filename = 'error-report-'.Str::slug($bulkUpload->original_filename, '-').'-'.$bulkUpload->id.'.xlsx';

            return Excel::download(new BulkUploadErrorReportExport($bulkUpload), $filename);
        } catch (\Exception $e) {
            \Log::error('Error downloading error report: '.$e->getMessage(), [
                'id' => $id,
                'user_id' => Auth::id(),
            ]);

            return redirect()->back()->withErrors(['error' => 'Failed to generate error report. Please try again.']);
        }
    }
}
