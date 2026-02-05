<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMailTypeEnum;
use App\Mail\PaymentMail;
use App\Models\Payment;
use App\Models\PaymentFile;
use App\Models\Role;
use App\Models\Country;
use App\Models\Hotel;
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
            'tab' => 'required|in:email,whatsapp,hotel',
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

    public function searchCountries(Request $request)
    {
        $search = trim($request->get('search', ''));
        $query = Country::where('is_active', 1);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "{$search}%")
                    ->orWhere('iso_code', 'LIKE', "{$search}%");
            });
        }

        $countries = $query->orderBy('name')
            ->get(['id', 'name', 'iso_code'])
            ->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name . ' (' . $c->iso_code . ')'
            ]);

        return response()->json($countries);
    }

    public function storeCountry(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'iso_code' => 'required|string|size:2|unique:countries,iso_code',
            'iso3_code' => 'nullable|string|max:3',
            'dialing_code' => 'nullable|string|max:10',
            'nationality' => 'nullable|string|max:255',
            'nationality_ar' => 'nullable|string|max:255',
            'currency_code' => 'nullable|string|max:3',
            'continent' => 'nullable|string|max:50',
        ]);

        try {
            $country = Country::create([
                'name' => $validated['name'],
                'name_ar' => $validated['name_ar'] ?? null,
                'iso_code' => strtoupper($validated['iso_code']),
                'iso3_code' => $validated['iso3_code'] ? strtoupper($validated['iso3_code']) : null,
                'dialing_code' => $validated['dialing_code'] ?? null,
                'nationality' => $validated['nationality'] ?? null,
                'nationality_ar' => $validated['nationality_ar'] ?? null,
                'currency_code' => $validated['currency_code'] ? strtoupper($validated['currency_code']) : null,
                'continent' => $validated['continent'] ?? null,
                'is_active' => 1,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Country added successfully',
                'country' => $country
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error adding country: ' . $e->getMessage()
            ], 500);
        }
    }

    public function hotelsList(Request $request)
    {
        $search = trim($request->get('search', ''));

        $query = Hotel::with('countryRelation:id,name,iso_code');

        if (strlen($search) >= 2) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('city', 'LIKE', "{$search}%")
                    ->orWhere('country', 'LIKE', "{$search}%");
            });
        }

        $hotels = $query->orderBy('name')->paginate($request->get('per_page', 20));

        $hotels->through(function ($hotel) {
            return [
                'id' => $hotel->id,
                'name' => $hotel->name,
                'address' => $hotel->address,
                'city' => $hotel->city,
                'state' => $hotel->state,
                'country' => $hotel->country,
                'country_id' => $hotel->country_id,
                'country_data' => $hotel->countryRelation ? [
                    'id' => $hotel->countryRelation->id,
                    'name' => $hotel->countryRelation->name,
                    'iso_code' => $hotel->countryRelation->iso_code,
                ] : null,
                'zip_code' => $hotel->zip_code,
                'phone' => $hotel->phone,
                'email' => $hotel->email,
                'website' => $hotel->website,
                'rating' => $hotel->rating,
                'latitude' => $hotel->latitude,
                'longitude' => $hotel->longitude,
            ];
        });

        return response()->json($hotels);
    }

    public function storeHotel(Request $request)
    {
        Gate::authorize('manage-system-settings');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'country_id' => 'nullable|exists:countries,id',
            'zip_code' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:500',
            'rating' => 'nullable|integer|min:1|max:5',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        try {
            $validated['country'] = $validated['country_id'] ? Country::where('id', $validated['country_id'])->value('iso_code') : null;

            $hotel = Hotel::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Hotel added successfully',
                'hotel' => $hotel
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error adding hotel: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateHotel(Request $request, $id)
    {
        Gate::authorize('manage-system-settings');

        $hotel = Hotel::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'country_id' => 'nullable|exists:countries,id',
            'zip_code' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:500',
            'rating' => 'nullable|integer|min:1|max:5',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        try {
            $validated['country'] = $validated['country_id'] ? Country::where('id', $validated['country_id'])->value('iso_code') : null;

            $hotel->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Hotel updated successfully',
                'hotel' => $hotel
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating hotel: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteHotel($id)
    {
        Gate::authorize('manage-system-settings');

        try {
            Hotel::findOrFail($id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Hotel deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting hotel: ' . $e->getMessage()
            ], 500);
        }
    }
}
