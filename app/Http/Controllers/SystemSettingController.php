<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMailTypeEnum;
use App\Mail\PaymentMail;
use App\Models\Payment;
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
            $pdf = Pdf::loadView('email.payment.success', ['payment' => $payment]);
            
            $filename = "payment_receipt_{$payment->voucher_number}.pdf";
            $path = "temp/{$filename}";
            
            Storage::disk('public')->put($path, $pdf->output());
            
            $filePath = storage_path("app/public/{$path}");

            $resayil = new ResayilController();
            $response = $resayil->document(
                $request->phone,
                $request->country_code,
                $filePath,
                $filename,
                "Payment Receipt - {$payment->voucher_number}"
            );

            Storage::disk('public')->delete($path);

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
}
