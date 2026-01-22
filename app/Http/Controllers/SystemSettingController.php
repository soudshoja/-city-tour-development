<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMailTypeEnum;
use App\Mail\PaymentMail;
use App\Models\Payment;
use App\Models\PaymentFile;
use App\Models\Role;
use App\Services\PaymentReceiptService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;

class SystemSettingController extends Controller
{
    public function index()
    {
        Gate::authorize('manage-system-settings');

        $user = Auth::user();
        $companyId = getCompanyId($user);

        $paymentsQuery = Payment::with(['client', 'agent.branch.company'])
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc');

        if ($companyId) {
            $paymentsQuery->whereHas('agent.branch', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            });
        }

        $payments = $paymentsQuery->limit(50)->get();

        return view('admin.system-settings.index', compact('payments', 'companyId'));
    }

    public function sendTestEmail(Request $request)
    {
        Gate::authorize('manage-email-tester');

        $request->validate([
            'payment_id' => 'required|exists:payments,id',
            'email' => 'required|email',
            'email_type' => 'required|in:payment_success,payment_failure',
        ]);

        $user = Auth::user();
        $companyId = getCompanyId($user);

        $paymentQuery = Payment::where('id', $request->payment_id);

        if ($companyId) {
            $paymentQuery->whereHas('agent.branch', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            });
        }

        $payment = $paymentQuery->firstOrFail();
        $emailType = PaymentMailTypeEnum::from($request->email_type);

        try {
            Mail::to($request->email)->send(new PaymentMail($payment->id, $emailType));

            return back()->with('success', "Test email sent successfully to {$request->email}");
        } catch (\Exception $e) {
            return back()->with('error', "Failed to send email: {$e->getMessage()}");
        }
    }

    public function previewEmail(Request $request)
    {
        Gate::authorize('manage-email-tester');

        $user = Auth::user();
        $companyId = getCompanyId($user);

        $paymentQuery = Payment::where('id', $request->payment_id);

        if ($companyId) {
            $paymentQuery->whereHas('agent.branch', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            });
        }

        $payment = $paymentQuery->firstOrFail();

        return view('payment.pdf.success', [
            'payment' => $payment,
        ]);
    }

    public function sendWhatsAppPdf(Request $request)
    {
        Gate::authorize('manage-email-tester');

        $request->validate([
            'payment_id' => 'required|exists:payments,id',
            'phone' => 'required|string',
            'country_code' => 'required|string',
        ]);

        $user = Auth::user();
        $companyId = getCompanyId($user);

        $paymentQuery = Payment::where('id', $request->payment_id);

        if ($companyId) {
            $paymentQuery->whereHas('agent.branch', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            });
        }

        $payment = $paymentQuery->firstOrFail();

        $service = new PaymentReceiptService();
        $result = $service->generateAndSendPdf(
            $payment,
            $request->phone,
            $request->country_code
        );

        if ($result['success']) {
            return back()->with('success', $result['message']);
        } else {
            return back()->with('error', $result['message']);
        }
    }

    public function downloadPdf(Request $request)
    {
        Gate::authorize('manage-email-tester');

        $user = Auth::user();
        $companyId = getCompanyId($user);

        $paymentQuery = Payment::with(['client', 'agent.branch.company', 'paymentItems', 'paymentMethod'])
            ->where('id', $request->payment_id);

        if ($companyId) {
            $paymentQuery->whereHas('agent.branch', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            });
        }

        $payment = $paymentQuery->firstOrFail();

        $pdf = Pdf::loadView('payment.pdf.success', ['payment' => $payment, 'isPdf' => true]);

        return $pdf->download("payment_receipt_{$payment->voucher_number}.pdf");
    }

    public function saveTab(Request $request)
    {
        $request->validate([
            'tab' => 'required|in:email,whatsapp',
        ]);

        session(['system_settings_active_tab' => $request->tab]);

        return response()->json(['success' => true]);
    }

    public function checkFileStatus(Request $request)
    {
        Gate::authorize('manage-system-settings');

        $request->validate([
            'payment_id' => 'required|exists:payments,id',
        ]);

        $user = Auth::user();
        $companyId = getCompanyId($user);

        $paymentQuery = Payment::where('id', $request->payment_id);

        if ($companyId) {
            $paymentQuery->whereHas('agent.branch', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            });
        }

        $payment = $paymentQuery->firstOrFail();

        $paymentFile = PaymentFile::where('payment_id', $payment->id)
            ->where('expiry_date', '>', now())
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$paymentFile) {
            return response()->json([
                'has_file' => false,
                'message' => 'No file uploaded yet. Will upload new file on send.',
                'status' => 'none'
            ]);
        }

        $resayil = new ResayilController();
        $fileInfo = $resayil->getFileInfo($paymentFile->file_id);

        if (!($fileInfo['success'] ?? false)) {
            return response()->json([
                'has_file' => true,
                'file_id' => $paymentFile->file_id,
                'status' => 'error',
                'message' => 'Could not verify file status with Resayil API. Will re-upload on send.',
                'expiry_date' => $paymentFile->expiry_date->format('Y-m-d H:i:s'),
                'created_at' => $paymentFile->created_at->format('Y-m-d H:i:s')
            ]);
        }

        $fileData = $fileInfo['data'] ?? [];
        $isActive = $fileInfo['is_active'] ?? false;

        return response()->json([
            'has_file' => true,
            'file_id' => $paymentFile->file_id,
            'status' => $fileData['status'] ?? 'unknown',
            'is_active' => $isActive,
            'message' => $isActive
                ? '✓ File is active in Resayil. Will reuse existing file on send.'
                : '⚠ File is not active. Will upload new file on send.',
            'expiry_date' => $paymentFile->expiry_date->format('Y-m-d H:i:s'),
            'created_at' => $paymentFile->created_at->format('Y-m-d H:i:s'),
            'file_data' => [
                'filename' => $fileData['filename'] ?? null,
                'size' => $fileData['size'] ?? null,
                'mime' => $fileData['mime'] ?? null,
                'last_access' => $fileData['lastAccessAt'] ?? null,
                'deliveries' => $fileData['stats']['deliveries'] ?? 0,
            ]
        ]);
    }
}
