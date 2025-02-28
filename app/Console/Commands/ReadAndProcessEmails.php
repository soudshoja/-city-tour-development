<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Webklex\IMAP\Facades\Client as ImapClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\TaskEmail;
use App\Models\TaskHotelDetailEmail;
use App\Models\Supplier;
use App\Models\Company;
use App\Models\Agent;
use App\Models\Client;
use App\Services\OpenAIServiceEmail;

class ReadAndProcessEmails extends Command
{

    protected $signature = 'emails:process';
    protected $description = 'Read emails, process using OpenAI, and insert into the task_emails table';

    protected $openAIService;

    public function __construct(OpenAIServiceEmail $openAIService)
    {
        parent::__construct();
        $this->openAIService = $openAIService;
    }



    public function handle()
    {
        $client = ImapClient::account('default'); 
        $client->connect();

        // Gmail labels to read emails from
        $labels = ['magic', 'tbo', 'webbeds'];

        foreach ($labels as $label) {
            $this->info("\n📂 Processing emails from: " . strtoupper($label));
            \Log::info("label. $label");
            try {
                $folder = $client->getFolder($label);
                $messages = $folder->query()->all()->limit(5)->get();

                foreach ($messages as $message) {
                    $emailId = $message->getMessageId(); // Unique email identifier
                    $emailText = $message->getTextBody();

                    // ✅ Check if this email has already been processed
                    if (DB::table('task_emails')->where('email_id', $emailId)->exists()) {
                        $this->warn("⚠️ Email already processed (ID: $emailId), skipping...");
                        continue;
                    }

                    // 🔹 Use OpenAI to extract structured data
                    $extractedData = $this->extractHotelData($emailText);

                    if (is_object($extractedData)) {
                        $extractedData = json_decode(json_encode($extractedData), true);
                    }

                    if ($extractedData && isset($extractedData['data'])) {
                        $taskData = $extractedData['data'];
    
                        // 🔹 Insert extracted data into `task_emails`
                        $taskEmail = TaskEmail::create([
                            'email_id' => $emailId,
                            'client_id' => $taskData['client_id'] ?? null,
                            'agent_id' => $taskData['agent_id'] ?? null,
                            'type' => $label,
                            'status' => 'pending',
                            'client_name' => $taskData['client_name'] ?? null,
                            'reference' => $taskData['reference'] ?? null,
                            'duration' => $taskData['duration'] ?? null,
                            'payment_type' => $taskData['payment_type'] ?? null,
                            'price' => $taskData['price'] ?? null,
                            'tax' => $taskData['tax'] ?? null,
                            'surcharge' => $taskData['surcharge'] ?? null,
                            'total' => $taskData['total'] ?? null,
                            'cancellation_policy' => $taskData['cancellation_policy'] ?? null,
                            'additional_info' => $taskData['additional_info'] ?? null,
                            'supplier_name' => $taskData['supplier_name'] ?? null,
                            'supplier_id' => $taskData['supplier_id'] ?? null,
                            'venue' => $taskData['venue'] ?? null,
                            'invoice_price' => $taskData['invoice_price'] ?? null,
                            'voucher_status' => $taskData['voucher_status'] ?? null,
                        ]);
                    
                        $taskId = $taskEmail->id;

                            // Insert into `task_hotel_details`
                        if (!empty($taskData['task_hotel_details'])) {
                            $hotelDetails = $taskData['task_hotel_details'];

                            TaskHotelDetailEmail::create([
                                'hotel_id' => null, // Set a valid `hotel_id` if available
                                'booking_time' => $hotelDetails['booking_time'] ?? null,
                                'check_in' => $hotelDetails['check_in'] ?? null,
                                'check_out' => $hotelDetails['check_out'] ?? null,
                                'room_number' => $hotelDetails['room_number'] ?? null,
                                'room_type' => $hotelDetails['room_type'] ?? null,
                                'room_amount' => $hotelDetails['room_amount'] ?? null,
                                'room_details' => $hotelDetails['room_details'] ?? null,
                                'rate' => $hotelDetails['rate'] ?? null,
                                'task_id' => $taskId, // Associate with the task
                            ]);
                        }
                        
                        $this->info("✅ Email ($emailId) processed and inserted.");
                    } else {
                        $this->warn("⚠️ Could not extract valid data from email (ID: $emailId).");
                    }
                }
            } catch (\Exception $e) {
                $this->error("⚠️ Error processing $label: " . $e->getMessage());
            }
        }

        $this->info("\n✅ Email processing completed!");
    }

