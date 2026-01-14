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
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class SettingController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'company_id' => 'nullable|exists:companies,id',
        ]);

        if(auth()->user()->role_id == Role::ADMIN){
            if (!$request->has('company_id') && session()->has('company_id')) {
                return redirect()->route('settings.index', [
                    'company_id' => session('company_id')
                ]);
            } 
        } else {
            if ($request->has('company_id')) {
                return redirect()->route('settings.index');
            }
        }

        $companyId = getCompanyId(auth()->user());

        Setting::all()->each(function ($setting) {
            $setting->value = $setting->value;
        });

        $settings = Setting::where('company_id', $companyId)
            ->get();

        $invoiceExpiryDefault = $settings->firstWhere('key', 'invoice_expiry_days')->value ?? 30;
        $isAdmin = auth()->user()->role_id == Role::ADMIN && auth()->user()->hasRole('admin');
        $activeTab = session('settings_active_tab', 'invoice');

        $invoiceWhatsappSetting = UserSetting::getValue(auth()->id(), 'invoice_whatsapp_notification', false);

        return view('settings.index', compact(
            'invoiceExpiryDefault',
            'isAdmin',
            'companyId',
            'activeTab',
            'invoiceWhatsappSetting'
        ));
    }

    public function saveTab(Request $request)
    {
        $request->validate([
            'tab' => 'required|in:invoice,payment,terms,charges,payment-methods',
            'company_id' => 'nullable|integer',
        ]);

        // Log::info("[SETTINGS] Saving active tab", ['tab' => $request->tab, 'company_id' => $request->company_id]);

        session(['settings_active_tab' => $request->tab]);

        // Log::info("[SETTINGS] Active tab saved", ['active tab' => session('settings_active_tab')]);
        
        if ($request->has('company_id')) {
            session(['company_id' => $request->company_id]);
        }

        return response()->json(['success' => true]);
    }

    public function updateInvoiceExpiry(Request $request)
    {

        if(!(Auth()->user()->role_id = Role::ADMIN || Auth()->user()->role_id = Role::SUPER_ADMIN)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update settings.',
            ], 403);
        }

        $request->validate([
            'invoice_expiry_default' => 'required|integer|min:1|max:365',
        ]);

        $expiryDays = (int) $request->input('invoice_expiry_default');
        $setting = Setting::updateOrCreate(
            ['key' => 'invoice_expiry_days'],
            [
                'value' => $expiryDays,
            ]
        );

        if(!$setting) {
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

        Log::info('[SETTINGS] Fetching charges', ['request' => $request->all()]);

        $request->validate([
            'company_id' => 'nullable|integer|exists:companies,id',
        ]);

        $user = auth()->user();
        $companyId = $this->resolveCompanyId($request, $user);

        if ($companyId === null) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to determine company.',
            ], 403);
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

    /**
     * Get payment method groups via AJAX
     */
    public function getPaymentMethods(Request $request)
    {
        $request->validate([
            'company_id' => 'nullable|integer|exists:companies,id',
        ]);

        $user = auth()->user();
        $companyId = $this->resolveCompanyId($request, $user);

        if ($companyId === null) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to determine company.',
            ], 403);
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

    private function resolveCompanyId(Request $request, $user): ?int
    {
        if ($user->role_id == Role::ADMIN) {
            return $request->input('company_id', 1);
        } elseif ($user->role_id == Role::COMPANY) {
            return $user->company->id ?? null;
        } elseif ($user->role_id == Role::BRANCH) {
            return $user->branch->company_id ?? null;
        } elseif ($user->role_id == Role::AGENT) {
            return $user->agent->branch->company_id ?? null;
        } elseif ($user->role_id == Role::ACCOUNTANT) {
            return $user->accountant->branch->company_id ?? null;
        }

        return null;
    }
}
