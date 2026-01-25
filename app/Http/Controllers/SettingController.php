<?php

namespace App\Http\Controllers;

use App\Models\Charge;
use App\Models\PaymentMethod;
use App\Models\PaymentMethodGroup;
use App\Models\PaymentMethodChose;
use App\Models\Role;
use App\Models\Setting;
use App\Models\UserSetting;
use Database\Seeders\SettingSeeder;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class SettingController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $companyId = getCompanyId($user);

        $settings = Setting::where('company_id', $companyId)->get();

        $invoiceExpiryDefault = $settings->firstWhere('key', 'invoice_expiry_days')->value ?? 30;
        $activeTab = session('settings_active_tab', 'payment');
        $invoiceWhatsappSetting = UserSetting::getValue(Auth::user()->id, 'invoice_whatsapp_notification', false);

        return view('settings.index', compact(
            'invoiceExpiryDefault',
            'companyId',
            'activeTab',
            'invoiceWhatsappSetting'
        ));
    }

    public function saveTab(Request $request)
    {
        $request->validate([
            'tab' => 'required|in:invoice,payment,terms,charges,payment-methods',
        ]);

        session(['settings_active_tab' => $request->tab]);

        return response()->json(['success' => true]);
    }

    public function updateInvoiceExpiry(Request $request)
    {
        $user = Auth::user();

        if (!($user->role_id == Role::ADMIN || $user->role_id == Role::COMPANY)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update settings.',
            ], 403);
        }

        $request->validate([
            'invoice_expiry_default' => 'required|integer|min:1|max:365',
        ]);

        $companyId = getCompanyId($user);

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'No company selected.',
            ], 400);
        }

        $expiryDays = (int) $request->input('invoice_expiry_default');
        $setting = Setting::updateOrCreate(
            [
                'key' => 'invoice_expiry_days',
                'company_id' => $companyId,
            ],
            [
                'value' => $expiryDays,
            ]
        );

        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update invoice expiry days.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Invoice expiry days updated successfully.',
        ]);
    }

    public function getCharges(Request $request)
    {
        Gate::authorize('viewAny', Charge::class);

        $user = Auth::user();
        $companyId = getCompanyId($user);

        if ($companyId === null) {
            return response()->json([
                'success' => false,
                'message' => 'No company selected.',
            ], 400);
        }

        try {
            $charges = Charge::with(['methods' => function ($query) {
                $query->select('id', 'charge_id', 'english_name', 'arabic_name', 'paid_by', 'self_charge', 'service_charge', 'charge_type', 'is_active', 'description', 'currency');
            }])
                ->where('company_id', $companyId)
                ->get();

            return response()->json([
                'success' => true,
                'charges' => $charges,
                'total' => $charges->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching charges', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch charges.',
            ], 500);
        }
    }

    public function getPaymentMethods(Request $request)
    {
        $user = Auth::user();
        $companyId = getCompanyId($user);

        if ($companyId === null) {
            return response()->json([
                'success' => false,
                'message' => 'No company selected.',
            ], 400);
        }

        try {
            $companyChargeIds = Charge::where('company_id', $companyId)
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();

            $paymentMethodGroups = PaymentMethodGroup::with(['paymentMethods' => function ($query) use ($companyChargeIds) {
                $query->whereIn('charge_id', $companyChargeIds)
                    ->with(['company:id,name', 'charge:id,name']);
            }])
                ->whereHas('paymentMethods', function ($query) use ($companyChargeIds) {
                    $query->whereIn('charge_id', $companyChargeIds);
                })
                ->get();

            $choices = PaymentMethodChose::where('company_id', $companyId)->get();
            $selectedMethods = $choices->pluck('payment_method_id', 'payment_method_group_id')->toArray();
            $enabledGroups = $choices->pluck('is_enabled', 'payment_method_group_id')->toArray();
            $choiceIds = $choices->pluck('id', 'payment_method_group_id')->toArray();

            return response()->json([
                'success' => true,
                'paymentMethodGroups' => $paymentMethodGroups,
                'selectedMethods' => $selectedMethods,
                'enabledGroups' => $enabledGroups,
                'choiceIds' => $choiceIds,
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching payment methods', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payment methods.',
            ], 500);
        }
    }
}
