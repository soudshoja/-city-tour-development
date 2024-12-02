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
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

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

    public function extractFlightData($content)
    {
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
            - `client_name`: name of the client associated with the task.
            - `supplier_name`: name of the supplier for the task, depends on supplier stated on the pdf, usually at the top or bottom of the pdf. They are responsible of sending this pdf.
            - `client_name`: Name of the client.
            - `cancellation_policy`: Cancellation policy details.
            - `venue`: Venue or location associated with the task.
        
        2. `task_flight_details` model, which applies only if the task is a flight, with the following fields:
            - `farebase`: Fare basis of the flight.
            - `departure_time`: Departure time of the flight.
            - `departure_from`: Location of departure.
            - `airport_from`: Airport code or name for departure.
            - `arrival_time`: Arrival time of the flight.
            - `terminal_to`: Arrival terminal.
            - `arrive_to`: Location of arrival.
            - `airport_to`: Airport code or name for arrival.
            - `terminal_from`: Departure terminal.
            - `airline_name`: Airline name. 
            - `flight_number`: Flight number.
            - `class_type`: Class type of the flight.
            - `baggage_allowed`: Baggage allowance.
            - `equipment`: Equipment used in the flight.
            - `flight_meal`: Meal options during the flight.
            - `seat_no`: Seat number.
        
        Extract relevant data from the uploaded content in JSON format, matching the structure of these models. Only include fields with available data, and omit any null or empty fields.

        this is the content: $content

        only pass me the data extracted in JSON format.

        example answer = 
        {
            'additional_info': 'additional info',
            'status': 'status',
            'price': 100.00,
            'surcharge': 10.00,
            'total': 110.00,
            'tax': 5.00,
            'reference': 'reference',
            'type': 'flight',
            'agent_name': 'agent name',
            'client_name': 'client name',
            'supplier_name': 'supplier name',
            'cancellation_policy': 'cancellation policy',
            'venue': 'venue',
            'task_flight_details': {
                'farebase': 'farebase',
                'departure_time': 'departure time',
                'departure_from': 'departure from',
                'airport_from': 'airport from',
                'arrival_time': 'arrival time',
                'terminal_to': 'terminal to',
                'arrive_to': 'arrive to',
                'airport_to': 'airport to',
                'terminal_from': 'terminal from',
                'airline_name': 'airline name',
                'flight_number': 'flight number',
                'class_type': 'class type',
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
        $taskCreated = Task::findOrFail(28);
        return [
            'status' => 'success',
            'message' => 'Task created successfully',
            'data' => $taskCreated->id,
        ];

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
            - `client_name`: name of the client associated with the task.
            - `supplier_name`: name of the supplier for the task, depends on supplier stated on the pdf, usually at the top or bottom of the pdf. They are responsible of sending this pdf.
            - `client_name`: Name of the client.
            - `cancellation_policy`: Cancellation policy details.
            - `venue`: Venue or location associated with the task.
        
        2. `task_hotel_details` model, which applies only if the task is a hotel booking, with the following fields:
            - `hotel_id`: Check-in date for the hotel booking.
            - `booking_time`: Check-out date for the hotel booking.
            - `check_in`: Type of room booked.
            - `check_out`: Price of the room in float type.
            - `room_number`: Name of the hotel.
            - `room_type`: Address of the hotel.
            - `room_amount`: Contact number of the hotel.
            - `room_details`: Email address of the hotel.
            - `rate`: Website of the hotel.
            - `task_id`: Rating of the hotel.

        Extract relevant data from the uploaded content in JSON format, matching the structure of these models. Only include fields with available data, and omit any null or empty fields.

        this is the content: $content

        only pass me the data extracted in JSON format.

        example answer = 

        {
            'additional_info': 'King Bed Deluxe High Floor - 2408 Oaks Liwa Heights, Jumeirah Lake Towers',
            'status': 'status',
            'price': 100.00,
            'surcharge': 10.00,
            'total': 110.00,
            'tax': 5.00,
            'reference': 'relevant reference',
            'type': 'hotel',
            'agent_name': 'agent name',
            'client_name': 'Khaled Alajmi',
            'supplier_name': 'Magic Holidays',
            'cancellation_policy': 'cancellation policy',
            'venue': 'venue',
            'task_hotel_details': {
                'hotel_id': 'hotel id',
                'booking_time': 'booking time',
                'check_in': 'check in',
                'check_out': 'check out',
                'room_number': 'room number',
                'room_type': 'room type',
                'room_amount': 'room amount',
                'room_details': 'room details',
                'rate': 'rate',
                'task_id': 'task id',
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
        $client = Client::where('name', 'like', '%' . $task['client_name'] . '%')->first();

        if (!$client) {
            $client = Client::create([
                'name' => $task['client_name'],
                'status' => 'active',
            ]);
        }

        $agent = Agent::where('name', 'like', '%' . $task['agent_name'] . '%')->first();

        $supplier = Supplier::where('name', 'like', '%' . $task['supplier_name'] . '%')->first();

        $taskData = [
            'additional_info' => $task['additional_info'] ?? null,
            'status' => $task['status'] ?? null,
            'client_name' => $task['client_name'] ?? null,
            'price' => isset($task['price']) ? $task['price'] : null,
            'surcharge' => isset($task['surcharge']) ? $task['surcharge'] : null,
            'total' => isset($task['total']) ? $task['total'] : null,
            'tax' => isset($task['tax']) ? $task['tax'] : null,
            'reference' => $task['reference'] ?? null,
            'type' => strtoupper($task['type']) ?? null,
            'agent_id' => $agent->id ?? 16,
            'client_id' => $client->id ?? 1,
            'supplier_id' => $supplier->id ?? 11,
            'cancellation_policy' => $task['cancellation_policy'] ?? null,
            'venue' => $task['venue'] ?? null,
        ];
        $taskCreated = Task::create($taskData);

        logger('Task created: ', $taskCreated->get()->toArray());

        // Save flight details if available
        // if (isset($data['task_flight_details'])) {
        //     $data = $data['task_flight_details'];
        //     $airline = Airline::where('name', 'like', '%' . $data['airline_name'] . '%')->first();
        //     $flightDetails = [
        // 'farebase' => $flight['fare_basis'] ?? null,
        // 'departure_time' => $flight['departure_time'] ?? null,
        // 'departure_from' => $flight['from'] ?? null,
        // 'airport_from' => $flight['from'] ?? null,
        // 'arrival_time' => $flight['arrival_time'] ?? null,
        // 'terminal' => $flight['terminal_arrival'] ?? null,
        // 'arrive_to' => $flight['to'] ?? null,
        // 'airport_to' => $flight['to'] ?? null,
        // 'terminal_from' => $flight['terminal_from'] ?? null,
        // 'airline_id' => $airline->id ?? null,
        // 'flight_number' => $flight['flight_number'] ?? null,
        // 'class_type' => $flight['class'] ?? null,
        // 'baggage_allowed' => $flight['baggage_allowance'] ?? null,
        // 'equipment' => $flight['equipment'] ?? null,
        // 'flight_meal' => $flight['meal'] ?? null,
        // 'seat_no' => $flight['seat_no'] ?? null,
        // 'task_id' => $taskCreated->id,
        //     ];
        //     TaskFlightDetail::create($flightDetails);
        //}

        return [
            'status' => 'success',
            'message' => 'Task created successfully',
            'data' => $taskCreated->id,
        ];
    }

    public function fineTuningView()
    {
        return view('ai.openai.fine-tuning');
    }
}
