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

        // Helper to normalize data structure for all scenarios
        
        if ($extension === 'pdf') {
            $response = $this->extractPdfFiles($filePath);

            Log::info("extractPdfFiles response for {$fileName}: " . json_encode($response));

            if($response['status'] !== 'success') {
                $errorMessage = $response['message'] ?? 'Unknown error occurred.';
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

            $normalized =  TaskSchema::normalize(array_merge(
                $data['task']  ?? [],
                [
                    'task_flight_details' => $data['task_flight_details'] ?? [],
                    'task_hotel_details' => $data['task_hotel_details'] ?? [],
                ]
            ));

            // Determine type and normalize
            // $taskType = $data['task']['type'] ?? 'flight';
            // $normalized = $normalizeData(array_merge($data['task'] ?? [], [
            //     'task_flight_details' => $data['task_flight_details'] ?? [],
            //     'task_hotel_details' => $data['task_hotel_details'] ?? [],
            // ]), $taskType);
           
            return [
                'status' => 'success',
                'message' => "Successfully processed {$fileName} using AI.",
                'original_filename' => $fileName,
                'data' => $normalized,
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

                if(!is_array($extractedData)) {
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
        $prompt .= "\n2. `task_flight_details` model (for flights):\n";
        foreach ($flightFields as $field => $meta) {
            $prompt .= "   - `$field`: {$meta['description']}\n";
        }
        $prompt .= "\n3. `task_hotel_details` model (for hotels):\n";
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
                    'ticket_number': '3580878589', //example of ticket flight number with the airline code, 10-digit ticket number only
                }
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
                'cancellation_policy': '',
                'venue': '',
                'task_flight_details': {
                    // flight details here
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
            },
            {
                'additional_info': 'additional info',
                'ticket_number': '3580878590', //[3-digit airline code] - [10-digit ticket number] (only save the 10-digit ticket number),
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
        
        foreach($decodedResponse['result'] as $task){
            
            if(!isset($task['reference']) || empty($task['reference'])){
                
                $checkResponse = $this->getReferenceNumberFromFile([
                    'content' => $content,
                    'passenger_name' => $task['client_name'] ?? '',
                    'example' => $task['reference'] ?? [],
                ]);
                if($checkResponse['status'] === 'error'){
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

                if($getReferenceResponse['status'] === 'error') {
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
        if(!isset($data['passenger_name']) || empty($data['passenger_name'])){
            
            return [
                'status' => 'error',
                'message' => 'Passenger name is required to extract reference number',
                'data' => null,
            ];
        }

        if(!isset($data['content']) || empty($data['content'])){
            return [
                'status' => 'error',
                'message' => 'Content is required to extract reference number',
                'data' => null,
            ];
        }

        $prompt = " You extract the reference number which is the ticket number from the file, which is usually stated at the end of the line where the price is stated. The ticket number is usually 10 digits long, and it is usually preceded by a 3-digit airline code, so you can just take the last 10 digits as the ticket number.";

        $prompt .= " The reference number is usually like this: T-K229-2833133219, and it is usually preceded by a 3-digit airline code, so you can just take the last 10 digits as the ticket number. For example, if the ticket number is T-K229-2833133219, you can just use '2833133219' as the ticket number.";

        $prompt .= "If there is multiple reference numbers/ticket numbers, make sure you return for the correct passenger/client, i want for this passenger/client: ". $data['passenger_name'] .". ";

        $prompt .= "example response : {\"reference_number\": \"2833133219\"}";

        if(isset($data['example'])){
            $prompt .= " Here are some example reference numbers you can refer to: " . implode(', ', $data['example']);
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
            'task' => TaskSchema::getSchema(),
            'task_flight_details' => TaskFlightSchema::getSchema(),
            'task_hotel_details' => TaskHotelSchema::getSchema(),
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