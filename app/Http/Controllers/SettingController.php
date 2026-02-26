<?php

namespace App\Http\Controllers;

use App\Models\Charge;
use App\Models\PaymentMethod;
use App\Models\PaymentMethodGroup;
use App\Models\PaymentMethodChose;
use App\Models\Role;
use App\Models\Setting;
use App\Models\UserSetting;
use App\Models\Agent;
use App\Models\AgentCharge;
use App\Models\AgentLoss;
use App\Models\AgentNotificationSetting;
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
        $bearerOptions = AgentCharge::getBearerOptions();

        return view('settings.index', compact(
            'invoiceExpiryDefault',
            'companyId',
            'activeTab',
            'invoiceWhatsappSetting',
            'bearerOptions',
        ));
    }

    public function saveTab(Request $request)
    {
        $request->validate([
            'tab' => 'required|in:invoice,payment,terms,charges,payment-methods,agent-charges,agent-loss,notifications',
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

    /**
     * Get agent charge settings for the company.
     */
    public function getAgentCharges(Request $request)
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
            $agents = Agent::whereHas('branch', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
                ->with('branch:id,name')
                ->select('id', 'name', 'email', 'branch_id', 'type_id', 'commission')
                ->get();

            $settings = AgentCharge::where('company_id', $companyId)
                ->get()
                ->keyBy('agent_id')
                ->toArray();

            return response()->json([
                'success' => true,
                'agents' => $agents,
                'settings' => $settings,
                'bearerOptions' => AgentCharge::getBearerOptions(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching agent charges', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch agent charge settings.',
            ], 500);
        }
    }

    /**
     * Store or update agent charge setting.
     */
    public function storeAgentCharge(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role_id, [Role::ADMIN, Role::COMPANY])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        $validated = $request->validate([
            'agent_id' => 'required|exists:agents,id',
            'company_id' => 'required|exists:companies,id',
            'charge_bearer' => 'required|in:company,agent,split',
            'agent_percentage' => 'required_if:charge_bearer,split|numeric|min:0|max:100',
            'company_percentage' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Auto-set percentages based on bearer
        if ($validated['charge_bearer'] === 'company') {
            $validated['agent_percentage'] = 0;
            $validated['company_percentage'] = 100;
        } elseif ($validated['charge_bearer'] === 'agent') {
            $validated['agent_percentage'] = 100;
            $validated['company_percentage'] = 0;
        } else {
            // Split - validate percentages sum to 100
            $validated['company_percentage'] = 100 - ($validated['agent_percentage'] ?? 0);

            if (abs(($validated['agent_percentage'] + $validated['company_percentage']) - 100) > 0.01) {
                return response()->json([
                    'success' => false,
                    'message' => 'Percentages must sum to 100%',
                ], 422);
            }
        }

        try {
            $setting = AgentCharge::updateOrCreate(
                [
                    'agent_id' => $validated['agent_id'],
                    'company_id' => $validated['company_id'],
                ],
                [
                    'charge_bearer' => $validated['charge_bearer'],
                    'agent_percentage' => $validated['agent_percentage'],
                    'company_percentage' => $validated['company_percentage'],
                    'notes' => $validated['notes'] ?? null,
                    'updated_by' => $user->id,
                ]
            );

            if ($setting->wasRecentlyCreated) {
                $setting->created_by = $user->id;
                $setting->save();
            }

            Log::info('AgentCharge saved', [
                'setting_id' => $setting->id,
                'agent_id' => $setting->agent_id,
                'bearer' => $setting->charge_bearer,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Setting saved successfully.',
                'setting' => $setting->toArray(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to save agent charge setting', [
                'error' => $e->getMessage(),
                'agent_id' => $validated['agent_id'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save setting: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk update agent charge settings.
     */
    public function bulkUpdateAgentCharges(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role_id, [Role::ADMIN, Role::COMPANY])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'agent_ids' => 'required|array|min:1',
            'agent_ids.*' => 'exists:agents,id',
            'charge_bearer' => 'required|in:company,agent,split',
            'agent_percentage' => 'required_if:charge_bearer,split|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        // Auto-set percentages
        if ($validated['charge_bearer'] === 'company') {
            $agentPct = 0;
            $companyPct = 100;
        } elseif ($validated['charge_bearer'] === 'agent') {
            $agentPct = 100;
            $companyPct = 0;
        } else {
            $agentPct = $validated['agent_percentage'] ?? 0;
            $companyPct = 100 - $agentPct;
        }

        try {
            $updated = 0;
            foreach ($validated['agent_ids'] as $agentId) {
                $setting = AgentCharge::updateOrCreate(
                    [
                        'agent_id' => $agentId,
                        'company_id' => $validated['company_id'],
                    ],
                    [
                        'charge_bearer' => $validated['charge_bearer'],
                        'agent_percentage' => $agentPct,
                        'company_percentage' => $companyPct,
                        'notes' => $validated['notes'] ?? null,
                        'updated_by' => $user->id,
                    ]
                );
                $updated++;

                if ($setting->wasRecentlyCreated) {
                    $setting->created_by = $user->id;
                    $setting->save();
                }
            }

            Log::info('Bulk agent charge settings updated', [
                'count' => $updated,
                'bearer' => $validated['charge_bearer'],
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Updated settings for {$updated} agents.",
            ]);
        } catch (\Exception $e) {
            Log::error('Bulk update failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update settings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete agent charge setting (reset to default).
     */
    public function deleteAgentCharge(int $id)
    {
        $user = Auth::user();

        if (!in_array($user->role_id, [Role::ADMIN, Role::COMPANY])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        try {
            $setting = AgentCharge::findOrFail($id);

            // Verify company access
            $companyId = getCompanyId($user);
            if ($user->role_id != Role::ADMIN && $setting->company_id != $companyId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.',
                ], 403);
            }

            $setting->delete();

            Log::info('AgentCharge deleted', [
                'setting_id' => $id,
                'agent_id' => $setting->agent_id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Setting deleted. Agent will use default (company bears all).',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete agent charge setting', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete setting.',
            ], 500);
        }
    }

    /**
     * Get agent loss settings for the company.
     */
    public function getAgentLoss(Request $request)
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
            $agents = Agent::whereHas('branch', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
                ->with([
                    'branch:id,name',
                    'lossAccount:id,name,code'
                ])
                ->select('id', 'name', 'email', 'branch_id', 'type_id', 'commission', 'loss_account_id')
                ->get();

            $settings = AgentLoss::where('company_id', $companyId)
                ->get()
                ->keyBy('agent_id')
                ->toArray();

            return response()->json([
                'success' => true,
                'agents' => $agents,
                'settings' => $settings,
                'bearerOptions' => AgentLoss::getBearerOptions(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching agent loss settings', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch agent loss settings.',
            ], 500);
        }
    }

    /**
     * Store or update agent loss setting.
     */
    public function storeAgentLoss(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role_id, [Role::ADMIN, Role::COMPANY])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        $validated = $request->validate([
            'agent_id' => 'required|exists:agents,id',
            'company_id' => 'required|exists:companies,id',
            'loss_bearer' => 'required|in:company,agent,split',
            'agent_percentage' => 'required_if:loss_bearer,split|numeric|min:0|max:100',
            'company_percentage' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Auto-set percentages based on bearer
        if ($validated['loss_bearer'] === 'company') {
            $validated['agent_percentage'] = 0;
            $validated['company_percentage'] = 100;
        } elseif ($validated['loss_bearer'] === 'agent') {
            $validated['agent_percentage'] = 100;
            $validated['company_percentage'] = 0;
        } else {
            // Split - validate percentages sum to 100
            $validated['company_percentage'] = 100 - ($validated['agent_percentage'] ?? 0);

            if (abs(($validated['agent_percentage'] + $validated['company_percentage']) - 100) > 0.01) {
                return response()->json([
                    'success' => false,
                    'message' => 'Percentages must sum to 100%',
                ], 422);
            }
        }

        try {
            $setting = AgentLoss::updateOrCreate(
                [
                    'agent_id' => $validated['agent_id'],
                    'company_id' => $validated['company_id'],
                ],
                [
                    'loss_bearer' => $validated['loss_bearer'],
                    'agent_percentage' => $validated['agent_percentage'],
                    'company_percentage' => $validated['company_percentage'],
                    'notes' => $validated['notes'] ?? null,
                    'updated_by' => $user->id,
                ]
            );

            if ($setting->wasRecentlyCreated) {
                $setting->created_by = $user->id;
                $setting->save();
            }

            Log::info('AgentLoss saved', [
                'setting_id' => $setting->id,
                'agent_id' => $setting->agent_id,
                'bearer' => $setting->loss_bearer,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Setting saved successfully.',
                'setting' => $setting->toArray(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to save agent loss setting', [
                'error' => $e->getMessage(),
                'agent_id' => $validated['agent_id'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save setting: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk update agent loss settings.
     */
    public function bulkUpdateAgentLoss(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role_id, [Role::ADMIN, Role::COMPANY])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'agent_ids' => 'required|array|min:1',
            'agent_ids.*' => 'exists:agents,id',
            'loss_bearer' => 'required|in:company,agent,split',
            'agent_percentage' => 'required_if:loss_bearer,split|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validated['loss_bearer'] === 'company') {
            $agentPct = 0;
            $companyPct = 100;
        } elseif ($validated['loss_bearer'] === 'agent') {
            $agentPct = 100;
            $companyPct = 0;
        } else {
            $agentPct = $validated['agent_percentage'] ?? 0;
            $companyPct = 100 - $agentPct;
        }

        try {
            $updated = 0;
            foreach ($validated['agent_ids'] as $agentId) {
                $setting = AgentLoss::updateOrCreate(
                    [
                        'agent_id' => $agentId,
                        'company_id' => $validated['company_id'],
                    ],
                    [
                        'loss_bearer' => $validated['loss_bearer'],
                        'agent_percentage' => $agentPct,
                        'company_percentage' => $companyPct,
                        'notes' => $validated['notes'] ?? null,
                        'updated_by' => $user->id,
                    ]
                );
                $updated++;

                if ($setting->wasRecentlyCreated) {
                    $setting->created_by = $user->id;
                    $setting->save();
                }
            }

            Log::info('Bulk agent loss settings updated', [
                'count' => $updated,
                'bearer' => $validated['loss_bearer'],
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Updated settings for {$updated} agents.",
            ]);
        } catch (\Exception $e) {
            Log::error('Bulk update failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update settings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete agent loss setting (reset to default).
     */
    public function deleteAgentLoss(int $id)
    {
        $user = Auth::user();

        if (!in_array($user->role_id, [Role::ADMIN, Role::COMPANY])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        try {
            $setting = AgentLoss::findOrFail($id);

            // Verify company access
            $companyId = getCompanyId($user);
            if ($user->role_id != Role::ADMIN && $setting->company_id != $companyId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.',
                ], 403);
            }

            $setting->delete();

            Log::info('AgentLoss deleted', [
                'setting_id' => $id,
                'agent_id' => $setting->agent_id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Setting deleted. Agent will use default (company bears all).',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete agent loss setting', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete setting.',
            ], 500);
        }
    }

    /**
     * Get notification settings (company-wide from settings table).
     */
    public function getNotificationSettings(Request $request)
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
            $prefixes = ['notification.unassigned_task', 'notification.autobill'];
            $settings = [];

            foreach ($prefixes as $prefix) {
                $settings[$prefix] = [
                    'channel' => Setting::getByKey($companyId, "{$prefix}.channel", 'none'),
                    'email' => Setting::getByKey($companyId, "{$prefix}.email", ''),
                    'phone' => Setting::getByKey($companyId, "{$prefix}.phone", ''),
                ];
            }

            return response()->json([
                'success' => true,
                'settings' => $settings,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching notification settings', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notification settings.',
            ], 500);
        }
    }

    /**
     * Update company-wide notification setting.
     */
    public function updateNotificationSetting(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role_id, [Role::ADMIN, Role::COMPANY])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        $validated = $request->validate([
            'prefix' => 'required|regex:/^notification\.[a-z_]+$/',
            'channel' => 'required|in:email,whatsapp,both,none',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        $companyId = getCompanyId($user);

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'No company selected.',
            ], 400);
        }

        // Validate that email/phone is provided when channel requires it
        if (in_array($validated['channel'], ['email', 'both']) && empty($validated['email'])) {
            return response()->json([
                'success' => false,
                'message' => 'Email is required when channel includes email.',
            ], 422);
        }

        if (in_array($validated['channel'], ['whatsapp', 'both']) && empty($validated['phone'])) {
            return response()->json([
                'success' => false,
                'message' => 'Phone number is required when channel includes WhatsApp.',
            ], 422);
        }

        try {
            $prefix = $validated['prefix'];

            $descriptions = [
                'notification.unassigned_task' => 'Unassigned task notification',
                'notification.autobill' => 'Auto billing notification',
            ];
            $desc = $descriptions[$prefix] ?? 'Notification setting';

            Setting::updateOrCreate(
                ['key' => "{$prefix}.channel", 'company_id' => $companyId],
                ['value' => $validated['channel'], 'type' => 'string', 'description' => "{$desc} - channel"]
            );

            $emailValue = in_array($validated['channel'], ['email', 'both']) ? $validated['email'] : null;
            $phoneValue = in_array($validated['channel'], ['whatsapp', 'both']) ? $validated['phone'] : null;

            Setting::updateOrCreate(
                ['key' => "{$prefix}.email", 'company_id' => $companyId],
                ['value' => $emailValue, 'type' => 'string', 'description' => "{$desc} - recipient email"]
            );

            Setting::updateOrCreate(
                ['key' => "{$prefix}.phone", 'company_id' => $companyId],
                ['value' => $phoneValue, 'type' => 'string', 'description' => "{$desc} - recipient phone"]
            );

            Log::info('Notification setting updated', [
                'prefix' => $prefix,
                'channel' => $validated['channel'],
                'company_id' => $companyId,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notification setting saved successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to save notification setting', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to save notification setting.',
            ], 500);
        }
    }

    /**
     * Get agent notification settings for agent task close tab.
     */
    public function getAgentNotifications(Request $request)
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
            $agents = Agent::whereHas('branch', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
                ->with('branch:id,name')
                ->select('id', 'name', 'email', 'phone_number', 'country_code', 'branch_id', 'type_id')
                ->get();

            $settings = AgentNotificationSetting::where('company_id', $companyId)
                ->where('notification_type', AgentNotificationSetting::TYPE_TASK_CLOSE)
                ->get()
                ->keyBy('agent_id')
                ->toArray();

            return response()->json([
                'success' => true,
                'agents' => $agents,
                'settings' => $settings,
                'channelOptions' => AgentNotificationSetting::getChannelOptions(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching agent notification settings', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch agent notification settings.',
            ], 500);
        }
    }

    /**
     * Store or update agent notification setting.
     */
    public function storeAgentNotification(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role_id, [Role::ADMIN, Role::COMPANY])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

        $validated = $request->validate([
            'agent_id' => 'required|exists:agents,id',
            'company_id' => 'required|exists:companies,id',
            'notification_type' => 'required|string|max:50',
            'channel' => 'required|in:email,whatsapp,both',
            'is_active' => 'required|boolean',
        ]);

        try {
            $setting = AgentNotificationSetting::updateOrCreate(
                [
                    'agent_id' => $validated['agent_id'],
                    'company_id' => $validated['company_id'],
                    'notification_type' => $validated['notification_type'],
                ],
                [
                    'channel' => $validated['channel'],
                    'is_active' => $validated['is_active'],
                    'updated_by' => $user->id,
                ]
            );

            if ($setting->wasRecentlyCreated) {
                $setting->created_by = $user->id;
                $setting->save();
            }

            Log::info('AgentNotificationSetting saved', [
                'setting_id' => $setting->id,
                'agent_id' => $setting->agent_id,
                'type' => $setting->notification_type,
                'channel' => $setting->channel,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Setting saved successfully.',
                'setting' => $setting->toArray(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to save agent notification setting', [
                'error' => $e->getMessage(),
                'agent_id' => $validated['agent_id'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save setting: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk update agent notification settings.
     */
    public function bulkUpdateAgentNotifications(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role_id, [Role::ADMIN, Role::COMPANY])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'agent_ids' => 'required|array|min:1',
            'agent_ids.*' => 'exists:agents,id',
            'notification_type' => 'required|string|max:50',
            'channel' => 'required|in:email,whatsapp,both',
            'is_active' => 'required|boolean',
        ]);

        try {
            $updated = 0;
            foreach ($validated['agent_ids'] as $agentId) {
                $setting = AgentNotificationSetting::updateOrCreate(
                    [
                        'agent_id' => $agentId,
                        'company_id' => $validated['company_id'],
                        'notification_type' => $validated['notification_type'],
                    ],
                    [
                        'channel' => $validated['channel'],
                        'is_active' => $validated['is_active'],
                        'updated_by' => $user->id,
                    ]
                );
                $updated++;

                if ($setting->wasRecentlyCreated) {
                    $setting->created_by = $user->id;
                    $setting->save();
                }
            }

            Log::info('Bulk agent notification settings updated', [
                'count' => $updated,
                'type' => $validated['notification_type'],
                'channel' => $validated['channel'],
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Updated settings for {$updated} agents.",
            ]);
        } catch (\Exception $e) {
            Log::error('Bulk notification update failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update settings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete agent notification setting (reset to default).
     */
    public function deleteAgentNotification(int $id)
    {
        $user = Auth::user();

        if (!in_array($user->role_id, [Role::ADMIN, Role::COMPANY])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        try {
            $setting = AgentNotificationSetting::findOrFail($id);

            $companyId = getCompanyId($user);
            if ($user->role_id != Role::ADMIN && $setting->company_id != $companyId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.',
                ], 403);
            }

            $setting->delete();

            Log::info('AgentNotificationSetting deleted', [
                'setting_id' => $id,
                'agent_id' => $setting->agent_id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Setting deleted. Agent notification disabled.',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete agent notification setting', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete setting.',
            ], 500);
        }
    }
}
