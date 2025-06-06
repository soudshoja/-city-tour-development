<?php

namespace App\AI\Services;

use App\AI\Contracts\AIClientInterface;
use App\Enums\TaskType;
use App\Http\Traits\HttpRequestTrait;
use App\Models\Agent;
use App\Models\Airport;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\TaskFlightDetail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

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

    public function processWithAiTool(string $filePath, string $fileName) : array
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if ($extension === 'pdf') {
            $response = $this->extractPdfFiles($filePath);

            Log::info("extractPdfFiles response for {$fileName}: " . json_encode($response));

            if($response['status'] !== 'success') {
                $errorMessage = $response['message'] ?? 'Unknown error occurred.';
                Log::error("AI Tool processing failed for {$fileName}: " . $errorMessage);

                return [
                    'status' => 'error',
                    'message' => $errorMessage,
                    'original_filename' => $fileName,
                    'data' => null,
                ];
            }

            $data = $response['data'] ?? null;

            if(!$data) {
                Log::error("Failed to decode AI Tool response for {$fileName}: " . json_last_error_msg());
            
                return [
                    'status' => 'error',
                    'message' => 'Failed to decode AI Tool response',
                    'original_filename' => $fileName,
                    'data' => null,
                ];
            }

            Log::info('Extracting data from AI Tool for ' . $fileName . ': ' . json_encode($data));

            $task = $data['task'] ?? null;
            $taskFlightDetails = $data['task_flight_details'] ?? null;
            $taskHotelDetails = $data['task_hotel_details'] ?? null;

            if ($task['type'] === 'flight') {
                $processedData = [
                    'status' => 'success',
                    'message' => "Successfully processed {$fileName} using AI.",
                    'original_filename' => $fileName,
                    'data' => [
                        'additional_info' => $task['additional_info'] ?? 'N/A',
                        'ticket_number' => $task['ticket_number'] ?? 'N/A',
                        'status' => $task['status'] ?? 'N/A',
                        'reference' => $task['reference'] ?? 'N/A',
                        'gds_office_id' => $task['gds_office_id'] ?? 'N/A',
                        'type' => $task['type'] ?? 'N/A',
                        'agent_name' => $task['agent_name'] ?? 'N/A',
                        'agent_email' => $task['agent_email'] ?? 'N/A',
                        'agent_amadeus_id' => $task['agent_amadeus_id'] ?? 'N/A',
                        'client_name' => $task['client_name'] ?? 'N/A',
                        'supplier_name' => $task['supplier_name'] ?? 'N/A',
                        'supplier_country' => $task['supplier_country'] ?? 'N/A',
                        'cancellation_policy' => $task['cancellation_policy'] ?? null,
                        'venue' => $task['venue'] ?? null,
                        'price' => $task['price'] ?? null,
                        'exchange_currency' => $task['exchange_currency'] ?? null,
                        'original_price' => $task['original_price'] ?? null,
                        'original_currency' => $task['original_currency'] ?? null,
                        'total' => $task['total'] ?? null,
                        'surcharge' => $task['surcharge'] ?? null,
                        'tax' => $task['tax'] ?? null,
                        'taxes_record' => $task['taxes_record'] ?? 'N/A',
                        'penalty_fee' => $task['penalty_fee'] ?? 0.00,
                        'refund_charge' => $task['refund_charge'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                        'task_flight_details' => [
                            'farebase' => $taskFlightDetails['farebase'] ?? null,
                            'departure_time' => $taskFlightDetails['departure_time'] ?? null,
                            'departure_from' => $taskFlightDetails['departure_from'] ?? null,
                            'airport_from' => $taskFlightDetails['airport_from'] ?? 'N/A',
                            'terminal_from' => $taskFlightDetails['terminal_from'] ?? 'N/A',
                            'arrival_time' => $taskFlightDetails['arrival_time'] ?? null,
                            'duration_time' => $taskFlightDetails['duration_time'] ?? 'N/A',
                            'arrive_to' => $taskFlightDetails['arrive_to'] ?? 'N/A',
                            'airport_to' => $taskFlightDetails['airport_to'] ?? 'N/A',
                            'terminal_to' => $taskFlightDetails['terminal_to'] ?? 'N/A',
                            'airline_name' => $taskFlightDetails['airline_name'] ?? 'N/A',
                            'flight_number' => $taskFlightDetails['flight_number'] ?? 'N/A',
                            'class_type' => $taskFlightDetails['class_type'] ?? 'N/A',
                            'baggage_allowed' => $taskFlightDetails['baggage_allowed'] ?? 'N/A',
                            'equipment' => $taskFlightDetails['equipment'] ?? null,
                            'flight_meal' => $taskFlightDetails['flight_meal'] ?? null,
                            'seat_no' => $taskFlightDetails['seat_no'] ?? null,
                        ],
                        
                    ]
                ];
            } else if( $task['type'] === 'hotel'){
                $processedData = [
                    'status' => 'success',
                    'message' => "Successfully processed {$fileName} using AI.",
                    'original_filename' => $fileName,
                    'data' => [
                        'additional_info' => $task['additional_info'] ?? 'N/A',
                        'ticket_number' => $task['ticket_number'] ?? 'N/A',
                        'status' => $task['status'] ?? 'N/A',
                        'reference' => $task['reference'] ?? 'N/A',
                        'created_by' => $task['created_by'] ?? null,
                        'issued_by' => $task['issued_by'] ?? null,
                        'type' => $task['type'] ?? 'N/A',
                        'agent_name' => $task['agent_name'] ?? 'N/A',
                        'agent_email' => $task['agent_email'] ?? 'N/A',
                        'client_name' => $task['client_name'] ?? 'N/A',
                        'supplier_name' => $task['supplier_name'] ?? 'N/A',
                        'supplier_country' => $task['supplier_country'] ?? null,
                        'cancellation_policy' => $task['cancellation_policy'] ?? null,
                        'venue' => $task['venue'] ?? null,
                        'price' => $task['price'] ?? null,
                        'exchange_currency' => $task['exchange_currency'] ?? null,
                        'original_price' => $task['original_price'] ?? null,
                        'original_currency' => $task['original_currency'] ?? null,
                        'total' => $task['total'] ?? null,
                        'surcharge' => $task['surcharge'] ?? null,
                        'tax' => $task['tax'] ?? null,
                        'taxes_record' => $task['taxes_record'] ?? 'N/A',
                        'penalty_fee' => $task['penalty_fee'] ?? 0.00,
                        'refund_charge' => $task['refund_charge'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                        'task_hotel_details' => [
                            'hotel_name' => $taskHotelDetails['hotel_name'] ?? null,
                            'check_in_date' => $taskHotelDetails['check_in_date'] ?? null,
                            'check_out_date' => $taskHotelDetails['check_out_date'] ?? null,
                            'room_type' => $taskHotelDetails['room_type'] ?? null,
                            'number_of_rooms' => $taskHotelDetails['number_of_rooms'] ?? null,
                            'number_of_guests' => $taskHotelDetails['number_of_guests'] ?? null,
                            'meal_plan' => $taskHotelDetails['meal_plan'] ?? null,
                        ],
                    ]
                ];

            } else {
                Log::warning("Unsupported task type in {$fileName}: {$task['type']}");
                return [
                    'status' => 'error',
                    'message' => "Unsupported task type in {$fileName}: {$task['type']}",
                    'original_filename' => $fileName,
                    'data' => null,
                ];
            }

            return $processedData;

        } elseif (in_array($extension, ['txt', 'text', 'air'])) {

            $fileContent = File::get($filePath);

            try {
                $response = $this->extractAirFiles($fileContent);

                if (!isset($response['status']) || $response['status'] !== 'success') {
                    $errorMessage = $response['message'] ?? 'Unknown error occurred.';
                    Log::error("AI Tool processing failed for {$fileName}: " . $errorMessage);
                    return [
                        'status' => 'error',
                        'message' => $errorMessage,
                        'original_filename' => $fileName,
                        'data' => null,
                    ];
                }

                // Log::info("AI Tool processing response for {$fileName}: " . json_encode($response));

                $extractedData = $response['data'] ?? null;

                if (!$extractedData) {
                    Log::error("Failed to decode AI Tool response for {$fileName}: " . json_last_error_msg());
                    
                    return [
                        'status' => 'error',
                        'message' => 'Failed to decode AI Tool response',
                        'original_filename' => $fileName,
                        'data' => null,
                    ];
                }

                $processedData = [
                    'status' => 'success',
                    'message' => "Successfully processed {$fileName} using AI.",
                    'original_filename' => $fileName,
                    'data' => [
                        'additional_info' => $extractedData['additional_info'] ?? 'N/A',
                        'ticket_number' => $extractedData['ticket_number'] ?? 'N/A',
                        'status' => $extractedData['status'] ?? 'N/A',
                        'supplier_status' => $extractedData['status'] ?? 'N/A',
                        'reference' => $extractedData['reference'] ?? 'N/A',
                        'created_by' => $extractedData['created_by'] ?? null,
                        'issued_by' => $extractedData['issued_by'] ?? null,
                        'type' => $extractedData['type'] ?? 'N/A',
                        'agent_name' => $extractedData['agent_name'] ?? 'N/A',
                        'agent_email' => $extractedData['agent_email'] ?? 'N/A',
                        'agent_amadeus_id' => $extractedData['agent_amadeus_id'] ?? 'N/A',
                        'client_name' => $extractedData['client_name'] ?? 'N/A',
                        'supplier_name' => $extractedData['supplier_name'] ?? 'N/A',
                        'supplier_country' => $extractedData['supplier_country'] ?? 'N/A',
                        'cancellation_policy' => $extractedData['cancellation_policy'] ?? 'N/A',
                        'venue' => $extractedData['venue'] ?? 'N/A',
                        'task_flight_details' => [
                            'farebase' => $extractedData['task_flight_details']['farebase'] ?? null,
                            'departure_time' => $extractedData['task_flight_details']['departure_time'] ?? null,
                            'departure_from' => $extractedData['task_flight_details']['departure_from'] ?? null,
                            'airport_from' => $extractedData['task_flight_details']['airport_from'] ?? 'N/A',
                            'terminal_from' => $extractedData['task_flight_details']['terminal_from'] ?? 'N/A',
                            'arrival_time' => $extractedData['task_flight_details']['arrival_time'] ?? null,
                            'duration_time' => $extractedData['task_flight_details']['duration_time'] ?? 'N/A',
                            'arrive_to' => $extractedData['task_flight_details']['arrive_to'] ?? 'N/A',
                            'airport_to' => $extractedData['task_flight_details']['airport_to'] ?? 'N/A',
                            'terminal_to' => $extractedData['task_flight_details']['terminal_to'] ?? 'N/A',
                            'airline_name' => $extractedData['task_flight_details']['airline_name'] ?? 'N/A',
                            'flight_number' => $extractedData['task_flight_details']['flight_number'] ?? 'N/A',
                            'class_type' => $extractedData['task_flight_details']['class_type'] ?? 'N/A',
                            'baggage_allowed' => $extractedData['task_flight_details']['baggage_allowed'] ?? 'N/A',
                            'equipment' => $extractedData['task_flight_details']['equipment'] ?? 'N/A',
                            'flight_meal' => $extractedData['task_flight_details']['flight_meal'] ?? 'N/A',
                            'seat_no' => $extractedData['task_flight_details']['seat_no'] ?? 'N/A',
                            'ticket_number' => $extractedData['task_flight_details']['ticket_number'] ?? 'N/A',
                        ],
                        'price' => $extractedData['price'] ?? null,
                        'exchange_currency' => $extractedData['exchange_currency'] ?? null,
                        'original_price' => $extractedData['original_price'] ?? null,
                        'original_currency' => $extractedData['original_currency'] ?? null,
                        'total' => $extractedData['total'] ?? null,
                        'surcharge' => $extractedData['surcharge'] ?? null,
                        'tax' => $extractedData['tax'] ?? null,
                        'taxes_record' => $extractedData['taxes_record'] ?? 'N/A',
                        'penalty_fee' => $extractedData['penalty_fee'] ?? 0.00,
                        'refund_charge' => $extractedData['refund_charge'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                ];

                return $processedData;
            } catch (\Exception $e) {
                Log::error("Exception occurred while processing {$fileName}: " . $e->getMessage(), [
                    'trace' => $e->getTraceAsString()
                ]);
                
                return [
                    'status' => 'error',
                    'message' => "Exception occurred while processing {$fileName}: " . $e->getMessage(),
                    'original_filename' => $fileName,
                    'data' => null,
                ];
            }
        } else {
            Log::warning("Unsupported file type for {$fileName}: {$extension}");
            
            return [
                'status' => 'error',
                'message' => "Unsupported file type for {$fileName}: {$extension}",
                'original_filename' => $fileName,
                'data' => null,
            ];
        }
    }

    public function extractAirFiles(string $content): array
    {
        $supplierList = json_encode(Supplier::all()->toArray());

        $airportList = json_encode(Airport::all()->toArray());

        $taskTypes = Task::where('type', [TaskType::hotel, TaskType::flight])->get();

        $agentAmadeusIdList = Agent::limit(10)->pluck('amadeus_id');

        $exampleGdsId = [
            'KWIKT2619',
            'KWIKT2843',
            'KWIKT2844',
        ];

        $companiesGdsId = Company::whereNotNull('gds_office_id')
            ->pluck('gds_office_id')
            ->toArray();

        $branchesGdsId = Branch::whereNotNull('gds_office_id')
            ->pluck('gds_office_id')
            ->toArray();
        
        $gdsOfficeIdList = array_merge($companiesGdsId, $branchesGdsId);

        $gdsOfficeIdList = array_merge($gdsOfficeIdList, $exampleGdsId);

        $gdsOfficeIdList = json_encode($gdsOfficeIdList);


        $prompt = "
        You are an assistant for processing uploaded files to extract structured data for a task management system. The system has two models:
        
        1. `tasks` model with the following fields:
            - `additional_info`: Include summarized, relevant details from the airfile in fewer than 10 words, ensuring all information directly corresponds to the airfile's content.
            - `ticket_number`: Ticket number. 
            - `status`: Current status of the task. It can be: 'refund' (if the file contains refund indicator such as `RF`). Make sure to set the status to 'refund' if you detect `RF` keyword. Other status are 'issued', 'reissued' or 'void'. Whatever filet hat has 'confirmed' as it's status, use 'issued' status to store into database, if the files has 'FO' and original ticket number, set the status to 'reissued'
            - `refund_date`: Date of refund if applicable.
            - `price`: Price of the task in float type. You may found files with different currency, but the air file already provide the exchange price beside the original price, so just use the exchanged price as the price. usually our default currency is KWD, so if the file has KWD as the currency, you can just use the price as is. If the file has different currency, you can use the exchanged price, which is usually stated in the file like 'EGP5197.00    ;KWD32.000' or 'USD 100.00 ; KWD 30.000'. In this case, you can just use the exchanged price, which is the next or first value after the semicolon, so in this case, you can just use '30.000' as the price.
            - 'exchange_currency': Currency used after exchange, if the file has different currency, you can use the exchanged currency, which is usually stated in the file like 'EGP5197.00    ;KWD32.000' or 'USD 100.00 ; KWD 30.000'. In this case, you can just use 'KWD' as the exchange currency.
            - `original_price`: Original price of the task before exchange currency, if the file has different currency, you can use the original price, which is usually stated in the file like 'EGP5197.00    ;KWD32.000' or 'USD 100.00 ; KWD 30.000'. In this case, you can just use '32.000' as the original price. if this field is not available, you can set it to null.
            - `original_currency`: Original currency of the task before exchange currency, if the file has different currency, you can use the original currency, which is usually stated in the file like 'EGP5197.00    ;KWD32.000' or 'USD 100.00 ; KWD 30.000'. In this case, you can just use 'EGP' or 'USD' as the original currency. if this field is not available, you can set it to null.
            - `total`: Total amount for the task in float type. This is usually more than the price, because it includes the price, tax and any other fees. You don't need to calculate the total, just use the total amount stated in the file, which is usually stated at then end of line where the price is stated. if you see the total amount is same as the price, it usually means that there is no tax or any other fees.
            - `surcharge`: Any surcharge applied in float type.
            - `penalty_fee`: Penalty fee if applicable especially for reissued tickets.
            - `tax`: Total tax amount in float type.
            - `taxes_record`: Parsed from the long line starting with KRF. All tax codes with their respective amounts are extracted.
            - `refund_charge`: Total tax amount of YQ, YR, YX and other which non-refundable in float type. make sure to result in only one value of float type.
            - `reference`: Reference code for the task. take the ticket number from the file, which is usually stated at the end of the line where the price is stated. The ticket number is usually 10 digits long, and it is usually preceded by a 3-digit airline code, so you can just take the last 10 digits as the ticket number.
            - `created_by`: GDS office ID, this indicates who created the task. Usually on line before line A , and to know who created the task, it is the first GDS office ID in the line
            - `issued_by`: GDS office ID, this indicates who issued/pay the task. Usually on line before line A , and to know who issued the task, it is the last GDS office ID in the line/ or line after it. (still before line A), this is the example of real gds office id: $gdsOfficeIdList
            - `type`: Type of task. You can refer the type from this list: $taskTypes. You may always set the type to 'flight' if it airfile. 
            - `agent_name`: name of the agent handling the task.
            - `agent_email`: email of the agent handling the task.
            - `agent_amadeus_id`: Amadeus ID of the agent handling the task. its located on C line of the file. The character often have 6 characters, 4 digit with 2 letters, like '1234AB'. However, the list of characters usually have 2 extra letters at the end, like '1234ABAS'. The last 2 letters are referring to the role of the agent (AS refer to agent, SU refer to the supplier), so you can just remove the last 2 letters and keep the first 6 characters. for example, if the agent amadeus id is '1234ABAS', you can just set it to '1234AB'. 
            This is example list of amadeus id: $agentAmadeusIdList
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
            'exchange_currency': 'KWD',
            'original_price': 100.00,
            'original_currency': 'USD',
            'total': 115.00,
            'surcharge': 10.00,
            'tax': 5.00,
            'taxes_record': 'KRF:7.500,CJ:7.600,F6:1.000,GZ:2.000,KW:5.000,N4:10.650,RN:9.900,VV:80.300,YQ:0.250,YX:0.900',
            'penalty_fee': '10.00',
            'refund_charge': '0.250+0.900',
            'reference': 'ticket_number',
            'created_by': 'KWIKT2619', //example of gds office id
            'issued_by': 'KWIKT2844', //example of gds office id
            'type': 'flight',
            'agent_name': 'agent name',
            'agent_email': 'agent email',
            'agent_amadeus_id': 'agent amadeus id',
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

        if (!isset($response['choices'][0]['message']['content'])) {
            return [
                'status' => 'error',
                'message' => 'Failed to extract data from the response',
                'data' => null,
            ];
        }
        $message = $response['choices'][0]['message']['content'];
        $decodedResponse = json_decode($message, true);

        $checkResponse = $this->checkReferenceNumber($decodedResponse['reference'] ?? '');

        while ($checkResponse['status'] === 'error') {
            Log::warning('Invalid reference number detected: ' . $decodedResponse['reference']);
            $decodedResponse = $this->getReferenceNumberFromFile([
                'content' => $content,
                'example' => $checkResponse['data']['example'] ?? [],
            ])['data'];
            $checkResponse = $this->checkReferenceNumber($decodedResponse['reference'] ?? '');
        }

        return [
            'status' => 'success',
            'message' => 'Data extracted successfully',
            'data' => $decodedResponse,
        ];
    }


    public function getReferenceNumberFromFile($data = [])
    {
        $prompt = " You extract the reference number which is the ticket number from the file, which is usually stated at the end of the line where the price is stated. The ticket number is usually 10 digits long, and it is usually preceded by a 3-digit airline code, so you can just take the last 10 digits as the ticket number.";

        if(isset($data['example'])){
            $prompt .= " Here are some example reference numbers: " . implode(', ', $data['example']);
        }

        $response = $this->chatCompletionJsonResponse([
            [
                'role' => 'user',
                'content' => $prompt,
            ],
            [
                'role' => 'user',
                'content' => $data['content'] ?? '',
            ],
        ]);

        if (!isset($response['choices'][0]['message']['content'])) {
            return [
                'status' => 'error',
                'message' => 'Failed to extract reference number from the response',
                'data' => null,
            ];
        }
        $message = $response['choices'][0]['message']['content'];
        $decodedResponse = json_decode($message, true);

        return [
            'status' => 'success',
            'message' => 'Reference number extracted successfully',
            'data' => $decodedResponse,
        ];
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
                'agent_amadeus_id' => 'agent_amadeus_id',
                'client_name' => 'client_name',
                'reference' => 'ticket_number',
                'gds_office_id' => 'gds_office_id',
                'duration' => 'duration',
                'payment_type' => 'payment_type',
                'price' => 0,
                'exchange_currency' => 'KWD',
                'original_price' => 0,
                'original_currency' => 'USD',
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
                'enabled' => false,
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

        $supplierList = Supplier::all()->pluck('name')->toArray();
        $supplierList = json_encode($supplierList);
  

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
                'text' => 'For the supplier name, you can refer to the supplier from this list: ' . $supplierList,
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

    public function checkReferenceNumber(string $referenceNumber): array
    {
        // This method can be implemented to check if the reference number return by OpenAI is valid.
    
        $exampleReferenceNumbers = [
            '1234567890', 
            '9876543210',
            '2833133212',
            '4567891234',
            '1122334455',
        ];

        if (!preg_match('/^\d{10}$/', $referenceNumber)) {
            Log::error('Invalid reference number format: ' . $referenceNumber);
            return [
                'status' => 'error',
                'message' => 'Invalid reference number format. It should be a 10-digit number.',
                'data' => [
                    'example' => $exampleReferenceNumbers
                ]
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Reference number format is valid.',
            'data' => [
                'reference_number' => $referenceNumber,
            ],
        ];
    }
}