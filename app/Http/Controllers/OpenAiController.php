<?php

namespace App\Http\Controllers;

use App\Http\Requests\OpenAiRequest;
use App\Http\Traits\HttpRequestTrait;
use App\Models\Client;
use App\Models\Task;
use App\Models\TaskFlightDetail;
use App\Models\Agent;
use App\Models\Supplier;
use App\Models\Airline;
use App\Models\Country;
use App\Models\Hotel;
use App\Models\Role;
use App\Models\TaskHotelDetail;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use PhpParser\Node\Expr\Throw_;
use Ramsey\Uuid\Type\Integer;

class OpenAiController extends Controller
{
    use HttpRequestTrait;
    public function index()
    {
        return view('ai.openai.index');
    }

    public function store(Request $request)
    {
        $prompt = $request->input('prompt');
        $url = config('services.open-ai.url') . '/chat/completions';
        $header = [
            'Authorization: Bearer ' . config('services.open-ai.key'),
            'Content-Type: application/json',
        ];
        $data = [
            'model' => 'gpt-4o-mini',  // Use a valid model name like 'gpt-4' or 'gpt-3.5-turbo'
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an assistant in a travel agency. You will suggest the best flight options to a customer based on their preferences but limit you response to 100 words only.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'stream' => false
        ];

        $response =  $this->postRequest($url, $header, json_encode($data));

        return response()->json($response);
    }

    public function chatCompletion(array $message)
    {
        $url = config('services.open-ai.url') . '/chat/completions';
        $header = [
            'Authorization: Bearer ' . config('services.open-ai.key'),
            'Content-Type: application/json',
        ];
        $data = [
            'model' => 'gpt-4o-mini',
            'messages' => $message,
        ];
        $response =  $this->postRequest($url, $header, json_encode($data));

        logger('chat completion response: ', $response);
        return $response;
    }

    public function extractPassport($content)
    {
        $prompt = "
        You are an assistant for a travel agency. You need to extract passport details from the uploaded content. This passport is extracted by tesseract-OCR. The details you need might be nearby the words or sentences. The passport details should include the following fields:
    
        - `passport_no`: Passport number or Passport No.
        - `civil_no`: Civil number or Civil No.
        - `name`: Full name as per the passport.
        - `nationality`: Nationality
        - `date_of_birth`: Date of birth
        - `date_of_issue`: Date of issue
        - `date_of_expiry`: Date of expiry
        - `place_of_birth`: Place of birth
        - `place_of_issue`: Place of issue
    
        only pass me the data extracted in JSON format.
        ";
    
        // Make the request to the OpenAI API
        $response = $this->chatCompletion([
            [
                'role' => 'user',
                'content' => $prompt,
            ],
            [
                'role' => 'user',
                'content' => $content,
            ],
        ]);
    
        // Check if response contains the expected structure
        if (isset($response['choices'][0]['message']['content'])) {
            $message = $response['choices'][0]['message']['content'];
    
            // Check if the response is already a valid JSON string
            $decodedResponse = json_decode($message, true);
    
            // If it's already a valid array, use it directly
            if (json_last_error() === JSON_ERROR_NONE) {
                return [
                    'status' => 'success',
                    'message' => 'Data extracted successfully',
                    'data' => $decodedResponse,
                ];
            } else {
                // If the message is not valid JSON, attempt to clean the response
                $cleanedResponse = $this->cleanJsonResponse($message);
    
                // Attempt decoding the cleaned response
                $data = json_decode($cleanedResponse, true);
    
                // Check if the cleaned response is a valid JSON
                if (json_last_error() === JSON_ERROR_NONE && isset($data['passport_no'])) {
                    return [
                        'status' => 'success',
                        'message' => 'Data extracted successfully',
                        'data' => $data,
                    ];
                } else {
                    return [
                        'status' => 'error',
                        'message' => 'Failed to parse JSON or missing required fields.',
                    ];
                }
            }
        } else {
            return [
                'status' => 'error',
                'message' => 'Data extraction failed. No content returned from OpenAI.',
            ];
        }
    }

    /**
     * @param string $content
     * 
     * @return string
     */
    public function flightOrHotel($content)
    {
        $prompt = " Check if this document is for a flight or hotel booking. 
                    The document might contain information like booking reference, passenger name, flight details, hotel details, etc. 
                    Suggest if it's a flight or hotel booking. 
                    sample answer: 'flight' or 'hotel'
                    ";
        
        $response = $this->chatCompletion([
            [
                'role' => 'user',
                'content' => $prompt,
            ],
            [
                'role' => 'user',
                'content' => $content,
            ],
        ]);

        if(isset($response['choices'][0]['message']['content'])) {
           $type = $response['choices'][0]['message']['content'];
           
           if($type !== 'flight' && $type !== 'hotel') {
                return [
                    'status' => 'error',
                    'message' => 'Invalid response. Please provide a valid response: flight or hotel'
                ];
           }
        }

        return [
            'status' => 'success',
            'message' => 'Document type identified successfully',
            'data' => $type,
        ];
    } 

