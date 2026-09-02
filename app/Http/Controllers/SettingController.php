<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AgentCharge;
use App\Models\AgentLoss;
use App\Models\AgentNotificationSetting;
use App\Services\Accounting\VoucherOptions;
use App\Services\Reminders\ReminderOptions;
use Database\Seeders\SettingSeeder;
use App\Models\Charge;
use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\PaymentMethodChose;
use App\Models\PaymentMethodGroup;
use App\Models\Role;
use App\Models\Setting;
use App\Models\UserSetting;
use App\Services\Resayil\ResayilAdminService;
use App\Support\Modules;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class SettingController extends Controller
{
    public function index(Request $request, ResayilAdminService $resayilAdmin)
    {
        $user = Auth::user();
        $companyId = getCompanyId($user);

        $settings = Setting::where('company_id', $companyId)->get();

        $invoiceExpiryDefault = $settings->firstWhere('key', 'invoice_expiry_days')->value ?? 30;
        $activeTab = session('settings_active_tab', 'payment');
        $invoiceWhatsappSetting = UserSetting::getValue(Auth::user()->id, 'invoice_whatsapp_notification', false);
        $bearerOptions = AgentCharge::getBearerOptions();

        // Reset active tab if user doesn't have permission for it
        $tabPermissions = [
            'charges' => ['viewAny', Charge::class],
            'payment-methods' => ['viewPaymentMethodGroup', PaymentMethod::class],
            'agent-charges' => ['viewAgentCharges', Setting::class],
            'agent-loss' => ['viewAgentLoss', Setting::class],
            'notifications' => ['viewNotifications', Setting::class],
            'accounting' => ['viewAccountingSettings', Setting::class],
        ];
        if (isset($tabPermissions[$activeTab])) {
            [$ability, $model] = $tabPermissions[$activeTab];
            if (! Gate::allows($ability, $model)) {
                $activeTab = 'payment';
            }
        }
        if ($activeTab === 'ai-config' && $user->role_id !== Role::ADMIN) {
            $activeTab = 'payment';
        }

        // Settings -> WhatsApp (redesign, 2026-08-26): the Resayil Admin
        // Center now renders INSIDE this page as a tab instead of a
        // separate route the sidebar linked out to. Same two gates the
        // standalone resayil-admin.index route carries (module:resayil,
        // can:manage-resayil) — checked here explicitly rather than
        // relying on the view alone, so a user without access never even
        // triggers the reseller-backed overview() call below.
        $hasWhatsappAccess = $companyId
            && Company::find($companyId)?->hasModule(Modules::RESAYIL)
            && Gate::allows('manage-resayil');

        if ($activeTab === 'whatsapp' && ! $hasWhatsappAccess) {
            $activeTab = 'payment';
        }

        $resayilOverview = null;
        $resayilActivePanel = 'overview';
        $resayilEmbedUrl = null;
        $resayilNotConfigured = true;

        if ($hasWhatsappAccess) {
            // overview() is cached per company (ResayilAdminService::overview,
            // config('resayil.admin_cache_ttl'), default 60s) — this is a
            // cache hit on every Settings load except the first one in that
            // window, same cost the standalone page already paid on every
            // visit. Only fetched for a user who can actually see the tab.
            $resayilOverview = $resayilAdmin->overview($companyId);
            $resayilActivePanel = in_array($request->query('panel'), ['overview', 'billing', 'team', 'inbox'], true)
                ? $request->query('panel')
                : 'overview';
            $resayilEmbedUrl = config('resayil.embed_url');
            $resayilNotConfigured = empty($resayilEmbedUrl);
        }

        return view('settings.index', compact(
            'invoiceExpiryDefault',
            'companyId',
            'activeTab',
            'invoiceWhatsappSetting',
            'bearerOptions',
            'resayilOverview',
            'resayilActivePanel',
            'resayilEmbedUrl',
            'resayilNotConfigured',
        ));
    }

    public function saveTab(Request $request)
    {
        $request->validate([
            'tab' => 'required|in:invoice,payment,terms,charges,payment-methods,agent-charges,agent-loss,notifications,accounting,ai-config,whatsapp',
        ]);

        session(['settings_active_tab' => $request->tab]);

        return response()->json(['success' => true]);
    }

    public function updateInvoiceExpiry(Request $request)
    {
        Gate::authorize('settingCompanyInvoice', Setting::class);
        $user = Auth::user();

        $request->validate([
            'invoice_expiry_default' => 'required|integer|min:1|max:365',
        ]);

        $companyId = getCompanyId($user);

        if (! $companyId) {
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

        if (! $setting) {
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
        Gate::authorize('viewAgentCharges', Setting::class);
        $user = Auth::user();
        $companyId = getCompanyId($user);

        if ($companyId === null) {
            return response()->json([
                'success' => false,
                'message' => 'No company selected.',
            ], 400);
        }

        try {
            $agentsQuery = Agent::whereHas('branch', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
                ->with('branch:id,name')
                ->select('id', 'name', 'email', 'branch_id', 'type_id', 'commission');

            if ($user->agent) {
                $agentsQuery->where('id', $user->agent->id);
            }
            $agents = $agentsQuery->get();

            $settingsQuery = AgentCharge::where('company_id', $companyId);
            if ($user->agent) {
                $settingsQuery->where('agent_id', $user->agent->id);
            }

            $settings = $settingsQuery->get()->keyBy('agent_id')->toArray();

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
        Gate::authorize('manageAgentCharges', Setting::class);
        $user = Auth::user();

        if ($user->agent && $request->agent_id != $user->agent->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
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
                'message' => 'Failed to save setting: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk update agent charge settings.
     */
    public function bulkUpdateAgentCharges(Request $request)
    {
        Gate::authorize('bulkManageAgentCharges', Setting::class);
        $user = Auth::user();

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
                'message' => 'Failed to update settings: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete agent charge setting (reset to default).
     */
    public function deleteAgentCharge(int $id)
    {
        Gate::authorize('manageAgentCharges', Setting::class);
        $user = Auth::user();

        try {
            $setting = AgentCharge::findOrFail($id);

            if ($user->agent && $setting->agent_id != $user->agent->id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

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
        Gate::authorize('viewAgentLoss', Setting::class);
        $user = Auth::user();
        $companyId = getCompanyId($user);

        if ($companyId === null) {
            return response()->json([
                'success' => false,
                'message' => 'No company selected.',
            ], 400);
        }

        try {
            $agentsQuery = Agent::whereHas('branch', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
                ->with([
                    'branch:id,name',
                    'lossAccount:id,name,code',
                ])
                ->select('id', 'name', 'email', 'branch_id', 'type_id', 'commission', 'loss_account_id');

            if ($user->agent) {
                $agentsQuery->where('id', $user->agent->id);
            }
            $agents = $agentsQuery->get();

            $settingsQuery = AgentLoss::where('company_id', $companyId);
            if ($user->agent) {
                $settingsQuery->where('agent_id', $user->agent->id);
            }

            $settings = $settingsQuery->get()->keyBy('agent_id')->toArray();

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
        Gate::authorize('manageAgentLoss', Setting::class);
        $user = Auth::user();

        if ($user->agent && $request->agent_id != $user->agent->id) {
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
                'message' => 'Failed to save setting: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk update agent loss settings.
     */
    public function bulkUpdateAgentLoss(Request $request)
    {
        Gate::authorize('bulkManageAgentLoss', Setting::class);
        $user = Auth::user();

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
                'message' => 'Failed to update settings: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete agent loss setting (reset to default).
     */
    public function deleteAgentLoss(int $id)
    {
        Gate::authorize('manageAgentLoss', Setting::class);
        $user = Auth::user();

        try {
            $setting = AgentLoss::findOrFail($id);

            if ($user->agent && $setting->agent_id != $user->agent->id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

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
     * AI Configuration (Settings > AI Configuration, admin only).
     * Values live in the settings table (aicfg.* on company 1) and override
     * config/ai.php at boot via AiConfigOverride. Blank/deleted = .env default.
     */
    public function getAiConfig()
    {
        if (Auth::user()->role_id !== Role::ADMIN) {
            abort(403, 'Unauthorized action.');
        }

        \App\Services\AiConfigOverride::apply();

        $values = [];
        $overridden = [];
        $stored = Setting::where('company_id', 1)->where('key', 'like', 'aicfg.%')->pluck('value', 'key');
        foreach (\App\Services\AiConfigOverride::MAP as $field => $paths) {
            $v = config($paths[0]);
            if (in_array($field, \App\Services\AiConfigOverride::CSV_FIELDS, true) && is_array($v)) {
                $v = implode(', ', $v);
            }
            if (in_array($field, \App\Services\AiConfigOverride::SECRET_FIELDS, true)) {
                $v = $v ? ('••••'.substr((string) $v, -4)) : '';
            }
            if (in_array($field, \App\Services\AiConfigOverride::BOOL_FIELDS, true)) {
                $v = (bool) $v;
            }
            $values[$field] = $v;
            $overridden[$field] = $stored->has("aicfg.$field");
        }

        // Effective fallback chain (custom aicfg.chain applied if set).
        $chain = [];
        foreach ((array) config('ai.chain', []) as $entry) {
            $chain[] = [
                'provider' => $entry['provider'] ?? 'openai',
                'model' => $entry['model'] ?? '',
            ];
        }
        $values['chain'] = $chain;
        $overridden['chain'] = $stored->has('aicfg.chain');

        return response()->json(['success' => true, 'values' => $values, 'overridden' => $overridden]);
    }

    public function updateAiConfig(Request $request)
    {
        if (Auth::user()->role_id !== Role::ADMIN) {
            abort(403, 'Unauthorized action.');
        }

        $saved = [];
        foreach (\App\Services\AiConfigOverride::MAP as $field => $paths) {
            if (! $request->has($field)) {
                continue;
            }
            $value = $request->input($field);
            if (in_array($field, \App\Services\AiConfigOverride::BOOL_FIELDS, true)) {
                $value = $request->boolean($field) ? '1' : '0';
            } else {
                $value = trim((string) $value);
            }

            // Secrets: blank means "keep as is" (the UI shows a mask, not the key).
            if (in_array($field, \App\Services\AiConfigOverride::SECRET_FIELDS, true)) {
                if ($value === '') {
                    continue;
                }
                if ($value === '__clear__') {
                    Setting::where('company_id', 1)->where('key', "aicfg.$field")->delete();
                    $saved[] = $field.' (cleared)';

                    continue;
                }
                if (str_starts_with($value, '••••')) {
                    continue; // the mask itself came back — no change
                }
            }

            if ($value === '' && ! in_array($field, \App\Services\AiConfigOverride::BOOL_FIELDS, true)) {
                // Blank non-secret = revert to .env/config default.
                Setting::where('company_id', 1)->where('key', "aicfg.$field")->delete();
                $saved[] = $field.' (default)';

                continue;
            }

            Setting::updateOrCreate(
                ['company_id' => 1, 'key' => "aicfg.$field"],
                ['value' => $value, 'type' => 'string', 'description' => 'AI configuration override']
            );
            $saved[] = $field;
        }

        // Custom fallback chain: array of {provider: resayil|openai, model?: string}.
        // Empty array (or all rows removed in the UI) = revert to the default chain.
        if ($request->has('chain')) {
            $chainIn = $request->input('chain');
            $chain = [];
            if (is_array($chainIn)) {
                foreach (array_slice($chainIn, 0, 6) as $entry) {
                    $provider = is_array($entry) ? ($entry['provider'] ?? null) : null;
                    if (! in_array($provider, ['resayil', 'openai'], true)) {
                        continue;
                    }
                    $model = is_array($entry) ? trim((string) ($entry['model'] ?? '')) : '';
                    if (strlen($model) > 100) {
                        continue;
                    }
                    $chain[] = $model !== ''
                        ? ['provider' => $provider, 'model' => $model]
                        : ['provider' => $provider];
                }
            }
            if (count($chain) > 0) {
                Setting::updateOrCreate(
                    ['company_id' => 1, 'key' => 'aicfg.chain'],
                    ['value' => json_encode($chain), 'type' => 'string', 'description' => 'AI configuration override']
                );
                $saved[] = 'chain ('.count($chain).' steps)';
            } else {
                Setting::where('company_id', 1)->where('key', 'aicfg.chain')->delete();
                $saved[] = 'chain (default)';
            }
        }

        Cache::forget(\App\Services\AiConfigOverride::CACHE_KEY);
        Log::info('AI configuration updated', ['fields' => $saved, 'user_id' => Auth::id()]);

        return response()->json(['success' => true, 'saved' => $saved]);
    }

    public function aiModels()
    {
        if (Auth::user()->role_id !== Role::ADMIN) {
            abort(403, 'Unauthorized action.');
        }

        \App\Services\AiConfigOverride::apply();
        try {
            $models = Cache::remember('aicfg_gateway_models', 60, function () {
                $url = rtrim((string) config('ai.providers.resayil.url'), '/');
                $key = (string) config('ai.providers.resayil.key');
                $list = \Illuminate\Support\Facades\Http::withToken($key)->withoutVerifying()
                    ->timeout(20)->get($url.'/models')->json();
                $ids = [];
                foreach (($list['data'] ?? (is_array($list) ? $list : [])) as $m) {
                    $id = is_array($m) ? ($m['id'] ?? null) : $m;
                    if ($id) {
                        $ids[] = $id;
                    }
                }
                sort($ids);

                return $ids;
            });

            return response()->json(['success' => true, 'models' => $models]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Model list failed: '.$e->getMessage()], 500);
        }
    }

    public function aiTest(Request $request)
    {
        if (Auth::user()->role_id !== Role::ADMIN) {
            abort(403, 'Unauthorized action.');
        }

        $type = $request->input('type', 'text');
        \App\Services\AiConfigOverride::apply();
        $t0 = microtime(true);
        try {
            if ($type === 'openai') {
                $key = (string) config('ai.providers.openai.key');
                if ($key === '') {
                    return response()->json(['success' => false, 'seconds' => 0, 'message' => 'No OpenAI key configured.']);
                }
                $r = \Illuminate\Support\Facades\Http::withToken($key)->timeout(30)
                    ->post(rtrim((string) config('ai.providers.openai.url'), '/').'/chat/completions', [
                        'model' => (string) config('ai.providers.openai.model'),
                        'messages' => [['role' => 'user', 'content' => 'Say OK']],
                        'max_tokens' => 1,
                    ]);
                $ok = $r->successful();
                $msg = $ok ? 'OpenAI key works (live completion).' : ('OpenAI HTTP '.$r->status().': '
                    .substr((string) ($r->json()['error']['message'] ?? $r->body()), 0, 120));
            } else {
                $url = rtrim((string) config('ai.providers.resayil.url'), '/').'/chat/completions';
                $key = (string) config('ai.providers.resayil.key');
                if ($type === 'vision') {
                    $model = (string) config('ai.providers.resayil.model_passport');
                    $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
                    $messages = [['role' => 'user', 'content' => [
                        ['type' => 'text', 'text' => 'What color is this image? Answer with one word.'],
                        ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,'.$png]],
                    ]]];
                } else {
                    $model = (string) config('ai.providers.resayil.model_text');
                    $messages = [['role' => 'user', 'content' => 'Reply with exactly: OK']];
                }
                $r = \Illuminate\Support\Facades\Http::withToken($key)->withoutVerifying()->timeout(60)
                    ->post($url, ['model' => $model, 'messages' => $messages, 'max_tokens' => 300]);
                $ok = $r->successful() && isset($r->json()['choices'][0]['message']['content']);
                $msg = $ok
                    ? ($model.' answered: '.trim((string) $r->json()['choices'][0]['message']['content']))
                    : ($model.' HTTP '.$r->status().': '.substr($r->body(), 0, 150));
            }

            return response()->json(['success' => $ok, 'seconds' => round(microtime(true) - $t0, 1), 'message' => $msg]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'seconds' => round(microtime(true) - $t0, 1),
                'message' => 'Test failed: '.$e->getMessage()]);
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
            $prefixes = ['notification.unassigned_task', 'notification.autobill', 'notification.invoice_created'];
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

        if (! in_array($user->role_id, [Role::ADMIN, Role::COMPANY])) {
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

        if (! $companyId) {
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
                'notification.invoice_created' => 'Invoice created email notification',
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
        Gate::authorize('viewNotifications', Setting::class);
        $user = Auth::user();
        $companyId = getCompanyId($user);

        if ($companyId === null) {
            return response()->json([
                'success' => false,
                'message' => 'No company selected.',
            ], 400);
        }

        try {
            $agentQuery = Agent::with('branch:id,name')
                ->select('id', 'name', 'email', 'phone_number', 'country_code', 'branch_id', 'type_id');

            if ($user->role_id === Role::AGENT) {
                $agentQuery->where('id', $user->agent?->id);
            } else {
                $agentQuery->whereHas('branch', function ($query) use ($companyId) {
                    $query->where('company_id', $companyId);
                });
            }
            $agents = $agentQuery->get();

            $supportedTypes = [
                AgentNotificationSetting::TYPE_TASK_CLOSE,
                AgentNotificationSetting::TYPE_PAYMENT_LINK_UNINVOICED,
                AgentNotificationSetting::TYPE_INVOICE_CREATED,
            ];

            $settingsQuery = AgentNotificationSetting::where('company_id', $companyId)
                ->whereIn('notification_type', $supportedTypes);

            if ($user->role_id === Role::AGENT) {
                $settingsQuery->where('agent_id', $user->agent?->id);
            }

            // Group by agent_id, then by notification_type:
            //   settings[agent_id][notification_type] = { id, channel, is_active, ... }
            $settings = [];
            foreach ($settingsQuery->get() as $row) {
                $settings[$row->agent_id][$row->notification_type] = $row->toArray();
            }

            return response()->json([
                'success' => true,
                'agents' => $agents,
                'settings' => $settings,
                'channelOptions' => AgentNotificationSetting::getChannelOptions(),
                'typeOptions' => [
                    AgentNotificationSetting::TYPE_TASK_CLOSE => 'Uninvoiced Task Reminder',
                    AgentNotificationSetting::TYPE_PAYMENT_LINK_UNINVOICED => 'Uninvoiced Payment Link Reminder',
                    AgentNotificationSetting::TYPE_INVOICE_CREATED => 'Invoice Notifications',
                ],
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

        if (! Gate::allows('manageNotifications', Setting::class)) {
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

        if ($user->role_id === Role::AGENT && (! $user->agent || $validated['agent_id'] != $user->agent->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 403);
        }

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
                'message' => 'Failed to save setting: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk update agent notification settings.
     */
    public function bulkUpdateAgentNotifications(Request $request)
    {
        $user = Auth::user();

        if (! Gate::allows('manageNotifications', Setting::class)) {
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
                'message' => 'Failed to update settings: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * W4.U (w4-brief.md "W4.U -- UI" §a). Canonical 12-service-type list this tab iterates for
     * the fee schedule, the commissionable-fee-types multi-select, and the per-service posting
     * basis select — reuses `config('accounting.posting_basis.default_by_service_type')`'s own
     * key set (that config block is this codebase's one existing source of truth for "the 12
     * service types", per config/accounting.php's own docblock) rather than hand-typing a second
     * list that could drift from it.
     *
     * @return string[]
     */
    private function accountingServiceTypes(): array
    {
        return array_keys(config('accounting.posting_basis.default_by_service_type', []));
    }

    /**
     * W4.U §a. Read-only snapshot of every posting-engine company option this wave introduced, so
     * the Accounting settings tab has something to render. Company-scoped `Setting` rows only —
     * never `accounts.actual_balance`/`journal_entries.balance` (this controller never reads the
     * ledger at all).
     */
    public function getAccountingSettings(Request $request)
    {
        Gate::authorize('viewAccountingSettings', Setting::class);

        $user = Auth::user();
        $companyId = getCompanyId($user);

        if ($companyId === null) {
            return response()->json(['success' => false, 'message' => 'No company selected.'], 400);
        }

        $serviceTypes = $this->accountingServiceTypes();

        $feeSchedule = [];
        foreach ($serviceTypes as $type) {
            $feeSchedule[$type] = [
                'amount' => Setting::getByKey($companyId, "accounting.refund.fee_schedule.{$type}.amount", 0),
                'percent' => Setting::getByKey($companyId, "accounting.refund.fee_schedule.{$type}.percent", 0),
                'override' => Setting::getByKey($companyId, "accounting.refund.fee_schedule.{$type}.override", 'needs_approval'),
            ];
        }

        $postingBasis = [];
        $defaults = config('accounting.posting_basis.default_by_service_type', []);
        foreach ($serviceTypes as $type) {
            $postingBasis[$type] = Setting::getByKey($companyId, "accounting.posting_basis.{$type}", $defaults[$type] ?? 'agent');
        }

        // W4.U verify-fix (MEDIUM): 'adm' and 'gateway_fee' removed from the bearer matrix — both
        // were persisted here but read by NO posting/business logic anywhere in the codebase
        // ('adm' has no consumer or hook at all — no ADM document type exists yet to apply a
        // bearer decision to; 'gateway_fee' duplicated the already-shipped, unrelated
        // Charge::paid_by mechanism from W4.D). 'commission_clawback' is kept: it has a real,
        // documented (if currently gated-off) consumer,
        // RefundPostingService::postClawbackBearerRecoveryHook(). See
        // resources/views/settings/partial/accounting.blade.php's matching comment.
        $bearerKinds = ['commission_clawback'];
        $bearerDefaults = ['commission_clawback' => 'company'];
        $bearer = [];
        foreach ($bearerKinds as $kind) {
            $bearer[$kind] = [
                'value' => Setting::getByKey($companyId, "bearer.{$kind}", $bearerDefaults[$kind]),
                'split_percent' => Setting::getByKey($companyId, "bearer.{$kind}.split_percent", 50),
            ];
        }

        return response()->json([
            'success' => true,
            'serviceTypes' => $serviceTypes,
            'settings' => [
                'invoice_overpay_cancel_policy' => Setting::getByKey($companyId, 'accounting.refund.invoice_overpay_cancel_policy', 'credit'),
                'unclaimed_writeback_months' => (int) Setting::getByKey($companyId, 'accounting.refund.unclaimed_writeback_months', 12),
                'commissionable_fee_types' => json_decode((string) Setting::getByKey($companyId, 'accounting.commissionable_fee_types', '[]'), true) ?: [],
                // W6.S item (6) (w6-brief.md "Options registered" -- "bulk_void_mode,
                // commissionable_fee_types registered"). No route consumes bulk_void_mode yet
                // (W6.B's own bulk-void endpoint) -- registered now via
                // TaskStatusService::bulkVoidMode() and this same settings form so the option is
                // not dead config once that route lands.
                'bulk_void_mode' => Setting::getByKey($companyId, 'accounting.bulk_void_mode', 'atomic'),
                // W6.S "Hold/confirmed follow-up lifecycle" (owner addition 2026-08-28) options --
                // consumed today by TaskStatusService::expire()/holdAutoExpire()/
                // holdExpireGraceHours().
                'hold_auto_expire' => filter_var(Setting::getByKey($companyId, 'accounting.hold_auto_expire', 'true'), FILTER_VALIDATE_BOOLEAN),
                'hold_expire_grace_hours' => (int) Setting::getByKey($companyId, 'accounting.hold_expire_grace_hours', 0),
                // W6.U "Reminders" (owner addition, 2026-08-28) -- consumed by
                // TaskStatusService::holdReminderOffsetsHours()/holdClientNudge() and
                // reminder:generate-deadlines.
                'hold_reminder_offsets_hours' => (string) Setting::getByKey($companyId, 'accounting.hold_reminder_offsets_hours', '24,2'),
                'hold_client_nudge' => filter_var(Setting::getByKey($companyId, 'accounting.hold_client_nudge', 'false'), FILTER_VALIDATE_BOOLEAN),
                'refund_send_on_post' => filter_var(Setting::getByKey($companyId, 'accounting.refund.refund_send_on_post', 'true'), FILTER_VALIDATE_BOOLEAN),
                'agent_unearn_notice' => filter_var(Setting::getByKey($companyId, 'accounting.refund.agent_unearn_notice', 'true'), FILTER_VALIDATE_BOOLEAN),
                'fee_schedule' => $feeSchedule,
                'posting_basis' => $postingBasis,
                'bearer' => $bearer,
                // W5.U (w5-brief.md §W5.U "Settings additions ... in the same accounting settings
                // tab from W4.U"). Read through the SAME `App\Services\Accounting\VoucherOptions`
                // keys `ReceiptVoucherController`/`BankPaymentController` actually resolve at post
                // time (VoucherOptions::APPROVAL_THRESHOLD_KEY / ::PV_ALLOW_OVERDRAFT_KEY) — unlike
                // the refund-lane pair above (a deliberately SEPARATE key namespace, see
                // config('accounting.vouchers')'s own docblock), these two have no such split: this
                // endpoint IS the one and only writer VoucherOptions ever reads back from, so using
                // its own constants here is what keeps the settings form from drifting out of sync
                // with what a real vouchers post actually honours.
                'voucher_approval_threshold' => VoucherOptions::approvalThreshold($companyId),
                'pv_allow_overdraft' => VoucherOptions::pvAllowOverdraft($companyId),
                // P2.5.H (p2_5-brief.md §P2.5.H). Read through the SAME
                // `App\Services\Accounting\StatementOptions` key StatementController actually
                // resolves at request time, same convention the vouchers pair above already
                // follows for its own resolver class.
                'statement_mode' => \App\Services\Accounting\StatementOptions::mode($companyId),
                // P2.5.I (p2_5-brief.md §P2.5.I). Read through the SAME
                // `App\Services\Reminders\ReminderOptions` keys the generators and the
                // commission_unearned listener resolve at run time -- same "this endpoint is the
                // one and only writer" convention statement_mode/vouchers above already follow.
                'reminders' => $this->reminderSettingsPayload($companyId),
            ],
        ]);
    }

    /**
     * P2.5.I (p2_5-brief.md §P2.5.I). {@see self::REMINDER_SETTING_KINDS} is the same list
     * `resources/views/settings/partial/accounting.blade.php`'s `reminderKinds` array must be
     * kept in sync with (server-side truth; the blade comment points back here).
     */
    private const REMINDER_SETTING_KINDS = ['overdue_invoice', 'statement_balance', 'ticketing_deadline', 'commission_unearned', 'payment_link_uninvoiced'];

    private function reminderSettingsPayload(int $companyId): array
    {
        $enabled = [];
        $channel = [];
        foreach (self::REMINDER_SETTING_KINDS as $kind) {
            $enabled[$kind] = ReminderOptions::enabled($companyId, $kind);
            $channel[$kind] = ReminderOptions::channel($companyId, $kind);
        }

        $quiet = ReminderOptions::quietHours($companyId);

        return [
            'enabled' => $enabled,
            'channel' => $channel,
            'overdue_invoice_offsets_days' => implode(',', ReminderOptions::overdueInvoiceOffsetsDays($companyId)),
            'daily_run_time' => ReminderOptions::dailyRunTime($companyId),
            'quiet_start' => $quiet['start'] ?? '',
            'quiet_end' => $quiet['end'] ?? '',
        ];
    }

    /**
     * W4.U §a. Persists every field the Accounting tab's form submits, one `Setting` row per key
     * (`updateOrCreate`, same convention every other tab in this controller already uses).
     * Deliberately does NOT post anything to the ledger — this only ever writes company-scoped
     * `Setting` rows; the NEXT refund posted through {@see \App\Services\Accounting\RefundPostingService}
     * is what reads them back.
     */
    public function storeAccountingSettings(Request $request)
    {
        Gate::authorize('manageAccountingSettings', Setting::class);

        $user = Auth::user();
        $companyId = getCompanyId($user);

        if ($companyId === null) {
            return response()->json(['success' => false, 'message' => 'No company selected.'], 400);
        }

        $serviceTypes = $this->accountingServiceTypes();

        $validated = $request->validate([
            'invoice_overpay_cancel_policy' => ['required', 'in:credit,refund_out,manual'],
            'unclaimed_writeback_months' => ['required', 'integer', 'min:1', 'max:120'],
            'commissionable_fee_types' => ['nullable', 'array'],
            'commissionable_fee_types.*' => ['string', 'in:' . implode(',', $serviceTypes)],
            'refund_send_on_post' => ['required', 'boolean'],
            'agent_unearn_notice' => ['required', 'boolean'],
            'fee_schedule' => ['nullable', 'array'],
            'fee_schedule.*.amount' => ['nullable', 'numeric', 'min:0'],
            'fee_schedule.*.percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fee_schedule.*.override' => ['nullable', 'in:free,needs_approval'],
            'posting_basis' => ['nullable', 'array'],
            'posting_basis.*' => ['in:agent,principal'],
            'bearer' => ['nullable', 'array'],
            'bearer.*.value' => ['in:company,agent,split'],
            'bearer.*.split_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            // W5.U: nullable means "always require a manual approve() step" — VoucherOptions'
            // own documented meaning for an absent/NULL threshold; a bare empty string from the
            // number input clears the Setting row below rather than coercing to 0 (a 0 threshold
            // would auto-approve every zero-amount voucher only, which is not what an accountant
            // clearing this field intends).
            'voucher_approval_threshold' => ['nullable', 'numeric', 'min:0'],
            // 'nullable' (not 'required'): this endpoint is one shared form for the WHOLE
            // Accounting settings tab, and pre-existing callers (W4.U's own test suite, predating
            // this sub-wave) submit payloads that never heard of this field. Matches the tolerance
            // every other section of this same endpoint already gives an absent/partial payload
            // (fee_schedule/posting_basis/bearer are all 'nullable' too) -- a caller who hasn't
            // touched the vouchers section yet must not be forced to also resubmit it.
            'pv_allow_overdraft' => ['nullable', 'boolean'],
            // P2.5.H — one of App\Services\Accounting\StatementOptions::MODES. 'nullable' for the
            // same reason every other field added to this one shared form is.
            'statement_mode' => ['nullable', 'in:' . implode(',', \App\Services\Accounting\StatementOptions::MODES)],
            // W6.S item (6): registered so the option is not dead config once W6.B's bulk-void
            // route lands. 'nullable' for the same reason every other field added to this one
            // shared form is -- a pre-existing caller must not be forced to resubmit a field it
            // never heard of.
            'bulk_void_mode' => ['nullable', 'in:atomic,per_task_report'],
            'hold_auto_expire' => ['nullable', 'boolean'],
            'hold_expire_grace_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            // W6.U "Reminders" -- comma-separated positive hour offsets, e.g. "24,2".
            'hold_reminder_offsets_hours' => ['nullable', 'string', 'regex:/^\d+(,\d+)*$/'],
            'hold_client_nudge' => ['nullable', 'boolean'],
            // P2.5.I (p2_5-brief.md §P2.5.I). 'nullable' for the whole block, same
            // partial-payload tolerance every other section of this shared form gives.
            'reminders' => ['nullable', 'array'],
            'reminders.enabled' => ['nullable', 'array'],
            'reminders.enabled.*' => ['boolean'],
            'reminders.channel' => ['nullable', 'array'],
            'reminders.channel.*' => ['in:' . implode(',', ReminderOptions::CHANNELS)],
            'reminders.overdue_invoice_offsets_days' => ['nullable', 'string', 'regex:/^\d+(,\s*\d+)*$/'],
            'reminders.daily_run_time' => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'reminders.quiet_start' => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'reminders.quiet_end' => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
        ]);

        // P2.5.F writer (b): "company option changes" named explicitly. One consolidated row per
        // save covering every Setting key this form touches, rather than one row per key — a whole
        //-form snapshot before/after is what an accountant reviewing "what changed on this save"
        // actually wants, and matches how the form itself submits (one request, many fields).
        $settingsBefore = Setting::where('company_id', $companyId)->pluck('value', 'key')->all();

        try {
            Setting::updateOrCreate(
                ['key' => 'accounting.refund.invoice_overpay_cancel_policy', 'company_id' => $companyId],
                ['value' => $validated['invoice_overpay_cancel_policy'], 'type' => 'string', 'description' => 'Refund disposition default for overpaid/credited amounts']
            );
            Setting::updateOrCreate(
                ['key' => 'accounting.refund.unclaimed_writeback_months', 'company_id' => $companyId],
                ['value' => $validated['unclaimed_writeback_months'], 'type' => 'integer', 'description' => 'Months before unclaimed client credit/agent payable writes back to 4210']
            );
            Setting::updateOrCreate(
                ['key' => 'accounting.commissionable_fee_types', 'company_id' => $companyId],
                ['value' => json_encode(array_values($validated['commissionable_fee_types'] ?? [])), 'type' => 'json', 'description' => 'Service types whose refund/cancellation fee is commissionable']
            );
            Setting::updateOrCreate(
                ['key' => 'accounting.refund.refund_send_on_post', 'company_id' => $companyId],
                ['value' => $validated['refund_send_on_post'], 'type' => 'boolean', 'description' => 'Send client CRN + statement notice when a refund posts']
            );
            Setting::updateOrCreate(
                ['key' => 'accounting.refund.agent_unearn_notice', 'company_id' => $companyId],
                ['value' => $validated['agent_unearn_notice'], 'type' => 'boolean', 'description' => 'Send agent commission-unearned notice when a refund posts']
            );
            // W5.U: written under VoucherOptions' OWN key constants (not a parallel
            // 'accounting.refund.*' namespace) so the very next voucher post reads back exactly
            // what this form just saved — see VoucherOptions::approvalThreshold()/pvAllowOverdraft()
            // for the read side.
            // Setting::getValueAttribute()'s 'string' branch unconditionally does `(string)
            // $value` -- (string) null is '' (empty string), NEVER null, so a row that EXISTS
            // with a raw-NULL `value` column can never round-trip back into
            // VoucherOptions::approvalThreshold()'s own `$value === null` check (it would read
            // back '', not null, and cast to 0.0 -- silently turning "always require manual
            // approval" into "auto-approve everything at KWD 0.000 or below", the opposite of
            // this field's documented meaning). `Setting::getByKey()`'s OTHER branch -- no row at
            // all -- returns the given $default (null) untouched, with no accessor involved. A
            // cleared field therefore DELETES the row rather than writing a NULL value, so a
            // later read is a plain "no row" rather than a value the accessor would mangle.
            $thresholdProvided = array_key_exists('voucher_approval_threshold', $validated) && $validated['voucher_approval_threshold'] !== null;
            if ($thresholdProvided) {
                Setting::updateOrCreate(
                    ['key' => VoucherOptions::APPROVAL_THRESHOLD_KEY, 'company_id' => $companyId],
                    [
                        'value' => (string) $validated['voucher_approval_threshold'],
                        'type' => 'string',
                        'description' => 'Amount at/under which a posted RV/PV auto-approves; no row always requires manual approve()',
                    ]
                );
            } else {
                Setting::where('key', VoucherOptions::APPROVAL_THRESHOLD_KEY)->where('company_id', $companyId)->delete();
            }
            // Absent (a caller that never touched this field, e.g. a pre-existing partial-payload
            // submitter) leaves the setting at its config default (false) rather than forcing
            // every legacy caller of this shared endpoint to also learn this new field.
            if (array_key_exists('pv_allow_overdraft', $validated)) {
                Setting::updateOrCreate(
                    ['key' => VoucherOptions::PV_ALLOW_OVERDRAFT_KEY, 'company_id' => $companyId],
                    ['value' => (bool) $validated['pv_allow_overdraft'], 'type' => 'boolean', 'description' => 'Allow a Payment Voucher to take a bank leaf negative']
                );
            }
            // P2.5.H: only write when the caller actually submitted it, same convention as
            // pv_allow_overdraft above.
            if (array_key_exists('statement_mode', $validated) && $validated['statement_mode'] !== null) {
                Setting::updateOrCreate(
                    ['key' => \App\Services\Accounting\StatementOptions::MODE_KEY, 'company_id' => $companyId],
                    ['value' => $validated['statement_mode'], 'type' => 'string', 'description' => 'Client/supplier/agent statement default: open_items or full_activity']
                );
            }

            // W6.S item (6): 'nullable' fields on this shared form -- only write when the caller
            // actually submitted them, same convention as pv_allow_overdraft above.
            if (array_key_exists('bulk_void_mode', $validated) && $validated['bulk_void_mode'] !== null) {
                Setting::updateOrCreate(
                    ['key' => 'accounting.bulk_void_mode', 'company_id' => $companyId],
                    ['value' => $validated['bulk_void_mode'], 'type' => 'string', 'description' => 'Bulk-void transaction mode: atomic (all-or-nothing) or per_task_report']
                );
            }
            if (array_key_exists('hold_auto_expire', $validated) && $validated['hold_auto_expire'] !== null) {
                Setting::updateOrCreate(
                    ['key' => 'accounting.hold_auto_expire', 'company_id' => $companyId],
                    ['value' => (bool) $validated['hold_auto_expire'], 'type' => 'boolean', 'description' => 'Automatically expire on-hold/confirmed tasks past their deadline that were never issued']
                );
            }
            if (array_key_exists('hold_expire_grace_hours', $validated) && $validated['hold_expire_grace_hours'] !== null) {
                Setting::updateOrCreate(
                    ['key' => 'accounting.hold_expire_grace_hours', 'company_id' => $companyId],
                    ['value' => (int) $validated['hold_expire_grace_hours'], 'type' => 'integer', 'description' => 'Grace period (hours) added to a task deadline before TaskStatusService::expire() may flip it']
                );
            }
            // W6.U "Reminders" (owner addition, 2026-08-28) -- consumed by
            // TaskStatusService::holdReminderOffsetsHours()/holdClientNudge() and
            // reminder:generate-deadlines.
            if (array_key_exists('hold_reminder_offsets_hours', $validated) && $validated['hold_reminder_offsets_hours'] !== null) {
                Setting::updateOrCreate(
                    ['key' => 'accounting.hold_reminder_offsets_hours', 'company_id' => $companyId],
                    ['value' => $validated['hold_reminder_offsets_hours'], 'type' => 'string', 'description' => 'Comma-separated hours-before-deadline for ticketing-deadline reminders']
                );
            }
            if (array_key_exists('hold_client_nudge', $validated) && $validated['hold_client_nudge'] !== null) {
                Setting::updateOrCreate(
                    ['key' => 'accounting.hold_client_nudge', 'company_id' => $companyId],
                    ['value' => (bool) $validated['hold_client_nudge'], 'type' => 'boolean', 'description' => 'Also send the client a nudge message when a ticketing-deadline reminder fires']
                );
            }

            // P2.5.I (p2_5-brief.md §P2.5.I) -- written under App\Services\Reminders\
            // ReminderOptions' own key namespace so the very next reminder:generate/process:reminder
            // run reads back exactly what this form just saved, same convention VoucherOptions/
            // StatementOptions above already follow for their own resolver classes.
            $reminderSettings = $validated['reminders'] ?? [];
            foreach (self::REMINDER_SETTING_KINDS as $kind) {
                if (array_key_exists($kind, $reminderSettings['enabled'] ?? [])) {
                    Setting::updateOrCreate(
                        ['key' => "accounting.reminders.{$kind}.enabled", 'company_id' => $companyId],
                        ['value' => (bool) $reminderSettings['enabled'][$kind], 'type' => 'boolean', 'description' => "Whether the {$kind} reminder is generated"]
                    );
                }
                if (array_key_exists($kind, $reminderSettings['channel'] ?? [])) {
                    Setting::updateOrCreate(
                        ['key' => "accounting.reminders.{$kind}.channel", 'company_id' => $companyId],
                        ['value' => $reminderSettings['channel'][$kind], 'type' => 'string', 'description' => "Delivery channel for the {$kind} reminder"]
                    );
                }
            }
            if (! empty($reminderSettings['overdue_invoice_offsets_days'])) {
                Setting::updateOrCreate(
                    ['key' => 'accounting.reminders.overdue_invoice.offsets_days', 'company_id' => $companyId],
                    ['value' => $reminderSettings['overdue_invoice_offsets_days'], 'type' => 'string', 'description' => 'Comma-separated days-past-due at which an overdue-invoice reminder re-fires']
                );
            }
            if (! empty($reminderSettings['daily_run_time'])) {
                Setting::updateOrCreate(
                    ['key' => 'accounting.reminders.daily_run_time', 'company_id' => $companyId],
                    ['value' => $reminderSettings['daily_run_time'], 'type' => 'string', 'description' => 'Time of day the daily-cadence reminder generators run']
                );
            }
            // Quiet hours: both filled -> "HH:MM-HH:MM"; either blank -> delete the row so
            // ReminderOptions::quietHours() falls back to its own null (disabled) default.
            $quietStart = $reminderSettings['quiet_start'] ?? '';
            $quietEnd = $reminderSettings['quiet_end'] ?? '';
            if ($quietStart !== '' && $quietEnd !== '') {
                Setting::updateOrCreate(
                    ['key' => 'accounting.reminders.quiet_hours', 'company_id' => $companyId],
                    ['value' => "{$quietStart}-{$quietEnd}", 'type' => 'string', 'description' => 'Window inside which no reminder is scheduled; a would-be scheduled reminder shifts to the end of it']
                );
            } elseif (array_key_exists('reminders', $validated)) {
                Setting::where('company_id', $companyId)->where('key', 'accounting.reminders.quiet_hours')->delete();
            }

            foreach ($serviceTypes as $type) {
                $row = $validated['fee_schedule'][$type] ?? null;
                if ($row === null) {
                    continue;
                }
                Setting::updateOrCreate(
                    ['key' => "accounting.refund.fee_schedule.{$type}.amount", 'company_id' => $companyId],
                    ['value' => $row['amount'] ?? 0, 'type' => 'string', 'description' => "Refund fee amount for {$type}"]
                );
                Setting::updateOrCreate(
                    ['key' => "accounting.refund.fee_schedule.{$type}.percent", 'company_id' => $companyId],
                    ['value' => $row['percent'] ?? 0, 'type' => 'string', 'description' => "Refund fee percent for {$type}"]
                );
                Setting::updateOrCreate(
                    ['key' => "accounting.refund.fee_schedule.{$type}.override", 'company_id' => $companyId],
                    ['value' => $row['override'] ?? 'needs_approval', 'type' => 'string', 'description' => "Refund fee override policy for {$type}"]
                );
            }

            foreach ($serviceTypes as $type) {
                if (! array_key_exists($type, $validated['posting_basis'] ?? [])) {
                    continue;
                }
                Setting::updateOrCreate(
                    ['key' => "accounting.posting_basis.{$type}", 'company_id' => $companyId],
                    ['value' => $validated['posting_basis'][$type], 'type' => 'string', 'description' => "Posting basis override for {$type}"]
                );
            }

            // W4.U verify-fix (MEDIUM): 'adm'/'gateway_fee' removed — see getAccountingSettings()'s
            // matching comment above.
            foreach (['commission_clawback'] as $kind) {
                $row = $validated['bearer'][$kind] ?? null;
                if ($row === null) {
                    continue;
                }
                Setting::updateOrCreate(
                    ['key' => "bearer.{$kind}", 'company_id' => $companyId],
                    ['value' => $row['value'], 'type' => 'string', 'description' => "Default bearer for {$kind}"]
                );
                Setting::updateOrCreate(
                    ['key' => "bearer.{$kind}.split_percent", 'company_id' => $companyId],
                    ['value' => $row['split_percent'] ?? 50, 'type' => 'string', 'description' => "Split percent (agent share) for {$kind}"]
                );
            }

            Log::info('Accounting settings saved', ['company_id' => $companyId, 'user_id' => $user->id]);

            $settingsAfter = Setting::where('company_id', $companyId)->pluck('value', 'key')->all();
            $changedKeys = array_keys(array_diff_assoc($settingsAfter, $settingsBefore)
                + array_diff_key($settingsAfter, $settingsBefore));

            \App\Services\Accounting\AccountingLog::write(
                action: 'option_change',
                companyId: $companyId,
                subjectType: 'company_setting',
                subjectId: $companyId,
                before: array_intersect_key($settingsBefore, array_flip($changedKeys)),
                after: array_intersect_key($settingsAfter, array_flip($changedKeys)),
                actorId: $user->id,
            );

            return response()->json(['success' => true, 'message' => 'Accounting settings saved successfully.']);
        } catch (\Exception $e) {
            Log::error('Failed to save accounting settings', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Failed to save accounting settings: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete agent notification setting (reset to default).
     */
    public function deleteAgentNotification(int $id)
    {
        $user = Auth::user();

        if (! Gate::allows('manageNotifications', Setting::class)) {
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

            if ($user->role_id === Role::AGENT && (! $user->agent || $setting->agent_id != $user->agent->id)) {
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
