<?php

namespace App\Http\Controllers;

use App\Exports\BulkInvoiceTemplateExport;
use App\Exports\BulkUploadErrorReportExport;
use App\Http\Requests\BulkInvoiceUploadRequest;
use App\Imports\BulkInvoiceImport;
use App\Jobs\CreateBulkInvoicesJob;
use App\Models\BulkUpload;
use App\Models\BulkUploadRow;
use App\Models\Invoice;
use App\Services\BulkUploadValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
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
     * Show the bulk invoice upload form.
     */
    public function index()
    {
        $user = Auth::user();
        $isAgent = $user->role_id == \App\Models\Role::AGENT;
        $agents = [];

        // Get available agents based on user role
        if (!$isAgent) {
            if ($user->role_id == \App\Models\Role::COMPANY) {
                $agents = \App\Models\Agent::whereHas('branch', function($q) use ($user) {
                    $q->where('company_id', $user->company->id);
                })->with('user')->get();
            } elseif ($user->role_id == \App\Models\Role::BRANCH) {
                $agents = \App\Models\Agent::where('branch_id', $user->branch->id)
                    ->with('user')->get();
            } elseif ($user->role_id == \App\Models\Role::ACCOUNTANT) {
                $agents = \App\Models\Agent::where('branch_id', $user->accountant->branch->id)
                    ->with('user')->get();
            } elseif ($user->role_id == \App\Models\Role::ADMIN) {
                $companyId = session('company_id', 1);
                $agents = \App\Models\Agent::whereHas('branch', function($q) use ($companyId) {
                    $q->where('company_id', $companyId);
                })->with('user')->get();
            }
        }

        // Format agents for searchable dropdown
        $agentsForDropdown = $agents->map(function($agent) {
            return [
                'id' => $agent->id,
                'name' => ($agent->user->name ?? 'Agent #'.$agent->id) .
                         ($agent->branch ? ' (' . ($agent->branch->name ?? 'Branch #'.$agent->branch_id) . ')' : '')
            ];
        });

        return view('bulk-invoice.upload', [
            'isAgent' => $isAgent,
            'agents' => $agentsForDropdown,
        ]);
    }

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
     * 6. Redirect to preview page
     */
    public function upload(BulkInvoiceUploadRequest $request): JsonResponse|RedirectResponse
    {
        try {
            // Get context
            $user = Auth::user();
            $companyId = getCompanyId($user);

            Log::info('[BULK UPLOAD] Upload started', [
                'user_id' => $user->id,
                'user_role' => $user->role_id,
                'company_id' => $companyId,
                'file_name' => $request->file('file')->getClientOriginalName(),
            ]);

            // Get agent_id based on user role
            if ($user->role_id == \App\Models\Role::AGENT) {
                // Agent user - use their own agent_id
                $agentId = $user->agent?->id;
                Log::info('[BULK UPLOAD] Using agent user', ['agent_id' => $agentId]);
            } else {
                // Company/Branch/Accountant/Admin - get from request (agent selector)
                $agentId = $request->input('agent_id');
                Log::info('[BULK UPLOAD] Using selected agent', ['agent_id' => $agentId]);

                // Validate agent_id is provided
                if (! $agentId) {
                    return response()->json([
                        'error' => 'Please select an agent to create invoices for.',
                    ], 422);
                }

                // Validate agent belongs to user's scope
                $agent = \App\Models\Agent::find($agentId);
                if (! $agent || $agent->branch->company_id != $companyId) {
                    return response()->json([
                        'error' => 'Invalid agent selection.',
                    ], 422);
                }
            }

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
                    'payment_id' => $rowResult['matched']['payment_id'] ?? null,
                    'raw_data' => json_encode($rows[$index]),
                    'errors' => ! empty($rowResult['errors']) ? json_encode($rowResult['errors']) : null,
                    'flag_reason' => $rowResult['flag_reason'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            BulkUploadRow::insert($rowRecords);

            // Redirect to preview page on successful validation
            return redirect()->route('bulk-invoices.preview', $bulkUpload->id)
                ->with('message', "Upload validated: {$results['valid']} valid rows, {$results['errors']} errors, {$results['flagged']} flagged.");
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

    /**
     * Preview the invoices to be created from a validated bulk upload.
     *
     * Displays grouped invoice cards by client and date, plus flagged rows section.
     *
     * @param  int  $id  The bulk upload ID
     */
    public function preview(int $id): View
    {
        $user = Auth::user();
        $companyId = getCompanyId($user);

        // Load BulkUpload scoped by company_id AND status='validated' with eager loading
        $bulkUpload = BulkUpload::where('id', $id)
            ->where('company_id', $companyId)
            ->where('status', 'validated')
            ->with(['rows.client'])
            ->firstOrFail();

        // Separate valid rows from flagged rows
        $validRows = $bulkUpload->rows->where('status', 'valid');
        $flaggedRows = $bulkUpload->rows->where('status', 'flagged');

        // Group valid rows by composite key (client_id + invoice_date)
        $invoiceGroups = $validRows->groupBy(function ($row) {
            $clientId = $row->client_id;
            $invoiceDate = $row->raw_data['invoice_date'] ?? date('Y-m-d');

            return "{$clientId}_{$invoiceDate}";
        });

        // Count unique clients
        $clientCount = $validRows->pluck('client_id')->unique()->count();

        return view('bulk-invoice.preview', compact('bulkUpload', 'invoiceGroups', 'flaggedRows', 'clientCount'));
    }

    /**
     * Approve bulk upload and mark it for processing.
     *
     * Uses conditional update to prevent race conditions (double-click, concurrent requests).
     * Only updates if status is still 'validated'.
     *
     * @param  int  $id  The bulk upload ID
     */
    public function approve(int $id): RedirectResponse
    {
        $user = Auth::user();
        $companyId = getCompanyId($user);

        // Conditional update to prevent race conditions
        $updated = BulkUpload::where('id', $id)
            ->where('company_id', $companyId)
            ->where('status', 'validated')
            ->update(['status' => 'processing']);

        if ($updated === 0) {
            return redirect()->back()->withErrors(['error' => 'Upload already processed or no longer in validated status.']);
        }

        // Dispatch job AFTER status update committed (afterCommit prevents job
        // from running before 'processing' status is visible in database)
        CreateBulkInvoicesJob::dispatch($id)
            ->onQueue('invoices')
            ->afterCommit();

        return redirect()->route('bulk-invoices.success', $id)
            ->with('message', 'Invoices are being created in the background.');
    }

    /**
     * Reject bulk upload and mark it as discarded.
     *
     * Uses conditional update to prevent race conditions.
     *
     * @param  int  $id  The bulk upload ID
     */
    public function reject(int $id): RedirectResponse
    {
        $user = Auth::user();
        $companyId = getCompanyId($user);

        // Conditional update to prevent race conditions
        $updated = BulkUpload::where('id', $id)
            ->where('company_id', $companyId)
            ->where('status', 'validated')
            ->update(['status' => 'rejected']);

        if ($updated === 0) {
            return redirect()->back()->withErrors(['error' => 'Upload already processed or no longer in validated status.']);
        }

        return redirect()->route('dashboard')->with('message', 'Upload rejected and discarded.');
    }

    /**
     * Show success page after bulk upload approval.
     *
     * Displays upload summary with invoice/client counts.
     * Loads actual created invoices when processing is complete.
     *
     * @param  int  $id  The bulk upload ID
     */
    public function success(int $id): View
    {
        $user = Auth::user();
        $companyId = getCompanyId($user);

        // Load BulkUpload scoped by company_id
        $bulkUpload = BulkUpload::where('id', $id)
            ->where('company_id', $companyId)
            ->firstOrFail();

        // Load valid rows for counting
        $validRows = $bulkUpload->rows()->where('status', 'valid')->with('client')->get();

        // Group by composite key to count invoices (same logic as preview)
        $invoiceGroups = $validRows->groupBy(function ($row) {
            $clientId = $row->client_id;
            $invoiceDate = $row->raw_data['invoice_date'] ?? date('Y-m-d');

            return "{$clientId}_{$invoiceDate}";
        });

        $invoiceCount = $invoiceGroups->count();
        $clientCount = $validRows->pluck('client_id')->unique()->count();

        // Load actual invoices if processing is complete
        $invoices = collect([]);
        if ($bulkUpload->status === 'completed' && ! empty($bulkUpload->invoice_ids)) {
            $invoices = Invoice::whereIn('id', $bulkUpload->invoice_ids)
                ->with('client')
                ->get();
        }

        return view('bulk-invoice.success', compact(
            'bulkUpload', 'invoiceCount', 'clientCount', 'invoices'
        ));
    }
}
