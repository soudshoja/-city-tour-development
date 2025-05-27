<?php

namespace App\AI\Services;

use App\AI\Contracts\AIClientInterface;
use App\Enums\TaskType;
use App\Http\Traits\HttpRequestTrait;
use App\Models\Airport;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\TaskFlightDetail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;

class OpenAIClient implements AIClientInterface
{
    use HttpRequestTrait;

    protected string $apiUrl;
    protected string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->apiUrl = config('services.open-ai.url');
        $this->apiKey = config('services.open-ai.key');
        $this->model = config('services.open-ai.model');
    }

    public function chat(array $messages): array
    {
        $url = $this->apiUrl . '/chat/completions';
        $response = Http::withToken($this->apiKey)
        ->withoutVerifying()
        ->post($url, [
            'model' => $this->model,
            'messages' => $messages,
        ]);

        // Check if the API call failed
        if ($response->failed()) {
            throw new \Exception('OpenAI API request failed: ' . $response->body());
        }

        return $response->json();
    }

    public function chatCompletionJsonResponse(array $message)
    {
        // Log::info('OpenAI config: ', [
        //     'api_url' => $this->apiUrl,
        //     'api_key' => $this->apiKey,
        //     'model' => $this->model,
        // ]);

        $url = $this->apiUrl . '/chat/completions';
        $header = [
            'Authorization: Bearer ' . config('services.open-ai.key'),
            'Content-Type: application/json',
        ];

        array_push($message, [
            'role' => 'user',
            'content' => 'Please respond with JSON format',
        ]);

        $data = [
            'model' => config('services.open-ai.model'),
            'messages' => $message,
            'response_format' => [
                'type' => 'json_object',
            ]
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($url, $data);

        Log::info('OpenAI API response: ', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        if ($response->failed()) {
            return [
                'status' => 'error',
                'message' => 'Failed to get response from OpenAI API',
            ];
        }

        $response = json_decode($response->body(), true);

        logger('chat completion response: ', $response);
        return $response;
    }

    public function createResponse(array $content)
    {
        // logger('content: ', $content);

        $input = [
            $content
        ];

        logger('input: ', $input);

        $response = Http::timeout(120)->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($this->apiUrl . '/responses', [
            'model' => $this->model,
            'input' => $input,
            'text' => [
                'format' => [
                    'type' => 'json_object',
                ]
            ]
        ]);

        logger('create response: ', $response->json());

        if ($response->failed()) {
            return [
                'status' => 'error',
                'message' => 'Failed to get response from OpenAI API',
            ];
        }

        $response = json_decode($response->body(), true);

        return $response;

    }

    public function extractAirFiles(string $content): array
    {
        $supplierList = json_encode(Supplier::all()->toArray());

        $airportList = json_encode(Airport::all()->toArray());

        $taskTypes = Task::where('type', [TaskType::hotel, TaskType::flight])->get();

        $prompt = "
        You are an assistant for processing uploaded files to extract structured data for a task management system. The system has two models:
        
        1. `tasks` model with the following fields:
            - `additional_info`: Include summarized, relevant details from the airfile in fewer than 10 words, ensuring all information directly corresponds to the airfile's content.
            - `ticket_number`: Ticket number. 
            - `status`: Current status of the task. It can be: 'refund' (if the file contains refund indicator such as `RF`). Make sure to set the status to 'refund' if you detect `RF` keyword. Other status are 'issued', 'reissued' or 'void'. Whatever filet hat has 'confirmed' as it's status, use 'issued' status to store into database, if the files has 'FO' and original ticket number, set the status to 'reissued'
            - `refund_date`: Date of refund if applicable.
            - `price`: Price of the task in float type.
            - `surcharge`: Any surcharge applied in float type.
            - `penalty_fee`: Penalty fee if applicable especially for reissued tickets.
            - `tax`: Total tax amount in float type.
            - `taxes_record`: Parsed from the long line starting with KRF. All tax codes with their respective amounts are extracted.
            - `refund_charge`: Total tax amount of YQ, YR, YX and other which non-refundable in float type. make sure to result in only one value of float type.
            - `reference`: Reference code for the task. use the full gds pnr code from the file.
            - `gds_office_id`: GDS office ID, if available.
            - `type`: Type of task. You can refer the type from this list: $taskTypes. You may always set the type to 'flight' if it airfile. 
            - `agent_name`: name of the agent handling the task.
            - `agent_email`: email of the agent handling the task.
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
            - `duration_time`: Duration of the flight in `XhYm` format (e.g., `2h5m`, `1h 45m`, `3h`). Do not return `HH:MM:SS` or timestamps. Only return readable duration in hours and minutes like `2h 5m`.
            - `arrive_to`: Location of arrival, it must be a country. If the information retrieve is a city, state or any other than country, you must set it to suitable country.
            - `airport_to`: Airport code or name for arrival.
            - `terminal_to`: Arrival terminal.
            - `airline_name`: Airline name. 
            - `flight_number`: Flight number.
            - `class_type`: Class type of the flight.
            - `baggage_allowed`: Baggage allowance.
            - `equipment`: Equipment used in the flight.
            - `ticket_number`: flight ticket number. 
            - `flight_meal`: Meal options during the flight.
            - `seat_no`: Seat number.
        
        CHEAT SHEET:
        - MUC1A [GDS_PNR+Ref];[Session];[GDS_PCC];[AgentNo];[GDS_PCC];[AgentNo]... ; [CouponCount] ; ... ; [AirlineCode] [AirlinePNR]
        
        Extract relevant data from the uploaded content in JSON format, matching the structure of these models. Only include fields with available data, and omit any null or empty fields.
        if some of the fields are not available, you can set them to null.
        
        all related time should be in the format of 'Y-m-d H:i:s'

        Analyze the uploaded file to locate and extract relevant fields.
        If the file type is (.air) but the structure doesn't match the reference example, reject the file.
        If the uploaded file type is (.air), set it to amedeus as per supplier's list that i gave you,
        then bind the data to the `tasks` and `task_flight_details` models in JSON format. 
        
        The venue field is populated using the airport_to field from the file, which contains codes like 'DXB'. 
        These codes are matched against $airportList and the corresponding location data from the list is used to update the venue field.
        
        this is the content: $content

        only pass me the data extracted in JSON format.

        example answer = 
        {
            'additional_info': 'additional info',
            'ticket_number': '3580878589', //[3-digit airline code] - [10-digit ticket number] (only save the 10-digit ticket number),
            'status': 'completed'/ 'hold' / 'confirmed',
            'price': 100.00,
            'surcharge': 10.00,
            'tax': 5.00,
            'taxes_record': 'KRF:7.500,CJ:7.600,F6:1.000,GZ:2.000,KW:5.000,N4:10.650,RN:9.900,VV:80.300,YQ:0.250,YX:0.900',
            'penalty_fee': '10.00',
            'refund_charge': '0.250+0.900',
            'reference': 'ticket_number',
            'gds_office_id': 'gds_office_id',
            'type': 'flight',
            'agent_name': 'agent name',
            'agent_email': 'agent email',
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
                'duration_time': '2h 5m',
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
                'ticket_number': 'K381-3580878589', //example of ticket flight number with the airline code
            }
        }

        ";

        $response = $this->chatCompletionJsonResponse([
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

            return [
                'status' => 'success',
                'message' => 'Data extracted successfully',
                'data' => $decodedResponse,
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Failed to extract data from the response',
                'data' => null,
            ];
        }
        
    }

    public function extractPdfFiles(string $fileId) : array
    {
        $uploadFileResponseId = $this->uploadFileToOpenAI($fileId);

        $fileId = $uploadFileResponseId;

        $taskModel = [
            'task' => [
                'company_name' => 'company_name or agency name',
                'type' => 'flight or hotel',
                'status' => 'status',
                'agent_name' => 'agent_name',
                'agent_email' => 'agent_email',
                'client_name' => 'client_name',
                'reference' => 'reference',
                'gds_office_id' => 'gds_office_id',
                'duration' => 'duration',
                'payment_type' => 'payment_type',
                'price' => 0,
                'tax' => 0,
                'surcharge' => 0,
                'penalty_fee' => 0,
                'total' => 0,
                'cancellation_policy' => '',
                'additional_info' => '',
                'venue' => '',
                'invoice_price' => 0,
                'voucher_status' => '',
                'refund_date' => '',
                'enabled' => true,
                'taxes_record' => '',
                'refund_charge' => 0,
                'ticket_number' => '3580878589',
            ],
            'task_hotel_details' => [
                'hotel_name' => 'JW Marriott Hotel',
                'booking_time' => '2024-10-12 14:00:00',
                'check_in' => '2024-10-16',
                'check_out' => '2024-10-20',
                'room_reference' => 'JW123456',
                'room_number' => '123',
                'room_type' => 'Deluxe Room',
                'room_amount' => 1,
                'room_details' => '2 adults, 1 child',
                'room_promotion' => '10% off', 
                'rate' => 0,
                'meal_type' => 'Breakfast included',
                'is_refundable' => true,
                'supplements' => 'Extra bed available',
            ],
            'task_flight_details' => [
                'farebase' => '20.00',
                'departure_time' => '2024-10-16 14:00:00',
                'country_from' => 'Kuwait',
                'airport_from' => 'KWI',
                'terminal_from' => '1',
                'arrival_time' => '2024-10-16 16:00:00',
                'duration_time' => '2h 5m',
                'country_to' => 'Singapore',
                'airport_to' => 'SIN',
                'terminal_to' => '1',
                'airline_name' => 'Kuwait Airways',
                'flight_number' => 'KU-123',
                'ticket_number' => '3580878589',
                'class_type' => 'economy',
                'baggage_allowed' => '2 pieces',
                'equipment' => 'equipment',
                'flight_meal' => 'chicken',
                'seat_no' => '12A',
            ],
        ];
  

        $content = [
            [
                'type' => 'input_file',
                'file_id' => $fileId,
            ],
            [
                'type' => 'input_text',
                'text' => 'Please extract the data from this file and return it in JSON format.',
            ],
                        [
                'type' => 'input_text',
                'text' => 'Try to get information following my models, if the task is a flight, you can use the task_flight_details model, if the task is a hotel, you can use the task_hotel_details model. If the task is a flight and hotel, you can use both models.',
            ],
            [
                'type' => 'input_text',
                'text' => 'Please make sure to use the same field names as in the models.',
            ],
            [
                'type' => 'input_text',
                'text' => json_encode($taskModel),
            ]
            
        ];

        $response = $this->createResponse(
            [
                'role' => 'user',
                'content' => $content,
            ]
        );

        Log::info('OpenAI API response: ', $response);


        if (
            isset($response['output'][0]['content'][0]['text']) &&
            is_string($response['output'][0]['content'][0]['text'])
        ) {
            $message = $response['output'][0]['content'][0]['text'];
            $decodedResponse = json_decode($message, true);
        } else {
            return [
                'status' => 'error',
                'message' => 'Failed to extract data from the response',
                'data' => null,
            ];
        }

        $this->deleteFileFromOpenAI($fileId);

        return [
            'status' => 'success',
            'message' => 'Data extracted successfully',
            'data' => $decodedResponse,
        ];

    } 

    public function uploadFileToOpenAI($file, string $purpose = 'user_data')
    {
        // Accepts either UploadedFile or file path
        $fileResource = $file instanceof UploadedFile ? fopen($file->getRealPath(), 'r') : fopen($file, 'r');

        $response = Http::withToken($this->apiKey)
            ->attach('file', $fileResource, is_string($file) ? basename($file) : $file->getClientOriginalName())
            ->post($this->apiUrl . '/files', [
                'purpose' => $purpose,
            ]);
        
        logger('upload file response: ', $response->json());

        fclose($fileResource);

        if ($response->failed()) {
            throw new \Exception('Error uploading file: ' . $response->body());
        }

        return $response->json('id'); // Return file_id
    }


    public function deleteFileFromOpenAI($fileId)
    {
        $response = Http::withToken($this->apiKey)
            ->delete($this->apiUrl . '/files/' . $fileId);

        logger('delete file response: ', $response->json());

        // if ($response->failed()) {
        //     throw new \Exception('Error deleting file: ' . $response->body());
        // }

        return;
    }
}