    /**
     * Extract flight data from the content
     * 
     * @param string $content
     * 
     * @return array from saveTasks()
     */
    public function extractFlightData($content)
    {
        $supplierList = json_encode(Supplier::all()->toArray());

        $prompt = "
        You are an assistant for processing uploaded files to extract structured data for a task management system. The system has two models:
        
        1. `tasks` model with the following fields:
            - `additional_info`: Additional information but make sure to only include relevant data and below 10 words, summarize it.
            - `status`: Current status of the task. whether it's completed, hold or confirmed or any other status.
            - `price`: Price of the task in float type.
            - `surcharge`: Any surcharge applied in float type.
            - `total`: Total amount for the task in float type.
            - `tax`: Total tax amount in float type.
            - `reference`: Reference code for the task.
            - `type`: Type of task (e.g., flight).
            - `agent_name`: name of the agent handling the task.
            - `client_name`: name of the client associated with the task.
            - `supplier_name`: name of the supplier for the task, depends on supplier stated on the pdf, usually at the top or bottom of the pdf. They are responsible of sending this pdf.
                You can refer the supplier from this list: $supplierList
                if the supplier is not in the list, just set it to null.
            - `supplier_country`: Country of the supplier if stated anywhere in the pdf.
            - `client_name`: Name of the client.
            - `cancellation_policy`: Cancellation policy details.
            - `venue`: Venue or location associated with the task.
        
        2. `task_flight_details` model, which applies only if the task is a flight, with the following fields:
            - `farebase`: Fare basis of the flight in float type.
            - `departure_time`: Departure time of the flight.
            - `departure_from`: Location of departure, it must be a country. If the information retrieve is a city, state or any other than country, you must set it to suitable country.
            - `airport_from`: Airport code or name for departure.
            - `terminal_from`: Departure terminal.
            - `arrival_time`: Arrival time of the flight.
            - `arrive_to`: Location of arrival, it must be a country. If the information retrieve is a city, state or any other than country, you must set it to suitable country.
            - `airport_to`: Airport code or name for arrival.
            - `terminal_to`: Arrival terminal.
            - `airline_name`: Airline name. 
            - `flight_number`: Flight number.
            - `class_type`: Class type of the flight.
            - `baggage_allowed`: Baggage allowance.
            - `equipment`: Equipment used in the flight.
            - `flight_meal`: Meal options during the flight.
            - `seat_no`: Seat number.
        
        Extract relevant data from the uploaded content in JSON format, matching the structure of these models. Only include fields with available data, and omit any null or empty fields.
        if some of the fields are not available, you can set them to null.
        
        all related time should be in the format of 'Y-m-d H:i:s'

        this is the content: $content

        only pass me the data extracted in JSON format.

        example answer = 
        {
            'additional_info': 'additional info',
            'status': 'completed'/ 'hold' / 'confirmed',
            'price': 100.00,
            'surcharge': 10.00,
            'total': 110.00,
            'tax': 5.00,
            'reference': 'reference',
            'type': 'flight',
            'agent_name': 'agent name',
            'client_name': 'client name',
            'supplier_name': 'Amadeus',
            'supplier_country': 'Kuwait',
            'cancellation_policy': 'cancellation policy',
            'venue': 'venue',
            'task_flight_details': {
                'farebase': '20.00',
                'departure_time': '2024-10-16 14:00:00',
                'departure_from': 'Kuwait',
                'airport_from': 'KWI',
                'terminal_from': '1',
                'arrival_time': '2024-10-16 16:00:00',
                'arrive_to': 'Singapore',
                'airport_to': 'SIN',
                'terminal_to': '1',
                'airline_name': 'Kuwait Airways',
                'flight_number': 'KU-123',
                'class_type': 'economy',
                'baggage_allowed': 'baggage allowed',
                'equipment': 'equipment',
                'flight_meal': 'flight meal',
                'seat_no': 'seat no',
            }
        }

        ";

        $response = $this->chatCompletion([
            [
                'role' => 'user',
                'content' => $prompt,
            ],
            [
                'role' => 'user',
                'content' => $content,
            ],
        ]);

        if(isset($response['choices'][0]['message']['content'])) {
            $message = $response['choices'][0]['message']['content'];
            
            $decodedResponse = json_decode($message, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $this->saveTasks($decodedResponse);
            } else {
                $cleanedResponse = $this->cleanJsonResponse($message);
                $data = json_decode($cleanedResponse, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    return $this->saveTasks($data);
                } else {
                    return [
                        'status' => 'error',
                        'message' => 'Failed to parse JSON or missing required fields.',
                    ];
                }
            }
        }            
            
    }

