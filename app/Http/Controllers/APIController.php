<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Client;
use App\Models\Agent;
use App\Models\Supplier;
use App\Models\Country;
use App\Models\Hotel;

class APIController extends Controller
{
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
        $request->validate([
            'client_name' => 'nullable|string',
        ]);

        $query = Client::query();

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
        $request->validate([
            'agent_name' => 'nullable|string',
        ]);

        $query = Agent::query();

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
        $request->validate([
            'company_name' => 'nullable|string',
        ]);

        $query = Company::query();

        if ($request->filled('company_name')) {
            $searchName = $request->company_name;
            
            $query->where(function ($q) use ($searchName) {
                $q->where('name', 'LIKE', "%{$searchName}%")->get();
            });
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
        $request->validate([
            'supplier_name' => 'nullable|string',
        ]);

        $query = Supplier::query();

        if ($request->filled('supplier_name')) {
            $searchName = $request->supplier_name;
            
            $query->where(function ($q) use ($searchName) {
                $q->where('name', 'LIKE', "%{$searchName}%")->get();
            });
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