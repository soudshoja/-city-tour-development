<?php

namespace App\Http\Controllers;

use App\Exports\BulkInvoiceTemplateExport;
use App\Exports\BulkUploadErrorReportExport;
use App\Http\Requests\BulkInvoiceUploadRequest;
use App\Imports\BulkInvoiceImport;
use App\Models\BulkUpload;
use App\Models\BulkUploadRow;
use App\Services\BulkUploadValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
     * @var BulkUploadValidationService
     */
    protected $validationService;

    /**
     * Create a new controller instance.
     */
    public function __construct(BulkUploadValidationService $validationService)
    {
        $this->validationService = $validationService;
    }

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
     * Upload and process an Excel file for bulk invoice creation.
     *
     * Orchestrates the full upload flow:
     * 1. Store file to disk for audit
     * 2. Parse Excel to array
     * 3. Validate headers
     * 4. Validate all rows
     * 5. Create BulkUpload and BulkUploadRow records
     * 6. Return JSON validation summary
     */
    public function upload(BulkInvoiceUploadRequest $request): JsonResponse
    {
        try {
            // Get context
            $user = Auth::user();
            $companyId = getCompanyId($user);
            $agentId = $user->agent?->id;

            // Get uploaded file
            $file = $request->file('file');

            // Store file to disk for audit
            $timestamp = time();
            $filename = $timestamp.'_'.$file->getClientOriginalName();
            $storedPath = Storage::disk('local')->putFileAs(
                'bulk-uploads/'.$companyId,
                $file,
                $filename
            );

            // Parse Excel file
            $rows = Excel::toArray(new BulkInvoiceImport, $file)[0];

            // Validate headers
            if (empty($rows)) {
                return response()->json([
                    'error' => 'The uploaded file is empty or has no data rows.',
                ], 422);
            }

            $headerResult = $this->validationService->validateHeaders(array_keys($rows[0]));

            if (! $headerResult['valid']) {
                // Create failed BulkUpload record for audit
                BulkUpload::create([
                    'company_id' => $companyId,
                    'agent_id' => $agentId,
                    'user_id' => $user->id,
                    'original_filename' => $file->getClientOriginalName(),
                    'stored_path' => $storedPath,
                    'status' => 'failed',
                    'total_rows' => 0,
                    'valid_rows' => 0,
                    'error_rows' => 0,
                    'flagged_rows' => 0,
                    'error_summary' => [
                        'header_errors' => $headerResult['missing'],
                    ],
                ]);

                return response()->json([
                    'error' => 'Invalid Excel headers.',
                    'missing_headers' => $headerResult['missing'],
                    'extra_headers' => $headerResult['extra'],
                ], 422);
            }

            // Validate all rows
            $results = $this->validationService->validateAll($rows, $companyId);

            // Create BulkUpload record
            $bulkUpload = BulkUpload::create([
                'company_id' => $companyId,
                'agent_id' => $agentId,
                'user_id' => $user->id,
                'original_filename' => $file->getClientOriginalName(),
                'stored_path' => $storedPath,
                'status' => 'validated',
                'total_rows' => $results['total'],
                'valid_rows' => $results['valid'],
                'error_rows' => $results['errors'],
                'flagged_rows' => $results['flagged'],
                'error_summary' => $this->buildErrorSummary($results['rows']),
            ]);

            // Create BulkUploadRow records (bulk insert for performance)
            $rowRecords = [];
            foreach ($results['rows'] as $index => $rowResult) {
                $rowRecords[] = [
                    'bulk_upload_id' => $bulkUpload->id,
                    'row_number' => $index + 1,
                    'status' => $rowResult['status'],
                    'task_id' => $rowResult['matched']['task_id'] ?? null,
                    'client_id' => $rowResult['matched']['client_id'] ?? null,
                    'supplier_id' => $rowResult['matched']['supplier_id'] ?? null,
                    'raw_data' => json_encode($rows[$index]),
                    'errors' => ! empty($rowResult['errors']) ? json_encode($rowResult['errors']) : null,
                    'flag_reason' => $rowResult['flag_reason'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            BulkUploadRow::insert($rowRecords);

            // Return JSON response
            return response()->json([
                'upload_id' => $bulkUpload->id,
                'status' => $bulkUpload->status,
                'summary' => [
                    'total_rows' => $results['total'],
                    'valid_rows' => $results['valid'],
                    'error_rows' => $results['errors'],
                    'flagged_rows' => $results['flagged'],
                ],
                'has_errors' => $results['errors'] > 0,
                'has_flagged' => $results['flagged'] > 0,
            ]);
        } catch (\Exception $e) {
            Log::error('Bulk upload failed: '.$e->getMessage(), [
                'user_id' => Auth::id(),
                'file' => $file?->getClientOriginalName() ?? 'unknown',
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Failed to process upload. Please try again.',
            ], 500);
        }
    }

    /**
     * Build error summary from validation results.
     *
     * Aggregates errors by type for the error_summary JSON field.
     */
    private function buildErrorSummary(array $rowResults): array
    {
        $summary = [];

        foreach ($rowResults as $rowResult) {
            if ($rowResult['status'] === 'error') {
                foreach ($rowResult['errors'] as $error) {
                    // Extract error type from message (e.g., "Row 3: task_id is required" -> "task_id is required")
                    $errorType = preg_replace('/^Row \d+: /', '', $error);

                    if (! isset($summary[$errorType])) {
                        $summary[$errorType] = 0;
                    }

                    $summary[$errorType]++;
                }
            }
        }

        return $summary;
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
