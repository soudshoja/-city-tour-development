<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMailTypeEnum;
use App\Mail\PaymentMail;
use App\Models\Payment;
use App\Models\PaymentFile;
use App\Policies\SystemSettingPolicy;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SystemSettingController extends Controller
{
    public function index()
    {
        Gate::authorize('manage-system-settings');

        $payments = Payment::with(['client', 'agent'])
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return view('admin.system-settings.index', compact('payments'));
    }

    public function sendTestEmail(Request $request)
    {
        Gate::authorize('manage-email-tester');

        $request->validate([
            'payment_id' => 'required|exists:payments,id',
            'email' => 'required|email',
            'email_type' => 'required|in:payment_success,payment_failure',
        ]);

        $payment = Payment::findOrFail($request->payment_id);
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

        $payment = Payment::findOrFail($request->payment_id);
        
        return view('email.payment.success', [
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

        $payment = Payment::with(['client', 'agent.branch.company', 'paymentItems', 'paymentMethod'])
            ->findOrFail($request->payment_id);

        try {
            $paymentFile = PaymentFile::where('payment_id', $payment->id)
                ->where('expiry_date', '>', now())
                ->first();

            $fileId = null;
            $filePath = null;
            
            if (!$paymentFile) {
                // No valid cache, generate PDF and upload
                $pdf = Pdf::loadView('email.payment.success', ['payment' => $payment]);
                
                $filename = "payment_receipt_{$payment->voucher_number}.pdf";
                $path = "temp/{$filename}";
                
                Storage::disk('public')->put($path, $pdf->output());
                
                $filePath = storage_path("app/public/{$path}");
            } else {
                $fileId = $paymentFile->file_id;
                
                // Generate PDF in case file is no longer active
                $pdf = Pdf::loadView('email.payment.success', ['payment' => $payment]);
                
                $filename = "payment_receipt_{$payment->voucher_number}.pdf";
                $path = "temp/{$filename}";
                
                Storage::disk('public')->put($path, $pdf->output());
                
                $filePath = storage_path("app/public/{$path}");
            }

            // Send WhatsApp message (ResayilController will verify file_id validity)
            $resayil = new ResayilController();
            $response = $resayil->document(
                $request->phone,
                $request->country_code,
                $filePath,
                "payment_receipt_{$payment->voucher_number}.pdf",
                "Payment Receipt - {$payment->voucher_number}",
                true,
                $fileId
            );

            // Clean up temp file
            if ($filePath && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }

            // If document method returned new file info (re-uploaded), save it
            if (($response['success'] ?? false) && isset($response['new_file_id'])) {
                $newFileId = $response['new_file_id'];
                $expiresAt = $response['expires_at'] ?? null;
                
                if ($expiresAt) {
                    PaymentFile::create([
                        'payment_id' => $payment->id,
                        'file_id' => $newFileId,
                        'expiry_date' => \Carbon\Carbon::parse($expiresAt)
                    ]);
                }
            }

            if ($response['success'] ?? false) {
                return back()->with('success', "PDF sent successfully via WhatsApp to {$request->country_code}{$request->phone}");
            } else {
                return back()->with('error', "Failed to send PDF: " . ($response['error'] ?? 'Unknown error'));
            }
        } catch (\Exception $e) {
            return back()->with('error', "Failed to generate/send PDF: {$e->getMessage()}");
        }
    }

    public function downloadPdf(Request $request)
    {
        Gate::authorize('manage-email-tester');

        $payment = Payment::with(['client', 'agent.branch.company', 'paymentItems', 'paymentMethod'])
            ->findOrFail($request->payment_id);

        $pdf = Pdf::loadView('email.payment.success', ['payment' => $payment]);
        
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

        $payment = Payment::findOrFail($request->payment_id);

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