    private function processWithAI($emailText)
    {
        \Log::info("Starting AI Processing for Email");

        // Fetch clients and agents from DB
        $userData = $this->fetchUserBasedData();

        // Convert them to JSON format for AI processing
        $clientsJson = json_encode($userData['clients'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $agentsJson = json_encode($userData['agents'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $messages = [
            ['role' => 'system', 'content' => 'You are an AI assistant that extracts structured data from emails.'],
            ['role' => 'user', 'content' => "Extract structured data from the following email and return JSON format. Fields:
            - email (client or agent email)
            - client_name
            - reference
            - duration
            - payment_type
            - price
            - tax
            - surcharge
            - total
            - cancellation_policy
            - additional_info
            - supplier_id
            - venue
            - invoice_price
            - voucher_status
    
            - Match `client_name` or 'email' with the `clients` list to get `client_id`.
            - Match `agent_name` or 'email' with the `agents` list to get `agent_id`.
    
            Available Clients:
            $clientsJson
    
            Available Agents:
            $agentsJson
    
            Email Content:
            ---
            $emailText
            ---
            Return JSON format with `client_id`, `agent_id`, and relevant details."]
        ];

        try {
            $response = $this->openAIService->getChatResponse($messages);

            if (!isset($response['choices'][0]['message']['content'])) {
                \Log::error("Unexpected OpenAI response structure", ['response' => $response]);
                return null;
            }
            
            $content = $response['choices'][0]['message']['content'];

            // **Extract JSON correctly**
            $jsonString = preg_replace('/```json(.*?)```/s', '$1', $content);
            $jsonString = trim($jsonString); // Trim extra spaces

            if (empty($jsonString)) {
                \Log::error("Extracted JSON string is empty. Possible regex failure.", ['content' => $content]);
            }
            
            $jsonData = json_decode($jsonString, true);
    
            if (json_last_error() !== JSON_ERROR_NONE) {
                \Log::error("JSON decoding failed", ['json_error' => json_last_error_msg(), 'response' => $content]);
                return null;
            }

            return [
                'client_id' => $jsonData['client_id'] ?? null,
                'agent_id' => $jsonData['agent_id'] ?? null,
                'client_name' => $jsonData['client_name'] ?? null,
                'reference' => $jsonData['reference'] ?? null,
                'duration' => $jsonData['duration'] ?? null,
                'payment_type' => $jsonData['payment_type'] ?? null,
                'price' => $jsonData['price'] ?? null,
                'tax' => $jsonData['tax'] ?? null,
                'surcharge' => $jsonData['surcharge'] ?? null,
                'total' => $jsonData['total'] ?? null,
                'cancellation_policy' => $jsonData['cancellation_policy'] ?? null,
                'additional_info' => $jsonData['additional_info'] ?? null,
                'supplier_id' => $jsonData['supplier_id'] ?? null,
                'venue' => $jsonData['venue'] ?? null,
                'invoice_price' => $jsonData['invoice_price'] ?? null,
                'voucher_status' => $jsonData['voucher_status'] ?? null,
            ];

        } catch (\Exception $e) {
            \Log::error("OpenAI API Error: " . $e->getMessage());
        }
        return null;
    }


    public function extractHotelData($content)
    {
        $supplierList = Supplier::all()->toArray();
        $supplierListJson = json_encode($supplierList);

        $userData = $this->fetchUserBasedData();
        // Convert them to JSON format for AI processing
        $clientsJson = json_encode($userData['clients'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $agentsJson = json_encode($userData['agents'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $prompt = "
        You are an assistant for processing uploaded files to extract structured data for a task management system. The system has two models:

        1. `tasks` model with the following fields:
            - `additional_info`: Additional information.
            - `status`: Current status of the task.
            - `price`: Price of the task in float type.
            - `surcharge`: Any surcharge applied in float type.
            - `total`: Total amount for the task in float type.
            - `tax`: Total tax amount in float type.
            - `reference`: Reference code for the task.
            - `type`: Type of task (hotel or flight).
            - `vendor_name`: name of the vendor email from.
            - `destination`: destination for this travel.
            - `company_name`: name of the company for the task.
            - `agent_name`: name of the agent handling the task.
            - `agent_id`:  You can refer the agent id from this list: $agentsJson
            - `client_name`: name of the client associated with the task, some text have the client name as holder name.
            - `supplier_name`: name of the supplier for the task.
                You can refer the supplier from this list: $supplierListJson
                if the supplier is not in the list, just set it to null.
            - `supplier_id`: You can refer the supplier id from this list: $supplierListJson
            - `supplier_country`: Country of the supplier if stated anywhere in the pdf.
            - `client_name`: Name of the client.
            - `client_id`: You can refer the client id from this list: $clientsJson
            - `cancellation_policy`: Cancellation policy details.
            - `venue`: Venue or location associated with the task.
        
        2. `task_hotel_details` model, which applies only if the task is a hotel booking, with the following fields:
            - `hotel_name`: Name of the hotel.
            - `hotel_address`: Address of the hotel.
            - `hotel_city`: City of the hotel.
            - `hotel_state`: State of the hotel.
            - `hotel_country`: Country of the hotel.
            - `hotel_zip`: Zip code of the hotel.
            - `booking_time`: Time of booking.
            - `check_in`: Check-in date.
            - `check_out`: Check-out date.
            - `room_number`: Room number.
            - `room_type`: Type of room.
            - `room_amount`: Amount of the room in float type.
            - `room_details`: Details of the room.
            - `rate`: Rate of the room in float type.

        Extract relevant data from the uploaded content in JSON format, matching the structure of these models. Only include fields with available data, and omit any null or empty fields.
        if some of the fields are not available, you can set them to null.
        this is the content: $content

        only pass me the data extracted in JSON format.

        example answer = 

        {
            'additional_info': 'King Bed Deluxe High Floor - 2408 Oaks Liwa Heights, Jumeirah Lake Towers',
            'status': 'completed',
            'price': 100.00,
            'surcharge': 10.00,
            'total': 110.00,
            'tax': 5.00,
            'reference': 'relevant reference',
            'type': 'hotel',
            'agent_name': 'agent name',
            'client_name': 'Khaled Alajmi',
            'client_id': 2,
            'agent_id': 2,
            'supplier_id': 2,
            'destination': 'Dubai',
            'company_name': 'City Travellers',
            'supplier_name': 'Magic Holidays',
            'vendor_name': 'Magic Holidays',
            'supplier_country': 'Kuwait',
            'cancellation_policy': 'cancellation policy',
            'venue': 'venue',
            'task_hotel_details': {
                'hotel_name': 'Oaks Liwa Heights',
                'hotel_address': 'Jumeirah Lake Towers',
                'hotel_city': null,
                'hotel_state': 'Dubai',
                'hotel_country': 'United Arab Emirates',
                'hotel_zip': '12345',
                'booking_time': '2024-10-16 14:00:00',
                'check_in': '2024-10-17',
                'check_out': '2024-10-20',
                'room_number': '101',
                'room_type': 'Deluxe Room',
                'room_amount': '100.00',
                'room_details': 'Sea View',
                'rate': '40.00', 
            }
        }
        ";

        $messages = [
            ['role' => 'system', 'content' => 'You are an AI assistant that extracts structured data.'],
            ['role' => 'user', 'content' => $prompt]
        ];

        try {
        $response = $this->openAIService->getChatResponse($messages);

        if (isset($response['choices'][0]['message']['content'])) {
            $message = $response['choices'][0]['message']['content'];

            $decodedResponse = json_decode($message, true);

            if (json_last_error() === JSON_ERROR_NONE) {

                return [
                    'status' => 'success',
                    'message' => 'Data extracted successfully',
                    'data' => $decodedResponse,
                ];
                // return $taskController->saveTasks($decodedResponse);
            } else {
                $cleanedResponse = $this->cleanJsonResponse($message);
                $data = json_decode($cleanedResponse, true);

                if (json_last_error() === JSON_ERROR_NONE) {

                    return [
                        'status' => 'success',
                        'message' => 'Data extracted successfully',
                        'data' => $data,
                    ];
                    // return $taskController->saveTasks($data);
                } else {
                    return [
                        'status' => 'error',
                        'message' => 'Failed to parse JSON or missing required fields.',
                    ];
                }
            }
        }

        } catch (\Exception $e) {
            \Log::error("Error after OpenAI response: " . $e->getMessage());
        }
    }

    private function fetchUserBasedData()
    {
        $suppliers = Supplier::all();
        $companies = Company::all(); 
        $agents = Agent::all(); 
        $clients = Client::all(); 
        return [
            'suppliers' => $suppliers,
            'companies' => $companies,
            'agents' => $agents->map(function ($agent) {
                return [
                    'name' => $agent->name,
                    'id' => $agent->id,
                    'email' => $agent->email,
                    'contact' => $agent->phone_number,
                    'branchId' => $agent->branch_id,
                    'type' => $agent->type,
                ];
            }),
            'clients' => $clients->map(function ($client) {
                return [
                    'name' => $client->name,
                    'id' => $client->id,
                    'agentId' => $client->agent_id,
                    'agentName' => optional($client->agent)->name ?? 'N/A',
                    'contact' => $client->phone,
                    'email' => $client->email,
                    'address' => $client->address,
                    'passportNo' => $client->passport_no,
                ];
            }),
        ];
    }
}
