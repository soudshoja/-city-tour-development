<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Company;
use App\Models\Client;
use App\Models\Agent;
use App\Models\Supplier;
use App\Models\Country;
use App\Models\Hotel;
use App\Models\WebhookClient;

class APIController extends Controller
{
    /**
     * getClient/getAgent/getCompany/getSupplier are gated by the `verify.webhook.signature`
     * middleware (see routes/api.php), which -- per its own docblock -- only verifies a signature
     * IF one was presented; it never REQUIRES one. This turns that into a hard requirement for
     * these endpoints, mirroring App\Http\Webhooks\TaskWebhook::webhook()'s own top-of-method
     * check. Returns the verified WebhookClient (which carries the caller's company_id for
     * scoping) or null if no signature was verified.
     */
    private function requireWebhookClient(Request $request): ?WebhookClient
    {
        $client = $request->attributes->get('webhook_client');

        return $client instanceof WebhookClient ? $client : null;
    }

    private function webhookAuthRequiredResponse(): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => 'A verified webhook signature is required for this endpoint.',
        ], 401);
    }

    public function getTaskStructure(Request $request)
    {
        $request->validate ([
            'task_type' => 'required|string',
        ]);

        $task = [
            'client_id',
            'agent_id',
            'company_id',
            'supplier_id',
            'type',
            'status',
            'supplier_status',
            'client_name',
            'client_ref',
            'passenger_name',
            'reference',
            'gds_reference',
            'airline_reference',
            'created_by',
            'issued_by',
            'iata_number',
            'issued_date',
            'expiry_date',
            'price',
            'exchange_currency',
            'exchange_rate',
            'original_price',
            'original_currency',
            'tax',
            'original_tax',
            'surcharge',
            'original_surcharge',
            'penalty_fee',
            'supplier_surcharge',
            'taxes_record',
            'total',
            'original_total',
            'cancellation_policy',
            'cancellation_deadline',
            'supplier_pay_date',
            'additional_info',
            'ticket_number',
            'file_name',
            'venue',
            'refund_charge',
            'refund_date',
        ];

        $details = match ($request->task_type) {
            'flight' => array_merge($task, [
                'farebase',
                'departure_time',
                'country_id_from',
                'airport_from',
                'terminal_from',
                'arrival_time',
                'duration_time',
                'country_id_to',
                'airport_to',
                'terminal_to',
                'airline_id',
                'flight_number',
                'ticket_number',   
                'class_type',
                'baggage_allowed',
                'equipment',
                'flight_meal',
                'seat_no',
            ]),

            'hotel' => array_merge($task, [
                'hotel_id',
                'booking_time',
                'check_in',
                'check_out',
                'room_reference',
                'room_number',
                'room_type',
                'room_amount',
                'room_details',
                'room_promotion',
                'rate',
                'meal_type',
                'supplements',
            ]),
            
            'visa' => array_merge($task, [
                'visa_type',
                'application_number',
                'expire_date',
                'number_of_entries',
                'stay_duration',
                'issuing_country',
            ]),

            'insurance' => array_merge($task, [
                'date',
                'paid_leaves',
                'document_number',
                'insurance_type',
                'destination_country',
                'plan_type',
                'duration',
                'package',
            ]),
            
            default => []
        };
        
        if (empty($details)) {
            return response()->json([
                'status' => 'error',
                'message' => "Task type '{$request->task_type}' is not yet supported. Please contact support team for further enquiry",
            ], 422);
        }

        return response()->json([
            'task' => $task,
            "task_{$request->task_type}_details" => $details,
        ]);
    }

    public function getClient(Request $request)
    {
        $webhookClient = $this->requireWebhookClient($request);
        if (! $webhookClient) {
            return $this->webhookAuthRequiredResponse();
        }

        $request->validate([
            'client_name' => 'nullable|string',
        ]);

        // Column allowlist -- passport_no, old_passport_no, civil_no and date_of_birth (all
        // present on Client's own $fillable) are deliberately excluded. This is a pre-flight
        // ID/name-lookup helper for task-webhook validation, not a client-record export; none of
        // that identity data is needed to confirm a client_id/name exists before posting a task.
        $query = Client::query()
            ->select(['id', 'name', 'first_name', 'middle_name', 'last_name', 'email', 'phone', 'country_code', 'agent_id', 'company_id', 'status'])
            ->where('company_id', $webhookClient->company_id);

        // If search term provided, filter by name
        if ($request->filled('client_name')) {
            $searchName = $request->client_name;

            $query->where(function ($q) use ($searchName) {
                $q->where('name', 'LIKE', "%{$searchName}%")
                    ->orWhere('first_name', 'LIKE', "%{$searchName}%")
                    ->orWhere('middle_name', 'LIKE', "%{$searchName}%")
                    ->orWhere('last_name', 'LIKE', "%{$searchName}%")
                    ->orWhereRaw("CONCAT_WS(' ', first_name, last_name) LIKE ?", ["%{$searchName}%"])
                    ->orWhereRaw("CONCAT_WS(' ', first_name, middle_name, last_name) LIKE ?", ["%{$searchName}%"]);
            });
        }

        $clients = $query->get();

        if ($clients->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => $request->filled('client_name') 
                    ? "No client found with name '{$request->client_name}'"
                    : "No clients found",
            ], 422);
        }

        return response()->json([
            'clients' => $clients,
        ]);
    }

    public function getAgent(Request $request)
    {
        $webhookClient = $this->requireWebhookClient($request);
        if (! $webhookClient) {
            return $this->webhookAuthRequiredResponse();
        }

        $request->validate([
            'agent_name' => 'nullable|string',
        ]);

        // Column allowlist -- commission, salary and target (agent compensation) are deliberately
        // excluded; not needed to validate an agent_id/name before posting a task.
        // Agent has no company_id of its own; scoped via branch -> company_id.
        $query = Agent::query()
            ->select(['id', 'name', 'email', 'phone_number', 'country_code', 'branch_id', 'type_id'])
            ->whereHas('branch', fn ($q) => $q->where('company_id', $webhookClient->company_id));

        if ($request->filled('agent_name')) {
            $searchName = $request->agent_name;

            $query->where(function ($q) use ($searchName) {
                $q->where('name', 'LIKE', "%{$searchName}%");
            });
        }

        $agents = $query->get();

        if ($agents->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => "No agent found with name '{$request->agent_name}'",
            ], 422);
        }

        return response()->json([
            'agents' => $agents,    
        ]);
    }

    public function getCompany(Request $request)
    {
        $webhookClient = $this->requireWebhookClient($request);
        if (! $webhookClient) {
            return $this->webhookAuthRequiredResponse();
        }

        $request->validate([
            'company_name' => 'nullable|string',
        ]);

        // A webhook client belongs to exactly one company (WebhookClient::company_id) -- it may
        // only ever see that one company's own record, never another tenant's. Column allowlist --
        // iata_client_id/iata_client_secret (IATA API credentials), gds_office_id and user_id
        // (internal owner reference) are deliberately excluded.
        $query = Company::query()
            ->select(['id', 'name', 'code', 'email', 'phone', 'address', 'iata_code', 'country_id', 'status'])
            ->where('id', $webhookClient->company_id);

        if ($request->filled('company_name')) {
            $searchName = $request->company_name;

            // NOTE: pre-existing bug fixed in passing -- this used to call ->get() *inside* the
            // where(Closure) callback, executing (and discarding the result of) an extra,
            // unconstrained query on every request. Replaced with a plain ->where().
            $query->where('name', 'LIKE', "%{$searchName}%");
        }

        $companies = $query->get();

        if ($companies->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => "No company found with name '{$request->company_name}'",
            ], 422);
        }

        return response()->json([
            'companies' => $companies,
        ]);
    }

    public function getSupplier(Request $request)
    {
        $webhookClient = $this->requireWebhookClient($request);
        if (! $webhookClient) {
            return $this->webhookAuthRequiredResponse();
        }

        $request->validate([
            'supplier_name' => 'nullable|string',
        ]);

        // Suppliers aren't directly company-scoped -- they're shared platform-wide and linked to
        // companies via the supplier_companies pivot -- so this uses the pre-existing
        // Supplier::scopeActiveForCompany() (same scope InvoiceController/TaskWebhook use
        // elsewhere) rather than inventing a new query. Column allowlist -- payment_terms and
        // auth_type (commercial/internal) are deliberately excluded.
        $query = Supplier::query()
            ->select(['id', 'name', 'email', 'phone', 'address', 'city', 'state', 'country_id', 'website', 'contact_person', 'is_online', 'has_hotel', 'has_flight', 'has_visa', 'has_insurance', 'has_tour', 'has_cruise', 'has_car', 'has_rail', 'has_esim', 'has_event', 'has_lounge', 'has_ferry'])
            ->activeForCompany($webhookClient->company_id);

        if ($request->filled('supplier_name')) {
            $searchName = $request->supplier_name;

            // NOTE: same pre-existing get()-inside-where(Closure) bug as getCompany(), fixed the
            // same way.
            $query->where('name', 'LIKE', "%{$searchName}%");
        }

        $suppliers = $query->get();

        if ($suppliers->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => "No supplier found with name '{$request->supplier_name}'",
            ], 422);
        }

        return response()->json([
            'suppliers' => $suppliers,
        ]);
    }

    public function getCountry(Request $request)
    {
        $request->validate([
            'country_name' => 'nullable|string',
        ]);

        $countries = Country::where('name', 'LIKE', "%{$request->country_name}%")->get();

        if ($countries->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => "No country found with name '{$request->country_name}'",
            ], 422);
        }

        return response()->json([
            'countries' => $countries,
        ]);
    }

    public function getHotel(Request $request)
    {
        $request->validate([
            'hotel_name' => 'nullable|string',
        ]);

        $hotels = Hotel::where('name', 'LIKE', "%{$request->hotel_name}%")->get();

        if ($hotels->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => "No hotel found with name '{$request->hotel_name}'",
            ], 422);
        }

        return response()->json([
            'hotels' => $hotels,
        ]);
    }
}