    /**
     * Extract hotel data from the content
     * 
     * @param string $content
     * 
     * @return array from saveTasks()
     */
    public function extractHotelData($content)
    {
        $supplierList = Supplier::all()->toArray();
        
        $prompt = "
        You are an assistant for processing uploaded files to extract structured data for a task management system. The system has two models:

        1. `tasks` model with the following fields:
            - `additional_info`: Additional information but make sure to only include relevant data and below 10 words, summarize it.
            - `status`: Current status of the task.
            - `price`: Price of the task in float type.
            - `surcharge`: Any surcharge applied in float type.
            - `total`: Total amount for the task in float type.
            - `tax`: Total tax amount in float type.
            - `reference`: Reference code for the task.
            - `type`: Type of task (e.g., flight).
            - `agent_name`: name of the agent handling the task.
            - `client_name`: name of the client associated with the task, some pdfs have the client name as holder name.
            - `supplier_name`: name of the supplier for the task, depends on supplier stated on the pdf, usually at the top or bottom of the pdf. They are responsible of sending this pdf.
                You can refer the supplier from this list: $supplierList
                if the supplier is not in the list, just set it to null.
            - `supplier_country`: Country of the supplier if stated anywhere in the pdf.
            - `client_name`: Name of the client.
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
            'supplier_name': 'Magic Holidays',
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
        
        $response = $this->chatCompletion([
            [
                'role' => 'user',
                'content' => $prompt,
            ],
            [
                'role' => 'user',
                'content' => $content,
            ],
        ]);

        if(isset($response['choices'][0]['message']['content'])) {
            $message = $response['choices'][0]['message']['content'];
            
            $decodedResponse = json_decode($message, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $this->saveTasks($decodedResponse);
            } else {
                $cleanedResponse = $this->cleanJsonResponse($message);
                $data = json_decode($cleanedResponse, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    return $this->saveTasks($data);
                } else {
                    return [
                        'status' => 'error',
                        'message' => 'Failed to parse JSON or missing required fields.',
                    ];
                }
            }
        }
    }

    function cleanJsonResponse($responseText)
    {
        // Remove code block delimiters like """ and ```
        $responseText = preg_replace('/^[\s]*"""|```json|```|"""[\s]*$/', '', $responseText);

        // Remove any newlines or excess whitespace around the JSON
        $responseText = trim($responseText);

        // Decode the JSON to verify it's valid, then re-encode it to return clean JSON
        $jsonData = json_decode($responseText, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($jsonData, JSON_PRETTY_PRINT); // Return clean, pretty JSON
        } else {
            // Handle JSON decoding errors if needed
            throw new Exception("Invalid JSON format in AI response.");
        }
    }

    /**
     * Save tasks to the database
     * 
     * @param array $data
     * 
     * @return array contains status, message and data of task id
     * 
     */
    function saveTasks($data)
    {
        logger('Data: ', $data);
        $task = $data;
        
        if(auth()->user()->role_id == Role::COMPANY )
        {
            $companyId = auth()->user()->company->id;
        } else if (auth()->user()->role_id == Role::BRANCH) {
            $companyId = auth()->user()->branch->company_id;
        } else if(auth()->user()->role_id == Role::AGENT) {
            $companyId = auth()->user()->agent->branch->company_id;
        } else {

            return [
                'status' => 'error',
                'message' => 'User not authorized to create task',
            ];

        }

        $agent = (isset($task['agent_name']) && $task['agent_name'] !== null) ?
            Agent::where('name', 'like', '%' . $task['agent_name'] . '%')->first()
            : Agent::with(['branch' => function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            }])->first();
        
        $client = (isset($task['client_name']) && $task['client_name'] !== null) ? Client::where('name', 'like', '%' . $task['client_name'] . '%')->first() : null;
 
        if($task['supplier_name'] === null) {
            throw new Exception('Supplier name is not found');
        }

        $supplier = Supplier::where('name', 'like', '%' . $task['supplier_name'] . '%')->first();
        
        if(!$supplier) {
            return [
                'status' => 'error',
                'message' => 'Supplier not found',
            ];
        }

