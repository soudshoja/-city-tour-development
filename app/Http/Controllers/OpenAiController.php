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
use App\Models\ChatCompletion;
use App\Models\Conversation;
use App\Models\Country;
use App\Models\Hotel;
use App\Models\Message;
use App\Models\Role;
use App\Models\TaskHotelDetail;
use App\Models\User;
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
            'model' => config('services.open-ai.model'),  // Use a valid model name like 'gpt-4' or 'gpt-3.5-turbo'
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

    public function chatCompletionJsonResponse(array $message)
    {
        $url = config('services.open-ai.url') . '/chat/completions';
        $header = [
            'Authorization: Bearer ' . config('services.open-ai.key'),
            'Content-Type: application/json',
        ];
        $data = [
            'model' => config('services.open-ai.model'),
            'messages' => $message,
            'response_format' => [
                'type' => 'json_object',
            ]
        ];

        $response =  $this->postRequest($url, $header, json_encode($data));

        logger('chat completion response: ', $response);
        return $response;
    }

    public function chatCompletion(array $message)
    {
        $url = config('services.open-ai.url') . '/chat/completions';
        $header = [
            'Authorization: Bearer ' . config('services.open-ai.key'),
            'Content-Type: application/json',
        ];
        $data = [
            'model' => config('services.open-ai.model'),
            'messages' => $message,
        ];
        $response =  $this->postRequest($url, $header, json_encode($data));

        logger('chat completion response: ', $response);
        return $response;
    }

    public function chatCompletionTools(string $model = 'gpt-4o-mini', array $tools, array $message)
    {
        $url = config('services.open-ai.url') . '/chat/completions';
        $header = [
            'Authorization: Bearer ' . config('services.open-ai.key'),
            'Content-Type: application/json',
        ];

        $data = [
            'model' => $model,
            'messages' => $message,
            'tools' => $tools,
            'user' => 'user',
        ];

        $response =  $this->postRequest($url, $header, json_encode($data));

        logger('chat completion tools response: ', $response);

        return $response;
    }

    public function chatCompletionImage($prompt, $image)
    {
        $url = config('services.open-ai.url') . '/chat/completions';
        $header = [
            'Authorization: Bearer ' . config('services.open-ai.key'),
            'Content-Type: application/json',
        ];
        $data = [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $prompt,
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => 'C:\Users\User\Documents\GitHub\city-tour\storage\app\public\passports\passportClient.jpeg',
                            ]
                        ],
                    ]
                ],
            ],
        ];

        $response =  $this->postRequest($url, $header, json_encode($data));
        logger('chat completion image response: ', $response);
        return response()->json($response);
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

        if (isset($response['choices'][0]['message']['content'])) {
            $message = $response['choices'][0]['message']['content'];
            $message = $this->cleanJsonResponse($message);

            return [
                'status' => 'success',
                'message' => 'Data extracted successfully',
                'data' => $message,
            ];
        } else {
            $message = $response;

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

        if (isset($response['choices'][0]['message']['content'])) {
            $type = $response['choices'][0]['message']['content'];

            if ($type !== 'flight' && $type !== 'hotel') {
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
        $taskController = new TaskController();

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

        if (isset($response['choices'][0]['message']['content'])) {
            $message = $response['choices'][0]['message']['content'];

            $decodedResponse = json_decode($message, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $taskController->saveTasks($decodedResponse);
            } else {
                $cleanedResponse = $this->cleanJsonResponse($message);
                $data = json_decode($cleanedResponse, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    return $taskController->saveTasks($data);
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
        $taskController = new TaskController();

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

        if (isset($response['choices'][0]['message']['content'])) {
            $message = $response['choices'][0]['message']['content'];

            $decodedResponse = json_decode($message, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $taskController->saveTasks($decodedResponse);
            } else {
                $cleanedResponse = $this->cleanJsonResponse($message);
                $data = json_decode($cleanedResponse, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    return $taskController->saveTasks($data);
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
     * Dont used yet
     * 
     * @return view
     */
    public function fineTuningView()
    {
        return view('ai.openai.fine-tuning');
    }


    // THREAD
    public function createThreadRun(string $assistantId, User $user)
    {
        if ($assistantId == null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Assistant ID is required',
            ]);
        }

        $url = config('services.open-ai.url') . '/threads/runs';
        $header = [
            'Authorization: Bearer ' . config('services.open-ai.key'),
            'Content-Type: application/json',
            'OpenAI-Beta: assistants=v2',
        ];
        $data = [
            'assistant_id' => $assistantId,
            'additional_instructions' => 'Address the user as' . $user->name . ', but you dont need to call his name every time you respond.',
            'metadata' => [
                'user_id' => (string) $user->id,
            ],
        ];

        $response = $this->postRequest($url, $header, json_encode($data));

        logger('create thread run response: ', $response);

        if (isset($response['id'])) {
            return [
                'status' => 'success',
                'message' => 'Thread run created successfully',
                'data' => $response,
            ];
        } else {

            return [
                'status' => 'error',
                'message' => 'Failed to create thread run',
                'data' => $response,
            ];
        }
    }

    public function createThread(User $user)
    {
        $url = config('services.open-ai.url') . '/threads';
        $header = [
            'Authorization: Bearer ' . config('services.open-ai.key'),
            'Content-Type: application/json',
            'OpenAI-Beta: assistants=v2',
        ];
        $data = [
            'messages' => [
                'role' => 'user',
                'content' => 'You are going to help me to manage my travel business, my name is ' . $user->name . '. My id is ' . $user->id . '.',
            ],
            'metadata' => [
                'user_id' => (string) $user->id,
            ],
        ];

        $response = $this->postRequest($url, $header, json_encode($data));

        logger('create thread response: ', $response);

        return [
            'status' => isset($response['id']) ? 'success' : 'error',
            'message' => isset($response['id']) ? 'Thread created successfully' : 'Failed to create thread',
            'data' => $response,
        ];
    }

    public function retrieveThread($id)
    {
        $url = config('services.open-ai.url') . '/threads/' . $id;
        $header = [
            'Authorization: Bearer ' . config('services.open-ai.key'),
            'Content-Type: application/json',
            'OpenAI-Beta: assistants=v2',
        ];

        $response = $this->getRequest($url, $header);

        return response()->json($response);
    }

    public function deleteThread(string $threadId)
    {
        $url = config('services.open-ai.url') . '/threads/' . $threadId;
        $header = [
            'Authorization: Bearer ' . config('services.open-ai.key'),
            'Content-Type: application/json',
            'OpenAI-Beta: assistants=v2',
        ];

        $response = $this->deleteRequest($url, $header);

        return response()->json($response);
    }

    // public function createAssistant()
    // {
    //     $url = config('services.open-ai.url') . '/assistants';
    //     $header = [
    //         'Authorization: Bearer ' . config('services.open-ai.key'),
    //         'Content-Type: application/json',
    //         'OpenAI-Beta: assistants=v2',
    //     ];

    //     $data = [
    //         'model' => 'gpt-4o-mini',
    //         'name' => 'City Tour Travel Assistant',
    //         'description' => 'Assistant for City Tour travel agency system',
    //         'instructions' => 'You are an assistant in a travel agency system. You will learn everything about this system and help users to get the information they need. You can ask for help if you need it.',
    //         'metadata' => [
    //             'user_id' => 'user-test',
    //         ],
    //         'temperature' => 0.5,
    //     ];

    //     $response = $this->postRequest($url, $header, json_encode($data));

    //     return response()->json($response);
    // }

    // MESSAGE
    public function createMessage(string $threadId, string $message, array $functions = [], bool $isFunctionResponse = false)
    {
        $url = config('services.open-ai.url') . '/threads/' . $threadId . '/messages';
        $header = [
            'Authorization: Bearer ' . config('services.open-ai.key'),
            'Content-Type: application/json',
            'OpenAI-Beta: assistants=v2',
        ];
        $data = [
            'role' => 'user',
            'content' => $message,
        ];

        $messageResponse =  $this->postRequest($url, $header, json_encode($data)); 

        return [
            'status' => isset($messageResponse['id']) ? 'success' : 'error',
            'message' => isset($messageResponse['id']) ? 'Message created successfully' : 'Failed to create message',
            'data' => $messageResponse,
        ];
    }

    public function getMessages(string $threadId, string $assistantId, User $user)
    {
        $url = config('services.open-ai.url') . '/threads/' . $threadId . '/messages';
        $header = [
            'Authorization: Bearer ' . config('services.open-ai.key'),
            'Content-Type: application/json',
            'OpenAI-Beta: assistants=v2',
        ];


        $response = $this->getRequest($url, $header);
        logger('get messages response: ', $response);
        if(isset($response['error']['message'])){
            if(str_contains($response['error']['message'], 'No thread found')){
               
                $createThread = $this->createThread($user);

                if($createThread['status'] == 'error'){
                    return $createThread;
                }
                logger('create thread response: ', $createThread['data']);

                $threadId = $createThread['data']['id'];

                Conversation::updateOrCreate([
                    'user_id' => $user->id,
                    'assistant_id' => env('OPENAI_ASSISTANT_ID'),
                    'thread_id' => $threadId,
                ]);

                $response = $this->getMessages($threadId, $assistantId, $user);

            } else {
                return [
                    'status' => 'error',
                    'message' => 'Failed to retrieve messages',
                    'data' => $response,
                ];
            }
        }

        return [
            'status' => 'success',
            'message' => 'Messages retrieved successfully',
            'data' => $response['data']
        ];
    }

    // RUN
    public function createRun(string $assistantId, string $threadId, User $user)
    {

        $url = config('services.open-ai.url') . '/threads/' . $threadId . '/runs';
        $header = [
            'Authorization: Bearer ' . config('services.open-ai.key'),
            'Content-Type: application/json',
            'OpenAI-Beta: assistants=v2',
        ];
        $data = [
            'assistant_id' => $assistantId,
            'additional_instructions' => "Address the user as" . $user->name . ", but you don't need to call his name every time you respond.",
            'metadata' => [
                'user_id' => (string) $user->id,
            ],
        ];

        $response = $this->postRequest($url, $header, json_encode($data));
        
        return [
            'status' => isset($response['id']) ? 'success' : 'error',
            'message' => isset($response['id']) ? 'Run created successfully' : 'Failed to create run',
            'data' => $response,
        ];
    }

    public function checkRun(string $threadId, string $runId)
    {
        $url = config('services.open-ai.url') . '/threads/' . $threadId . '/runs/' . $runId;

        $header = [
            'Authorization: Bearer ' . config('services.open-ai.key'),
            'Content-Type: application/json',
            'OpenAI-Beta: assistants=v2',
        ];


        $countCheck = 0;

        while (true) {

            try {

                $response = $this->getRequest($url, $header);

                if (isset($response['status']) ? $response['status'] === 'completed' : false) {
                    return [
                        'status' => 'success',
                        'message' => 'Run completed successfully',
                        'data' => $response
                    ];
                } if (isset($response['status']) ? $response['status'] === 'requires_action' : false) {
                    return [
                        'status' => 'requires_action',
                        'message' => 'Run requires action',
                        'data' => $response,
                    ];
                } else

                if ($countCheck > 5) {
                    return [
                        'status' => 'error',
                        'message' => 'Run is taking too long to complete',
                        'data' => $response,
                    ];
                }
            } catch (Exception $e) {
                throw $e;
            }

            sleep(1);
            $countCheck++;
        }
    }

    public function listRun($threadId)
    {
        $url = config('services.open-ai.url') . '/threads/' . $threadId . '/runs';
        $header = [
            'Authorization: Bearer ' . config('services.open-ai.key'),
            'Content-Type: application/json',
            'OpenAI-Beta: assistants=v2',
        ];

        $response = $this->getRequest($url, $header);

        return response()->json($response);
    }

    public function cancelRun($threadId, $runId)
    {
        $url = config('services.open-ai.url') . '/threads/' . $threadId . '/runs/' . $runId . '/cancel';
        $header = [
            'Authorization: Bearer ' . config('services.open-ai.key'),
            'OpenAI-Beta: assistants=v2',
        ];

        $cancelRunResponse = $this->postRequest($url, $header, null);

        if(isset($cancelRunResponse['error'])){
            return [
                'status' => 'error',
                'message' => 'Failed to cancel run',
                'data' => $cancelRunResponse,
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Run cancelled successfully',
            'data' => $cancelRunResponse,
        ];

    }

    // RUN STEP
    public function listStep(string $threadId, string $runId)
    {
        $url = config('services.open-ai.url') . '/threads/' . $threadId . '/runs/' . $runId . '/steps';
        $header = [
            'Authorization: Bearer ' . config('services.open-ai.key'),
            'Content-Type: application/json',
            'OpenAI-Beta: assistants=v2',
        ];

        $response = $this->getRequest($url, $header);

        if(isset($response['error'])){
            return [
                'status' => 'error',
                'message' => 'Failed to step run',
                'data' => $response,
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Run stepped successfully',
            'data' => $response['data']
        ];
    }

    public function retrieveStep(string $threadId, string $runId, string $stepId)
    {
        $url = config('services.open-ai.url') . '/threads/' . $threadId . '/runs/' . $runId . '/steps/' . $stepId;
        $header = [
            'Authorization: Bearer ' . config('services.open-ai.key'),
            'Content-Type: application/json',
            'OpenAI-Beta: assistants=v2',
        ];

        $response = $this->getRequest($url, $header);

        if(isset($response['error'])){
            return [
                'status' => 'error',
                'message' => 'Failed to retrieve run',
                'data' => $response,
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Run retrieved successfully',
            'data' => $response['data']
        ];
    }

    public function sendMessage(Request $request)
    {

        $response = $this->askOpenAi($request->input('prompt'), $request->input('user_id'));

        return response()->json($response);
    }

    /**
     * Ask the AI system
     * 
     * @param string $content
     * @param int $userId
     * 
     * @return array ['status', 'message', 'data']
     */
    public function askOpenAi($content, $userId) : array
    {
        $user = User::find($userId);
        $conversation = collect();
        $createNewThread = true;

        //Check if the message is question or action
        $response = $this->promptOrAction($content);
        
        logger('prompt or action response: ', $response);

        if (isset($response['error']) || $response['status'] === 'error') {
            return $response;
        }

        if ($response['data']['type'] === 'prompt') {

            //Check thread for this user, if not exist create new thread, by default create new thread is true
            $conversation = Conversation::where('user_id', $userId)->latest()->first();

            if ($conversation) {
                $createNewThread = $conversation->thread_id == null || $conversation->assistant_id == null; // return false or true
            } 

            if ($createNewThread) {

                $threadRunResponse = $this->createThread($user);

                if ($threadRunResponse['status'] == 'error') {
                    return $threadRunResponse;
                }
                
                // one user can only one thread at a time
                $conversation = Conversation::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'assistant_id' => env('OPENAI_ASSISTANT_ID'),
                    ],
                    [
                        'thread_id' => $threadRunResponse['data']['id'],
                    ]
                );
            }

            $assistantId = $conversation->assistant_id;
            $threadId = $conversation->thread_id;

            //Create message for thread
            $messageResponse = $this->createMessage($threadId, $content);

            if($messageResponse['status'] == 'error') return $messageResponse;
            

            $messageResponse = $messageResponse['data'];
            
            logger('message response: ', $messageResponse);

            $createdMessageId = $this->saveMessagesDB(
                $conversation->id,
                null,
                $messageResponse['id'],
                'prompt',
                $tokens = []
            );

            //Run thread
            $runResponse = $this->createRun($assistantId, $threadId, $user);

            if($runResponse['status'] === 'error') return $runResponse; 

            $runId = $runResponse['data']['id'];

            logger('run response: ', $runResponse);

            $this->updateMessageDB($createdMessageId, ['run_id' => $runId]);

            //Run status check, if status is complete, get messages and show to user
            $checkRunResponse = $this->checkRun($threadId, $runId);

            if($checkRunResponse['status'] === 'error') return $checkRunResponse;
            
            logger('check run response: ', $checkRunResponse);

            $toolOutputs = [];

            if( $checkRunResponse['status']==='requires_action'){
                foreach($checkRunResponse['data']['required_action']['submit_tool_outputs']['tool_calls'] as $tool){
                    $toolId = $tool['id'];
                    $toolName = $tool['function']['name'];
                    $toolArguments = json_decode($tool['function']['arguments'], true);
                    $functionResponse = $this->callFunction($toolName, $toolArguments);

                    if(isset($functionResponse['error'])) return $functionResponse;

                    $toolOutputs[] = [
                        'tool_call_id' => $toolId,
                        'output' => $functionResponse['success'],
                    ];
                }

                $toolOutputResponse = $this->submitToolOutputs($threadId, $runId, $toolOutputs);

                if($toolOutputResponse['status'] === 'error') return $toolOutputResponse;
            }

            $tokens = $checkRunResponse['data']['usage'];

            $messages = $this->getMessages($threadId, $assistantId, $user);

            if($messages['status'] === 'error') return $messages;

            $latestMessage = $messages['data'][0];

            $answer = $latestMessage['content'][0]['text']['value'];

            if ($latestMessage['role'] == 'assistant') {
                $this->saveMessagesDB(
                    $conversation->id,
                    $runId,
                    $latestMessage['id'],
                    'answer',
                    $tokens
                );
            }

            return [
                'status' => 'success',
                'message' => 'Question asked successfully',
                'data' => $messages['data'],
            ];


        } else if($response['data']['type'] === 'action') {

            return [
                'status' => 'error',
                'message' => 'Sorry, action is not yet supported',
                'data' => [
                    'type' => $response['data']['type'],
                ]
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Invalid response. Please provide a valid response: action or question',
                'data' => $response['data']
            ];
        }
    }

    public function promptOrAction($data)
    {

        $content = [
            [
                'role' => 'user',
                'content' => 'determined if the content is prompt or action : ' . $data . ' and return the answer either , "action" or "prompt", usually the it will be a prompt, if you are not sure just default to prompt
                action usually is a command or instruction so that the system can perform an action based on the command or instruction, while prompt is a question or request for information',
            ],
            [
                'role' => 'user',
                'content' => 'example answer in json format:
                    {
                     "type": "prompt"
                    }     
                "',
            ]
        ];

        $response = $this->chatCompletionJsonResponse($content);

        if (isset($response['error'])) {
            return [
                'status' => 'error',
                'message' => 'Failed to determine the type of content',
                'data' => $response['error']
            ];
        }

        if (isset($response['choices'][0]['message']['content'])); {
            $message = $response['choices'][0]['message']['content'];

            $message = json_decode($message)->type;

            if ($message == 'action') {
                return [
                    'status' => 'error',
                    'message' => 'Sorry, action is not yet supported',
                    'data' => [
                        'type' => $message,
                    ]
                ];
            }

            if ($message !== 'prompt') {
                return [
                    'status' => 'error',
                    'message' => 'Invalid response. Please provide a valid response: action or question',
                    'data' => $message
                ];
            }

            return [
                'status' => 'success',
                'message' => 'Prompt type identified successfully',
                'data' => [
                    'type' => $message,
                ]
            ];
        }
    }

    /**
     * Ask a question to the AI system
     * 
     * @param array $data
     * @param int $userId
     * 
     * @return array ['status', 'message', 'data']
     */
    public function askQuestion($data, $userId)
    {

        $data =  [];

        $pastConversation = Conversation::with('messages')->where('user_id', $userId)->get();

        if (count($pastConversation) > 0) {

            foreach ($pastConversation as $conversation) {
                $data[] = [
                    'role' => 'user',
                    'content' => 'my question: ' . $conversation->messages->where('type', 'question')->first()->content . ' and your answer: ' . $conversation->messages->where('type', 'answer')->first()->content,
                ];
            }
        } else {

            $response = $this->teachAiSystem($userId);

            $conversationId = $response['data']['conversation_id'];

            $conversation = Conversation::with('messages')->where('id', $conversationId)->first();

            $data[] = [
                'role' => 'user',
                'content' => 'my question: ' . $conversation->messages->where('type', 'question')->first()->content . ' and your answer: ' . $conversation->messages->where('type', 'answer')->first()->content,
            ];
        }

        $response = $this->chatCompletion($data);

        if (isset($response['choices'][0]['message']['content'])) {

            $message = $response['choices'][0]['message']['content'];
            return $message;
            $message = $this->cleanJsonResponse($message);

            $conversationId = Conversation::create([
                'user_id' => $userId,
            ])->id;

            $this->saveMessagesToDB($conversationId, 'question', $data);

            // $this->saveMessages($conversationId, 'answer', $message);

            $recordChat = ChatCompletion::create([
                'conversation_id' => $conversationId,
                'chat_id' => $response['id'],
                'object' => $response['object'],
                'created' => $response['created'],
                'model' => $response['model'],
                'system_fingerprint' => $response['system_fingerprint'],
                'prompt_tokens' => $response['usage']['prompt_tokens'],
                'completion_tokens' => $response['usage']['completion_tokens'],
                'total_tokens' => $response['usage']['total_tokens'],
                'reasoning_tokens' => $response['usage']['completion_tokens_details']['reasoning_tokens'],
                'accepted_prediction_tokens' => $response['usage']['completion_tokens_details']['accepted_prediction_tokens'],
                'rejected_prediction_tokens' => $response['usage']['completion_tokens_details']['rejected_prediction_tokens'],

            ]);

            return [
                'status' => 'success',
                'message' => 'Question answer successfully',
                'data' => $message,
            ];
        } else {
            $message = $response;

            return [
                'status' => 'error',
                'message' => 'Question failed. No content returned from OpenAI.',
                'data' => $message,
            ];
        }
    }


    /**
     * Teach the AI system
     * 
     * @param int $userId
     * 
     * @return array ['status', 'message', 'data' => ['conversation_id']]
     */
    public function teachAiSystem($userId)
    {
        $content = [
            [
                'role' => 'user',
                'content' => 'You are an assistant in a travel agency system. You will learn everything about this system and help users to get the information they need. You can ask for help if you need it.,
                our system is cather for business. It is b2b system. We have three types of users: company, branch and agent. Each user has different roles and permissions. The company is the main user and has the highest permissions. The branch is the second user and has the second highest permissions. The agent is the third user and has the lowest permissions.,
                The company can create branches and agents. The branch can create agents. The agent can create tasks. The company can see all the tasks created by the agents. The branch can see all the tasks created by the agents. The agent can see only the tasks created by him.,
                The company can see all the tasks created by the agents. The branch can see all the tasks created by the agents. The agent can see only the tasks created by him.,
                the main purpose of the system is to help the agents to create tasks and manage them. The task got from various suppliers. The task can be flight, hotel, car rental, etc. The agent can create a task and assign it to a client,
                Users then will create invoice for the task and send payment link to client to pay for the task'
            ],
            [
                'role' => 'user',
                'content' => 'Now you understand the system, users will ask you questions about the system. You need to answer them correctly and provide them with the information they need.',
            ]
        ];

        $response = $this->chatCompletion($content);

        if (isset($response['error'])) {
            return $response;
        }

        if (isset($response['choices'][0]['message']['content'])) {
            $message = $response['choices'][0]['message']['content'];

            $message = json_decode($message);

            if ($message !== 'action' && $message !== 'question') {
                return [
                    'status' => 'error',
                    'message' => 'Invalid response. Please provide a valid response: action or question',
                    'data' => $message
                ];
            }

            $conversationId = Conversation::create([
                'user_id' => $userId,
            ])->id;

            // $this->saveMessages($conversationId, 'question', $content);

            // $this->saveMessages($conversationId, 'answer', $message);

            return [
                'status' => 'success',
                'message' => 'Teaching the system successfully',
                'data' => [
                    'conversation_id' => $conversationId
                ]
            ];
        }
    }

    /**
     * @param int $conversationId
     * @param string $type of content
     * @param string $runId
     * @param string $messageId
     * @param array $tokens [prompt_tokens, completion_tokens, total_tokens, cache_tokens]
     * 
     * @return int $messageId
     */
    public function saveMessagesDB(int $conversationId, ?string $runId = null, string $messageId, string $type, array $tokens)
    {
        return Message::create([
            'conversation_id' => $conversationId,
            'run_id' => $runId,
            'message_id' => $messageId,
            'type' => $type,
            'prompt_tokens' => $tokens['prompt_tokens'] ?? null,
            'completion_tokens' => $tokens['completion_tokens'] ?? null,
            'total_tokens' => $tokens['total_tokens'] ?? null,
            'cache_tokens' => $tokens['prompt_token_details']['cached_tokens'] ?? null,
        ])->id;
    }

    public function updateMessageDB(int $id, array $columns)
    {
        Message::where('id', $id)->update($columns);
    }

    //TODO: Upload a file to OpenAi embeddings
    public function uploadFileToOpenAi(Request $request)
    {
        $file = $request->file('file');

        $url = config('services.open-ai.url') . '/files';
        $header = [
            'Authorization: Bearer ' . config('services.open-ai.key'),
            'Content-Type: application/json',
            'OpenAI-Beta: embeddings=v1',
        ];

        $data = [
            'file' => $file,
        ];

        $response = $this->postRequest($url, $header, $data);

        return response()->json($response);
    }

    public function getClient()
    {
        $user = auth()->user();

        if($user->role_id == Role::ADMIN){
            $client = Client::all();
        } else if ($user->role_id == Role::COMPANY) {
            $client = Client::with(['agent.branch' => function ($query) use ($user) {
                $query->where('company_id', $user->company_id);
            }])->get();
        } else if ($user->role_id == Role::BRANCH) {
            $client = Client::with(['agent' => function ($query) use ($user) {
                $query->where('branch_id', $user->branch_id);
            }])->get();
        } else {
            $client = Client::where('agent_id', $user->id)->get();
        }

        return $client->toArray();
    }

    public function embedding(string $query)
    {
        $url = config('services.open-ai.url') . '/embeddings';
        $header = [
            'Authorization: Bearer ' . config('services.open-ai.key'),
            'Content-Type: application/json'
        ];
        $data = [
            'model' => 'text-embedding-ada-002',
            'input' => $query,
            'encoding_format' => 'float',
        ];

        $response = $this->postRequest($url, $header, json_encode($data));

        if(isset($response['error'])){
            return [
                'status' => 'error',
                'message' => 'Failed to get embeddings',
                'data' => $response,
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Embeddings retrieved successfully',
            'data' => $response['data'],
        ];
    }

    private function callFunction($functionName, $arguments)
    {
        switch ($functionName) {
            case 'getPendingTasksCount':
                return [
                    'success' => $this->getPendingTaskCount($arguments['user_id']),
                ];
            case 'getClient':
                return [
                    'success' => $this->getClient()
                ];
            default:
                return ['error' => 'Function not implemented.'];
        }
    }

    public function submitToolOutputs(string $threadId, string $runId, array $toolOutputs)
    {
        $url = config('services.open-ai.url') . "/threads/{$threadId}/runs/{$runId}/submit_tool_outputs";
        $header = [
            'Authorization: Bearer ' . config('services.open-ai.key'),
            'Content-Type: application/json',
            'OpenAI-Beta: assistants=v2',
        ];


        $data = [
            'tool_outputs' => $toolOutputs,
        ];

        $response = $this->postRequest($url, $header, json_encode($data));
        
        logger('submit tool outputs response: ', $response);

        return [
            'status' => isset($response['id']) ? 'success' : 'error',
            'message' => isset($response['id']) ? 'Tool output submitted successfully' : 'Failed to submit tool output',
            'data' => $response,
        ];
    }

    public function getPendingTaskCount(int $userId)
    {
        $user = User::find($userId);

        if ($user->role_id == Role::ADMIN) {

            $tasks = Task::with('agent.branch', 'client', 'invoiceDetail.invoice')->get(); // Retrieve all tasks for admin

            $taskCount = $tasks->count(); // Task count for admin

        } elseif ($user->role_id == Role::COMPANY) {
            
            $agents = Agent::with(['branch'=> function ($query) use ($user) {
                $query->where('company_id', $user->company_id);
            }])->get();

            $clients = Client::whereIn('agent_id', $agents->pluck('id'))->get();

            // Get all agents for this company
            $agentIds = $agents->pluck('id'); // Get all agents for this company
            $tasks = Task::with('agent.branch', 'client','invoiceDetail.invoice')->whereIn('agent_id', $agentIds)->get(); // Retrieve tasks for the company’s agents
            $taskCount = $tasks->count();

        } elseif ($user->role_id == Role::AGENT) {
            $tasks = Task::where('agent_id', $user->id)->get(); // Retrieve tasks for this agent    
            $taskCount = $tasks->count();
        } 

        logger('task count: '.(string)$taskCount);
        return 'You have ' . (string)$taskCount . ' pending tasks';
    }

}
