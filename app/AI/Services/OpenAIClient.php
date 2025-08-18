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
use App\Schema\TaskSchema;
use App\Schema\TaskFlightSchema;
use App\Schema\TaskHotelSchema;
use App\Schema\TaskInsuranceSchema;
use Exception;
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

    public function extractPassportData($file, string $fileName): array
    {
        try {
            // Upload the file to OpenAI and get the file ID
            $fileId = $this->uploadFileToOpenAI($file);

            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if (!in_array($extension, ['jpg', 'jpeg', 'png', 'pdf'])) {
                return [
                    'status' => 'error',
                    'message' => 'Unsupported file type. Only JPG, JPEG, PNG, and PDF files are supported.',
                    'data' => null
                ];
            }

            // Prepare the prompt for OpenAI API
            $prompt = "You are an assistant for a travel agency. Your task is to extract passport details from the provided image or document. 

            Analyze the document carefully and extract the following fields. Return the data in JSON format only:

            - `passport_no`: Passport number or Passport No.
            - `civil_no`: Civil number or Civil No. (if available)
            - `name`: Full name as per the passport
            - `first_name`: First name
            - `middle_name`: Middle name (if available)
            - `last_name`: Last name (if available)
            - `nationality`: Nationality
            - `date_of_birth`: Date of birth in YYYY-MM-DD format
            - `date_of_issue`: Date of issue in YYYY-MM-DD format
            - `date_of_expiry`: Date of expiry in YYYY-MM-DD format
            - `place_of_birth`: Place of birth
            - `place_of_issue`: Place of issue

            Important guidelines:
            1. If a field is not found or not clearly visible, set its value to null
            2. Ensure all dates are in YYYY-MM-DD format
            3. Extract the full name exactly as it appears on the passport
            4. For passport number, include only the alphanumeric passport number without any prefixes
            5. Return only valid JSON format without any additional text or explanations";

            $messages = [
                [
                    'role' => 'system',
                    'content' => $prompt
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => $extension === 'pdf' ? 'input_file' : 'input_image',
                            'file_id' => $fileId
                        ],
                        [
                            'type' => 'input_text',
                            'text' => 'Please extract the passport information from this document and return it in the specified JSON format.'
                        ]
                    ]
                ]
            ];

            $response = $this->createResponse($messages);

            if ($response['status'] === 'error') {
                return $response; 
            }

            $extractedContent = $response['output'][0]['content'][0]['text'] ?? null;

            if (!$extractedContent) {
                return [
                    'status' => 'error',
                    'message' => 'No passport data found or invalid response format',
                    'data' => null
                ];
            }

            $passportData = json_decode($extractedContent, true);

            $this->deleteFileFromOpenAI($fileId);

            return [
                'status' => 'success',
                'message' => 'Passport data extracted successfully',
                'data' => $passportData
            ];
        } catch (\Exception $e) {
            Log::error('Exception in extractPassportData: ' . $e->getMessage());

            return [
                'status' => 'error',
                'message' => 'Exception occurred during passport extraction: ' . $e->getMessage(),
                'data' => null
            ];
        }
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

        $response = Http::timeout(120)->withHeaders([
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

        if (isset($content[0]) && is_array($content[0])) {
            $input = $content;
        } else {
            $input = [$content];
        }

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

    public function processWithAiTool(string $filePath, string $fileName): array
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Helper to normalize data structure for all scenarios

        if ($extension === 'pdf') {
            $response = $this->extractPdfFiles($filePath);

            Log::info("extractPdfFiles response for {$fileName}: " . json_encode($response));

            if ($response['status'] !== 'success') {
                $errorMessage = $response['message'] ?? 'Unknown error occurred.';
                return [
                    'status' => 'error',
                    'message' => $errorMessage,
                    'original_filename' => $fileName,
                    'data' => null,
                ];
            }

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

            // Handle both single task and multiple tasks
            if (!is_array($extractedData)) {
                Log::error("AI Tool response for {$fileName} is not an array: " . json_last_error_msg());
                return [
                    'status' => 'error',
                    'message' => 'AI Tool response is not an array',
                    'original_filename' => $fileName,
                    'data' => null,
                ];
            }

            // Normalize all items (similar to air files processing)
            $processedItems = [];
            foreach ($extractedData as $item) {
                $normalized = TaskSchema::normalize(array_merge(
                    $item ?? [],
                    [
                        'task_flight_details' => $item['task_flight_details'] ?? [],
                        'task_hotel_details' => $item['task_hotel_details'] ?? [],
                        'task_insurance_details' => $item['task_insurance_details'] ?? [],
                    ]
                ));
                $processedItems[] = $normalized;
            }

            Log::info('Extracting data from AI Tool for ' . $fileName . ': ' . json_encode($processedItems));

            return [
                'status' => 'success',
                'message' => "Successfully processed {$fileName} using AI.",
                'original_filename' => $fileName,
                'data' => $processedItems,
            ];
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

                if (!is_array($extractedData)) {
                    Log::error("AI Tool response for {$fileName} is not an array: " . json_last_error_msg());
                    return [
                        'status' => 'error',
                        'message' => 'AI Tool response is not an array',
                        'original_filename' => $fileName,
                        'data' => null,
                    ];
                }

                // Normalize all items
                $processedItems = [];
                foreach ($extractedData as $item) {
                    // $type = $item['type'] ?? 'flight';
                    $processedItems[] = TaskSchema::normalize($item);
                }

                return [
                    'status' => 'success',
                    'message' => "Successfully processed {$fileName} using AI.",
                    'original_filename' => $fileName,
                    'data' => $processedItems,
                ];
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
        $airportList = json_encode(Airport::all()->toArray());

        $taskFields = TaskSchema::getSchema();
        $flightFields = TaskFlightSchema::getSchema();
        $hotelFields = TaskHotelSchema::getSchema();

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


        $prompt = "You are an assistant for processing uploaded files to extract structured data for a task management system.\n\n";
        $prompt .= "1. `tasks` model with the following fields:\n";
        foreach ($taskFields as $field => $meta) {
            $prompt .= "   - `$field`: {$meta['desc']}\n";
        }
        $prompt .= "\n2. `task_flight_details` model (for flights) - THIS IS AN ARRAY that can contain multiple flight details:\n";
        foreach ($flightFields as $field => $meta) {
            $prompt .= "   - `$field`: {$meta['description']}\n";
        }
        $prompt .= "\n3. `task_hotel_details` model (for hotels) - THIS IS AN ARRAY that can contain multiple hotel details:\n";
        foreach ($hotelFields as $field => $meta) {
            $prompt .= "   - `$field`: {$meta['description']}\n";
        }
        $prompt .= "CHEAT SHEET:
        - MUC1A [GDS_PNR+Ref];[Session];[GDS_PCC];[AgentNo];[GDS_PCC];[AgentNo]... ; [CouponCount] ; ... ; [AirlineCode] [AirlinePNR]
        
        Extract relevant data from the uploaded content in JSON format, matching the structure of these models. Only include fields with available data, and omit any null or empty fields.
        if some of the fields are not available, you can set them to null.
        
        all related time should be in the format of 'Y-m-d H:i:s'

        Analyze the uploaded file to locate and extract relevant fields.
        If the file type is (.air) but the structure doesn't match the reference example, reject the file.
        If the uploaded file type is (.air), set it to amedeus as per supplier's list that i gave you,
        then bind the data to the `tasks` and `task_flight_details` models in JSON format. 
        
        IMPORTANT: task_flight_details and task_hotel_details are ARRAYS that can contain multiple flight/hotel segments.
        For multi-segment trips (connecting flights, round trips), include all segments in the task_flight_details array.
        
        The venue field is populated using the airport_to field from the file, which contains codes like 'DXB'. 
        These codes are matched against $airportList and the corresponding location data from the list is used to update the venue field.

        sometimes the files have multiple passenger and ticket numbers, so because of that, you have to return the array of the objects, each object should have the same structure as the example below, but with different values for each passenger and ticket number. as for the price of the ticket, the air file will return one set of price if the ticket have the same price, so you can just use the same price for all passengers, but if the ticket have different price for each passenger, the air file will return different value of price.
        
        it doesn't matter if the file has multiple passenger and ticket numbers or not, you need to return it as an array of objects.

        this is the content: $content

        only pass me the data extracted in JSON format.

        example answer = 
        'result' : [
            {
                'additional_info': 'additional info',
                'ticket_number': '3580878589', //[3-digit airline code] - [10-digit ticket number] (only save the 10-digit ticket number),
                'status': 'completed'/ 'hold' / 'confirmed',
                'price': 100.00,
                'exchange_currency': 'KWD',
                'original_price': 100.00,
                'original_currency': 'USD',
                'total': 115.00,
                'original_surcharge': 10.00,
                'surcharge': 10.00,
                'original_tax': 5.00,
                'tax': 5.00,
                'taxes_record': 'KRF:7.500,CJ:7.600,F6:1.000,GZ:2.000,KW:5.000,N4:10.650,RN:9.900,VV:80.300,YQ:0.250,YX:0.900',
                'penalty_fee': '10.00',
                'refund_charge': '0.250+0.900',
                'reference': 'ticket_number',
                'gds_reference' => '8D46RD',
                'amadeus_reference' => 'KUIXNO',
                'created_by' => 'KWIKT2619', //example of gds office id
                'issued_by' => 'KWIKT2844', //example of gds office id
                'type': 'flight',
                'agent_name': 'agent name',
                'agent_email': 'agent email',
                'agent_amadeus_id': 'agent amadeus id',
                'client_name': 'client name',
                'supplier_name': 'Amadeus',
                'supplier_country': 'Kuwait',
                'cancellation_policy': 'cancellation policy',
                'cancellation_deadline': '2025-06-01 10:00:00', //example of cancellation deadline, if not available, set it to null
                'venue': 'venue',
                'task_flight_details': [
                    {
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
                        'ticket_number': '3580878589', //example of ticket flight number with the airline code, 10-digit ticket number only
                    }
                ]
            }
        ]

        example for two or more passengers:
        [
            {
                'additional_info': 'additional info',
                'ticket_number': '3580878589', //[3-digit airline code] - [10-digit ticket number] (only save the 10-digit ticket number),
                'status': 'completed'/ 'hold' / 'confirmed',
                'price': 100.00,
                'exchange_currency': 'KWD',
                'original_price': 100.00,
                'original_currency': 'USD',
                'total': 115.00,
                'original_surcharge': 10.00,
                'surcharge': 10.00,
                'original_tax': 5.00,
                'tax': 5.00,
                'taxes_record': 'KRF:7.500,CJ:7.600,F6:1.000,GZ:2.000,KW:5.000,N4:10.650,RN:9.900,VV:80.300,YQ:0.250,YX:0.900',
                'penalty_fee': '10.00',
                'refund_charge': '0.250+0.900',
                'reference': 'ticket_number',
                'gds_reference' => 'KFD5TW',
                'amadeus_reference' => 'KFD5TW',
                'created_by' => 'KWIKT2619', //example of gds office id
                'issued_by' => 'KWIKT2844', //example of gds office id
                'type' => 'flight',
                'agent_name' => 'agent name',
                'agent_email' => 'agent email',
                'agent_amadeus_id' => 'agent amadeus id',
                'client_name' => 'client name',
                'supplier_name' => 'Amadeus',
                'supplier_country' => 'Kuwait',
                'cancellation_daeadline' => '2025-06-01 10:00:00', //example of cancellation deadline, if not available, set it to null
                // if the cancellation policy is not available, you can set it to null
                // if the venue is not available, you can set it to null
                // if the flight details are not available, you can set it to null, if it is available , make sure it is same data with the first object as it is the same flight, just different passenger.
            }
        ]

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

        foreach ($decodedResponse['result'] as $task) {

            if (!isset($task['reference']) || empty($task['reference'])) {

                $checkResponse = $this->getReferenceNumberFromFile([
                    'content' => $content,
                    'passenger_name' => $task['client_name'] ?? '',
                    'example' => $task['reference'] ?? [],
                ]);
                if ($checkResponse['status'] === 'error') {
                    return [
                        'status' => 'error',
                        'message' => $checkResponse['message'],
                        'data' => null,
                    ];
                }
                $task['reference'] = $checkResponse['data']['reference_number'];
            }

            $checkResponse = $this->checkReferenceNumber($task['reference'] ?? '');

            while ($checkResponse['status'] === 'error') {
                Log::warning('Invalid reference number detected: ' . $task['reference']);

                $getReferenceResponse = $this->getReferenceNumberFromFile([
                    'content' => $content,
                    'passenger_name' => $task['client_name'] ?? '',
                    'example' => $task['reference'] ?? [],
                ]);

                if ($getReferenceResponse['status'] === 'error') {
                    return [
                        'status' => 'error',
                        'message' => $getReferenceResponse['message'],
                        'data' => null,
                    ];
                }

                $checkResponse = $this->checkReferenceNumber($getReferenceResponse['data']['reference_number'] ?? '');
            }

            $task['reference'] = $checkResponse['data']['reference_number'];
        }

        return [
            'status' => 'success',
            'message' => 'Data extracted successfully',
            'data' => $decodedResponse['result'],
        ];
    }


    public function getReferenceNumberFromFile($data = [])
    {
        if (!isset($data['passenger_name']) || empty($data['passenger_name'])) {

            return [
                'status' => 'error',
                'message' => 'Passenger name is required to extract reference number',
                'data' => null,
            ];
        }

        if (!isset($data['content']) || empty($data['content'])) {
            return [
                'status' => 'error',
                'message' => 'Content is required to extract reference number',
                'data' => null,
            ];
        }

        $prompt = " You extract the reference number which is the ticket number from the file, which is usually stated at the end of the line where the price is stated. The ticket number is usually 10 digits long, and it is usually preceded by a 3-digit airline code, so you can just take the last 10 digits as the ticket number.";

        $prompt .= " The reference number is usually like this: T-K229-2833133219, and it is usually preceded by a 3-digit airline code, so you can just take the last 10 digits as the ticket number. For example, if the ticket number is T-K229-2833133219, you can just use '2833133219' as the ticket number.";

        $prompt .= "If there is multiple reference numbers/ticket numbers, make sure you return for the correct passenger/client, i want for this passenger/client: " . $data['passenger_name'] . ". ";

        $prompt .= "example response : {\"reference_number\": \"2833133219\"}";

        if (isset($data['example'])) {
            $prompt .= " Here are some example reference numbers you can refer to: " . json_encode($data['example']) . ". ";
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

    public function extractPdfFiles(string $fileId): array
    {
        $uploadFileResponseId = $this->uploadFileToOpenAI($fileId);

        $fileId = $uploadFileResponseId;

        $taskFields = TaskSchema::getSchema();
        $flightFields = TaskFlightSchema::getSchema();
        $hotelFields = TaskHotelSchema::getSchema();
        $insuranceFields = TaskInsuranceSchema::getSchema();

        $suppliers = Supplier::all();

        $supplierList =$suppliers->pluck('name')->toArray();
   
        $supplierList = json_encode($supplierList);

        $airportList = json_encode(Airport::all()->toArray());

        // Build comprehensive prompt for PDF extraction
        $prompt = "You are an assistant for processing uploaded PDF documents to extract structured travel booking data.\n\n";
        $prompt .= "Extract data following these models:\n\n";
        $prompt .= "1. `tasks` model with the following fields:\n";
        foreach ($taskFields as $field => $meta) {
            $prompt .= "   - `$field`: {$meta['desc']}\n";
        }
        $prompt .= "\n2. `task_flight_details` model (for flights) - THIS IS AN ARRAY that can contain multiple flight details:\n";
        foreach ($flightFields as $field => $meta) {
            $prompt .= "   - `$field`: {$meta['description']}\n";
        }
        $prompt .= "\n3. `task_hotel_details` model (for hotels) - THIS IS AN ARRAY that can contain multiple hotel details:\n";
        foreach ($hotelFields as $field => $meta) {
            $prompt .= "   - `$field`: {$meta['description']}\n";
        }
        $prompt .= "\n4. `task_insurance_details` model (for insurances) - THIS IS AN ARRAY that can contain multiple insurance details:\n";
        foreach ($insuranceFields as $field => $meta) {
            $prompt .= "   - `$field`: {$meta['description']}\n";
        }
        $prompt .= "\nINSURANCE TASK COLLAPSING RULE (CRITICAL):\n";
        $prompt .= "- Do NOT create additional tasks for spouse/children/relatives listed on the certificate.\n";
        $prompt .= "- Do NOT record or output any list of covered relatives/members. Ignore extra names.\n";
        $prompt .= "- Set client_name to the buyer/policyholder (name nearest to the policy header or explicitly labeled).\n";
        $prompt .= "- If currency symbols (e.g., KD, $, €) are found in the files, replace them with the proper ISO currency code (e.g., KWD, USD, EUR).\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (FIRST TAKAFUL INSURANCE): If the supplier or insurer is 'First Takaful' (case-insensitive), set issued_by to 'First Takaful' and agent_name to null.\n";

        $prompt .= "\nIMPORTANT INSTRUCTIONS:\n";
        $prompt .= "- The PDF may contain multiple passengers/bookings. Return an array of task objects.\n";
        $prompt .= "- Each passenger should be a separate task object with their own ticket/booking details.\n";
        $prompt .= "- If multiple passengers share the same flight/booking, they may have the same flight details but different ticket numbers and passenger names.\n";
        $prompt .= "- task_flight_details and task_hotel_details are ARRAYS that can contain multiple flight/hotel segments for each task.\n";
        $prompt .= "- For INSURANCE: follow the INSURANCE TASK COLLAPSING RULE (do NOT create a task per covered person).\n";
        $prompt .= "- Extract all available data, set missing fields to null.\n";
        $prompt .= "- All dates should be in 'Y-m-d H:i:s' format.\n";
        $prompt .= "- For supplier name, refer to this list: $supplierList\n";
        $prompt .= "- Airport codes should be matched against: $airportList\n";
        $prompt .= "- HOTEL MEAL/BOARD RULES:\n";
        $prompt .= "  • If the document mentions a meal plan (e.g., 'board', 'free breakfast', 'half board', 'full board'), copy the wording exactly as shown into task_hotel_details[*].meal_type.\n";
        $prompt .= "  • If you're unsure which room line it belongs to, include the phrase in tasks.additional_info instead.\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (Como Travels):\n";
        $prompt .= "  • Create ONE task per ROOM (never per passenger, never one combined task for all rooms). Always set tasks.issued_by and tasks.created_by to Como Travels.\n";
        $prompt .= "  • For each ROOM task: set tasks.total and tasks.price to that room’s TOTAL (sell) across its nights only.\n";
        $prompt .= "  • If there is ONLY ONE room in the booking, then set tasks.total and tasks.price to the booking 'Sell Price' only.\n";
        $prompt .= "  • Example (2 rooms R1 and R2): produce exactly 2 tasks; task(R1) uses R1’s nightly 'Total (sell)' sum; task(R2) uses R2’s nightly 'Total (sell)' sum.\n";
        $prompt .= "  • STATUS: Read the value labeled 'Reservation status' in the document.\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (SMILE HOLIDAYS):\n";
        $prompt .= "  • For Smile Holidays proforma/invoices that have a 'Pax' column, copy that value into tasks.additional_info, e.g., 'Pax: 1'.\n";
        $prompt .= "  • ADDITIONAL REQUESTS → ROOM DETAILS: If the document contains 'Additional Requests', 'Special Instructions', 'Remarks' or similar booking notes, append a concise version to task_hotel_details[*].room_details (for single-room bookings append to that room; for multi-room bookings, either repeat for each room or put it into tasks.additional_info with room labels).\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (BAHRAIN E-VISA):\n";
        $prompt .= "  • Set tasks.reference to the Visa Number from the document.\n";
        $prompt .= "  • Store the Application Number and other important visa details (e.g., Visa Expiry, Period of Stay, Number of Entries) in tasks.additional_info.\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (TBO CAR):\n";
        $prompt .= "  • If the file shows 'Net Amount' and 'Agent Markup': set price and total with Net Amount exactly as showen (ignore markup value).\n";
        $prompt .= "  • Put the Agent Markup value in tasks.additional_info (e.g., 'Agent Markup: KWD 12.00').\n";
        $prompt .= "  • If Net Amount is shown in another currency (e.g., 'KWD 209.45 (USD 685.25)'), store that other-currency value (e.g., USD 685.25) in original_price/original_currency.\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (FLY DUBAI):\n";
        $prompt .= "  • Set tasks.issued_by and tasks.created_by to the first invoice name from the document. Set agent to null if the agent in the document is not in the agent list.\n";
        $prompt .= "  • Set tasks.original_price with the Base fare, tasks.price with the Fare total, and tasks.total with the Booking total from the document.\n";
        $prompt .= "  • If the document contains multiple passengers, always use the Booking total as the basis and divide it equally among all passengers to compute each passenger’s price. Do NOT assign the full total to each passenger.\n";
        $prompt .= "  • Place all other monetary details (e.g., Optional extras, Transaction fee, Admin fees, Taxes/fees, etc.) into tasks.additional_info.\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (World of Luxury):\n";
        $prompt .= "  • Create one task per room. Set task.total and task.price to the booking Grand Total.\n";
        $prompt .= "  • Set reference to the Inv.Nr; set issued_by and created_by to the Tour Operator; set agent and issued_date to null.\n";
        $prompt .= "  • Populate task_hotel_details with Hotel, Room, Type, Board, Nights, Check-in, Check-out, and the segment total.\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (Cebu Pacific):\n";
        $prompt .= "  • Set reference = Booking Reference No. and issued_date = Booking Date. Set agent, created_by and issued_by to null.\n";
        $prompt .= "  • Set task.original_price to the per-passenger share of 'Amount in Booking Currency' (total ÷ passenger_count). Set task.price and task.total to the same amount after conversion using exchange_rate.\n";
        $prompt .= "  • Store fee breakdown: set surcharge = Admin Fee + Fuel Surcharge; set tax = sum of VATs + passenger/service/security charges; penalty_fee = 0 unless stated.\n";
        $prompt .= "  • Copy all labeled amounts into additional_info as 'Label: Amount' pairs (e.g., Base Fare, Administrative Fee, Fuel Surcharge, VAT for Admin Fees, and so on).\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (Cham Wings Airlines and Air Arabia):\n";
        $prompt .= "  • Set tasks.ticket_number = full E-Ticket Number exactly as shown (e.g. 3862304374206/1). Set issued_by and created_by to Como Travels.\n";
        $prompt .= "  • Set tasks.reference = last 10 digits of the E-Ticket Number, before the slash (e.g. 3862304374206/1 → 2304374206).\n";
        $prompt .= "  • For every non-KWD amount (Fare/Charges/Taxes/etc.), append to additional_info exactly as 'Label: CUR 999.99' (e.g., 'Fare: AED 278.17'); keep the document’s grand original in original_price/original_tax/original_currency.\n";
        $prompt .= "  • Map the itinerary column 'Charges' to tax only; do not populate surcharge unless the document explicitly has a 'Surcharge' line.\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (Travel Collection and Heysam Group):\n";
        $prompt .= "  • Create ONE task per ROOM (NEVER per passenger). If the file has N rooms, output N tasks; if it has 1 room, output 1 task.\n";
        $prompt .= "  • totals: set price and total to the booking GRAND TOTAL shown on the invoice (e.g., EUR 375.00). set issued_date to print date.\n";
        $prompt .= "  • Set reference to the Inv.Nr; set issued_by and created_by to the Tour Operator name only (without country); set agent to null.\n";
        $prompt .= "  • Populate task_hotel_details with Hotel, Room, Type, Board, Nights, Check-in, Check-out, and the segment total.\n";

        $prompt .= "- SUPPLIER-SPECIFIC HINTS (NDC SUPPLIERS): If the supplier has 'NDC' in its name (case-insensitive), set created_by to exactly match issued_by.\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (EMIRATES NDC): Set issued_by to the agency/office name that appears immediately next to the 'IATA:' number.\n";

        $prompt .= "- Return the result in this JSON format:\n\n";

        $prompt .= "{\n";
        $prompt .= "  \"result\": [\n";
        $prompt .= "    {\n";
        $prompt .= "      \"additional_info\": \"relevant booking info\",\n";
        $prompt .= "      \"ticket_number\": \"document/ticket number\",\n";
        $prompt .= "      \"gds_reference\": \"booking reference/PNR\",\n";
        $prompt .= "      \"airline_reference\": \"airline confirmation code\",\n";
        $prompt .= "      \"status\": \"issued/confirmed/cancelled/refunded\",\n";
        $prompt .= "      \"supplier_status\": \"same as status\",\n";
        $prompt .= "      \"refund_date\": \"2025-06-01 10:00:00\",\n";
        $prompt .= "      \"price\": 100.00,\n";
        $prompt .= "      \"exchange_currency\": \"KWD\",\n";
        $prompt .= "      \"original_price\": 100.00,\n";
        $prompt .= "      \"original_currency\": \"USD\",\n";
        $prompt .= "      \"total\": 115.00,\n";
        $prompt .= "      \"original_surcharge\": 10.00,\n";
        $prompt .= "      \"surcharge\": 10.00,\n";
        $prompt .= "      \"penalty_fee\": 0.00,\n";
        $prompt .= "      \"original_tax\": 5.00,\n";
        $prompt .= "      \"tax\": 5.00,\n";
        $prompt .= "      \"taxes_record\": \"tax breakdown if available\",\n";
        $prompt .= "      \"refund_charge\": 0.00,\n";
        $prompt .= "      \"reference\": \"main reference number\",\n";
        $prompt .= "      \"created_by\": \"agent/office code\",\n";
        $prompt .= "      \"issued_by\": \"issuing agent/office\",\n";
        $prompt .= "      \"type\": \"flight/hotel/package\",\n";
        $prompt .= "      \"agent_name\": \"agent name\",\n";
        $prompt .= "      \"agent_email\": \"agent email\",\n";
        $prompt .= "      \"agent_amadeus_id\": \"agent system id\",\n";
        $prompt .= "      \"client_name\": \"passenger/customer name\",\n";
        $prompt .= "      \"supplier_name\": \"supplier/vendor name\",\n";
        $prompt .= "      \"supplier_country\": \"supplier country\",\n";
        $prompt .= "      \"cancellation_policy\": \"cancellation terms\",\n";
        $prompt .= "      \"cancellation_deadline\": \"2025-06-01 10:00:00\",\n";
        $prompt .= "      \"venue\": \"service location\",\n";
        $prompt .= "      \"issued_date\": \"2025-07-03 00:00:00\",\n";
        $prompt .= "      \"is_exchanged\": false,\n";
        $prompt .= "      \"task_flight_details\": [\n";
        $prompt .= "        {\n";
        $prompt .= "          \"farebase\": 20.00,\n";
        $prompt .= "          \"departure_time\": \"2025-07-03 14:00:00\",\n";
        $prompt .= "          \"country_id_from\": \"departure country\",\n";
        $prompt .= "          \"airport_from\": \"departure airport code\",\n";
        $prompt .= "          \"terminal_from\": \"departure terminal\",\n";
        $prompt .= "          \"arrival_time\": \"2025-07-03 16:00:00\",\n";
        $prompt .= "          \"duration_time\": \"2h 30m\",\n";
        $prompt .= "          \"country_id_to\": \"arrival country\",\n";
        $prompt .= "          \"airport_to\": \"arrival airport code\",\n";
        $prompt .= "          \"terminal_to\": \"arrival terminal\",\n";
        $prompt .= "          \"airline_id\": \"airline name\",\n";
        $prompt .= "          \"flight_number\": \"flight number\",\n";
        $prompt .= "          \"class_type\": \"economy/business/first\",\n";
        $prompt .= "          \"baggage_allowed\": \"baggage allowance\",\n";
        $prompt .= "          \"equipment\": \"aircraft type\",\n";
        $prompt .= "          \"ticket_number\": \"flight ticket number\",\n";
        $prompt .= "          \"flight_meal\": \"meal service\",\n";
        $prompt .= "          \"seat_no\": \"seat assignment\"\n";
        $prompt .= "        }\n";
        $prompt .= "      ],\n";
        $prompt .= "      \"task_hotel_details\": [\n";
        $prompt .= "        {\n";
        $prompt .= "          \"hotel_name\": \"hotel name\",\n";
        $prompt .= "          \"booking_time\": \"2025-07-03 10:00:00\",\n";
        $prompt .= "          \"check_in\": \"2025-07-03 15:00:00\",\n";
        $prompt .= "          \"check_out\": \"2025-07-05 11:00:00\",\n";
        $prompt .= "          \"room_reference\": \"room booking reference\",\n";
        $prompt .= "          \"room_number\": \"room number\",\n";
        $prompt .= "          \"room_type\": \"room type\",\n";
        $prompt .= "          \"room_amount\": 150.00,\n";
        $prompt .= "          \"room_details\": \"room details and amenities\",\n";
        $prompt .= "          \"room_promotion\": \"special offers or discounts\",\n";
        $prompt .= "          \"rate\": 150.00,\n";
        $prompt .= "          \"meal_type\": \"breakfast/half-board/full-board\",\n";
        $prompt .= "          \"is_refundable\": true,\n";
        $prompt .= "          \"supplements\": \"additional services\"\n";
        $prompt .= "        }\n";
        $prompt .= "      ],\n";
        $prompt .= "        \"task_insurance_details\": [\n";
        $prompt .= "          {\n";
        $prompt .= "            \"insurance_type\": \"Tr\",\n";
        $prompt .= "            \"destination\": \"Worldwide\",\n";
        $prompt .= "            \"plan_type\": \"Family Plan\",\n";
        $prompt .= "            \"duration\": \"Up to 30 days\",\n";
        $prompt .= "            \"package\": \"Worldwide (Silver) Plan\",\n";
        $prompt .= "            \"document_reference\": \"policy/certificate reference\",\n";
        $prompt .= "            \"date\": \"2025\",\n"; 
        $prompt .= "            \"paid_leaves\": 0,\n";
        $prompt .= "          }\n";
        $prompt .= "        ]\n";
        $prompt .= "    }\n";
        $prompt .= "  ]\n";
        $prompt .= "}\n\n";
        $prompt .= "Remember: Always return an array of objects, even for single passengers. Analyze the document carefully for multiple bookings/passengers.";

        $content = [
            [
                'type' => 'input_file',
                'file_id' => $fileId,
            ],
            [
                'type' => 'input_text',
                'text' => $prompt,
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

        // Ensure the response has the expected structure
        if (!isset($decodedResponse['result']) || !is_array($decodedResponse['result'])) {
            // If the response doesn't have 'result' key, try to wrap the response
            if (is_array($decodedResponse)) {
                // Check if it's already an array of tasks
                $firstItem = reset($decodedResponse);
                if (is_array($firstItem) && (isset($firstItem['ticket_number']) || isset($firstItem['client_name']))) {
                    $decodedResponse = ['result' => $decodedResponse];
                } else {
                    // Single task object, wrap it in an array
                    $decodedResponse = ['result' => [$decodedResponse]];
                }
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Invalid response format from AI',
                    'data' => null,
                ];
            }
        }

        // Process each task for reference number validation (similar to extractAirFiles)
        foreach ($decodedResponse['result'] as &$task) {
            // Validate and fix reference numbers if needed
            if (!isset($task['reference']) || empty($task['reference'])) {
                // Use ticket_number as reference if reference is missing
                $task['reference'] = $task['ticket_number'] ?? '';
            }

            // Basic reference number validation for PDFs (less strict than air files)
            if (!empty($task['reference'])) {
                $checkResponse = $this->checkReferenceNumber($task['reference']);
                if ($checkResponse['status'] === 'error') {
                    // For PDFs, if reference doesn't match air file format, keep it as is
                    Log::warning('PDF reference number format differs from air file format: ' . $task['reference']);
                }
            }
        }

        return [
            'status' => 'success',
            'message' => 'Data extracted successfully',
            'data' => $decodedResponse['result'],
        ];
    } 

    /**
     * Process multiple files of different types in batch - PDFs, text files, etc.
     * 
     * @param array $files Array of file information with paths and names
     * @return array Array containing results for each file
     */
    public function processBatchFiles(array $files): array
    {
        if (empty($files)) {
            return [
                'status' => 'error',
                'message' => 'No files provided for batch processing',
                'data' => []
            ];
        }

        $fileContents = [];
        $fileIds = [];
        $fileMetadata = [];

        // Process each file based on its type
        foreach ($files as $index => $fileInfo) {
            $filePath = $fileInfo['path'];
            $fileName = $fileInfo['name'];
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            try {
                if ($extension === 'pdf') {
                    // For PDFs, upload to OpenAI and get file ID
                    $fileId = $this->uploadFileToOpenAI($filePath);
                    $fileIds[$fileName] = $fileId;
                    $fileMetadata[$fileName] = [
                        'type' => 'pdf',
                        'file_id' => $fileId,
                        'index' => $index
                    ];
                } elseif (in_array($extension, ['txt', 'text', 'air'])) {
                    // For text files, read content directly
                    $content = File::get($filePath);
                    $fileContents[$fileName] = $content;
                    $fileMetadata[$fileName] = [
                        'type' => 'text',
                        'content' => $content,
                        'index' => $index
                    ];
                } else {
                    Log::warning("Unsupported file type for batch processing: {$fileName} ({$extension})");
                    $fileMetadata[$fileName] = [
                        'type' => 'unsupported',
                        'error' => "Unsupported file type: {$extension}",
                        'index' => $index
                    ];
                }
            } catch (\Exception $e) {
                Log::error("Failed to process file {$fileName}: " . $e->getMessage());
                $fileMetadata[$fileName] = [
                    'type' => 'error',
                    'error' => $e->getMessage(),
                    'index' => $index
                ];
            }
        }

        // Build the batch processing prompt
        $taskFields = TaskSchema::getSchema();
        $flightFields = TaskFlightSchema::getSchema();
        $hotelFields = TaskHotelSchema::getSchema();
        $insuranceFields = TaskInsuranceSchema::getSchema();
        $supplierList = Supplier::all()->pluck('name')->toArray();
        $airportList = Airport::all()->toArray();

        $prompt = $this->buildBatchProcessingPrompt($taskFields, $flightFields, $hotelFields, $insuranceFields, $supplierList, $airportList, $fileMetadata);

        // Build content array for the API call
        $content = [];
        
        // Add PDF files
        foreach ($fileIds as $fileName => $fileId) {
            $content[] = [
                'type' => 'input_file',
                'file_id' => $fileId,
            ];
        }
        
        // Add text content with file names
        foreach ($fileContents as $fileName => $textContent) {
            $content[] = [
                'type' => 'input_text',
                'text' => "=== FILE: {$fileName} ===\n{$textContent}\n=== END FILE: {$fileName} ===\n\n",
            ];
        }
        
        // Add the main prompt
        $content[] = [
            'type' => 'input_text',
            'text' => $prompt,
        ];

        try {
            $response = $this->createResponse([
                'role' => 'user',
                'content' => $content,
            ]);

            Log::info('OpenAI Batch Processing API response: ', $response);

            if (
                isset($response['output'][0]['content'][0]['text']) &&
                is_string($response['output'][0]['content'][0]['text'])
            ) {
                $message = $response['output'][0]['content'][0]['text'];
                $decodedResponse = json_decode($message, true);
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Failed to extract data from the batch response',
                    'data' => []
                ];
            }

            // Clean up - delete uploaded PDF files
            foreach ($fileIds as $fileName => $fileId) {
                $this->deleteFileFromOpenAI($fileId);
            }

            // Ensure the response has the expected structure
            if (!isset($decodedResponse['files']) || !is_array($decodedResponse['files'])) {
                return [
                    'status' => 'error',
                    'message' => 'Invalid batch response format from AI',
                    'data' => []
                ];
            }

            // Process and validate each file's results
            $processedResults = [];
            foreach ($fileMetadata as $fileName => $metadata) {
                if ($metadata['type'] === 'unsupported' || $metadata['type'] === 'error') {
                    $processedResults[$fileName] = [
                        'status' => 'error',
                        'message' => $metadata['error'],
                        'data' => []
                    ];
                    continue;
                }

                if (!isset($decodedResponse['files'][$fileName])) {
                    $processedResults[$fileName] = [
                        'status' => 'error',
                        'message' => "No results found for file: $fileName",
                        'data' => []
                    ];
                    continue;
                }

                $fileResults = $decodedResponse['files'][$fileName];
                
                // Ensure it's an array
                if (!is_array($fileResults)) {
                    $fileResults = [$fileResults];
                }

                // Process each task for reference number validation and normalization
                foreach ($fileResults as &$task) {
                    // Validate and fix reference numbers if needed
                    if (!isset($task['reference']) || empty($task['reference'])) {
                        // Use ticket_number as reference if reference is missing
                        $task['reference'] = $task['ticket_number'] ?? '';
                    }

                    // Reference number validation
                    if (!empty($task['reference'])) {
                        $checkResponse = $this->checkReferenceNumber($task['reference']);
                        if ($checkResponse['status'] === 'error') {
                            // For non-air files, if reference doesn't match format, keep it as is
                            Log::warning('Reference number format differs from expected format: ' . $task['reference']);
                        }
                    }

                    // Normalize the task data
                    $task = TaskSchema::normalize($task);
                }

                $processedResults[$fileName] = [
                    'status' => 'success',
                    'message' => 'Data extracted successfully',
                    'data' => $fileResults
                ];
            }

            return [
                'status' => 'success',
                'message' => 'Batch processing completed',
                'data' => $processedResults
            ];

        } catch (\Exception $e) {
            // Clean up files even if processing failed
            foreach ($fileIds as $fileName => $fileId) {
                try {
                    $this->deleteFileFromOpenAI($fileId);
                } catch (\Exception $deleteException) {
                    Log::warning("Failed to delete file $fileId during error cleanup: " . $deleteException->getMessage());
                }
            }

            Log::error('Batch processing failed: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Batch processing failed: ' . $e->getMessage(),
                'data' => []
            ];
        }
    }

    /**
     * Build comprehensive prompt for batch processing multiple file types
     */
    private function buildBatchProcessingPrompt($taskFields, $flightFields, $hotelFields, $insuranceFields, $supplierList, $airportList, $fileMetadata): string
    {
        $supplierListJson = json_encode($supplierList);
        $airportListJson = json_encode($airportList);

        $prompt = "You are an assistant for processing multiple uploaded files of different types to extract structured travel booking data.\n\n";
        
        $prompt .= "BATCH PROCESSING INSTRUCTIONS:\n";
        $prompt .= "- I am providing multiple files for batch processing (PDFs, text files, AIR files, etc.)\n";
        $prompt .= "- Each file may contain multiple passengers/bookings. Extract all data from each file.\n";
        $prompt .= "- IMPORTANT: Look for correlations and relationships between files - they may be related bookings, refunds, reissues, or amendments.\n";
        $prompt .= "- Cross-reference data between files to ensure consistency and identify connections.\n";
        $prompt .= "- Return a structured response with results grouped by filename.\n\n";

        $prompt .= "FILE TYPES BEING PROCESSED:\n";
        foreach ($fileMetadata as $fileName => $metadata) {
            if ($metadata['type'] === 'pdf') {
                $prompt .= "- {$fileName}: PDF document (uploaded as file)\n";
            } elseif ($metadata['type'] === 'text') {
                $prompt .= "- {$fileName}: Text/AIR file (content provided inline)\n";
            }
        }
        $prompt .= "\n";

        $prompt .= "Extract data following these models:\n\n";
        $prompt .= "1. `tasks` model with the following fields:\n";
        foreach ($taskFields as $field => $meta) {
            $prompt .= "   - `$field`: {$meta['desc']}\n";
        }
        $prompt .= "\n2. `task_flight_details` model (for flights) - THIS IS AN ARRAY that can contain multiple flight details:\n";
        foreach ($flightFields as $field => $meta) {
            $prompt .= "   - `$field`: {$meta['description']}\n";
        }
        $prompt .= "\n3. `task_hotel_details` model (for hotels) - THIS IS AN ARRAY that can contain multiple hotel details:\n";
        foreach ($hotelFields as $field => $meta) {
            $prompt .= "   - `$field`: {$meta['description']}\n";
        }
        $prompt .= "\n4. `task_insurance_details` model (for insurances) - THIS IS AN ARRAY that can contain multiple insurance details:\n";
        foreach ($insuranceFields as $field => $meta) {
            $prompt .= "   - `$field`: {$meta['description']}\n";
        }
        $prompt .= "\nINSURANCE TASK COLLAPSING RULE (CRITICAL):\n";
        $prompt .= "- Do NOT create additional tasks for spouse/children/relatives listed on the certificate.\n";
        $prompt .= "- Do NOT record or output any list of covered relatives/members. Ignore extra names.\n";
        $prompt .= "- Set client_name to the buyer/policyholder (name nearest to the policy header or explicitly labeled).\n";
        $prompt .= "- If currency symbols (e.g., KD, $, €) are found in the files, replace them with the proper ISO currency code (e.g., KWD, USD, EUR).\n";

        $prompt .= "\nSPECIAL PROCESSING RULES:\n";
        $prompt .= "- For AIR files (.air, .txt): Set supplier_name to 'Amadeus' and extract structured data according to AIR format\n";
        $prompt .= "- For PDF files: Extract booking information, tickets, itineraries, etc.\n";
        $prompt .= "- Each passenger should be a separate task object with their own ticket/booking details\n";
        $prompt .= "- If multiple passengers share the same flight/booking, they may have the same flight details but different ticket numbers and passenger names\n";
        $prompt .= "- task_flight_details and task_hotel_details are ARRAYS that can contain multiple flight/hotel segments for each task\n";
        $prompt .= "- For INSURANCE: follow the INSURANCE TASK COLLAPSING RULE (do NOT create a task per covered person).\n";
        $prompt .= "- For multi-segment trips (connecting flights, multiple hotels), include all segments in the respective arrays\n";
        $prompt .= "- Extract all available data, set missing fields to null\n";
        $prompt .= "- All dates should be in 'Y-m-d H:i:s' format\n";
        $prompt .= "- For supplier name, refer to this list: $supplierListJson\n";
        $prompt .= "- Airport codes should be matched against: $airportListJson\n\n";

        $prompt .= "CROSS-FILE CORRELATION:\n";
        $prompt .= "- Look for matching reference numbers, PNRs, or ticket numbers across files\n";
        $prompt .= "- Identify if files represent related transactions (original booking + refund, reissue, amendment, etc.)\n";
        $prompt .= "- If files are related, note the relationship in the additional_info field\n";
        $prompt .= "- For non-issued statuses (refund, void, reissue), try to link to original bookings using reference numbers\n\n";

        $prompt .= "Return the result in this JSON format:\n\n";
        $prompt .= "{\n";
        $prompt .= "  \"files\": {\n";
        foreach ($fileMetadata as $fileName => $metadata) {
            if ($metadata['type'] !== 'unsupported' && $metadata['type'] !== 'error') {
                $prompt .= "    \"$fileName\": [\n";
                $prompt .= "      {\n";
                $prompt .= "        \"additional_info\": \"relevant booking info + any cross-file correlations\",\n";
                $prompt .= "        \"ticket_number\": \"document/ticket number\",\n";
                $prompt .= "        \"gds_reference\": \"booking reference/PNR\",\n";
                $prompt .= "        \"airline_reference\": \"airline confirmation code\",\n";
                $prompt .= "        \"status\": \"issued/confirmed/cancelled/refunded/void/reissue\",\n";
                $prompt .= "        \"supplier_status\": \"same as status\",\n";
                $prompt .= "        \"refund_date\": \"2025-06-01 10:00:00\",\n";
                $prompt .= "        \"price\": 100.00,\n";
                $prompt .= "        \"exchange_currency\": \"KWD\",\n";
                $prompt .= "        \"original_price\": 100.00,\n";
                $prompt .= "        \"original_currency\": \"USD\",\n";
                $prompt .= "        \"total\": 115.00,\n";
                $prompt .= "        \"original_surcharge\": 10.00,\n";
                $prompt .= "        \"surcharge\": 10.00,\n";
                $prompt .= "        \"penalty_fee\": 0.00,\n";
                $prompt .= "        \"original_tax\": 5.00,\n";
                $prompt .= "        \"tax\": 5.00,\n";
                $prompt .= "        \"taxes_record\": \"tax breakdown if available\",\n";
                $prompt .= "        \"refund_charge\": 0.00,\n";
                $prompt .= "        \"reference\": \"main reference number\",\n";
                $prompt .= "        \"created_by\": \"agent/office code\",\n";
                $prompt .= "        \"issued_by\": \"issuing agent/office\",\n";
                $prompt .= "        \"type\": \"flight/hotel/package\",\n";
                $prompt .= "        \"agent_name\": \"agent name\",\n";
                $prompt .= "        \"agent_email\": \"agent email\",\n";
                $prompt .= "        \"agent_amadeus_id\": \"agent system id\",\n";
                $prompt .= "        \"client_name\": \"passenger/customer name\",\n";
                $prompt .= "        \"supplier_name\": \"supplier/vendor name\",\n";
                $prompt .= "        \"supplier_country\": \"supplier country\",\n";
                $prompt .= "        \"cancellation_policy\": \"cancellation terms\",\n";
                $prompt .= "        \"cancellation_deadline\": \"2025-06-01 10:00:00\",\n";
                $prompt .= "        \"venue\": \"service location\",\n";
                $prompt .= "        \"issued_date\": \"2025-07-04 00:00:00\",\n";
                $prompt .= "        \"task_flight_details\": [\n";
                $prompt .= "          {\n";
                $prompt .= "            \"farebase\": 20.00,\n";
                $prompt .= "            \"departure_time\": \"2025-07-04 14:00:00\",\n";
                $prompt .= "            \"country_id_from\": \"departure country\",\n";
                $prompt .= "            \"airport_from\": \"departure airport code\",\n";
                $prompt .= "            \"terminal_from\": \"departure terminal\",\n";
                $prompt .= "            \"arrival_time\": \"2025-07-04 16:00:00\",\n";
                $prompt .= "            \"duration_time\": \"2h 30m\",\n";
                $prompt .= "            \"country_id_to\": \"arrival country\",\n";
                $prompt .= "            \"airport_to\": \"arrival airport code\",\n";
                $prompt .= "            \"terminal_to\": \"arrival terminal\",\n";
                $prompt .= "            \"airline_id\": \"airline name\",\n";
                $prompt .= "            \"flight_number\": \"flight number\",\n";
                $prompt .= "            \"class_type\": \"economy/business/first\",\n";
                $prompt .= "            \"baggage_allowed\": \"baggage allowance\",\n";
                $prompt .= "            \"equipment\": \"aircraft type\",\n";
                $prompt .= "            \"ticket_number\": \"flight ticket number\",\n";
                $prompt .= "            \"flight_meal\": \"meal service\",\n";
                $prompt .= "            \"seat_no\": \"seat assignment\"\n";
                $prompt .= "          }\n";
                $prompt .= "        ],\n";
                $prompt .= "        \"task_hotel_details\": [\n";
                $prompt .= "          {\n";
                $prompt .= "            \"hotel_name\": \"hotel name\",\n";
                $prompt .= "            \"booking_time\": \"2025-07-04 10:00:00\",\n";
                $prompt .= "            \"check_in\": \"2025-07-04 15:00:00\",\n";
                $prompt .= "            \"check_out\": \"2025-07-06 11:00:00\",\n";
                $prompt .= "            \"room_reference\": \"room booking reference\",\n";
                $prompt .= "            \"room_number\": \"room number\",\n";
                $prompt .= "            \"room_type\": \"room type\",\n";
                $prompt .= "            \"room_amount\": 150.00,\n";
                $prompt .= "            \"room_details\": \"room details and amenities\",\n";
                $prompt .= "            \"room_promotion\": \"special offers or discounts\",\n";
                $prompt .= "            \"rate\": 150.00,\n";
                $prompt .= "            \"meal_type\": \"breakfast/half-board/full-board\",\n";
                $prompt .= "            \"is_refundable\": true,\n";
                $prompt .= "            \"supplements\": \"additional services\"\n";
                $prompt .= "          }\n";
                $prompt .= "        ],\n";
                $prompt .= "        \"task_insurance_details\": [\n";
                $prompt .= "          {\n";
                $prompt .= "            \"insurance_type\": \"Tr\",\n";
                $prompt .= "            \"destination\": \"Worldwide\",\n";
                $prompt .= "            \"plan_type\": \"Family Plan\",\n";
                $prompt .= "            \"duration\": \"Up to 30 days\",\n";
                $prompt .= "            \"package\": \"Worldwide (Silver) Plan\",\n";
                $prompt .= "            \"document_reference\": \"policy/certificate reference\",\n";
                $prompt .= "            \"date\": 2025,\n"; 
                $prompt .= "            \"paid_leaves\": 0,\n";
                $prompt .= "          }\n";
                $prompt .= "        ]\n";
                $prompt .= "      }\n";
                $prompt .= "    ]" . (array_search($fileName, array_keys($fileMetadata)) < count($fileMetadata) - 1 ? "," : "") . "\n";
            }
        }
        $prompt .= "  }\n";
        $prompt .= "}\n\n";
        
        $prompt .= "Remember: Always return an array of objects for each file, even for single passengers. ";
        $prompt .= "Analyze each document carefully for multiple bookings/passengers. ";
        $prompt .= "Look for cross-file relationships and correlations. ";
        $prompt .= "Group results by filename exactly as shown in the JSON structure above.";

        return $prompt;
    }

    /**
     * Extract data from multiple PDF files in batch.
     * 
     * @param array $fileIds Array of file IDs already uploaded to OpenAI
     * @return array Array containing results for each file
     */
    public function extractMultiplePdfFiles(array $fileIds): array
    {
        if (empty($fileIds)) {
            return [
                'status' => 'error',
                'message' => 'No file IDs provided for batch processing',
                'data' => []
            ];
        }

        $taskFields = TaskSchema::getSchema();
        $flightFields = TaskFlightSchema::getSchema();
        $hotelFields = TaskHotelSchema::getSchema();
        $insuranceFields = TaskInsuranceSchema::getSchema();

        $supplierList = Supplier::all()->pluck('name')->toArray();
        $supplierList = json_encode($supplierList);

        $airportList = json_encode(Airport::all()->toArray());

        // Build comprehensive prompt for batch PDF extraction
        $prompt = "You are an assistant for processing multiple uploaded PDF documents to extract structured travel booking data.\n\n";
        $prompt .= "Extract data following these models:\n\n";
        $prompt .= "1. `tasks` model with the following fields:\n";
        foreach ($taskFields as $field => $meta) {
            $prompt .= "   - `$field`: {$meta['desc']}\n";
        }
        $prompt .= "\n2. `task_flight_details` model (for flights) - THIS IS AN ARRAY that can contain multiple flight details:\n";
        foreach ($flightFields as $field => $meta) {
            $prompt .= "   - `$field`: {$meta['description']}\n";
        }
        $prompt .= "\n3. `task_hotel_details` model (for hotels) - THIS IS AN ARRAY that can contain multiple hotel details:\n";
        foreach ($hotelFields as $field => $meta) {
            $prompt .= "   - `$field`: {$meta['description']}\n";
        }
        $prompt .= "\n4. `task_insurance_details` model (for insurances) - THIS IS AN ARRAY that can contain multiple insurance details:\n";
        foreach ($insuranceFields as $field => $meta) {
            $prompt .= "   - `$field`: {$meta['description']}\n";
        }
        $prompt .= "\nINSURANCE TASK COLLAPSING RULE (CRITICAL):\n";
        $prompt .= "- Do NOT create additional tasks for spouse/children/relatives listed on the certificate.\n";
        $prompt .= "- Do NOT record or output any list of covered relatives/members. Ignore extra names.\n";
        $prompt .= "- Set client_name to the buyer/policyholder (name nearest to the policy header or explicitly labeled).\n";
        $prompt .= "- If currency symbols (e.g., KD, $, €) are found in the files, replace them with the proper ISO currency code (e.g., KWD, USD, EUR).\n";

        $prompt .= "\nIMPORTANT INSTRUCTIONS:\n";
        $prompt .= "- I am providing multiple PDF files for batch processing.\n";
        $prompt .= "- Each PDF may contain multiple passengers/bookings. Extract all passengers from each PDF.\n";
        $prompt .= "- Return a structured response with results grouped by file.\n";
        $prompt .= "- Each passenger should be a separate task object with their own ticket/booking details.\n";
        $prompt .= "- If multiple passengers share the same flight/booking, they may have the same flight details but different ticket numbers and passenger names.\n";
        $prompt .= "- Extract all available data, set missing fields to null.\n";
        $prompt .= "- All dates should be in 'Y-m-d H:i:s' format.\n";
        $prompt .= "- For supplier name, refer to this list: $supplierList\n";
        $prompt .= "- Airport codes should be matched against: $airportList\n";
        $prompt .= "- HOTEL MEAL/BOARD RULES:\n";
        $prompt .= "  • If the document mentions a meal plan (e.g., 'board', 'free breakfast', 'half board', 'full board'), copy the wording exactly as shown into task_hotel_details[*].meal_type.\n";
        $prompt .= "  • If you're unsure which room line it belongs to, include the phrase in tasks.additional_info instead.\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (SMILE HOLIDAYS):\n";
        $prompt .= "  • For Smile Holidays proforma/invoices that have a 'Pax' column, copy that value into tasks.additional_info, e.g., 'Pax: 1'.\n";
        $prompt .= "  • ADDITIONAL REQUESTS → ROOM DETAILS: If the document contains 'Additional Requests', 'Special Instructions', 'Remarks' or similar booking notes, append a concise version to task_hotel_details[*].room_details (for single-room bookings append to that room; for multi-room bookings, either repeat for each room or put it into tasks.additional_info with room labels).\n";
        $prompt .= "- Return the result in this JSON format:\n\n";

        $prompt .= "{\n";
        $prompt .= "  \"files\": {\n";
        foreach ($fileIds as $index => $fileId) {
            $prompt .= "    \"$fileId\": [\n";
            $prompt .= "      {\n";
            $prompt .= "        \"additional_info\": \"relevant booking info\",\n";
            $prompt .= "        \"ticket_number\": \"document/ticket number\",\n";
            $prompt .= "        \"gds_reference\": \"booking reference/PNR\",\n";
            $prompt .= "        \"airline_reference\": \"airline confirmation code\",\n";
            $prompt .= "        \"status\": \"issued/confirmed/cancelled/refunded\",\n";
            $prompt .= "        \"supplier_status\": \"same as status\",\n";
            $prompt .= "        \"refund_date\": \"2025-06-01 10:00:00\",\n";
            $prompt .= "        \"price\": 100.00,\n";
            $prompt .= "        \"exchange_currency\": \"KWD\",\n";
            $prompt .= "        \"original_price\": 100.00,\n";
            $prompt .= "        \"original_currency\": \"USD\",\n";
            $prompt .= "        \"total\": 115.00,\n";
            $prompt .= "        \"original_surcharge\": 10.00,\n";
            $prompt .= "        \"surcharge\": 10.00,\n";
            $prompt .= "        \"penalty_fee\": 0.00,\n";
            $prompt .= "        \"original_tax\": 5.00,\n";
            $prompt .= "        \"tax\": 5.00,\n";
            $prompt .= "        \"taxes_record\": \"tax breakdown if available\",\n";
            $prompt .= "        \"refund_charge\": 0.00,\n";
            $prompt .= "        \"reference\": \"main reference number\",\n";
            $prompt .= "        \"created_by\": \"agent/office code\",\n";
            $prompt .= "        \"issued_by\": \"issuing agent/office\",\n";
            $prompt .= "        \"type\": \"flight/hotel/package\",\n";
            $prompt .= "        \"agent_name\": \"agent name\",\n";
            $prompt .= "        \"agent_email\": \"agent email\",\n";
            $prompt .= "        \"agent_amadeus_id\": \"agent system id\",\n";
            $prompt .= "        \"client_name\": \"passenger/customer name\",\n";
            $prompt .= "        \"supplier_name\": \"supplier/vendor name\",\n";
            $prompt .= "        \"supplier_country\": \"supplier country\",\n";
            $prompt .= "        \"cancellation_policy\": \"cancellation terms\",\n";
            $prompt .= "        \"cancellation_deadline\": \"2025-06-01 10:00:00\",\n";
            $prompt .= "        \"venue\": \"service location\",\n";
            $prompt .= "        \"issued_date\": \"2025-07-03 00:00:00\",\n";
            $prompt .= "        \"task_flight_details\": [\n";
            $prompt .= "          {\n";
            $prompt .= "            \"farebase\": 20.00,\n";
            $prompt .= "            \"departure_time\": \"2025-07-03 14:00:00\",\n";
            $prompt .= "            \"country_id_from\": \"departure country\",\n";
            $prompt .= "            \"airport_from\": \"departure airport code\",\n";
            $prompt .= "            \"terminal_from\": \"departure terminal\",\n";
            $prompt .= "            \"arrival_time\": \"2025-07-03 16:00:00\",\n";
            $prompt .= "            \"duration_time\": \"2h 30m\",\n";
            $prompt .= "            \"country_id_to\": \"arrival country\",\n";
            $prompt .= "            \"airport_to\": \"arrival airport code\",\n";
            $prompt .= "            \"terminal_to\": \"arrival terminal\",\n";
            $prompt .= "            \"airline_id\": \"airline name\",\n";
            $prompt .= "            \"flight_number\": \"flight number\",\n";
            $prompt .= "            \"class_type\": \"economy/business/first\",\n";
            $prompt .= "            \"baggage_allowed\": \"baggage allowance\",\n";
            $prompt .= "            \"equipment\": \"aircraft type\",\n";
            $prompt .= "            \"ticket_number\": \"flight ticket number\",\n";
            $prompt .= "            \"flight_meal\": \"meal service\",\n";
            $prompt .= "            \"seat_no\": \"seat assignment\"\n";
            $prompt .= "          }\n";
            $prompt .= "        ],\n";
            $prompt .= "        \"task_hotel_details\": [\n";
            $prompt .= "          {\n";
            $prompt .= "            \"hotel_name\": \"hotel name\",\n";
            $prompt .= "            \"booking_time\": \"2025-07-03 10:00:00\",\n";
            $prompt .= "            \"check_in\": \"2025-07-03 15:00:00\",\n";
            $prompt .= "            \"check_out\": \"2025-07-05 11:00:00\",\n";
            $prompt .= "            \"room_reference\": \"room booking reference\",\n";
            $prompt .= "            \"room_number\": \"room number\",\n";
            $prompt .= "            \"room_type\": \"room type\",\n";
            $prompt .= "            \"room_amount\": 150.00,\n";
            $prompt .= "            \"room_details\": \"room details and amenities\",\n";
            $prompt .= "            \"room_promotion\": \"special offers or discounts\",\n";
            $prompt .= "            \"rate\": 150.00,\n";
            $prompt .= "            \"meal_type\": \"breakfast/half-board/full-board\",\n";
            $prompt .= "            \"is_refundable\": true,\n";
            $prompt .= "            \"supplements\": \"additional services\"\n";
            $prompt .= "          }\n";
            $prompt .= "        ],\n";
            $prompt .= "        \"task_insurance_details\": [\n";
            $prompt .= "          {\n";
            $prompt .= "            \"insurance_type\": \"Tr\",\n";
            $prompt .= "            \"destination\": \"Worldwide\",\n";
            $prompt .= "            \"plan_type\": \"Family Plan\",\n";
            $prompt .= "            \"duration\": \"Up to 30 days\",\n";
            $prompt .= "            \"package\": \"Worldwide (Silver) Plan\",\n";
            $prompt .= "            \"document_reference\": \"policy/certificate reference\",\n";
            $prompt .= "            \"date\": \"2025\",\n"; 
            $prompt .= "            \"paid_leaves\": 0,\n";
            $prompt .= "          }\n";
            $prompt .= "        ]\n";
            $prompt .= "      }\n";
            $prompt .= "    ]" . ($index < count($fileIds) - 1 ? "," : "") . "\n";
        }
        $prompt .= "  }\n";
        $prompt .= "}\n\n";
        $prompt .= "Remember: Always return an array of objects, even for single passengers. Analyze the document carefully for multiple bookings/passengers.";

        // Build content array with all file IDs and the prompt
        $content = [];
        
        // Add all file IDs to the content
        foreach ($fileIds as $fileId) {
            $content[] = [
                'type' => 'input_file',
                'file_id' => $fileId,
            ];
        }
        
        // Add the prompt
        $content[] = [
            'type' => 'input_text',
            'text' => $prompt,
        ];

        try {
            $response = $this->createResponse([
                'role' => 'user',
                'content' => $content,
            ]);

            Log::info('OpenAI Batch API response: ', $response);

            if (
                isset($response['output'][0]['content'][0]['text']) &&
                is_string($response['output'][0]['content'][0]['text'])
            ) {
                $message = $response['output'][0]['content'][0]['text'];
                $decodedResponse = json_decode($message, true);
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Failed to extract data from the batch response',
                    'data' => []
                ];
            }

            // Clean up - delete all uploaded files
            foreach ($fileIds as $fileId) {
                $this->deleteFileFromOpenAI($fileId);
            }

            // Ensure the response has the expected structure
            if (!isset($decodedResponse['files']) || !is_array($decodedResponse['files'])) {
                return [
                    'status' => 'error',
                    'message' => 'Invalid batch response format from AI',
                    'data' => []
                ];
            }

            // Process and validate each file's results
            $processedResults = [];
            foreach ($fileIds as $fileId) {
                if (!isset($decodedResponse['files'][$fileId])) {
                    $processedResults[$fileId] = [
                        'status' => 'error',
                        'message' => "No results found for file ID: $fileId",
                        'data' => []
                    ];
                    continue;
                }

                $fileResults = $decodedResponse['files'][$fileId];
                
                // Ensure it's an array
                if (!is_array($fileResults)) {
                    $fileResults = [$fileResults];
                }

                // Process each task for reference number validation
                foreach ($fileResults as &$task) {
                    // Validate and fix reference numbers if needed
                    if (!isset($task['reference']) || empty($task['reference'])) {
                        // Use ticket_number as reference if reference is missing
                        $task['reference'] = $task['ticket_number'] ?? '';
                    }

                    // Basic reference number validation for PDFs
                    if (!empty($task['reference'])) {
                        $checkResponse = $this->checkReferenceNumber($task['reference']);
                        if ($checkResponse['status'] === 'error') {
                            // For PDFs, if reference doesn't match air file format, keep it as is
                            Log::warning('PDF reference number format differs from air file format: ' . $task['reference']);
                        }
                    }
                }

                $processedResults[$fileId] = [
                    'status' => 'success',
                    'message' => 'Data extracted successfully',
                    'data' => $fileResults
                ];
            }

            return [
                'status' => 'success',
                'message' => 'Batch extraction completed',
                'data' => $processedResults
            ];

        } catch (Exception $e) {
            // Clean up files even if processing failed
            foreach ($fileIds as $fileId) {
                try {
                    $this->deleteFileFromOpenAI($fileId);
                } catch (Exception $deleteException) {
                    Log::warning("Failed to delete file $fileId during error cleanup: " . $deleteException->getMessage());
                }
            }

            Log::error('Batch PDF extraction failed: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Batch extraction failed: ' . $e->getMessage(),
                'data' => []
            ];
        }
    }

    public function uploadFileToOpenAI($file, string $purpose = 'user_data')
    {
        try {
            if ($file instanceof UploadedFile) {
                $filePath = $file->getRealPath();
                $fileName = $file->getClientOriginalName();
            } elseif (is_string($file)) {
                // Sanitize and validate string path
                $file = str_replace("\0", '', $file); // Remove null bytes
                $filePath = $file;
                $fileName = basename($file);

                if (!file_exists($filePath)) {
                    throw new \Exception("File not found at path: $filePath");
                }
            } else {
                throw new \InvalidArgumentException('Invalid file type. Expected UploadedFile or file path string.');
            }

            logger('Uploading to OpenAI', ['filePath' => $filePath, 'fileName' => $fileName]);

            $fileResource = fopen($filePath, 'r');

            $response = Http::withToken($this->apiKey)
                ->attach('file', $fileResource, $fileName)
                ->post($this->apiUrl . '/files', [
                    'purpose' => $purpose,
                ]);

            fclose($fileResource);

            logger('upload file response: ', $response->json());

            if ($response->failed()) {
                throw new \Exception('Error uploading file: ' . $response->body());
            }

            return $response->json('id');

        } catch (\Throwable $e) {
            logger()->error('Failed to upload file to OpenAI', ['error' => $e->getMessage()]);
            throw $e; // re-throw for handling elsewhere if needed
        }
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