        $taskData = [
            'additional_info' => $task['additional_info'] ?? null,
            'status' => $task['status'] ? strtolower($task['status']) : null,
            'client_name' => $client->name ?? null,
            'price' => isset($task['price']) ? $task['price'] : null,
            'surcharge' => isset($task['surcharge']) ? $task['surcharge'] : null,
            'total' => isset($task['total']) ? $task['total'] : null,
            'tax' => isset($task['tax']) ? $task['tax'] : null,
            'reference' => $task['reference'] ?? null,
            'type' => strtoupper($task['type']) ?? null,
            'agent_id' => $agent->id ,
            'client_id' => $client->id ?? null,
            'supplier_id' => $supplier->id ,
            'cancellation_policy' => $task['cancellation_policy'] ?? null,
            'venue' => $task['venue'] ?? null,
        ];

        try {
            $taskCreated = Task::create($taskData);

            logger('Task created: ', $taskCreated->get()->toArray());


            if (isset($data['task_flight_details'])) {
                $this->saveFlightDetails($data, $taskCreated->id);
            }

            if (isset($data['task_hotel_details'])) {
                $this->saveHotelDetails($data, $taskCreated->id);
            }
        } catch (Exception $e) {
            throw $e;
        }
        return [
            'status' => 'success',
            'message' => 'Task created successfully',
            'data' => $taskCreated,
        ];
    }

    /**
     * Dont used yet
     * 
     * @return view
     */
    public function fineTuningView()
    {
        return view('ai.openai.fine-tuning');
    }

    /**
     * Save flight details to the database
     * 
     * @param array $data
     * @param int $taskId
     * 
     * @return void 
     *
     */
    public function saveFlightDetails(array $data, int $taskId)
    {
        
        try{

            $data = $data['task_flight_details'];
            
            $airline = isset($data['airline_name']) ? Airline::where('name', 'like', '%' . $data['airline_name'] . '%')->first() : null;
            $countryFrom = isset($data['departure_from']) ? Country::where('name', 'like', '%' . $data['departure_from'] . '%')->first() : null;
            $countryTo = isset($data['departure_from']) ? Country::where('name', 'like', '%' . $data['arrive_to'] . '%')->first() : null;


            $flightDetails = [
                'farebase' => (float)$data['farebase'] ?? null,
                'departure_time' => $data['departure_time'] ?? null,
                'country_id_from' => $countryFrom->id ?? null,
                'airport_from' => $data['airport_from'] ?? null,
                'terminal_from' => $data['terminal_from'] ?? null,
                'arrival_time' => $data['arrival_time'] ?? null,
                'country_id_to' => $countryTo-> id ?? null,
                'airport_to' => $data['airport_to'] ?? null,
                'terminal_to' => $data['terminal_to'] ?? null,
                'airline_id' => $airline->id ?? null,
                'flight_number' => $data['flight_number'] ?? null,
                'class_type' => $data['class_type'] ?? null,
                'baggage_allowed' => $data['baggage_allowed'] ?? null,
                'equipment' => $data['equipment'] ?? null,
                'flight_meal' => $data['flight_meal'] ?? null,
                'seat_no' => $data['seat_no'] ?? null,
                'task_id' => $taskId
            ];

             TaskFlightDetail::create($flightDetails);

        } catch (Exception $e) {

           throw $e; 
        }
    }

    /**
     * Save hotel details to the database
     * 
     * @param array $data
     * @param int $taskId
     * 
     * @return void
     */
    public function saveHotelDetails(array $data, int $taskId)
    {
        try {
            $data = $data['task_hotel_details'];

            $hotel = Hotel::where('name', 'like', '%' . $data['hotel_name'] . '%')->first();

            $hotelCountry = Country::where('name', 'like', '%' . $data['hotel_country'] . '%')->first();

            if(!$hotel) {
                $hotel = Hotel::create([
                    'name' => $data['hotel_name'],
                    'address' => $data['hotel_address'] ?? null,
                    'city' => $data['hotel_city'] ?? null,
                    'state' => $data['hotel_state'] ?? null,
                    'country_id' => $hotelCountry->id ?? null,
                    'zip' => $data['hotel_zip'] ?? null,
                ]);
            }

            $hotelDetails = [
                'hotel_id' => $hotel->id,
                'booking_time' => $data['booking_time'] ?? null,
                'check_in' => $data['check_in'] ?? null,
                'check_out' => $data['check_out'] ?? null,
                'room_number' => $data['room_number'] ?? null,
                'room_type' => $data['room_type'] ?? null,
                'room_amount' => $data['room_amount'] ?? null,
                'room_details' => $data['room_details'] ?? null,
                'rate' => $data['rate'] ?? null,
                'task_id' => $taskId
            ];
            TaskHotelDetail::create($hotelDetails);
        } catch (Exception $e) {
            throw $e;
        }
    }
}
